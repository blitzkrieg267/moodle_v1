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
 * External function: read a learner's KRECK summary.
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
use local_kreck\api;

/**
 * Returns the learner summary used by the MicroLearn app and dashboards.
 */
class get_learner_summary extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner user id; 0 for the current user.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Return the summary.
     *
     * @param int $userid
     * @return array
     */
    public static function execute(int $userid = 0): array {
        global $USER;

        ['userid' => $userid] = self::validate_parameters(self::execute_parameters(), ['userid' => $userid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kreck:view', $context);

        if (empty($userid)) {
            $userid = (int) $USER->id;
        } else if ($userid !== (int) $USER->id) {
            // Reading someone else's record requires the institution-level capability.
            require_capability('local/kreck:viewinstitution', $context);
        }

        return api::get_learner_summary($userid);
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'Learner user id.'),
            'student' => new external_single_structure([
                'studentid' => new external_value(PARAM_RAW, 'KRECK student ID.', VALUE_OPTIONAL, null, NULL_ALLOWED),
                'schoolid' => new external_value(PARAM_RAW, 'School ID.', VALUE_OPTIONAL, null, NULL_ALLOWED),
                'classname' => new external_value(PARAM_TEXT, 'Class name.', VALUE_OPTIONAL, null, NULL_ALLOWED),
                'stream' => new external_value(PARAM_TEXT, 'Stream.', VALUE_OPTIONAL, null, NULL_ALLOWED),
            ]),
            'attendance' => new external_single_structure([
                'windowdays' => new external_value(PARAM_INT, 'Window length in days.'),
                'sessions' => new external_value(PARAM_INT, 'Sessions in the window.'),
                'present' => new external_value(PARAM_INT, 'Sessions present or late.'),
                'rate' => new external_value(PARAM_FLOAT, 'Attendance rate 0-1, null if no data.', VALUE_OPTIONAL, null, NULL_ALLOWED),
            ]),
            'fees' => new external_single_structure([
                'hasaccount' => new external_value(PARAM_BOOL, 'Whether a fee account exists.'),
                'balance' => new external_value(PARAM_FLOAT, 'Outstanding balance in major units.'),
                'currency' => new external_value(PARAM_ALPHA, 'ISO currency code.'),
                'status' => new external_value(PARAM_INT, '0 current, 1 overdue, 2 paid, 3 failed-mandate.'),
            ]),
            'streak' => new external_single_structure([
                'current' => new external_value(PARAM_INT, 'Current streak.'),
                'longest' => new external_value(PARAM_INT, 'Longest streak.'),
                'lastactive' => new external_value(PARAM_INT, 'Last active timestamp.', VALUE_OPTIONAL, null, NULL_ALLOWED),
            ]),
            'struggletopics' => new external_multiple_structure(
                new external_single_structure([
                    'topic' => new external_value(PARAM_TEXT, 'Topic name.'),
                    'rate' => new external_value(PARAM_FLOAT, 'Wrong-answer rate 0-1.'),
                    'attempts' => new external_value(PARAM_INT, 'Attempts recorded.'),
                ])
            ),
        ]);
    }
}
