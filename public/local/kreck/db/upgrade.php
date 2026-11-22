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
 * local_kreck upgrade steps.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade local_kreck.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_kreck_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026111107) {
        // Cross-system deep-link store (SMS <-> LMS).
        $table = new xmldb_table('local_kreck_link');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('entitytype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('entitykey', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('direction', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'togibbon');
        $table->add_field('relurl', XMLDB_TYPE_CHAR, '500', null, XMLDB_NOTNULL, null, null);
        $table->add_field('label', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('extid', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('entity-direction', XMLDB_INDEX_UNIQUE, ['entitytype', 'entitykey', 'direction']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026111107, 'local', 'kreck');
    }

    if ($oldversion < 2026111301) {
        // Landing page UX refresh: frosted-glass featured course cards with live
        // stats (activities, lessons, discussions) and read-more descriptions.
        // Records the changelog version so admins can see what changed on upgrade.
        set_config('local_kreck/changelogversion', 2026111301);

        upgrade_plugin_savepoint(true, 2026111301, 'local', 'kreck');
    }

    if ($oldversion < 2026111302) {
        // Version bump to force upgrade recognition and cache invalidation.
        set_config('local_kreck/changelogversion', 2026111302);

        upgrade_plugin_savepoint(true, 2026111302, 'local', 'kreck');
    }

    if ($oldversion < 2026111303) {
        // Accessible, independently actionable featured course cards.
        set_config('local_kreck/changelogversion', 2026111303);

        upgrade_plugin_savepoint(true, 2026111303, 'local', 'kreck');
    }

    if ($oldversion < 2026111304) {
        // Replace the duplicate course listings with the searchable course wall.
        set_config('local_kreck/changelogversion', 2026111304);

        upgrade_plugin_savepoint(true, 2026111304, 'local', 'kreck');
    }

    if ($oldversion < 2026111305) {
        // Animated, directionally navigable course wall with a six-card window.
        set_config('local_kreck/changelogversion', 2026111305);

        upgrade_plugin_savepoint(true, 2026111305, 'local', 'kreck');
    }

    if ($oldversion < 2026111306) {
        // Full-width landing page and theme dropdown contrast improvements.
        set_config('local_kreck/changelogversion', 2026111306);

        upgrade_plugin_savepoint(true, 2026111306, 'local', 'kreck');
    }

    if ($oldversion < 2026111307) {
        // Personalized course wall and LMS calendar dashboard for learners.
        set_config('local_kreck/changelogversion', 2026111307);

        upgrade_plugin_savepoint(true, 2026111307, 'local', 'kreck');
    }

    if ($oldversion < 2026111308) {
        $table = new xmldb_table('local_kreck_sync_receipt');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('requestid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('digest', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('response', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('requestid', XMLDB_INDEX_UNIQUE, ['requestid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_kreck_sync_event');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('eventid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('eventtype', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, null);
        $table->add_field('entitytype', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('entityid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextrun', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastresponse', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('eventid', XMLDB_INDEX_UNIQUE, ['eventid']);
        $table->add_index('delivery', XMLDB_INDEX_NOTUNIQUE, ['status', 'nextrun']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        set_config('local_kreck/changelogversion', 2026111308);
        upgrade_plugin_savepoint(true, 2026111308, 'local', 'kreck');
    }

    if ($oldversion < 2026111309) {
        set_config('local_kreck/changelogversion', 2026111309);
        upgrade_plugin_savepoint(true, 2026111309, 'local', 'kreck');
    }

    if ($oldversion < 2026111320) {
        set_config('local_kreck/changelogversion', 2026111320);

        upgrade_plugin_savepoint(true, 2026111320, 'local', 'kreck');
    }

    if ($oldversion < 2026111403) {
        set_config('local_kreck/changelogversion', 2026111403);
        set_config('gibbonheartbeatstatus', 'not_checked', 'local_kreck');

        external_update_descriptions('local_kreck');

        upgrade_plugin_savepoint(true, 2026111403, 'local', 'kreck');
    }

    if ($oldversion < 2026111901) {
        // "Secure the app by virtue of installing KRECK": tighten the host LMS's
        // own security posture (OWASP A05/A07). Only ever strengthens settings;
        // prior values are recorded to local_kreck/hardening_prior for review or
        // revert. Idempotent, so it is also safe to re-run on future upgrades.
        \local_kreck\hardening::apply();

        set_config('local_kreck/changelogversion', 2026111901);
        upgrade_plugin_savepoint(true, 2026111901, 'local', 'kreck');
    }

    if ($oldversion < 2026112100) {
        // Register the new local_kreck_set_webhook_secret web service, so the
        // paired KRECK SMS can push the shared HMAC secret over the token channel
        // (fixes the LMS->SMS webhook failing when the two secrets don't match).
        external_update_descriptions('local_kreck');

        set_config('local_kreck/changelogversion', 2026112100);
        upgrade_plugin_savepoint(true, 2026112100, 'local', 'kreck');
    }

    if ($oldversion < 2026112200) {
        // Parent mirror: a table of parent↔child links, and the KRECK Parent
        // role (user-context assignable) that lets a parent see their child's
        // KRECK summary + fees. The new local/kreck:viewchild capability is
        // registered by Moodle right after this step from db/access.php.
        $table = new xmldb_table('local_kreck_parentlink');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('parentuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('childuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sourceid', XMLDB_TYPE_CHAR, '64', null, null, null, null);
            $table->add_field('contactpriority', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('parentuserid', XMLDB_KEY_FOREIGN, ['parentuserid'], 'user', ['id']);
            $table->add_key('childuserid', XMLDB_KEY_FOREIGN, ['childuserid'], 'user', ['id']);
            $table->add_index('parentchild', XMLDB_INDEX_UNIQUE, ['parentuserid', 'childuserid']);
            $dbman->create_table($table);
        }

        // Register the new capability first, then create the role that holds it.
        update_capabilities('local_kreck');
        // Make sure the plugin's new lang strings are readable now, so the role
        // is created with its proper name (not a "[[parentrole]]" placeholder)
        // even if the string cache predates this upgrade.
        get_string_manager()->reset_caches();
        \local_kreck\parentrole::ensure();

        set_config('local_kreck/changelogversion', 2026112200);
        upgrade_plugin_savepoint(true, 2026112200, 'local', 'kreck');
    }

    if ($oldversion < 2026112201) {
        // Migrated the legacy local_kreck_after_config() callback to the modern
        // \core\hook\after_config hook (registered in db/hooks.php). No schema
        // change; the hook registry is refreshed automatically on upgrade.
        upgrade_plugin_savepoint(true, 2026112201, 'local', 'kreck');
    }

    if ($oldversion < 2026112202) {
        // Added the one-click "Seed LMS Demo" admin page (classes/demo_seeder.php
        // + demo_seed.php, registered in settings.php). No DB/schema change; the
        // admin tree is rebuilt automatically after upgrade.
        upgrade_plugin_savepoint(true, 2026112202, 'local', 'kreck');
    }

    return true;
}
