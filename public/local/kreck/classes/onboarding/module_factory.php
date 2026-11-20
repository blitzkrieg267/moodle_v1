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
 * Creates activity modules the native LMS way (add_moduleinfo).
 *
 * No custom tables, no hand-rolled DB inserts: every module is built with the
 * exact field set core's add_moduleinfo expects, verified against this LMS.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\onboarding;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds core activity modules from a simple spec.
 */
class module_factory {

    /** @var string[] Module types this factory can create natively. */
    const SUPPORTED = ['forum', 'page', 'assign', 'quiz', 'url', 'label'];

    /**
     * Map convenience aliases onto a natively supported module.
     *
     * @param string $type
     * @return string
     */
    public static function normalise_type(string $type): string {
        $aliases = [
            'resource' => 'page',   // "reference notes" without a file upload.
            'lesson' => 'url',      // "self-paced path" placeholder.
            'announcement' => 'forum',
        ];
        $type = strtolower(trim($type));
        return $aliases[$type] ?? $type;
    }

    /**
     * Create one activity module in a course section.
     *
     * @param \stdClass $course Full course record.
     * @param int $sectionnum Section number (0 = general).
     * @param array $spec ['type','name','intro','content','externalurl','forumtype']
     * @return int The new course module id.
     */
    public static function create(\stdClass $course, int $sectionnum, array $spec): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $type = self::normalise_type($spec['type'] ?? 'label');
        if (!in_array($type, self::SUPPORTED, true)) {
            throw new \moodle_exception('onb_unsupportedmodule', 'local_kreck', '', $type);
        }

        $name = $spec['name'] ?? ucfirst($type);
        $intro = $spec['intro'] ?? '';

        // A course already has one auto-created Announcements (news) forum, so
        // reuse it rather than making a duplicate.
        if ($type === 'forum' && ($spec['forumtype'] ?? '') === 'news') {
            $existing = $DB->get_record('forum', ['course' => $course->id, 'type' => 'news']);
            if ($existing) {
                $cm = get_coursemodule_from_instance('forum', $existing->id, $course->id, false, IGNORE_MISSING);
                if ($cm) {
                    return (int) $cm->id;
                }
            }
        }

        $mi = new \stdClass();
        $mi->modulename = $type;
        $mi->module = $DB->get_field('modules', 'id', ['name' => $type], MUST_EXIST);
        $mi->course = $course->id;
        $mi->section = $sectionnum;
        $mi->name = $name;
        $mi->visible = 1;
        $mi->visibleoncoursepage = 1;
        $mi->cmidnumber = '';
        $mi->introeditor = ['text' => $intro, 'format' => FORMAT_HTML, 'itemid' => 0];
        $mi->showdescription = 0;
        $mi->groupmode = 0;
        $mi->groupingid = 0;
        $mi->completion = 0;
        $mi->completionview = 0;
        $mi->completionexpected = 0;
        $mi->completiongradeitemnumber = null;
        $mi->availabilityconditionsjson = '';

        self::apply_type_defaults($mi, $type, $spec);

        $result = add_moduleinfo($mi, $course);
        return (int) $result->coursemodule;
    }

    /**
     * Convert a strict local date/time pair to a LMS timestamp.
     *
     * @param string $date ISO date (YYYY-MM-DD).
     * @param string $time 24-hour time (HH:MM or HH:MM:SS).
     * @param string $timezone IANA timezone from the source site.
     * @param string $field Human-readable field name for validation errors.
     * @return int
     */
    public static function timestamp(string $date, string $time, string $timezone, string $field): int {
        $date = trim($date);
        $time = trim($time);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) {
            throw new \moodle_exception('onb_invalidschedule', 'local_kreck', '', $field);
        }

        try {
            $zone = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            throw new \moodle_exception('onb_invalidtimezone', 'local_kreck', '', $timezone);
        }

        $normalisedtime = strlen($time) === 5 ? $time . ':00' : $time;
        $datetime = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $normalisedtime, $zone);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$datetime || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                || $datetime->format('Y-m-d H:i:s') !== $date . ' ' . $normalisedtime) {
            throw new \moodle_exception('onb_invalidschedule', 'local_kreck', '', $field);
        }

        return $datetime->getTimestamp();
    }

    /**
     * Resolve an optional assignment date/time pair.
     *
     * @param array $spec Assignment fields.
     * @param string $datekey Date field key.
     * @param string $timekey Time field key.
     * @param string $timezone IANA timezone.
     * @param string $label Human-readable field name.
     * @param bool $required Whether both values are mandatory.
     * @return int Zero when an optional pair is empty.
     */
    protected static function assignment_timestamp(
        array $spec,
        string $datekey,
        string $timekey,
        string $timezone,
        string $label,
        bool $required = false
    ): int {
        $date = trim((string) ($spec[$datekey] ?? ''));
        $time = trim((string) ($spec[$timekey] ?? ''));
        if ($date === '' && $time === '' && !$required) {
            return 0;
        }
        if ($date === '' || $time === '') {
            throw new \moodle_exception('onb_invalidschedule', 'local_kreck', '', $label);
        }
        return self::timestamp($date, $time, $timezone, $label);
    }

    /**
     * Add the per-module-type required fields.
     *
     * @param \stdClass $mi
     * @param string $type
     * @param array $spec
     */
    protected static function apply_type_defaults(\stdClass $mi, string $type, array $spec): void {
        switch ($type) {
            case 'forum':
                $mi->type = $spec['forumtype'] ?? 'general';
                $mi->forcesubscribe = ($mi->type === 'news') ? 1 : 0;
                $mi->assessed = 0;
                $mi->scale = 0;
                $mi->grade_forum = 0;
                break;

            case 'page':
                $content = $spec['content'] ?? '<p>[INSERT LESSON CONTENT HERE] — replace this with your notes. '
                    . 'To add a video or file later, edit the page and use the editor toolbar.</p>';
                $mi->page = ['text' => $content, 'format' => FORMAT_HTML, 'itemid' => 0];
                $mi->contentformat = FORMAT_HTML;
                $mi->display = 5;
                $mi->printheading = 1;
                $mi->printintro = 0;
                $mi->printlastmodified = 1;
                break;

            case 'url':
                $mi->externalurl = $spec['externalurl'] ?? 'https://example.com/replace-this-link';
                $mi->display = 0;
                $mi->parameter = [];
                $mi->config = new \stdClass();
                break;

            case 'label':
                // Intro editor is the whole content; nothing else required.
                break;

            case 'assign':
                $timezone = trim((string) ($spec['timezone'] ?? date_default_timezone_get()));
                $opendate = self::assignment_timestamp($spec, 'open_date', 'open_time', $timezone, 'opening date/time');
                $duedate = self::assignment_timestamp($spec, 'due_date', 'due_time', $timezone, 'due date/time',
                    !empty($spec['source_ref']));
                $cutoffdate = self::assignment_timestamp($spec, 'cutoff_date', 'cutoff_time', $timezone, 'cutoff date/time');
                if ($opendate > 0 && $duedate > 0 && $opendate >= $duedate) {
                    throw new \moodle_exception('onb_invalidassignmentdates', 'local_kreck');
                }
                if ($cutoffdate > 0 && $duedate > 0 && $cutoffdate < $duedate) {
                    throw new \moodle_exception('onb_invalidassignmentdates', 'local_kreck');
                }
                $mi->assignsubmission_onlinetext_enabled = 1;
                $mi->assignsubmission_file_enabled = 0;
                $mi->assignsubmission_file_maxfiles = 1;
                $mi->assignsubmission_file_maxsizebytes = 0;
                $mi->assignfeedback_comments_enabled = 1;
                $mi->duedate = $duedate;
                $mi->allowsubmissionsfromdate = $opendate;
                $mi->cutoffdate = $cutoffdate;
                $mi->gradingduedate = 0;
                $mi->submissiondrafts = 0;
                $mi->requiresubmissionstatement = 0;
                $mi->sendnotifications = 0;
                $mi->sendlatenotifications = 0;
                $mi->sendstudentnotifications = 1;
                $mi->teamsubmission = 0;
                $mi->requireallteammemberssubmit = 0;
                $mi->teamsubmissiongroupingid = 0;
                $mi->blindmarking = 0;
                $mi->markingworkflow = 0;
                $mi->markingallocation = 0;
                $mi->attemptreopenmethod = 'none';
                $mi->maxattempts = -1;
                $mi->grade = (float) ($spec['grade'] ?? 100);
                if ($mi->grade <= 0) {
                    throw new \moodle_exception('onb_invalidassignmentgrade', 'local_kreck');
                }
                $mi->gradepass = 0;
                break;

            case 'quiz':
                $mi->timeopen = 0;
                $mi->timeclose = 0;
                $mi->timelimit = 0;
                $mi->overduehandling = 'autosubmit';
                $mi->graceperiod = 0;
                $mi->preferredbehaviour = 'deferredfeedback';
                $mi->canredoquestions = 0;
                $mi->attempts = 0;
                $mi->attemptonlast = 0;
                $mi->grademethod = 1;
                $mi->decimalpoints = 2;
                $mi->questiondecimalpoints = -1;
                $mi->shuffleanswers = 1;
                $mi->questionsperpage = 1;
                $mi->navmethod = 'free';
                $mi->grade = 100;
                $mi->gradepass = 0;
                $mi->sumgrades = 0;
                $mi->quizpassword = '';
                $mi->password = '';
                $mi->subnet = '';
                $mi->browsersecurity = '-';
                $mi->delay1 = 0;
                $mi->delay2 = 0;
                $mi->showuserpicture = 0;
                $mi->showblocks = 0;
                // Permissive review options (all bits set for both passes).
                $allreview = 0x3fff | (0x3fff << 16);
                foreach (['reviewattempt', 'reviewcorrectness', 'reviewmarks', 'reviewspecificfeedback',
                        'reviewgeneralfeedback', 'reviewrightanswer', 'reviewoverallfeedback', 'reviewmaxmarks'] as $f) {
                    $mi->$f = $allreview;
                }
                break;
        }
    }
}
