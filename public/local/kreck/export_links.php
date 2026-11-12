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
 * KRECK LMS -> SMS link export.
 *
 * Emits a "links.moodle" JSON file describing the Moodle courses (and the
 * activities inside them) that this plugin provisioned, so the KRECK SMS
 * (Gibbon) side can deep-link back into the LMS ("Open in KRECK LMS").
 * The pipeline always starts at Gibbon; this is the return leg that lets the
 * SMS surface the LMS assets it created.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/adminlib.php");

use local_kreck\onboarding\engine;

admin_externalpage_setup('local_kreck_exportlinks');
require_capability('local/kreck:manage', context_system::instance());

$pageurl = new moodle_url('/local/kreck/export_links.php');
$engine = new engine();

/**
 * Build the links.moodle payload for a given batch record.
 *
 * @param array $record Batch record (holds coursemap: shortname => courseid).
 * @return array
 */
function local_kreck_build_links_payload(array $record): array {
    $coursemap = $record['coursemap'] ?? [];
    $courses = [];
    foreach ($coursemap as $shortname => $courseid) {
        $courseid = (int) $courseid;
        if (!$courseid) {
            continue;
        }
        $activities = [];
        try {
            $modinfo = get_fast_modinfo($courseid);
            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible && !$cm->is_visible_on_course_page()) {
                    // Still export hidden-but-present activities; skip only orphaned stubs.
                    if ($cm->deletioninprogress) {
                        continue;
                    }
                }
                if ($cm->deletioninprogress) {
                    continue;
                }
                $activities[] = [
                    'cmid'    => (int) $cm->id,
                    'modname' => (string) $cm->modname,
                    'name'    => (string) format_string($cm->name),
                    'relurl'  => '/mod/' . $cm->modname . '/view.php?id=' . (int) $cm->id,
                ];
            }
        } catch (\Throwable $e) {
            // Course may have been removed since the batch; emit it without activities.
            $activities = [];
        }
        $courses[] = [
            'shortname'       => (string) $shortname,
            'moodle_courseid' => $courseid,
            'relurl'          => '/course/view.php?id=' . $courseid,
            'activities'      => $activities,
        ];
    }

    return [
        'generated' => date('c'),
        'source'    => 'moodle',
        'base_hint' => 'moodle',
        'batchid'   => (string) ($record['batchid'] ?? ''),
        'courses'   => $courses,
    ];
}

// Download action.
$action = optional_param('action', '', PARAM_ALPHA);
$batchid = optional_param('batchid', '', PARAM_RAW_TRIMMED);

if ($action === 'download') {
    require_sesskey();
    $record = $batchid ? $engine->get_batch($batchid) : null;
    if (!$record) {
        // Fall back to the newest batch.
        $batches = $engine->list_batches();
        if (!empty($batches)) {
            $record = $engine->get_batch($batches[0]['batchid']);
        }
    }
    if (!$record) {
        throw new \moodle_exception('exportlinks_nobatch', 'local_kreck');
    }
    $payload = local_kreck_build_links_payload($record);
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $filename = 'kreck_links_moodle_' . ($record['batchid'] ?? 'batch') . '.json';
    send_file($json, $filename, 0, 0, true, true, 'application/json');
    die;
}

$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('exportlinks', 'local_kreck'));
$PAGE->set_heading(get_string('exportlinks', 'local_kreck'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('exportlinks', 'local_kreck'));
echo html_writer::tag('p', get_string('exportlinks_intro', 'local_kreck'), ['class' => 'text-muted']);

$batches = $engine->list_batches();
if (empty($batches)) {
    echo $OUTPUT->notification(get_string('exportlinks_nobatch', 'local_kreck'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    die;
}

// Preview the newest batch and offer a download per batch.
$table = new html_table();
$table->head = [
    get_string('exportlinks_batch', 'local_kreck'),
    get_string('onb_created', 'local_kreck'),
    get_string('exportlinks_courses', 'local_kreck'),
    get_string('exportlinks_activities', 'local_kreck'),
    '',
];
foreach ($batches as $b) {
    $record = $engine->get_batch($b['batchid']);
    $payload = $record ? local_kreck_build_links_payload($record) : ['courses' => []];
    $activitycount = 0;
    foreach ($payload['courses'] as $c) {
        $activitycount += count($c['activities']);
    }
    $dl = html_writer::link(
        new moodle_url($pageurl, ['action' => 'download', 'batchid' => $b['batchid'], 'sesskey' => sesskey()]),
        get_string('exportlinks_download', 'local_kreck'),
        ['class' => 'btn btn-sm btn-primary']
    );
    $table->data[] = [
        $b['batchid'],
        userdate($b['timecreated']),
        count($payload['courses']),
        $activitycount,
        $dl,
    ];
}
echo html_writer::table($table);

// Show a JSON preview of the newest batch.
$newest = $engine->get_batch($batches[0]['batchid']);
if ($newest) {
    $preview = local_kreck_build_links_payload($newest);
    echo $OUTPUT->heading(get_string('exportlinks_preview', 'local_kreck'), 4, 'mt-4');
    echo html_writer::tag('pre',
        s(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ['class' => 'bg-light p-3 rounded', 'style' => 'max-height:400px;overflow:auto;']);
}

echo $OUTPUT->footer();
