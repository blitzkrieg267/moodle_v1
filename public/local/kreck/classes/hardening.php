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
 * KRECK host security hardening.
 *
 * "Secure the app by virtue of installing KRECK": installing or upgrading the
 * plugin tightens the host LMS's own security posture (OWASP A05 Security
 * Misconfiguration, A07 Identification & Authentication Failures). Every change
 * only ever STRENGTHENS a setting — a stricter existing value is never relaxed —
 * and the prior values are recorded once to local_kreck/hardening_prior so an
 * administrator can review or revert. Idempotent: safe to run on every upgrade.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck;

defined('MOODLE_INTERNAL') || die();

/**
 * Only-tightening, reversible, idempotent host hardening.
 */
class hardening {

    /**
     * Apply the hardening baseline to the host LMS.
     *
     * @return array Human-readable report lines describing each change made.
     */
    public static function apply(): array {
        global $CFG;

        $report = [];
        $prior = [];

        // --- Session cookie hardening (mitigates cookie theft / session hijack) ---
        // Block JavaScript access to the session cookie (defence-in-depth for XSS).
        self::tighten_bool('cookiehttponly', 1, $report, $prior);
        // Mark cookies HTTPS-only, but ONLY when the site is served over https —
        // forcing it on an http site would lock everyone out.
        if (!empty($CFG->wwwroot) && strpos($CFG->wwwroot, 'https://') === 0) {
            self::tighten_bool('cookiesecure', 1, $report, $prior);
        } else {
            $report[] = 'cookiesecure: skipped (site wwwroot is not https://)';
        }

        // --- Account enumeration & brute force (A07) ---
        // Do not reveal whether a username/email exists on login or password reset.
        self::tighten_bool('protectusernames', 1, $report, $prior);
        // Enable account lockout after repeated failed logins (0 = disabled).
        self::raise_int('lockoutthreshold', 10, $report, $prior);
        self::raise_int('lockoutwindow', 1800, $report, $prior);     // 30-minute window.
        self::raise_int('lockoutduration', 1800, $report, $prior);   // 30-minute lockout.

        // --- Password policy: match the KRECK SMS baseline (A07) ---
        self::tighten_bool('passwordpolicy', 1, $report, $prior);
        self::raise_int('minpasswordlength', 8, $report, $prior);
        self::raise_int('minpassworddigits', 1, $report, $prior);
        self::raise_int('minpasswordlower', 1, $report, $prior);
        self::raise_int('minpasswordupper', 1, $report, $prior);
        self::raise_int('minpasswordnonalphanum', 1, $report, $prior);

        // --- Web-service transport: drop deprecated protocols, keep what remains ---
        // KRECK syncs over REST. Removing the long-deprecated AMF / XML-RPC
        // transports shrinks the web-service attack surface without touching REST
        // or SOAP that an admin may rely on.
        self::prune_protocols(['amf', 'xmlrpc'], $report, $prior);

        // Record the prior values ONCE, the first time hardening runs, so an admin
        // can see exactly what KRECK changed and restore it if they wish.
        if (get_config('local_kreck', 'hardening_prior') === false) {
            set_config('hardening_prior', json_encode($prior), 'local_kreck');
        }
        set_config('hardening_appliedat', time(), 'local_kreck');
        set_config('hardening_report', json_encode($report), 'local_kreck');

        if (function_exists('mtrace')) {
            mtrace('  [KRECK] host security hardening applied:');
            foreach ($report as $line) {
                mtrace('    - ' . $line);
            }
        }

        return $report;
    }

    /**
     * Set a core boolean/flag setting to $want only if it is not already that.
     *
     * @param string $name Core config name.
     * @param int $want Desired (hardened) value.
     * @param array $report
     * @param array $prior
     */
    private static function tighten_bool(string $name, int $want, array &$report, array &$prior): void {
        $cur = get_config('moodle', $name);
        if ((string) $cur !== (string) $want) {
            $prior[$name] = ($cur === false) ? null : $cur;
            set_config($name, $want);
            $report[] = $name . ': ' . self::fmt($cur) . ' -> ' . $want;
        }
    }

    /**
     * Raise a core integer setting to at least $min (never lowers it).
     *
     * @param string $name Core config name.
     * @param int $min Minimum (hardened) value.
     * @param array $report
     * @param array $prior
     */
    private static function raise_int(string $name, int $min, array &$report, array &$prior): void {
        $raw = get_config('moodle', $name);
        $cur = (int) $raw;
        if ($cur < $min) {
            $prior[$name] = ($raw === false) ? null : $raw;
            set_config($name, $min);
            $report[] = $name . ': ' . self::fmt($raw) . ' -> ' . $min;
        }
    }

    /**
     * Remove deprecated web-service protocols, keeping everything else enabled.
     *
     * @param array $deprecated Protocol short names to drop if present.
     * @param array $report
     * @param array $prior
     */
    private static function prune_protocols(array $deprecated, array &$report, array &$prior): void {
        $cur = (string) get_config('moodle', 'webserviceprotocols');
        if ($cur === '') {
            return; // No protocols enabled; nothing to prune.
        }
        $active = array_values(array_filter(array_map('trim', explode(',', $cur))));
        $kept = array_values(array_diff($active, $deprecated));
        if (count($kept) !== count($active)) {
            $prior['webserviceprotocols'] = $cur;
            set_config('webserviceprotocols', implode(',', $kept));
            $report[] = 'webserviceprotocols: ' . $cur . ' -> ' . implode(',', $kept);
        }
    }

    /**
     * Format a prior value for the report.
     *
     * @param mixed $v
     * @return string
     */
    private static function fmt($v): string {
        if ($v === false) {
            return '(unset)';
        }
        if ($v === '') {
            return '(empty)';
        }
        return (string) $v;
    }
}
