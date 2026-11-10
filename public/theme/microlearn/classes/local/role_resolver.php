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
 * Maps a Moodle user to a KRECK dashboard role.
 *
 * @package   theme_microlearn
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_microlearn\local;

/**
 * Resolves which KRECK dashboard a user should see.
 */
class role_resolver {

    /** @var string Learner dashboard. */
    const ROLE_LEARNER = 'learner';
    /** @var string Teacher dashboard. */
    const ROLE_TEACHER = 'teacher';
    /** @var string Parent dashboard. */
    const ROLE_PARENT = 'parent';
    /** @var string Institution dashboard. */
    const ROLE_INSTITUTION = 'institution';

    /**
     * All valid roles.
     *
     * @return string[]
     */
    public static function all(): array {
        return [self::ROLE_LEARNER, self::ROLE_TEACHER, self::ROLE_PARENT, self::ROLE_INSTITUTION];
    }

    /**
     * Resolve the role for a user.
     *
     * Precedence: institution > teacher > parent > learner. Site admins get the
     * institution view, but may preview any dashboard with ?kreckrole=<role>.
     *
     * @param int $userid
     * @return string
     */
    public static function resolve(int $userid): string {
        // Admin preview override, handy for demos and QA.
        if (is_siteadmin()) {
            $override = optional_param('kreckrole', '', PARAM_ALPHA);
            if (in_array($override, self::all(), true)) {
                return $override;
            }
            return self::ROLE_INSTITUTION;
        }

        if (self::is_institution($userid)) {
            return self::ROLE_INSTITUTION;
        }
        if (self::is_teacher($userid)) {
            return self::ROLE_TEACHER;
        }
        if (self::is_parent($userid)) {
            return self::ROLE_PARENT;
        }
        return self::ROLE_LEARNER;
    }

    /**
     * Does the user hold a manager-like role?
     *
     * @param int $userid
     * @return bool
     */
    protected static function is_institution(int $userid): bool {
        global $DB;
        $sql = "SELECT 1
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid AND r.archetype = :archetype";
        return $DB->record_exists_sql($sql, ['userid' => $userid, 'archetype' => 'manager']);
    }

    /**
     * Does the user teach anywhere?
     *
     * @param int $userid
     * @return bool
     */
    protected static function is_teacher(int $userid): bool {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(['editingteacher', 'teacher'], SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $sql = "SELECT 1
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid AND r.archetype $insql";
        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Is the user a parent/mentor (holds a role in another user's context)?
     *
     * @param int $userid
     * @return bool
     */
    protected static function is_parent(int $userid): bool {
        global $DB;
        $sql = "SELECT 1
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid AND ctx.contextlevel = :userlevel";
        return $DB->record_exists_sql($sql, ['userid' => $userid, 'userlevel' => CONTEXT_USER]);
    }
}
