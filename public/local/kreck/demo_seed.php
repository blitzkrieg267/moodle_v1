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
 * KRECK — one-click LMS demo seeder (admin page).
 *
 * A friendly wrapper around \local_kreck\demo_seeder: one click stands up a
 * complete demo on the LMS — 15 logins, a demo course with teachers + students,
 * an Assignment with marks, the KRECK learner analytics, and the parent mirror.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
\local_kreck\diagnostics::register(__FILE__);
require_once("{$CFG->libdir}/adminlib.php");

admin_externalpage_setup('local_kreck_demoseed');

$context = context_system::instance();
require_capability('local/kreck:manage', $context);

$PAGE->set_url(new moodle_url('/local/kreck/demo_seed.php'));
$PAGE->set_title(get_string('demoseed', 'local_kreck'));
$PAGE->set_heading(get_string('demoseed', 'local_kreck'));

$seedResult = null;
$wipeCount = null;

if (data_submitted() && confirm_sesskey()) {
    $action = optional_param('kreckaction', '', PARAM_ALPHA);
    $seeder = new \local_kreck\demo_seeder();
    if ($action === 'seed') {
        $seedResult = $seeder->seed();
    } else if ($action === 'wipe') {
        $wipeCount = $seeder->wipe();
    }
}

$demoCount = $DB->count_records_select('user', "username LIKE 'demo.%' AND deleted = 0");
$roleName = ['staff' => 'Staff', 'teacher' => 'Teacher', 'student' => 'Student', 'parent' => 'Parent'];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('demoseed', 'local_kreck'));

echo html_writer::tag('p', get_string('demoseed_intro', 'local_kreck'), ['class' => 'lead']);

// What the run adds.
$items = [
    get_string('demoseed_i_logins', 'local_kreck'),
    get_string('demoseed_i_course', 'local_kreck'),
    get_string('demoseed_i_assign', 'local_kreck'),
    get_string('demoseed_i_analytics', 'local_kreck'),
    get_string('demoseed_i_parents', 'local_kreck'),
];
echo html_writer::start_div('card', ['style' => 'max-width:860px;padding:1rem 1.25rem;margin:1rem 0;border-left:4px solid #0e9f9a;']);
echo html_writer::tag('strong', get_string('demoseed_adds', 'local_kreck'));
echo html_writer::alist($items, ['style' => 'margin:.5rem 0 0;']);
echo html_writer::tag('p', get_string('demoseed_idempotent', 'local_kreck'),
    ['style' => 'background:#fef3c7;color:#92400e;border-radius:8px;padding:.6rem .9rem;font-size:13px;margin:.8rem 0 .4rem;']);
if ($demoCount > 0) {
    echo html_writer::tag('p', get_string('demoseed_present', 'local_kreck', $demoCount), ['class' => 'text-muted', 'style' => 'font-size:13px;']);
}

// Seed button.
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'style' => 'display:inline-block;margin-right:.5rem;']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'kreckaction', 'value' => 'seed']);
echo html_writer::tag('button', $demoCount > 0 ? get_string('demoseed_reseed', 'local_kreck') : get_string('demoseed_run', 'local_kreck'),
    ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

// Remove button.
if ($demoCount > 0) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'style' => 'display:inline-block;',
        'onsubmit' => "return confirm('" . get_string('demoseed_confirmremove', 'local_kreck') . "');"]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'kreckaction', 'value' => 'wipe']);
    echo html_writer::tag('button', get_string('demoseed_remove', 'local_kreck'), ['type' => 'submit', 'class' => 'btn btn-danger']);
    echo html_writer::end_tag('form');
}
echo html_writer::end_div();

// Results.
if ($wipeCount !== null) {
    echo $OUTPUT->notification(get_string('demoseed_removed', 'local_kreck', $wipeCount), \core\output\notification::NOTIFY_SUCCESS);
}

if ($seedResult !== null) {
    if (!empty($seedResult['ok'])) {
        echo $OUTPUT->notification(get_string('demoseed_done', 'local_kreck'), \core\output\notification::NOTIFY_SUCCESS);
    }
    // Phase log.
    echo html_writer::start_div('card', ['style' => 'max-width:860px;padding:1rem 1.25rem;margin:1rem 0;']);
    echo html_writer::tag('strong', get_string('demoseed_whathappened', 'local_kreck'));
    echo html_writer::tag('pre', s(implode("\n", $seedResult['log'])),
        ['style' => 'background:#0f1b14;color:#d7f0e4;border-radius:10px;padding:.9rem 1.1rem;font-size:12.5px;line-height:1.6;white-space:pre-wrap;margin-top:.5rem;']);
    echo html_writer::end_div();

    // Credentials table.
    $table = new html_table();
    $table->head = [get_string('username'), get_string('role'), get_string('fullname')];
    $table->attributes['style'] = 'max-width:860px;';
    foreach ($seedResult['accounts'] as $a) {
        [$key, $username, $first, $last, $kind, $desc] = $a;
        $table->data[] = [html_writer::tag('code', $username), $roleName[$kind] ?? $kind, $desc . ' — ' . $first . ' ' . $last];
    }
    echo html_writer::start_div('card', ['style' => 'max-width:860px;padding:1rem 1.25rem;margin:1rem 0;']);
    echo html_writer::tag('strong', get_string('demoseed_logins', 'local_kreck'));
    echo ' — ' . get_string('demoseed_password', 'local_kreck') . ' ' . html_writer::tag('code', $seedResult['password']);
    echo html_writer::table($table);
    echo html_writer::tag('p', get_string('demoseed_lmsnote', 'local_kreck'),
        ['style' => 'background:#fef3c7;color:#92400e;border-radius:8px;padding:.6rem .9rem;font-size:13px;']);
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
