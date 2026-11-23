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
 * External function: live "probe" that returns a student's COMPLETE gradebook —
 * every grade item across every enrolled course (assignments, quizzes, manual
 * items, category sub-totals and the course total), with the full spectrum of
 * marks metrics. The KRECK SMS calls this to pull marks on demand instead of the
 * manual export/import.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * Returns a learner's complete gradebook for the KRECK SMS to pull and display.
 */
class get_student_marks extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'studentid' => new external_value(PARAM_RAW, 'KRECK student id (cross-system join key); username or idnumber are accepted as fallbacks.'),
        ]);
    }

    /**
     * Resolve the KRECK student id to a Moodle user id.
     *
     * @param string $studentid
     * @return int user id or 0
     */
    protected static function resolve_userid(string $studentid): int {
        global $DB;
        $studentid = trim($studentid);
        if ($studentid === '') {
            return 0;
        }
        // Primary: the KRECK identity record (studentid -> userid).
        $uid = (int) $DB->get_field('local_kreck_student', 'userid', ['studentid' => $studentid]);
        if ($uid) {
            return $uid;
        }
        // Fallbacks: username (the manifest lowercases it) or idnumber.
        $uid = (int) $DB->get_field_select('user', 'id',
            '(username = :u1 OR username = :u2 OR idnumber = :i) AND deleted = 0',
            ['u1' => $studentid, 'u2' => \core_text::strtolower($studentid), 'i' => $studentid], IGNORE_MULTIPLE);
        return $uid;
    }

    /**
     * @param string $studentid
     * @return array
     */
    public static function execute(string $studentid): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_item.php');
        require_once($CFG->libdir . '/grade/grade_grade.php');
        require_once($CFG->dirroot . '/lib/enrollib.php');

        ['studentid' => $studentid] = self::validate_parameters(self::execute_parameters(), ['studentid' => $studentid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kreck:manage', $context);

        $userid = self::resolve_userid((string) $studentid);
        if (!$userid) {
            return self::empty_result((string) $studentid);
        }
        $user = \core_user::get_user($userid, '*', IGNORE_MISSING);
        if (!$user || !empty($user->deleted)) {
            return self::empty_result((string) $studentid);
        }

        $courses = enrol_get_users_courses($userid, true, 'id, shortname, fullname');
        $courseout = [];
        $totalitems = 0;
        $gradeditems = 0;
        $pcts = [];

        foreach ($courses as $course) {
            $items = \grade_item::fetch_all(['courseid' => $course->id]);
            if (!$items) {
                continue;
            }
            uasort($items, static fn($a, $b) => ($a->sortorder <=> $b->sortorder));

            $rows = [];
            $coursetotal = null;
            foreach ($items as $item) {
                if ((int) $item->gradetype === GRADE_TYPE_NONE) {
                    continue; // container with no grade value
                }
                $gg = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
                $final = ($gg && $gg->finalgrade !== null && $gg->finalgrade !== '') ? (float) $gg->finalgrade : null;
                $graded = $final !== null;

                $percentage = null;
                if ($graded && (int) $item->gradetype === GRADE_TYPE_VALUE && $item->grademax > $item->grademin) {
                    $percentage = round(($final - $item->grademin) / ($item->grademax - $item->grademin) * 100, 1);
                }
                // A human-readable display value in the item's own grade type.
                $display = $graded ? grade_format_gradevalue($final, $item, true, GRADE_DISPLAY_TYPE_REAL) : '—';
                $letter = ($graded && (int) $item->gradetype === GRADE_TYPE_VALUE)
                    ? grade_format_gradevalue($final, $item, true, GRADE_DISPLAY_TYPE_LETTER) : '';
                $feedback = ($gg && $gg->feedback !== null && $gg->feedback !== '')
                    ? trim(html_to_text(format_text((string) $gg->feedback, $gg->feedbackformat ?? FORMAT_HTML), 0, false)) : '';
                $weight = $item->aggregationcoef2 ? round((float) $item->aggregationcoef2 * 100, 1)
                    : ((float) $item->aggregationcoef ?: 0.0);

                $row = [
                    'name'       => $item->get_name(true),
                    'itemtype'   => (string) $item->itemtype,                 // mod | manual | category | course
                    'module'     => (string) ($item->itemmodule ?? ''),       // assign, quiz, forum, ...
                    'gradetype'  => (int) $item->gradetype,                    // 1 value, 2 scale, 3 text
                    'grade'      => $graded ? $final : null,
                    'display'    => (string) $display,
                    'grademax'   => (float) $item->grademax,
                    'grademin'   => (float) $item->grademin,
                    'percentage' => $percentage,
                    'letter'     => (string) $letter,
                    'feedback'   => $feedback,
                    'weight'     => (float) $weight,
                    'graded'     => $graded,
                    'dategraded' => ($gg && $gg->dategraded) ? (int) $gg->dategraded : 0,
                    'hidden'     => $item->is_hidden() ? 1 : 0,
                ];

                if ((string) $item->itemtype === 'course') {
                    $coursetotal = $row;
                } else {
                    $rows[] = $row;
                    $totalitems++;
                    if ($graded) {
                        $gradeditems++;
                        if ($percentage !== null) {
                            $pcts[] = $percentage;
                        }
                    }
                }
            }

            $courseout[] = [
                'courseid'    => (int) $course->id,
                'shortname'   => (string) $course->shortname,
                'fullname'    => format_string($course->fullname),
                'coursetotal' => $coursetotal,
                'items'       => $rows,
            ];
        }

        return [
            'found'        => true,
            'studentid'    => (string) $studentid,
            'userid'       => (int) $userid,
            'fullname'     => fullname($user),
            'username'     => (string) $user->username,
            'coursecount'  => count($courseout),
            'itemcount'    => $totalitems,
            'gradedcount'  => $gradeditems,
            'averagepct'   => $pcts ? round(array_sum($pcts) / count($pcts), 1) : null,
            'generated'    => time(),
            'courses'      => $courseout,
        ];
    }

    /** The shape returned when the student can't be resolved or has no data. */
    protected static function empty_result(string $studentid): array {
        return [
            'found' => false, 'studentid' => $studentid, 'userid' => 0, 'fullname' => '', 'username' => '',
            'coursecount' => 0, 'itemcount' => 0, 'gradedcount' => 0, 'averagepct' => null,
            'generated' => time(), 'courses' => [],
        ];
    }

    public static function execute_returns(): external_single_structure {
        $grademetrics = new external_single_structure([
            'name'       => new external_value(PARAM_TEXT, 'Grade item name.'),
            'itemtype'   => new external_value(PARAM_ALPHA, 'course, category, mod or manual.'),
            'module'     => new external_value(PARAM_PLUGIN, 'Activity module (assign, quiz, …) or empty.', VALUE_OPTIONAL, ''),
            'gradetype'  => new external_value(PARAM_INT, '1 value, 2 scale, 3 text.'),
            'grade'      => new external_value(PARAM_FLOAT, 'Final numeric grade, null if not graded.', VALUE_OPTIONAL, null, NULL_ALLOWED),
            'display'    => new external_value(PARAM_RAW, 'Human-readable grade in the item grade type.'),
            'grademax'   => new external_value(PARAM_FLOAT, 'Maximum grade.'),
            'grademin'   => new external_value(PARAM_FLOAT, 'Minimum grade.'),
            'percentage' => new external_value(PARAM_FLOAT, 'Percentage 0-100, null when N/A.', VALUE_OPTIONAL, null, NULL_ALLOWED),
            'letter'     => new external_value(PARAM_RAW, 'Letter grade, if configured.', VALUE_OPTIONAL, ''),
            'feedback'   => new external_value(PARAM_RAW, 'Grader feedback (plain text).', VALUE_OPTIONAL, ''),
            'weight'     => new external_value(PARAM_FLOAT, 'Item weight / aggregation coefficient.', VALUE_OPTIONAL, 0),
            'graded'     => new external_value(PARAM_BOOL, 'Whether a grade is recorded.'),
            'dategraded' => new external_value(PARAM_INT, 'When graded (unix ts), 0 if never.', VALUE_OPTIONAL, 0),
            'hidden'     => new external_value(PARAM_INT, '1 if the item is hidden from the learner.', VALUE_OPTIONAL, 0),
        ]);
        return new external_single_structure([
            'found'       => new external_value(PARAM_BOOL, 'Whether the student was resolved on the LMS.'),
            'studentid'   => new external_value(PARAM_RAW, 'Echoed KRECK student id.'),
            'userid'      => new external_value(PARAM_INT, 'LMS user id (0 if not found).'),
            'fullname'    => new external_value(PARAM_TEXT, 'Learner full name.'),
            'username'    => new external_value(PARAM_RAW, 'LMS username.', VALUE_OPTIONAL, ''),
            'coursecount' => new external_value(PARAM_INT, 'Courses with a gradebook.'),
            'itemcount'   => new external_value(PARAM_INT, 'Total gradeable items (excl. course totals).'),
            'gradedcount' => new external_value(PARAM_INT, 'Items with a recorded grade.'),
            'averagepct'  => new external_value(PARAM_FLOAT, 'Mean percentage across graded value items, null if none.', VALUE_OPTIONAL, null, NULL_ALLOWED),
            'generated'   => new external_value(PARAM_INT, 'When this snapshot was produced (unix ts).'),
            'courses'     => new external_multiple_structure(new external_single_structure([
                'courseid'    => new external_value(PARAM_INT, 'Course id.'),
                'shortname'   => new external_value(PARAM_RAW, 'Course short name.'),
                'fullname'    => new external_value(PARAM_TEXT, 'Course full name.'),
                'coursetotal' => new external_single_structure($grademetrics->keys, 'The course total item.', VALUE_OPTIONAL),
                'items'       => new external_multiple_structure($grademetrics, 'Every graded item in the course.'),
            ])),
        ]);
    }
}
