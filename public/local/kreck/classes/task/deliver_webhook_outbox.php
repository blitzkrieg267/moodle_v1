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
 * Deliver pending LMS change notifications to SMS.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled delivery task for the durable LMS-to-SMS outbox.
 */
class deliver_webhook_outbox extends \core\task\scheduled_task {

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskdeliverwebhookoutbox', 'local_kreck');
    }

    /**
     * Deliver due events, retaining failed deliveries for exponential retry.
     */
    public function execute(): void {
        global $DB;

        $url = trim((string) get_config('local_kreck', 'gibbonwebhookurl'));
        $secret = (string) get_config('local_kreck', 'gibbonwebhooksecret');
        if ($url === '' || $secret === '') {
            return;
        }

        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            mtrace('KRECK webhook delivery skipped: receiver URL must use HTTPS.');
            return;
        }

        $events = $DB->get_records_select(
            'local_kreck_sync_event',
            'status = 0 AND nextrun <= :now',
            ['now' => time()],
            'id ASC',
            '*',
            0,
            25
        );
        foreach ($events as $event) {
            $timestamp = (string) time();
            $body = $event->payload;
            $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
            $curl = new \curl();
            $response = $curl->post($url, $body, [
                'CURLOPT_HTTPHEADER' => [
                    'Content-Type: application/json',
                    'X-Kreck-Timestamp: ' . $timestamp,
                    'X-Kreck-Signature: ' . $signature,
                    'X-Kreck-Event-ID: ' . $event->eventid,
                ],
                'CURLOPT_CONNECTTIMEOUT' => 5,
                'CURLOPT_TIMEOUT' => 15,
            ]);
            $info = $curl->get_info();
            $httpstatus = (int) ($info['http_code'] ?? 0);
            $attempts = (int) $event->attempts + 1;
            $now = time();

            if ($httpstatus >= 200 && $httpstatus < 300) {
                $event->status = 1;
                $event->lastresponse = clean_param((string) $response, PARAM_TEXT);
            } else {
                $event->status = 0;
                $event->lastresponse = clean_param(
                    'HTTP ' . $httpstatus . '; curl error ' . $curl->get_errno() . '; ' . (string) $response,
                    PARAM_TEXT
                );
                $event->nextrun = $now + min(3600, 30 * (2 ** min($attempts - 1, 7)));
            }
            $event->attempts = $attempts;
            $event->timemodified = $now;
            $DB->update_record('local_kreck_sync_event', $event);
        }
    }
}
