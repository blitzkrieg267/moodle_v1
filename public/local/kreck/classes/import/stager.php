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
 * Parses and stages an uploaded import file, and remembers confirmed mappings.
 *
 * Nothing touches the real database here: the parsed rows are stored in a temp
 * file keyed by a random token and read back when the user confirms.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\import;

use local_kreck\onboarding\csv_importer;
use PhpOffice\PhpSpreadsheet\IOFactory;

defined('MOODLE_INTERNAL') || die();

/**
 * Staging + saved-mapping store for the smart importer.
 */
class stager {

    /** @var string Config plugin. */
    const PLUGIN = 'local_kreck';

    /**
     * Read an uploaded file into a header row and data rows.
     *
     * @param string $path
     * @return array ['headers' => string[], 'rows' => array<array>]
     */
    public static function read_file(string $path): array {
        $magic = (string) file_get_contents($path, false, null, 0, 2);
        if ($magic === 'PK') {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $table = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        } else {
            $text = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));
            $table = [];
            foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $table[] = str_getcsv($line);
            }
        }

        $headers = [];
        $rows = [];
        foreach ($table as $cells) {
            $cells = array_map(static fn($v) => is_string($v) ? trim($v) : (string) ($v ?? ''), (array) $cells);
            if (!$headers) {
                if (array_filter($cells, static fn($v) => $v !== '')) {
                    $headers = $cells;
                }
                continue;
            }
            if (array_filter($cells, static fn($v) => $v !== '')) {
                $rows[] = $cells;
            }
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Per-column sample values for content sniffing.
     *
     * @param array $rows
     * @param int $ncols
     * @param int $limit
     * @return array colindex => value list
     */
    public static function samples(array $rows, int $ncols, int $limit = 25): array {
        $samples = array_fill(0, $ncols, []);
        foreach (array_slice($rows, 0, $limit) as $row) {
            for ($i = 0; $i < $ncols; $i++) {
                $samples[$i][] = $row[$i] ?? '';
            }
        }
        return $samples;
    }

    /**
     * A stable signature for a header layout.
     *
     * @param array $headers
     * @return string
     */
    public static function signature(array $headers): string {
        $norm = array_map([matcher::class, 'normalise'], $headers);
        sort($norm);
        return md5(implode('|', $norm));
    }

    // ---- Temp staging -------------------------------------------------------

    /**
     * Stage parsed data; returns a token.
     *
     * @param array $data
     * @return string
     */
    public static function stage(array $data): string {
        global $CFG;
        $token = random_string(24);
        $dir = "{$CFG->tempdir}/local_kreck_import";
        if (!is_dir($dir)) {
            make_temp_directory('local_kreck_import');
        }
        file_put_contents("{$dir}/{$token}.json", json_encode($data));
        return $token;
    }

    /**
     * Load staged data by token.
     *
     * @param string $token
     * @return array|null
     */
    public static function unstage(string $token): ?array {
        global $CFG;
        $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
        $file = "{$CFG->tempdir}/local_kreck_import/{$token}.json";
        if (!is_readable($file)) {
            return null;
        }
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    // ---- Saved mappings -----------------------------------------------------

    /**
     * Recall a saved mapping for a header layout, as colindex => fieldkey.
     *
     * @param array $headers
     * @return array|null
     */
    public static function recall_mapping(array $headers): ?array {
        $json = get_config(self::PLUGIN, 'importmap_' . self::signature($headers));
        if (!$json) {
            return null;
        }
        $byheader = json_decode($json, true);
        if (!is_array($byheader)) {
            return null;
        }
        $map = [];
        foreach ($headers as $i => $h) {
            $map[$i] = $byheader[matcher::normalise((string) $h)] ?? field_registry::SKIP;
        }
        return $map;
    }

    /**
     * Remember a confirmed mapping, keyed by header layout.
     *
     * @param array $headers
     * @param array $map colindex => fieldkey
     */
    public static function remember_mapping(array $headers, array $map): void {
        $byheader = [];
        foreach ($headers as $i => $h) {
            $byheader[matcher::normalise((string) $h)] = $map[$i] ?? field_registry::SKIP;
        }
        set_config('importmap_' . self::signature($headers), json_encode($byheader), self::PLUGIN);
    }

    // ---- Build the manifest fragment ----------------------------------------

    /**
     * Turn staged rows + a confirmed mapping into a manifest fragment.
     *
     * @param string $type courses|students
     * @param array $rows
     * @param array $map colindex => fieldkey
     * @return array
     */
    public static function build_fragment(string $type, array $rows, array $map): array {
        $assoc = [];
        foreach ($rows as $row) {
            $r = [];
            foreach ($map as $i => $key) {
                if ($key !== field_registry::SKIP && ($row[$i] ?? '') !== '') {
                    $r[$key] = $row[$i];
                }
            }
            if ($r) {
                $assoc[] = $r;
            }
        }

        switch ($type) {
            case 'students':
                return ['students' => csv_importer::students_from_rows($assoc)];
            case 'staff':
                return ['users' => csv_importer::staff_from_rows($assoc)];
            case 'enrolments':
                return ['enrolments' => csv_importer::enrol_from_rows($assoc)];
            default:
                return ['courses' => csv_importer::courses_from_rows($assoc)];
        }
    }
}
