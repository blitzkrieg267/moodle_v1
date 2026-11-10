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
 * External function: record a learner's fee account (KRECK debit-order sync).
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
use local_kreck\persistent\feeaccount;

/**
 * Create or update one learner's fee account snapshot.
 */
class ingest_fees extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Learner user id.'),
            'balance' => new external_value(PARAM_FLOAT, 'Outstanding balance in major units (e.g. 450.00).'),
            'currency' => new external_value(PARAM_ALPHA, 'ISO currency code.', VALUE_DEFAULT, 'BWP'),
            'status' => new external_value(PARAM_INT, '0 current, 1 overdue, 2 paid, 3 failed-mandate.', VALUE_DEFAULT, 0),
            'mandateref' => new external_value(PARAM_RAW, 'Debit-order mandate reference.', VALUE_DEFAULT, null),
            'laststatement' => new external_value(PARAM_INT, 'Timestamp of the last statement; 0 = now.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Upsert the fee account.
     *
     * @param int $userid
     * @param float $balance
     * @param string $currency
     * @param int $status
     * @param string|null $mandateref
     * @param int $laststatement
     * @return array
     */
    public static function execute(
        int $userid,
        float $balance,
        string $currency = 'BWP',
        int $status = 0,
        ?string $mandateref = null,
        int $laststatement = 0
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'balance' => $balance,
            'currency' => $currency,
            'status' => $status,
            'mandateref' => $mandateref,
            'laststatement' => $laststatement,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/kreck:manage', $context);

        if (!$DB->record_exists('user', ['id' => $params['userid'], 'deleted' => 0])) {
            throw new \invalid_parameter_exception('Unknown learner user id.');
        }
        if (!in_array($params['status'], [
                feeaccount::STATUS_CURRENT, feeaccount::STATUS_OVERDUE,
                feeaccount::STATUS_PAID, feeaccount::STATUS_FAILED], true)) {
            throw new \invalid_parameter_exception('Invalid fee status.');
        }

        $existing = feeaccount::get_record(['userid' => $params['userid']]);
        $record = $existing ?: new feeaccount();
        $record->set('userid', $params['userid']);
        $record->set('balancecents', (int) round($params['balance'] * 100));
        $record->set('currency', strtoupper($params['currency']));
        $record->set('status', $params['status']);
        $record->set('mandateref', $params['mandateref']);
        $record->set('laststatement', $params['laststatement'] ?: time());
        $record->save();

        return [
            'id' => (int) $record->get('id'),
            'result' => $existing ? 'updated' : 'created',
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The fee account record id.'),
            'result' => new external_value(PARAM_ALPHA, 'created or updated.'),
        ]);
    }
}
