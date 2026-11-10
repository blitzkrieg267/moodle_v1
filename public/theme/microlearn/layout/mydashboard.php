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
 * KRECK role-aware dashboard layout.
 *
 * Builds the standard drawers chrome, then resolves the viewer's KRECK role
 * and renders a bespoke, block-free dashboard above the normal main content.
 *
 * @package   theme_microlearn
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');

$addblockbutton = $OUTPUT->addblockbutton();

$blockdraweropen = (get_user_preferences('drawer-open-block') == true);
if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers', 'kreck-dashboard'];
$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);

// Resolve the KRECK role and build its dashboard.
$kreckroledashboard = '';
$hasroledashboard = false;
if (isloggedin() && !isguestuser()) {
    try {
        $role = \theme_microlearn\local\role_resolver::resolve((int) $USER->id);
        $dashboardcontext = \theme_microlearn\local\dashboard::context_for($role, (int) $USER->id);
        $kreckroledashboard = $OUTPUT->render_from_template('theme_microlearn/dashboard/' . $role, $dashboardcontext);
        $hasroledashboard = true;
    } catch (\Throwable $e) {
        // Never let the dashboard widget break the page; fall back to standard content.
        $kreckroledashboard = '';
        $hasroledashboard = false;
        debugging('KRECK dashboard render failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'blockdraweropen' => $blockdraweropen,
    'primarymoremenu' => $primarymenu['moremenu'],
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'addblockbutton' => $addblockbutton,
    'kreckroledashboard' => $kreckroledashboard,
    'hasroledashboard' => $hasroledashboard,
];

echo $OUTPUT->render_from_template('theme_microlearn/mydashboard', $templatecontext);
