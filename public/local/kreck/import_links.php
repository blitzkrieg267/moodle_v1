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
 * KRECK SMS -> LMS link import (reverse of export_links.php).
 *
 * Uploads the SMS (KRECK SMS) deep-links file produced by the SMS side
 * (Kreck Bridge -> Export to Kreck LMS -> "Download SMS links") and stores the
 * relative SMS URLs in local_kreck_link (direction 'togibbon'), so the LMS can
 * surface "View in KRECK SMS" links on course and user pages. Idempotent on
 * (entitytype, entitykey, direction); a re-run updates in place and never
 * duplicates. Mirrors the SMS-side kreck_import_links.php.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
\local_kreck\diagnostics::register(__FILE__);
require_once("{$CFG->libdir}/adminlib.php");

use local_kreck\onboarding\engine;

admin_externalpage_setup('local_kreck_importlinks');
require_capability('local/kreck:manage', context_system::instance());

$pageurl = new moodle_url('/local/kreck/import_links.php');
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('importlinks', 'local_kreck'));
$PAGE->set_heading(get_string('importlinks', 'local_kreck'));

$result = null;
$parseerror = '';

if (optional_param('action', '', PARAM_ALPHA) === 'import' && confirm_sesskey()) {
    if (empty($_FILES['linksfile']['tmp_name']) || !is_uploaded_file($_FILES['linksfile']['tmp_name'])) {
        $parseerror = get_string('importlinks_nofile', 'local_kreck');
    } else {
        $raw = file_get_contents($_FILES['linksfile']['tmp_name']);
        $payload = json_decode((string) $raw, true);
        if (!is_array($payload)) {
            $parseerror = get_string('importlinks_badjson', 'local_kreck');
        } else {
            // Accept either a dedicated SMS links file {courses,students} or a
            // full manifest that carries a links block.
            $links = isset($payload['links']) && is_array($payload['links']) ? $payload['links'] : $payload;
            if (empty($links['courses']) && empty($links['students'])) {
                $parseerror = get_string('importlinks_notlinks', 'local_kreck');
            } else {
                $engine = new engine();
                $result = $engine->import_links($links);
            }
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importlinks', 'local_kreck'));
echo html_writer::tag('p', get_string('importlinks_intro', 'local_kreck'), ['class' => 'text-muted']);

if ($parseerror !== '') {
    echo $OUTPUT->notification($parseerror, \core\output\notification::NOTIFY_ERROR);
}

if ($result !== null) {
    $a = (object) [
        'created' => (int) $result['created'],
        'updated' => (int) $result['updated'],
        'skipped' => (int) $result['skipped'],
    ];
    echo $OUTPUT->notification(get_string('importlinks_done', 'local_kreck', $a), \core\output\notification::NOTIFY_SUCCESS);

    if (!empty($result['rows'])) {
        $table = new html_table();
        $table->head = [
            get_string('importlinks_type', 'local_kreck'),
            get_string('importlinks_key', 'local_kreck'),
            get_string('importlinks_result', 'local_kreck'),
        ];
        foreach (array_slice($result['rows'], 0, 500) as $row) {
            $label = $row['result'] . (isset($row['reason']) ? ' (' . $row['reason'] . ')' : '');
            $table->data[] = [s($row['type']), s($row['key']), s($label)];
        }
        echo html_writer::table($table);
    }
}

// Upload form (plain file input + sesskey, mirroring the SMS-side import page).
$formhtml = html_writer::start_tag('form', [
    'method' => 'post',
    'enctype' => 'multipart/form-data',
    'action' => $pageurl->out(false),
]);
$formhtml .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'import']);
$formhtml .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$formhtml .= html_writer::tag('p', html_writer::tag('b', get_string('importlinks_file', 'local_kreck')));
$formhtml .= html_writer::empty_tag('input', ['type' => 'file', 'name' => 'linksfile', 'accept' => 'application/json,.json', 'required' => 'required']);
$formhtml .= ' ' . html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('importlinks_submit', 'local_kreck'),
]);
$formhtml .= html_writer::end_tag('form');
echo html_writer::div($formhtml, 'card p-3', ['style' => 'max-width:720px']);

echo html_writer::tag('p', get_string('importlinks_help', 'local_kreck'), ['class' => 'text-muted mt-2']);

echo $OUTPUT->footer();
