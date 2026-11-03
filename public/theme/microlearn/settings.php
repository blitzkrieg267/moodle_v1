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
 * KRECK MicroLearn theme settings.
 *
 * Minimal admin surface for phase 1: brand colour, login background, and
 * raw SCSS escape hatches (read by the Boost-inherited pre/extra callbacks).
 *
 * @package   theme_microlearn
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs(
        'themesettingmicrolearn',
        get_string('configtitle', 'theme_microlearn')
    );

    // --- General tab --------------------------------------------------------
    $page = new admin_settingpage('theme_microlearn_general', get_string('generalsettings', 'theme_microlearn'));

    // Brand colour (overrides the default KRECK teal if set).
    $name = 'theme_microlearn/brandcolor';
    $title = get_string('brandcolor', 'theme_microlearn');
    $description = get_string('brandcolor_desc', 'theme_microlearn');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Login page background image.
    $name = 'theme_microlearn/backgroundimage';
    $title = get_string('backgroundimage', 'theme_microlearn');
    $description = get_string('backgroundimage_desc', 'theme_microlearn');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'backgroundimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);

    // --- Advanced tab (raw SCSS) -------------------------------------------
    $page = new admin_settingpage('theme_microlearn_advanced', get_string('advancedsettings', 'theme_microlearn'));

    // Raw SCSS injected before the compile (brand variables, etc.).
    $name = 'theme_microlearn/scsspre';
    $title = get_string('rawscsspre', 'theme_microlearn');
    $description = get_string('rawscsspre_desc', 'theme_microlearn');
    $setting = new admin_setting_scsscode($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Raw SCSS appended after the compile (final overrides).
    $name = 'theme_microlearn/scss';
    $title = get_string('rawscss', 'theme_microlearn');
    $description = get_string('rawscss_desc', 'theme_microlearn');
    $setting = new admin_setting_scsscode($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}
