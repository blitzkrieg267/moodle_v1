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
 * Turns the client intake tables (CSV or workbook) into an onboarding manifest.
 *
 * The column vocabulary lives here and is shared by the CSV paste mode and the
 * Excel workbook upload. Only user-supplied fields are recognised; everything
 * the system fills itself (course format, activities, auth, ...) is omitted.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\onboarding;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds manifest fragments from intake tables. Courses use the default blueprint.
 */
class csv_importer {

    /** @var string[] Categories sheet columns. */
    const CATEGORY_COLUMNS = ['category_path'];
    /** @var string[] Staff sheet columns. */
    const STAFF_COLUMNS = ['username', 'firstname', 'lastname', 'email'];
    /** @var string[] Cohorts sheet columns. */
    const COHORT_COLUMNS = ['name', 'idnumber'];
    /** @var string[] Students sheet columns. */
    const STUDENT_COLUMNS = ['username', 'firstname', 'lastname', 'email', 'cohort'];
    /** @var string[] Courses sheet columns. */
    const COURSE_COLUMNS = ['fullname', 'shortname', 'category_path', 'numsections',
        'startdate', 'summary', 'teachers', 'cohorts', 'format'];

    /**
     * Map a 2D table (first row = header) to associative rows, keeping only
     * allowed columns and dropping empty rows.
     *
     * @param array $table List of rows (each a list of cell values).
     * @param string[] $allowed
     * @return array List of associative rows.
     */
    public static function map_table(array $table, array $allowed): array {
        // Compact lookup so "Full name", "Start date", "Number of weeks" etc.
        // all resolve to the right key.
        $compactallowed = [];
        foreach ($allowed as $k) {
            $compactallowed[preg_replace('/[^a-z0-9]/', '', $k)] = $k;
        }
        $aliases = [
            'coursename' => 'fullname', 'course' => 'fullname',
            'coursecode' => 'shortname', 'code' => 'shortname',
            'categorypath' => 'category_path', 'category' => 'category_path',
            'weeks' => 'numsections', 'numberofweeks' => 'numsections', 'sections' => 'numsections',
            'startdate' => 'startdate', 'start' => 'startdate',
            'teacheremails' => 'teachers', 'teacheremail' => 'teachers', 'teacher' => 'teachers',
            'cohortids' => 'cohorts', 'cohortid' => 'cohort',
        ];

        $resolve = static function (string $raw) use ($allowed, $compactallowed, $aliases): ?string {
            $key = strtolower(trim($raw));
            $key = trim(preg_replace('/\(.*?\)/', '', $key)); // Drop parenthetical hints.
            if (in_array($key, $allowed, true)) {
                return $key;
            }
            $compact = preg_replace('/[^a-z0-9]/', '', $key);
            if (isset($compactallowed[$compact]) ) {
                return $compactallowed[$compact];
            }
            if (isset($aliases[$compact]) && in_array($aliases[$compact], $allowed, true)) {
                return $aliases[$compact];
            }
            return null;
        };

        $map = null;
        $rows = [];
        foreach ($table as $cells) {
            $cells = array_map(static fn($v) => is_string($v) ? trim($v) : (string) ($v ?? ''), (array) $cells);
            if ($map === null) {
                if (!array_filter($cells, static fn($v) => $v !== '')) {
                    continue; // Skip leading blank rows before the header.
                }
                $map = [];
                foreach ($cells as $i => $name) {
                    $key = $resolve($name);
                    if ($key !== null) {
                        $map[$i] = $key;
                    }
                }
                continue;
            }
            // Skip an example/comment row (first cell starts with #).
            if (isset($cells[0]) && strpos($cells[0], '#') === 0) {
                continue;
            }
            $row = [];
            foreach ($map as $i => $key) {
                $row[$key] = $cells[$i] ?? '';
            }
            if (array_filter($row, static fn($v) => $v !== '')) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * Parse CSV text into a 2D table.
     *
     * @param string $csv
     * @return array
     */
    protected static function csv_to_table(string $csv): array {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', trim($csv));
        if ($csv === '') {
            return [];
        }
        $table = [];
        foreach (preg_split('/\r\n|\r|\n/', $csv) as $line) {
            if (strpos(ltrim($line), '#') === 0) {
                continue;
            }
            $table[] = str_getcsv($line);
        }
        return $table;
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

    // ---- Per-entity builders from already-mapped associative rows ------------

    /**
     * @param array $rows
     * @return array categories[] fragment list.
     */
    public static function categories_from_rows(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            $path = $r['category_path'] ?? '';
            if ($path === '') {
                continue;
            }
            $parts = array_map('trim', explode('/', $path));
            $out[] = ['name' => end($parts), 'category_path' => $path];
        }
        return $out;
    }

    /**
     * @param array $rows
     * @return array users[] fragment list.
     */
    public static function staff_from_rows(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            if (($r['username'] ?? '') === '' && ($r['email'] ?? '') === '') {
                continue;
            }
            $out[] = [
                'username' => $r['username'] !== '' ? $r['username'] : $r['email'],
                'firstname' => $r['firstname'] ?? '',
                'lastname' => $r['lastname'] ?? '',
                'email' => $r['email'] ?? '',
            ];
        }
        return $out;
    }

    /**
     * @param array $rows
     * @return array cohorts[] fragment list.
     */
    public static function cohorts_from_rows(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            $name = $r['name'] ?? '';
            $idnumber = $r['idnumber'] ?? '';
            if ($name === '' && $idnumber === '') {
                continue;
            }
            $out[] = ['name' => $name !== '' ? $name : $idnumber, 'idnumber' => $idnumber];
        }
        return $out;
    }

    /**
     * @param array $rows
     * @return array students[] fragment list.
     */
    public static function students_from_rows(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            if (($r['username'] ?? '') === '' && ($r['email'] ?? '') === '') {
                continue;
            }
            $student = [
                'username' => $r['username'] !== '' ? $r['username'] : $r['email'],
                'firstname' => $r['firstname'] ?? 'Learner',
                'lastname' => $r['lastname'] ?? '',
                'email' => $r['email'] ?? '',
            ];
            if (($r['cohort'] ?? '') !== '') {
                $student['cohort'] = $r['cohort'];
            }
            $out[] = $student;
        }
        return $out;
    }

    /**
     * @param array $rows
     * @return array courses[] fragment list.
     */
    public static function courses_from_rows(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            if (($r['shortname'] ?? '') === '' || ($r['fullname'] ?? '') === '') {
                continue;
            }
            $course = [
                'fullname' => $r['fullname'],
                'shortname' => $r['shortname'],
                'format' => ($r['format'] ?? '') !== '' ? $r['format'] : 'weeks',
            ];
            foreach (['category_path', 'startdate', 'summary'] as $k) {
                if (($r[$k] ?? '') !== '') {
                    $course[$k] = $r[$k];
                }
            }
            if (($r['numsections'] ?? '') !== '') {
                $course['numsections'] = (int) $r['numsections'];
            }
            $teachers = self::multi($r['teachers'] ?? '');
            if ($teachers) {
                $course['teachers'] = $teachers;
            }
            $cohorts = self::multi($r['cohorts'] ?? '');
            if ($cohorts) {
                $course['cohorts'] = $cohorts;
            }
            $out[] = $course;
        }
        return $out;
    }

    /**
     * @param array $rows
     * @return array enrolments[] fragment list.
     */
    public static function enrol_from_rows(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            $user = $r['user'] ?? '';
            $course = $r['course'] ?? '';
            if ($user === '' || $course === '') {
                continue;
            }
            $e = ['user' => $user, 'course' => $course];
            if (($r['role'] ?? '') !== '') {
                $e['role'] = $r['role'];
            }
            $out[] = $e;
        }
        return $out;
    }

    // ---- Single-CSV convenience entry points --------------------------------

    /**
     * Build a manifest fragment { courses: [...] } from a courses CSV.
     *
     * @param string $csv
     * @return array
     */
    public static function courses_to_manifest(string $csv): array {
        $rows = self::map_table(self::csv_to_table($csv), self::COURSE_COLUMNS);
        return ['courses' => self::courses_from_rows($rows)];
    }

    /**
     * Build a manifest fragment { students: [...] } from a students CSV.
     *
     * @param string $csv
     * @return array
     */
    public static function students_to_manifest(string $csv): array {
        $rows = self::map_table(self::csv_to_table($csv), self::STUDENT_COLUMNS);
        return ['students' => self::students_from_rows($rows)];
    }
}
