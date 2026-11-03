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
 * Issue #142: what the feedback snapshots leave in the session cache, before and after the fix.
 *
 * Runs N simulated answers against the session cache 'adaptivequizattempt'. "Before" replays the
 * earlier lines literally (one key per number of played questions, the release computed as
 * `$numplayed = $count >= 1`), "after" uses attemptfeedback's own writer. The payload of a snapshot
 * grows by --bytes per answered question (default 2,000 bytes: a feedback state that carries the
 * progress with its played questions).
 *
 * Reports per checkpoint: snapshots left in the cache and their serialised size.
 *
 *   php local/catquiz/cli/measure_feedback_cache.php --questions=10,50,100,150 [--bytes=2000]
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

[$options] = cli_get_params(['questions' => '10,50,100,150', 'bytes' => 2000, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    echo "php local/catquiz/cli/measure_feedback_cache.php --questions=10,50,100,150 [--bytes=2000]\n";
    exit(0);
}
$checkpoints = array_map('intval', explode(',', $options['questions']));
$bytes = (int) $options['bytes'];
$max = max($checkpoints);

/**
 * A feedback state for n answered questions.
 *
 * @param int $n
 * @param int $bytes
 * @return array
 */
function local_catquiz_measure_payload(int $n, int $bytes): array {
    return ['studentfeedback' => [], 'playedquestions' => array_fill(0, $n, str_repeat('x', $bytes))];
}

/**
 * Snapshots of an attempt still in the cache, and their serialised size.
 *
 * @param \cache $cache
 * @param int $attemptid
 * @param int $upto
 * @return array [count, bytes]
 */
function local_catquiz_measure_left(\cache $cache, int $attemptid, int $upto): array {
    $keys = array_map(fn($n) => sprintf('feedbackdata_%d_%d', $attemptid, $n), range(0, $upto));
    $keys[] = sprintf('feedbackdata_%d', $attemptid);
    $count = 0;
    $size = 0;
    foreach ($cache->get_many($keys) as $value) {
        if ($value !== false) {
            $count++;
            $size += strlen(serialize($value));
        }
    }
    return [$count, $size];
}

/**
 * A progress that only counts played questions.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_catquiz_measure_progress extends \local_catquiz\teststrategy\progress {
    /** @var int Number of played questions. */
    public int $played = 0;

    /**
     * Constructor without the database.
     */
    public function __construct() {
    }

    /**
     * Number of played questions.
     *
     * @return int
     */
    public function get_num_playedquestions() {
        return $this->played;
    }
}

$cache = cache::make('local_catquiz', 'adaptivequizattempt');
$rows = [];

// Before: the earlier lines, literally.
$cache->purge();
for ($played = 0; $played <= $max; $played++) {
    // The release on a cache hit, as it was written.
    if ($numplayed = $played >= 1) {
        $cache->delete(sprintf('feedbackdata_%d_%d', 900001, $numplayed - 1));
    }
    $cache->set(sprintf('feedbackdata_%d_%d', 900001, $played), local_catquiz_measure_payload($played, $bytes));
    if (in_array($played, $checkpoints, true)) {
        $rows[$played]['before'] = local_catquiz_measure_left($cache, 900001, $max);
    }
}

// After: attemptfeedback's writer.
$cache->purge();
$feedback = new \local_catquiz\output\attemptfeedback(900002);
$progress = new local_catquiz_measure_progress();
(new ReflectionProperty($feedback, 'progress'))->setValue($feedback, $progress);
$save = new ReflectionMethod($feedback, 'save_to_cache');
for ($played = 0; $played <= $max; $played++) {
    $progress->played = $played;
    $save->invoke($feedback, local_catquiz_measure_payload($played, $bytes));
    if (in_array($played, $checkpoints, true)) {
        $rows[$played]['after'] = local_catquiz_measure_left($cache, 900002, $max);
    }
}
$cache->purge();

printf("%-10s | %-28s | %-28s\n", 'Fragen', 'vorher: Snapshots / Bytes', 'nachher: Snapshots / Bytes');
foreach ($rows as $n => $row) {
    printf(
        "%-10d | %5d / %20s | %5d / %20s\n",
        $n,
        $row['before'][0],
        number_format($row['before'][1], 0, ',', '.'),
        $row['after'][0],
        number_format($row['after'][1], 0, ',', '.')
    );
}
