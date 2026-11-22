<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Connection checks for the SMS <-> LMS bridge.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck;

defined('MOODLE_INTERNAL') || die();

/**
 * Send a signed, read-only heartbeat from LMS to the configured SMS site.
 */
class connection {

    /**
     * Return a URL without user info, query parameters, or fragments.
     *
     * @param string $url
     * @return string
     */
    private static function safe_url(string $url): string {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return $url === '' ? 'not configured' : 'invalid URL';
        }
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        return ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . $port . ($parts['path'] ?? '');
    }

    /**
     * Check LMS-to-SMS connectivity and retain only the latest result.
     *
     * @return array
     */
    public static function heartbeat_gibbon(): array {
        global $CFG, $DB;

        $checkedat = time();
        $url = trim((string) get_config('local_kreck', 'gibbonwebhookurl'));
        $secret = (string) get_config('local_kreck', 'gibbonwebhooksecret');
        $secrettrimmed = trim($secret);
        $secretrecord = $DB->get_record('config_plugins', [
            'plugin' => 'local_kreck',
            'name' => 'gibbonwebhooksecret',
        ], 'id, value', IGNORE_MISSING);
        $message = '';
        $ok = false;
        $durationms = 0;
        $httpstatus = 0;
        $curlerrno = 0;
        $responsemessage = '';
        $signature = '';
        $debug = [
            'moodleDatabase' => [
                'name' => (string) ($CFG->dbname ?? 'unknown'),
                'tablePrefix' => (string) ($CFG->prefix ?? 'unknown'),
            ],
            'destination' => self::safe_url($url),
            'localSharedSecretSetting' => [
                'row' => $secretrecord ? 'present' : 'missing',
                'status' => $secrettrimmed === '' ? 'empty' : 'set',
                'length' => strlen($secrettrimmed),
                'value' => 'redacted',
            ],
            'steps' => [[
                'step' => 'Load LMS settings',
                'result' => $url !== '' && $secrettrimmed !== '' ? 'passed' : 'failed',
                'detail' => 'Webhook URL and local secret presence checked; values are not shown.',
            ]],
        ];

        if ($url === '' || $secrettrimmed === '') {
            $message = get_string('connectionnotconfigured', 'local_kreck');
            $debug['steps'][] = ['step' => 'Validate destination and secret', 'result' => 'failed', 'detail' => $message];
        } else {
            $parts = parse_url($url);
            if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
                $message = get_string('connectionhttpsrequired', 'local_kreck');
                $debug['steps'][] = ['step' => 'Validate destination', 'result' => 'failed', 'detail' => $message];
            } else if (!function_exists('random_bytes')) {
                $message = get_string('connectionrandomunavailable', 'local_kreck');
                $debug['steps'][] = ['step' => 'Generate request nonce', 'result' => 'failed', 'detail' => $message];
            } else {
                $timestamp = (string) $checkedat;
                $payload = [
                    'schema_version' => '1.0',
                    'source' => 'moodle',
                    'event_type' => 'heartbeat',
                    'nonce' => bin2hex(random_bytes(16)),
                ];
                $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
                if ($body === false) {
                    $message = get_string('connectionencodefailed', 'local_kreck');
                    $debug['steps'][] = ['step' => 'Encode heartbeat', 'result' => 'failed', 'detail' => $message];
                } else {
                    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
                    $debug['requestId'] = substr($payload['nonce'], 0, 12);
                    $debug['timestampUtc'] = gmdate('Y-m-d H:i:s', $checkedat);
                    $debug['steps'][] = [
                        'step' => 'Create signed heartbeat',
                        'result' => 'passed',
                        'detail' => 'HMAC-SHA256 created; signature redacted; nonce prefix is the request ID.',
                    ];
                    $curl = new \curl();
                    $started = microtime(true);
                    $debug['request'] = [
                        'method' => 'POST',
                        'contentType' => 'application/json',
                        'connectTimeoutSeconds' => 5,
                        'totalTimeoutSeconds' => 15,
                        'tlsPeerVerification' => true,
                        'tlsHostnameVerification' => true,
                    ];
                    $responsebody = $curl->post($url, $body, [
                        'CURLOPT_HTTPHEADER' => [
                            'Content-Type: application/json',
                            'X-Kreck-Timestamp: ' . $timestamp,
                            'X-Kreck-Signature: ' . $signature,
                        ],
                        'CURLOPT_CONNECTTIMEOUT' => 5,
                        'CURLOPT_TIMEOUT' => 15,
                        'CURLOPT_FOLLOWLOCATION' => false,
                        'CURLOPT_SSL_VERIFYPEER' => true,
                        'CURLOPT_SSL_VERIFYHOST' => 2,
                    ]);
                    $durationms = (int) round((microtime(true) - $started) * 1000);
                    $info = $curl->get_info();
                    $httpstatus = (int) ($info['http_code'] ?? 0);
                    $curlerrno = (int) $curl->get_errno();
                    $response = is_string($responsebody) ? json_decode($responsebody, true) : null;
                    $curlmessage = $curlerrno > 0 && function_exists('curl_strerror')
                        ? curl_strerror($curlerrno)
                        : '';
                    $debug['steps'][] = [
                        'step' => 'HTTPS transport',
                        'result' => $curlerrno > 0 ? 'failed' : 'completed',
                        'httpStatus' => $httpstatus,
                        'contentType' => (string) ($info['content_type'] ?? ''),
                        'curlErrno' => $curlerrno,
                        'curlMessage' => $curlmessage,
                        'elapsedMs' => $durationms,
                        'dnsMs' => isset($info['namelookup_time']) ? (int) round($info['namelookup_time'] * 1000) : null,
                        'connectMs' => isset($info['connect_time']) ? (int) round($info['connect_time'] * 1000) : null,
                        'firstByteMs' => isset($info['starttransfer_time']) ? (int) round($info['starttransfer_time'] * 1000) : null,
                    ];
                    $responsemessage = is_array($response)
                        ? (string) ($response['error'] ?? $response['message'] ?? $response['exception'] ?? '')
                        : '';
                    if ($responsemessage === '' && is_string($responsebody)) {
                        $responsemessage = html_entity_decode(
                            strip_tags(substr($responsebody, 0, 1000)),
                            ENT_QUOTES | ENT_HTML5,
                            'UTF-8'
                        );
                    }
                    $responsemessage = clean_param(substr($responsemessage, 0, 300), PARAM_TEXT);
                    if ($secret !== '') {
                        $responsemessage = str_replace($secret, '[REDACTED]', $responsemessage);
                    }
                    if ($signature !== '') {
                        $responsemessage = str_replace($signature, '[REDACTED]', $responsemessage);
                    }
                    $debug['steps'][] = [
                        'step' => 'SMS response',
                        'result' => is_array($response) ? 'JSON received' : 'non-JSON response',
                        'httpStatus' => $httpstatus,
                        'message' => $responsemessage,
                    ];
                    $ok = $httpstatus >= 200 && $httpstatus < 300
                        && is_array($response)
                        && ($response['status'] ?? '') === 'ok'
                        && ($response['service'] ?? '') === 'kreck_bridge';
                    if ($ok) {
                        $message = get_string('connectionok', 'local_kreck');
                    } else if ($curlerrno > 0) {
                        $message = get_string('connectioncurlfailed', 'local_kreck', (object) [
                            'curlerrno' => $curlerrno,
                            'curlmessage' => $curlmessage,
                        ]);
                    } else {
                        if ($httpstatus === 503
                                && strpos($responsemessage, 'Webhook authentication is not configured') !== false) {
                            $message = get_string('connectiongibbonsecretmissing', 'local_kreck');
                        } else if ($httpstatus === 401
                                && strpos($responsemessage, 'Webhook signature is invalid') !== false) {
                            $message = get_string('connectiongibbonsignatureinvalid', 'local_kreck');
                        } else if ($httpstatus === 401
                                && strpos($responsemessage, 'Webhook timestamp is invalid or expired') !== false) {
                            $message = get_string('connectiongibbontimestampinvalid', 'local_kreck');
                        } else if ($httpstatus === 404) {
                            $message = get_string('connectiongibbonroute404', 'local_kreck');
                        } else if ($httpstatus === 400
                                && strpos($responsemessage, 'payload is not valid JSON') !== false) {
                            $message = get_string('connectiongibbonpayloadinvalid', 'local_kreck');
                        } else {
                            $message = get_string('connectionrequestfailed', 'local_kreck', (object) [
                                'httpstatus' => $httpstatus,
                                'curlerrno' => $curlerrno,
                                'curlmessage' => $curlmessage,
                                'responsemessage' => $responsemessage,
                            ]);
                        }
                    }
                    if ($secret !== '') {
                        $message = str_replace($secret, '[REDACTED]', $message);
                    }
                    if ($signature !== '') {
                        $message = str_replace($signature, '[REDACTED]', $message);
                    }
                }
            }
        }

        // These record the LMS->SMS (outbound) check only. Do NOT write
        // 'gibbonheartbeatat' here: that key is the SMS->LMS "last seen" time set
        // by the ping web service, and writing it here conflated the two
        // directions on the LMS status page.
        set_config('gibbonheartbeatattempt', $checkedat, 'local_kreck');
        set_config('gibbonheartbeatstatus', $ok ? 'ok' : 'error', 'local_kreck');
        set_config('gibbonheartbeatmessage', clean_param($message, PARAM_TEXT), 'local_kreck');
        set_config('gibbonheartbeatdebug', json_encode($debug, JSON_INVALID_UTF8_SUBSTITUTE), 'local_kreck');
        if ($ok) {
            set_config('gibbonlastoutboundat', $checkedat, 'local_kreck');
        }

        return [
            'ok' => $ok,
            'message' => $message,
            'checkedat' => $checkedat,
            'durationms' => $durationms,
            'httpstatus' => $httpstatus,
            'curlerrno' => $curlerrno,
            'debug' => $debug,
            'gibbonheartbeatat' => (int) get_config('local_kreck', 'gibbonheartbeatat'),
        ];
    }
}
