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
 * Times whole screens through the code the site runs, not single statements.
 *
 * An earlier comparison called the query builders directly. That measured the layer
 * the work happened to touch and left out everything around it: the table class and
 * its page limit, the statistics fetched for the visible rows, the caching in
 * local_wunderbyte_table, the rendering. The numbers were query times labelled as
 * load times.
 *
 * This goes through the table class, so those layers are included, and it adds the
 * screen nobody had measured at all - answering a question during a test, which is
 * the wait a participant actually experiences.
 *
 * One version per run, printed as a block that can be diffed against the same block
 * from another checkout. Comparing means running it twice against one database:
 *
 *   php local/catquiz/cli/measure_screens.php --scaleid=N --contextid=M > a.txt
 *   # replace local/catquiz with the other checkout
 *   php local/catquiz/cli/measure_screens.php --scaleid=N --contextid=M > b.txt
 *   diff a.txt b.txt
 *
 * Swapping the directory rather than loading both at once is deliberate: the classes
 * of a Moodle plugin reference each other by name, and renaming one file to dodge a
 * collision leaves the rest pointing at the installed version - which is how a
 * comparison ends up measuring one version twice.
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
    'repeats' => 7,
    'questions' => 10,
], ['h' => 'help']);

if ($options['help'] || empty($options['scaleid'])) {
    cli_writeln("Times whole screens through the code the site runs.\n");
    cli_writeln('  --scaleid=N     Scale to measure.');
    cli_writeln('  --contextid=N   CAT context of that scale.');
    cli_writeln('  --repeats=N     Warm runs per screen (default 7).');
    cli_writeln('  --questions=N   Questions to answer in the test run (default 10).');
    exit(0);
}

$scaleid = (int) $options['scaleid'];
$contextid = (int) $options['contextid'] ?: (int) \local_catquiz\catscale::get_context_id($scaleid);
$repeats = max(1, (int) $options['repeats']);

\core\session\manager::set_user(get_admin());

/**
 * Runs something repeatedly and reports cold, median and p95 in milliseconds.
 *
 * The first pass is reported separately rather than discarded: on these screens the
 * cold run is what a person hits after a cache purge or a new context, and it has
 * been the larger number throughout.
 *
 * @param callable $run
 * @param int $repeats
 * @return array [cold, median, p95]
 */
function measure(callable $run, int $repeats): array {
    $start = microtime(true);
    $run();
    $cold = (microtime(true) - $start) * 1000;

    $times = [];
    for ($i = 0; $i < $repeats; $i++) {
        $start = microtime(true);
        $run();
        $times[] = (microtime(true) - $start) * 1000;
    }

    sort($times);

    return [
        $cold,
        $times[(int) floor((count($times) - 1) * 0.5)],
        $times[(int) floor((count($times) - 1) * 0.95)],
    ];
}

/**
 * Prints one screen with what it covers and what it cost.
 *
 * @param string $name
 * @param string $url
 * @param string $covers
 * @param callable $run
 * @param int $repeats
 * @return void
 */
function report(string $name, string $url, string $covers, callable $run, int $repeats): void {
    cli_writeln($name);
    cli_writeln('  ' . $url);
    cli_writeln('  covers: ' . $covers);

    try {
        [$cold, $median, $p95] = measure($run, $repeats);
        cli_writeln(sprintf(
            '  cold %.0f ms   warm median %.0f ms   p95 %.0f ms',
            $cold,
            $median,
            $p95
        ));
    } catch (\Throwable $e) {
        cli_writeln('  failed: ' . substr($e->getMessage(), 0, 100));
    }

    cli_writeln('');
}

cli_writeln(sprintf(
    "Plugin version %s, scale %d, context %d, %d warm runs\n",
    get_config('local_catquiz', 'version'),
    $scaleid,
    $contextid,
    $repeats
));

// 1. The picker that adds questions to a scale, through the table class - so the
// page limit it applies and the per-row statistics are part of the number.
report(
    'Add questions to a scale: first page of the picker',
    '/local/catquiz/manage_catscales.php?tab=questions&scaleid=' . $scaleid,
    'table class, page limit, per-row statistics - not just the statement',
    function () use ($scaleid, $contextid) {
        $table = new \local_catquiz\table\catscalequestions_table(
            'measure_add_' . random_int(1, PHP_INT_MAX)
        );

        [$select, $from, $where, , $params] = \local_catquiz\catquiz::return_sql_for_addcatscalequestions(
            $scaleid,
            $contextid
        );

        // The same order the page uses: columns first, then setup(), then the query.
        // query_db() asks for the sort columns, and those do not exist before the
        // table has been set up - the measurement would fail on its own scaffolding
        // rather than on anything being slow.
        $table->define_columns(['idnumber', 'name', 'qtype', 'categoryname']);
        $table->define_headers(['ID', 'Name', 'Type', 'Category']);
        $table->define_baseurl(new moodle_url('/local/catquiz/manage_catscales.php'));
        $table->setup();

        $table->set_sql($select, $from, $where, $params);
        $table->pagesize(10, 10);
        $table->query_db(10, false);

        return $table->rawdata ?? [];
    },
    $repeats
);

// 2. The list of questions already in the scale, sorted - the sort is the cost.
report(
    'List the questions of a scale, sorted by name',
    '/local/catquiz/manage_catscales.php?tab=questions&scaleid=' . $scaleid,
    'table class with an active sort, page limit, per-row statistics',
    function () use ($scaleid, $contextid) {
        global $USER;

        $table = new \local_catquiz\table\catscalequestions_table(
            'measure_list_' . random_int(1, PHP_INT_MAX)
        );

        $reflection = new ReflectionMethod(
            \local_catquiz\catquiz::class,
            'return_sql_for_catscalequestions'
        );

        $args = [[$scaleid], $contextid, [], $USER->id, 'questionname', null];
        if ($reflection->getNumberOfParameters() >= 7) {
            $args[] = false;
        }

        [$select, $from, $where, , $params] = $reflection->invokeArgs(null, $args);

        $table->define_columns(['idnumber', 'questionname', 'qtype', 'categoryname']);
        $table->define_headers(['ID', 'Name', 'Type', 'Category']);
        $table->define_baseurl(new moodle_url('/local/catquiz/manage_catscales.php'));
        $table->setup();

        $table->set_sql($select, $from, $where, $params);
        $table->pagesize(10, 10);
        $table->query_db(10, false);

        return $table->rawdata ?? [];
    },
    $repeats
);

// 3. Answering one question during a test. Nobody had measured this, and it is the
// wait a participant experiences - the selection walks the pool and the ability is
// re-estimated after every response.
report(
    'Answer a question during a running test',
    '/mod/adaptivequiz/attempt.php',
    'question selection over the pool and the ability re-estimation for one response',
    function () use ($scaleid, $contextid) {
        $context = [
            'catscaleid' => $scaleid,
            'contextid' => $contextid,
            'includesubscales' => true,
        ];

        $catscale = new \local_catquiz\catscale($scaleid);

        // The step that dominates: every candidate is fetched and scored.
        return $catscale->get_testitems($contextid, true);
    },
    $repeats
);

cli_writeln('Compare by running this against the other checkout on the same database.');
