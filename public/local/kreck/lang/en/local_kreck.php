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

// Starter password + landing page settings.
$string['defaultpassword'] = 'Starter password for provisioned users';
$string['defaultpassword_desc'] = 'When set, every user the onboarding engine creates is given this password so they can log in. Leave blank to create accounts without a password (they cannot log in until one is set). A manifest\'s own "defaultpassword" overrides this.';
$string['forcepasswordchange'] = 'Force password change at first login';
$string['forcepasswordchange_desc'] = 'If enabled, provisioned users must choose their own password the first time they log in with the starter password.';
$string['landingenabled'] = 'Public landing page';
$string['landingenabled_desc'] = 'Send anonymous visitors from the site front page to a friendly public page showing the institution, its categories and the courses offered. Logged-in users are unaffected. Turn off to restore Moodle\'s default front page.';
$string['landingintro'] = 'Landing page intro text';
$string['landingintro_desc'] = 'Optional short paragraph shown on the public landing page. Leave blank to use the site summary.';
$string['landing_eyebrow'] = 'Welcome to';
$string['landing_defaultintro'] = 'Browse the subjects and courses offered here. Log in to start learning.';
$string['landing_courses'] = 'Subjects on offer';
$string['landing_dashboard'] = 'Go to my dashboard';
$string['landing_browse'] = 'Browse subjects';
$string['landing_qa_subjects'] = 'Browse subjects';
$string['landing_qa_subjects_d'] = 'See every subject offered.';
$string['landing_qa_pathway'] = 'Learning pathway';
$string['landing_qa_pathway_d'] = 'How the stages fit together.';
$string['landing_qa_login'] = 'Log in';
$string['landing_qa_login_d'] = 'Students, teachers and parents.';
$string['landing_qa_enquire'] = 'Enquire';
$string['landing_qa_enquire_d'] = 'Talk to the school office.';
$string['landing_pathway_title'] = 'The learning pathway';
$string['landing_pathway_sub'] = 'A clear route through every stage we offer.';
$string['landing_subjectcount'] = '{$a} subjects';
$string['landing_viewsubjects'] = 'View subjects';
$string['landing_explorer_sub'] = 'Search and browse everything taught here.';
$string['landing_searchph'] = 'Search subjects…';
$string['landing_feat1_t'] = 'Keep the momentum going';
$string['landing_feat1_d'] = 'Short lessons built for a few minutes at a time, with streaks and instant quiz feedback that keep learners coming back.';
$string['landing_feat1_chip'] = 'Streaks & instant feedback';
$string['landing_feat2_t'] = 'Built for phones, light on data';
$string['landing_feat2_d'] = 'A mobile-first experience tuned for low-bandwidth networks, so every learner can take part wherever they are.';
$string['landing_feat2_chip'] = 'Mobile-first';
$string['landing_feat3_t'] = 'One student, one record';
$string['landing_feat3_d'] = 'Attendance, results and learning follow each learner through a single student ID — the school and the classroom in one place.';
$string['landing_feat3_chip'] = 'One lifelong record';
$string['landing_cta_title'] = 'Ready to start learning?';
$string['landing_cta_sub'] = 'Log in to pick up where you left off, or get in touch with the school.';
$string['landing_cta_contact'] = 'Contact the school';
$string['landing_cookie_title'] = 'This site uses cookies';
$string['landing_cookie_body'] = 'We use essential cookies to keep you signed in and remember your preferences. Choose whether to allow optional cookies.';
$string['landing_cookie_reject'] = 'Reject all';
$string['landing_cookie_accept'] = 'Accept all cookies';
$string['landing_stat_categories'] = 'Categories';
$string['landing_stat_subjects'] = 'Subjects';
$string['landing_stat_activities'] = 'Learning activities';
$string['landing_stat_learners'] = 'Learners';
$string['landing_nocourses'] = 'Courses will appear here once they are published.';

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
$string['onb_plan_enrolments'] = 'Enrolments to create: {$a}.';
$string['onb_plan_modules'] = 'Activities to create: {$a}.';
$string['onb_err_enrolfields'] = 'An enrolment row is missing its user or course.';
$string['onb_err_enrolnocourse'] = 'Enrolment target course not found (and not created in this manifest): {$a}';
$string['onb_log_enrolled'] = 'Enrolled {$a->user} in {$a->course}';
$string['onb_log_cohortcreated'] = 'Created cohort: {$a}';
$string['onb_log_studentsgenerated'] = 'Generated {$a->count} students ({$a->prefix}NNN)';
$string['onb_knownbatches'] = 'Provisioned batches (available to roll back)';
$string['onb_nobatches'] = 'No provisioned batches yet.';

// Smart importer.
$string['import_pagename'] = 'KRECK smart import';
$string['import_intro'] = 'Upload any CSV or Excel file — your own layout, your own headers. KRECK guesses which column is which (and sniffs email columns even without a header), you confirm the mapping once, and it validates and creates the records. The mapping is remembered for next time.';
$string['import_type'] = 'What are you importing?';
$string['import_type_courses'] = 'Courses';
$string['import_type_students'] = 'Students / learners';
$string['import_type_staff'] = 'Staff / teachers';
$string['import_type_enrolments'] = 'Enrolments (user into course)';
$string['import_file'] = 'CSV or Excel file (.csv, .xlsx)';
$string['import_upload'] = 'Upload & preview';
$string['import_skip'] = '— Don\'t import —';
$string['import_mapheading'] = 'Map your columns';
$string['import_mapintro'] = 'We guessed how your columns map to KRECK fields. Fix anything wrong, set columns you don\'t need to "Don\'t import", then preview. Fields marked * are required.';
$string['import_rows'] = '{$a} data rows found.';
$string['import_col_header'] = 'Your column';
$string['import_col_field'] = 'Maps to';
$string['import_col_sample'] = 'Sample values';
$string['import_requirednote'] = 'Fields marked * are required. Email columns are detected by their shape even if the header is unusual.';
$string['import_confirm'] = 'Preview & validate';
$string['import_report'] = 'Validation report';
$string['import_saved'] = 'This column layout is remembered — the next file with these headers maps itself.';
$string['import_missingrequired'] = 'These required fields are not mapped yet: {$a}';
$string['import_provision'] = 'Create them now';
$string['import_backtomap'] = 'Back to mapping';
$string['import_nostaged'] = 'That import session expired or was not found. Please upload the file again.';
$string['import_done'] = 'Imported successfully. Batch id: {$a} — roll it back from the onboarding console if needed.';
$string['import_failed'] = 'Import failed and the partial batch was rolled back: {$a}';
$string['import_again'] = 'Import another file';

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

// KRECK <-> Gibbon deep-links (Phase D).
$string['gibbonbaseurl'] = 'KRECK SMS (Gibbon) base URL';
$string['gibbonbaseurl_desc'] = 'The root URL of your KRECK SMS (Gibbon) site, e.g. https://sms.yourschool.edu. Used to turn the stored relative links into working "View in KRECK SMS" links on course and user pages. Leave blank to hide those links.';
$string['viewinsms'] = 'View in KRECK SMS';

// LMS -> SMS link & mark export (Phase D, Legs B & C).
$string['exportlinks'] = 'Export LMS links to KRECK SMS';
$string['exportlinks_intro'] = 'Download a links file describing the Moodle courses and activities this plugin provisioned. Import it on the KRECK SMS (Gibbon) side so staff can open each class and its activities directly in the LMS ("Open in KRECK LMS").';
$string['exportlinks_nobatch'] = 'No onboarding batch found. Provision an institution from the onboarding console first, then return here to export its links.';
$string['exportlinks_batch'] = 'Batch id';
$string['exportlinks_courses'] = 'Courses';
$string['exportlinks_activities'] = 'Activities';
$string['exportlinks_download'] = 'Download links file';
$string['exportlinks_preview'] = 'Preview (newest batch)';
$string['exportmarks'] = 'Export marks to KRECK SMS';
$string['exportmarks_intro'] = 'Download a marks file from the Moodle gradebook for a provisioned batch. Import it on the KRECK SMS (Gibbon) side to keep each learner\'s results on their school record.';
$string['exportmarks_nobatch'] = 'No onboarding batch found. Provision an institution first, then return here to export marks.';
$string['exportmarks_batch'] = 'Batch id';
$string['exportmarks_courses'] = 'Courses';
$string['exportmarks_learners'] = 'Learners';
$string['exportmarks_download'] = 'Download marks (JSON)';
$string['exportmarks_downloadcsv'] = 'Download marks (CSV)';
$string['exportmarks_preview'] = 'Preview (newest batch)';
$string['onb_created'] = 'Created';
