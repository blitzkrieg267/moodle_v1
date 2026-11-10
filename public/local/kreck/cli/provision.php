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
 * KRECK onboarding CLI: dry-run, provision or roll back a manifest.
 *
 * Usage:
 *   php local/kreck/cli/provision.php --manifest=path.json --dry-run
 *   php local/kreck/cli/provision.php --manifest=path.json --execute
 *   php local/kreck/cli/provision.php --rollback=onboarding_batch_XXXX
 *   php local/kreck/cli/provision.php --list
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_kreck\onboarding\engine;
use local_kreck\onboarding\manifest;

[$options, $unrecognised] = cli_get_params([
    'manifest' => '',
    'dry-run' => false,
    'execute' => false,
    'rollback' => '',
    'list' => false,
    'help' => false,
], ['h' => 'help']);

if ($options['help']) {
    cli_writeln("KRECK onboarding engine\n"
        . "  --manifest=FILE --dry-run   Validate without writing.\n"
        . "  --manifest=FILE --execute   Provision the manifest.\n"
        . "  --rollback=BATCHID          Roll back a provisioned batch.\n"
        . "  --list                      List provisioned batches.\n");
    exit(0);
}

$engine = new engine();

if ($options['list']) {
    foreach ($engine->list_batches() as $b) {
        cli_writeln("{$b['batchid']}  courses={$b['courses']} users={$b['users']} categories={$b['categories']}  "
            . userdate($b['timecreated']));
    }
    exit(0);
}

if (!empty($options['rollback'])) {
    $result = $engine->rollback($options['rollback']);
    if ($result['ok']) {
        cli_writeln("Rolled back {$options['rollback']}: " . json_encode($result['removed']));
        exit(0);
    }
    cli_error($result['error']);
}

if (empty($options['manifest']) || !is_readable($options['manifest'])) {
    cli_error('Provide a readable --manifest=FILE (or use --rollback / --list).');
}

$manifest = manifest::from_json(file_get_contents($options['manifest']));

if ($options['execute']) {
    $result = $engine->provision($manifest);
    foreach ($result['log'] as $line) {
        cli_writeln($line);
    }
    if ($result['ok']) {
        cli_writeln("[PROVISIONED] batch={$result['batchid']}");
        exit(0);
    }
    cli_error("[FAILED] rolled back. " . $result['error']);
}

// Default: dry run.
$result = $engine->validate($manifest);
$plan = $result['plan'];
cli_writeln('Categories: ' . count($plan['categories_create']) . ' to create, '
    . count($plan['categories_exist']) . ' exist.');
cli_writeln('Staff users: ' . count($plan['users_create']) . ' to create, ' . count($plan['users_exist']) . ' exist.');
cli_writeln('Cohorts: ' . count($plan['cohorts_create']) . ' to create, ' . count($plan['cohorts_exist']) . ' exist.');
cli_writeln('Students to create: ' . $plan['students_create'] . '.');
cli_writeln('Courses: ' . count($plan['courses']) . ' to create.');
cli_writeln('Activities to create: ' . $plan['modules_total'] . '.');
foreach ($result['warnings'] as $w) {
    cli_writeln('WARN: ' . $w);
}
if ($result['ok']) {
    cli_writeln('[DRY-RUN PASSED]');
    exit(0);
}
foreach ($result['errors'] as $e) {
    cli_writeln('ERROR: ' . $e);
}
cli_error('[DRY-RUN FAILED]');
