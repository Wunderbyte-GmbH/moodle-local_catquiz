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
 * Issues #59 and #62: the feedback path must not abort the question selection.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use ReflectionMethod;

/**
 * Guards the two ways an incomplete attempt state used to break the first question.
 *
 * Both issues share a shape: a value that is legitimately absent at the start of an
 * attempt is read without a check, the resulting error travels up through
 * strategy::return_next_testitem(), and the learner sees "couldn't define the first
 * question, the quiz is possibly misconfigured". The message points at the
 * configuration while the cause sits in the feedback path.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class feedback_path_robustness_test extends advanced_testcase {
    /**
     * Issue #62: the debug row survives a first question without a predecessor.
     *
     * catquiz.php removes lastquestion from the attempt data outright, so the key is
     * missing systematically rather than occasionally. On a normal instance
     * (array) null is [], but with DEBUG_DEVELOPER Moodle turns the notice into an
     * exception - so debug information was unobtainable exactly on the instances set
     * up to collect it.
     *
     * @return void
     */
    public function test_debuginfo_tolerates_a_missing_last_question(): void {
        $this->resetAfterTest();

        $source = file_get_contents(
            __DIR__ . '/../classes/teststrategy/feedbackgenerator/debuginfo.php'
        );

        // The two fields must be guarded like their neighbours.
        $this->assertStringContainsString(
            "(array) (\$newdata['lastquestion'] ?? [])",
            $source,
            'lastquestion is absent on the first question of every attempt.'
        );
        $this->assertStringContainsString(
            "\$newdata['lastmiddleware'] ?? self::NA",
            $source,
            'lastmiddleware is absent in the same situation.'
        );
        $this->assertStringNotContainsString(
            "(array) \$newdata['lastquestion'],",
            $source,
            'The unguarded access must be gone.'
        );
    }

    /**
     * Issue #59: no ability yet must not produce a null scale id.
     *
     * attemptfeedback::update_data() returns early when no person abilities exist and
     * never sets 'catscales'. array_key_first([]) is null, which reached
     * catscale::__construct() and failed there - one level below the mistake.
     *
     * @return void
     */
    public function test_ability_range_is_never_asked_for_a_null_scale(): void {
        $this->resetAfterTest();

        $source = file_get_contents(__DIR__ . '/../classes/teststrategy/feedbackgenerator.php');

        $this->assertStringContainsString(
            "\$newdata['catscales'] ?? []",
            $source,
            'The key is absent before any ability has been estimated.'
        );
        $this->assertStringNotContainsString(
            'get_ability_range(array_key_first($catscales))',
            $source,
            'Passing array_key_first() of a possibly empty array is what produced null.'
        );
    }

    /**
     * The signature states the expectation, so a wrong call reports its own location.
     *
     * @return void
     */
    public function test_ability_range_declares_an_int_parameter(): void {
        $this->resetAfterTest();

        $method = new ReflectionMethod(
            \local_catquiz\teststrategy\feedback_helper::class,
            'get_ability_range'
        );
        $parameters = $method->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertNotNull($parameters[0]->getType(), 'The parameter must be typed.');
        $this->assertSame(
            'int',
            $parameters[0]->getType()->getName(),
            'Without the declaration a null call fails one level deeper, in catscale.'
        );
        $this->assertFalse(
            $parameters[0]->getType()->allowsNull(),
            'Accepting null would defeat the purpose of the declaration.'
        );
    }

    /**
     * Every caller passes an int, so the tightened signature breaks nothing.
     *
     * @return void
     */
    public function test_all_callers_pass_an_integer(): void {
        global $CFG;

        $this->resetAfterTest();

        // Only the helper's own calls are of interest; catscale has a method of the
        // same name that takes no argument. Matching the receiver keeps the two
        // apart, and a nested cast or a line break in between would defeat a pattern
        // that tries to capture the whole argument.
        $pattern = '/(?:feedbackhelper|this)->get_ability_range\(\s*([^;]*?)\s*\)/s';

        $root = $CFG->dirroot . '/local/catquiz/classes';
        $suspicious = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            preg_match_all($pattern, $source, $matches);
            foreach ($matches[1] as $argument) {
                // An explicit cast, an int-typed helper, or a ternary that yields one
                // are all fine. What is not fine is array_key_first() on a value that
                // may be empty - the call that produced null.
                if (str_contains($argument, 'array_key_first($catscales)')) {
                    $suspicious[] = basename($file->getPathname()) . ': ' . $argument;
                }
            }
        }

        $this->assertSame(
            [],
            $suspicious,
            'array_key_first() on a possibly empty array is what produced the null call.'
        );
    }
    /**
     * A half-built attemptfeedback is inert, not explosive.
     *
     * Issue #64: the constructor returns early when no attempt is found and again when
     * the attempt has no test environment. contextid, courseid and teststrategy are
     * typed properties without defaults, so on those paths the object was left
     * half-built and the next read threw "must not be accessed before initialization".
     *
     * Observed in the selection pipeline, where strategy.php builds this object after
     * each answer: the exception ended the run and the learner saw no further
     * question. The symptom - "the attempt stops after Q1" - points nowhere near the
     * feedback code that produced it.
     *
     * @return void
     */
    public function test_attemptfeedback_without_test_environment_does_not_throw(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // An attempt id that resolves to no test environment takes the second early
        // return - the path that used to leave the object unusable.
        $feedback = new \local_catquiz\output\attemptfeedback(999999, 0);

        $this->assertSame(0, $feedback->contextid);
        $this->assertSame(0, $feedback->courseid);
        $this->assertSame(0, $feedback->teststrategy);
    }
}
