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
 * Times the same work on one version, in a form another version can be diffed against.
 *
 * Three levels, because they answer different questions and mixing them has already
 * produced misleading numbers here:
 *
 *   query    - the statement alone. Shows what an index or a rewrite did; says
 *              nothing about what a person waits for.
 *   screen   - through the table class: page limit, per-row statistics, caching.
 *   manager  - the whole CAT manager page. This is the level at which building every
 *              panel up front differs from building the active one with ten rows,
 *              and the two lower levels cannot see that difference at all.
 *
 * Every level also reports a fingerprint of what came back. A faster statement that
 * returns fewer rows is not an improvement, and between versions with deliberate
 * behavioural differences the fingerprint separates "same work, faster" from
 * "different work, therefore not comparable".
 *
 * Comparing means running this twice against one database with the plugin directory
 * swapped in between, in separate processes. Loading both versions at once by
 * renaming one class does not work: the classes of a Moodle plugin reference each
 * other by name, so the rest keeps pointing at the installed version and the
 * measurement quietly becomes a hybrid.
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
    'repeats' => 20,
    'mode' => 'all',
], ['h' => 'help']);

if ($options['help'] || empty($options['scaleid'])) {
    cli_writeln("Times work on one version, for diffing against another.\n");
    cli_writeln('  --scaleid=N     Scale to measure.');
    cli_writeln('  --contextid=N   CAT context; taken from the scale when omitted.');
    cli_writeln('  --repeats=N     Warm runs (default 20; 7 is thin for a p95).');
    cli_writeln('  --mode=X        query | screen | manager | all (default all).');
    exit(0);
}

$scaleid = (int) $options['scaleid'];
$contextid = (int) $options['contextid'] ?: (int) \local_catquiz\catscale::get_context_id($scaleid);
$repeats = max(1, (int) $options['repeats']);
$mode = $options['mode'];

\core\session\manager::set_user(get_admin());

/**
 * Runs something repeatedly and reports cold, median, p95 and the spread.
 *
 * The cold run is reported rather than discarded: it is what a person hits after a
 * cache purge, and on these screens it has consistently been the larger number.
 *
 * Minimum and maximum are printed because at small repeat counts a p95 sits close to
 * "the slowest run" - stating the range keeps that visible instead of dressing it up
 * as a percentile.
 *
 * @param callable $run Returns whatever should be fingerprinted.
 * @param int $repeats
 * @return array
 */
function measure(callable $run, int $repeats): array {
    $start = microtime(true);
    $result = $run();
    $cold = (microtime(true) - $start) * 1000;

    $times = [];
    for ($i = 0; $i < $repeats; $i++) {
        $start = microtime(true);
        $run();
        $times[] = (microtime(true) - $start) * 1000;
    }

    sort($times);
    $last = count($times) - 1;

    return [
        'cold' => $cold,
        'median' => $times[(int) floor($last * 0.5)],
        'p95' => $times[(int) floor($last * 0.95)],
        'min' => $times[0],
        'max' => $times[$last],
        'result' => $result,
    ];
}

/**
 * Describes a result so two versions can be checked for having done the same work.
 *
 * The row count and the ordered ids, not the payload: the payload legitimately
 * differs between versions - a column added, a label reworded - while the set and
 * the order of rows is what "the same screen" means.
 *
 * @param mixed $result
 * @return string
 */
function fingerprint($result): string {
    // A flat list of rows: the count and the ordered ids. The payload legitimately
    // differs between versions - a column added, a label reworded - while the set and
    // the order of rows is what "the same screen" means.
    if (is_array($result) && $result !== [] && array_key_exists('id', (array) reset($result))) {
        $ids = array_map(
            function ($row) {
                $row = (array) $row;
                return $row['id'] ?? '?';
            },
            array_values($result)
        );

        return sprintf('%d rows, ids %s', count($result), substr(sha1(implode(',', $ids)), 0, 12));
    }

    // Anything else - the manager page returns a nested template structure. Walking
    // only the top level turned most of it into question marks, so two clearly
    // different pages could share a hash that said nothing. The whole structure is
    // canonicalised instead: keys sorted, so an unordered difference does not count
    // as one, and objects reduced to their properties.
    if (is_array($result) || is_object($result)) {
        $canonical = canonicalise($result);

        // What the page shows, not how it was assembled.
        //
        // Comparing the whole structure marked the two versions as incomparable, and
        // that was the wrong answer: one carries isQuestions-style flags and builds
        // only the active panel, the other builds every panel. More keys, less work,
        // same page - which is the improvement, not a difference in result.
        //
        // So the fingerprint takes the panels that carry content and the active one.
        // A page that shows the same panels with the same content is the same page,
        // however much was built behind it. A panel that lost its content still
        // shows up, because that would be a loss rather than an optimisation.
        $keys = is_array($canonical) ? array_keys($canonical) : [];

        $panels = [];
        $active = [];
        foreach ($keys as $key) {
            if (str_starts_with($key, 'is') && !empty($canonical[$key])) {
                $active[] = substr($key, 2);
                continue;
            }
            if (str_ends_with($key, 'display') && !empty($canonical[$key])) {
                $panels[] = substr($key, 0, -7);
            }
        }

        sort($panels);
        sort($active);

        return sprintf(
            'page: panels [%s] active [%s]',
            implode(' ', $panels),
            implode(' ', $active) ?: 'none declared'
        );
    }

    if (is_scalar($result)) {
        return 'value ' . substr(sha1((string) $result), 0, 12);
    }

    return 'not comparable';
}

/**
 * Sorts keys throughout a structure so equal content hashes equally.
 *
 * Without this the hash depends on the order a version happened to assemble its
 * template data, and a reordering would read as a behavioural difference.
 *
 * @param mixed $value
 * @return mixed
 */
function canonicalise($value) {
    if (is_object($value)) {
        $value = get_object_vars($value);
    }

    if (is_array($value)) {
        ksort($value);
        foreach ($value as $key => $child) {
            $value[$key] = canonicalise($child);
        }
    }

    return $value;
}

/**
 * Prints one measurement.
 *
 * @param string $level
 * @param string $name
 * @param string $url
 * @param string $covers
 * @param callable $run
 * @param int $repeats
 * @return void
 */
function report(
    string $level,
    string $name,
    string $url,
    string $covers,
    callable $run,
    int $repeats
): void {
    cli_writeln(sprintf('[%s] %s', $level, $name));
    cli_writeln('  ' . $url);
    cli_writeln('  covers: ' . $covers);

    try {
        $m = measure($run, $repeats);
        cli_writeln(sprintf(
            '  cold %.0f ms   warm median %.0f ms   p95 %.0f ms   range %.0f-%.0f ms',
            $m['cold'],
            $m['median'],
            $m['p95'],
            $m['min'],
            $m['max']
        ));
        cli_writeln('  result: ' . fingerprint($m['result']));
    } catch (\Throwable $e) {
        cli_writeln('  failed: ' . substr($e->getMessage(), 0, 120));
    }

    cli_writeln('');
}

/**
 * Whether a level was asked for.
 *
 * @param string $mode
 * @param string $level
 * @return bool
 */
function wanted(string $mode, string $level): bool {
    return $mode === 'all' || $mode === $level;
}

cli_writeln(sprintf(
    "Plugin version %s, scale %d, context %d, %d warm runs, mode %s\n",
    get_config('local_catquiz', 'version'),
    $scaleid,
    $contextid,
    $repeats,
    $mode
));

if (wanted($mode, 'query')) {
    report(
        'query',
        'Add questions to a scale: the statement behind the picker',
        '/local/catquiz/manage_catscales.php?tab=questions&scaleid=' . $scaleid,
        'the SQL alone - no table class, no page limit, no per-row statistics',
        function () use ($scaleid, $contextid) {
            global $DB;

            [$select, $from, $where, , $params] =
                \local_catquiz\catquiz::return_sql_for_addcatscalequestions($scaleid, $contextid);

            return $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params, 0, 10);
        },
        $repeats
    );
}

if (wanted($mode, 'screen')) {
    report(
        'screen',
        'Add questions to a scale: first page of the picker',
        '/local/catquiz/manage_catscales.php?tab=questions&scaleid=' . $scaleid,
        'table class, page limit, per-row statistics',
        function () use ($scaleid, $contextid) {
            $table = new \local_catquiz\table\catscalequestions_table(
                'measure_add_' . random_int(1, PHP_INT_MAX)
            );

            [$select, $from, $where, , $params] =
                \local_catquiz\catquiz::return_sql_for_addcatscalequestions($scaleid, $contextid);

            $table->define_columns(['idnumber', 'name', 'qtype', 'categoryname']);
            $table->define_headers(['ID', 'Name', 'Type', 'Category']);
            $table->define_baseurl(new moodle_url('/local/catquiz/manage_catscales.php'));
            $table->pageable(true);
            $table->setup();

            $table->set_sql($select, $from, $where, $params);
            $table->pagesize(10, 10);
            $table->query_db(10, true);

            return $table->rawdata ?? [];
        },
        $repeats
    );

    report(
        'screen',
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

            // No sort passed to the builder: it writes ORDER BY into the statement,
            // and the table wraps that in SELECT COUNT(1) FROM ( ... ) for the pager,
            // where PostgreSQL rejects it. The page sorts through the table, whose
            // ORDER BY sits outside the counted subquery.
            $args = [[$scaleid], $contextid, [], $USER->id, null, null];
            if ($reflection->getNumberOfParameters() >= 7) {
                $args[] = false;
            }

            [$select, $from, $where, , $params] = $reflection->invokeArgs(null, $args);

            $table->define_columns(['idnumber', 'questionname', 'qtype', 'categoryname']);
            $table->define_headers(['ID', 'Name', 'Type', 'Category']);
            $table->define_sortablecolumns(['questionname']);
            $table->define_baseurl(new moodle_url('/local/catquiz/manage_catscales.php'));
            $table->sortable(true, 'questionname', SORT_ASC);
            $table->pageable(true);
            $table->setup();

            $table->set_sql($select, $from, $where, $params);
            $table->pagesize(10, 10);
            $table->query_db(10, true);

            return $table->rawdata ?? [];
        },
        $repeats
    );
}

if (wanted($mode, 'manager')) {
    report(
        'manager',
        'Open the CAT manager on a scale (whole page)',
        '/local/catquiz/manage_catscales.php?scaleid=' . $scaleid,
        'the complete page: every panel the version decides to build, not one table',
        function () use ($scaleid, $contextid) {
            global $PAGE;

            $dashboard = new \local_catquiz\output\catscalemanager\managecatscaledashboard(
                0,
                $contextid,
                $scaleid,
                0,
                1,
                'question',
                'scaledetails'
            );

            // Here a version decides how much to build: one assembles every tab,
            // another only the active one. That is the difference this level
            // exists for, and the one the table numbers cannot show.
            return $dashboard->export_for_template($PAGE->get_renderer('local_catquiz'));
        },
        $repeats
    );
}

if (wanted($mode, 'screen') || wanted($mode, 'manager')) {
    report(
        'runtime',
        'Fetch the item pool for a running test',
        '/mod/adaptivequiz/attempt.php',
        'candidate retrieval only - NOT a full answer cycle: no response is stored, '
            . 'no ability re-estimated, no next question selected',
        function () use ($scaleid, $contextid) {
            $catscale = new \local_catquiz\catscale($scaleid);

            return $catscale->get_testitems($contextid, true);
        },
        $repeats
    );
}

cli_writeln('Compare by running this again with the other plugin directory in place.');
