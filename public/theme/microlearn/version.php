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
 * KRECK MicroLearn theme version information.
 *
 * A Boost child theme that re-skins Moodle into the KRECK MicroLearn
 * mobile-first dual-layer platform (KRECK SMS + MicroLearn LMS).
 *
 * @package   theme_microlearn
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026110300;        // YYYYMMDDXX.
$plugin->requires  = 2026100200;        // Requires this Moodle 6.0 core (matches theme_boost).
$plugin->component = 'theme_microlearn';
$plugin->dependencies = [
    'theme_boost' => 2026100500,
];
