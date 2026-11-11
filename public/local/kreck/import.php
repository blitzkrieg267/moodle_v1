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
 * KRECK smart importer: upload any CSV/Excel, auto-map its columns, preview,
 * validate, then create through the onboarding engine.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/adminlib.php");

use local_kreck\import\field_registry;
use local_kreck\import\matcher;
use local_kreck\import\stager;
use local_kreck\onboarding\engine;
use local_kreck\onboarding\manifest;
use core\output\notification;

admin_externalpage_setup('local_kreck_import');
require_capability('local/kreck:manage', context_system::instance());

$pageurl = new moodle_url('/local/kreck/import.php');
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('import_pagename', 'local_kreck'));
$PAGE->set_heading(get_string('import_pagename', 'local_kreck'));

$action = optional_param('action', '', PARAM_ALPHA);
$token = optional_param('token', '', PARAM_ALPHANUMEXT);

/**
 * Keep only valid field keys (or skip) from a posted mapping.
 *
 * @param array $posted
 * @param string $type
 * @return array
 */
function local_kreck_clean_map(array $posted, string $type): array {
    $valid = array_column(field_registry::fields($type), 'key');
    $map = [];
    foreach ($posted as $i => $key) {
        $map[(int) $i] = in_array($key, $valid, true) ? $key : field_registry::SKIP;
    }
    return $map;
}

// ---- Provision (final write) -----------------------------------------------
if ($action === 'provision' && confirm_sesskey()) {
    $token = required_param('token', PARAM_ALPHANUMEXT);
    $type = required_param('type', PARAM_ALPHA);
    $staged = stager::unstage($token);
    if (!$staged) {
        throw new moodle_exception('import_nostaged', 'local_kreck');
    }
    $map = local_kreck_clean_map(optional_param_array('map', [], PARAM_RAW), $type);
    $fragment = stager::build_fragment($type, $staged['rows'], $map);
    $result = (new engine())->provision(new manifest($fragment));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('import_pagename', 'local_kreck'));
    if ($result['ok']) {
        echo $OUTPUT->notification(get_string('import_done', 'local_kreck', $result['batchid']),
            notification::NOTIFY_SUCCESS);
        echo html_writer::tag('pre', s(implode("\n", $result['log'])), ['class' => 'bg-light p-3 rounded']);
    } else {
        echo $OUTPUT->notification(get_string('import_failed', 'local_kreck', $result['error']),
            notification::NOTIFY_ERROR);
    }
    echo html_writer::link($pageurl, get_string('import_again', 'local_kreck'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    die;
}

// ---- Confirm (validate + show report) --------------------------------------
if ($action === 'confirm' && confirm_sesskey()) {
    $token = required_param('token', PARAM_ALPHANUMEXT);
    $type = required_param('type', PARAM_ALPHA);
    $staged = stager::unstage($token);
    if (!$staged) {
        throw new moodle_exception('import_nostaged', 'local_kreck');
    }
    $map = local_kreck_clean_map(optional_param_array('map', [], PARAM_RAW), $type);
    stager::remember_mapping($staged['headers'], $map);

    $fragment = stager::build_fragment($type, $staged['rows'], $map);
    $validation = (new engine())->validate(new manifest($fragment));
    $plan = $validation['plan'];

    // Required fields still unmapped?
    $mapped = array_values($map);
    $missing = [];
    foreach (field_registry::fields($type) as $f) {
        if (!empty($f['required']) && !in_array($f['key'], $mapped, true)) {
            $missing[] = $f['label'];
        }
    }
    $ok = $validation['ok'] && empty($missing);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('import_report', 'local_kreck'));
    echo $OUTPUT->notification(get_string('import_saved', 'local_kreck'), notification::NOTIFY_INFO);

    if ($missing) {
        echo $OUTPUT->notification(
            get_string('import_missingrequired', 'local_kreck', implode(', ', $missing)), notification::NOTIFY_ERROR);
    }

    $lines = [];
    if ($type === 'courses') {
        $lines[] = get_string('onb_plan_courses', 'local_kreck', count($plan['courses']));
        $lines[] = get_string('onb_plan_cats', 'local_kreck', (object) [
            'create' => count($plan['categories_create']), 'exist' => count($plan['categories_exist'])]);
        $lines[] = get_string('onb_plan_modules', 'local_kreck', $plan['modules_total']);
    } else if ($type === 'students') {
        $lines[] = get_string('onb_plan_students', 'local_kreck', $plan['students_create']);
        $lines[] = get_string('onb_plan_cohorts', 'local_kreck', (object) [
            'create' => count($plan['cohorts_create']), 'exist' => count($plan['cohorts_exist'])]);
    } else if ($type === 'staff') {
        $lines[] = get_string('onb_plan_users', 'local_kreck', (object) [
            'create' => count($plan['users_create']), 'exist' => count($plan['users_exist'])]);
    } else if ($type === 'enrolments') {
        $lines[] = get_string('onb_plan_enrolments', 'local_kreck', $plan['enrolments']);
    }
    foreach ($validation['warnings'] as $w) {
        $lines[] = 'WARNING: ' . $w;
    }
    foreach ($validation['errors'] as $e) {
        $lines[] = 'ERROR: ' . $e;
    }
    echo html_writer::tag('pre', s(implode("\n", $lines)), ['class' => 'bg-light p-3 rounded']);

    if ($ok) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'provision']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'token', 'value' => $token]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => $type]);
        foreach ($map as $i => $key) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => "map[$i]", 'value' => $key]);
        }
        echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary',
            'value' => get_string('import_provision', 'local_kreck')]);
        echo html_writer::end_tag('form');
    }
    echo html_writer::link(new moodle_url($pageurl, ['token' => $token]),
        get_string('import_backtomap', 'local_kreck'), ['class' => 'btn btn-link']);
    echo $OUTPUT->footer();
    die;
}

// ---- Map stage (token present) ---------------------------------------------
if ($token !== '') {
    $staged = stager::unstage($token);
    if (!$staged) {
        redirect($pageurl, get_string('import_nostaged', 'local_kreck'), null, notification::NOTIFY_ERROR);
    }
    $type = $staged['type'];
    $headers = $staged['headers'];
    $rows = $staged['rows'];
    $ncols = count($headers);
    $samples = stager::samples($rows, $ncols);
    $fields = field_registry::fields($type);

    $map = stager::recall_mapping($headers) ?? matcher::guess($headers, $samples, $fields);
    $options = field_registry::options($type);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('import_mapheading', 'local_kreck'));
    echo html_writer::tag('p', get_string('import_mapintro', 'local_kreck'), ['class' => 'text-muted']);
    echo html_writer::tag('p', get_string('import_rows', 'local_kreck', count($rows)), ['class' => 'text-muted']);

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'confirm']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'token', 'value' => $token]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => $type]);

    $table = new html_table();
    $table->head = [
        get_string('import_col_header', 'local_kreck'),
        get_string('import_col_field', 'local_kreck'),
        get_string('import_col_sample', 'local_kreck'),
    ];
    foreach ($headers as $i => $h) {
        $select = html_writer::select($options, "map[$i]", $map[$i] ?? field_registry::SKIP, false);
        $sample = array_slice(array_filter($samples[$i], static fn($v) => $v !== ''), 0, 3);
        $preview = html_writer::tag('span', s(implode(' · ', $sample)), ['class' => 'text-muted']);
        $skipped = ($map[$i] ?? '') === field_registry::SKIP;
        $table->data[] = new html_table_row([
            new html_table_cell(html_writer::tag('strong', s($h !== '' ? $h : "Column " . ($i + 1)))),
            new html_table_cell($select),
            new html_table_cell($preview),
        ]);
        if ($skipped) {
            end($table->data)->attributes['class'] = 'text-muted';
        }
    }
    echo html_writer::table($table);

    echo html_writer::tag('p', get_string('import_requirednote', 'local_kreck'), ['class' => 'text-muted small']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary',
        'value' => get_string('import_confirm', 'local_kreck')]);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    die;
}

// ---- Upload stage (default) ------------------------------------------------
$form = new \local_kreck\form\import_upload_form($pageurl);
if ($data = $form->get_data()) {
    $path = $form->save_temp_file('importfile');
    if ($path) {
        $parsed = stager::read_file($path);
        @unlink($path);
        $newtoken = stager::stage([
            'type' => $data->type,
            'headers' => $parsed['headers'],
            'rows' => $parsed['rows'],
        ]);
        redirect(new moodle_url($pageurl, ['token' => $newtoken]));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('import_pagename', 'local_kreck'));
echo html_writer::tag('p', get_string('import_intro', 'local_kreck'), ['class' => 'text-muted']);
$form->display();
echo $OUTPUT->footer();
