<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * KRECK SMS <-> LMS connection health page.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
\local_kreck\diagnostics::register(__FILE__);
require_once("{$CFG->libdir}/adminlib.php");

admin_externalpage_setup('local_kreck_connection');
require_capability('local/kreck:manage', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'heartbeat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        $result = \local_kreck\connection::heartbeat_gibbon();
    } catch (\Throwable $exception) {
        $exceptionmessage = $exception->getMessage();
        $sharedsecret = (string) get_config('local_kreck', 'gibbonwebhooksecret');
        if ($sharedsecret !== '') {
            $exceptionmessage = str_replace($sharedsecret, '[REDACTED]', $exceptionmessage);
        }
        $exceptionmessage = clean_param(substr($exceptionmessage, 0, 300), PARAM_TEXT);
        debugging('KRECK LMS-to-SMS connection check failed: ' . $exceptionmessage, DEBUG_DEVELOPER);
        error_log('KRECK LMS-to-SMS connection check failed: ' . $exceptionmessage);
        $result = [
            'ok' => false,
            'message' => get_string('connectioninternalerror', 'local_kreck'),
            'checkedat' => time(),
            'durationms' => 0,
            'debug' => [
                'stage' => 'LMS endpoint',
                'exceptionClass' => get_class($exception),
                'exceptionMessage' => $exceptionmessage,
            ],
            'gibbonheartbeatat' => (int) get_config('local_kreck', 'gibbonheartbeatat'),
        ];
    }
    echo json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$PAGE->set_url(new moodle_url('/local/kreck/connection.php'));
$PAGE->set_title(get_string('connectiontitle', 'local_kreck'));
$PAGE->set_heading(get_string('connectiontitle', 'local_kreck'));

$gibbonheartbeatat = (int) get_config('local_kreck', 'gibbonheartbeatat');
$gibbonheartbeatattempt = (int) get_config('local_kreck', 'gibbonheartbeatattempt');
$gibbonheartbeatstatus = (string) get_config('local_kreck', 'gibbonheartbeatstatus');
$gibbonheartbeatmessage = (string) get_config('local_kreck', 'gibbonheartbeatmessage');
$storedDebug = json_decode((string) get_config('local_kreck', 'gibbonheartbeatdebug'), true);
$webhookurl = trim((string) get_config('local_kreck', 'gibbonwebhookurl'));
$webhooksecret = trim((string) get_config('local_kreck', 'gibbonwebhooksecret'));
$webhooksecretrow = $DB->get_record('config_plugins', [
    'plugin' => 'local_kreck',
    'name' => 'gibbonwebhooksecret',
], 'id, value', IGNORE_MISSING);
$webhookparts = parse_url($webhookurl);
$safeWebhookUrl = $webhookparts && !empty($webhookparts['host'])
    ? ($webhookparts['scheme'] ?? 'https') . '://' . $webhookparts['host']
        . (isset($webhookparts['port']) ? ':' . (int) $webhookparts['port'] : '')
        . ($webhookparts['path'] ?? '')
    : ($webhookurl === '' ? 'not configured' : 'invalid URL');
$localDebug = [
    'moodleDatabase' => [
        'name' => (string) ($CFG->dbname ?? 'unknown'),
        'tablePrefix' => (string) ($CFG->prefix ?? 'unknown'),
    ],
    'moodleToGibbonSettings' => [
        'destination' => $safeWebhookUrl,
        'secretSettingRow' => $webhooksecretrow ? 'present' : 'missing',
        'sharedSecret' => $webhooksecret === '' ? 'empty' : 'set; value redacted',
        'secretLength' => strlen($webhooksecret),
    ],
    'lastRequest' => is_array($storedDebug) ? $storedDebug : 'No request has completed yet.',
];
$sesskey = sesskey();
$endpoint = (new moodle_url('/local/kreck/connection.php'))->out(false);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('connectiontitle', 'local_kreck'));
echo html_writer::tag('p', get_string('connectionintro', 'local_kreck'), ['class' => 'text-muted']);

$gibbontime = $gibbonheartbeatat > 0
    ? userdate($gibbonheartbeatat)
    : get_string('connectionnever', 'local_kreck');
echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');
echo html_writer::tag('h3', get_string('connectiongibbontomoodle', 'local_kreck'), ['class' => 'h5 card-title']);
echo html_writer::tag('p', get_string('connectiongibbonlastseen', 'local_kreck', $gibbontime), [
    'id' => 'kreck-gibbon-to-moodle',
    'class' => 'mb-0',
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');
echo html_writer::tag('h3', get_string('connectionmoodletogibbon', 'local_kreck'), ['class' => 'h5 card-title']);
echo html_writer::tag('p', get_string('connectionstatuschecking', 'local_kreck'), [
    'id' => 'kreck-moodle-to-gibbon',
    'class' => 'mb-2',
    'role' => 'status',
    'aria-live' => 'polite',
]);
echo html_writer::tag('noscript', get_string('connectionjavascriptrequired', 'local_kreck'));
echo html_writer::tag('details',
    html_writer::tag('summary', get_string('connectiondiagnosticssummary', 'local_kreck'))
        . html_writer::tag('pre', s(json_encode($localDebug, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)), [
            'id' => 'kreck-heartbeat-debug',
            'style' => 'white-space:pre-wrap;overflow-wrap:anywhere',
        ]),
    ['class' => 'mb-3']
);
echo html_writer::tag('progress', '', [
    'id' => 'kreck-heartbeat-progress',
    'class' => 'w-100 mb-3',
    'hidden' => 'hidden',
]);
echo html_writer::tag('button', get_string('connectionchecknow', 'local_kreck'), [
    'id' => 'kreck-heartbeat-button',
    'type' => 'button',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_div();
echo html_writer::end_div();

if ($gibbonheartbeatattempt > 0) {
    $attemptstatus = $gibbonheartbeatstatus === 'ok'
        ? get_string('connectionok', 'local_kreck')
        : $gibbonheartbeatmessage;
    echo html_writer::tag('p', get_string('connectionlastattempt', 'local_kreck', userdate($gibbonheartbeatattempt))
        . ' — ' . $attemptstatus, ['class' => 'text-muted small']);
}

$config = [
    'endpoint' => $endpoint,
    'sesskey' => $sesskey,
    'incomingLabel' => get_string('connectiongibbonlastseen', 'local_kreck', ''),
    'localDebug' => $localDebug,
    'initialAttempt' => $gibbonheartbeatattempt,
    'initialStatus' => $gibbonheartbeatstatus,
    'initialMessage' => $gibbonheartbeatmessage,
    'labels' => [
        'waiting' => get_string('connectionwaiting', 'local_kreck'),
        'connecting' => get_string('connectionconnecting', 'local_kreck'),
        'timeout' => get_string('connectiontimeout', 'local_kreck'),
        'unexpected' => get_string('connectionunexpectedresponse', 'local_kreck'),
        'networkerror' => get_string('connectionnetworkerror', 'local_kreck'),
        'browserunsupported' => get_string('connectionbrowserunsupported', 'local_kreck'),
        'elapsed' => get_string('connectionelapsed', 'local_kreck'),
        'diagnosticserror' => get_string('connectiondiagnosticserror', 'local_kreck'),
    ],
];
$connectionjs = 'window.KRECK_CONNECTION = '
    . json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)
    . ";\n" . <<<'JS'
(function() {
    const config = window.KRECK_CONNECTION;
    const statusNode = document.getElementById('kreck-moodle-to-gibbon');
    const incomingNode = document.getElementById('kreck-gibbon-to-moodle');
    const debugNode = document.getElementById('kreck-heartbeat-debug');
    const progress = document.getElementById('kreck-heartbeat-progress');
    const button = document.getElementById('kreck-heartbeat-button');
    if (!config || !statusNode || !progress || !button) {
        return;
    }
    let checking = false;
    const display = (ok, message, checkedAt, durationMs) => {
        const checked = checkedAt ? new Date(checkedAt * 1000).toLocaleString() : '';
        statusNode.textContent = (ok ? 'Connected: ' : 'Not connected: ') + message
            + (checked ? ' Last checked: ' + checked : '')
            + (durationMs ? ' (' + durationMs + ' ms)' : '');
        statusNode.className = ok ? 'text-success mb-2' : 'text-danger mb-2';
    };
    const renderDebug = (requestDebug) => {
        if (debugNode) {
            debugNode.textContent = JSON.stringify({
                localMoodle: config.localDebug,
                latestRequest: requestDebug || config.labels.diagnosticserror
            }, null, 2);
        }
    };
    const check = async () => {
        if (checking) {
            return;
        }
        checking = true;
        button.disabled = true;
        progress.hidden = false;
        statusNode.textContent = config.labels.connecting;
        statusNode.className = 'text-muted mb-2';
        const startedAt = Date.now();
        let controller;
        let timeout;
        let elapsed;
        let responseDebug = null;
        try {
            if (typeof AbortController !== 'function') {
                throw new Error(config.labels.browserunsupported);
            }
            controller = new AbortController();
            timeout = window.setTimeout(() => controller.abort(), 25000);
            elapsed = window.setInterval(() => {
                const seconds = Math.floor((Date.now() - startedAt) / 1000);
                statusNode.textContent = (seconds >= 8 ? config.labels.waiting : config.labels.connecting)
                    + ' ' + config.labels.elapsed.replace('{$a}', String(seconds));
            }, 1000);
            const body = new URLSearchParams({action: 'heartbeat', sesskey: config.sesskey});
            const response = await fetch(config.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString(),
                signal: controller.signal
            });
            const responseText = await response.text();
            responseDebug = {
                stage: 'Browser request to LMS',
                httpStatus: response.status,
                contentType: response.headers.get('Content-Type'),
                elapsedMs: Date.now() - startedAt
            };
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (error) {
                const excerpt = responseText.replace(/\s+/g, ' ').slice(0, 180);
                responseDebug.responseFormat = 'non-JSON';
                responseDebug.responseExcerpt = excerpt;
                renderDebug(responseDebug);
                throw new Error(config.labels.unexpected + ' HTTP ' + response.status
                    + (excerpt ? ': ' + excerpt : ''));
            }
            responseDebug.responseFormat = 'JSON';
            renderDebug(result.debug || responseDebug);
            if (!response.ok || typeof result.ok !== 'boolean') {
                throw new Error((result.message || config.labels.unexpected) + ' HTTP ' + response.status);
            }
            display(result.ok, result.message || '', result.checkedat || 0, result.durationms || 0);
            renderDebug(result.debug || responseDebug);
            if (incomingNode && result.gibbonheartbeatat) {
                incomingNode.textContent = config.incomingLabel.replace(/\s*$/, '')
                    + ' ' + new Date(result.gibbonheartbeatat * 1000).toLocaleString();
            }
        } catch (error) {
            const message = error.name === 'AbortError'
                ? config.labels.timeout
                : (error.message || config.labels.networkerror);
            display(false, message, Math.floor(Date.now() / 1000), Date.now() - startedAt);
            if (!responseDebug || !debugNode || !debugNode.textContent) {
                renderDebug({
                    stage: 'Browser request to LMS',
                    errorName: error.name,
                    error: message,
                    elapsedMs: Date.now() - startedAt
                });
            }
        } finally {
            if (timeout !== undefined) {
                window.clearTimeout(timeout);
            }
            if (elapsed !== undefined) {
                window.clearInterval(elapsed);
            }
            checking = false;
            button.disabled = false;
            progress.hidden = true;
        }
    };
    button.addEventListener('click', check);
    check();
    window.setInterval(check, 60000);
})();
JS;
$PAGE->requires->js_init_code($connectionjs);

echo $OUTPUT->footer();
