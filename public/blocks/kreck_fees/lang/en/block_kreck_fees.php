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
 * Strings for component 'block_kreck_fees'.
 *
 * @package   block_kreck_fees
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'KRECK fees';
$string['kreck_fees:addinstance'] = 'Add a new KRECK fees block';
$string['kreck_fees:myaddinstance'] = 'Add a new KRECK fees block to the Dashboard';

// Card.
$string['amountdue'] = 'Amount due';
$string['balance'] = 'Balance';
$string['attendance'] = 'Attendance';
$string['studentid'] = 'Student ID';
$string['reminder'] = 'A payment is due. Please settle the balance or contact the school office.';
$string['noaccount'] = 'No fee account has been set up for this learner yet.';

// Fee status.
$string['feestatus_current'] = 'Up to date';
$string['feestatus_overdue'] = 'Overdue';
$string['feestatus_paid'] = 'Paid';
$string['feestatus_failed'] = 'Payment failed';

// Fallback.
$string['nodataplugin'] = 'The KRECK data plugin (local_kreck) is not installed yet, so there is nothing to show.';

// Privacy.
$string['privacy:metadata'] = 'The KRECK fees block only displays data stored by the local_kreck plugin; it stores no data of its own.';
