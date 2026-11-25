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
 * Option lists for the plugin's searchable (autocomplete) form fields.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the choice lists used by autocomplete fields, so every form offers the same options.
 */
class pick {

    /** The product-wide default currency: Botswana Pula. */
    const DEFAULT_CURRENCY = 'BWP';

    /**
     * ISO currencies as code => "CODE — Name", with BWP always first.
     *
     * @return string[]
     */
    public static function currencies(): array {
        $out = [self::DEFAULT_CURRENCY => self::DEFAULT_CURRENCY . ' — Botswana Pula'];
        foreach (get_string_manager()->get_list_of_currencies() as $code => $name) {
            if ($code !== self::DEFAULT_CURRENCY) {
                $out[$code] = $code . ' — ' . $name;
            }
        }
        return $out;
    }

    /**
     * Normalise a currency code: blank means BWP; a known code stays; anything else is null (invalid).
     *
     * @param string|null $code
     * @return string|null
     */
    public static function normalise_currency(?string $code): ?string {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return self::DEFAULT_CURRENCY;
        }
        return array_key_exists($code, self::currencies()) ? $code : null;
    }

    /**
     * Active (not deleted, not suspended, not guest) users as username => "Full name (username)".
     *
     * @param int $limit safety cap on the list size
     * @return string[]
     */
    public static function users(int $limit = 5000): array {
        global $DB;
        $names = \core_user\fields::get_name_fields();
        $fields = 'id, username, ' . implode(', ', $names);
        $rows = $DB->get_records_select('user', 'deleted = 0 AND suspended = 0 AND username <> :guest',
            ['guest' => 'guest'], 'lastname, firstname', $fields, 0, $limit);
        $out = [];
        foreach ($rows as $u) {
            $out[$u->username] = fullname($u) . ' (' . $u->username . ')';
        }
        return $out;
    }

    /**
     * Distinct non-empty values of a plugin table column as value => value, for tag-style autocompletes.
     *
     * @param string $table table without prefix
     * @param string $column column name
     * @return string[]
     */
    public static function distinct(string $table, string $column): array {
        global $DB;
        $values = $DB->get_fieldset_sql("SELECT DISTINCT {$column} FROM {{$table}} WHERE {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column}");
        return array_combine($values, $values) ?: [];
    }

    /**
     * Known onboarding batches (newest first) as batchid => "batchid — date, N courses, N users".
     *
     * @return string[]
     */
    public static function batches(): array {
        $engine = new \local_kreck\onboarding\engine();
        $out = [];
        foreach ($engine->list_batches() as $b) {
            $when = $b['timecreated'] ? userdate($b['timecreated'], get_string('strftimedatetimeshort')) : '';
            $out[$b['batchid']] = $b['batchid'] . ' — ' . trim($when . ', ' . $b['courses'] . ' courses, ' . $b['users'] . ' users', ', ');
        }
        return $out;
    }
}
