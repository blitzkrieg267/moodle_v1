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
 * KRECK MicroLearn settings.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_kreck', get_string('pluginname', 'local_kreck'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_kreck/enabled',
        get_string('enabled', 'local_kreck'),
        get_string('enabled_desc', 'local_kreck'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_kreck/defaultcurrency',
        get_string('defaultcurrency', 'local_kreck'),
        get_string('defaultcurrency_desc', 'local_kreck'),
        'BWP',
        PARAM_ALPHA,
        4
    ));

    // Base URL of the KRECK SMS (Gibbon), used to build "View in KRECK SMS" deep-links.
    $settings->add(new admin_setting_configtext(
        'local_kreck/gibbonbaseurl',
        get_string('gibbonbaseurl', 'local_kreck'),
        get_string('gibbonbaseurl_desc', 'local_kreck'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_kreck/attendancewindow',
        get_string('attendancewindow', 'local_kreck'),
        get_string('attendancewindow_desc', 'local_kreck'),
        30,
        PARAM_INT,
        4
    ));

    // From the KRECK model: whether fee status may gate app access is a
    // school-level decision, off by default.
    $settings->add(new admin_setting_configcheckbox(
        'local_kreck/feegatesapp',
        get_string('feegatesapp', 'local_kreck'),
        get_string('feegatesapp_desc', 'local_kreck'),
        0
    ));

    // Starter password for provisioned users.
    $settings->add(new admin_setting_configtext(
        'local_kreck/defaultpassword',
        get_string('defaultpassword', 'local_kreck'),
        get_string('defaultpassword_desc', 'local_kreck'),
        '',
        PARAM_RAW_TRIMMED
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_kreck/forcepasswordchange',
        get_string('forcepasswordchange', 'local_kreck'),
        get_string('forcepasswordchange_desc', 'local_kreck'),
        1
    ));

    // Public landing page.
    $settings->add(new admin_setting_configcheckbox(
        'local_kreck/landingenabled',
        get_string('landingenabled', 'local_kreck'),
        get_string('landingenabled_desc', 'local_kreck'),
        1
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_kreck/landingintro',
        get_string('landingintro', 'local_kreck'),
        get_string('landingintro_desc', 'local_kreck'),
        '',
        PARAM_TEXT
    ));

    // Public landing page: all-course glass wall with live stats.
    $settings->add(new admin_setting_configcheckbox(
        'local_kreck/landingcourses',
        get_string('landingcourses', 'local_kreck'),
        get_string('landingcourses_desc', 'local_kreck'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_kreck/landingcoursesdebug',
        get_string('landingcoursesdebug', 'local_kreck'),
        get_string('landingcoursesdebug_desc', 'local_kreck'),
        0
    ));

    // Changelog awareness: after an upgrade, show the admin what changed in
    // this version. Marking it read records the version so it won't repeat.
    $changelogseen = local_kreck_get_changelog_seen_version();
    if ($changelogseen < $plugin->version) {
        $changelog = local_kreck_get_changelog();
        if ($changelog !== '') {
            $markurl = new moodle_url('/admin/settings.php', [
                'section' => 'localpluginslocal_kreck',
                'changelogseen' => 1,
            ]);
            if (!empty($_GET['changelogseen'])) {
                local_kreck_mark_changelog_seen($plugin->version);
            }
            $settings->add(new admin_setting_configstatic(
                'local_kreck/changelognotice',
                get_string('changelogtitle', 'local_kreck'),
                $changelog . '<p class="changelog-seen"><a href="' . $markurl->out(false) . '">' . get_string('changelogmarkseen', 'local_kreck') . '</a></p>'
            ));
        }
    }
}

// Admin tool: seed sample KRECK data onto a learner (capability-gated, so a
// manager with local/kreck:manage can reach it without full site config).
$ADMIN->add('localplugins', new admin_externalpage(
    'local_kreck_seed',
    get_string('seedpage', 'local_kreck'),
    new moodle_url('/local/kreck/seed.php'),
    'local/kreck:manage'
));

// Onboarding & provisioning console.
$ADMIN->add('localplugins', new admin_externalpage(
    'local_kreck_onboarding',
    get_string('onboarding', 'local_kreck'),
    new moodle_url('/local/kreck/onboarding.php'),
    'local/kreck:manage'
));

// Smart importer (map any spreadsheet).
$ADMIN->add('localplugins', new admin_externalpage(
    'local_kreck_import',
    get_string('import_pagename', 'local_kreck'),
    new moodle_url('/local/kreck/import.php'),
    'local/kreck:manage'
));

// LMS -> SMS link export (courses + activities back to Gibbon).
$ADMIN->add('localplugins', new admin_externalpage(
    'local_kreck_exportlinks',
    get_string('exportlinks', 'local_kreck'),
    new moodle_url('/local/kreck/export_links.php'),
    'local/kreck:manage'
));

// LMS -> SMS marks export (gradebook back to Gibbon record-keeping).
$ADMIN->add('localplugins', new admin_externalpage(
    'local_kreck_exportmarks',
    get_string('exportmarks', 'local_kreck'),
    new moodle_url('/local/kreck/export_marks.php'),
    'local/kreck:manage'
));
