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
 * KRECK MicroLearn data backbone - version information.
 *
 * local_kreck holds the KRECK SMS (Layer 1) data that core Moodle does not:
 * the lifelong student record, attendance, fee accounts, learning streaks and
 * topic-struggle analytics, plus the web services that let the school system
 * and the mobile app read and write that one shared record.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026111106;        // YYYYMMDDXX.
$plugin->requires  = 2025041400;        // Moodle 5.0 and up.
$plugin->component = 'local_kreck';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
