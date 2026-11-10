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
 * Strings for component 'local_kreck'.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'KRECK MicroLearn data';

// Capabilities.
$string['kreck:view'] = 'View KRECK learner data (attendance, fees, streaks, topics)';
$string['kreck:manage'] = 'Create and update KRECK learner data';
$string['kreck:viewinstitution'] = 'View whole-school KRECK analytics';

// Settings.
$string['enabled'] = 'Enable KRECK data';
$string['enabled_desc'] = 'Turn the KRECK MicroLearn data backbone on or off site-wide.';
$string['defaultcurrency'] = 'Default currency';
$string['defaultcurrency_desc'] = 'ISO currency code used for fee balances when a learner record does not specify one (e.g. BWP, ZAR).';
$string['attendancewindow'] = 'Attendance window (days)';
$string['attendancewindow_desc'] = 'How many recent days the learner attendance rate is calculated over.';
$string['feegatesapp'] = 'Let fee status gate app access';
$string['feegatesapp_desc'] = 'If enabled, a school may restrict learning-app access based on fee status. Off by default; this is a school-level policy decision.';

// Privacy.
$string['privacy:metadata:local_kreck_student'] = 'The lifelong KRECK student record linking a user to their student ID, school and class.';
$string['privacy:metadata:local_kreck_student:userid'] = 'The user the record belongs to.';
$string['privacy:metadata:local_kreck_student:studentid'] = 'The KRECK lifelong student ID.';
$string['privacy:metadata:local_kreck_student:schoolid'] = 'The school the learner belongs to.';
$string['privacy:metadata:local_kreck_student:classname'] = 'The learner\'s class.';
$string['privacy:metadata:local_kreck_student:stream'] = 'The learner\'s stream.';
$string['privacy:metadata:local_kreck_student:guardiancontact'] = 'A guardian contact detail for the learner.';

$string['privacy:metadata:local_kreck_attendance'] = 'Daily attendance marks for the learner.';
$string['privacy:metadata:local_kreck_attendance:userid'] = 'The learner the mark belongs to.';
$string['privacy:metadata:local_kreck_attendance:sessiondate'] = 'The day of the session.';
$string['privacy:metadata:local_kreck_attendance:status'] = 'Present, absent, late or excused.';

$string['privacy:metadata:local_kreck_feeaccount'] = 'The learner\'s fee balance and debit-order status.';
$string['privacy:metadata:local_kreck_feeaccount:userid'] = 'The learner the account belongs to.';
$string['privacy:metadata:local_kreck_feeaccount:balancecents'] = 'The outstanding balance.';
$string['privacy:metadata:local_kreck_feeaccount:status'] = 'The account status.';

$string['privacy:metadata:local_kreck_streak'] = 'The learner\'s app-engagement streak.';
$string['privacy:metadata:local_kreck_streak:userid'] = 'The learner the streak belongs to.';
$string['privacy:metadata:local_kreck_streak:current'] = 'The current streak length.';
$string['privacy:metadata:local_kreck_streak:longest'] = 'The longest streak reached.';

$string['privacy:metadata:local_kreck_topicstat'] = 'Per-topic attempt and wrong-answer counts for the learner.';
$string['privacy:metadata:local_kreck_topicstat:userid'] = 'The learner the stats belong to.';
$string['privacy:metadata:local_kreck_topicstat:topic'] = 'The topic.';
$string['privacy:metadata:local_kreck_topicstat:attempts'] = 'Number of attempts.';
$string['privacy:metadata:local_kreck_topicstat:wrong'] = 'Number of wrong answers.';
