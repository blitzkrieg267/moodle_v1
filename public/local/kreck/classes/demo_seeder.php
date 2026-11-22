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
 * KRECK — one-click LMS demo seeder.
 *
 * Stands up a complete, self-contained demo on the LMS so the whole MicroLearn
 * experience is demonstrable without a live SMS feed: 15 demo logins (matching
 * the SMS side's usernames), a demo course with teachers + students enrolled, an
 * Assignment with graded marks, the KRECK learner analytics (attendance, fees,
 * streak, struggle topics), and the parent mirror (each parent linked to their
 * child with the KRECK Parent role in the child's user context).
 *
 * Idempotent: seed() first wipes anything a previous run created (demo.* users +
 * their course/analytics/links) and reseeds from clean.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck;

defined('MOODLE_INTERNAL') || die();

use local_kreck\persistent\student;
use local_kreck\persistent\streak;
use local_kreck\persistent\feeaccount;
use local_kreck\persistent\attendance;
use local_kreck\persistent\topicstat;
use local_kreck\persistent\parentlink;

class demo_seeder {

    /** Shared demo password. */
    const DEMO_PASSWORD = 'Demo@Kreck26';
    /** Username prefix for every demo account. */
    const USER_PREFIX = 'demo.';
    /** Course shortname KRECK owns for the demo. */
    const COURSE_SHORTNAME = 'KRECK-DEMO';

    /** The 15 demo accounts: [key, username, first, surname, kind, description]. */
    const ACCOUNTS = [
        ['admin',     'demo.admin',     'Thandiwe', 'Admin',     'staff',   'School Administrator'],
        ['principal', 'demo.principal', 'Kagiso',   'Principal', 'staff',   'Principal'],
        ['bursar',    'demo.bursar',    'Naledi',   'Bursar',    'staff',   'Bursar / Finance Officer'],
        ['registrar', 'demo.registrar', 'Oarabile', 'Registrar', 'staff',   'Registrar / Admissions'],
        ['hod',       'demo.hod',       'Mpho',     'Molefe',    'teacher', 'Head of Department'],
        ['teacher1',  'demo.teacher1',  'Tebogo',   'Khan',      'teacher', 'Teacher (course lead)'],
        ['teacher2',  'demo.teacher2',  'Lesego',   'Dube',      'teacher', 'Teacher'],
        ['support',   'demo.support',   'Boitumelo','Support',   'staff',   'IT / Support Staff'],
        ['librarian', 'demo.librarian', 'Palesa',   'Books',     'staff',   'Librarian'],
        ['student1',  'demo.student1',  'Amara',    'Mokoena',   'student', 'Student'],
        ['student2',  'demo.student2',  'Katlego',  'Sithole',   'student', 'Student'],
        ['student3',  'demo.student3',  'Zinhle',   'Ndlovu',    'student', 'Student'],
        ['parent1',   'demo.parent1',   'Grace',    'Mokoena',   'parent',  'Parent of Amara'],
        ['parent2',   'demo.parent2',   'Sipho',    'Sithole',   'parent',  'Parent of Katlego'],
        ['parent3',   'demo.parent3',   'Nomsa',    'Ndlovu',    'parent',  'Parent of Zinhle'],
    ];

    /** @var string[] progress log */
    private $log = [];

    private function say(string $m): void { $this->log[] = $m; }
    private function fail(string $phase, \Throwable $e): void {
        $this->say("!! $phase failed: " . $e->getMessage());
        debugging('local_kreck demo seed: ' . $phase . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    /** All demo user ids currently present (username demo.*). */
    private function demo_user_ids(): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select('user', 'id', "username LIKE :p AND deleted = 0", ['p' => self::USER_PREFIX . '%']));
    }

    /**
     * Remove every demo user and all demo data. Returns the number of users removed.
     */
    public function wipe(): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $ids = $this->demo_user_ids();

        // Delete the demo course (removes its modules, enrolments, grades, context).
        if ($course = $DB->get_record('course', ['shortname' => self::COURSE_SHORTNAME])) {
            delete_course($course->id, false);
        }

        foreach ($ids as $uid) {
            // KRECK analytics + links.
            $DB->delete_records('local_kreck_attendance', ['userid' => $uid]);
            $DB->delete_records('local_kreck_streak', ['userid' => $uid]);
            $DB->delete_records('local_kreck_feeaccount', ['userid' => $uid]);
            $DB->delete_records('local_kreck_topicstat', ['userid' => $uid]);
            $DB->delete_records('local_kreck_student', ['userid' => $uid]);
            $DB->delete_records('local_kreck_parentlink', ['parentuserid' => $uid]);
            $DB->delete_records('local_kreck_parentlink', ['childuserid' => $uid]);
            // Role assignments made in this user's context (the parent mirror).
            if ($ctx = \context_user::instance($uid, IGNORE_MISSING)) {
                $DB->delete_records('role_assignments', ['contextid' => $ctx->id]);
            }
            $DB->delete_records('role_assignments', ['userid' => $uid]);
            // Hard-delete the throwaway demo account.
            $DB->delete_records('user', ['id' => $uid]);
            if ($ctx) { $ctx->delete(); }
        }
        return count($ids);
    }

    /**
     * Resolve a role id by shortname (same approach as the onboarding engine).
     */
    private function role_id(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname]);
    }

    /**
     * Create (or match by username) a demo user. Returns the user id.
     */
    private function ensure_user(array $a): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        [$key, $username, $first, $last] = $a;

        if ($existing = $DB->get_record_select('user', 'username = :u AND deleted = 0', ['u' => $username])) {
            return (int) $existing->id;
        }
        $u = (object) [
            'username'   => $username,
            'auth'       => 'manual',
            'confirmed'  => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'firstname'  => $first,
            'lastname'   => $last,
            'email'      => $username . '@demo.kreck.local',
            'idnumber'   => 'kreckdemo:' . $key,
            'password'   => self::DEMO_PASSWORD,
        ];
        return (int) user_create_user($u, true, false);
    }

    /**
     * Seed the full LMS demo. Returns:
     *   ['ok'=>bool, 'log'=>string[], 'accounts'=>array, 'password'=>string]
     */
    public function seed(): array {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/lib/enrollib.php');
        require_once($CFG->dirroot . '/lib/gradelib.php');

        $this->log = [];

        // Need a real user context for module/file APIs.
        if (!isloggedin() || isguestuser()) {
            \core\session\manager::set_user(get_admin());
        }

        // PHASE 0 — wipe previous demo.
        try { $n = $this->wipe(); $this->say("Reset: cleared previous demo ($n users)."); }
        catch (\Throwable $e) { $this->fail('wipe', $e); }

        // PHASE 1 — the 15 demo users.
        $P = [];
        try {
            foreach (self::ACCOUNTS as $a) { $P[$a[0]] = $this->ensure_user($a); }
            $this->say('Accounts: created ' . count($P) . ' demo logins (password: ' . self::DEMO_PASSWORD . ').');
        } catch (\Throwable $e) { $this->fail('accounts', $e); }

        // PHASE 2 — demo course + enrolments.
        $course = null;
        try {
            $catid = (int) $DB->get_field_sql('SELECT id FROM {course_categories} WHERE visible = 1 ORDER BY sortorder, id LIMIT 1');
            if (!$catid) { $catid = (int) $DB->get_field_sql('SELECT id FROM {course_categories} ORDER BY id LIMIT 1'); }
            $course = create_course((object) [
                'fullname'      => 'KRECK Demo Class',
                'shortname'     => self::COURSE_SHORTNAME,
                'category'      => $catid,
                'summary'       => 'KRECK demonstration course (seeded demo data).',
                'summaryformat' => FORMAT_HTML,
                'format'        => 'topics',
                'numsections'   => 3,
                'startdate'     => usergetmidnight(time() - 60 * DAYSECS),
            ]);
            $manual = enrol_get_plugin('manual');
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
            if ($manual && $instance) {
                $manual->enrol_user($instance, $P['teacher1'], $this->role_id('editingteacher'));
                $manual->enrol_user($instance, $P['hod'], $this->role_id('editingteacher'));
                $manual->enrol_user($instance, $P['teacher2'], $this->role_id('teacher'));
                foreach (['student1', 'student2', 'student3'] as $sk) {
                    $manual->enrol_user($instance, $P[$sk], $this->role_id('student'));
                }
            }
            $this->say('Course: "KRECK Demo Class" created with 3 teachers + 3 students enrolled.');
        } catch (\Throwable $e) { $this->fail('course', $e); }

        // PHASE 3 — an Assignment with graded marks.
        try {
            if ($course) {
                $cmid = \local_kreck\onboarding\module_factory::create($course, 1, [
                    'type'  => 'assign',
                    'name'  => 'Term Project — Research Essay',
                    'intro' => 'Demo assignment. Submit your research essay; grades are already recorded for the demo students.',
                    'grade' => 100,
                    'due_date' => date('Y-m-d', time() + 14 * DAYSECS),
                    'due_time' => '23:59',
                ]);
                require_once($CFG->dirroot . '/mod/assign/locallib.php');
                $cm = get_coursemodule_from_id('assign', $cmid, $course->id, false, MUST_EXIST);
                $assign = new \assign(\context_module::instance($cm->id), $cm, $course);
                $marks = ['student1' => 85.0, 'student2' => 72.5, 'student3' => 64.0];
                foreach ($marks as $sk => $mark) {
                    $grade = $assign->get_user_grade($P[$sk], true);
                    $grade->grade = $mark;
                    $grade->grader = $P['teacher1'];
                    $assign->update_grade($grade);
                }
                $this->say('Assignment: "Term Project" added with marks for 3 students (85, 72.5, 64).');
            }
        } catch (\Throwable $e) { $this->fail('assignment', $e); }

        // PHASE 4 — KRECK learner analytics per demo student.
        try {
            $profiles = [
                'student1' => ['class' => 'KRECK Demo Class', 'present' => 27, 'total' => 30, 'fee' => 0.0,    'feestatus' => feeaccount::STATUS_CURRENT, 'streak' => [12, 20], 'topics' => [['Quadratic equations', 18, 6], ['Essay structure', 12, 3]]],
                'student2' => ['class' => 'KRECK Demo Class', 'present' => 24, 'total' => 30, 'fee' => 1250.0, 'feestatus' => feeaccount::STATUS_OVERDUE, 'streak' => [3, 9],  'topics' => [['Balancing equations', 20, 11], ['Trigonometry', 15, 7]]],
                'student3' => ['class' => 'KRECK Demo Class', 'present' => 29, 'total' => 30, 'fee' => 300.0,  'feestatus' => feeaccount::STATUS_CURRENT, 'streak' => [7, 14], 'topics' => [['Photosynthesis', 14, 4]]],
            ];
            $made = 0;
            foreach ($profiles as $sk => $pr) {
                $uid = $P[$sk];
                // Identity.
                $rec = student::get_record(['userid' => $uid]) ?: new student();
                $rec->set('userid', $uid);
                $rec->set('studentid', 'KD-' . strtoupper($sk));
                $rec->set('classname', $pr['class']);
                $rec->save();
                // Attendance (last N session days; first M present, a couple late, rest absent).
                for ($i = 0; $i < $pr['total']; $i++) {
                    $day = usergetmidnight(time() - $i * DAYSECS);
                    $status = attendance::STATUS_PRESENT;
                    if ($i >= $pr['present']) { $status = attendance::STATUS_ABSENT; }
                    elseif ($i % 9 === 8) { $status = attendance::STATUS_LATE; }
                    $att = attendance::get_record(['userid' => $uid, 'sessiondate' => $day]) ?: new attendance();
                    $att->set('userid', $uid); $att->set('sessiondate', $day); $att->set('status', $status);
                    $att->set('classname', $pr['class']); $att->set('source', 'demo'); $att->save();
                }
                // Fees.
                $fee = feeaccount::get_record(['userid' => $uid]) ?: new feeaccount();
                $fee->set('userid', $uid); $fee->set('balancecents', (int) round($pr['fee'] * 100));
                $fee->set('currency', 'BWP'); $fee->set('status', $pr['feestatus']); $fee->set('laststatement', time());
                $fee->save();
                // Streak.
                $st = streak::get_record(['userid' => $uid]) ?: new streak();
                $st->set('userid', $uid); $st->set('current', $pr['streak'][0]); $st->set('longest', $pr['streak'][1]);
                $st->set('lastactive', time()); $st->save();
                // Struggle topics.
                foreach ($pr['topics'] as [$topic, $attempts, $wrong]) {
                    $ts = topicstat::get_record(['userid' => $uid, 'topic' => $topic]) ?: new topicstat();
                    $ts->set('userid', $uid); $ts->set('topic', $topic); $ts->set('attempts', $attempts);
                    $ts->set('wrong', $wrong); $ts->set('lastseen', time()); $ts->save();
                }
                $made++;
            }
            $this->say("Learner analytics: attendance, fees, streak & struggle-topics seeded for $made students.");
        } catch (\Throwable $e) { $this->fail('analytics', $e); }

        // PHASE 5 — parent mirror (parent role in child's user context + link row).
        try {
            $parentroleid = \local_kreck\parentrole::get_id();
            $pairs = [['parent1', 'student1'], ['parent2', 'student2'], ['parent3', 'student3']];
            $linked = 0;
            foreach ($pairs as [$pk, $sk]) {
                $childctx = \context_user::instance($P[$sk]);
                role_assign($parentroleid, $P[$pk], $childctx->id);
                $link = parentlink::get_record(['parentuserid' => $P[$pk], 'childuserid' => $P[$sk]]) ?: new parentlink();
                $link->set('parentuserid', $P[$pk]); $link->set('childuserid', $P[$sk]);
                $link->set('sourceid', 'kreckdemo'); $link->set('contactpriority', 1); $link->save();
                $linked++;
            }
            $this->say("Parent mirror: linked $linked parents to their child (KRECK Parent role in the child's context).");
        } catch (\Throwable $e) { $this->fail('parent mirror', $e); }

        return ['ok' => true, 'log' => $this->log, 'accounts' => self::ACCOUNTS, 'password' => self::DEMO_PASSWORD];
    }
}
