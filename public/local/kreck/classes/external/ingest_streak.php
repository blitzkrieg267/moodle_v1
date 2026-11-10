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
 * External function: record a learner's app-engagement streak.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_kreck\persistent\streak;

/**
 * Create or update one learner's streak. The longest streak only ever grows.
 */
class ingest_streak extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner user id.'),
            'current' => new external_value(PARAM_INT, 'Current streak length in days.'),
            'longest' => new external_value(PARAM_INT, 'Longest streak; 0 = derive from current/existing.', VALUE_DEFAULT, 0),
            'lastactive' => new external_value(PARAM_INT, 'Last active timestamp; 0 = now.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Upsert the streak.
     *
     * @param int $userid
     * @param int $current
     * @param int $longest
     * @param int $lastactive
     * @return array
     */
    public static function execute(int $userid, int $current, int $longest = 0, int $lastactive = 0): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'current' => $current,
            'longest' => $longest,
            'lastactive' => $lastactive,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kreck:manage', $context);

        if (!$DB->record_exists('user', ['id' => $params['userid'], 'deleted' => 0])) {
            throw new \invalid_parameter_exception('Unknown learner user id.');
        }
        if ($params['current'] < 0 || $params['longest'] < 0) {
            throw new \invalid_parameter_exception('Streak values cannot be negative.');
        }

        $existing = streak::get_record(['userid' => $params['userid']]);
        $record = $existing ?: new streak();

        // The longest streak is the greatest of: supplied longest, the new
        // current, and whatever was stored before.
        $prevlongest = $existing ? (int) $existing->get('longest') : 0;
        $longest = max($params['longest'], $params['current'], $prevlongest);

        $record->set('userid', $params['userid']);
        $record->set('current', $params['current']);
        $record->set('longest', $longest);
        $record->set('lastactive', $params['lastactive'] ?: time());
        $record->save();

        return [
            'id' => (int) $record->get('id'),
            'result' => $existing ? 'updated' : 'created',
            'longest' => $longest,
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The streak record id.'),
            'result' => new external_value(PARAM_ALPHA, 'created or updated.'),
            'longest' => new external_value(PARAM_INT, 'The resulting longest streak.'),
        ]);
    }
}
