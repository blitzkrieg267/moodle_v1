<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, version 3 or later.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

defined('MOODLE_INTERNAL') || die();

$observers = [
    ['eventname' => '\core\event\course_created', 'callback' => '\local_kreck\sync\observer::course_created'],
    ['eventname' => '\core\event\course_updated', 'callback' => '\local_kreck\sync\observer::course_updated'],
    ['eventname' => '\core\event\course_deleted', 'callback' => '\local_kreck\sync\observer::course_deleted'],
    ['eventname' => '\core\event\user_created', 'callback' => '\local_kreck\sync\observer::user_created'],
    ['eventname' => '\core\event\user_updated', 'callback' => '\local_kreck\sync\observer::user_updated'],
    ['eventname' => '\core\event\user_deleted', 'callback' => '\local_kreck\sync\observer::user_deleted'],
    ['eventname' => '\core\event\course_module_created', 'callback' => '\local_kreck\sync\observer::course_module_created'],
    ['eventname' => '\core\event\course_module_updated', 'callback' => '\local_kreck\sync\observer::course_module_updated'],
    ['eventname' => '\core\event\course_module_deleted', 'callback' => '\local_kreck\sync\observer::course_module_deleted'],
    ['eventname' => '\core\event\user_enrolment_created', 'callback' => '\local_kreck\sync\observer::user_enrolment_created'],
    ['eventname' => '\core\event\user_enrolment_updated', 'callback' => '\local_kreck\sync\observer::user_enrolment_updated'],
    ['eventname' => '\core\event\user_enrolment_deleted', 'callback' => '\local_kreck\sync\observer::user_enrolment_deleted'],
    ['eventname' => '\core\event\user_graded', 'callback' => '\local_kreck\sync\observer::user_graded'],
];
