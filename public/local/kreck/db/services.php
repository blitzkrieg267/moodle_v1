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
 * KRECK MicroLearn external (web service) functions.
 *
 * These are the Layer-1 <-> app bridge: the KRECK school system pushes the
 * daily register and fee updates in, and the mobile app reads the learner
 * summary out, all against the one shared student record.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_kreck_get_learner_summary' => [
        'classname'   => 'local_kreck\\external\\get_learner_summary',
        'methodname'  => 'execute',
        'description' => 'Return a learner\'s KRECK summary: student ID, attendance, fee balance, streak and struggle topics.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/kreck:view',
        'services'    => ['local_kreck_service'],
    ],
    'local_kreck_ingest_attendance' => [
        'classname'   => 'local_kreck\\external\\ingest_attendance',
        'methodname'  => 'execute',
        'description' => 'Record (or update) an attendance mark for a learner on a given day. Used by the KRECK register sync.',
        'type'        => 'write',
        'ajax'        => false,
        'capabilities' => 'local/kreck:manage',
        'services'    => ['local_kreck_service'],
    ],
    'local_kreck_ingest_fees' => [
        'classname'   => 'local_kreck\\external\\ingest_fees',
        'methodname'  => 'execute',
        'description' => 'Record (or update) a learner\'s fee balance and debit-order status. Used by the KRECK fees sync.',
        'type'        => 'write',
        'ajax'        => false,
        'capabilities' => 'local/kreck:manage',
        'services'    => ['local_kreck_service'],
    ],
    'local_kreck_ingest_streak' => [
        'classname'   => 'local_kreck\\external\\ingest_streak',
        'methodname'  => 'execute',
        'description' => 'Record (or update) a learner\'s app-engagement streak. Used by the KRECK app attendance feed.',
        'type'        => 'write',
        'ajax'        => false,
        'capabilities' => 'local/kreck:manage',
        'services'    => ['local_kreck_service'],
    ],
    'local_kreck_ingest_topicstat' => [
        'classname'   => 'local_kreck\\external\\ingest_topicstat',
        'methodname'  => 'execute',
        'description' => 'Record (or update) per-topic attempt/wrong counts for a learner (struggle analytics).',
        'type'        => 'write',
        'ajax'        => false,
        'capabilities' => 'local/kreck:manage',
        'services'    => ['local_kreck_service'],
    ],
];

$services = [
    'KRECK MicroLearn data service' => [
        'shortname'      => 'local_kreck_service',
        'functions'      => [
            'local_kreck_get_learner_summary',
            'local_kreck_ingest_attendance',
            'local_kreck_ingest_fees',
            'local_kreck_ingest_streak',
            'local_kreck_ingest_topicstat',
        ],
        'restrictedusers' => 1,
        'enabled'         => 0,
        'downloadfiles'   => 0,
        'uploadfiles'     => 0,
    ],
];
