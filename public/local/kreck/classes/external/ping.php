<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Authenticated SMS-to-LMS connectivity check.
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
 * Respond to a signed-in KRECK Bridge heartbeat without changing LMS content.
 */
class ping extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Record the last successful heartbeat received from SMS.
     *
     * @return array
     */
    public static function execute(): array {
        global $CFG;

        self::validate_parameters(self::execute_parameters(), []);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kreck:manage', $context);

        $timestamp = time();
        set_config('gibbonheartbeatat', $timestamp, 'local_kreck');

        return [
            'status' => 'ok',
            'timestamp' => $timestamp,
            'component' => 'local_kreck',
            'moodleversion' => (string) $CFG->version,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Heartbeat result.'),
            'timestamp' => new external_value(PARAM_INT, 'LMS server timestamp.'),
            'component' => new external_value(PARAM_TEXT, 'Responding LMS component.'),
            'moodleversion' => new external_value(PARAM_TEXT, 'LMS core version.'),
        ]);
    }
}
