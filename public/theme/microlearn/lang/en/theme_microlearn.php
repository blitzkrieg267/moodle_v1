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
 * Strings for component 'theme_microlearn'.
 *
 * @package   theme_microlearn
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'KRECK MicroLearn';
$string['choosereadme'] = 'KRECK MicroLearn is a mobile-first child theme of Boost for the KRECK SMS + MicroLearn LMS platform. It retargets the Moodle Design System tokens to the KRECK brand (slate structure, teal primary action, amber accent) for a modern, low-bandwidth, card-based experience.';

// Settings pages.
$string['configtitle'] = 'KRECK MicroLearn settings';
$string['generalsettings'] = 'General';
$string['advancedsettings'] = 'Advanced';

// General settings.
$string['brandcolor'] = 'Brand colour';
$string['brandcolor_desc'] = 'Overrides the default KRECK teal used for primary actions and links. Leave blank to use the theme default (#1e8a74).';
$string['backgroundimage'] = 'Page background image';
$string['backgroundimage_desc'] = 'An optional background image shown behind pages on larger screens. Keep it light for low-bandwidth mobile users.';

// Advanced (raw SCSS) settings.
$string['rawscsspre'] = 'Raw initial SCSS';
$string['rawscsspre_desc'] = 'SCSS injected before the main styles compile. Use it to set SCSS variables (e.g. $primary).';
$string['rawscss'] = 'Raw SCSS';
$string['rawscss_desc'] = 'SCSS appended after all other styles, including the theme defaults. Use it for final overrides.';

// Role dashboards.
$string['dash_hi'] = 'Hi, {$a}';
$string['dash_school'] = 'Your school';
$string['dash_eyebrow_learner'] = 'Your learning';
$string['dash_eyebrow_teacher'] = 'Your classes';
$string['dash_eyebrow_parent'] = 'Your child';
$string['dash_eyebrow_institution'] = 'Institution';
$string['dash_learner_sub'] = 'Keep your streak going and pick up where you left off.';
$string['dash_teacher_sub'] = 'Your classes at a glance, and where learners are struggling.';
$string['dash_parent_sub'] = 'Your child\'s attendance, fees and learning.';
$string['dash_institution_sub'] = 'Attendance, fees and app activity across the school.';
$string['dash_daystreak'] = 'day streak';
$string['dash_best'] = 'Best {$a}';
$string['dash_attendance'] = 'Attendance';
$string['dash_focus'] = 'Focus topic';
$string['dash_fees'] = 'Fees';
$string['dash_streak'] = 'Streak';
$string['dash_wrong_short'] = 'wrong';
$string['dash_keeplearning'] = 'Keep learning';
$string['dash_feereminder'] = 'A payment of {$a} is due. Please settle it or contact the office.';
$string['dash_classes'] = 'Classes';
$string['dash_learners'] = 'Learners';
$string['dash_avgattendance'] = 'Avg attendance';
$string['dash_struggle_title'] = 'Where your class is struggling';
$string['dash_struggle_title_school'] = 'Where learners struggle most';
$string['dash_struggle_empty'] = 'No topic data yet. It appears as learners complete quizzes.';
$string['dash_totallearners'] = 'Learners';
$string['dash_feesoverdue'] = 'Fees overdue';
$string['dash_appactive'] = 'Active on app';
$string['dash_parent_focus'] = 'Could use support with {$a} at home.';
$string['dash_parent_nochild'] = 'No child is linked to your account yet. Ask the school to connect your child\'s record.';
$string['dash_manage'] = 'Administration';
$string['dash_reports'] = 'Reports';

// Fee status labels (used by the parent dashboard).
$string['feestatus_current'] = 'Up to date';
$string['feestatus_overdue'] = 'Overdue';
$string['feestatus_paid'] = 'Paid';
$string['feestatus_failed'] = 'Payment failed';

// Block regions (inherited drawers layouts use side-pre).
$string['region-side-pre'] = 'Right';

// Privacy (no personal data stored by the theme).
$string['privacy:metadata'] = 'The KRECK MicroLearn theme does not store any personal data.';
