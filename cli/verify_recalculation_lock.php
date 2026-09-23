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
 * Verifies the recalculation lock across two real processes (issue #44).
 *
 * A PHPUnit test cannot show this. Moodle's database lock is re-entrant within one
 * process: a lock held by the test and then requested by the service in the same
 * request is granted, so the two runs never collide. An earlier test looked like it
 * proved the lock held - it passed because the run had been skipped for having no new
 * responses.
 *
 * Two processes do collide. This script is one half of the pair: started twice
 * against the same scale, the second call must be refused rather than wait.
 *
 * Usage:
 *   # Terminal 1 - holds the lock for a while:
 *   php cli/verify_recalculation_lock.php --scaleid=3 --hold=10
 *
 *   # Terminal 2 - while the first still runs:
 *   php cli/verify_recalculation_lock.php --scaleid=3 --expect=refused
 *
 * In CI both halves run from one step; see the workflow.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'scaleid' => 0,
    'hold' => 0,
    'expect' => '',
], ['h' => 'help']);

if ($options['help'] || empty($options['scaleid'])) {
    cli_writeln("Verifies the recalculation lock across two processes (issue #44).\n");
    cli_writeln('  --scaleid=N   Scale whose lock is taken (required).');
    cli_writeln('  --hold=N      Seconds to hold the lock before releasing it.');
    cli_writeln('  --expect=X    "granted" or "refused"; sets the exit code.');
    exit(0);
}

$scaleid = (int) $options['scaleid'];
$hold = (int) $options['hold'];
$expect = (string) $options['expect'];

// The same resource name the service uses. Repeating it here is the point rather than
// a shortcut: a parallel run identifies the lock exactly this way, and the test is
// about that collision.
$locktype = 'local_catquiz_calculation';
$resource = 'scale_' . $scaleid;

$factory = \core\lock\lock_config::get_lock_factory($locktype);

// Zero timeout, like the service: a scheduled run that waits occupies the queue
// instead of reporting that the work is already under way.
$lock = $factory->get_lock($resource, 0);

$outcome = ($lock === false) ? 'refused' : 'granted';
cli_writeln(sprintf('Lock on %s: %s', $resource, $outcome));

if ($lock !== false) {
    if ($hold > 0) {
        cli_writeln(sprintf('Holding for %d seconds ...', $hold));
        sleep($hold);
    }
    $lock->release();
    cli_writeln('Released.');
}

if ($expect !== '') {
    if ($outcome !== $expect) {
        cli_writeln(sprintf('FAILED: expected %s, got %s', $expect, $outcome));
        exit(1);
    }
    cli_writeln('As expected.');
}

exit(0);
