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
 * Institution-wide LMS calendar for KRECK administrators.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php'); // local_kreck_get_school_calendar_course_ids() lives here.
\local_kreck\diagnostics::register(__FILE__);

require_login();

global $CFG, $PAGE, $OUTPUT, $SITE;

$context = context_system::instance();
require_capability('local/kreck:viewinstitution', $context);

$time = optional_param('time', time(), PARAM_INT);
$pageurl = new moodle_url('/local/kreck/school_calendar.php', ['time' => $time]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('landing_calendar_school_title', 'local_kreck'));
$PAGE->set_heading(format_string($SITE->fullname));

require_once($CFG->dirroot . '/calendar/lib.php');
$calendar = calendar_information::create($time, SITEID);
$calendar->courses = local_kreck_get_school_calendar_course_ids();

// Server-rendered month kept only as a no-JS fallback; the KRECK widget below
// takes over and navigates month/week/day/agenda in place.
$renderer = $PAGE->get_renderer('core_calendar');
[$calendardata, $calendartemplate] = calendar_get_view($calendar, 'month', true, false);
$fallbackhtml = $renderer->render_from_template($calendartemplate, $calendardata);
$PAGE->requires->js_call_amd('core_calendar/popover');

$calendarconfig = json_encode(
    ['courseid' => (int) SITEID, 'view' => 'month'],
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
$calendarwidgetjs = file_get_contents(__DIR__ . '/calendar_widget.js');
if ($calendarwidgetjs !== false) {
    $PAGE->requires->js_init_code($calendarwidgetjs, true);
}

echo $OUTPUT->header();
?>
<style>
.kl-school-cal{background:radial-gradient(900px 500px at 100% 0,rgba(82,112,31,.18),transparent 60%),#10150d;
  color:#edf1e6;border-radius:22px;padding:clamp(1.2rem,3vw,2rem);margin:1rem 0;
  box-shadow:0 10px 30px rgba(15,30,46,.12);}
.kl-school-cal h2{font-family:Georgia,"Times New Roman",Times,serif;color:#fff;margin:0 0 .3rem;}
.kl-school-cal .kl-sub{color:#c7d1da;margin:0 0 1.2rem;}
</style>
<div class="kl-school-cal">
  <h2><?php echo s(get_string('landing_calendar_school_title', 'local_kreck')); ?></h2>
  <p class="kl-sub"><?php echo s(format_string($SITE->fullname)); ?></p>
  <div data-kreck-calendar data-config='<?php echo $calendarconfig; ?>'>
    <noscript><?php echo $fallbackhtml; ?></noscript>
  </div>
</div>
<?php
echo $OUTPUT->footer();
