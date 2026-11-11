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

// Fee status labels (shared by the seed form).
$string['feestatus_current'] = 'Up to date';
$string['feestatus_overdue'] = 'Overdue';
$string['feestatus_paid'] = 'Paid';
$string['feestatus_failed'] = 'Payment failed';

// Seed tool.
$string['seedpage'] = 'Seed KRECK sample data';
$string['seed_intro'] = 'Put sample attendance, fee, streak and topic data onto a learner so the KRECK dashboards and blocks populate without the live SMS feed. This is a testing tool.';
$string['seed_identity'] = 'Learner';
$string['seed_username'] = 'Username';
$string['seed_username_help'] = 'The username of the Moodle user to put sample KRECK data on. Use your own admin username to see the cards populate on your dashboard.';
$string['seed_studentid'] = 'Student ID (optional)';
$string['seed_classname'] = 'Class (optional)';
$string['seed_engagement'] = 'Streak';
$string['seed_streakcurrent'] = 'Current streak (days)';
$string['seed_streaklongest'] = 'Longest streak (days)';
$string['seed_attendance'] = 'Attendance';
$string['seed_attendancetotal'] = 'Session days to generate';
$string['seed_attendancepresent'] = 'Of those, days present';
$string['seed_fees'] = 'Fees';
$string['seed_feebalance'] = 'Balance (major units, e.g. 450.00)';
$string['seed_feecurrency'] = 'Currency code';
$string['seed_feestatus'] = 'Fee status';
$string['seed_topic'] = 'Focus topic';
$string['seed_topicname'] = 'Topic name';
$string['seed_topicattempts'] = 'Attempts';
$string['seed_topicwrong'] = 'Wrong answers';
$string['seed_submit'] = 'Seed data';
$string['seed_done'] = 'Sample KRECK data saved for {$a}.';
$string['seed_nouser'] = 'No active user has that username.';
$string['seed_presenttoohigh'] = 'Days present cannot exceed the number of session days.';
$string['seed_wrongtoohigh'] = 'Wrong answers cannot exceed attempts.';

// Onboarding engine.
$string['onboarding'] = 'KRECK onboarding & provisioning';
$string['onb_intro'] = 'Provision a whole institution from a JSON manifest: categories, users and weekly courses pre-populated with a complete set of core activities. Run a dry run first, then provision, and roll back any batch by its id.';
$string['onb_manifest'] = 'Onboarding manifest (JSON)';
$string['onb_manifest_help'] = 'Paste a KRECK onboarding manifest. Download the template below for the expected structure. No media is uploaded; media slots become text placeholders you edit later.';
$string['onb_mode'] = 'Mode';
$string['onb_mode_dryrun'] = 'Dry run (validate only, no changes)';
$string['onb_mode_execute'] = 'Provision (create everything)';
$string['onb_mode_csv'] = 'Build manifest from courses CSV';
$string['onb_mode_workbook'] = 'Build manifest from intake workbook (.xlsx)';
$string['onb_mode_rollback'] = 'Roll back a batch';
$string['onb_intakefile'] = 'Institution intake workbook (.xlsx)';
$string['onb_intakefile_help'] = 'Upload the filled KRECK institution intake workbook. Its Categories, Staff, Cohorts, Students and Courses sheets are read into a manifest, shown and validated below. Only the columns in the sheets are used; anything the system fills by default (course format, activities, logins) is ignored.';
$string['onb_wb_nofile'] = 'Please choose an .xlsx file to upload.';
$string['onb_wb_unreadable'] = 'The uploaded workbook could not be read.';
$string['onb_wb_badfile'] = 'The file is not a valid .xlsx workbook: {$a}';
$string['onb_downloadworkbook'] = 'Download the intake workbook (Excel)';
$string['onb_presets'] = 'Starter templates by exam body';
$string['onb_presets_help'] = 'Download a ready-made manifest of the standard subjects for an exam body, paste it into the manifest box, dry-run it, then provision. Add teachers, cohorts and learners, and adjust the number of weeks, afterwards.';
$string['onb_coursescsv'] = 'Courses CSV';
$string['onb_coursescsv_help'] = 'Paste a CSV with a header row. Recognised columns: fullname, shortname, category_path, numsections, startdate (YYYY-MM-DD), summary, teachers (semicolon-separated emails), cohorts (semicolon-separated idnumbers), format. Each course gets the default weekly blueprint (announcements plus one of every activity per week).';
$string['onb_csv_built'] = 'Manifest built from your CSV and validated below.';
$string['onb_csv_copy'] = 'Copy this JSON into the manifest box, choose Provision, and Run:';
$string['onb_downloadcsv'] = 'Download the courses CSV template';
$string['onb_rollbackbatch'] = 'Batch id to roll back';
$string['onb_submit'] = 'Run';
$string['onb_downloadtemplate'] = 'Download the manifest template (JSON)';
$string['onb_results'] = 'Result';
$string['onb_dryrun_pass'] = '[DRY-RUN PASSED] No blocking errors. {$a} activities would be created.';
$string['onb_dryrun_fail'] = '[DRY-RUN FAILED] Fix the errors below before provisioning.';
$string['onb_provisioned'] = 'Provisioned successfully. Batch id: {$a}. Keep this id to roll back.';
$string['onb_provision_failed'] = 'Provisioning failed and the partial batch was rolled back: {$a}';
$string['onb_rolledback'] = 'Rolled back batch {$a->batch}: {$a->courses} courses, {$a->users} users, {$a->cohorts} cohorts, {$a->categories} categories removed.';
$string['onb_plan_cats'] = 'Categories: {$a->create} to create, {$a->exist} already exist.';
$string['onb_plan_courses'] = 'Courses: {$a} to create.';
$string['onb_plan_users'] = 'Staff users: {$a->create} to create, {$a->exist} already exist.';
$string['onb_plan_cohorts'] = 'Cohorts: {$a->create} to create, {$a->exist} already exist.';
$string['onb_plan_students'] = 'Students to create (explicit + generated): {$a}.';
$string['onb_plan_modules'] = 'Activities to create: {$a}.';
$string['onb_log_cohortcreated'] = 'Created cohort: {$a}';
$string['onb_log_studentsgenerated'] = 'Generated {$a->count} students ({$a->prefix}NNN)';
$string['onb_knownbatches'] = 'Provisioned batches (available to roll back)';
$string['onb_nobatches'] = 'No provisioned batches yet.';

// Blueprint content.
$string['onb_generaltitle'] = 'General course administration';
$string['onb_weektitle'] = 'Week {$a}';
$string['onb_announcements'] = 'Announcements';
$string['onb_announcements_intro'] = 'Official announcements and notices for this course.';
$string['onb_mod_notes'] = 'Week {$a}: Study guide';
$string['onb_mod_assign'] = 'Week {$a}: Diagnostic task';
$string['onb_mod_quiz'] = 'Week {$a}: Checkpoint quiz';
$string['onb_mod_forum'] = 'Week {$a}: Discussion';
$string['onb_mod_path'] = 'Week {$a}: Self-paced path';

// Engine messages.
$string['onb_badjson'] = 'The manifest is not valid JSON.';
$string['onb_unsupportedmodule'] = 'Unsupported module type: {$a}';
$string['onb_err_nouser'] = 'A user entry is missing a username and email.';
$string['onb_err_coursefields'] = 'A course entry is missing its fullname or shortname.';
$string['onb_err_shortname'] = 'Course shortname already exists: {$a}';
$string['onb_err_nobatch'] = 'No stored batch with id: {$a}';
$string['onb_warn_module'] = 'Module type "{$a}" is not natively supported and will be skipped.';
$string['onb_warn_teacher'] = 'Teacher {$a} does not exist and will be created as a stub account.';
$string['onb_log_catcreated'] = 'Created category: {$a}';
$string['onb_log_usercreated'] = 'Created user: {$a}';
$string['onb_log_coursecreated'] = 'Created course: {$a}';
$string['onb_log_done'] = 'Provisioning complete. Batch id: {$a}';

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
