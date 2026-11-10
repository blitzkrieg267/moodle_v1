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
 * Per-learner, per-topic attempt/wrong counts for struggle analytics.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\persistent;

use core\persistent;

/**
 * Persistent for a learner's performance on one topic.
 */
class topicstat extends persistent {

    /** @var string Table name. */
    const TABLE = 'local_kreck_topicstat';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'userid' => [
                'type' => PARAM_INT,
            ],
            'topic' => [
                'type' => PARAM_TEXT,
            ],
            'attempts' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'wrong' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'lastseen' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    /**
     * Wrong-answer rate for this topic, 0-1.
     *
     * @return float
     */
    public function get_struggle_rate(): float {
        $attempts = (int) $this->get('attempts');
        if ($attempts <= 0) {
            return 0.0;
        }
        return $this->get('wrong') / $attempts;
    }
}
