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
 * KRECK sample-data seeding tool.
 *
 * An administrator can put sample attendance, fee, streak and topic data on a
 * learner so the dashboards and blocks populate without the live SMS feed.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/adminlib.php");

use local_kreck\persistent\student;
use local_kreck\persistent\streak;
use local_kreck\persistent\feeaccount;
use local_kreck\persistent\attendance;
use local_kreck\persistent\topicstat;

admin_externalpage_setup('local_kreck_seed');

$context = context_system::instance();
require_capability('local/kreck:manage', $context);

$PAGE->set_url(new moodle_url('/local/kreck/seed.php'));
$PAGE->set_title(get_string('seedpage', 'local_kreck'));
$PAGE->set_heading(get_string('seedpage', 'local_kreck'));

$form = new \local_kreck\form\seed_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/search.php'));
}

if ($data = $form->get_data()) {
    $user = $DB->get_record('user', ['username' => $data->username, 'deleted' => 0], '*', MUST_EXIST);
    $uid = (int) $user->id;

    // Identity record.
    $studentrec = student::get_record(['userid' => $uid]) ?: new student();
    $studentrec->set('userid', $uid);
    if (trim((string) $data->studentid) !== '') {
        $studentrec->set('studentid', trim($data->studentid));
    } else if (!$studentrec->get('id')) {
        $studentrec->set('studentid', 'KR-' . str_pad((string) $uid, 5, '0', STR_PAD_LEFT));
    }
    $studentrec->set('classname', trim((string) $data->classname) !== '' ? $data->classname : null);
    $studentrec->save();

    // Streak.
    $streakrec = streak::get_record(['userid' => $uid]) ?: new streak();
    $streakrec->set('userid', $uid);
    $streakrec->set('current', (int) $data->streakcurrent);
    $streakrec->set('longest', max((int) $data->streaklongest, (int) $data->streakcurrent));
    $streakrec->set('lastactive', time());
    $streakrec->save();

    // Fee account.
    $feerec = feeaccount::get_record(['userid' => $uid]) ?: new feeaccount();
    $feerec->set('userid', $uid);
    $feerec->set('balancecents', (int) round(((float) $data->feebalance) * 100));
    $feerec->set('currency', strtoupper($data->feecurrency));
    $feerec->set('status', (int) $data->feestatus);
    $feerec->set('laststatement', time());
    $feerec->save();

    // Attendance: generate the last N session days, the first M of them present.
    $total = max(0, (int) $data->attendancetotal);
    $present = min((int) $data->attendancepresent, $total);
    for ($i = 0; $i < $total; $i++) {
        $day = usergetmidnight(time() - ($i * DAYSECS));
        $status = $i < $present ? attendance::STATUS_PRESENT : attendance::STATUS_ABSENT;
        $attrec = attendance::get_record(['userid' => $uid, 'sessiondate' => $day]) ?: new attendance();
        $attrec->set('userid', $uid);
        $attrec->set('sessiondate', $day);
        $attrec->set('status', $status);
        $attrec->set('source', 'seed');
        $attrec->save();
    }

    // Topic struggle.
    if (trim((string) $data->topic) !== '') {
        $topicrec = topicstat::get_record(['userid' => $uid, 'topic' => $data->topic]) ?: new topicstat();
        $topicrec->set('userid', $uid);
        $topicrec->set('topic', $data->topic);
        $topicrec->set('attempts', (int) $data->topicattempts);
        $topicrec->set('wrong', min((int) $data->topicwrong, (int) $data->topicattempts));
        $topicrec->set('lastseen', time());
        $topicrec->save();
    }

    redirect(
        $PAGE->url,
        get_string('seed_done', 'local_kreck', $user->username),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('seedpage', 'local_kreck'));
echo html_writer::tag('p', get_string('seed_intro', 'local_kreck'), ['class' => 'text-muted']);
$form->display();
echo $OUTPUT->footer();
