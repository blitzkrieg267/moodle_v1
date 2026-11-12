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
 * KRECK LMS -> SMS marks export.
 *
 * Reads the Moodle gradebook for a provisioned batch and emits a marks file
 * keyed by (studentid, course shortname, item) so the KRECK SMS (Gibbon) side
 * can keep each learner's results on their lifelong school record.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/adminlib.php");
require_once("{$CFG->libdir}/gradelib.php");
require_once("{$CFG->dirroot}/user/lib.php");

use local_kreck\onboarding\engine;

admin_externalpage_setup('local_kreck_exportmarks');
require_capability('local/kreck:manage', context_system::instance());

$pageurl = new moodle_url('/local/kreck/export_marks.php');
$engine = new engine();

/**
 * Build the marks rows for a batch record.
 *
 * Each row: studentid, courseShortname, item, grade, grademax, moodlecourseid.
 *
 * @param array $record Batch record (coursemap: shortname => courseid).
 * @return array[] list of row arrays.
 */
function local_kreck_build_marks_rows(array $record): array {
    global $DB;

    $coursemap = $record['coursemap'] ?? [];
    $rows = [];

    foreach ($coursemap as $shortname => $courseid) {
        $courseid = (int) $courseid;
        if (!$courseid || !$DB->record_exists('course', ['id' => $courseid])) {
            continue;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            continue;
        }
        $enrolled = get_enrolled_users($context, '', 0, 'u.id, u.username');
        if (empty($enrolled)) {
            continue;
        }
        $userids = array_map(static fn($u) => (int) $u->id, $enrolled);

        // Map each Moodle userid to its KRECK studentid.
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $studentmap = $DB->get_records_select_menu('local_kreck_student',
            "userid $insql", $params, '', 'userid, studentid');

        // Course total.
        $coursegrades = grade_get_grades($courseid, 'course', null, null, $userids);
        if (!empty($coursegrades->items[0]->grades)) {
            $gmax = $coursegrades->items[0]->grademax ?? null;
            foreach ($coursegrades->items[0]->grades as $uid => $g) {
                $studentid = $studentmap[$uid] ?? null;
                if (!$studentid || $g->grade === null || $g->grade === false) {
                    continue;
                }
                $rows[] = [
                    'studentid'      => (string) $studentid,
                    'courseshortname' => (string) $shortname,
                    'item'           => 'Course total',
                    'grade'          => (string) $g->grade,
                    'grademax'       => $gmax !== null ? (string) $gmax : '',
                    'moodlecourseid' => $courseid,
                ];
            }
        }

        // Per-activity items.
        try {
            $modinfo = get_fast_modinfo($courseid);
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->deletioninprogress) {
                    continue;
                }
                $gi = grade_get_grades($courseid, 'mod', $cm->modname, $cm->instance, $userids);
                if (empty($gi->items)) {
                    continue;
                }
                foreach ($gi->items as $item) {
                    if (empty($item->grades)) {
                        continue;
                    }
                    $gmax = $item->grademax ?? null;
                    $itemname = $item->name ?: format_string($cm->name);
                    foreach ($item->grades as $uid => $g) {
                        $studentid = $studentmap[$uid] ?? null;
                        if (!$studentid || $g->grade === null || $g->grade === false) {
                            continue;
                        }
                        $rows[] = [
                            'studentid'       => (string) $studentid,
                            'courseshortname' => (string) $shortname,
                            'item'            => (string) $itemname,
                            'grade'           => (string) $g->grade,
                            'grademax'        => $gmax !== null ? (string) $gmax : '',
                            'moodlecourseid'  => $courseid,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            continue;
        }
    }

    return $rows;
}

$action = optional_param('action', '', PARAM_ALPHA);
$batchid = optional_param('batchid', '', PARAM_RAW_TRIMMED);

if ($action === 'download' || $action === 'downloadcsv') {
    require_sesskey();
    $record = $batchid ? $engine->get_batch($batchid) : null;
    if (!$record) {
        $batches = $engine->list_batches();
        if (!empty($batches)) {
            $record = $engine->get_batch($batches[0]['batchid']);
        }
    }
    if (!$record) {
        throw new \moodle_exception('exportmarks_nobatch', 'local_kreck');
    }
    $rows = local_kreck_build_marks_rows($record);
    $bid = $record['batchid'] ?? 'batch';

    if ($action === 'downloadcsv') {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['studentid', 'courseshortname', 'item', 'grade', 'grademax', 'moodlecourseid']);
        foreach ($rows as $r) {
            fputcsv($fh, [$r['studentid'], $r['courseshortname'], $r['item'],
                $r['grade'], $r['grademax'], $r['moodlecourseid']]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        send_file($csv, 'kreck_marks_moodle_' . $bid . '.csv', 0, 0, true, true, 'text/csv');
        die;
    }

    $payload = [
        'generated' => date('c'),
        'source'    => 'moodle',
        'batchid'   => (string) $bid,
        'marks'     => $rows,
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    send_file($json, 'kreck_marks_moodle_' . $bid . '.json', 0, 0, true, true, 'application/json');
    die;
}

$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('exportmarks', 'local_kreck'));
$PAGE->set_heading(get_string('exportmarks', 'local_kreck'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('exportmarks', 'local_kreck'));
echo html_writer::tag('p', get_string('exportmarks_intro', 'local_kreck'), ['class' => 'text-muted']);

$batches = $engine->list_batches();
if (empty($batches)) {
    echo $OUTPUT->notification(get_string('exportmarks_nobatch', 'local_kreck'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    die;
}

$table = new html_table();
$table->head = [
    get_string('exportmarks_batch', 'local_kreck'),
    get_string('onb_created', 'local_kreck'),
    get_string('exportmarks_courses', 'local_kreck'),
    '',
];
foreach ($batches as $b) {
    $record = $engine->get_batch($b['batchid']);
    $coursecount = $record ? count($record['coursemap'] ?? []) : 0;
    $buttons = html_writer::link(
        new moodle_url($pageurl, ['action' => 'download', 'batchid' => $b['batchid'], 'sesskey' => sesskey()]),
        get_string('exportmarks_download', 'local_kreck'),
        ['class' => 'btn btn-sm btn-primary me-2'])
        . html_writer::link(
        new moodle_url($pageurl, ['action' => 'downloadcsv', 'batchid' => $b['batchid'], 'sesskey' => sesskey()]),
        get_string('exportmarks_downloadcsv', 'local_kreck'),
        ['class' => 'btn btn-sm btn-outline-secondary']);
    $table->data[] = [$b['batchid'], userdate($b['timecreated']), $coursecount, $buttons];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
