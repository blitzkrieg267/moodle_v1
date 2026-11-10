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
 * A single attendance mark for a learner on a session day.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\persistent;

use core\persistent;

/**
 * Persistent for one day's attendance record.
 */
class attendance extends persistent {

    /** @var string Table name. */
    const TABLE = 'local_kreck_attendance';

    /** @var int Present. */
    const STATUS_PRESENT = 0;
    /** @var int Absent. */
    const STATUS_ABSENT = 1;
    /** @var int Late. */
    const STATUS_LATE = 2;
    /** @var int Excused. */
    const STATUS_EXCUSED = 3;

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
            'sessiondate' => [
                'type' => PARAM_INT,
            ],
            'status' => [
                'type' => PARAM_INT,
                'default' => self::STATUS_PRESENT,
                'choices' => [
                    self::STATUS_PRESENT,
                    self::STATUS_ABSENT,
                    self::STATUS_LATE,
                    self::STATUS_EXCUSED,
                ],
            ],
            'classname' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'source' => [
                'type' => PARAM_ALPHANUMEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'note' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }
}
