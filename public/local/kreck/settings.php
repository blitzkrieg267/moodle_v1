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
}

// Admin tool: seed sample KRECK data onto a learner (capability-gated, so a
// manager with local/kreck:manage can reach it without full site config).
$ADMIN->add('localplugins', new admin_externalpage(
    'local_kreck_seed',
    get_string('seedpage', 'local_kreck'),
    new moodle_url('/local/kreck/seed.php'),
    'local/kreck:manage'
));
