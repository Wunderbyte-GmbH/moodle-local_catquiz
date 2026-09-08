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
 * Times the same screens on two versions of the plugin, against one database.
 *
 * A measurement of one version alone says how fast it is; it does not say what
 * changed. This runs both against the same data on the same machine in the same
 * minute, so the difference is the code and nothing else.
 *
 * The second version is supplied as a directory - a checkout of the branch to
 * compare with. Its query builders are loaded under an alias, so both live in one
 * process and neither is reinstalled between measurements.
 *
 * Usage:
 *   php local/catquiz/cli/compare_versions.php \
 *       --scaleid=110 --contextid=111 --other=/tmp/v3 --repeats=7
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
    'scaleid' => 0,
    'contextid' => 0,
    'other' => '',
    'repeats' => 7,
], ['h' => 'help']);

if ($options['help'] || empty($options['scaleid']) || empty($options['other'])) {
    cli_writeln("Times the same screens on two plugin versions.\n");
    cli_writeln('  --scaleid=N     Scale to measure.');
    cli_writeln('  --contextid=N   CAT context of that scale.');
    cli_writeln('  --other=PATH    Checkout of the version to compare with.');
    cli_writeln('  --repeats=N     Warm runs per screen (default 7).');
    exit(0);
}

$scaleid = (int) $options['scaleid'];
$contextid = (int) $options['contextid'];
$other = rtrim($options['other'], '/');
$repeats = max(1, (int) $options['repeats']);

if (!is_file($other . '/classes/catquiz.php')) {
    cli_error("No plugin checkout at $other.");
}

\core\session\manager::set_user(get_admin());

/**
 * Loads the other version's query builder under a name of its own.
 *
 * Both versions declare local_catquiz\catquiz, so the second is renamed while being
 * read. That keeps the comparison in one process: reinstalling between runs would
 * add cache and connection effects to the difference being measured.
 *
 * @param string $path
 * @return string The class name to call.
 */
function load_other_version(string $path): string {
    $source = file_get_contents($path . '/classes/catquiz.php');

    $source = preg_replace('/^<\?php/', '', $source, 1);
    $source = preg_replace(
        '/^namespace\s+local_catquiz;/m',
        'namespace local_catquiz_other;',
        $source,
        1
    );

    // Everything the class refers to still lives in the installed version.
    $source = "namespace local_catquiz_other;\n"
        . "use local_catquiz\\catscale;\n"
        . "use local_catquiz\\catcontext;\n"
        . substr($source, strpos($source, 'class ') !== false ? strpos($source, 'abstract class ') !== false
            ? strpos($source, 'abstract class ') : strpos($source, 'class ') : 0);

    $file = make_temp_directory('catquiz') . '/other_catquiz.php';
    file_put_contents($file, "<?php\n" . $source);

    require_once($file);

    return '\\local_catquiz_other\\catquiz';
}

/**
 * Runs a statement repeatedly and returns median and p95 in milliseconds.
 *
 * @param callable $run
 * @param int $repeats
 * @return array [median, p95]
 */
function time_it(callable $run, int $repeats): array {
    global $DB;

    // One untimed pass so the comparison is warm cache against warm cache.
    $run();

    $times = [];
    for ($i = 0; $i < $repeats; $i++) {
        $start = microtime(true);
        $run();
        $times[] = (microtime(true) - $start) * 1000;
    }

    sort($times);
    $median = $times[(int) floor((count($times) - 1) * 0.5)];
    $p95 = $times[(int) floor((count($times) - 1) * 0.95)];

    return [$median, $p95];
}

$othername = load_other_version($other);

cli_writeln(sprintf(
    "Scale %d, context %d, %d runs per screen\n",
    $scaleid,
    $contextid,
    $repeats
));

cli_writeln('  A = this working copy');
cli_writeln('  B = ' . $other . "\n");

$screens = [];

// 1. The dialog that adds questions to a scale.
$screens[] = [
    'name' => 'Add questions to a scale: first page of the picker',
    'url' => '/local/catquiz/manage_catscales.php?tab=questions&scaleid=' . $scaleid,
    'what' => 'Ten unassigned questions, no sort, no filter - the dialog as it opens.',
    'run' => function (string $class) use ($scaleid, $contextid) {
        global $DB;

        [$select, $from, $where, , $params] = $class::return_sql_for_addcatscalequestions(
            $scaleid,
            $contextid
        );

        // The current version narrows this inside the subquery; the comparison runs
        // whatever the version under test produces.
        return function () use ($DB, $select, $from, $where, $params) {
            $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params, 0, 10);
        };
    },
];

// 2. The list of questions already in a scale.
$screens[] = [
    'name' => 'List the questions of a scale, sorted by name',
    'url' => '/local/catquiz/manage_catscales.php?tab=questions&scaleid=' . $scaleid,
    'what' => 'Ten rows of the question table, ordered by question name.',
    'run' => function (string $class) use ($scaleid, $contextid) {
        global $DB, $USER;

        $args = [[$scaleid], $contextid, [], $USER->id, null, null];

        // The current version takes a seventh argument for the lean column set.
        $reflection = new ReflectionMethod($class, 'return_sql_for_catscalequestions');
        if ($reflection->getNumberOfParameters() >= 7) {
            $args[] = true;
        }

        [$select, $from, $where, , $params] = $reflection->invokeArgs(null, $args);

        return function () use ($DB, $select, $from, $where, $params) {
            $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params, 0, 10);
        };
    },
];

// 3. Counting those questions, which drives the pager.
$screens[] = [
    'name' => 'Count the questions of a scale (the pager total)',
    'url' => '/local/catquiz/manage_catscales.php?tab=questions&scaleid=' . $scaleid,
    'what' => 'The number under the table, recomputed on every page view.',
    'run' => function (string $class) use ($scaleid, $contextid) {
        global $DB, $USER;

        if (method_exists($class, 'return_sql_for_catscalequestions_count')) {
            [$from, $where, $params] = $class::return_sql_for_catscalequestions_count(
                [$scaleid],
                $contextid
            );

            return function () use ($DB, $from, $where, $params) {
                $DB->count_records_sql("SELECT COUNT(*) FROM $from WHERE $where", $params);
            };
        }

        // The older version has no dedicated count; it counts the full statement.
        [, $from, $where, , $params] = $class::return_sql_for_catscalequestions(
            [$scaleid],
            $contextid,
            [],
            $USER->id,
            null,
            null
        );

        return function () use ($DB, $from, $where, $params) {
            $DB->count_records_sql("SELECT COUNT(*) FROM $from WHERE $where", $params);
        };
    },
];

foreach ($screens as $screen) {
    cli_writeln($screen['name']);
    cli_writeln('  ' . $screen['url']);
    cli_writeln('  ' . $screen['what']);

    $results = [];
    foreach (['A' => '\\local_catquiz\\catquiz', 'B' => $othername] as $label => $class) {
        try {
            [$median, $p95] = time_it(($screen['run'])($class), $repeats);
            $results[$label] = $p95;
            cli_writeln(sprintf('  %s  median %7.0f ms   p95 %7.0f ms', $label, $median, $p95));
        } catch (\Throwable $e) {
            $results[$label] = null;
            cli_writeln(sprintf('  %s  not available: %s', $label, substr($e->getMessage(), 0, 70)));
        }
    }

    if (isset($results['A'], $results['B']) && $results['B'] > 0) {
        $factor = $results['B'] / $results['A'];
        cli_writeln(sprintf(
            '  -> %s %.1f x',
            $factor >= 1 ? 'A is faster by' : 'A is slower by',
            $factor >= 1 ? $factor : 1 / $factor
        ));
    }

    cli_writeln('');
}
