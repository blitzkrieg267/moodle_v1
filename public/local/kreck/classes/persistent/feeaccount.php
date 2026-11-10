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
 * A learner's fee account snapshot (mirrored from KRECK SMS debit orders).
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\persistent;

use core\persistent;

/**
 * Persistent for the per-learner fee balance and debit-order status.
 */
class feeaccount extends persistent {

    /** @var string Table name. */
    const TABLE = 'local_kreck_feeaccount';

    /** @var int Up to date. */
    const STATUS_CURRENT = 0;
    /** @var int Overdue. */
    const STATUS_OVERDUE = 1;
    /** @var int Paid in full. */
    const STATUS_PAID = 2;
    /** @var int Debit order failed. */
    const STATUS_FAILED = 3;

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'userid' => [
                'type' => PARAM_INT,
            ],
            'balancecents' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'currency' => [
                'type' => PARAM_ALPHA,
                'default' => 'BWP',
            ],
            'status' => [
                'type' => PARAM_INT,
                'default' => self::STATUS_CURRENT,
                'choices' => [
                    self::STATUS_CURRENT,
                    self::STATUS_OVERDUE,
                    self::STATUS_PAID,
                    self::STATUS_FAILED,
                ],
            ],
            'mandateref' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'laststatement' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    /**
     * The balance as a decimal major-unit amount (e.g. cents -> pula).
     *
     * @return float
     */
    public function get_balance_major(): float {
        return $this->get('balancecents') / 100;
    }
}
