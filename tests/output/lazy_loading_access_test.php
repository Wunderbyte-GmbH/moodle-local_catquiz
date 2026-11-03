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
use local_catquiz\external\feedback_tab_clicked;
use local_catquiz\external\render_feedback_tab;
use local_catquiz\external\render_statistics_chart;
use local_catquiz\local\access\feedback_access;
use local_catquiz\local\statistics\feedback_page_meter;
use local_catquiz\output\catquizstatistics;
use local_catquiz\teststrategy\feedback_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/tests/fixtures/feedback_page_fixture.php');

/**
 * Who may load what later, and what loading leaves behind (issue #85).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\external\render_feedback_tab
 * @covers \local_catquiz\external\render_statistics_chart
 * @covers \local_catquiz\local\access\feedback_access
 * @covers \local_catquiz\teststrategy\feedback_helper
 * @covers \local_catquiz\output\attemptfeedback
 */
final class lazy_loading_access_test extends advanced_testcase {
    use \local_catquiz\tests\feedback_page_fixture;

    /**
     * A CAT manager without any role in the course: the role that may open every result page.
     *
     * Not local/catquiz:view_users_feedback, only the right to manage CATquiz - and to enter courses
     * without being enrolled, which the result page needs as well (require_login of the course).
     *
     * @return \stdClass
     */
    private function cat_manager(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'catmanager']);
        assign_capability('local/catquiz:canmanage', CAP_ALLOW, $roleid, \context_system::instance()->id);
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $user;
    }

    /**
     * Two groups in separate groups mode, a non-editing teacher in group A, a student in each.
     *
     * @return array [teacher, student A, student B, attempt of A, attempt of B]
     */
    private function separate_groups(): array {
        $this->create_feedback_scope();
        $course = $this->fixturecourse;
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $a = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $b = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $a->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupb->id, 'userid' => $b->id]);
        $attempta = $this->create_completed_attempt($a, time() - 120);
        $attemptb = $this->create_completed_attempt($b, time() - 60);
        $this->forget_feedback_caches();
        return [$teacher, $a, $b, $attempta, $attemptb];
    }

    /**
     * Review of issue #85: the CAT manager may open every result page - and its tabs.
     *
     * The endpoint required local/catquiz:view_users_feedback in the activity and ignored the CAT
     * manager, whom feedback_access lets see every result.
     */
    public function test_cat_manager_loads_the_tabs_of_any_attempt(): void {
        $this->resetAfterTest();
        $this->create_feedback_scope(NOGROUPS);
        $student = $this->getDataGenerator()->create_and_enrol($this->fixturecourse, 'student');
        $attempt = $this->create_completed_attempt($student, time() - 60);
        $this->setUser($this->cat_manager());

        $result = render_feedback_tab::execute($attempt, 'learningprogress', 'student');

        $this->assertArrayHasKey('html', $result);
        $this->assertNotSame('', $result['html']);
        $this->resetDebugging();
    }

    /**
     * Separate groups: a teacher of group A loads nothing of group B - neither a tab nor a collapsed attempt.
     */
    public function test_separate_groups_limit_the_endpoint(): void {
        $this->resetAfterTest();
        [$teacher, , $b, $attempta, $attemptb] = $this->separate_groups();
        $this->setUser($teacher);
        $context = \context_module::instance(get_coursemodule_from_instance('adaptivequiz', $this->fixturequiz->id)->id);

        $this->assertFalse(has_capability('moodle/site:accessallgroups', $context), 'The fixture: a teacher without it.');
        $this->assertFalse(feedback_access::can_view_user_in_groups($context, (int) $b->id));

        $this->assertArrayHasKey('html', render_feedback_tab::execute($attempta, 'learningprogress', 'student'));
        foreach (['learningprogress', render_feedback_tab::WHOLE_ATTEMPT] as $generator) {
            feedback_page_meter::new_request();
            try {
                render_feedback_tab::execute($attemptb, $generator, 'student');
                $this->fail("$generator of an attempt of group B must be refused.");
            } catch (\moodle_exception $e) {
                $this->assertSame('error:noreviewpermission', $e->errorcode);
            }
        }
        $this->resetDebugging();
    }

    /**
     * Separate groups: the shortcode catquizfeedback lists the teacher's groups only.
     */
    public function test_separate_groups_limit_the_feedback_list(): void {
        global $COURSE, $DB, $CFG;
        $this->resetAfterTest();
        [$teacher, , , $attempta] = $this->separate_groups();
        $this->setUser($teacher);
        $COURSE = $this->fixturecourse;
        set_config('lazyfeedbacktabs', 1, 'local_catquiz');

        $output = feedback_helper::get_feedback_data(
            ['numberofattempts' => 10, 'courseid' => $this->fixturecourse->id],
            \context_course::instance($this->fixturecourse->id),
            $teacher,
            $this->fixturecourse,
            $DB,
            $CFG
        );

        $this->assertSame([$attempta], array_map(fn($a) => (int) $a['attemptid'], $output['attempt']));
        $this->resetDebugging();
    }

    /**
     * Read only: the endpoint writes nothing to the session - no feedback snapshot, no other key.
     *
     * With $CFG->enable_read_only_sessions the AJAX request runs without the session lock, and what it
     * writes to the session is lost. The tab used to store the feedback snapshot of the attempt there.
     */
    public function test_endpoint_writes_nothing_to_the_session(): void {
        global $CFG, $SESSION;
        $this->resetAfterTest();
        $CFG->enable_read_only_sessions = 1;
        $this->create_feedback_scope();
        $student = $this->getDataGenerator()->create_and_enrol($this->fixturecourse, 'student');
        $attempt = $this->create_completed_attempt($student, time() - 60);
        $this->setUser($student);
        // What every request of a logged-in user has already done (multi-factor login, strings).
        render_feedback_tab::execute($attempt, 'personabilities', 'student');
        $this->forget_feedback_caches();

        $cache = \cache::make('local_catquiz', 'adaptivequizattempt');
        $before = serialize($SESSION);
        foreach (['learningprogress', render_feedback_tab::WHOLE_ATTEMPT] as $generator) {
            feedback_page_meter::new_request();
            render_feedback_tab::execute($attempt, $generator, 'student');
        }

        $this->assertFalse($cache->get('feedbackdata_' . $attempt), 'No feedback snapshot in the session cache.');
        $this->assertSame($before, serialize($SESSION), 'The session is the same as before.');
        $this->resetDebugging();
    }

    /**
     * Parallel requests: the endpoints of a page hold no session lock (issue #85).
     *
     * Moodle releases the lock of an AJAX request only when every function of it declares
     * readonlysession. The tab, its log event and the charts are sent at the same moment; one write
     * function among them made the others wait.
     */
    public function test_endpoints_hold_no_session_lock(): void {
        global $CFG, $SESSION;
        $this->resetAfterTest();
        $functions = [];
        require($CFG->dirroot . '/local/catquiz/db/services.php');
        $names = [
            'local_catquiz_render_feedback_tab',
            'local_catquiz_render_statistics_chart',
            'local_catquiz_feedback_tab_clicked',
        ];
        foreach ($names as $name) {
            $this->assertTrue($functions[$name]['readonlysession'] ?? false, "$name declares readonlysession.");
        }
        $this->assertSame('read', $functions['local_catquiz_render_statistics_chart']['type']);

        // The log event of a tab changes nothing in the session either: the lock is not needed.
        $this->create_feedback_scope();
        $student = $this->getDataGenerator()->create_and_enrol($this->fixturecourse, 'student');
        $attempt = $this->create_completed_attempt($student, time() - 60);
        $this->setUser($student);
        feedback_tab_clicked::execute($attempt, 'learningprogress', 'Progress');
        $before = serialize($SESSION);
        feedback_tab_clicked::execute($attempt, 'learningprogress', 'Progress');
        $this->assertSame($before, serialize($SESSION));
    }

    /**
     * The statistics endpoint: the rule of the shortcode, the scope of the test.
     */
    public function test_statistics_endpoint_access(): void {
        $this->resetAfterTest();
        $this->create_feedback_scope();
        $student = $this->getDataGenerator()->create_and_enrol($this->fixturecourse, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->fixturecourse, 'editingteacher');
        $this->create_completed_attempt($student, time() - 60);
        $scope = ['courseid' => (int) $this->fixturecourse->id, 'testid' => 0, 'scaleid' => $this->fixturescaleid];

        $this->setUser($teacher);
        foreach (array_keys(catquizstatistics::CHARTS) as $chart) {
            feedback_page_meter::new_request();
            $result = render_statistics_chart::execute($scope, $chart);
            $this->assertStringContainsString('data-chartname="' . $chart . '"', $result['html']);
        }

        // A test brings its scale: another one is refused.
        feedback_page_meter::new_request();
        try {
            render_statistics_chart::execute(
                ['testid' => (int) $this->fixturequiz->id, 'scaleid' => $this->fixturescaleid + 1000] + $scope,
                catquizstatistics::CHART_ABILITYPROFILE
            );
            $this->fail('The scale of another test.');
        } catch (\invalid_parameter_exception $e) {
            $this->addToAssertionCount(1);
        }

        $this->setUser($student);
        feedback_page_meter::new_request();
        $this->expectException(\required_capability_exception::class);
        render_statistics_chart::execute($scope, catquizstatistics::CHART_ABILITYPROFILE);
    }

    /**
     * The first rendering of the statistics builds no chart: only empty regions, whatever the attempts.
     *
     * Before, the shortcode built all five charts - the population of attempts, the histograms and the
     * learning progress - for a page that shows one tab.
     */
    public function test_statistics_shortcode_builds_no_chart_at_first(): void {
        global $COURSE, $PAGE;
        $this->resetAfterTest();
        $this->create_feedback_scope();
        $teacher = $this->getDataGenerator()->create_and_enrol($this->fixturecourse, 'editingteacher');
        $COURSE = $this->fixturecourse;
        $PAGE->set_url('/');
        set_config('lazyfeedbacktabs', 1, 'local_catquiz');
        $args = ['globalscale' => $this->fixturescaleid, 'courseid' => $this->fixturecourse->id];

        $queries = [];
        foreach ([0, 20] as $k) {
            for ($i = 0; $i < $k; $i++) {
                $student = $this->getDataGenerator()->create_and_enrol($this->fixturecourse, 'student');
                $this->create_completed_attempt($student, time() - 1000 + $i);
            }
            $this->setUser($teacher);
            $this->forget_feedback_caches();
            feedback_page_meter::render_statistics_shortcode($args);
            $this->forget_feedback_caches();
            $m = feedback_page_meter::measure(fn() => feedback_page_meter::render_statistics_shortcode($args));
            $queries[$k] = $m['queries'];
            $regions = feedback_page_meter::lazy_regions($m['html'])['statistics'];
            $this->assertSame(array_keys(catquizstatistics::CHARTS), array_keys($regions));
            $this->assertStringNotContainsString('chart-area', $m['html'], 'No chart is built with the page.');
        }
        $this->assertSame($queries[0], $queries[20], 'The page costs the same with or without attempts.');

        // Off: every chart is built with the page, as before.
        set_config('lazyfeedbacktabs', 0, 'local_catquiz');
        $html = feedback_page_meter::render_statistics_shortcode($args);
        $this->assertStringContainsString('chart-area', $html);
        $this->resetDebugging();
    }
}
