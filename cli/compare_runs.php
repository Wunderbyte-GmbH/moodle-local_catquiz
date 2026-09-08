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
 * Puts two measure_screens.php runs side by side, and refuses to divide when the two
 * did not do the same work.
 *
 * A speed ratio is only meaningful between runs that produced the same result. A
 * statement that is twice as fast because it returns half the rows is not an
 * improvement, and between versions with deliberate behavioural differences the two
 * cases look identical in a table of milliseconds.
 *
 * The fingerprints each run prints are therefore compared first. Where they differ,
 * this prints NOT COMPARABLE and no ratio - the numbers are still shown, because the
 * difference is often the interesting part, but they are not presented as a speedup.
 *
 * Usage:
 *   php local/catquiz/cli/compare_runs.php --a=current.txt --b=v3.txt
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params([
    'help' => false,
    'a' => '',
    'b' => '',
    'labela' => 'A',
    'labelb' => 'B',
], ['h' => 'help']);

if ($options['help'] || empty($options['a']) || empty($options['b'])) {
    cli_writeln("Compares two measure_screens.php runs.\n");
    cli_writeln('  --a=FILE        First run.');
    cli_writeln('  --b=FILE        Second run.');
    cli_writeln('  --labela=NAME   What to call the first (default A).');
    cli_writeln('  --labelb=NAME   What to call the second (default B).');
    exit(0);
}

/**
 * Reads a run into screens keyed by name.
 *
 * @param string $path
 * @return array
 */
function parse_run(string $path): array {
    if (!is_readable($path)) {
        cli_error("Cannot read $path.");
    }

    $screens = [];
    $current = null;

    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^\[(\w+)\]\s+(.*)$/', $line, $m)) {
            $current = $m[2];
            $screens[$current] = ['level' => $m[1], 'median' => null, 'fingerprint' => null];
            continue;
        }

        if ($current === null) {
            continue;
        }

        if (preg_match('/warm median\s+([0-9.]+) ms\s+p95\s+([0-9.]+) ms/', $line, $m)) {
            $screens[$current]['median'] = (float) $m[1];
            $screens[$current]['p95'] = (float) $m[2];
        }

        if (preg_match('/^\s*result:\s*(.+)$/', $line, $m)) {
            $screens[$current]['fingerprint'] = trim($m[1]);
        }

        if (preg_match('/^\s*failed:/', $line)) {
            $screens[$current]['failed'] = true;
        }
    }

    return $screens;
}

$a = parse_run($options['a']);
$b = parse_run($options['b']);

cli_writeln(sprintf("%s vs %s\n", $options['labela'], $options['labelb']));

$comparable = 0;
$blocked = 0;

foreach ($a as $name => $left) {
    $right = $b[$name] ?? null;

    cli_writeln($name);

    if ($right === null) {
        cli_writeln('  NOT COMPARABLE - the other run does not have this screen.');
        cli_writeln('');
        $blocked++;
        continue;
    }

    if (!empty($left['failed']) || !empty($right['failed'])) {
        cli_writeln('  FAILED in at least one run - no ratio.');
        cli_writeln('');
        $blocked++;
        continue;
    }

    cli_writeln(sprintf(
        '  %-8s median %7.0f ms   p95 %7.0f ms   %s',
        $options['labela'],
        $left['median'],
        $left['p95'] ?? 0,
        $left['fingerprint'] ?? '-'
    ));
    cli_writeln(sprintf(
        '  %-8s median %7.0f ms   p95 %7.0f ms   %s',
        $options['labelb'],
        $right['median'],
        $right['p95'] ?? 0,
        $right['fingerprint'] ?? '-'
    ));

    // The gate. Same numbers, different work - that is the case this exists to catch.
    if ($left['fingerprint'] !== $right['fingerprint']) {
        cli_writeln('  NOT COMPARABLE - the two runs produced different results.');
        cli_writeln('  A ratio here would compare different work and read as a speedup.');
        $blocked++;
        cli_writeln('');
        continue;
    }

    if ($left['median'] > 0 && $right['median'] > 0) {
        $ratio = $right['median'] / $left['median'];
        cli_writeln(sprintf(
            '  -> %s is %.1f x %s',
            $options['labela'],
            $ratio >= 1 ? $ratio : 1 / $ratio,
            $ratio >= 1 ? 'faster' : 'slower'
        ));
        $comparable++;
    }

    cli_writeln('');
}

cli_writeln(sprintf(
    '%d screens comparable, %d not.',
    $comparable,
    $blocked
));

if ($blocked > 0) {
    cli_writeln('Screens marked NOT COMPARABLE need a decision before the numbers are '
        . 'quoted: either the difference is intended, or one of the runs is wrong.');
}
