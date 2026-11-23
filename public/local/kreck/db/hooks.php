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
 * KRECK hook callbacks.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \local_kreck\hook_listener::class . '::add_theme_contrast_styles',
    ],
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \local_kreck\hook_listener::class . '::add_sms_link',
    ],
    [
        // Restyle the login page to the KRECK glass/brand aesthetic.
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \local_kreck\hook_listener::class . '::style_login_page',
    ],
    [
        // Point the navbar logo at the KRECK welcome page (static brand anchor).
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \local_kreck\hook_listener::class . '::retarget_logo',
    ],
    [
        // Add a primary-nav "Home" item that opens the KRECK welcome page.
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => \local_kreck\hook_listener::class . '::extend_primary_navigation',
    ],
    [
        // Redirect the site front page to the KRECK welcome page (modern
        // replacement for the legacy local_kreck_after_config() callback).
        'hook' => \core\hook\after_config::class,
        'callback' => \local_kreck\hook_listener::class . '::after_config',
    ],
];
