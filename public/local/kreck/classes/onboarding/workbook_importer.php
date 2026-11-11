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
 * Reads a KRECK institution intake workbook (.xlsx) into an onboarding manifest.
 *
 * Uses Moodle's bundled PhpSpreadsheet. Each named sheet maps to one entity;
 * unknown sheets and columns are ignored, so the human form can carry extra
 * notes without breaking the import.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\onboarding;

defined('MOODLE_INTERNAL') || die();

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Intake workbook reader.
 */
class workbook_importer {

    /**
     * Build a full manifest array from an .xlsx file on disk.
     *
     * @param string $path
     * @return array
     */
    public static function from_file(string $path): array {
        if (!is_readable($path)) {
            throw new \moodle_exception('onb_wb_unreadable', 'local_kreck');
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $e) {
            throw new \moodle_exception('onb_wb_badfile', 'local_kreck', '', null, $e->getMessage());
        }

        $manifest = [];

        $categories = csv_importer::categories_from_rows(
            self::rows($spreadsheet, 'Categories', csv_importer::CATEGORY_COLUMNS));
        if ($categories) {
            $manifest['categories'] = $categories;
        }

        $users = csv_importer::staff_from_rows(
            self::rows($spreadsheet, 'Staff', csv_importer::STAFF_COLUMNS));
        if ($users) {
            $manifest['users'] = $users;
        }

        $cohorts = csv_importer::cohorts_from_rows(
            self::rows($spreadsheet, 'Cohorts', csv_importer::COHORT_COLUMNS));
        if ($cohorts) {
            $manifest['cohorts'] = $cohorts;
        }

        $students = csv_importer::students_from_rows(
            self::rows($spreadsheet, 'Students', csv_importer::STUDENT_COLUMNS));
        if ($students) {
            $manifest['students'] = $students;
        }

        $courses = csv_importer::courses_from_rows(
            self::rows($spreadsheet, 'Courses', csv_importer::COURSE_COLUMNS));
        $manifest['courses'] = $courses;

        return $manifest;
    }

    /**
     * Read one named sheet into mapped associative rows.
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @param string $sheetname
     * @param string[] $allowed
     * @return array
     */
    protected static function rows($spreadsheet, string $sheetname, array $allowed): array {
        $sheet = $spreadsheet->getSheetByName($sheetname);
        if (!$sheet) {
            return [];
        }
        // 2D array of values (formatted strings), 1-based rows flattened to lists.
        $table = $sheet->toArray(null, true, false, false);
        return csv_importer::map_table($table, $allowed);
    }
}
