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
 * KRECK onboarding & provisioning console.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/adminlib.php");

use local_kreck\onboarding\engine;
use local_kreck\onboarding\manifest;
use core\output\notification;

admin_externalpage_setup('local_kreck_onboarding');
require_capability('local/kreck:manage', context_system::instance());

// Serve the manifest template as a download.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'template') {
    send_file(__DIR__ . '/samples/onboarding_template.json', 'kreck_onboarding_template.json',
        0, 0, false, true, 'application/json');
    die;
}
if ($action === 'csvtemplate') {
    send_file(__DIR__ . '/samples/courses_template.csv', 'kreck_courses_template.csv',
        0, 0, false, true, 'text/csv');
    die;
}
if ($action === 'workbook') {
    send_file(__DIR__ . '/samples/KRECK_Institution_Intake.xlsx', 'KRECK_Institution_Intake.xlsx',
        0, 0, false, true, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    die;
}
if ($action === 'preset') {
    $body = optional_param('body', '', PARAM_ALPHA);
    $allowedpresets = ['igcse', 'alevel', 'olevel', 'bgcse', 'psle'];
    if (in_array($body, $allowedpresets, true)) {
        send_file(__DIR__ . "/samples/presets/{$body}.json", "kreck_preset_{$body}.json",
            0, 0, false, true, 'application/json');
    }
    die;
}

$pageurl = new moodle_url('/local/kreck/onboarding.php');
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('onboarding', 'local_kreck'));
$PAGE->set_heading(get_string('onboarding', 'local_kreck'));

$engine = new engine();
$form = new \local_kreck\form\onboarding_form();

$resulthtml = '';

if ($data = $form->get_data()) {
    require_sesskey();

    if ($data->mode === 'rollback') {
        $res = $engine->rollback(trim((string) $data->rollbackbatch));
        if ($res['ok']) {
            $a = (object) ['batch' => s($data->rollbackbatch), 'courses' => $res['removed']['courses'],
                'users' => $res['removed']['users'], 'cohorts' => $res['removed']['cohorts'] ?? 0,
                'categories' => $res['removed']['categories']];
            $resulthtml = $OUTPUT->notification(get_string('onb_rolledback', 'local_kreck', $a), notification::NOTIFY_SUCCESS);
        } else {
            $resulthtml = $OUTPUT->notification($res['error'], notification::NOTIFY_ERROR);
        }
    } else {
        try {
            $generatedjson = '';
            $preview = in_array($data->mode, ['csv', 'workbook'], true);
            if ($data->mode === 'csv') {
                $fragment = \local_kreck\onboarding\csv_importer::courses_to_manifest((string) $data->coursescsv);
                $generatedjson = json_encode($fragment,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $manifest = new manifest($fragment);
            } else if ($data->mode === 'workbook') {
                $path = $form->save_temp_file('intakefile');
                if (!$path) {
                    throw new \moodle_exception('onb_wb_nofile', 'local_kreck');
                }
                $fragment = \local_kreck\onboarding\workbook_importer::from_file($path);
                @unlink($path);
                $generatedjson = json_encode($fragment,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $manifest = new manifest($fragment);
            } else {
                $manifest = manifest::from_json((string) $data->manifest);
            }

            if ($data->mode === 'dryrun' || $preview) {
                $res = $engine->validate($manifest);
                $plan = $res['plan'];
                $lines = [
                    get_string('onb_plan_cats', 'local_kreck', (object) [
                        'create' => count($plan['categories_create']), 'exist' => count($plan['categories_exist'])]),
                    get_string('onb_plan_users', 'local_kreck', (object) [
                        'create' => count($plan['users_create']), 'exist' => count($plan['users_exist'])]),
                    get_string('onb_plan_cohorts', 'local_kreck', (object) [
                        'create' => count($plan['cohorts_create']), 'exist' => count($plan['cohorts_exist'])]),
                    get_string('onb_plan_students', 'local_kreck', $plan['students_create']),
                    get_string('onb_plan_courses', 'local_kreck', count($plan['courses'])),
                    get_string('onb_plan_modules', 'local_kreck', $plan['modules_total']),
                ];
                foreach ($res['warnings'] as $w) {
                    $lines[] = 'WARNING: ' . $w;
                }
                $type = $res['ok'] ? notification::NOTIFY_SUCCESS : notification::NOTIFY_ERROR;
                $heading = $res['ok']
                    ? get_string('onb_dryrun_pass', 'local_kreck', $plan['modules_total'])
                    : get_string('onb_dryrun_fail', 'local_kreck');
                foreach ($res['errors'] as $e) {
                    $lines[] = 'ERROR: ' . $e;
                }
                if ($preview) {
                    $resulthtml = $OUTPUT->notification(get_string('onb_csv_built', 'local_kreck'),
                            $res['ok'] ? notification::NOTIFY_SUCCESS : notification::NOTIFY_WARNING)
                        . html_writer::tag('p', get_string('onb_csv_copy', 'local_kreck'))
                        . html_writer::tag('pre', s($generatedjson), ['class' => 'bg-light p-3 rounded'])
                        . html_writer::tag('pre', s(implode("\n", $lines)), ['class' => 'bg-light p-3 rounded']);
                } else {
                    $resulthtml = $OUTPUT->notification($heading, $type)
                        . html_writer::tag('pre', s(implode("\n", $lines)), ['class' => 'bg-light p-3 rounded']);
                }

            } else { // execute.
                $res = $engine->provision($manifest);
                if ($res['ok']) {
                    $resulthtml = $OUTPUT->notification(
                        get_string('onb_provisioned', 'local_kreck', $res['batchid']), notification::NOTIFY_SUCCESS)
                        . html_writer::tag('pre', s(implode("\n", $res['log'])), ['class' => 'bg-light p-3 rounded']);
                } else {
                    $resulthtml = $OUTPUT->notification(
                        get_string('onb_provision_failed', 'local_kreck', $res['error']), notification::NOTIFY_ERROR);
                }
            }
        } catch (\Throwable $ex) {
            $resulthtml = $OUTPUT->notification($ex->getMessage(), notification::NOTIFY_ERROR);
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('onboarding', 'local_kreck'));
echo html_writer::tag('p', get_string('onb_intro', 'local_kreck'), ['class' => 'text-muted']);
echo html_writer::div(
    html_writer::link(new moodle_url($pageurl, ['action' => 'workbook']),
        get_string('onb_downloadworkbook', 'local_kreck'), ['class' => 'btn btn-primary me-2'])
    . html_writer::link(new moodle_url($pageurl, ['action' => 'template']),
        get_string('onb_downloadtemplate', 'local_kreck'), ['class' => 'btn btn-outline-secondary me-2'])
    . html_writer::link(new moodle_url($pageurl, ['action' => 'csvtemplate']),
        get_string('onb_downloadcsv', 'local_kreck'), ['class' => 'btn btn-outline-secondary']),
    'mb-3');

// Starter templates by exam body.
echo $OUTPUT->heading(get_string('onb_presets', 'local_kreck'), 4, 'mt-2');
$bodies = [
    'igcse' => 'Cambridge IGCSE',
    'alevel' => 'Cambridge A Level',
    'olevel' => 'Cambridge O Level',
    'bgcse' => 'BGCSE',
    'psle' => 'PSLE',
];
$presetlinks = '';
foreach ($bodies as $slug => $label) {
    $presetlinks .= html_writer::link(new moodle_url($pageurl, ['action' => 'preset', 'body' => $slug]),
        $label, ['class' => 'btn btn-sm btn-outline-secondary me-2 mb-1']);
}
echo html_writer::div($presetlinks, 'mb-2');
echo html_writer::tag('p', get_string('onb_presets_help', 'local_kreck'), ['class' => 'text-muted small mb-4']);

echo $resulthtml;

$form->display();

// Known batches, available to roll back.
$batches = $engine->list_batches();
echo $OUTPUT->heading(get_string('onb_knownbatches', 'local_kreck'), 3, 'mt-4');
if (empty($batches)) {
    echo html_writer::tag('p', get_string('onb_nobatches', 'local_kreck'), ['class' => 'text-muted']);
} else {
    $table = new html_table();
    $table->head = ['Batch id', 'Created', 'Courses', 'Users', 'Categories'];
    foreach ($batches as $b) {
        $table->data[] = [$b['batchid'], userdate($b['timecreated']), $b['courses'], $b['users'], $b['categories']];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
