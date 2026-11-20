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
 * Queue LMS changes for signed delivery to SMS.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\sync;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer callbacks for the durable sync outbox.
 */
class observer {

    /** @var int Nested suppression depth while applying an inbound SMS snapshot. */
    private static $suppressiondepth = 0;

    /**
     * Suppress outbound events while applying SMS-owned changes.
     */
    public static function suspend(): void {
        self::$suppressiondepth++;
    }

    /**
     * Resume outbound event recording.
     */
    public static function resume(): void {
        if (self::$suppressiondepth < 1) {
            throw new \coding_exception('LMS-to-SMS sync suppression was not active.');
        }
        self::$suppressiondepth--;
    }

    public static function course_created(\core\event\course_created $event): void {
        self::record($event, 'course', 'created');
    }

    public static function course_updated(\core\event\course_updated $event): void {
        self::record($event, 'course', 'updated');
    }

    public static function course_deleted(\core\event\course_deleted $event): void {
        self::record($event, 'course', 'deleted');
    }

    public static function user_created(\core\event\user_created $event): void {
        self::record($event, 'user', 'created');
    }

    public static function user_updated(\core\event\user_updated $event): void {
        self::record($event, 'user', 'updated');
    }

    public static function user_deleted(\core\event\user_deleted $event): void {
        self::record($event, 'user', 'deleted');
    }

    public static function course_module_created(\core\event\course_module_created $event): void {
        self::record($event, 'activity', 'created');
    }

    public static function course_module_updated(\core\event\course_module_updated $event): void {
        self::record($event, 'activity', 'updated');
    }

    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        self::record($event, 'activity', 'deleted');
    }

    public static function user_enrolment_created(\core\event\user_enrolment_created $event): void {
        self::record($event, 'enrolment', 'created');
    }

    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event): void {
        self::record($event, 'enrolment', 'updated');
    }

    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event): void {
        self::record($event, 'enrolment', 'deleted');
    }

    public static function user_graded(\core\event\user_graded $event): void {
        self::record($event, 'grade', 'updated');
    }

    /**
     * Store only identifiers and event metadata; no names, emails, grades, or other learner data.
     *
     * @param \core\event\base $event
     * @param string $entitytype
     * @param string $action
     */
    private static function record(\core\event\base $event, string $entitytype, string $action): void {
        global $DB;

        if (self::$suppressiondepth > 0) {
            return;
        }

        $eventdata = $event->get_data();
        $courseid = (int) ($eventdata['courseid'] ?? 0);
        $entityid = (string) ($eventdata['objectid'] ?? 0);
        $course = $courseid > 0
            ? $DB->get_record('course', ['id' => $courseid], 'id,shortname', IGNORE_MISSING)
            : false;
        $payload = [
            'schema_version' => '1.0',
            'source' => 'moodle',
            'event_id' => bin2hex(random_bytes(16)),
            'event_type' => $event->get_name(),
            'entity_type' => $entitytype,
            'entity_id' => $entityid,
            'action' => $action,
            'occurred_at' => gmdate('c', (int) ($eventdata['timecreated'] ?? time())),
            'course_id' => $courseid ?: null,
            'course_shortname' => $course ? $course->shortname : null,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \coding_exception('Could not encode LMS sync event: ' . json_last_error_msg());
        }

        $now = time();
        $DB->insert_record('local_kreck_sync_event', (object) [
            'eventid' => $payload['event_id'],
            'eventtype' => $payload['event_type'],
            'entitytype' => $entitytype,
            'entityid' => $entityid,
            'action' => $action,
            'payload' => $json,
            'status' => 0,
            'attempts' => 0,
            'nextrun' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
