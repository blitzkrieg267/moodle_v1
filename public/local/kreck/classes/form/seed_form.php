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
 * Seed form for entering sample KRECK data (admin/testing tool).
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\form;

use local_kreck\persistent\feeaccount;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/formslib.php");

/**
 * Lets an administrator put sample data on a learner so the cards populate.
 */
class seed_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition(): void {
        $mform = $this->_form;

        // Identity.
        $mform->addElement('header', 'identity', get_string('seed_identity', 'local_kreck'));
        $mform->addElement('text', 'username', get_string('seed_username', 'local_kreck'));
        $mform->setType('username', PARAM_USERNAME);
        $mform->addRule('username', null, 'required', null, 'client');
        $mform->addHelpButton('username', 'seed_username', 'local_kreck');
        $mform->addElement('text', 'studentid', get_string('seed_studentid', 'local_kreck'));
        $mform->setType('studentid', PARAM_RAW);
        $mform->addElement('text', 'classname', get_string('seed_classname', 'local_kreck'));
        $mform->setType('classname', PARAM_TEXT);

        // Engagement.
        $mform->addElement('header', 'engagement', get_string('seed_engagement', 'local_kreck'));
        $mform->addElement('text', 'streakcurrent', get_string('seed_streakcurrent', 'local_kreck'));
        $mform->setType('streakcurrent', PARAM_INT);
        $mform->setDefault('streakcurrent', 5);
        $mform->addElement('text', 'streaklongest', get_string('seed_streaklongest', 'local_kreck'));
        $mform->setType('streaklongest', PARAM_INT);
        $mform->setDefault('streaklongest', 12);

        // Attendance.
        $mform->addElement('header', 'attend', get_string('seed_attendance', 'local_kreck'));
        $mform->addElement('text', 'attendancetotal', get_string('seed_attendancetotal', 'local_kreck'));
        $mform->setType('attendancetotal', PARAM_INT);
        $mform->setDefault('attendancetotal', 20);
        $mform->addElement('text', 'attendancepresent', get_string('seed_attendancepresent', 'local_kreck'));
        $mform->setType('attendancepresent', PARAM_INT);
        $mform->setDefault('attendancepresent', 18);

        // Fees.
        $mform->addElement('header', 'feeshdr', get_string('seed_fees', 'local_kreck'));
        $mform->addElement('text', 'feebalance', get_string('seed_feebalance', 'local_kreck'));
        $mform->setType('feebalance', PARAM_FLOAT);
        $mform->setDefault('feebalance', 450);
        $mform->addElement('text', 'feecurrency', get_string('seed_feecurrency', 'local_kreck'));
        $mform->setType('feecurrency', PARAM_ALPHA);
        $mform->setDefault('feecurrency', 'BWP');
        $mform->addElement('select', 'feestatus', get_string('seed_feestatus', 'local_kreck'), [
            feeaccount::STATUS_CURRENT => get_string('feestatus_current', 'local_kreck'),
            feeaccount::STATUS_OVERDUE => get_string('feestatus_overdue', 'local_kreck'),
            feeaccount::STATUS_PAID => get_string('feestatus_paid', 'local_kreck'),
            feeaccount::STATUS_FAILED => get_string('feestatus_failed', 'local_kreck'),
        ]);
        $mform->setDefault('feestatus', feeaccount::STATUS_OVERDUE);

        // Topic struggle.
        $mform->addElement('header', 'topichdr', get_string('seed_topic', 'local_kreck'));
        $mform->addElement('text', 'topic', get_string('seed_topicname', 'local_kreck'));
        $mform->setType('topic', PARAM_TEXT);
        $mform->setDefault('topic', 'Fractions');
        $mform->addElement('text', 'topicattempts', get_string('seed_topicattempts', 'local_kreck'));
        $mform->setType('topicattempts', PARAM_INT);
        $mform->setDefault('topicattempts', 10);
        $mform->addElement('text', 'topicwrong', get_string('seed_topicwrong', 'local_kreck'));
        $mform->setType('topicwrong', PARAM_INT);
        $mform->setDefault('topicwrong', 7);

        $this->add_action_buttons(true, get_string('seed_submit', 'local_kreck'));
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);

        if (!empty($data['username'])
                && !$DB->record_exists('user', ['username' => $data['username'], 'deleted' => 0])) {
            $errors['username'] = get_string('seed_nouser', 'local_kreck');
        }
        if ((int) $data['attendancepresent'] > (int) $data['attendancetotal']) {
            $errors['attendancepresent'] = get_string('seed_presenttoohigh', 'local_kreck');
        }
        if ((int) $data['topicwrong'] > (int) $data['topicattempts']) {
            $errors['topicwrong'] = get_string('seed_wrongtoohigh', 'local_kreck');
        }
        return $errors;
    }
}
