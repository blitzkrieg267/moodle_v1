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
 * A cross-system deep-link to the KRECK SMS (Gibbon).
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\persistent;

use core\persistent;

/**
 * Persistent for a stored back-link (course/user -> Gibbon relative URL).
 */
class link extends persistent {

    /** @var string Table name. */
    const TABLE = 'local_kreck_link';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'entitytype' => [
                'type' => PARAM_ALPHA,
            ],
            'entitykey' => [
                'type' => PARAM_RAW,
            ],
            'direction' => [
                'type' => PARAM_ALPHA,
                'default' => 'togibbon',
            ],
            'relurl' => [
                'type' => PARAM_RAW,
            ],
            'label' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'extid' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }
}
