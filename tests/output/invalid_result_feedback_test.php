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

namespace local_catquiz\output;

use advanced_testcase;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage;
use local_catquiz\teststrategy\feedbackgenerator\customscalefeedback;
use local_catquiz\teststrategy\feedbackgenerator\debuginfo;
use local_catquiz\teststrategy\feedbackgenerator\graphicalsummary;
use local_catquiz\teststrategy\feedbackgenerator\learningprogress;
use local_catquiz\teststrategy\feedbackgenerator\personabilities;
use local_catquiz\teststrategy\feedbacksettings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * What an attempt without a valid result still shows (issue #120).
 *
 * Validity used to switch off every feedback generator but the primary one - and with them the
 * quiz history, the question summary and the export. What reads the result stays out; what the
 * attempt contained and the export for authorised users stay.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\output\attemptfeedback
 * @covers \local_catquiz\teststrategy\feedbackgenerator
 */
final class invalid_result_feedback_test extends advanced_testcase {
    /**
     * Each real generator declares what it depends on.
     */
    public function test_generators_declare_their_dependency(): void {
        $expected = [
            customscalefeedback::class => feedbackgenerator::DEPENDS_ON_RESULT,
            comparetotestaverage::class => feedbackgenerator::DEPENDS_ON_GLOBAL,
            personabilities::class => feedbackgenerator::DEPENDS_ON_RESULT,
            learningprogress::class => feedbackgenerator::DEPENDS_ON_ATTEMPT,
            graphicalsummary::class => feedbackgenerator::DEPENDS_ON_ATTEMPT,
            debuginfo::class => feedbackgenerator::DIAGNOSTIC,
        ];
        foreach ($expected as $class => $dependency) {
            $generator = new $class(new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB), new feedback_helper());
            $this->assertSame($dependency, $generator->get_result_dependency(), $class);
        }
    }

    /**
     * A generator that returns fixed feedback, for driving generate_feedback().
     *
     * @param string $name
     * @param string $dependency
     * @return feedbackgenerator
     */
    private function generator(string $name, string $dependency): feedbackgenerator {
        return new class ($name, $dependency) extends feedbackgenerator {
            /** @var string */
            private string $name;
            /** @var string */
            private string $dependency;

            /**
             * Constructor.
             *
             * @param string $name
             * @param string $dependency
             */
            public function __construct(string $name, string $dependency) {
                parent::__construct(new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB), new feedback_helper());
                $this->name = $name;
                $this->dependency = $dependency;
            }

            /**
             * Fixed feedback for both audiences.
             *
             * @param array $feedbackdata
             * @return array
             */
            public function get_feedback(array $feedbackdata): array {
                return [
                    'studentfeedback' => ['heading' => $this->name, 'content' => 'student ' . $this->name],
                    'teacherfeedback' => ['heading' => $this->name, 'content' => 'teacher ' . $this->name],
                ];
            }

            /**
             * Student feedback.
             *
             * @param array $data
             * @return array
             */
            protected function get_studentfeedback(array $data): array {
                return [];
            }

            /**
             * Teacher feedback.
             *
             * @param array $data
             * @return array
             */
            protected function get_teacherfeedback(array $data): array {
                return [];
            }

            /**
             * Required keys.
             *
             * @return array
             */
            public function get_required_context_keys(): array {
                return [];
            }

            /**
             * Heading.
             *
             * @return string
             */
            public function get_heading(): string {
                return $this->name;
            }

            /**
             * Name.
             *
             * @return string
             */
            public function get_generatorname(): string {
                return $this->name;
            }

            /**
             * Dependency.
             *
             * @return string
             */
            public function get_result_dependency(): string {
                return $this->dependency;
            }

            /**
             * Load data.
             *
             * @param int $adaptiveattemptid
             * @param array $existingdata
             * @param array $newdata
             * @return array|null
             */
            public function load_data(int $adaptiveattemptid, array $existingdata, array $newdata): ?array {
                return null;
            }
        };
    }

    /**
     * Runs generate_feedback() with the usual set of generator kinds.
     *
     * The global scale 1 and the subscale 5, which the strategy reports first. Valid: the subscale was
     * measured. Global only: the subscale was not, the global scale was. None: neither.
     *
     * @param string $state valid, global or none
     * @return array The feedback context.
     */
    private function feedback(string $state): array {
        $generators = [
            $this->generator('customscalefeedback', feedbackgenerator::DEPENDS_ON_RESULT),
            $this->generator('comparetotestaverage', feedbackgenerator::DEPENDS_ON_GLOBAL),
            $this->generator('learningprogress', feedbackgenerator::DEPENDS_ON_ATTEMPT),
            $this->generator('graphicalsummary', feedbackgenerator::DEPENDS_ON_ATTEMPT),
            $this->generator('debuginfo', feedbackgenerator::DIAGNOSTIC),
        ];
        $feedbackdata = [
            'attemptid' => 0,
            'catscaleid' => 1,
            'teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'customscalefeedback_abilities' => [
                1 => ['value' => 0.1],
                5 => ['value' => 0.3, 'toreport' => true, 'primary' => true],
            ],
            // Measured or not: without a productive answer a scale has no result.
            'nbyscale' => [1 => $state === 'none' ? 0 : 8, 5 => $state === 'valid' ? 4 : 0],
        ];

        $reflection = new \ReflectionClass(attemptfeedback::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('generate_feedback');
        $method->setAccessible(true);
        return $method->invoke($instance, $generators, $feedbackdata);
    }

    /**
     * The strategy reached no target, but the global scale is valid: "Your result" stays, and the
     * notice says why the target was missed and where the global estimate is (decision of 2026-10-14).
     */
    public function test_global_scale_valid_without_target(): void {
        $this->resetAfterTest();

        $context = $this->feedback('global');
        $student = array_column($context['studentfeedback'], 'generatorname');

        $this->assertSame(
            ['novalidresult', 'comparetotestaverage', 'learningprogress', 'graphicalsummary', 'debuginfo'],
            $student
        );
        $notice = $context['studentfeedback'][0]['content'];
        $this->assertStringContainsString(get_string('feedbacktarget_lowestsub', 'local_catquiz'), $notice);
        $this->assertStringContainsString(get_string('feedbackreason_nmin', 'local_catquiz'), $notice);
        $this->assertStringContainsString(get_string('comparetotestaverage', 'local_catquiz'), $notice);
    }

    /**
     * Not even the global scale is valid: no "Your result", and the notice names the reason in the
     * whole test; quiz history and learning progress stay.
     */
    public function test_invalid_result_keeps_the_attempt_and_the_export(): void {
        $this->resetAfterTest();

        $context = $this->feedback('none');
        $student = array_column($context['studentfeedback'], 'generatorname');
        $teacher = array_column($context['teacherfeedback'], 'generatorname');

        $this->assertSame(
            ['novalidresult', 'learningprogress', 'graphicalsummary', 'debuginfo'],
            $student,
            'The notice comes first; the test generators write for both audiences.'
        );
        $this->assertStringContainsString(
            get_string('feedbacknoglobalresult', 'local_catquiz', (object) [
                'reason' => get_string('feedbackreason_nmin', 'local_catquiz'),
                'historytab' => get_string('quizgraphicalsummary', 'local_catquiz'),
                'progresstab' => get_string('learningprogresstitle', 'local_catquiz'),
            ]),
            $context['studentfeedback'][0]['content']
        );

        $this->assertContains('debuginfo', $teacher, 'The export stays for authorised users.');
        $this->assertContains('customscalefeedback', $teacher, 'Teachers still see the reason.');
        $this->assertNotContains('comparetotestaverage', $teacher);
    }

    /**
     * The real export tab needs no debug storage: it is offered for every attempt (issue #120).
     */
    public function test_export_tab_does_not_need_debug_storage(): void {
        $this->resetAfterTest();
        set_config('store_debug_info', 0, 'local_catquiz');

        $export = new debuginfo(new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB), new feedback_helper());

        $this->assertTrue($export->has_data_for(['attemptid' => 7, 'contextid' => 1]));
    }

    /**
     * Valid: everything is shown, no notice.
     */
    public function test_valid_result_is_unchanged(): void {
        $this->resetAfterTest();

        $student = array_column($this->feedback('valid')['studentfeedback'], 'generatorname');

        $this->assertSame(
            [
                'customscalefeedback', 'comparetotestaverage', 'learningprogress',
                'graphicalsummary', 'debuginfo',
            ],
            $student
        );
    }

    /**
     * One reason only, the first that applies: N, then fraction, then SE (decision of 2026-10-14).
     */
    public function test_one_reason_by_priority(): void {
        $method = new \ReflectionMethod(attemptfeedback::class, 'reason_phrase');
        $method->setAccessible(true);
        $phrase = fn(array $categories) => $method->invoke(null, $categories);

        $this->assertSame(get_string('feedbackreason_nmin', 'local_catquiz'), $phrase(['se', 'allcorrect', 'nmin']));
        $this->assertSame(get_string('feedbackreason_allcorrect', 'local_catquiz'), $phrase(['se', 'allcorrect']));
        $this->assertSame(get_string('feedbackreason_allincorrect', 'local_catquiz'), $phrase(['se', 'allincorrect']));
        $this->assertSame(get_string('feedbackreason_se', 'local_catquiz'), $phrase(['se']));
        $this->assertSame(get_string('feedbackreason_other', 'local_catquiz'), $phrase([]));
    }
}
