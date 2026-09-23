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
 * Regression test: the cron must close attempts on the authoritative path.
 *
 * Issue #5. The task used to call local_catquiz\local\attempt\attempt::complete(),
 * which only flips attemptstate/attemptstopcriteria. It never stamped the
 * immutable timefinished and never invoked the CAT model's
 * post_complete_attempt_callback, so a cron-closed attempt skipped
 * attempt_finalizer::finalize() entirely - neither the end time nor the result
 * (and with it resultvalid) were persisted. Browser, administrative and cron
 * completion must produce consistent data.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\task\cancel_expired_attempts
 */

namespace local_catquiz\task;

use advanced_testcase;
use ReflectionMethod;

/**
 * Guards the cron completion path.
 *
 * @package    local_catquiz
 * @covers \local_catquiz\task\cancel_expired_attempts
 */
final class cancel_expired_attempts_path_test extends advanced_testcase {
    /**
     * The task must not carry a second completion mechanism of its own.
     *
     * A cron-closed attempt has to run through adaptivequiz_complete_attempt(),
     * which stamps timefinished exactly once and calls the catmodel callback that
     * triggers the finaliser.
     *
     * @return void
     */
    public function test_task_uses_the_authoritative_completion_function(): void {
        $this->resetAfterTest(true);

        $source = file_get_contents(
            (new \ReflectionClass(cancel_expired_attempts::class))->getFileName()
        );

        $this->assertStringContainsString(
            'adaptivequiz_complete_attempt(',
            $source,
            'The cron must close attempts through the authoritative completion function.'
        );
        // The local shortcut must be gone: it never stamps timefinished and never
        // reaches the finaliser.
        $this->assertStringNotContainsString(
            '$attempt->complete(',
            $source,
            'The cron must not use its own completion shortcut.'
        );
    }

    /**
     * The authoritative completion function exists and invokes the catmodel
     * callback that runs the finaliser.
     *
     * @return void
     */
    public function test_authoritative_completion_function_reaches_the_catmodel(): void {
        global $CFG;
        $this->resetAfterTest(true);

        require_once($CFG->dirroot . '/mod/adaptivequiz/locallib.php');
        $this->assertTrue(
            function_exists('adaptivequiz_complete_attempt'),
            'mod_adaptivequiz must provide the authoritative completion function.'
        );

        $reflection = new \ReflectionFunction('adaptivequiz_complete_attempt');
        $source = implode(
            '',
            array_slice(
                file($reflection->getFileName()),
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1
            )
        );

        // The completion timestamp is stamped once and never overwritten ...
        $this->assertStringContainsString('timefinished', $source);
        // ... and the CAT model is handed the completed attempt so that it can run
        // its idempotent finaliser.
        $this->assertStringContainsString('post_complete_attempt_callback', $source);
    }

    /**
     * The finaliser refuses to invent an end time - the cron therefore has to
     * deliver a real one.
     *
     * @return void
     */
    public function test_finalizer_requires_an_authoritative_end_time(): void {
        $this->resetAfterTest(true);

        $method = new ReflectionMethod(
            \local_catquiz\local\attempt\attempt_finalizer::class,
            'finalize'
        );
        $source = implode(
            '',
            array_slice(
                file($method->getFileName()),
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1
            )
        );

        $this->assertStringNotContainsString(
            '$finishedat = time();',
            $source,
            'Finalisation must never fabricate an end time.'
        );
    }
}
