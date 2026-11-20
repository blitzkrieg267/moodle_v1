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
 * local_kreck install-time hook.
 *
 * Fresh installs do not run db/upgrade.php, so the host security hardening that
 * "secures the app by virtue of installing KRECK" is applied here as well. It is
 * the same only-tightening, reversible, idempotent routine the upgrade path uses.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run on fresh install of local_kreck.
 *
 * @return bool
 */
function xmldb_local_kreck_install() {
    \local_kreck\hardening::apply();
    return true;
}
