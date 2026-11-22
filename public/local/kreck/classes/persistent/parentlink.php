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
 * A parent/guardian ↔ child (learner) link, mirrored from the KRECK SMS family.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\persistent;

use core\persistent;

/**
 * Persistent for a parent↔child link. Unique on (parentuserid, childuserid),
 * which makes re-sync idempotent and backs the parent "My children" view.
 */
class parentlink extends persistent {

    /** @var string Table name. */
    const TABLE = 'local_kreck_parentlink';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'parentuserid' => [
                'type' => PARAM_INT,
            ],
            'childuserid' => [
                'type' => PARAM_INT,
            ],
            'sourceid' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'contactpriority' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
        ];
    }

    /**
     * The child user ids a given parent is linked to.
     *
     * @param int $parentuserid
     * @return int[] child user ids
     */
    public static function child_userids(int $parentuserid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select(
            self::TABLE, 'childuserid', 'parentuserid = :p', ['p' => $parentuserid]
        ));
    }
}
