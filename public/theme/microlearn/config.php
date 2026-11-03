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
 * KRECK MicroLearn theme config.
 *
 * This is a child of theme_boost. It changes NOTHING in core or in Boost:
 * the whole re-skin is delivered additively through SCSS token overrides
 * (see scss/) so that Moodle point upgrades stay safe. Layouts and
 * templates are inherited from Boost until role-specific overrides land
 * in later phases.
 *
 * @package   theme_microlearn
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'microlearn';

// Inherit everything structural from Boost.
$THEME->parents = ['boost'];

// We ship no static sheets; all CSS is compiled from SCSS (see lib.php).
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->usefallback = true;

// SCSS pipeline: Boost's full preset (Bootstrap + Moodle Design System +
// Moodle component styles) as the base, with KRECK brand variables injected
// before it (pre) and KRECK design-token overrides appended after it (extra).
$THEME->scss = function($theme) {
    return theme_microlearn_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_microlearn_get_pre_scss';
$THEME->extrascsscallback = 'theme_microlearn_get_extra_scss';

// Match Boost's modern behaviour.
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->activityheaderconfig = [
    'notitle' => true,
];

// Layouts are inherited from Boost (drawers-based, responsive). Role-specific
// layouts (e.g. a bespoke mydashboard) will be registered here in a later phase.
