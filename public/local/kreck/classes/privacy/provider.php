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
 * Privacy provider for local_kreck.
 *
 * All KRECK data is keyed by user id and stored in the system context.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy implementation.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /** @var string[] The plugin's user-keyed tables. */
    private const TABLES = [
        'local_kreck_student',
        'local_kreck_attendance',
        'local_kreck_feeaccount',
        'local_kreck_streak',
        'local_kreck_topicstat',
    ];

    /**
     * Describe the data stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_kreck_student', [
            'userid' => 'privacy:metadata:local_kreck_student:userid',
            'studentid' => 'privacy:metadata:local_kreck_student:studentid',
            'schoolid' => 'privacy:metadata:local_kreck_student:schoolid',
            'classname' => 'privacy:metadata:local_kreck_student:classname',
            'stream' => 'privacy:metadata:local_kreck_student:stream',
            'guardiancontact' => 'privacy:metadata:local_kreck_student:guardiancontact',
        ], 'privacy:metadata:local_kreck_student');

        $collection->add_database_table('local_kreck_attendance', [
            'userid' => 'privacy:metadata:local_kreck_attendance:userid',
            'sessiondate' => 'privacy:metadata:local_kreck_attendance:sessiondate',
            'status' => 'privacy:metadata:local_kreck_attendance:status',
        ], 'privacy:metadata:local_kreck_attendance');

        $collection->add_database_table('local_kreck_feeaccount', [
            'userid' => 'privacy:metadata:local_kreck_feeaccount:userid',
            'balancecents' => 'privacy:metadata:local_kreck_feeaccount:balancecents',
            'status' => 'privacy:metadata:local_kreck_feeaccount:status',
        ], 'privacy:metadata:local_kreck_feeaccount');

        $collection->add_database_table('local_kreck_streak', [
            'userid' => 'privacy:metadata:local_kreck_streak:userid',
            'current' => 'privacy:metadata:local_kreck_streak:current',
            'longest' => 'privacy:metadata:local_kreck_streak:longest',
        ], 'privacy:metadata:local_kreck_streak');

        $collection->add_database_table('local_kreck_topicstat', [
            'userid' => 'privacy:metadata:local_kreck_topicstat:userid',
            'topic' => 'privacy:metadata:local_kreck_topicstat:topic',
            'attempts' => 'privacy:metadata:local_kreck_topicstat:attempts',
            'wrong' => 'privacy:metadata:local_kreck_topicstat:wrong',
        ], 'privacy:metadata:local_kreck_topicstat');

        return $collection;
    }

    /**
     * Contexts containing data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();

        foreach (self::TABLES as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                $contextlist->add_system_context();
                break;
            }
        }

        return $contextlist;
    }

    /**
     * Users within a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        foreach (self::TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}}", []);
        }
    }

    /**
     * Export a user's data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $hassystem = false;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $hassystem = true;
                break;
            }
        }
        if (!$hassystem) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $context = context_system::instance();

        foreach (self::TABLES as $table) {
            $records = $DB->get_records($table, ['userid' => $userid]);
            if ($records) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_kreck'), $table],
                    (object) ['records' => array_values($records)]
                );
            }
        }
    }

    /**
     * Delete all users' data in a context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof context_system) {
            return;
        }
        foreach (self::TABLES as $table) {
            $DB->delete_records($table);
        }
    }

    /**
     * Delete one user's data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_system) {
                continue;
            }
            $userid = $contextlist->get_user()->id;
            foreach (self::TABLES as $table) {
                $DB->delete_records($table, ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete data for a set of users in a context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        foreach (self::TABLES as $table) {
            $DB->delete_records_select($table, "userid {$insql}", $inparams);
        }
    }
}
