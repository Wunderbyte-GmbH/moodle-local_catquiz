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
 * Issue #64: a failed selection has to say which stage emptied the pool.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use ReflectionClass;

/**
 * Guards the diagnostic output of the selection pipeline.
 *
 * An attempt ended after the first question while items demonstrably remained. The
 * pipeline narrows the pool step by step, but only the final outcome was observable -
 * so the decisive question, which stage emptied the pool, could not be answered from
 * the request itself. Counting outside the request does not replace that: by then the
 * numbers no longer match.
 *
 * These tests do not fix the reported behaviour. They pin the diagnosis that makes it
 * decidable, which is what the issue asks for before anything else.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class selection_pipeline_diagnosis_test extends advanced_testcase {
    /**
     * The strategy exposes a candidate count per stage.
     *
     * @return void
     */
    public function test_strategy_reports_stage_counts(): void {
        $this->resetAfterTest();

        $reflection = new ReflectionClass(\local_catquiz\teststrategy\strategy::class);

        $this->assertTrue(
            $reflection->hasMethod('get_stage_counts'),
            'Without this the pipeline gives no account of where the pool was lost.'
        );

        $method = $reflection->getMethod('get_stage_counts');
        $this->assertTrue($method->isPublic(), 'The diagnosis has to be readable from outside.');
        $this->assertSame('array', $method->getReturnType()->getName());
    }

    /**
     * Every narrowing stage of the chain is recorded.
     *
     * A stage that is instrumented but not named would leave exactly the gap the
     * issue describes: the pool is empty and nothing says where.
     *
     * @return void
     */
    public function test_every_narrowing_stage_is_recorded(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/strategy.php'
        );

        foreach (
            [
            'start',
            'add_scale_standarderror',
            'maximumquestionscheck',
            'removeplayedquestions',
            'noremainingquestions',
            'fisherinformation',
            ] as $stage
        ) {
            $this->assertStringContainsString(
                "record_stage('" . $stage . "'",
                $source,
                "The stage $stage narrows the pool and has to be accounted for."
            );
        }
    }

    /**
     * The counts are stored together with the error, not only kept in memory.
     *
     * The reported attempt ended in a request that was over by the time anyone
     * looked. Whatever is not persisted at that moment is gone.
     *
     * @return void
     */
    public function test_stage_counts_are_persisted_with_the_error(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/strategy.php'
        );

        $start = strpos($source, 'private function after_error');
        $this->assertNotFalse($start, 'The error path must exist.');
        $end = strpos($source, "\n    }\n", $start);
        $body = substr($source, $start, $end - $start);

        $this->assertStringContainsString(
            'catquizerror',
            $body,
            'The status itself was already persisted.'
        );
        // The counts used to be written here and only here. The reported abort never
        // reaches this path - the selection returns without an error - so the write
        // moved to persist_stage_counts(), which runs at every exit.
        $this->assertStringContainsString(
            'persist_stage_counts',
            $source,
            'The counts have to be written somewhere that the reported abort reaches.'
        );
    }

    /**
     * Recording does not alter the result that flows through the chain.
     *
     * The instrumentation sits in the selection path, which every question of every
     * running attempt passes through. It must hand on exactly what it received.
     *
     * @return void
     */
    public function test_recording_passes_the_result_through(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/strategy.php'
        );

        $start = strpos($source, 'private function record_stage');
        $this->assertNotFalse($start);
        $end = strpos($source, "\n    }\n", $start);
        $body = substr($source, $start, $end - $start);

        $this->assertStringContainsString(
            'return $result ??',
            $body,
            'The stage result must be handed on unchanged, not replaced.'
        );
        $this->assertStringNotContainsString(
            'unset(',
            $body,
            'Recording must not touch the candidate pool.'
        );
    }
    /**
     * The counts are written on every exit, not only on the error path.
     *
     * Reported from a reproduction run: the attempt stopped after two questions with
     * 22 unplayed items in the pool, and the cache held neither key. The engine row
     * explained why - catquizerror was false. The selection never reported an error,
     * so after_error() never ran, and that was the only place writing the counts.
     *
     * A diagnosis that is absent for exactly the abort it was built for is no
     * diagnosis.
     *
     * @return void
     */
    public function test_counts_are_written_on_every_exit(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/strategy.php'
        );

        $start = strpos($source, 'public function return_next_testitem');
        $this->assertNotFalse($start);
        $end = strpos($source, "\n    /**", $start);
        $body = substr($source, $start, $end - $start);

        // Every way out of the selection has to leave the counts behind.
        preg_match_all('/\n\s+return .+?;/', $body, $returns, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($returns[0], 'The selection must have exits at all.');

        $unwritten = [];
        foreach ($returns[0] as $return) {
            $before = substr($body, max(0, $return[1] - 160), min(160, $return[1]));
            if (!str_contains($before, 'persist_stage_counts')) {
                $unwritten[] = trim($return[0]);
            }
        }

        $this->assertSame(
            [],
            $unwritten,
            'These exits leave no diagnosis behind, which is what made the reported '
                . 'abort undiagnosable.'
        );
    }

    /**
     * Writing does not depend on an error having been reported.
     *
     * @return void
     */
    public function test_writing_is_independent_of_the_error_path(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/strategy.php'
        );

        $start = strpos($source, 'private function after_error');
        $this->assertNotFalse($start);
        $end = strpos($source, "\n    }\n", $start);
        $body = substr($source, $start, $end - $start);

        $this->assertStringNotContainsString(
            "cache->set('catquizstagecounts'",
            $body,
            'Writing only here is what left the reported abort without any counts.'
        );
    }
    /**
     * Each stage records its own candidate count, not the previous stage's.
     *
     * The chain used to read
     * record_stage('add_scale_standarderror', $this->maximumquestionscheck()).
     * PHP evaluates arguments before the call, so maximumquestionscheck ran first and
     * its result was filed under add_scale_standarderror. Every count in the trace was
     * shifted by one stage.
     *
     * For issue #64 that is not a cosmetic problem: the whole point of the trace is to
     * name the stage that discards the last candidate. A shifted trace names its
     * predecessor, and the investigation goes to the wrong filter with data that looks
     * authoritative.
     *
     * @return void
     */
    public function test_each_stage_records_its_own_count(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/strategy.php'
        );

        // A record_stage call that also runs the next filter is the defect itself.
        // Comment lines are dropped first: the fix is explained in a comment that
        // quotes the old call, and matching that would make the test fail on its own
        // documentation.
        $code = implode("\n", array_filter(
            explode("\n", $source),
            fn($line) => !preg_match('/^\s*(\/\/|\*|\/\*)/', $line)
        ));

        $this->assertDoesNotMatchRegularExpression(
            '/record_stage\(\s*\x27\w+\x27\s*,\s*\$this->\w+\(\)/',
            $code,
            'Passing the next filter as an argument files its result under the '
                . 'previous stage name, because PHP evaluates arguments first.'
        );

        // Every stage of the chain still has to appear, or the trace has holes.
        foreach (
            [
            'start',
            'add_scale_standarderror',
            'maximumquestionscheck',
            'removeplayedquestions',
            'noremainingquestions',
            'fisherinformation',
            ] as $stage
        ) {
            $this->assertStringContainsString(
                "record_stage('$stage')",
                $source,
                "The stage $stage has to record a count of its own."
            );
        }
    }
    /**
     * The question pool is loaded before the selection chain, on every path.
     *
     * first_question_selector() used to be the only place that filled
     * $context['questions'], and it returns early on three paths: not the first
     * question, the classic strategy, and an existing ability when
     * firstquestion_use_existing_data is on. The chain then ran with 'questions'
     * unset and noremainingquestions did count(null).
     *
     * In PHP 8 that is a TypeError - an Error, not an Exception, so the catch around
     * the chain does not hold it. The attempt died after question one, which is the
     * symptom reported in issue #64. Reproduced on a production instance: all six
     * strategies failed identically at noremainingquestions.php:49.
     *
     * The guard against a regression is the load, not a null check. A `?? []` in
     * noremainingquestions would turn the fatal into "no remaining questions" and so
     * produce the reported symptom instead of removing it.
     *
     * @return void
     */
    public function test_pool_is_loaded_before_the_chain(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/strategy.php'
        );

        $load = strpos($source, 'new questions_loader()');
        $this->assertNotFalse(
            $load,
            'Nothing loads the pool. The chain then depends on first_question_selector '
                . 'having taken a path that happens to fill it.'
        );

        // Before the chain, not inside it: the first stage already reads the pool.
        $chain = strpos($source, "record_stage('start')");
        $this->assertNotFalse($chain);
        $this->assertLessThan(
            $chain,
            $load,
            'The pool has to exist before the first stage runs.'
        );
    }

    /**
     * noremainingquestions still counts a real array rather than a fallback.
     *
     * A null-coalescing default there would hide the missing pool and report "no
     * remaining questions" - the very outcome issue #64 describes.
     *
     * @return void
     */
    public function test_no_silent_fallback_for_the_pool(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/preselect_task/'
                . 'noremainingquestions.php'
        );

        $this->assertStringNotContainsString(
            "\$context['questions'] ?? []",
            $source,
            'A fallback here turns a missing pool into "no remaining questions" and '
                . 'reproduces the defect instead of fixing it.'
        );
    }
}
