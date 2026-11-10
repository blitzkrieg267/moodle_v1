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
 * External function: record per-topic attempt/wrong counts (struggle data).
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
use local_kreck\persistent\topicstat;

/**
 * Create or update one learner's stats for a topic.
 */
class ingest_topicstat extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner user id.'),
            'topic' => new external_value(PARAM_TEXT, 'Topic name.'),
            'attempts' => new external_value(PARAM_INT, 'Total attempts recorded for the topic.'),
            'wrong' => new external_value(PARAM_INT, 'Wrong answers (clamped to attempts).'),
            'mode' => new external_value(PARAM_ALPHA, '"set" (default) replaces counts, "add" increments.',
                VALUE_DEFAULT, 'set'),
            'lastseen' => new external_value(PARAM_INT, 'Last seen timestamp; 0 = now.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Upsert the topic stats.
     *
     * @param int $userid
     * @param string $topic
     * @param int $attempts
     * @param int $wrong
     * @param string $mode
     * @param int $lastseen
     * @return array
     */
    public static function execute(int $userid, string $topic, int $attempts, int $wrong,
            string $mode = 'set', int $lastseen = 0): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'topic' => $topic,
            'attempts' => $attempts,
            'wrong' => $wrong,
            'mode' => $mode,
            'lastseen' => $lastseen,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kreck:manage', $context);

        if (!$DB->record_exists('user', ['id' => $params['userid'], 'deleted' => 0])) {
            throw new \invalid_parameter_exception('Unknown learner user id.');
        }
        if (trim($params['topic']) === '') {
            throw new \invalid_parameter_exception('Topic is required.');
        }
        if ($params['attempts'] < 0 || $params['wrong'] < 0) {
            throw new \invalid_parameter_exception('Counts cannot be negative.');
        }

        $existing = topicstat::get_record(['userid' => $params['userid'], 'topic' => $params['topic']]);
        $record = $existing ?: new topicstat();

        $attempts = $params['attempts'];
        $wrong = $params['wrong'];
        if ($params['mode'] === 'add' && $existing) {
            $attempts += (int) $existing->get('attempts');
            $wrong += (int) $existing->get('wrong');
        }
        $wrong = min($wrong, $attempts);

        $record->set('userid', $params['userid']);
        $record->set('topic', $params['topic']);
        $record->set('attempts', $attempts);
        $record->set('wrong', $wrong);
        $record->set('lastseen', $params['lastseen'] ?: time());
        $record->save();

        return [
            'id' => (int) $record->get('id'),
            'result' => $existing ? 'updated' : 'created',
            'attempts' => $attempts,
            'wrong' => $wrong,
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The topicstat record id.'),
            'result' => new external_value(PARAM_ALPHA, 'created or updated.'),
            'attempts' => new external_value(PARAM_INT, 'Resulting attempts.'),
            'wrong' => new external_value(PARAM_INT, 'Resulting wrong count.'),
        ]);
    }
}
