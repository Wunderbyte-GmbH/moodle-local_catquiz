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
 * Evaluates an attempt load run (mod_adaptivequiz #17): the traces of the requests, the question usages, the
 * sessions.
 *
 * Reads local_catquiz_trace for the attempts at the activity 'catquizload' that started after --since and prints
 * JSON: per kind of request (first_item, next_item, reload, complete) and per span the count and p50/p95/p99/max
 * of the time, the database queries and the memory; the cold first request separately from the warm ones; the
 * question usages: questions, slots answered more than once, a question in two slots, steps per slot; the size
 * of the session files of the run. No content of a question or an answer is read.
 *
 * Usage (from the Moodle root): php local/catquiz/tests/load/analyse_attempt_load.php --since=<unix time>
 *     [--idnumber=catquizload]
 *
 * The section "integrity" checks the CAT attempts and progress of the run (issue #149): one CAT attempt per attempt
 * of the host, of the same person; at most one progress per CAT attempt, of the same person and component.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['since' => 0, 'idnumber' => 'catquizload', 'help' => false], ['h' => 'help']);
$since = (int) $options['since'];

/**
 * Percentiles of a list of numbers (nearest rank).
 *
 * @param array $values
 * @return array
 */
function local_catquiz_load_stats(array $values): array {
    $values = array_values(array_filter($values, fn($v) => $v !== null));
    if (!$values) {
        return ['n' => 0];
    }
    sort($values);
    $n = count($values);
    $rank = fn(float $p) => $values[max(0, (int) ceil($p * $n) - 1)];
    return [
        'n' => $n,
        'p50' => round($rank(0.5), 2),
        'p95' => round($rank(0.95), 2),
        'p99' => round($rank(0.99), 2),
        'max' => round($values[$n - 1], 2),
    ];
}

$cm = $DB->get_record('course_modules', ['idnumber' => (string) $options['idnumber']], '*', MUST_EXIST);
$attempts = $DB->get_records_select(
    'adaptivequiz_attempt',
    'instance = ? AND timecreated >= ?',
    [$cm->instance, $since],
    'id ASC'
);
if (!$attempts) {
    cli_error('No attempts since ' . $since);
}
[$insql, $params] = $DB->get_in_or_equal(array_keys($attempts));
$traces = $DB->get_records_select('local_catquiz_trace', "adaptiveattemptid $insql", $params, 'id ASC');

$bykind = [];
$byspan = [];
$cold = null;
$requestids = [];
foreach ($traces as $trace) {
    $spans = json_decode($trace->spans, true) ?: [];
    $request = $spans[0]['meta'] ?? [];
    $requestids[] = $request['requestid'] ?? null;
    $row = [
        'ms' => $trace->requestms === null ? null : (float) $trace->requestms,
        'db' => (int) $trace->dbqueries,
        'peakkb' => (int) ($request['memorypeakkb'] ?? 0),
        'fatal' => !empty($request['fatal']),
    ];
    if ($cold === null) {
        $cold = $row + ['kind' => $trace->kind, 'requestid' => $request['requestid'] ?? null];
        continue;
    }
    $bykind[$trace->kind][] = $row;
    foreach (array_slice($spans, 1) as $span) {
        $byspan[$span['name']]['ms'][] = isset($span['ms']) ? (float) $span['ms'] : null;
        $byspan[$span['name']]['db'][] = $span['queries'] ?? null;
        $byspan[$span['name']]['memdeltakb'][] = $span['memdeltakb'] ?? null;
        $byspan[$span['name']]['peakgrewkb'][] = $span['peakgrewkb'] ?? null;
    }
}

$kinds = [];
foreach ($bykind as $kind => $rows) {
    $kinds[$kind] = [
        'requestms' => local_catquiz_load_stats(array_column($rows, 'ms')),
        'dbqueries' => local_catquiz_load_stats(array_column($rows, 'db')),
        'memorypeakkb' => local_catquiz_load_stats(array_column($rows, 'peakkb')),
        'fatal' => count(array_filter($rows, fn($r) => $r['fatal'])),
    ];
}
ksort($byspan);
$spans = [];
foreach ($byspan as $name => $values) {
    $spans[$name] = [
        'ms' => local_catquiz_load_stats($values['ms']),
        'dbqueries' => local_catquiz_load_stats($values['db']),
        'memdeltakb' => local_catquiz_load_stats($values['memdeltakb']),
        'peakgrewkb' => local_catquiz_load_stats($values['peakgrewkb']),
    ];
}

// The question usages: every answer processed once, no question twice.
$usages = [];
foreach ($attempts as $attempt) {
    $slots = $DB->get_records('question_attempts', ['questionusageid' => $attempt->uniqueid], 'slot ASC', 'id, slot, questionid');
    $questions = array_column($slots, 'questionid');
    $stepsperslot = [];
    $processedtwice = 0;
    foreach ($slots as $qa) {
        $steps = $DB->get_records('question_attempt_steps', ['questionattemptid' => $qa->id], 'sequencenumber ASC', 'id, state');
        $stepsperslot[] = count($steps);
        /* A response is processed once: a second step that ends the question would be a double submission. Saving the
           response (state complete) and finishing it (graded...) are the two steps of one answer, so only the
           finishing states count - one per question. */
        $ending = array_filter($steps, fn($s) => preg_match('/^(graded|gaveup|mangr)/', (string) $s->state) === 1);
        if (count($ending) > 1) {
            $processedtwice++;
        }
    }
    $usages[] = [
        'attempt' => (int) $attempt->id,
        'state' => $attempt->attemptstate,
        'stopcode' => $attempt->stopcode,
        'questionsattempted' => (int) $attempt->questionsattempted,
        'slots' => count($slots),
        'duplicatequestions' => count($questions) - count(array_unique($questions)),
        'slotsprocessedtwice' => $processedtwice,
        'stepsperslot' => array_count_values($stepsperslot),
    ];
}

// Integrity of the CAT attempts and progress (issue #149): nothing created twice, nothing of another person.
$integrity = ['catattempts' => 0, 'withoutcatattempt' => 0, 'morethanonecatattempt' => 0, 'catattemptofanother' => 0,
    'progress' => 0, 'morethanoneprogress' => 0, 'progressofanother' => 0, 'progresswithoutcatattempt' => 0];
foreach ($attempts as $attempt) {
    [$compsql, $compparams] = $DB->get_in_or_equal(\local_catquiz\catquiz::component_names('mod_adaptivequiz'));
    $cats = $DB->get_records_select(
        'local_catquiz_attempts',
        "attemptid = ? AND component $compsql",
        array_merge([$attempt->id], $compparams),
        'id ASC',
        'id, userid'
    );
    $integrity['catattempts'] += count($cats);
    if (!$cats) {
        $integrity['withoutcatattempt']++;
        continue;
    }
    if (count($cats) > 1) {
        $integrity['morethanonecatattempt']++;
    }
    foreach ($cats as $cat) {
        if ((int) $cat->userid !== (int) $attempt->userid) {
            $integrity['catattemptofanother']++;
        }
        $rows = $DB->get_records('local_catquiz_progress', ['attemptid' => $cat->id], 'id ASC', 'id, userid, component');
        $integrity['progress'] += count($rows);
        if (count($rows) > 1) {
            $integrity['morethanoneprogress']++;
        }
        foreach ($rows as $row) {
            if (
                (int) $row->userid !== (int) $attempt->userid
                || !in_array($row->component, \local_catquiz\catquiz::component_names('mod_adaptivequiz'), true)
            ) {
                $integrity['progressofanother']++;
            }
        }
    }
}
[$insqlp, $paramsp] = $DB->get_in_or_equal(array_keys($attempts));
$integrity['progresswithoutcatattempt'] = (int) $DB->count_records_sql(
    "SELECT COUNT(1) FROM {local_catquiz_progress} p
      WHERE p.attemptid NOT IN (SELECT ca.id FROM {local_catquiz_attempts} ca)
        AND p.userid IN (SELECT a.userid FROM {adaptivequiz_attempt} a WHERE a.id $insqlp)",
    $paramsp
);

// Sessions: the files of this run (file sessions, the default).
$sessiondir = $CFG->dataroot . '/sessions';
$sessions = [];
foreach (glob($sessiondir . '/sess_*') ?: [] as $file) {
    if (filemtime($file) >= $since) {
        $sessions[] = filesize($file) / 1024;
    }
}

echo json_encode([
    'since' => $since,
    'attempts' => count($attempts),
    'traces' => count($traces),
    'requestids' => count(array_filter(array_unique($requestids))),
    'cold' => $cold,
    'kinds' => $kinds,
    'spans' => $spans,
    'usages' => $usages,
    'integrity' => $integrity,
    'sessionkb' => local_catquiz_load_stats($sessions),
    'memorylimit' => ini_get('memory_limit'),
], JSON_PRETTY_PRINT) . "\n";
