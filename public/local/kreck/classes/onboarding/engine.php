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
 * Orchestrates native LMS core APIs (core_course_category::create,
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

    /** @var string Starter password applied to created users ('' = none). */
    protected $defaultpassword = '';

    /** @var bool Force a password change at first login. */
    protected $forcepasswordchange = true;

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

    /**
     * Use the source site's timezone, or LMS's configured server timezone
     * for older manifests that do not carry one.
     *
     * @param manifest $manifest
     * @return string
     */
    protected function manifest_timezone(manifest $manifest): string {
        $timezone = trim((string) ($manifest->meta()['timezone'] ?? ''));
        if ($timezone === '') {
            $timezone = (string) \core_date::get_server_timezone();
        }
        new \DateTimeZone($timezone);
        return $timezone;
    }

    /**
     * Validate schedule and assignment date fields before any writes occur.
     *
     * @param array $course
     * @param string $timezone
     * @param array $errors
     */
    protected function validate_course_dates(array $course, string $timezone, array &$errors): void {
        $shortname = (string) ($course['shortname'] ?? '(unnamed course)');
        try {
            $start = !empty($course['startdate'])
                ? module_factory::timestamp((string) $course['startdate'], '00:00:00', $timezone, 'course start date')
                : null;
            $end = !empty($course['enddate'])
                ? module_factory::timestamp((string) $course['enddate'], '23:59:59', $timezone, 'course end date')
                : null;
            if ($start !== null && $end !== null && $end < $start) {
                $errors[] = "Course {$shortname} ends before it starts.";
            }
        } catch (\moodle_exception $e) {
            $errors[] = "Course {$shortname}: " . $e->getMessage();
        }

        foreach ($course['schedule'] ?? [] as $index => $session) {
            try {
                $start = module_factory::timestamp(
                    (string) ($session['date'] ?? ''),
                    (string) ($session['start_time'] ?? ''),
                    $timezone,
                    'timetable start'
                );
                $end = module_factory::timestamp(
                    (string) ($session['date'] ?? ''),
                    (string) ($session['end_time'] ?? ''),
                    $timezone,
                    'timetable end'
                );
                if ($end <= $start) {
                    $errors[] = "Course {$shortname} timetable row " . ($index + 1) . " ends before or at its start.";
                }
            } catch (\moodle_exception $e) {
                $errors[] = "Course {$shortname} timetable row " . ($index + 1) . ': ' . $e->getMessage();
            }
        }

        foreach ($course['assignments'] ?? [] as $index => $assignment) {
            $label = (string) ($assignment['name'] ?? ('assignment ' . ($index + 1)));
            if ((int) ($assignment['section_num'] ?? 0) < 0) {
                $errors[] = "Course {$shortname}, {$label}: section number cannot be negative.";
            }
            if ((float) ($assignment['grade'] ?? 0) <= 0) {
                $errors[] = "Course {$shortname}, {$label}: maximum grade must be positive.";
            }
            try {
                $open = $this->validate_optional_pair($assignment, 'open_date', 'open_time', $timezone, 'opening date/time');
                $due = $this->validate_optional_pair($assignment, 'due_date', 'due_time', $timezone, 'due date/time', true);
                $cutoff = $this->validate_optional_pair($assignment, 'cutoff_date', 'cutoff_time', $timezone, 'cutoff date/time');
                if ($open !== null && $due !== null && $open >= $due) {
                    $errors[] = "Course {$shortname}, {$label}: opening date/time must be before the due date/time.";
                }
                if ($cutoff !== null && $due !== null && $cutoff < $due) {
                    $errors[] = "Course {$shortname}, {$label}: cutoff date/time cannot be before the due date/time.";
                }
            } catch (\moodle_exception $e) {
                $errors[] = "Course {$shortname}, {$label}: " . $e->getMessage();
            }
        }
    }

    /**
     * Validate and convert an optional date/time pair.
     *
     * @param array $values
     * @param string $datekey
     * @param string $timekey
     * @param string $timezone
     * @param string $label
     * @param bool $required
     * @return int|null
     */
    protected function validate_optional_pair(
        array $values,
        string $datekey,
        string $timekey,
        string $timezone,
        string $label,
        bool $required = false
    ): ?int {
        $date = trim((string) ($values[$datekey] ?? ''));
        $time = trim((string) ($values[$timekey] ?? ''));
        if ($date === '' && $time === '' && !$required) {
            return null;
        }
        if ($date === '' || $time === '') {
            throw new \moodle_exception('onb_invalidschedule', 'local_kreck', '', $label);
        }
        return module_factory::timestamp($date, $time, $timezone, $label);
    }

    /**
     * Create native LMS course calendar events for dated SMS timetable rows.
     *
     * @param \stdClass $course
     * @param array $schedule
     * @param string $timezone
     * @return int[] IDs of calendar events created.
     */
    protected function create_schedule_events(\stdClass $course, array $schedule, string $timezone): array {
        global $CFG, $DB;
        if (empty($schedule)) {
            return [];
        }

        require_once($CFG->dirroot . '/calendar/lib.php');
        $created = [];
        $existingevents = $DB->get_records('event', [
            'courseid' => $course->id,
            'eventtype' => 'course',
        ], '', 'id,name,timestart,timeduration,location');
        foreach ($schedule as $session) {
            $date = (string) ($session['date'] ?? '');
            $start = module_factory::timestamp($date, (string) ($session['start_time'] ?? ''), $timezone, 'timetable start');
            $end = module_factory::timestamp($date, (string) ($session['end_time'] ?? ''), $timezone, 'timetable end');
            if ($end <= $start) {
                throw new \moodle_exception('onb_invalidschedule', 'local_kreck', '', 'timetable end');
            }

            $period = trim((string) ($session['period'] ?? 'Class'));
            $room = trim((string) ($session['room'] ?? ''));
            $event = new \stdClass();
            $event->name = format_string($course->fullname . ' - ' . $period);
            $event->description = '<p>' . s((string) ($session['day'] ?? '')) . '</p>';
            $event->format = FORMAT_HTML;
            $event->course = (int) $course->id;
            $event->courseid = (int) $course->id;
            $event->groupid = 0;
            $event->userid = 0;
            $event->modulename = '';
            $event->instance = 0;
            $event->eventtype = 'course';
            $event->type = CALENDAR_EVENT_TYPE_STANDARD;
            $event->timestart = $start;
            $event->timeduration = $end - $start;
            $event->visible = 1;
            $event->location = $room;
            $duplicate = false;
            foreach ($existingevents as $existingevent) {
                if ($existingevent->name === $event->name
                        && (int) $existingevent->timestart === $start
                        && (int) $existingevent->timeduration === (int) $event->timeduration
                        && (string) ($existingevent->location ?? '') === $room) {
                    $duplicate = true;
                    break;
                }
            }
            if ($duplicate) {
                continue;
            }
            $createdevent = \calendar_event::create($event, false);
            $created[] = (int) $createdevent->id;
            $existingevents[] = (object) [
                'name' => $event->name,
                'timestart' => $start,
                'timeduration' => (int) $event->timeduration,
                'location' => $room,
            ];
        }

        return $created;
    }

    // ------------------------------------------------------------------ Dry run.

    /**
     * Preflight validation — no writes. Returns a plan and any blocking errors.
     *
     * @param manifest $manifest
     * @return array ['ok'=>bool, 'plan'=>array, 'errors'=>string[], 'warnings'=>string[]]
     */
    public function validate(manifest $manifest, bool $mergeexistingcourses = false): array {
        global $DB;

        $errors = [];
        $warnings = [];
        $timezone = '';
        try {
            $timezone = $this->manifest_timezone($manifest);
        } catch (\Exception $e) {
            $errors[] = get_string('onb_invalidtimezone', 'local_kreck', (string) ($manifest->meta()['timezone'] ?? ''));
        }
        $plan = ['categories_create' => [], 'categories_exist' => [], 'courses' => [], 'courses_existing' => [],
            'users_create' => [], 'users_exist' => [], 'users_auto_create' => [], 'modules_total' => 0,
            'modules_existing' => 0, 'calendar_events' => 0, 'calendar_events_existing' => 0,
            'cohorts_create' => [], 'cohorts_exist' => [], 'students_create' => 0,
            'students_exist' => [], 'enrolments' => 0];

        // Categories.
        foreach ($manifest->category_paths() as $path) {
            if ($this->find_category_id($path) !== null) {
                $plan['categories_exist'][] = $path;
            } else {
                $plan['categories_create'][] = $path;
            }
        }

        // Declared users (used to distinguish explicit teachers from teacher stubs).
        $declaredusers = [];
        $declaredusernames = [];
        foreach (array_merge($manifest->users(), $manifest->students()) as $declareduser) {
            $username = strtolower(trim((string) ($declareduser['username'] ?? ($declareduser['email'] ?? ''))));
            $email = strtolower(trim((string) ($declareduser['email'] ?? '')));
            if ($username !== '') {
                $declaredusernames[$username] = true;
            }
            if ($email !== '') {
                $declaredusers[$email] = true;
            }
        }

        // Staff users.
        $seenusernames = [];
        foreach ($manifest->users() as $u) {
            $username = trim((string) ($u['username'] ?? ($u['email'] ?? '')));
            if ($username === '') {
                $errors[] = get_string('onb_err_nouser', 'local_kreck');
                continue;
            }
            if (isset($seenusernames[strtolower($username)])) {
                $errors[] = get_string('onb_err_duplicateuser', 'local_kreck', $username);
                continue;
            }
            $seenusernames[strtolower($username)] = true;
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
            $existingcourse = $DB->get_record('course', ['shortname' => $shortname], 'id,shortname', IGNORE_MISSING);
            if ($existingcourse) {
                $plan['courses_existing'][] = $shortname;
                if (!$mergeexistingcourses) {
                    $errors[] = get_string('onb_err_shortname', 'local_kreck', $shortname);
                }
            } else {
                $plan['courses'][] = $shortname;
            }
            if ($timezone !== '') {
                $this->validate_course_dates($c, $timezone, $errors);
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
            $modcount += count($c['assignments'] ?? []);
            if ($existingcourse && $mergeexistingcourses) {
                $existingmodules = $this->course_module_index(get_course((int) $existingcourse->id));
                $missingcount = 0;
                foreach ($manifest->effective_sections($c) as $section) {
                    $sectionnum = (int) ($section['section_num'] ?? 0);
                    foreach ($section['modules'] ?? [] as $module) {
                        $type = module_factory::normalise_type($module['type'] ?? '');
                        if (!in_array($type, module_factory::SUPPORTED, true)) {
                            continue;
                        }
                        $key = $this->module_match_key($sectionnum, $type, (string) ($module['name'] ?? ucfirst($type)));
                        if (!isset($existingmodules[$key])) {
                            $missingcount++;
                            $existingmodules[$key] = true;
                        }
                    }
                }
                foreach ($c['assignments'] ?? [] as $assignment) {
                    $sectionnum = (int) ($assignment['section_num'] ?? 0);
                    $key = $this->module_match_key($sectionnum, 'assign', (string) ($assignment['name'] ?? 'Assignment'));
                    if (!isset($existingmodules[$key])) {
                        $missingcount++;
                        $existingmodules[$key] = true;
                    }
                }
                $plan['modules_existing'] += $missingcount;
                $plan['calendar_events_existing'] += count($c['schedule'] ?? []);
            } else {
                $plan['modules_total'] += $modcount;
                $plan['calendar_events'] += count($c['schedule'] ?? []);
            }
            // Teachers not declared and not present will be auto-created.
            foreach ($c['teachers'] ?? [] as $temail) {
                $exists = $DB->record_exists_select('user', 'email = :e AND deleted = 0', ['e' => $temail]);
                if (!$exists && empty($declaredusers[strtolower($temail)])) {
                    $warnings[] = get_string('onb_warn_teacher', 'local_kreck', $temail);
                    $usernameexists = $DB->record_exists_select('user', 'username = :u AND deleted = 0', ['u' => $temail]);
                    if (!$usernameexists && empty($declaredusernames[strtolower($temail)])) {
                        $plan['users_auto_create'][strtolower($temail)] = true;
                    }
                }
            }
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

        // Explicit students (the plan separates new accounts from existing ones).
        foreach ($manifest->students() as $student) {
            $username = trim((string) ($student['username'] ?? ($student['email'] ?? '')));
            if ($username === '') {
                $errors[] = get_string('onb_err_nouser', 'local_kreck');
                continue;
            }
            if (isset($seenusernames[strtolower($username)])) {
                $errors[] = get_string('onb_err_duplicateuser', 'local_kreck', $username);
                continue;
            }
            $seenusernames[strtolower($username)] = true;
            if ($DB->record_exists_select('user', 'username = :u AND deleted = 0', ['u' => $username])) {
                $plan['students_exist'][] = $username;
            } else {
                $plan['students_create']++;
            }
        }

        // Bulk-generated student users.
        foreach ($manifest->generators() as $g) {
            $plan['students_create'] += max(0, (int) ($g['count'] ?? 0));
        }

        // Enrolments.
        $plan['enrolments'] = 0;
        $manifestshortnames = array_map(static fn($c) => trim((string) ($c['shortname'] ?? '')), $manifest->courses());
        foreach ($manifest->enrolments() as $e) {
            $plan['enrolments']++;
            $coursekey = trim((string) ($e['course'] ?? ''));
            if (trim((string) ($e['user'] ?? '')) === '' || $coursekey === '') {
                $errors[] = get_string('onb_err_enrolfields', 'local_kreck');
                continue;
            }
            $courseexists = $DB->record_exists('course', ['shortname' => $coursekey])
                || in_array($coursekey, $manifestshortnames, true);
            if (!$courseexists) {
                $errors[] = get_string('onb_err_enrolnocourse', 'local_kreck', $coursekey);
            }
        }

        if ($mergeexistingcourses && !empty($plan['courses_existing'])) {
            $warnings[] = 'Matching courses are reused by exact shortname. Existing course settings and matching activities are preserved; only missing named activities and enrolments are added.';
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
    public function provision(manifest $manifest, bool $mergeexistingcourses = false): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/course/lib.php');

        // add_moduleinfo and file APIs need a real user context.
        if (!isloggedin() || isguestuser()) {
            \core\session\manager::set_user(get_admin());
        }

        // Resolve the starter password: manifest wins, else the plugin setting.
        $this->defaultpassword = (string) ($manifest->default_password()
            ?? (get_config('local_kreck', 'defaultpassword') ?: ''));
        $forcemanifest = $manifest->force_password_change();
        $this->forcepasswordchange = $forcemanifest !== null
            ? (bool) $forcemanifest
            : (get_config('local_kreck', 'forcepasswordchange') !== '0');

        $log = [];
        $record = ['batchid' => $this->batchid, 'timecreated' => time(),
            'categories' => [], 'users' => [], 'cohorts' => [], 'courses' => [], 'existingcourses' => [],
            'cms' => [], 'coursemap' => [], 'enrolments' => [], 'calendar_events' => 0,
            'mergeCms' => [], 'mergeEvents' => []];
        $suppressoutbound = ($manifest->meta()['source'] ?? '') === 'Kreck Bridge';
        if ($suppressoutbound) {
            \local_kreck\sync\observer::suspend();
        }

        try {
            $timezone = $this->manifest_timezone($manifest);
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
                // Persist the cross-system identity link (external SIS student ID
                // -> LMS userid). This is the join key that lets results/grades
                // round-trip back to the right person in the SIS (e.g. SMS).
                if (!empty($s['studentid'])) {
                    if ($this->ensure_student_record($uid, (string) $s['studentid'], $s)) {
                        $record['students'][] = $uid;
                    }
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
                $shortname = trim((string) $c['shortname']);
                $existingcourse = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING);
                if ($existingcourse && !$mergeexistingcourses) {
                    throw new \moodle_exception('onb_err_shortname', 'local_kreck', '', $shortname);
                }
                $catid = $catmap[$c['category_path'] ?? ''] ?? $this->default_category_id();
                $startdate = !empty($c['startdate'])
                    ? module_factory::timestamp((string) $c['startdate'], '00:00:00', $timezone, 'course start date')
                    : time();
                $enddate = !empty($c['enddate'])
                    ? module_factory::timestamp((string) $c['enddate'], '23:59:59', $timezone, 'course end date')
                    : 0;

                $coursedata = (object) [
                    'fullname' => $c['fullname'],
                    'shortname' => $c['shortname'],
                    'category' => $catid,
                    'format' => $c['format'] ?? 'weeks',
                    'numsections' => (int) ($c['numsections'] ?? 8),
                    'startdate' => $startdate,
                    'enddate' => $enddate,
                    'summary' => $c['summary'] ?? '',
                    'summaryformat' => FORMAT_HTML,
                    'visible' => 1,
                ];
                if ($existingcourse) {
                    $course = get_course((int) $existingcourse->id);
                    $record['existingcourses'][] = (int) $course->id;
                    $log[] = 'Reusing existing course ' . $course->shortname . '; existing course details and matching content are preserved.';
                } else {
                    $course = create_course($coursedata);
                    $record['courses'][] = (int) $course->id;
                }
                // shortname -> courseid map, so the LMS->SMS links/marks export
                // can reference the created courses. (Keeps the flat list above for rollback.)
                $record['coursemap'][(string) $course->shortname] = (int) $course->id;
                if (!$existingcourse) {
                    $log[] = get_string('onb_log_coursecreated', 'local_kreck', $course->shortname);
                }
                $eventids = $this->create_schedule_events($course, $c['schedule'] ?? [], $timezone);
                $record['calendar_events'] += count($eventids);
                if ($existingcourse) {
                    $record['mergeEvents'] = array_merge($record['mergeEvents'], $eventids);
                }
                if (!empty($eventids)) {
                    $log[] = get_string('onb_log_calendareventscreated', 'local_kreck', count($eventids));
                }

                // Sections + modules.
                $sections = $manifest->effective_sections($c);
                $sectionnums = array_map(fn($s) => (int) ($s['section_num'] ?? 0), $sections);
                foreach ($c['assignments'] ?? [] as $assignment) {
                    $sectionnums[] = max(0, (int) ($assignment['section_num'] ?? 0));
                }
                course_create_sections_if_missing($course, $sectionnums);

                $existingmodules = $existingcourse ? $this->course_module_index($course) : [];
                foreach ($sections as $section) {
                    $num = (int) ($section['section_num'] ?? 0);
                    if (!$existingcourse && !empty($section['title'])) {
                        $this->name_section($course, $num, $section['title']);
                    }
                    foreach ($section['modules'] ?? [] as $m) {
                        $type = module_factory::normalise_type($m['type'] ?? '');
                        if (!in_array($type, module_factory::SUPPORTED, true)) {
                            continue;
                        }
                        $key = $this->module_match_key($num, $type, (string) ($m['name'] ?? ucfirst($type)));
                        if ($existingcourse && isset($existingmodules[$key])) {
                            continue;
                        }
                        $cmid = module_factory::create($course, $num, $m);
                        if ($existingcourse) {
                            $record['mergeCms'][] = $cmid;
                        } else {
                            $record['cms'][] = $cmid;
                        }
                        $existingmodules[$key] = true;
                    }
                }
                foreach ($c['assignments'] ?? [] as $assignment) {
                    $sectionnum = (int) ($assignment['section_num'] ?? 0);
                    $assignment['timezone'] = $timezone;
                    $key = $this->module_match_key($sectionnum, 'assign', (string) ($assignment['name'] ?? 'Assignment'));
                    if ($existingcourse && isset($existingmodules[$key])) {
                        continue;
                    }
                    $cmid = module_factory::create($course, $sectionnum, $assignment);
                    if ($existingcourse) {
                        $record['mergeCms'][] = $cmid;
                    } else {
                        $record['cms'][] = $cmid;
                    }
                    $existingmodules[$key] = true;
                }

                // Enrol teachers.
                foreach ($c['teachers'] ?? [] as $temail) {
                    $this->enrol_teacher($course, $temail, $record);
                }

                // Bind cohorts to the course (cohort enrol as students).
                foreach ($c['cohorts'] ?? [] as $cref) {
                    $cid = $this->resolve_cohort($cref, $cohortmap);
                    if ($cid && !$existingcourse) {
                        $this->bind_cohort_to_course($course, $cid, 'student');
                    }
                }

                // Directly enrol any individually listed students.
                foreach ($c['students'] ?? [] as $login) {
                    $uid = $this->resolve_user_id($login);
                    if ($uid) {
                        if ($this->enrol_in_course($course, $uid, 'student')) {
                            $record['enrolments'][] = ['courseid' => (int) $course->id, 'userid' => (int) $uid];
                        }
                    }
                }

                rebuild_course_cache($course->id);
            }

            // 4. Enrolments into existing or just-created courses.
            foreach ($manifest->enrolments() as $e) {
                $courseid = $DB->get_field('course', 'id', ['shortname' => trim((string) ($e['course'] ?? ''))]);
                if (!$courseid) {
                    continue;
                }
                $enrolcourse = get_course($courseid);
                $login = trim((string) ($e['user'] ?? ''));
                $uid = $this->resolve_user_id($login);
                if (!$uid) {
                    [$uid, $created] = $this->ensure_user([
                        'username' => $login,
                        'email' => (strpos($login, '@') !== false) ? $login : ($login . '@example.invalid'),
                        'firstname' => 'KRECK',
                        'lastname' => 'User',
                    ]);
                    if ($created) {
                        $record['users'][] = $uid;
                    }
                }
                $roleshort = $this->normalise_role((string) ($e['role'] ?? 'student'));
                if ($this->enrol_in_course($enrolcourse, $uid, $roleshort)) {
                    $record['enrolments'][] = ['courseid' => (int) $courseid, 'userid' => (int) $uid];
                    $log[] = get_string('onb_log_enrolled', 'local_kreck',
                        (object) ['user' => $login, 'course' => $enrolcourse->shortname]);
                }
            }

            // 5. Cross-system deep-links from the KRECK SMS (relative SMS URLs),
            //    so LMS can render "View in KRECK SMS" links on course/user pages.
            $linkcount = $this->store_links($manifest->links(), $record);
            if ($linkcount) {
                $log[] = 'Stored ' . $linkcount . ' KRECK SMS back-link(s).';
            }

            $this->store_batch($record);
            $log[] = get_string('onb_log_done', 'local_kreck', $this->batchid);
            return ['ok' => true, 'batchid' => $this->batchid, 'log' => $log, 'record' => $record, 'error' => null];

        } catch (\Throwable $e) {
            // Best-effort clean-up of the partial batch so no half state remains.
            $this->rollback_record($record);
            return ['ok' => false, 'batchid' => $this->batchid, 'log' => $log, 'record' => $record,
                'error' => $e->getMessage()];
        } finally {
            if ($suppressoutbound) {
                \local_kreck\sync\observer::resume();
            }
        }
    }

    /**
     * Upsert the cross-system deep-links carried in the manifest's `links`
     * section (relative SMS URLs). Idempotent on (entitytype, entitykey,
     * direction). Returns how many links were stored.
     *
     * @param array $links ['courses'=>[shortname=>['url','gibbonCourseClassID']], 'students'=>[studentid=>[...]]]
     * @param array $record Batch record (created link ids tracked for rollback).
     * @return int
     */
    protected function store_links(array $links, array &$record): int {
        global $DB, $USER;
        if (empty($links)) {
            return 0;
        }
        $now = time();
        $uid = isset($USER->id) ? (int) $USER->id : 0;
        $rows = [];
        foreach (($links['courses'] ?? []) as $shortname => $info) {
            $rows[] = ['course', (string) $shortname, (string) ($info['url'] ?? ''), (string) ($info['gibbonCourseClassID'] ?? '')];
        }
        foreach (($links['students'] ?? []) as $sid => $info) {
            $rows[] = ['user', (string) $sid, (string) ($info['url'] ?? ''), (string) ($info['gibbonPersonID'] ?? '')];
        }
        $count = 0;
        foreach ($rows as [$etype, $ekey, $url, $extid]) {
            if ($url === '' || $ekey === '') {
                continue;
            }
            $data = (object) [
                'entitytype'   => $etype,
                'entitykey'    => $ekey,
                'direction'    => 'togibbon',
                'relurl'       => $url,
                'extid'        => $extid !== '' ? $extid : null,
                'usermodified' => $uid,
                'timemodified' => $now,
            ];
            $existing = $DB->get_record('local_kreck_link',
                ['entitytype' => $etype, 'entitykey' => $ekey, 'direction' => 'togibbon']);
            if ($existing) {
                $data->id = $existing->id;
                $DB->update_record('local_kreck_link', $data);
            } else {
                $data->timecreated = $now;
                $newid = $DB->insert_record('local_kreck_link', $data);
                $record['links'][] = (int) $newid;
            }
            $count++;
        }
        return $count;
    }

    /**
     * Standalone, idempotent import of SMS (KRECK SMS / SMS) deep-links into
     * local_kreck_link, independent of a full manifest onboarding. Mirrors
     * store_links() but reports per-key results for the reverse-import page.
     * Idempotent on (entitytype, entitykey, direction='togibbon').
     *
     * @param array $links ['courses'=>[shortname=>['url'|'relurl','gibbonCourseClassID'|'extid']],
     *                       'students'=>[studentid=>['url'|'relurl','gibbonPersonID'|'extid']]]
     * @return array ['created'=>int,'updated'=>int,'skipped'=>int,'rows'=>array]
     */
    public function import_links(array $links): array {
        global $DB, $USER;
        $now = time();
        $uid = isset($USER->id) ? (int) $USER->id : 0;
        $items = [];
        foreach (($links['courses'] ?? []) as $shortname => $info) {
            $items[] = ['course', (string) $shortname,
                (string) ($info['url'] ?? ($info['relurl'] ?? '')),
                (string) ($info['gibbonCourseClassID'] ?? ($info['extid'] ?? ''))];
        }
        foreach (($links['students'] ?? []) as $sid => $info) {
            $items[] = ['user', (string) $sid,
                (string) ($info['url'] ?? ($info['relurl'] ?? '')),
                (string) ($info['gibbonPersonID'] ?? ($info['extid'] ?? ''))];
        }
        $created = 0; $updated = 0; $skipped = 0; $rows = [];
        foreach ($items as [$etype, $ekey, $url, $extid]) {
            if ($url === '' || $ekey === '') {
                $skipped++;
                $rows[] = ['type' => $etype, 'key' => $ekey, 'result' => 'skipped', 'reason' => 'missing key or URL'];
                continue;
            }
            $data = (object) [
                'entitytype'   => $etype,
                'entitykey'    => $ekey,
                'direction'    => 'togibbon',
                'relurl'       => $url,
                'extid'        => $extid !== '' ? $extid : null,
                'usermodified' => $uid,
                'timemodified' => $now,
            ];
            $existing = $DB->get_record('local_kreck_link',
                ['entitytype' => $etype, 'entitykey' => $ekey, 'direction' => 'togibbon']);
            if ($existing) {
                $data->id = $existing->id;
                $DB->update_record('local_kreck_link', $data);
                $updated++;
                $rows[] = ['type' => $etype, 'key' => $ekey, 'result' => 'updated'];
            } else {
                $data->timecreated = $now;
                $DB->insert_record('local_kreck_link', $data);
                $created++;
                $rows[] = ['type' => $etype, 'key' => $ekey, 'result' => 'created'];
            }
        }
        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'rows' => $rows];
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

        $removed = ['enrolments' => 0, 'courses' => 0, 'users' => 0, 'cohorts' => 0, 'categories' => 0];

        // Un-enrol first, so enrolments into pre-existing courses are undone
        // (enrolments into courses we also delete below vanish with the course).
        if (!empty($record['enrolments'])) {
            $manual = enrol_get_plugin('manual');
            foreach ($record['enrolments'] as $en) {
                if (!$DB->record_exists('course', ['id' => $en['courseid']])) {
                    continue;
                }
                $instance = $DB->get_record('enrol', ['courseid' => $en['courseid'], 'enrol' => 'manual']);
                if ($instance) {
                    $manual->unenrol_user($instance, $en['userid']);
                    $removed['enrolments']++;
                }
            }
        }

        if (!empty($record['mergeCms'])) {
            foreach (array_reverse($record['mergeCms']) as $cmid) {
                if ($DB->record_exists('course_modules', ['id' => $cmid])) {
                    course_delete_module((int) $cmid);
                }
            }
        }

        if (!empty($record['mergeEvents'])) {
            require_once($CFG->dirroot . '/calendar/lib.php');
            foreach ($record['mergeEvents'] as $eventid) {
                if ($DB->record_exists('event', ['id' => $eventid])) {
                    \calendar_event::load((int) $eventid)->delete();
                }
            }
        }

        // Courses (this also removes their modules).
        foreach ($record['courses'] ?? [] as $cid) {
            if ($DB->record_exists('course', ['id' => $cid])) {
                delete_course($cid, false);
                $removed['courses']++;
            }
        }

        // KRECK cross-system links created by this batch.
        foreach ($record['links'] ?? [] as $linkid) {
            if ($DB->delete_records('local_kreck_link', ['id' => $linkid])) {
                $removed['links'] = ($removed['links'] ?? 0) + 1;
            }
        }

        // KRECK student identity records (before the users themselves).
        foreach ($record['students'] ?? [] as $uid) {
            if ($DB->delete_records('local_kreck_student', ['userid' => $uid])) {
                $removed['students'] = ($removed['students'] ?? 0) + 1;
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
     * Public accessor for a stored batch record (coursemap, links, etc.),
     * used by the link/mark exporters.
     *
     * @param string $batchid
     * @return array|null
     */
    public function get_batch(string $batchid): ?array {
        return $this->load_batch($batchid);
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

        // Starter password: per-row, else the run default. With a password set,
        // the account can log in; without one it is created password-less.
        $password = $u['password'] ?? $this->defaultpassword;
        if ($password !== null && $password !== '') {
            $new->password = $password;
            $id = user_create_user($new, true, false);
            if ($this->forcepasswordchange) {
                set_user_preference('auth_forcepasswordchange', 1, $id);
            }
        } else {
            $id = user_create_user($new, false, false);
        }
        return [(int) $id, true];
    }

    /**
     * Upsert the KRECK student record that links a LMS userid to the external
     * SIS student ID (the cross-system join key for the results round-trip).
     *
     * Uses the persistent model so audit fields and the unique userid key are
     * handled consistently. Best-effort: a failure never aborts provisioning.
     *
     * @param int $userid LMS user id.
     * @param string $studentid External SIS student ID (e.g. SMS studentID).
     * @param array $s The manifest student row (optional classname/stream/etc).
     * @return bool True if a record was created or updated.
     */
    protected function ensure_student_record(int $userid, string $studentid, array $s): bool {
        if ($userid <= 0 || $studentid === '') {
            return false;
        }
        try {
            $existing = \local_kreck\persistent\student::get_record(['userid' => $userid]);
            $record = $existing ?: new \local_kreck\persistent\student();
            $record->set('userid', $userid);
            $record->set('studentid', $studentid);
            if (isset($s['cohort'])) {
                $record->set('classname', (string) $s['cohort']);
            }
            if (isset($s['stream'])) {
                $record->set('stream', (string) $s['stream']);
            }
            if (isset($s['schoolid'])) {
                $record->set('schoolid', (string) $s['schoolid']);
            }
            if (isset($s['guardiancontact'])) {
                $record->set('guardiancontact', (string) $s['guardiancontact']);
            }
            $record->save();
            return true;
        } catch (\Throwable $e) {
            debugging('local_kreck: could not store student record for userid ' . $userid
                . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
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

        if ($this->enrol_in_course($course, (int) $user->id, 'editingteacher')) {
            $record['enrolments'][] = ['courseid' => (int) $course->id, 'userid' => (int) $user->id];
        }
    }

    /**
     * Manually enrol a user into a course with a role (by archetype/shortname).
     *
     * @param \stdClass $course
     * @param int $userid
     * @param string $roleshort
     * @return bool Whether a new enrolment was created.
     */
    protected function enrol_in_course(\stdClass $course, int $userid, string $roleshort): bool {
        global $DB;
        if (is_enrolled(\context_course::instance((int) $course->id), $userid)) {
            return false;
        }
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
        return true;
    }

    /**
     * Stable within a course for matching manifest content during merge.
     *
     * @param int $sectionnum
     * @param string $type
     * @param string $name
     * @return string
     */
    protected function module_match_key(int $sectionnum, string $type, string $name): string {
        return $sectionnum . '|' . strtolower($type) . '|' . trim($name);
    }

    /**
     * Index current activities by section, module type, and name.
     *
     * @param \stdClass $course
     * @return bool[]
     */
    protected function course_module_index(\stdClass $course): array {
        $index = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if (!$cm->deletioninprogress) {
                $index[$this->module_match_key($cm->sectionnum, $cm->modname, $cm->name)] = true;
            }
        }
        return $index;
    }

    /**
     * Map a human role label onto a LMS role shortname.
     *
     * @param string $label
     * @return string
     */
    protected function normalise_role(string $label): string {
        $l = strtolower(trim($label));
        $map = [
            'teacher' => 'editingteacher', 'editing teacher' => 'editingteacher', 'editingteacher' => 'editingteacher',
            'lecturer' => 'editingteacher', 'instructor' => 'editingteacher',
            'non-editing teacher' => 'teacher', 'nonediting teacher' => 'teacher', 'assistant' => 'teacher',
            'tutor' => 'teacher', 'ta' => 'teacher',
            'student' => 'student', 'learner' => 'student', 'pupil' => 'student',
            'manager' => 'manager', 'admin' => 'manager', 'principal' => 'manager', 'head' => 'manager',
        ];
        if (isset($map[$l])) {
            return $map[$l];
        }
        return in_array($l, ['editingteacher', 'teacher', 'student', 'manager'], true) ? $l : 'student';
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
