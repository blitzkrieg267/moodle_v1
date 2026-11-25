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
 * External function: apply a batch of SMS events to the LMS calendar, idempotently.
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
use local_kreck\events\mirror;

/**
 * Incremental event push: each SMS event becomes (or updates, or removes) the equivalent LMS calendar event.
 */
class sync_events extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'eventsjson' => new external_value(PARAM_RAW, 'JSON array of SMS events (source_id, op, name, start, end, scope, course(s)/users, ...).'),
            'timezone' => new external_value(PARAM_TIMEZONE, 'Timezone for events that do not carry one.', VALUE_DEFAULT, ''),
            'dryrun' => new external_value(PARAM_BOOL, 'Preview only; write nothing.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * @param string $eventsjson
     * @param string $timezone
     * @param bool $dryrun
     * @return array
     */
    public static function execute(string $eventsjson, string $timezone = '', bool $dryrun = false): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['eventsjson' => $eventsjson, 'timezone' => $timezone, 'dryrun' => $dryrun]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kreck:manage', $context);

        $events = json_decode($params['eventsjson'], true);
        if (!is_array($events)) {
            throw new \invalid_parameter_exception('eventsjson must be a JSON array of events.');
        }
        if (count($events) > mirror::MAX_EVENTS) {
            throw new \invalid_parameter_exception('Too many events in one call (maximum ' . mirror::MAX_EVENTS . ').');
        }
        $tz = $params['timezone'] !== '' ? $params['timezone'] : (get_config('core', 'timezone') ?: 'UTC');
        if ($tz === '99') {
            $tz = \core_date::get_server_timezone();
        }

        // One writer at a time so two overlapping pushes cannot both create the same event.
        $factory = \core\lock\lock_config::get_lock_factory('local_kreck_sync_events');
        $lock = $factory->get_lock('mirror', 15);
        if (!$lock) {
            throw new \moodle_exception('syncbusy', 'local_kreck');
        }
        try {
            $result = (new mirror())->apply($events, $tz, (bool) $params['dryrun']);
        } finally {
            $lock->release();
        }

        return [
            'status' => $params['dryrun'] ? 'validated' : 'applied',
            'created' => (int) $result['counts']['created'],
            'updated' => (int) $result['counts']['updated'],
            'unchanged' => (int) $result['counts']['unchanged'],
            'deleted' => (int) $result['counts']['deleted'],
            'skipped' => (int) $result['counts']['skipped'],
            'errors' => (int) $result['counts']['errors'],
            'results' => $result['results'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'applied or validated (dry run).'),
            'created' => new external_value(PARAM_INT, 'Calendar events created.'),
            'updated' => new external_value(PARAM_INT, 'Calendar events updated.'),
            'unchanged' => new external_value(PARAM_INT, 'Calendar events already up to date.'),
            'deleted' => new external_value(PARAM_INT, 'Calendar events removed.'),
            'skipped' => new external_value(PARAM_INT, 'Audiences not found on the LMS yet (course or user missing).'),
            'errors' => new external_value(PARAM_INT, 'SMS events that could not be applied.'),
            'results' => new external_multiple_structure(new external_single_structure([
                'source_id' => new external_value(PARAM_RAW, 'The SMS event id.'),
                'result' => new external_value(PARAM_ALPHA, 'created, updated, unchanged, deleted, skipped or error.'),
                'message' => new external_value(PARAM_RAW, 'Detail for skipped/error results.', VALUE_DEFAULT, ''),
            ])),
        ]);
    }
}
