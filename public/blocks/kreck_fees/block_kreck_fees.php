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
 * KRECK fees block.
 *
 * @package   block_kreck_fees
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A fee-status card driven by local_kreck.
 */
class block_kreck_fees extends block_base {

    /**
     * Initialise.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_kreck_fees');
    }

    /**
     * Where the block may be added.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return ['all' => false, 'my' => true, 'site' => true];
    }

    /**
     * One per page.
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

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        if (!class_exists('\\local_kreck\\api')) {
            $this->content->text = $OUTPUT->notification(
                get_string('nodataplugin', 'block_kreck_fees'),
                \core\output\notification::NOTIFY_INFO
            );
            return $this->content;
        }

        $summary = \local_kreck\api::get_learner_summary((int) $USER->id);
        $data = $this->build_template_context($summary);
        $this->content->text = $OUTPUT->render_from_template('block_kreck_fees/card', $data);

        return $this->content;
    }

    /**
     * Flatten the summary for the template.
     *
     * @param array $summary
     * @return array
     */
    protected function build_template_context(array $summary): array {
        $fees = $summary['fees'];
        $attendance = $summary['attendance'];

        $statusmap = [
            \local_kreck\persistent\feeaccount::STATUS_CURRENT => ['feestatus_current', 'ok'],
            \local_kreck\persistent\feeaccount::STATUS_OVERDUE => ['feestatus_overdue', 'warn'],
            \local_kreck\persistent\feeaccount::STATUS_PAID => ['feestatus_paid', 'ok'],
            \local_kreck\persistent\feeaccount::STATUS_FAILED => ['feestatus_failed', 'bad'],
        ];
        [$statuskey, $statusmod] = $statusmap[$fees['status']] ?? ['feestatus_current', 'ok'];

        $isoverdue = in_array((int) $fees['status'], [
            \local_kreck\persistent\feeaccount::STATUS_OVERDUE,
            \local_kreck\persistent\feeaccount::STATUS_FAILED,
        ], true);

        $hasattendance = $attendance['rate'] !== null;

        return [
            'hasaccount' => (bool) $fees['hasaccount'],
            'amount' => number_format((float) $fees['balance'], 2),
            'currency' => $fees['currency'],
            'statuslabel' => get_string($statuskey, 'block_kreck_fees'),
            'statusmod' => $statusmod,
            'isoverdue' => $isoverdue,
            'ispaidup' => (int) $fees['status'] === \local_kreck\persistent\feeaccount::STATUS_PAID
                || ((float) $fees['balance'] <= 0),

            'hasattendance' => $hasattendance,
            'attendancepct' => $hasattendance ? (int) round($attendance['rate'] * 100) : 0,

            'hasstudentid' => !empty($summary['student']['studentid']),
            'studentid' => $summary['student']['studentid'] ?? '',
        ];
    }
}
