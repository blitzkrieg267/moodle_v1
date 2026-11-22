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
 * External function: receive and provision a SMS institution manifest.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_kreck\onboarding\engine;
use local_kreck\onboarding\manifest;

defined('MOODLE_INTERNAL') || die();

/**
 * Receive versioned SMS snapshots over LMS's authenticated web service.
 */
class sync_manifest extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'manifestjson' => new external_value(PARAM_RAW, 'JSON institution manifest, schema version 1.0.'),
            'requestid' => new external_value(PARAM_ALPHANUMEXT, 'Stable 64-character idempotency key.'),
            'dryrun' => new external_value(PARAM_BOOL, 'Validate and return the impact plan without writing.', VALUE_DEFAULT, false),
            'existingcoursepolicy' => new external_value(PARAM_ALPHA, 'block existing courses or merge into matching courses.', VALUE_DEFAULT, 'block'),
        ]);
    }

    /**
     * Preflight the contract or provision the manifest once.
     *
     * @param string $manifestjson
     * @param string $requestid
     * @param bool $dryrun
     * @param string $existingcoursepolicy
     * @return array
     */
    public static function execute(
        string $manifestjson,
        string $requestid,
        bool $dryrun = false,
        string $existingcoursepolicy = 'block'
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'manifestjson' => $manifestjson,
            'requestid' => $requestid,
            'dryrun' => $dryrun,
            'existingcoursepolicy' => $existingcoursepolicy,
        ]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kreck:manage', $context);

        if (!preg_match('/^[a-f0-9]{64}$/', $params['requestid'])) {
            throw new \invalid_parameter_exception('The request id must be a lowercase SHA-256 hex digest.');
        }

        $data = json_decode($params['manifestjson'], true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \invalid_parameter_exception('The manifest is not valid JSON.');
        }
        self::validate_manifest_contract($data);
        $existingcoursepolicy = strtolower((string) $params['existingcoursepolicy']);
        if (!in_array($existingcoursepolicy, ['block', 'merge'], true)) {
            throw new \invalid_parameter_exception('Existing course policy must be block or merge.');
        }
        $digest = self::manifest_digest($data);
        if ($existingcoursepolicy === 'merge') {
            $digest = hash('sha256', $digest . '|existingcoursepolicy=merge');
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_kreck_sync_manifest');
        $lock = $factory->get_lock('gibbon-manifest', 10);
        if (!$lock) {
            throw new \moodle_exception('syncbusy', 'local_kreck');
        }

        try {
            $receipt = $DB->get_record('local_kreck_sync_receipt', ['requestid' => $params['requestid']]);
            if ($receipt) {
                if (!hash_equals($receipt->digest, $digest)) {
                    throw new \invalid_parameter_exception('The request id was already used for a different manifest.');
                }
                $response = json_decode($receipt->response, true);
                if (!is_array($response)) {
                    throw new \moodle_exception('syncreceiptinvalid', 'local_kreck');
                }
                $response['status'] = 'replayed';
                return $response;
            }

            $manifest = new manifest($data);
            $mergeexistingcourses = $existingcoursepolicy === 'merge';
            $validation = (new engine())->validate($manifest, $mergeexistingcourses);
            if (!$validation['ok']) {
                return [
                    'status' => 'conflict',
                    'batchid' => '',
                    'digest' => $digest,
                    'message' => get_string('syncmanifestconflict', 'local_kreck'),
                    'errors' => $validation['errors'],
                    'plan' => self::encode_plan($validation['plan']),
                    'warnings' => $validation['warnings'],
                ];
            }

            if ($params['dryrun']) {
                return [
                    'status' => 'validated',
                    'batchid' => '',
                    'digest' => $digest,
                    'message' => get_string('syncmanifestvalidated', 'local_kreck'),
                    'errors' => [],
                    'plan' => self::encode_plan($validation['plan']),
                    'warnings' => $validation['warnings'],
                ];
            }

            $result = (new engine())->provision($manifest, $mergeexistingcourses);
            if (!$result['ok']) {
                return [
                    'status' => 'conflict',
                    'batchid' => '',
                    'digest' => $digest,
                    'message' => get_string('syncmanifestconflict', 'local_kreck'),
                    'errors' => [(string) $result['error']],
                    'plan' => self::encode_plan($validation['plan']),
                    'warnings' => $validation['warnings'],
                ];
            }

            $response = [
                'status' => 'imported',
                'batchid' => (string) $result['batchid'],
                'digest' => $digest,
                'message' => get_string('syncmanifestdone', 'local_kreck', $result['batchid']),
                'errors' => [],
                'plan' => self::encode_plan($validation['plan']),
                'warnings' => $validation['warnings'],
            ];
            $receiptresponse = $response;
            $receiptresponse['warnings'] = empty($validation['warnings'])
                ? []
                : [get_string('syncmanifestwarningcount', 'local_kreck', count($validation['warnings']))];
            $receiptjson = json_encode($receiptresponse, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($receiptjson === false) {
                throw new \coding_exception('Could not encode manifest sync receipt: ' . json_last_error_msg());
            }

            $DB->insert_record('local_kreck_sync_receipt', (object) [
                'requestid' => $params['requestid'],
                'digest' => $digest,
                'response' => $receiptjson,
                'timecreated' => time(),
            ]);
            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * Encode the validation plan for the external API response.
     *
     * @param array $plan
     * @return string
     */
    private static function encode_plan(array $plan): string {
        $summary = [];
        foreach ($plan as $key => $value) {
            $summary[$key] = is_array($value) ? count($value) : (int) $value;
        }
        $json = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \coding_exception('Could not encode manifest impact plan: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * Enforce the structural and stable-ID requirements of contract v1.
     *
     * @param array $data
     */
    private static function validate_manifest_contract(array $data): void {
        if (($data['schema_version'] ?? '') !== '1.0'
                || !isset($data['batch_metadata'])
                || !is_array($data['batch_metadata'])
                || ($data['batch_metadata']['source'] ?? '') !== 'Kreck Bridge'
                || !is_string($data['batch_metadata']['schoolyear'] ?? null)
                || trim($data['batch_metadata']['schoolyear']) === ''
                || !is_string($data['batch_metadata']['generated'] ?? null)
                || strtotime($data['batch_metadata']['generated']) === false) {
            throw new \invalid_parameter_exception('Unsupported or incomplete SMS manifest contract.');
        }

        if (isset($data['batch_metadata']['version']) && $data['batch_metadata']['version'] !== '1.0') {
            throw new \invalid_parameter_exception('Unsupported SMS batch metadata version.');
        }
        if (isset($data['batch_metadata']['manifest_id'])
                && (!is_string($data['batch_metadata']['manifest_id'])
                    || trim($data['batch_metadata']['manifest_id']) === '')) {
            throw new \invalid_parameter_exception('Manifest id must be a non-empty string when present.');
        }
        if (!isset($data['links']) || !is_array($data['links'])) {
            throw new \invalid_parameter_exception('Manifest links must be an object.');
        }
        if (isset($data['defaultpassword']) && !is_string($data['defaultpassword'])) {
            throw new \invalid_parameter_exception('Manifest defaultpassword must be a string.');
        }
        if (isset($data['forcepasswordchange']) && !is_bool($data['forcepasswordchange'])) {
            throw new \invalid_parameter_exception('Manifest forcepasswordchange must be a boolean.');
        }

        $requiredfields = [
            'categories' => ['name', 'category_path', 'source_id'],
            'users' => ['source_id', 'username', 'firstname', 'lastname', 'email'],
            'cohorts' => ['name', 'idnumber', 'source_id'],
            'students' => ['source_id', 'username', 'firstname', 'lastname', 'email', 'studentid'],
            'courses' => ['fullname', 'shortname', 'category_path', 'source_id'],
            'enrolments' => ['user', 'course', 'role', 'source_id'],
        ];
        foreach ($requiredfields as $collection => $fields) {
            if (!isset($data[$collection]) || !is_array($data[$collection]) || !array_is_list($data[$collection])) {
                throw new \invalid_parameter_exception("Manifest collection '{$collection}' must be an array.");
            }
            foreach ($data[$collection] as $entry) {
                if (!is_array($entry)) {
                    throw new \invalid_parameter_exception("Each '{$collection}' entry must be an object.");
                }
                foreach ($fields as $field) {
                    if (!is_string($entry[$field] ?? null) || trim($entry[$field]) === '') {
                        throw new \invalid_parameter_exception(
                            "Each '{$collection}' entry must include a non-empty '{$field}' string."
                        );
                    }
                }
            }
        }

        // Parents are an OPTIONAL collection (older SMS exports omit it, and
        // must still import). Validate only when present.
        if (isset($data['parents'])) {
            if (!is_array($data['parents']) || !array_is_list($data['parents'])) {
                throw new \invalid_parameter_exception("Manifest collection 'parents' must be an array.");
            }
            foreach ($data['parents'] as $entry) {
                if (!is_array($entry)) {
                    throw new \invalid_parameter_exception("Each 'parents' entry must be an object.");
                }
                foreach (['source_id', 'username', 'firstname', 'lastname', 'email'] as $field) {
                    if (!is_string($entry[$field] ?? null) || trim($entry[$field]) === '') {
                        throw new \invalid_parameter_exception(
                            "Each 'parents' entry must include a non-empty '{$field}' string."
                        );
                    }
                }
                if (!isset($entry['children']) || !is_array($entry['children'])) {
                    throw new \invalid_parameter_exception("Each 'parents' entry must include a 'children' array.");
                }
            }
        }
    }

    /**
     * Hash the meaningful snapshot, excluding its generation timestamp and request UUID.
     *
     * @param array $data
     * @return string
     */
    private static function manifest_digest(array $data): string {
        unset($data['batch_metadata']['generated'], $data['batch_metadata']['manifest_id']);
        $canonical = self::canonicalise($data);
        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \invalid_parameter_exception('Manifest contains data that cannot be encoded.');
        }
        return hash('sha256', $json);
    }

    /**
     * Sort object keys recursively while preserving list order for stable hashes.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function canonicalise($value) {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $child) {
            $value[$key] = self::canonicalise($child);
        }
        return $value;
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHANUMEXT, 'validated, imported, replayed, or conflict.'),
            'batchid' => new external_value(PARAM_TEXT, 'LMS onboarding batch id, if imported.'),
            'digest' => new external_value(PARAM_ALPHANUMEXT, 'Stable manifest digest.'),
            'message' => new external_value(PARAM_TEXT, 'Human-readable sync result.'),
            'errors' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Validation or conflict detail.')),
            'plan' => new external_value(PARAM_RAW, 'JSON impact plan.'),
            'warnings' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Non-blocking preflight warning.')),
        ]);
    }
}
