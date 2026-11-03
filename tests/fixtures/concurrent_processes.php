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
 * Work that really runs at the same time, in processes of its own (issues #95, #101, #146).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs a piece of work in several forked processes that start at the same instant.
 *
 * Each process opens a database connection of its own, waits for the common start, runs the work and writes
 * its answer - or the class and message of what it threw - to a file. It then replaces itself with /bin/true,
 * so that nothing of the parent - its connection, its destructors - is touched on exit. The races are real
 * ones, against the real database; nothing is run one after another.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait local_catquiz_concurrent_processes {
    /**
     * Skips the test where processes cannot be forked.
     */
    protected function require_concurrent_processes(): void {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_exec') || !is_executable('/bin/true')) {
            // In CI the concurrency tests are required (audit 18.10.): a skip there would pass for green.
            if (getenv('CATQUIZ_REQUIRE_CONCURRENCY') === '1') {
                $this->fail('CATQUIZ_REQUIRE_CONCURRENCY=1, but pcntl or /bin/true is missing: the concurrency test cannot run.');
            }
            $this->markTestSkipped('Needs pcntl and /bin/true to run requests at the same time.');
        }
    }

    /**
     * Runs the work in several processes at the same instant.
     *
     * @param int $processes
     * @param callable $work Gets the number of the process; returns something json can encode.
     * @return array[] One answer per process, in their order: ['ok' => mixed] or ['error' => string].
     */
    protected function at_the_same_time(int $processes, callable $work): array {
        global $CFG;
        $dir = make_request_directory();
        $start = microtime(true) + 0.4;
        $children = [];
        for ($i = 0; $i < $processes; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Could not fork.');
            }
            if ($pid === 0) {
                try {
                    global $DB;
                    $DB = \moodle_database::get_driver_instance($CFG->dbtype, $CFG->dblibrary);
                    $DB->connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname, $CFG->prefix, $CFG->dboptions);
                    while (microtime(true) < $start) {
                        usleep(200);
                    }
                    $answer = ['ok' => $work($i)];
                } catch (\Throwable $e) {
                    $answer = ['error' => get_class($e) . ': ' . $e->getMessage()];
                }
                file_put_contents("$dir/$i.json", json_encode($answer));
                pcntl_exec('/bin/true');
                exit(0);
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }
        // The reset after the test empties only the tables this process wrote to; what the processes wrote
        // it does not know. Every table counts as written, or their rows would stay for the next tests.
        global $DB;
        foreach ($DB->get_tables(false) as $table) {
            \testing_util::set_table_modified_by_sql('UPDATE ' . $DB->get_prefix() . $table . ' SET');
        }
        $answers = [];
        for ($i = 0; $i < $processes; $i++) {
            $file = "$dir/$i.json";
            $answers[] = is_file($file) ? json_decode(file_get_contents($file), true) : ['error' => 'no answer'];
        }
        return $answers;
    }
}
