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
 * KRECK learning streak block.
 *
 * @package   block_kreck_streak
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A learner snapshot block driven by local_kreck.
 */
class block_kreck_streak extends block_base {

    /**
     * Initialise the block.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_kreck_streak');
    }

    /**
     * Where the block may be added.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'all' => false,
            'my' => true,
            'site' => true,
            'course-view' => true,
        ];
    }

    /**
     * Only one per page.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * No global config.
     *
     * @return bool
     */
    public function has_config(): bool {
        return false;
    }

    /**
     * Build the block body.
     *
     * @return stdClass
     */
    public function get_content() {
        global $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';
        $this->content->text = '';

        // Nothing meaningful to show to logged-out or guest users.
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        // Degrade gracefully if the data backbone is absent.
        if (!class_exists('\\local_kreck\\api')) {
            $this->content->text = $OUTPUT->notification(
                get_string('nodataplugin', 'block_kreck_streak'),
                \core\output\notification::NOTIFY_INFO
            );
            return $this->content;
        }

        $summary = \local_kreck\api::get_learner_summary((int) $USER->id);
        $data = $this->build_template_context($summary);
        $this->content->text = $OUTPUT->render_from_template('block_kreck_streak/card', $data);

        return $this->content;
    }

    /**
     * Turn the local_kreck summary into a flat template context.
     *
     * @param array $summary
     * @return array
     */
    protected function build_template_context(array $summary): array {
        $streak = $summary['streak'];
        $attendance = $summary['attendance'];
        $fees = $summary['fees'];
        $struggle = $summary['struggletopics'];

        // Attendance.
        $hasattendance = $attendance['rate'] !== null;

        // Fees.
        $feestatusmap = [
            \local_kreck\persistent\feeaccount::STATUS_CURRENT => ['feestatus_current', 'ok'],
            \local_kreck\persistent\feeaccount::STATUS_OVERDUE => ['feestatus_overdue', 'warn'],
            \local_kreck\persistent\feeaccount::STATUS_PAID => ['feestatus_paid', 'ok'],
            \local_kreck\persistent\feeaccount::STATUS_FAILED => ['feestatus_failed', 'bad'],
        ];
        [$feestatuskey, $feestatusmod] = $feestatusmap[$fees['status']] ?? ['feestatus_current', 'ok'];

        // Top struggle topic, if any.
        $hasstruggle = !empty($struggle);
        $topstruggle = $hasstruggle ? $struggle[0] : null;

        return [
            'streakcurrent' => (int) $streak['current'],
            'streaklongest' => (int) $streak['longest'],
            'streakplural' => (int) $streak['current'] !== 1,

            'hasattendance' => $hasattendance,
            'attendancepct' => $hasattendance ? (int) round($attendance['rate'] * 100) : 0,

            'hasfees' => (bool) $fees['hasaccount'],
            'feeamount' => number_format((float) $fees['balance'], 2),
            'feecurrency' => $fees['currency'],
            'feestatuslabel' => get_string($feestatuskey, 'block_kreck_streak'),
            'feestatusmod' => $feestatusmod,

            'hasstruggle' => $hasstruggle,
            'struggletopic' => $hasstruggle ? $topstruggle['topic'] : '',
            'strugglepct' => $hasstruggle ? (int) round($topstruggle['rate'] * 100) : 0,

            'hasstudentid' => !empty($summary['student']['studentid']),
            'studentid' => $summary['student']['studentid'] ?? '',
        ];
    }
}
