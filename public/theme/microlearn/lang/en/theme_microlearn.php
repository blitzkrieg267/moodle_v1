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
 * Strings for component 'theme_microlearn'.
 *
 * @package   theme_microlearn
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'KRECK MicroLearn';
$string['choosereadme'] = 'KRECK MicroLearn is a mobile-first child theme of Boost for the KRECK SMS + MicroLearn LMS platform. It retargets the Moodle Design System tokens to the KRECK brand (slate structure, teal primary action, amber accent) for a modern, low-bandwidth, card-based experience.';

// Settings pages.
$string['configtitle'] = 'KRECK MicroLearn settings';
$string['generalsettings'] = 'General';
$string['advancedsettings'] = 'Advanced';

// General settings.
$string['brandcolor'] = 'Brand colour';
$string['brandcolor_desc'] = 'Overrides the default KRECK teal used for primary actions and links. Leave blank to use the theme default (#1e8a74).';
$string['backgroundimage'] = 'Page background image';
$string['backgroundimage_desc'] = 'An optional background image shown behind pages on larger screens. Keep it light for low-bandwidth mobile users.';

// Advanced (raw SCSS) settings.
$string['rawscsspre'] = 'Raw initial SCSS';
$string['rawscsspre_desc'] = 'SCSS injected before the main styles compile. Use it to set SCSS variables (e.g. $primary).';
$string['rawscss'] = 'Raw SCSS';
$string['rawscss_desc'] = 'SCSS appended after all other styles, including the theme defaults. Use it for final overrides.';

// Block regions (inherited drawers layouts use side-pre).
$string['region-side-pre'] = 'Right';

// Privacy (no personal data stored by the theme).
$string['privacy:metadata'] = 'The KRECK MicroLearn theme does not store any personal data.';
