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
use local_catquiz\external\render_feedback_tab;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedbacksettings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Feedback tabs loaded when opened (issue #85).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\output\attemptfeedback
 * @covers \local_catquiz\external\render_feedback_tab
 */
final class lazy_feedback_test extends advanced_testcase {
    /** @var array Calls of get_feedback() by generator name. */
    public static array $built = [];

    /**
     * A generator with fixed output and configurable audiences, data and teacher permission.
     *
     * @param string $name
     * @param array $options dependency, audiences, hasdata, teacher.
     * @return feedbackgenerator
     */
    private function generator(string $name, array $options = []): feedbackgenerator {
        return new class ($name, $options) extends feedbackgenerator {
            /** @var string */
            private string $name;
            /** @var array */
            private array $options;

            /**
             * Constructor.
             *
             * @param string $name
             * @param array $options
             */
            public function __construct(string $name, array $options) {
                parent::__construct(new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB), new feedback_helper());
                $this->name = $name;
                $this->options = $options;
            }

            /**
             * Fixed feedback, counted.
             *
             * @param array $feedbackdata
             * @return array
             */
            public function get_feedback(array $feedbackdata): array {
                lazy_feedback_test::$built[] = $this->name;
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
                return 'Heading ' . $this->name;
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
                return $this->options['dependency'] ?? self::DEPENDS_ON_RESULT;
            }

            /**
             * Audiences.
             *
             * @return array
             */
            public function get_audiences(): array {
                return $this->options['audiences'] ?? [self::AUDIENCE_STUDENT];
            }

            /**
             * Whether the attempt data suffice.
             *
             * @param array $feedbackdata
             * @return bool
             */
            public function has_data_for(array $feedbackdata): bool {
                return $this->options['hasdata'] ?? true;
            }

            /**
             * Teacher permission.
             *
             * @param array $feedbackdata
             * @return bool
             */
            public function may_show_teacher_feedback(array $feedbackdata): bool {
                return $this->options['teacher'] ?? false;
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
     * The generators of a typical page.
     *
     * @param bool $teacher Whether the user may see teacher feedback.
     * @return feedbackgenerator[]
     */
    private function generators(bool $teacher): array {
        return [
            $this->generator('customscalefeedback'),
            $this->generator('comparetotestaverage'),
            $this->generator('graphicalsummary', ['dependency' => feedbackgenerator::DEPENDS_ON_ATTEMPT]),
            $this->generator('personabilities', ['hasdata' => false]),
            $this->generator('debuginfo', [
                'dependency' => feedbackgenerator::DIAGNOSTIC,
                'audiences' => [feedbackgenerator::AUDIENCE_TEACHER],
                'teacher' => $teacher,
            ]),
        ];
    }

    /**
     * Runs generate_feedback().
     *
     * @param array $generators
     * @param bool $valid
     * @param bool $lazy
     * @return array
     */
    private function generate(array $generators, bool $valid, bool $lazy): array {
        $reflection = new \ReflectionClass(attemptfeedback::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('generate_feedback');
        $method->setAccessible(true);
        return $method->invoke($instance, $generators, [
            'attemptid' => 0,
            'contextid' => 1,
            'customscalefeedback_abilities' => [5 => ['value' => 0.3, 'toreport' => true, 'primary' => true]],
            'nbyscale' => [5 => $valid ? 4 : 0],
        ], $lazy);
    }

    /**
     * Lazy: only the main feedback is built; every other tab is offered empty.
     */
    public function test_lazy_page_builds_only_the_main_feedback(): void {
        $this->resetAfterTest();
        self::$built = [];

        $context = $this->generate($this->generators(true), true, true);

        $this->assertSame(['customscalefeedback'], self::$built, 'Only the main feedback is built with the page.');
        $student = array_column($context['studentfeedback'], null, 'generatorname');
        $this->assertSame(['customscalefeedback', 'comparetotestaverage', 'graphicalsummary'], array_keys($student));
        $this->assertSame('1', $student['graphicalsummary']['lazy']);
        $this->assertSame('', $student['graphicalsummary']['content']);
        $this->assertSame('Heading graphicalsummary', $student['graphicalsummary']['heading']);
        $lazyteacher = array_values(array_filter($context['teacherfeedback'], fn($tab) => !empty($tab['lazy'])));
        $this->assertSame(['debuginfo'], array_column($lazyteacher, 'generatorname'));
        $this->assertSame('teacher', $lazyteacher[0]['audience']);
    }

    /**
     * No teacher tab without the permission; no tab for a generator lacking its data.
     */
    public function test_lazy_tabs_respect_permission_and_data(): void {
        $this->resetAfterTest();

        $context = $this->generate($this->generators(false), true, true);

        $lazyteacher = array_filter($context['teacherfeedback'] ?? [], fn($tab) => !empty($tab['lazy']));
        $this->assertSame([], $lazyteacher, 'No teacher tab without the permission.');
        $this->assertNotContains('personabilities', array_column($context['studentfeedback'], 'generatorname'));
    }

    /**
     * Invalid result: tabs that read the result are not even offered (issue #120 still holds).
     */
    public function test_lazy_tabs_follow_the_validity_rule(): void {
        $this->resetAfterTest();
        self::$built = [];

        $context = $this->generate($this->generators(true), false, true);
        $student = array_column($context['studentfeedback'], 'generatorname');

        $this->assertSame('novalidresult', $student[0]);
        $this->assertContains('graphicalsummary', $student);
        $this->assertNotContains('comparetotestaverage', $student);
    }

    /**
     * Off: everything is built with the page, as before.
     */
    public function test_eager_page_is_unchanged(): void {
        $this->resetAfterTest();
        self::$built = [];

        $this->generate($this->generators(true), true, false);

        $this->assertSame(
            ['customscalefeedback', 'comparetotestaverage', 'graphicalsummary', 'personabilities', 'debuginfo'],
            self::$built
        );
    }

    /**
     * One tab on its own: the generator asked for, its audience, the validity rule.
     */
    public function test_single_feedback(): void {
        $this->resetAfterTest();

        $single = function (bool $valid, string $name, string $audience, int $played = 5): array {
            $feedback = $this->getMockBuilder(attemptfeedback::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['load_feedbackdata', 'get_feedback_generators_for_teststrategy', 'get_progress'])
                ->getMock();
            $feedback->attemptid = 42;
            $feedback->teststrategy = LOCAL_CATQUIZ_STRATEGY_LOWESTSUB;
            $progress = $this->getMockBuilder(\local_catquiz\teststrategy\progress::class)
                ->onlyMethods(['get_quiz_settings', 'get_num_playedquestions'])
                ->getMock();
            $progress->method('get_quiz_settings')->willReturn((object) [
                'maxquestionsgroup' => (object) ['catquiz_minquestions' => 3],
            ]);
            $progress->method('get_num_playedquestions')->willReturn($played);
            $feedback->method('get_progress')->willReturn($progress);
            $feedback->method('load_feedbackdata')->willReturn([
                'attemptid' => 42, 'contextid' => 1, 'teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
                'customscalefeedback_abilities' => [5 => ['value' => 0.3, 'toreport' => true, 'primary' => true]],
                'nbyscale' => [5 => $valid ? 4 : 0],
            ]);
            $feedback->method('get_feedback_generators_for_teststrategy')->willReturn($this->generators(true));
            return $feedback->get_single_feedback($name, $audience);
        };

        $this->assertSame('student graphicalsummary', $single(true, 'graphicalsummary', 'student')['content']);
        $this->assertSame('teacher debuginfo', $single(true, 'debuginfo', 'teacher')['content']);
        $this->assertSame([], $single(true, 'debuginfo', 'student'), 'A teacher-only generator has no student tab.');
        $this->assertSame([], $single(true, 'customscalefeedback', 'student'), 'The main feedback is part of the page.');
        $this->assertSame([], $single(false, 'comparetotestaverage', 'student'), 'Reads the result: nothing for an invalid one.');
        $this->assertSame('student graphicalsummary', $single(false, 'graphicalsummary', 'student')['content']);
        $this->assertSame([], $single(true, 'nosuchgenerator', 'student'));
        // Below the minimum (one of three questions): the quiz history and the export stay (issue #120).
        $this->assertSame('student graphicalsummary', $single(false, 'graphicalsummary', 'student', 1)['content']);
        $this->assertSame('teacher debuginfo', $single(false, 'debuginfo', 'teacher', 1)['content']);
        $this->assertSame([], $single(false, 'comparetotestaverage', 'student', 1));
    }

    /**
     * The page carries the attempt into every lazy tab: the tab loads its content by it.
     *
     * The attempt-finished page and the review page render export_for_template() directly; the
     * attempt id was not part of it, so every lazy region asked for attempt "" and failed.
     */
    public function test_page_carries_the_attempt_into_lazy_tabs(): void {
        global $PAGE;
        $this->resetAfterTest();
        set_config('lazyfeedbacktabs', 1, 'local_catquiz');

        $feedback = $this->getMockBuilder(attemptfeedback::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load_feedbackdata', 'get_feedback_generators_for_teststrategy', 'get_progress'])
            ->getMock();
        $feedback->attemptid = 42;
        $feedback->teststrategy = LOCAL_CATQUIZ_STRATEGY_LOWESTSUB;
        $progress = $this->getMockBuilder(\local_catquiz\teststrategy\progress::class)
            ->onlyMethods(['get_quiz_settings', 'get_num_playedquestions'])
            ->getMock();
        $progress->method('get_quiz_settings')->willReturn((object) []);
        $progress->method('get_num_playedquestions')->willReturn(5);
        $feedback->method('get_progress')->willReturn($progress);
        $feedback->method('load_feedbackdata')->willReturn([
            'attemptid' => 42, 'contextid' => 1, 'teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'customscalefeedback_abilities' => [5 => ['value' => 0.3, 'toreport' => true, 'primary' => true]],
            'nbyscale' => [5 => 4],
        ]);
        $feedback->method('get_feedback_generators_for_teststrategy')->willReturn($this->generators(false));

        $output = $PAGE->get_renderer('core');
        $html = $output->render_from_template('local_catquiz/attemptfeedback', $feedback->export_for_template($output));

        preg_match_all('/data-region="local_catquiz-lazyfeedback" data-attemptid="([^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches[1], 'The page offers lazy tabs.');
        $this->assertSame(['42'], array_values(array_unique($matches[1])));
    }

    /**
     * The endpoint: the owner gets the tab, others need the feedback permission, teacher tabs the
     * teacher permission - also for a hidden activity.
     */
    public function test_endpoint_access(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $owner = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $attemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => $adaptivequiz->id, 'userid' => $owner->id, 'uniqueid' => 0, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => 2, 'difficultysum' => 0, 'standarderror' => 0,
            'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        set_coursemodule_visible(get_coursemodule_from_instance('adaptivequiz', $adaptivequiz->id)->id, 0);
        \course_modinfo::clear_instance_cache();

        $call = fn(string $audience) => render_feedback_tab::execute($attemptid, 'graphicalsummary', $audience);

        $this->setUser($owner);
        $this->assertArrayHasKey('html', $call('student'));
        $this->setUser($teacher);
        $this->assertArrayHasKey('html', $call('teacher'));

        foreach ([[$other, 'student'], [$owner, 'teacher']] as [$user, $audience]) {
            $this->setUser($user);
            try {
                $call($audience);
                $this->fail('Access must be refused.');
            } catch (\required_capability_exception $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * The endpoint does not hold the session lock.
     */
    public function test_endpoint_is_read_only(): void {
        global $CFG;
        $functions = [];
        require($CFG->dirroot . '/local/catquiz/db/services.php');

        $this->assertTrue($functions['local_catquiz_render_feedback_tab']['readonlysession'] ?? false);
        $this->assertSame('read', $functions['local_catquiz_render_feedback_tab']['type']);
    }
}
