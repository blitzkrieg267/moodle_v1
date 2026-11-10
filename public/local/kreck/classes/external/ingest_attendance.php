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
 * External function: record an attendance mark (KRECK register sync).
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
use local_kreck\persistent\attendance;

/**
 * Create or update one learner's attendance for a given day.
 */
class ingest_attendance extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner user id.'),
            'sessiondate' => new external_value(PARAM_INT, 'Midnight timestamp of the session day.'),
            'status' => new external_value(PARAM_INT, '0 present, 1 absent, 2 late, 3 excused.'),
            'classname' => new external_value(PARAM_TEXT, 'Class name.', VALUE_DEFAULT, null),
            'source' => new external_value(PARAM_ALPHANUMEXT, 'Origin of the mark, e.g. sms.', VALUE_DEFAULT, 'sms'),
            'note' => new external_value(PARAM_TEXT, 'Optional note.', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Upsert the attendance record.
     *
     * @param int $userid
     * @param int $sessiondate
     * @param int $status
     * @param string|null $classname
     * @param string|null $source
     * @param string|null $note
     * @return array
     */
    public static function execute(
        int $userid,
        int $sessiondate,
        int $status,
        ?string $classname = null,
        ?string $source = 'sms',
        ?string $note = null
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'sessiondate' => $sessiondate,
            'status' => $status,
            'classname' => $classname,
            'source' => $source,
            'note' => $note,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kreck:manage', $context);

        if (!$DB->record_exists('user', ['id' => $params['userid'], 'deleted' => 0])) {
            throw new \invalid_parameter_exception('Unknown learner user id.');
        }

        // Normalise the session date to midnight so there is one row per day.
        $day = usergetmidnight($params['sessiondate']);

        $existing = attendance::get_record(['userid' => $params['userid'], 'sessiondate' => $day]);
        $record = $existing ?: new attendance();
        $record->set('userid', $params['userid']);
        $record->set('sessiondate', $day);
        $record->set('status', $params['status']);
        $record->set('classname', $params['classname']);
        $record->set('source', $params['source']);
        $record->set('note', $params['note']);
        $record->save();

        return [
            'id' => (int) $record->get('id'),
            'result' => $existing ? 'updated' : 'created',
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The attendance record id.'),
            'result' => new external_value(PARAM_ALPHA, 'created or updated.'),
        ]);
    }
}
