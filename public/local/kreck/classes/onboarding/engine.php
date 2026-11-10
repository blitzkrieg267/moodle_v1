<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The KRECK onboarding / provisioning engine.
 *
 * Orchestrates native Moodle core APIs (core_course_category::create,
 * create_course, add_moduleinfo, user_create_user, enrol) to stand up a whole
 * institution from a manifest, with a dry-run preflight and a batch rollback.
 * It stores the batch manifest in plugin config (no custom tables).
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\onboarding;

defined('MOODLE_INTERNAL') || die();

/**
 * Provisioning engine.
 */
class engine {

    /** @var string Config plugin the batch manifests live under. */
    const PLUGIN = 'local_kreck';
    /** @var string Config name holding the comma-separated batch index. */
    const INDEX = 'onboarding_batches';

    /** @var string This run's batch id. */
    protected $batchid;

    /**
     * @param string|null $batchid Optional fixed id (else generated).
     */
    public function __construct(?string $batchid = null) {
        $this->batchid = $batchid ?: ('onboarding_batch_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -4)));
    }

    /**
     * The batch id for this run.
     *
     * @return string
     */
    public function get_batchid(): string {
        return $this->batchid;
    }

    // ------------------------------------------------------------------ Dry run.

    /**
     * Preflight validation — no writes. Returns a plan and any blocking errors.
     *
     * @param manifest $manifest
     * @return array ['ok'=>bool, 'plan'=>array, 'errors'=>string[], 'warnings'=>string[]]
     */
    public function validate(manifest $manifest): array {
        global $DB;

        $errors = [];
        $warnings = [];
        $plan = ['categories_create' => [], 'categories_exist' => [], 'courses' => [], 'users_create' => [],
            'users_exist' => [], 'modules_total' => 0, 'cohorts_create' => [], 'cohorts_exist' => [],
            'students_create' => 0];

        // Categories.
        foreach ($manifest->category_paths() as $path) {
            if ($this->find_category_id($path) !== null) {
                $plan['categories_exist'][] = $path;
            } else {
                $plan['categories_create'][] = $path;
            }
        }

        // Users.
        $declaredusers = [];
        foreach ($manifest->users() as $u) {
            $username = $u['username'] ?? ($u['email'] ?? '');
            $declaredusers[strtolower($u['email'] ?? '')] = true;
            if ($username === '') {
                $errors[] = get_string('onb_err_nouser', 'local_kreck');
                continue;
            }
            if ($DB->record_exists_select('user', 'username = :u AND deleted = 0', ['u' => $username])) {
                $plan['users_exist'][] = $username;
            } else {
                $plan['users_create'][] = $username;
            }
        }

        // Courses.
        foreach ($manifest->courses() as $c) {
            $shortname = trim($c['shortname'] ?? '');
            if ($shortname === '' || empty($c['fullname'])) {
                $errors[] = get_string('onb_err_coursefields', 'local_kreck');
                continue;
            }
            if ($DB->record_exists('course', ['shortname' => $shortname])) {
                $errors[] = get_string('onb_err_shortname', 'local_kreck', $shortname);
            }
            $modcount = 0;
            foreach ($manifest->effective_sections($c) as $section) {
                foreach ($section['modules'] ?? [] as $m) {
                    $type = module_factory::normalise_type($m['type'] ?? '');
                    if (!in_array($type, module_factory::SUPPORTED, true)) {
                        $warnings[] = get_string('onb_warn_module', 'local_kreck', $m['type'] ?? '?');
                        continue;
                    }
                    $modcount++;
                }
            }
            $plan['modules_total'] += $modcount;
            // Teachers not declared and not present will be auto-created.
            foreach ($c['teachers'] ?? [] as $temail) {
                $exists = $DB->record_exists_select('user', 'email = :e AND deleted = 0', ['e' => $temail]);
                if (!$exists && empty($declaredusers[strtolower($temail)])) {
                    $warnings[] = get_string('onb_warn_teacher', 'local_kreck', $temail);
                }
            }
            $plan['courses'][] = ['shortname' => $shortname, 'modules' => $modcount];
        }

        // Cohorts.
        foreach ($manifest->cohorts() as $cspec) {
            $label = $cspec['name'] ?? ($cspec['idnumber'] ?? '');
            if ($this->resolve_cohort($cspec['idnumber'] ?? ($cspec['name'] ?? ''), []) !== null) {
                $plan['cohorts_exist'][] = $label;
            } else {
                $plan['cohorts_create'][] = $label;
            }
        }

        // Students (explicit + generated).
        $plan['students_create'] = count($manifest->students());
        foreach ($manifest->generators() as $g) {
            $plan['students_create'] += max(0, (int) ($g['count'] ?? 0));
        }

        return ['ok' => empty($errors), 'plan' => $plan, 'errors' => $errors, 'warnings' => $warnings];
    }

    // --------------------------------------------------------------- Provision.

    /**
     * Execute the manifest. On any failure the partial batch is rolled back.
     *
     * @param manifest $manifest
     * @return array ['ok'=>bool, 'batchid'=>string, 'log'=>string[], 'record'=>array, 'error'=>?string]
     */
    public function provision(manifest $manifest): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/course/lib.php');

        // add_moduleinfo and file APIs need a real user context.
        if (!isloggedin() || isguestuser()) {
            \core\session\manager::set_user(get_admin());
        }

        $log = [];
        $record = ['batchid' => $this->batchid, 'timecreated' => time(),
            'categories' => [], 'users' => [], 'courses' => [], 'cms' => []];

        try {
            // 1. Categories.
            $catmap = [];
            foreach ($manifest->category_paths() as $path) {
                [$id, $created] = $this->ensure_category($path);
                $catmap[$path] = $id;
                if ($created) {
                    $record['categories'][] = $id;
                    $log[] = get_string('onb_log_catcreated', 'local_kreck', $path);
                }
            }

            // 2. Users (staff).
            foreach ($manifest->users() as $u) {
                [$uid, $created] = $this->ensure_user($u);
                if ($created) {
                    $record['users'][] = $uid;
                    $log[] = get_string('onb_log_usercreated', 'local_kreck', $u['username'] ?? $u['email']);
                }
            }

            // 2b. Cohorts (create + add listed members).
            $cohortmap = [];
            foreach ($manifest->cohorts() as $cspec) {
                [$cid, $created] = $this->ensure_cohort($cspec, $record);
                $cohortmap[$this->cohort_key($cspec)] = $cid;
                foreach ($cspec['members'] ?? [] as $login) {
                    $this->add_cohort_member($cid, $login);
                }
                if ($created) {
                    $log[] = get_string('onb_log_cohortcreated', 'local_kreck', $cspec['name'] ?? ($cspec['idnumber'] ?? ''));
                }
            }

            // 2c. Explicit student users.
            foreach ($manifest->students() as $s) {
                [$uid, $created] = $this->ensure_user($s);
                if ($created) {
                    $record['users'][] = $uid;
                }
                if (!empty($s['cohort'])) {
                    $cid = $this->resolve_cohort($s['cohort'], $cohortmap);
                    if ($cid) {
                        $this->add_cohort_member($cid, $s['username'] ?? $s['email']);
                    }
                }
            }

            // 2d. Bulk-generated student users.
            foreach ($manifest->generators() as $g) {
                $made = $this->generate_students($g, $cohortmap, $record);
                if ($made) {
                    $log[] = get_string('onb_log_studentsgenerated', 'local_kreck',
                        (object) ['count' => $made, 'prefix' => $g['prefix'] ?? 'student']);
                }
            }

            // 3. Courses.
            foreach ($manifest->courses() as $c) {
                $catid = $catmap[$c['category_path'] ?? ''] ?? $this->default_category_id();

                $coursedata = (object) [
                    'fullname' => $c['fullname'],
                    'shortname' => $c['shortname'],
                    'category' => $catid,
                    'format' => $c['format'] ?? 'weeks',
                    'numsections' => (int) ($c['numsections'] ?? 8),
                    'startdate' => !empty($c['startdate']) ? strtotime($c['startdate']) : time(),
                    'summary' => $c['summary'] ?? '',
                    'summaryformat' => FORMAT_HTML,
                    'visible' => 1,
                ];
                $course = create_course($coursedata);
                $record['courses'][] = (int) $course->id;
                $log[] = get_string('onb_log_coursecreated', 'local_kreck', $course->shortname);

                // Sections + modules.
                $sections = $manifest->effective_sections($c);
                $sectionnums = array_map(fn($s) => (int) ($s['section_num'] ?? 0), $sections);
                course_create_sections_if_missing($course, $sectionnums);

                foreach ($sections as $section) {
                    $num = (int) ($section['section_num'] ?? 0);
                    if (!empty($section['title'])) {
                        $this->name_section($course, $num, $section['title']);
                    }
                    foreach ($section['modules'] ?? [] as $m) {
                        $type = module_factory::normalise_type($m['type'] ?? '');
                        if (!in_array($type, module_factory::SUPPORTED, true)) {
                            continue;
                        }
                        $cmid = module_factory::create($course, $num, $m);
                        $record['cms'][] = $cmid;
                    }
                }

                // Enrol teachers.
                foreach ($c['teachers'] ?? [] as $temail) {
                    $this->enrol_teacher($course, $temail, $record);
                }

                // Bind cohorts to the course (cohort enrol as students).
                foreach ($c['cohorts'] ?? [] as $cref) {
                    $cid = $this->resolve_cohort($cref, $cohortmap);
                    if ($cid) {
                        $this->bind_cohort_to_course($course, $cid, 'student');
                    }
                }

                // Directly enrol any individually listed students.
                foreach ($c['students'] ?? [] as $login) {
                    $uid = $this->resolve_user_id($login);
                    if ($uid) {
                        $this->enrol_in_course($course, $uid, 'student');
                    }
                }

                rebuild_course_cache($course->id);
            }

            $this->store_batch($record);
            $log[] = get_string('onb_log_done', 'local_kreck', $this->batchid);
            return ['ok' => true, 'batchid' => $this->batchid, 'log' => $log, 'record' => $record, 'error' => null];

        } catch (\Throwable $e) {
            // Best-effort clean-up of the partial batch so no half state remains.
            $this->rollback_record($record);
            return ['ok' => false, 'batchid' => $this->batchid, 'log' => $log, 'record' => $record,
                'error' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------- Rollback.

    /**
     * Roll back a stored batch by id.
     *
     * @param string $batchid
     * @return array ['ok'=>bool, 'removed'=>array, 'error'=>?string]
     */
    public function rollback(string $batchid): array {
        $record = $this->load_batch($batchid);
        if ($record === null) {
            return ['ok' => false, 'removed' => [], 'error' => get_string('onb_err_nobatch', 'local_kreck', $batchid)];
        }
        $removed = $this->rollback_record($record);
        $this->forget_batch($batchid);
        return ['ok' => true, 'removed' => $removed, 'error' => null];
    }

    /**
     * Delete everything named in a batch record, in reverse dependency order.
     *
     * @param array $record
     * @return array counts removed
     */
    protected function rollback_record(array $record): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $removed = ['courses' => 0, 'users' => 0, 'cohorts' => 0, 'categories' => 0];

        // Courses (this also removes their modules).
        foreach ($record['courses'] ?? [] as $cid) {
            if ($DB->record_exists('course', ['id' => $cid])) {
                delete_course($cid, false);
                $removed['courses']++;
            }
        }

        // Users (soft delete, core API).
        foreach ($record['users'] ?? [] as $uid) {
            $user = $DB->get_record('user', ['id' => $uid, 'deleted' => 0]);
            if ($user) {
                delete_user($user);
                $removed['users']++;
            }
        }

        // Cohorts.
        if (!empty($record['cohorts'])) {
            require_once($CFG->dirroot . '/cohort/lib.php');
            foreach ($record['cohorts'] as $cohortid) {
                $cohort = $DB->get_record('cohort', ['id' => $cohortid]);
                if ($cohort) {
                    cohort_delete_cohort($cohort);
                    $removed['cohorts'] = ($removed['cohorts'] ?? 0) + 1;
                }
            }
        }

        // Categories, children before parents (reverse of creation order).
        foreach (array_reverse($record['categories'] ?? []) as $catid) {
            try {
                $cat = \core_course_category::get($catid, IGNORE_MISSING, true);
                if ($cat) {
                    $cat->delete_full(false);
                    $removed['categories']++;
                }
            } catch (\Throwable $e) {
                // Category already gone or not empty; skip.
                continue;
            }
        }

        return $removed;
    }

    // ----------------------------------------------------------- Batch storage.

    /**
     * Persist a batch record in plugin config and index it.
     *
     * @param array $record
     */
    protected function store_batch(array $record): void {
        set_config('batch_' . $record['batchid'], json_encode($record), self::PLUGIN);
        $index = array_filter(explode(',', (string) get_config(self::PLUGIN, self::INDEX)));
        $index[] = $record['batchid'];
        set_config(self::INDEX, implode(',', array_unique($index)), self::PLUGIN);
    }

    /**
     * Load a stored batch record.
     *
     * @param string $batchid
     * @return array|null
     */
    protected function load_batch(string $batchid): ?array {
        $json = get_config(self::PLUGIN, 'batch_' . $batchid);
        if (!$json) {
            return null;
        }
        $record = json_decode($json, true);
        return is_array($record) ? $record : null;
    }

    /**
     * Remove a batch from config and the index.
     *
     * @param string $batchid
     */
    protected function forget_batch(string $batchid): void {
        unset_config('batch_' . $batchid, self::PLUGIN);
        $index = array_filter(explode(',', (string) get_config(self::PLUGIN, self::INDEX)));
        $index = array_values(array_diff($index, [$batchid]));
        set_config(self::INDEX, implode(',', $index), self::PLUGIN);
    }

    /**
     * List known batch ids, newest first.
     *
     * @return array of ['batchid','timecreated','courses','users','categories']
     */
    public function list_batches(): array {
        $index = array_filter(explode(',', (string) get_config(self::PLUGIN, self::INDEX)));
        $out = [];
        foreach ($index as $batchid) {
            $record = $this->load_batch($batchid);
            if ($record) {
                $out[] = [
                    'batchid' => $batchid,
                    'timecreated' => $record['timecreated'] ?? 0,
                    'courses' => count($record['courses'] ?? []),
                    'users' => count($record['users'] ?? []),
                    'categories' => count($record['categories'] ?? []),
                ];
            }
        }
        usort($out, static fn($a, $b) => $b['timecreated'] <=> $a['timecreated']);
        return $out;
    }

    // ------------------------------------------------------------- DB helpers.

    /**
     * Find a category id by " A / B / C " path, or null.
     *
     * @param string $path
     * @return int|null
     */
    protected function find_category_id(string $path): ?int {
        global $DB;
        $parts = array_map('trim', explode('/', $path));
        $parentid = 0;
        foreach ($parts as $name) {
            $id = $DB->get_field('course_categories', 'id', ['name' => $name, 'parent' => $parentid]);
            if (!$id) {
                return null;
            }
            $parentid = $id;
        }
        return (int) $parentid;
    }

    /**
     * Ensure a category path exists, creating missing levels.
     *
     * @param string $path
     * @return array [int id, bool createdAnyLevel]
     */
    protected function ensure_category(string $path): array {
        global $DB;
        $parts = array_map('trim', explode('/', $path));
        $parentid = 0;
        $created = false;
        foreach ($parts as $name) {
            $id = $DB->get_field('course_categories', 'id', ['name' => $name, 'parent' => $parentid]);
            if (!$id) {
                $cat = \core_course_category::create((object) ['name' => $name, 'parent' => $parentid]);
                $id = $cat->id;
                $created = true;
            }
            $parentid = (int) $id;
        }
        return [$parentid, $created];
    }

    /**
     * Default category to drop courses into when none is given.
     *
     * @return int
     */
    protected function default_category_id(): int {
        $default = \core_course_category::get_default();
        return $default ? (int) $default->id : 1;
    }

    /**
     * Ensure a user exists.
     *
     * @param array $u
     * @return array [int id, bool created]
     */
    protected function ensure_user(array $u): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        $username = $u['username'] ?? ($u['email'] ?? '');
        $existing = $DB->get_record_select('user', 'username = :u AND deleted = 0', ['u' => $username]);
        if ($existing) {
            return [(int) $existing->id, false];
        }

        $new = (object) [
            'username' => $username,
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'firstname' => $u['firstname'] ?? 'KRECK',
            'lastname' => $u['lastname'] ?? 'User',
            'email' => $u['email'] ?? ($username . '@example.invalid'),
        ];
        $id = user_create_user($new, false, false);
        return [(int) $id, true];
    }

    /**
     * Set a course section's name.
     *
     * @param \stdClass $course
     * @param int $num
     * @param string $title
     */
    protected function name_section(\stdClass $course, int $num, string $title): void {
        global $DB;
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $num]);
        if ($section) {
            course_update_section($course, $section, (object) ['name' => $title]);
        }
    }

    /**
     * Enrol a teacher (by email) as editing teacher, creating a stub if needed.
     *
     * @param \stdClass $course
     * @param string $email
     * @param array $record Batch record (to track an auto-created user).
     */
    protected function enrol_teacher(\stdClass $course, string $email, array &$record): void {
        global $DB;

        $user = $DB->get_record_select('user', 'email = :e AND deleted = 0', ['e' => $email]);
        if (!$user) {
            [$uid, $created] = $this->ensure_user(['username' => $email, 'email' => $email,
                'firstname' => 'Teacher', 'lastname' => ucfirst(strtok($email, '@'))]);
            if ($created) {
                $record['users'][] = $uid;
            }
            $user = $DB->get_record('user', ['id' => $uid]);
        }

        $this->enrol_in_course($course, (int) $user->id, 'editingteacher');
    }

    /**
     * Manually enrol a user into a course with a role (by archetype/shortname).
     *
     * @param \stdClass $course
     * @param int $userid
     * @param string $roleshort
     */
    protected function enrol_in_course(\stdClass $course, int $userid, string $roleshort): void {
        global $DB;
        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        if (!$instance) {
            $iid = $plugin->add_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $iid]);
        }
        if ((int) $instance->status !== ENROL_INSTANCE_ENABLED) {
            $plugin->update_status($instance, ENROL_INSTANCE_ENABLED);
        }
        $roleid = $DB->get_field('role', 'id', ['shortname' => $roleshort]);
        $plugin->enrol_user($instance, $userid, $roleid);
    }

    /**
     * A stable key for a cohort spec (idnumber preferred, else name).
     *
     * @param array $cspec
     * @return string
     */
    protected function cohort_key(array $cspec): string {
        return !empty($cspec['idnumber']) ? (string) $cspec['idnumber'] : (string) ($cspec['name'] ?? '');
    }

    /**
     * Ensure a cohort exists in the system context.
     *
     * @param array $cspec
     * @param array $record
     * @return array [int id, bool created]
     */
    protected function ensure_cohort(array $cspec, array &$record): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $context = \context_system::instance();
        $idnumber = (string) ($cspec['idnumber'] ?? '');
        $name = (string) ($cspec['name'] ?? $idnumber);

        $existing = null;
        if ($idnumber !== '') {
            $existing = $DB->get_record('cohort', ['idnumber' => $idnumber]);
        }
        if (!$existing && $name !== '') {
            $existing = $DB->get_record('cohort', ['name' => $name, 'contextid' => $context->id]);
        }
        if ($existing) {
            return [(int) $existing->id, false];
        }

        $cohort = (object) [
            'contextid' => $context->id,
            'name' => $name,
            'idnumber' => $idnumber,
            'visible' => 1,
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
        ];
        $cid = cohort_add_cohort($cohort);
        $record['cohorts'][] = (int) $cid;
        return [(int) $cid, true];
    }

    /**
     * Resolve a cohort reference (idnumber or name) to an id, or null.
     *
     * @param string $ref
     * @param array $map Key => id from this run.
     * @return int|null
     */
    protected function resolve_cohort(string $ref, array $map): ?int {
        global $DB;
        if ($ref === '') {
            return null;
        }
        if (isset($map[$ref])) {
            return (int) $map[$ref];
        }
        $id = $DB->get_field('cohort', 'id', ['idnumber' => $ref])
            ?: $DB->get_field('cohort', 'id', ['name' => $ref]);
        return $id ? (int) $id : null;
    }

    /**
     * Add a user (by login or email) to a cohort, idempotently.
     *
     * @param int $cohortid
     * @param string $login
     */
    protected function add_cohort_member(int $cohortid, string $login): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $uid = $this->resolve_user_id($login);
        if ($uid && !$DB->record_exists('cohort_members', ['cohortid' => $cohortid, 'userid' => $uid])) {
            cohort_add_member($cohortid, $uid);
        }
    }

    /**
     * Bind a cohort to a course via the cohort enrol plugin and sync members now.
     *
     * @param \stdClass $course
     * @param int $cohortid
     * @param string $roleshort
     */
    protected function bind_cohort_to_course(\stdClass $course, int $cohortid, string $roleshort): void {
        global $CFG, $DB;
        $plugin = enrol_get_plugin('cohort');
        if (!$plugin) {
            return;
        }
        $roleid = $DB->get_field('role', 'id', ['shortname' => $roleshort]);
        if (!$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohortid])) {
            $plugin->add_instance($course, ['customint1' => $cohortid, 'roleid' => $roleid]);
        }
        // Enrol the cohort's members immediately rather than waiting for cron.
        require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
        enrol_cohort_sync(new \null_progress_trace(), $course->id);
    }

    /**
     * Bulk-create students from a generator spec, optionally adding to a cohort.
     *
     * @param array $g
     * @param array $map Cohort key => id.
     * @param array $record
     * @return int Number of students created.
     */
    protected function generate_students(array $g, array $map, array &$record): int {
        $count = max(0, (int) ($g['count'] ?? 0));
        $prefix = (string) ($g['prefix'] ?? 'student');
        $cohortid = !empty($g['cohort']) ? $this->resolve_cohort($g['cohort'], $map) : null;
        $domain = (string) ($g['emaildomain'] ?? 'example.invalid');
        $firstname = (string) ($g['firstname'] ?? 'Student');
        $lastname = (string) ($g['lastname'] ?? 'Learner');

        $made = 0;
        for ($i = 1; $i <= $count; $i++) {
            $num = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $username = $prefix . $num;
            [$uid, $created] = $this->ensure_user([
                'username' => $username,
                'firstname' => $firstname,
                'lastname' => $lastname . ' ' . $num,
                'email' => $username . '@' . $domain,
            ]);
            if ($created) {
                $record['users'][] = $uid;
                $made++;
            }
            if ($cohortid) {
                $this->add_cohort_member($cohortid, $username);
            }
        }
        return $made;
    }

    /**
     * Resolve a user id by username, then email.
     *
     * @param string $login
     * @return int|null
     */
    protected function resolve_user_id(string $login): ?int {
        global $DB;
        $id = $DB->get_field_select('user', 'id', 'username = :u AND deleted = 0', ['u' => $login])
            ?: $DB->get_field_select('user', 'id', 'email = :e AND deleted = 0', ['e' => $login]);
        return $id ? (int) $id : null;
    }
}
