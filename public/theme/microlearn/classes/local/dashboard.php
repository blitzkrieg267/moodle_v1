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
 * Builds the template context for each KRECK role dashboard.
 *
 * The theme owns presentation only; all learner data comes through
 * local_kreck's API facade (when installed), and every section degrades
 * gracefully when the data backbone or its data is absent.
 *
 * @package   theme_microlearn
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_microlearn\local;

/**
 * Role dashboard data provider.
 */
class dashboard {

    /**
     * Dispatch to the per-role builder.
     *
     * @param string $role
     * @param int $userid
     * @return array
     */
    public static function context_for(string $role, int $userid): array {
        switch ($role) {
            case role_resolver::ROLE_TEACHER:
                return self::teacher($userid);
            case role_resolver::ROLE_PARENT:
                return self::parent_dash($userid);
            case role_resolver::ROLE_INSTITUTION:
                return self::institution($userid);
            case role_resolver::ROLE_LEARNER:
            default:
                return self::learner($userid);
        }
    }

    /**
     * Whether the local_kreck data backbone is available.
     *
     * @return bool
     */
    protected static function has_backbone(): bool {
        global $DB;
        return class_exists('\\local_kreck\\api')
            && $DB->get_manager()->table_exists('local_kreck_student');
    }

    /**
     * First name for a greeting.
     *
     * @param int $userid
     * @return string
     */
    protected static function firstname(int $userid): string {
        global $DB, $USER;
        if ($userid === (int) $USER->id) {
            return $USER->firstname;
        }
        return (string) $DB->get_field('user', 'firstname', ['id' => $userid]);
    }

    /**
     * Learner dashboard.
     *
     * @param int $userid
     * @return array
     */
    protected static function learner(int $userid): array {
        $summary = self::has_backbone() ? \local_kreck\api::get_learner_summary($userid) : null;

        $streak = $summary['streak'] ?? ['current' => 0, 'longest' => 0];
        $attendance = $summary['attendance'] ?? ['rate' => null];
        $fees = $summary['fees'] ?? ['hasaccount' => false, 'status' => 0, 'balance' => 0, 'currency' => 'BWP'];
        $struggle = $summary['struggletopics'] ?? [];

        // "Keep learning" from the user's own courses.
        $lessons = [];
        $courses = enrol_get_users_courses($userid, true, ['id', 'fullname', 'shortname']);
        foreach (array_slice($courses, 0, 3) as $course) {
            if ((int) $course->id === SITEID) {
                continue;
            }
            $lessons[] = [
                'name' => format_string($course->fullname),
                'url' => (new \core\url('/course/view.php', ['id' => $course->id]))->out(false),
            ];
        }

        $hasattendance = $attendance['rate'] !== null;
        $isoverdue = (int) ($fees['status'] ?? 0) === 1 || (int) ($fees['status'] ?? 0) === 3;

        return [
            'role' => 'learner',
            'greeting' => get_string('dash_hi', 'theme_microlearn', self::firstname($userid)),
            'subtitle' => get_string('dash_learner_sub', 'theme_microlearn'),
            'streakcurrent' => (int) $streak['current'],
            'streaklongest' => (int) $streak['longest'],
            'hasattendance' => $hasattendance,
            'attendancepct' => $hasattendance ? (int) round($attendance['rate'] * 100) : 0,
            'hasstruggle' => !empty($struggle),
            'focustopic' => $struggle[0]['topic'] ?? '',
            'strugglepct' => !empty($struggle) ? (int) round($struggle[0]['rate'] * 100) : 0,
            'feeoverdue' => $isoverdue,
            'feeamount' => number_format((float) ($fees['balance'] ?? 0), 2),
            'feecurrency' => $fees['currency'] ?? 'BWP',
            'lessons' => $lessons,
            'haslessons' => !empty($lessons),
        ];
    }

    /**
     * Teacher dashboard.
     *
     * @param int $userid
     * @return array
     */
    protected static function teacher(int $userid): array {
        global $DB;

        $courseids = self::teacher_course_ids($userid);
        $classes = count($courseids);
        $learners = $classes ? self::learners_in_courses($courseids) : 0;

        $avgattendance = null;
        if (self::has_backbone()) {
            $avgattendance = self::attendance_rate_all();
        }

        return [
            'role' => 'teacher',
            'greeting' => get_string('dash_hi', 'theme_microlearn', self::firstname($userid)),
            'subtitle' => get_string('dash_teacher_sub', 'theme_microlearn'),
            'tiles' => [
                ['label' => get_string('dash_classes', 'theme_microlearn'), 'value' => $classes],
                ['label' => get_string('dash_learners', 'theme_microlearn'), 'value' => $learners],
                ['label' => get_string('dash_avgattendance', 'theme_microlearn'),
                    'value' => $avgattendance === null ? '—' : $avgattendance . '%'],
            ],
            'struggletopics' => self::struggle_rollup(),
            'hasstruggle' => !empty(self::struggle_rollup()),
        ];
    }

    /**
     * Parent dashboard (first linked child).
     *
     * @param int $userid
     * @return array
     */
    protected static function parent_dash(int $userid): array {
        $childid = self::first_child($userid);

        if (!$childid) {
            return [
                'role' => 'parent',
                'greeting' => get_string('dash_hi', 'theme_microlearn', self::firstname($userid)),
                'subtitle' => get_string('dash_parent_sub', 'theme_microlearn'),
                'haschild' => false,
            ];
        }

        $summary = self::has_backbone() ? \local_kreck\api::get_learner_summary($childid) : null;
        $attendance = $summary['attendance'] ?? ['rate' => null];
        $fees = $summary['fees'] ?? ['balance' => 0, 'currency' => 'BWP', 'status' => 0];
        $streak = $summary['streak'] ?? ['current' => 0];
        $struggle = $summary['struggletopics'] ?? [];

        $hasattendance = $attendance['rate'] !== null;
        $statuslabels = [0 => 'feestatus_current', 1 => 'feestatus_overdue', 2 => 'feestatus_paid', 3 => 'feestatus_failed'];
        $statuskey = $statuslabels[(int) ($fees['status'] ?? 0)] ?? 'feestatus_current';

        return [
            'role' => 'parent',
            'greeting' => get_string('dash_hi', 'theme_microlearn', self::firstname($userid)),
            'subtitle' => get_string('dash_parent_sub', 'theme_microlearn'),
            'haschild' => true,
            'childname' => self::firstname($childid),
            'hasattendance' => $hasattendance,
            'attendancepct' => $hasattendance ? (int) round($attendance['rate'] * 100) : 0,
            'feeamount' => number_format((float) ($fees['balance'] ?? 0), 2),
            'feecurrency' => $fees['currency'] ?? 'BWP',
            'feestatus' => get_string($statuskey, 'theme_microlearn'),
            'feeoverdue' => in_array((int) ($fees['status'] ?? 0), [1, 3], true),
            'streakcurrent' => (int) ($streak['current'] ?? 0),
            'hasstruggle' => !empty($struggle),
            'focustopic' => $struggle[0]['topic'] ?? '',
        ];
    }

    /**
     * Institution dashboard (whole-school roll-up).
     *
     * @param int $userid
     * @return array
     */
    protected static function institution(int $userid): array {
        global $DB;

        $totallearners = 0;
        $overdue = 0;
        $activestreaks = 0;
        $avgattendance = null;

        if (self::has_backbone()) {
            $totallearners = $DB->count_records('local_kreck_student');
            $overdue = $DB->count_records_select('local_kreck_feeaccount', 'status = 1 OR status = 3');
            $activestreaks = $DB->count_records_select('local_kreck_streak', 'current > 0');
            $avgattendance = self::attendance_rate_all();
        }

        return [
            'role' => 'institution',
            'greeting' => get_string('dash_school', 'theme_microlearn'),
            'subtitle' => get_string('dash_institution_sub', 'theme_microlearn'),
            'tiles' => [
                ['label' => get_string('dash_totallearners', 'theme_microlearn'), 'value' => $totallearners],
                ['label' => get_string('dash_avgattendance', 'theme_microlearn'),
                    'value' => $avgattendance === null ? '—' : $avgattendance . '%'],
                ['label' => get_string('dash_feesoverdue', 'theme_microlearn'), 'value' => $overdue],
                ['label' => get_string('dash_appactive', 'theme_microlearn'), 'value' => $activestreaks],
            ],
            'struggletopics' => self::struggle_rollup(),
            'hasstruggle' => !empty(self::struggle_rollup()),
            'adminurl' => (new \core\url('/admin/search.php'))->out(false),
            'reporturl' => (new \core\url('/report/insights/insights.php', ['contextid' => \context_system::instance()->id]))->out(false),
        ];
    }

    // --- Shared data helpers ------------------------------------------------

    /**
     * Course ids where the user holds a teaching role.
     *
     * @param int $userid
     * @return int[]
     */
    protected static function teacher_course_ids(int $userid): array {
        global $DB;
        $sql = "SELECT DISTINCT ctx.instanceid
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid
                   AND ctx.contextlevel = :courselevel
                   AND r.archetype IN ('editingteacher', 'teacher')";
        return array_map('intval', $DB->get_fieldset_sql($sql, [
            'userid' => $userid,
            'courselevel' => CONTEXT_COURSE,
        ]));
    }

    /**
     * Distinct learners enrolled as students in the given courses.
     *
     * @param int[] $courseids
     * @return int
     */
    protected static function learners_in_courses(array $courseids): int {
        global $DB;
        if (empty($courseids)) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $sql = "SELECT COUNT(DISTINCT ra.userid)
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ctx.contextlevel = :courselevel
                   AND ctx.instanceid $insql
                   AND r.archetype = 'student'";
        $params['courselevel'] = CONTEXT_COURSE;
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Site-wide attendance rate as a whole-number percentage.
     *
     * @return int|null
     */
    protected static function attendance_rate_all(): ?int {
        global $DB;
        $total = $DB->count_records('local_kreck_attendance');
        if ($total === 0) {
            return null;
        }
        $present = $DB->count_records_select('local_kreck_attendance', 'status = 0 OR status = 2');
        return (int) round($present / $total * 100);
    }

    /**
     * Topics learners struggle with most, rolled up across everyone.
     *
     * @param int $limit
     * @return array
     */
    protected static function struggle_rollup(int $limit = 4): array {
        global $DB;
        if (!self::has_backbone()) {
            return [];
        }
        $sql = "SELECT topic, SUM(attempts) AS attempts, SUM(wrong) AS wrong
                  FROM {local_kreck_topicstat}
              GROUP BY topic
                HAVING SUM(attempts) >= 3";
        $rows = $DB->get_records_sql($sql);
        $topics = [];
        foreach ($rows as $row) {
            $attempts = (int) $row->attempts;
            if ($attempts <= 0) {
                continue;
            }
            $rate = $row->wrong / $attempts;
            $topics[] = [
                'topic' => $row->topic,
                'pct' => (int) round($rate * 100),
                'rate' => $rate,
            ];
        }
        usort($topics, static fn($a, $b) => $b['rate'] <=> $a['rate']);
        return array_slice($topics, 0, $limit);
    }

    /**
     * The first user this user mentors (parent -> child link).
     *
     * @param int $userid
     * @return int|null
     */
    protected static function first_child(int $userid): ?int {
        global $DB;
        $sql = "SELECT ctx.instanceid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid AND ctx.contextlevel = :userlevel
              ORDER BY ctx.instanceid ASC";
        $childid = $DB->get_field_sql($sql, ['userid' => $userid, 'userlevel' => CONTEXT_USER], IGNORE_MULTIPLE);
        return $childid ? (int) $childid : null;
    }
}
