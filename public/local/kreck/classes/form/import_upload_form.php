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
 * Smart importer step 1: choose a type and upload a CSV/Excel file.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\form;

use local_kreck\import\field_registry;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/formslib.php");

/**
 * Upload form for the smart importer.
 */
class import_upload_form extends \moodleform {

    /**
     * Definition.
     */
    public function definition(): void {
        $mform = $this->_form;

        $types = [];
        foreach (field_registry::types() as $slug => $strid) {
            $types[$slug] = get_string($strid, 'local_kreck');
        }
        $mform->addElement('select', 'type', get_string('import_type', 'local_kreck'), $types);

        $mform->addElement('filepicker', 'importfile', get_string('import_file', 'local_kreck'),
            null, ['accepted_types' => ['.csv', '.xlsx']]);
        $mform->addRule('importfile', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('import_upload', 'local_kreck'));
    }
}
