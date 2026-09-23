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
 * Issue #10: bind feedback output to a valid result.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\output\attemptfeedback;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedbacksettings;
use ReflectionMethod;

/**
 * Gating of the feedback output (issue #10).
 *
 * Covers the three core acceptance criteria without a browser:
 *   - a generator without data produces no tab (no_data() is empty);
 *   - an attempt without a reportable scale shows exactly one central notice
 *     and no per-scale feedback;
 *   - a valid attempt keeps its generator feedback and shows no notice.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\teststrategy\feedbackgenerator::no_data
 * @covers     \local_catquiz\teststrategy\feedback_helper::has_reportable_result
 * @covers     \local_catquiz\teststrategy\feedback_helper::get_reportable_scales
 * @covers     \local_catquiz\output\attemptfeedback::generate_feedback
 */
final class feedback_gating_test extends advanced_testcase {
    /**
     * Builds a stub generator with a controllable student result.
     *
     * @param string $name       The generator name.
     * @param array  $student    The student feedback the stub returns.
     * @param array  $required   Required context keys the stub declares.
     * @return feedbackgenerator
     */
    private function make_generator(string $name, array $student, array $required = []): feedbackgenerator {
        $settings = new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB);
        $helper = new feedback_helper();
        return new class ($settings, $helper, $name, $student, $required) extends feedbackgenerator {
            /** @var string */
            private $name;
            /** @var array */
            private $student;
            /** @var array */
            private $required;
            /**
             * Constructor for the stub generator.
             *
             * @param feedbacksettings $settings
             * @param feedback_helper $helper
             * @param string $name
             * @param array $student
             * @param array $required
             */
            public function __construct($settings, $helper, string $name, array $student, array $required) {
                parent::__construct($settings, $helper);
                $this->name = $name;
                $this->student = $student;
                $this->required = $required;
            }
            /**
             * Returns the stubbed student feedback.
             *
             * @param array $data
             * @return array
             */
            protected function get_studentfeedback(array $data): array {
                return $this->student;
            }
            /**
             * Returns empty teacher feedback.
             *
             * @param array $data
             * @return array
             */
            protected function get_teacherfeedback(array $data): array {
                return [];
            }
            /**
             * Returns the declared required context keys.
             *
             * @return array
             */
            public function get_required_context_keys(): array {
                return $this->required;
            }
            /**
             * Returns the generator heading.
             *
             * @return string
             */
            public function get_heading(): string {
                return 'Heading ' . $this->name;
            }
            /**
             * Returns the generator name.
             *
             * @return string
             */
            public function get_generatorname(): string {
                return $this->name;
            }
            /**
             * Loads no data for the stub.
             *
             * @param int $attemptid
             * @param array $existingdata
             * @param array $newdata
             * @return array|null
             */
            public function load_data(int $attemptid, array $existingdata, array $newdata): ?array {
                return [];
            }
        };
    }

    /**
     * Invokes the private assembly with the given generators and data.
     *
     * @param array $generators
     * @param array $feedbackdata
     * @return array
     */
    private function assemble(array $generators, array $feedbackdata): array {
        $af = (new \ReflectionClass(attemptfeedback::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(attemptfeedback::class, 'generate_feedback');
        $method->setAccessible(true);
        return $method->invoke($af, $generators, $feedbackdata);
    }

    /**
     * The base no_data() fallback must be empty so no tab is produced.
     *
     * @return void
     */
    public function test_no_data_is_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->make_generator('empty', ['heading' => 'H', 'content' => 'C']);
        $method = new ReflectionMethod(feedbackgenerator::class, 'no_data');
        $method->setAccessible(true);
        $this->assertSame([], $method->invoke($gen), 'no_data() must return an empty result.');
    }

    /**
     * A generator returning an empty result yields no student tab.
     *
     * This is the mechanism issue #10 relies on: no_data() is empty and the
     * assembly skips empty results, so a generator with nothing to say produces
     * no tab instead of a stray "not available" block.
     *
     * @return void
     */
    public function test_generator_without_data_produces_no_tab(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        // The generator has nothing to report (empty result).
        $gen = $this->make_generator('empty', []);
        $context = $this->assemble([$gen], [
            'attemptid' => 0,
            'contextid' => \context_system::instance()->id,
            // A reportable scale so the central notice does not fire here.
            'customscalefeedback_abilities' => ['5' => ['toreport' => true, 'value' => 0.1]],
        ]);
        $this->assertArrayNotHasKey('studentfeedback', $context, 'An empty generator result must not create a tab.');
    }

    /**
     * With no reportable scale, exactly one central notice replaces the scale
     * feedback and carries the rejection reason.
     *
     * @return void
     */
    public function test_invalid_result_shows_single_central_notice(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        // A generator that WOULD produce a scale tab.
        $gen = $this->make_generator('customscalefeedback', ['heading' => 'Scale', 'content' => 'Scale feedback']);
        $context = $this->assemble([$gen], [
            'attemptid' => 0,
            'contextid' => \context_system::instance()->id,
            // No toreport scale -> invalid result. Include an exclusion reason.
            'customscalefeedback_abilities' => [
                '5' => ['excluded' => true, 'error' => ['nminscale' => ['nmin' => 3]]],
            ],
        ]);
        $this->assertArrayHasKey('studentfeedback', $context);
        $this->assertCount(1, $context['studentfeedback'], 'Exactly one central notice must be shown.');
        $notice = $context['studentfeedback'][0];
        $this->assertSame('novalidresult', $notice['generatorname']);
        $this->assertStringContainsString(
            get_string('feedbacknovalidresult', 'local_catquiz'),
            $notice['content'],
            'The notice must state that no valid result could be determined.'
        );
        $this->assertStringNotContainsString(
            'Scale feedback',
            $notice['content'],
            'The per-scale feedback must not appear when the result is invalid.'
        );
    }

    /**
     * For an invalid result, student-facing generators (peer comparison, learning
     * progress, ...) contribute no student feedback at all: the student sees only
     * the single central notice. This verifies the gating happens before, not
     * after, the generator output is assembled.
     *
     * @return void
     */
    public function test_invalid_result_skips_student_generators(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // A tracker the peer generator flips when it is actually executed.
        $executed = new \ArrayObject(['peer' => false]);
        $settings = new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB);
        $helper = new feedback_helper();
        $peer = new class ($settings, $helper, $executed) extends feedbackgenerator {
            /** @var \ArrayObject */
            private $executed;
            /**
             * Constructor.
             *
             * @param feedbacksettings $settings
             * @param feedback_helper $helper
             * @param \ArrayObject $executed
             */
            public function __construct($settings, $helper, \ArrayObject $executed) {
                parent::__construct($settings, $helper);
                $this->executed = $executed;
            }
            /**
             * Records execution and returns a would-be peer tab.
             *
             * @param array $data
             * @return array
             */
            protected function get_studentfeedback(array $data): array {
                $this->executed['peer'] = true;
                return ['heading' => 'Peers', 'content' => 'Peer comparison'];
            }
            /**
             * Returns empty teacher feedback.
             *
             * @param array $data
             * @return array
             */
            protected function get_teacherfeedback(array $data): array {
                return [];
            }
            /**
             * Returns the declared required context keys.
             *
             * @return array
             */
            public function get_required_context_keys(): array {
                return [];
            }
            /**
             * Returns the generator heading.
             *
             * @return string
             */
            public function get_heading(): string {
                return 'Peers';
            }
            /**
             * Returns the generator name.
             *
             * @return string
             */
            public function get_generatorname(): string {
                return 'comparetotestaverage';
            }
            /**
             * Loads no data for the stub.
             *
             * @param int $attemptid
             * @param array $existingdata
             * @param array $newdata
             * @return array|null
             */
            public function load_data(int $attemptid, array $existingdata, array $newdata): ?array {
                return [];
            }
        };
        $scale = $this->make_generator('customscalefeedback', ['heading' => 'Scale', 'content' => 'Scale feedback']);

        $context = $this->assemble([$scale, $peer], [
            'attemptid' => 0,
            'contextid' => \context_system::instance()->id,
            // No toreport scale -> invalid result.
            'customscalefeedback_abilities' => [
                '5' => ['excluded' => true, 'error' => ['nminscale' => ['nmin' => 3]]],
            ],
        ]);
        $this->assertArrayHasKey('studentfeedback', $context);
        // Only the notice, and the peer generator was never executed.
        $this->assertCount(1, $context['studentfeedback'], 'Student generators must be skipped for an invalid result.');
        $this->assertSame('novalidresult', $context['studentfeedback'][0]['generatorname']);
        $this->assertFalse($executed['peer'], 'The peer comparison must not run on an invalid result.');
    }

    /**
     * A valid result keeps the generator feedback and shows no central notice.
     *
     * @return void
     */
    public function test_valid_result_keeps_generator_feedback(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->make_generator('customscalefeedback', ['heading' => 'Scale', 'content' => 'Scale feedback']);
        $context = $this->assemble([$gen], [
            'attemptid' => 0,
            'contextid' => \context_system::instance()->id,
            'customscalefeedback_abilities' => ['5' => ['toreport' => true, 'value' => 0.1]],
        ]);
        $this->assertArrayHasKey('studentfeedback', $context);
        $this->assertCount(1, $context['studentfeedback']);
        $this->assertSame('customscalefeedback', $context['studentfeedback'][0]['generatorname']);
        $this->assertSame('Scale feedback', $context['studentfeedback'][0]['content']);
    }

    /**
     * The validity helper reflects toreport / excluded / hidden correctly.
     *
     * @return void
     */
    public function test_reportable_helper(): void {
        $this->assertTrue(feedback_helper::has_reportable_result(['5' => ['toreport' => true]]));
        $this->assertFalse(feedback_helper::has_reportable_result(['5' => ['toreport' => true, 'excluded' => true]]));
        $this->assertFalse(feedback_helper::has_reportable_result(['5' => ['toreport' => true, 'hidden' => true]]));
        $this->assertFalse(feedback_helper::has_reportable_result(['5' => ['value' => 0.1]]));
        $this->assertFalse(feedback_helper::has_reportable_result([]));
    }
}
