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
 * KRECK MicroLearn data facade.
 *
 * This is the single contract the theme, dashboard blocks and web services
 * read through. Keeping it here means the presentation layer never touches
 * the database directly, and the SMS wiring can change behind a stable shape.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck;

use local_kreck\persistent\student;
use local_kreck\persistent\streak;
use local_kreck\persistent\feeaccount;
use local_kreck\persistent\attendance;
use local_kreck\persistent\topicstat;

/**
 * Read-side API for a learner's KRECK record.
 */
class api {

    /**
     * Build the learner summary consumed by the MicroLearn dashboards.
     *
     * Every section degrades gracefully: a learner with no KRECK data yet gets
     * a well-formed summary of zeros rather than an error, so the UI always
     * renders.
     *
     * @param int $userid The learner's Moodle user id.
     * @param int $attendancedays Window (in days) for the attendance rate.
     * @return array
     */
    public static function get_learner_summary(int $userid, int $attendancedays = 30): array {
        return [
            'userid' => $userid,
            'student' => self::student_block($userid),
            'attendance' => self::attendance_block($userid, $attendancedays),
            'fees' => self::fee_block($userid),
            'streak' => self::streak_block($userid),
            'struggletopics' => self::struggle_topics($userid),
        ];
    }

    /**
     * Identity block.
     *
     * @param int $userid
     * @return array
     */
    protected static function student_block(int $userid): array {
        $record = student::get_record(['userid' => $userid]);
        return [
            'studentid' => $record ? $record->get('studentid') : null,
            'schoolid' => $record ? $record->get('schoolid') : null,
            'classname' => $record ? $record->get('classname') : null,
            'stream' => $record ? $record->get('stream') : null,
        ];
    }

    /**
     * Attendance rate over a recent window.
     *
     * @param int $userid
     * @param int $days
     * @return array
     */
    protected static function attendance_block(int $userid, int $days): array {
        global $DB;

        $since = time() - ($days * DAYSECS);
        $rows = $DB->get_records_select(
            attendance::TABLE,
            'userid = :userid AND sessiondate >= :since',
            ['userid' => $userid, 'since' => $since]
        );

        $total = count($rows);
        $present = 0;
        foreach ($rows as $row) {
            if ((int) $row->status === attendance::STATUS_PRESENT || (int) $row->status === attendance::STATUS_LATE) {
                $present++;
            }
        }

        return [
            'windowdays' => $days,
            'sessions' => $total,
            'present' => $present,
            'rate' => $total > 0 ? round($present / $total, 4) : null,
        ];
    }

    /**
     * Fee balance block.
     *
     * @param int $userid
     * @return array
     */
    protected static function fee_block(int $userid): array {
        $account = feeaccount::get_record(['userid' => $userid]);
        if (!$account) {
            return [
                'hasaccount' => false,
                'balance' => 0.0,
                'currency' => 'BWP',
                'status' => feeaccount::STATUS_CURRENT,
            ];
        }
        return [
            'hasaccount' => true,
            'balance' => $account->get_balance_major(),
            'currency' => $account->get('currency'),
            'status' => (int) $account->get('status'),
        ];
    }

    /**
     * Streak block.
     *
     * @param int $userid
     * @return array
     */
    protected static function streak_block(int $userid): array {
        $record = streak::get_record(['userid' => $userid]);
        return [
            'current' => $record ? (int) $record->get('current') : 0,
            'longest' => $record ? (int) $record->get('longest') : 0,
            'lastactive' => $record ? $record->get('lastactive') : null,
        ];
    }

    /**
     * The topics a learner is struggling with most.
     *
     * @param int $userid
     * @param int $limit
     * @param int $minattempts Ignore topics with too little evidence.
     * @return array
     */
    protected static function struggle_topics(int $userid, int $limit = 3, int $minattempts = 3): array {
        $records = topicstat::get_records(['userid' => $userid]);

        $topics = [];
        foreach ($records as $record) {
            if ((int) $record->get('attempts') < $minattempts) {
                continue;
            }
            $topics[] = [
                'topic' => $record->get('topic'),
                'rate' => round($record->get_struggle_rate(), 4),
                'attempts' => (int) $record->get('attempts'),
            ];
        }

        // Hardest topics first.
        usort($topics, static function ($a, $b) {
            return $b['rate'] <=> $a['rate'];
        });

        return array_slice($topics, 0, $limit);
    }
}
