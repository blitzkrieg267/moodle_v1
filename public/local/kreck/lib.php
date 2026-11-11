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
 * KRECK MicroLearn data plugin library callbacks.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Send anonymous visitors from the site front page to the public KRECK landing
 * page. Runs early (before the front page's force-login check), so it works
 * even when the site forces login — logged-in users are never redirected.
 *
 * @return void
 */
function local_kreck_after_config(): void {
    global $CFG, $USER;

    if (CLI_SCRIPT || (defined('AJAX_SCRIPT') && AJAX_SCRIPT) || (defined('WS_SERVER') && WS_SERVER)) {
        return;
    }
    if (during_initial_install()) {
        return;
    }
    if (!get_config('local_kreck', 'landingenabled')) {
        return;
    }
    // Only anonymous visitors; logged-in users keep their normal front page.
    if (isloggedin() && !isguestuser()) {
        return;
    }

    $me = qualified_me();
    if (!$me) {
        return;
    }
    $path = strtok($me, '?');
    $isfrontpage = in_array($path, [
        $CFG->wwwroot,
        $CFG->wwwroot . '/',
        $CFG->wwwroot . '/index.php',
    ], true);
    if (!$isfrontpage) {
        return;
    }
    // Never get in the way of an upgrade.
    if (moodle_needs_upgrading()) {
        return;
    }

    redirect(new moodle_url('/local/kreck/welcome.php'));
}
