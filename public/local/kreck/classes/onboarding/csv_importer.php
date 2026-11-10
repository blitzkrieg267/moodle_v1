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
 * Turns a simple CSV (the kind a client hands over) into an onboarding manifest.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\onboarding;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds manifest fragments from CSV text. Courses use the default blueprint.
 */
class csv_importer {

    /**
     * Columns recognised in a courses CSV (header row, any order, extras ignored).
     *
     * @var string[]
     */
    const COURSE_COLUMNS = ['fullname', 'shortname', 'category_path', 'numsections',
        'startdate', 'summary', 'teachers', 'cohorts', 'format'];

    /**
     * Columns recognised in a students CSV.
     *
     * @var string[]
     */
    const STUDENT_COLUMNS = ['username', 'firstname', 'lastname', 'email', 'cohort'];

    /**
     * Parse CSV text into rows keyed by header name.
     *
     * @param string $csv
     * @param string[] $allowed Columns to keep.
     * @return array List of associative rows.
     */
    protected static function rows(string $csv, array $allowed): array {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', trim($csv)); // Strip BOM.
        if ($csv === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $csv);
        $header = null;
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '' || strpos(ltrim($line), '#') === 0) {
                continue;
            }
            $fields = str_getcsv($line);
            if ($header === null) {
                $header = array_map(static fn($h) => strtolower(trim($h)), $fields);
                continue;
            }
            $row = [];
            foreach ($header as $i => $name) {
                if (in_array($name, $allowed, true)) {
                    $row[$name] = isset($fields[$i]) ? trim($fields[$i]) : '';
                }
            }
            if (!empty(array_filter($row, static fn($v) => $v !== ''))) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * Split a `;`-separated cell into a trimmed list.
     *
     * @param string $value
     * @return array
     */
    protected static function multi(string $value): array {
        if (trim($value) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(';', $value)), static fn($v) => $v !== ''));
    }

    /**
     * Build a manifest fragment { courses: [...] } from a courses CSV.
     *
     * @param string $csv
     * @return array
     */
    public static function courses_to_manifest(string $csv): array {
        $courses = [];
        foreach (self::rows($csv, self::COURSE_COLUMNS) as $row) {
            if (($row['shortname'] ?? '') === '' || ($row['fullname'] ?? '') === '') {
                continue;
            }
            $course = [
                'fullname' => $row['fullname'],
                'shortname' => $row['shortname'],
                'format' => ($row['format'] ?? '') !== '' ? $row['format'] : 'weeks',
            ];
            if (($row['category_path'] ?? '') !== '') {
                $course['category_path'] = $row['category_path'];
            }
            if (($row['numsections'] ?? '') !== '') {
                $course['numsections'] = (int) $row['numsections'];
            }
            if (($row['startdate'] ?? '') !== '') {
                $course['startdate'] = $row['startdate'];
            }
            if (($row['summary'] ?? '') !== '') {
                $course['summary'] = $row['summary'];
            }
            $teachers = self::multi($row['teachers'] ?? '');
            if ($teachers) {
                $course['teachers'] = $teachers;
            }
            $cohorts = self::multi($row['cohorts'] ?? '');
            if ($cohorts) {
                $course['cohorts'] = $cohorts;
            }
            $courses[] = $course;
        }
        return ['courses' => $courses];
    }

    /**
     * Build a manifest fragment { students: [...] } from a students CSV.
     *
     * @param string $csv
     * @return array
     */
    public static function students_to_manifest(string $csv): array {
        $students = [];
        foreach (self::rows($csv, self::STUDENT_COLUMNS) as $row) {
            if (($row['username'] ?? '') === '' && ($row['email'] ?? '') === '') {
                continue;
            }
            $student = [
                'username' => $row['username'] !== '' ? $row['username'] : $row['email'],
                'firstname' => $row['firstname'] ?? 'Learner',
                'lastname' => $row['lastname'] ?? '',
                'email' => $row['email'] ?? '',
            ];
            if (($row['cohort'] ?? '') !== '') {
                $student['cohort'] = $row['cohort'];
            }
            $students[] = $student;
        }
        return ['students' => $students];
    }
}
