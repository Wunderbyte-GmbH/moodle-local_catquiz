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
 * Issue #146: what a response costs in the session, before and after the pool left it.
 *
 * Before, the hydrated item pool was in the session cache 'adaptivequizattempt' and every question
 * handed out read it and wrote it back whole. After, the pool is in the application cache 'runtimepool';
 * the session holds when the person saw which question - two numbers a question.
 *
 * For every pool size and number of questions handed out the script reports, for both: bytes in the
 * session, bytes written per response, time per response and the additional peak memory of the updates.
 * "Before" replays the earlier lines literally; "after" uses catscale::update_testitem(). The pool rows
 * are synthetic, with the columns of the lean runtime pool.
 *
 *   php local/catquiz/cli/measure_session_pool.php --pools=100,500,1000 --questions=10,50,100,150
 *
 * Development tool, not shipped (.gitattributes).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['pools' => '100,500,1000', 'questions' => '10,50,100,150', 'help' => false], ['h' => 'help']);
if ($options['help']) {
    echo "php local/catquiz/cli/measure_session_pool.php --pools=100,500,1000 --questions=10,50,100,150\n";
    exit(0);
}
$pools = array_map('intval', explode(',', $options['pools']));
$checkpoints = array_map('intval', explode(',', $options['questions']));
$max = max($checkpoints);

\core\session\manager::set_user(get_admin());

/**
 * A synthetic pool row, with the columns of the lean runtime pool.
 *
 * @param int $id
 * @return stdClass
 */
function local_catquiz_measure_row(int $id): stdClass {
    $row = new stdClass();
    foreach (array_diff(\local_catquiz\catquiz::pool_columns(), \local_catquiz\catquiz::DISPLAYONLY_POOL_COLUMNS) as $column) {
        $row->$column = null;
    }
    $row->id = $id;
    $row->componentid = $id;
    $row->itemid = $id;
    $row->label = 'SIM' . $id;
    $row->idnumber = 'SIM' . $id;
    $row->qtype = 'multichoice';
    $row->catscaleid = 1 + $id % 7;
    $row->model = 'raschbirnbaum';
    $row->difficulty = sin($id);
    $row->discrimination = 1.2;
    $row->guessing = 0.0;
    $row->json = '{"difficulty":' . sin($id) . ',"discrimination":1.2}';
    $row->status = 4;
    $row->usable = 1;
    $row->attempts = $id % 40;
    return $row;
}

/**
 * Size of a cached value as the cache stores it.
 *
 * @param mixed $value
 * @return int
 */
function local_catquiz_measure_bytes($value): int {
    return $value === false ? 0 : strlen(serialize($value));
}

$session = cache::make('local_catquiz', 'adaptivequizattempt');
$rows = [];
foreach ($pools as $size) {
    $pool = [];
    for ($id = 1; $id <= $size; $id++) {
        $pool[$id] = local_catquiz_measure_row($id);
    }

    // Before: the pool in the session, read and written back whole for every question.
    $session->purge();
    $key = 'testitems_1_1_1_0';
    $session->set($key, $pool);
    $time = 0.0;
    $written = 0;
    $peak = memory_get_peak_usage();
    $base = memory_get_usage();
    for ($n = 1; $n <= $max; $n++) {
        $start = microtime(true);
        $testitems = $session->get($key);
        $question = $testitems[$n % $size + 1];
        $question->userlastattempttime = time();
        $testitems[$question->id] = $question;
        $session->set($key, $testitems);
        $time += microtime(true) - $start;
        $written += local_catquiz_measure_bytes($testitems);
        unset($testitems);
        if (in_array($n, $checkpoints, true)) {
            $rows[$size][$n]['before'] = [
                local_catquiz_measure_bytes($session->get($key)), $written / $n, 1000 * $time / $n,
                max(0, memory_get_peak_usage() - $peak) + max(0, memory_get_usage() - $base),
            ];
        }
    }

    // After: the pool in its own cache, the session holds the statistics of the person.
    $session->purge();
    \local_catquiz\local\attempt_cache::upgrade_session_layout();
    \local_catquiz\catscale::renew_user_question_stats((int) $USER->id);
    $time = 0.0;
    $written = 0;
    $peak = memory_get_peak_usage();
    $base = memory_get_usage();
    for ($n = 1; $n <= $max; $n++) {
        $question = clone $pool[$n % $size + 1];
        $question->userlastattempttime = time();
        $start = microtime(true);
        \local_catquiz\catscale::update_testitem(1, $question, 1);
        $time += microtime(true) - $start;
        $stats = \local_catquiz\catscale::user_question_stats((int) $USER->id);
        $written += local_catquiz_measure_bytes($stats);
        if (in_array($n, $checkpoints, true)) {
            $bytes = 0;
            foreach (['userquestionstatsgeneration', 'sessionlayout'] as $other) {
                $bytes += local_catquiz_measure_bytes($session->get($other));
            }
            $rows[$size][$n]['after'] = [
                local_catquiz_measure_bytes($stats) + $bytes, $written / $n, 1000 * $time / $n,
                max(0, memory_get_peak_usage() - $peak) + max(0, memory_get_usage() - $base),
            ];
        }
    }
    $session->purge();
}

printf(
    "%-6s %-6s | %-46s | %-46s\n",
    'Pool',
    'Fragen',
    'vorher: Session-B / B je Antwort / ms / Speicher',
    'nachher: Session-B / B je Antwort / ms / Speicher'
);
$format = fn(array $v) => sprintf(
    '%10s / %10s / %6.2f / %9s',
    number_format($v[0], 0, ',', '.'),
    number_format($v[1], 0, ',', '.'),
    $v[2],
    number_format($v[3], 0, ',', '.')
);
foreach ($rows as $size => $byquestions) {
    foreach ($byquestions as $n => $row) {
        printf("%-6d %-6d | %s | %s\n", $size, $n, $format($row['before']), $format($row['after']));
    }
}
