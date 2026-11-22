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
 * External function: receive the shared webhook secret from the paired KRECK SMS.
 *
 * The SMS is the source of truth for the HMAC secret. It pushes the secret to the
 * LMS over the already-authenticated web-service token channel so the two sides
 * share one value without an administrator copying it by hand. The secret is
 * stored, never returned.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

/**
 * Store the shared webhook signing secret (and optionally the SMS webhook URL).
 */
class set_webhook_secret extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'secret' => new external_value(PARAM_RAW, 'Shared HMAC secret used to sign LMS->SMS webhook deliveries.'),
            'webhookurl' => new external_value(PARAM_RAW, 'SMS webhook endpoint the LMS should POST signed events to.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Store the secret, and the webhook URL only when not already configured.
     *
     * @param string $secret
     * @param string $webhookurl
     * @return array
     */
    public static function execute(string $secret, string $webhookurl = ''): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['secret' => $secret, 'webhookurl' => $webhookurl]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kreck:manage', $context);

        $secret = trim((string) $params['secret']);
        if (strlen($secret) < 16) {
            throw new \invalid_parameter_exception('The shared webhook secret must be at least 16 characters.');
        }
        set_config('gibbonwebhooksecret', $secret, 'local_kreck');

        // Set the SMS webhook URL only when it is not already configured, so an
        // administrator's explicit value is never overwritten. Accept https (or
        // http for loopback testing only) to keep the delivery surface tight.
        $urlset = false;
        $url = trim((string) $params['webhookurl']);
        if ($url !== '') {
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $isloopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
            if ($host !== '' && ($scheme === 'https' || ($scheme === 'http' && $isloopback))) {
                $current = trim((string) get_config('local_kreck', 'gibbonwebhookurl'));
                if ($current === '') {
                    set_config('gibbonwebhookurl', $url, 'local_kreck');
                    $urlset = true;
                }
            }
        }

        // Now that both sides share the secret, immediately send one heartbeat
        // back to the SMS so its "LMS -> SMS received" status lights up from this
        // single pairing call. Best-effort: never fail pairing if it can't send.
        $heartbeat = false;
        try {
            $hb = \local_kreck\connection::heartbeat_gibbon();
            $heartbeat = !empty($hb['ok']);
        } catch (\Throwable $e) {
            $heartbeat = false;
        }

        return [
            'status' => 'ok',
            'secretlength' => strlen($secret),
            'urlset' => $urlset,
            'heartbeat' => $heartbeat,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Result.'),
            'secretlength' => new external_value(PARAM_INT, 'Length of the stored secret (the value itself is never returned).'),
            'urlset' => new external_value(PARAM_BOOL, 'Whether the SMS webhook URL was set (only when it was previously blank).'),
            'heartbeat' => new external_value(PARAM_BOOL, 'Whether a confirming heartbeat was successfully sent back to the SMS.'),
        ]);
    }
}
