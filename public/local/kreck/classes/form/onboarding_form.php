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
 * Onboarding form: choose dry-run / provision / rollback and supply a manifest.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\form;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/formslib.php");

/**
 * The onboarding control form.
 */
class onboarding_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('select', 'mode', get_string('onb_mode', 'local_kreck'), [
            'dryrun' => get_string('onb_mode_dryrun', 'local_kreck'),
            'execute' => get_string('onb_mode_execute', 'local_kreck'),
            'csv' => get_string('onb_mode_csv', 'local_kreck'),
            'rollback' => get_string('onb_mode_rollback', 'local_kreck'),
        ]);
        $mform->setDefault('mode', 'dryrun');

        $mform->addElement('textarea', 'manifest', get_string('onb_manifest', 'local_kreck'),
            ['rows' => 16, 'cols' => 80, 'style' => 'font-family:monospace;']);
        $mform->setType('manifest', PARAM_RAW);
        $mform->addHelpButton('manifest', 'onb_manifest', 'local_kreck');
        $mform->hideIf('manifest', 'mode', 'eq', 'rollback');
        $mform->hideIf('manifest', 'mode', 'eq', 'csv');

        $mform->addElement('textarea', 'coursescsv', get_string('onb_coursescsv', 'local_kreck'),
            ['rows' => 10, 'cols' => 80, 'style' => 'font-family:monospace;']);
        $mform->setType('coursescsv', PARAM_RAW);
        $mform->addHelpButton('coursescsv', 'onb_coursescsv', 'local_kreck');
        $mform->hideIf('coursescsv', 'mode', 'neq', 'csv');

        $mform->addElement('text', 'rollbackbatch', get_string('onb_rollbackbatch', 'local_kreck'), ['size' => 42]);
        $mform->setType('rollbackbatch', PARAM_RAW_TRIMMED);
        $mform->hideIf('rollbackbatch', 'mode', 'neq', 'rollback');

        $this->add_action_buttons(false, get_string('onb_submit', 'local_kreck'));
    }
}
