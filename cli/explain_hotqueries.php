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
 * Records the query plans of the performance critical queries.
 *
 * A query plan only says something at realistic volume: with a few dozen rows every
 * optimiser picks a sequential scan, and rightly so. The default sizes below mirror
 * a production instance (about 243k questions of which some 5.4k are CAT items), so
 * the plans show what actually happens there.
 *
 * Run from the Moodle root. Against an instance that already carries real data use
 * --no-seed, which is the honest measurement; --seed builds a synthetic body of the
 * same shape on an empty installation.
 *
 *   php local/catquiz/cli/explain_hotqueries.php --seed
 *   php local/catquiz/cli/explain_hotqueries.php --no-seed --out=doc/query-plans.md
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// The report is markdown, which uses backticks for code spans. Building them from
// chr() keeps the coding standard happy without changing the output.
$bt = chr(96);

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'seed' => false,
        'no-seed' => false,
        'questions' => 243183,
        'items' => 5396,
        'itemparams' => 4127,
        'attempts' => 12020,
        'out' => '',
    ],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Records query plans for the performance critical catquiz queries.

Options:
  --seed              Build synthetic data first (empty installation only).
  --no-seed           Measure whatever is already there (real instance).
  --questions=N       Size of the question bank when seeding.
  --items=N           Number of CAT items when seeding.
  --itemparams=N      Number of item parameters when seeding.
  --attempts=N        Number of attempts when seeding.
  --out=PATH          Write markdown to PATH instead of stdout.
  -h, --help          This text.
");
    exit(0);
}

/**
 * Returns the plan of a query as text, in the dialect of the current engine.
 *
 * @param string $sql
 * @param array $params
 * @return string
 */
function catquiz_explain(string $sql, array $params): string {
    global $DB;

    $family = $DB->get_dbfamily();
    // ANALYZE executes the query and reports what really happened, rather than what
    // the optimiser guessed. That is the difference between an estimate and a
    // measurement, and estimates are what mislead people about index usage.
    $prefixed = $family === 'postgres'
        ? "EXPLAIN (ANALYZE, BUFFERS) $sql"
        : "ANALYZE FORMAT=JSON $sql";

    try {
        $rows = $DB->get_records_sql($prefixed, $params);
    } catch (Throwable $e) {
        return 'EXPLAIN failed: ' . $e->getMessage();
    }

    $lines = [];
    foreach ($rows as $row) {
        $lines[] = implode(' ', array_map('strval', (array) $row));
    }

    return implode("\n", $lines);
}

/**
 * Returns whether an index appears in a plan.
 *
 * @param string $plan
 * @param string $needle
 * @return string
 */
function catquiz_uses(string $plan, string $needle): string {
    return stripos($plan, $needle) !== false ? 'yes' : 'NO';
}

global $DB, $CFG;

if ($options['seed'] && !$options['no-seed']) {
    cli_writeln('Seeding synthetic data - this takes a while at production scale.');
    require_once(__DIR__ . '/explain_seed.php');
    catquiz_explain_seed(
        (int) $options['questions'],
        (int) $options['items'],
        (int) $options['itemparams'],
        (int) $options['attempts']
    );
}

// Without fresh statistics the optimiser works from stale row counts and may pick a
// plan it would not pick in production. Every measurement below assumes this ran.
cli_writeln('Refreshing table statistics.');
if ($DB->get_dbfamily() === 'postgres') {
    $DB->execute('ANALYZE');
} else {
    foreach (
        ['question', 'question_versions', 'question_bank_entries',
        'local_catquiz_items', 'local_catquiz_itemparams', 'local_catquiz_attempts'] as $t
    ) {
        $DB->execute('ANALYZE TABLE {' . $t . '}');
    }
}

$counts = [];
foreach (
    ['question', 'local_catquiz_items', 'local_catquiz_itemparams',
    'local_catquiz_attempts'] as $table
) {
    $counts[$table] = $DB->count_records($table);
}

$scaleid = (int) $DB->get_field_sql('SELECT MIN(catscaleid) FROM {local_catquiz_items}');
$contextid = (int) $DB->get_field_sql('SELECT MIN(contextid) FROM {local_catquiz_items}');

$report = [];
$report[] = '# Query-Pläne der performancekritischen Abfragen';
$report[] = '';
$report[] = 'Engine: **' . $DB->get_dbfamily() . '** — erzeugt von ' . $bt . 'cli/explain_hotqueries.php' . $bt . '.';
$report[] = '';
$report[] = '| Tabelle | Zeilen |';
$report[] = '|---|---:|';
foreach ($counts as $table => $count) {
    $report[] = sprintf('| ' . $bt . '%s' . $bt . ' | %s |', $table, number_format($count, 0, ',', '.'));
}
$report[] = '';

// 1. Question list including the attempt statistics.
[$select, $from, $where, , $params] =
    \local_catquiz\catquiz::return_sql_for_catscalequestions([$scaleid], $contextid, []);
$plan = catquiz_explain("SELECT $select FROM $from WHERE $where", $params);
$report[] = '## 1. Fragenliste mit Statistik';
$report[] = '';
$report[] = 'Erwartet: Die Statistik-Unterabfragen schränken Kontext und Skala selbst ein (#21).';
$report[] = '';
$report[] = '' . $bt . '' . $bt . '' . $bt . '';
$report[] = $plan;
$report[] = '' . $bt . '' . $bt . '' . $bt . '';
$report[] = '';

// 2. The light count.
[$countfrom, $countwhere, $countparams] =
    \local_catquiz\catquiz::return_sql_for_catscalequestions_count([$scaleid], $contextid);
$countplan = catquiz_explain("SELECT COUNT(*) FROM $countfrom WHERE $countwhere", $countparams);
$report[] = '## 2. Leichte Zählung';
$report[] = '';
$report[] = 'Erwartet: **keine** Attempt-Tabellen im Plan (#21).';
$report[] = '';
$report[] = sprintf(
    '- ' . $bt . 'local_catquiz_attempts' . $bt . ' im Plan: **%s** (erwartet: NO)',
    catquiz_uses($countplan, 'local_catquiz_attempts')
);
$report[] = '';
$report[] = '' . $bt . '' . $bt . '' . $bt . '';
$report[] = $countplan;
$report[] = '' . $bt . '' . $bt . '' . $bt . '';
$report[] = '';

// 3. Add questions.
[$addselect, $addfrom, $addwhere, , $addparams] =
    \local_catquiz\catquiz::return_sql_for_addcatscalequestions($scaleid, $contextid);
$addplan = catquiz_explain("SELECT $addselect FROM $addfrom WHERE $addwhere", $addparams);
$report[] = '## 3. Frage hinzufügen';
$report[] = '';
$report[] = 'Erwartet: ' . $bt . 'NOT EXISTS' . $bt . ' nutzt den Index aus #25; keine Fensterfunktion über';
$report[] = 'alle Frageversionen mehr (#22). Diese Abfrage geht über die **gesamte**';
$report[] = 'Fragenbank, nicht nur über die CAT-Items - hier zählt der Plan am meisten.';
$report[] = '';
$report[] = '' . $bt . '' . $bt . '' . $bt . '';
$report[] = $addplan;
$report[] = '' . $bt . '' . $bt . '' . $bt . '';
$report[] = '';

// 4. Unusable item parameters per scale.
$usableplan = catquiz_explain(
    'SELECT lci.catscaleid, COUNT(*) FROM {local_catquiz_items} lci
       JOIN {local_catquiz_itemparams} lcip ON lcip.id = lci.activeparamid
      WHERE lcip.usable = 0 AND lci.contextid = :contextid
   GROUP BY lci.catscaleid',
    ['contextid' => $contextid]
);
$report[] = '## 4. Unbrauchbare Itemparameter je Skala';
$report[] = '';
$report[] = 'Erwartet: nutzt ' . $bt . '(contextid, usable)' . $bt . ' aus #54.';
$report[] = '';
$report[] = '' . $bt . '' . $bt . '' . $bt . '';
$report[] = $usableplan;
$report[] = '' . $bt . '' . $bt . '' . $bt . '';
$report[] = '';

$markdown = implode("\n", $report) . "\n";

if ($options['out']) {
    // The path is relative to the current working directory, which is normally the
    // Moodle root - so "doc/x.md" lands next to Moodle, not in the plugin. Resolve it
    // and report the absolute path, so nobody has to guess where the file went.
    $target = $options['out'];
    $directory = dirname($target);

    if (!is_dir($directory)) {
        cli_error(sprintf(
            'Directory %s does not exist. Paths are relative to the current directory; '
                . 'from the Moodle root use local/catquiz/doc/... .',
            $directory
        ));
    }

    // A failed write returns false. Reporting success regardless is
    // worse than failing: it was doing exactly that, right after PHP had warned that
    // the stream could not be opened.
    if (file_put_contents($target, $markdown) === false) {
        cli_error('Could not write to ' . $target);
    }

    cli_writeln('Written to ' . realpath($target));
} else {
    cli_writeln($markdown);
}
