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
 * The target fields each smart import can map columns onto.
 *
 * Each field: key, label, required flag, header synonyms, a type, and an
 * optional content-sniff regex (for columns whose values have a distinctive
 * shape, like email). This drives both the auto-mapping heuristics and the
 * mapping dropdowns.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\import;

defined('MOODLE_INTERNAL') || die();

/**
 * Field definitions per import type.
 */
class field_registry {

    /** @var string A column the user chose not to import. */
    const SKIP = '';

    /** @var string Email shape for content sniffing. */
    const EMAIL_RE = '/^[^@\s]+@[^@\s]+\.[^@\s]+$/';

    /**
     * Import types this plugin supports.
     *
     * @return array slug => label string id
     */
    public static function types(): array {
        return [
            'courses' => 'import_type_courses',
            'students' => 'import_type_students',
        ];
    }

    /**
     * The field list for a type.
     *
     * @param string $type
     * @return array
     */
    public static function fields(string $type): array {
        $registry = [
            'courses' => [
                ['key' => 'fullname', 'label' => 'Full name', 'required' => true,
                    'synonyms' => ['course name', 'course title', 'title', 'name', 'subject']],
                ['key' => 'shortname', 'label' => 'Short name', 'required' => true,
                    'synonyms' => ['course code', 'code', 'short code', 'short name', 'ref']],
                ['key' => 'category_path', 'label' => 'Category path', 'required' => false,
                    'synonyms' => ['category', 'path', 'faculty', 'department', 'stream', 'folder']],
                ['key' => 'numsections', 'label' => 'Number of weeks', 'required' => false, 'type' => 'int',
                    'synonyms' => ['weeks', 'number of weeks', 'sections', 'duration', 'num weeks']],
                ['key' => 'startdate', 'label' => 'Start date', 'required' => false, 'type' => 'date',
                    'synonyms' => ['start', 'start date', 'begins', 'commences']],
                ['key' => 'summary', 'label' => 'Summary', 'required' => false,
                    'synonyms' => ['description', 'about', 'overview', 'notes']],
                ['key' => 'teachers', 'label' => 'Teacher emails', 'required' => false, 'type' => 'emails',
                    'synonyms' => ['teacher', 'teacher email', 'teacher emails', 'instructor', 'lecturer', 'staff'],
                    'sniff' => self::EMAIL_RE],
                ['key' => 'cohorts', 'label' => 'Cohort IDs', 'required' => false,
                    'synonyms' => ['cohort', 'cohort id', 'cohorts', 'class', 'group']],
            ],
            'students' => [
                ['key' => 'username', 'label' => 'Username', 'required' => false,
                    'synonyms' => ['user name', 'login', 'user id', 'userid']],
                ['key' => 'firstname', 'label' => 'First name', 'required' => true,
                    'synonyms' => ['first name', 'given name', 'forename', 'name']],
                ['key' => 'lastname', 'label' => 'Last name', 'required' => true,
                    'synonyms' => ['last name', 'surname', 'family name']],
                ['key' => 'email', 'label' => 'Email', 'required' => true, 'type' => 'email',
                    'synonyms' => ['e-mail', 'email address', 'mail', 'e mail'], 'sniff' => self::EMAIL_RE],
                ['key' => 'cohort', 'label' => 'Cohort ID', 'required' => false,
                    'synonyms' => ['cohort', 'class', 'group', 'cohort id', 'stream']],
            ],
        ];
        return $registry[$type] ?? [];
    }

    /**
     * Required field keys for a type.
     *
     * @param string $type
     * @return string[]
     */
    public static function required_keys(string $type): array {
        $out = [];
        foreach (self::fields($type) as $f) {
            if (!empty($f['required'])) {
                $out[] = $f['key'];
            }
        }
        return $out;
    }

    /**
     * Map of field key => label, with a leading "don't import" option.
     *
     * @param string $type
     * @return array
     */
    public static function options(string $type): array {
        $opts = [self::SKIP => get_string('import_skip', 'local_kreck')];
        foreach (self::fields($type) as $f) {
            $opts[$f['key']] = $f['label'] . (!empty($f['required']) ? ' *' : '');
        }
        return $opts;
    }
}
