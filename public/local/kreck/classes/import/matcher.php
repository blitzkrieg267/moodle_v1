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
 * Auto-guesses a column -> field mapping (Mailchimp-style).
 *
 * Two heuristics: (1) header matching against each field's name and synonyms,
 * exact first then fuzzy (Levenshtein) for typos; (2) content sniffing, where a
 * column whose values mostly match a field's shape (e.g. email) is proposed
 * even if its header gave nothing.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\import;

defined('MOODLE_INTERNAL') || die();

/**
 * Column-to-field guesser.
 */
class matcher {

    /**
     * Normalise a header for comparison: lowercase, drop punctuation, collapse.
     *
     * @param string $s
     * @return string
     */
    public static function normalise(string $s): string {
        $s = strtolower(trim($s));
        $s = preg_replace('/\(.*?\)/', '', $s);        // Drop parenthetical hints.
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);    // Punctuation -> space.
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Guess the mapping for a set of columns.
     *
     * @param array $headers Column header strings (index-aligned).
     * @param array $samples Per-column sample value lists (index-aligned).
     * @param array $fields Field registry entries for the type.
     * @return array colindex => fieldkey ('' = unmapped)
     */
    public static function guess(array $headers, array $samples, array $fields): array {
        $map = [];
        $used = [];

        // Pass 1: header match (exact synonym, then fuzzy).
        $candidates = [];
        foreach ($headers as $i => $header) {
            $norm = self::normalise((string) $header);
            if ($norm === '') {
                continue;
            }
            foreach ($fields as $f) {
                $score = self::header_score($norm, $f);
                if ($score > 0) {
                    $candidates[] = ['col' => $i, 'key' => $f['key'], 'score' => $score];
                }
            }
        }
        // Highest-confidence pairings first; each column and field used once.
        usort($candidates, static fn($a, $b) => $b['score'] <=> $a['score']);
        foreach ($candidates as $c) {
            if (isset($map[$c['col']]) || isset($used[$c['key']])) {
                continue;
            }
            $map[$c['col']] = $c['key'];
            $used[$c['key']] = true;
        }

        // Pass 2: content sniffing for still-unmapped columns.
        foreach ($headers as $i => $header) {
            if (isset($map[$i])) {
                continue;
            }
            foreach ($fields as $f) {
                if (empty($f['sniff']) || isset($used[$f['key']])) {
                    continue;
                }
                if (self::sniff_ratio($samples[$i] ?? [], $f['sniff']) >= 0.8) {
                    $map[$i] = $f['key'];
                    $used[$f['key']] = true;
                    break;
                }
            }
        }

        // Fill the rest as "don't import".
        foreach ($headers as $i => $header) {
            if (!isset($map[$i])) {
                $map[$i] = field_registry::SKIP;
            }
        }
        ksort($map);
        return $map;
    }

    /**
     * Confidence that a normalised header names a field.
     *
     * @param string $norm
     * @param array $field
     * @return int 0 none, 100 exact, 70 fuzzy
     */
    protected static function header_score(string $norm, array $field): int {
        $targets = array_map([self::class, 'normalise'],
            array_merge([$field['key'], $field['label']], $field['synonyms'] ?? []));
        $targets = array_unique(array_filter($targets));

        foreach ($targets as $t) {
            if ($norm === $t) {
                return 100;
            }
        }
        // Fuzzy: short edit distance relative to length catches typos/spacing.
        $best = PHP_INT_MAX;
        foreach ($targets as $t) {
            $d = levenshtein($norm, $t);
            $best = min($best, $d);
        }
        $len = max(strlen($norm), 3);
        if ($best <= 2 && $best / $len <= 0.34) {
            return 70;
        }
        return 0;
    }

    /**
     * Fraction of non-empty sample values matching a regex.
     *
     * @param array $values
     * @param string $regex
     * @return float
     */
    protected static function sniff_ratio(array $values, string $regex): float {
        $values = array_values(array_filter(array_map('trim', $values), static fn($v) => $v !== ''));
        if (empty($values)) {
            return 0.0;
        }
        $hits = 0;
        foreach ($values as $v) {
            if (preg_match($regex, $v)) {
                $hits++;
            }
        }
        return $hits / count($values);
    }
}
