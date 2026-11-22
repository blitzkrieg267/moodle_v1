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
 * The KRECK parent/guardian role (the "parent mirror").
 *
 * Moodle ships no parent role, so KRECK defines its own "KRECK Parent" role,
 * assignable only in a user's context (the standard mentor pattern). A parent
 * account is given this role in each of their children's user contexts during
 * sync, which lets them see that child's KRECK summary + fees and nothing else.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates and maintains the KRECK Parent role. Idempotent.
 */
class parentrole {

    /** @var string The role shortname KRECK owns. */
    const SHORTNAME = 'kreckparent';

    /**
     * Ensure the KRECK Parent role exists, is user-context assignable, and has
     * the right capabilities. Stores its id in config local_kreck/parentroleid.
     * Safe to call repeatedly (install and every upgrade).
     *
     * @return int the role id
     */
    public static function ensure(): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/lib/accesslib.php');

        $name = get_string('parentrole', 'local_kreck');
        $description = get_string('parentrole_desc', 'local_kreck');

        $existing = $DB->get_record('role', ['shortname' => self::SHORTNAME]);
        if ($existing) {
            $roleid = (int) $existing->id;
            // Refresh the stored name/description so it self-heals if the role
            // was first created before this plugin's lang strings were cached.
            if ($existing->name !== $name || $existing->description !== $description) {
                $DB->update_record('role', (object) [
                    'id' => $roleid, 'name' => $name, 'description' => $description,
                ]);
            }
        } else {
            $roleid = create_role(
                $name,
                self::SHORTNAME,
                $description,
                '' // No archetype: a mentor-style role with only the caps we grant below.
            );
        }

        // Assignable only in a user's context (the mentor pattern).
        set_role_contextlevels($roleid, [CONTEXT_USER]);

        // Role definition lives at the system context; the role is later assigned
        // in each child's user context, so the parent sees only their children.
        $systemcontext = \context_system::instance();
        assign_capability('local/kreck:viewchild', CAP_ALLOW, $roleid, $systemcontext->id, true);
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $roleid, $systemcontext->id, true);

        set_config('parentroleid', $roleid, 'local_kreck');
        return $roleid;
    }

    /**
     * The configured parent role id, (re)creating the role if the config is
     * missing or points at a role that no longer exists.
     *
     * @return int
     */
    public static function get_id(): int {
        global $DB;
        $roleid = (int) get_config('local_kreck', 'parentroleid');
        if ($roleid > 0 && $DB->record_exists('role', ['id' => $roleid])) {
            return $roleid;
        }
        return self::ensure();
    }
}
