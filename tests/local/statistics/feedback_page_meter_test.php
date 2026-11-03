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

namespace local_catquiz\local\statistics;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/tests/fixtures/feedback_page_fixture.php');

/**
 * What the feedback pages cost with 0, 20 and 100 earlier attempts of a person (issue #85).
 *
 * The same measurement as cli/measure_feedback_page.php, on a fixture. With LOCAL_CATQUIZ_MEASURE=1 in the
 * environment the numbers are printed (they are the ones in doc/statistics-population.md).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\statistics\feedback_page_meter
 * @covers \local_catquiz\teststrategy\feedback_helper
 * @covers \local_catquiz\output\attemptfeedback
 */
final class feedback_page_meter_test extends advanced_testcase {
    use \local_catquiz\tests\feedback_page_fixture;

    /** @var int[] Numbers of earlier attempts measured. */
    private const KS = [0, 20, 100];

    /**
     * People with K earlier attempts each and one latest attempt.
     *
     * @return array K => [user, latest adaptivequiz_attempt.id]
     */
    private function people(): array {
        $people = [];
        $start = time() - 200 * DAYSECS;
        foreach (self::KS as $k) {
            $user = $this->getDataGenerator()->create_and_enrol($this->fixturecourse, 'student');
            for ($i = 0; $i < $k; $i++) {
                $this->create_completed_attempt($user, $start + $i * HOURSECS, 0.1 * ($i % 7));
            }
            $people[$k] = [$user, $this->create_completed_attempt($user, time() - 60, 0.4)];
        }
        return $people;
    }

    /**
     * Measures the result page and the shortcode for every K.
     *
     * @param bool $lazy
     * @return array K => ['page' => measurement, 'shortcode' => measurement]
     */
    private function measure_all(bool $lazy): array {
        global $COURSE, $PAGE;
        set_config('lazyfeedbacktabs', $lazy ? 1 : 0, 'local_catquiz');
        $people = $this->people();
        $COURSE = $this->fixturecourse;
        $PAGE->set_url('/local/catquiz/show_attemptfeedback.php');
        $PAGE->set_context(\context_course::instance($this->fixturecourse->id));
        $results = [];
        foreach ($people as $k => [$user, $latest]) {
            $this->setUser($user);
            $this->forget_feedback_caches();
            // Warm-up of what every request has anyway: login, language strings, templates.
            feedback_page_meter::render_result_page($latest, (int) $this->fixturecourse->id);
            $this->forget_feedback_caches();
            $results[$k] = [
                'page' => feedback_page_meter::measure(
                    fn() => feedback_page_meter::render_result_page($latest, (int) $this->fixturecourse->id)
                ),
                'shortcode' => feedback_page_meter::measure(fn() => feedback_page_meter::render_feedback_shortcode([
                    'numberofattempts' => $k + 1,
                    'courseid' => (int) $this->fixturecourse->id,
                ])),
            ];
        }
        $this->resetDebugging();
        if (getenv('LOCAL_CATQUIZ_MEASURE')) {
            foreach ($results as $k => $row) {
                foreach ($row as $name => $m) {
                    fwrite(STDERR, sprintf(
                        "lazy=%d K=%3d %-9s queries=%4d reads=%4d bytes=%7d sessiongrowth=%6d time=%.1fms\n",
                        $lazy,
                        $k,
                        $name,
                        $m['queries'],
                        $m['reads'],
                        $m['bytes'],
                        $m['sessiongrowth'],
                        $m['median']
                    ));
                }
            }
        }
        return $results;
    }

    /**
     * Lazy: the queries and the HTML of the result page and of the shortcode do not grow with K.
     *
     * Before, the shortcode built the feedback of every attempt it listed: one attemptfeedback, its test
     * environment, its progress and its generators per attempt, and a query for the name of each person.
     */
    public function test_cost_does_not_grow_with_the_number_of_attempts(): void {
        $this->resetAfterTest();
        $this->create_feedback_scope();

        $results = $this->measure_all(true);

        foreach (['page', 'shortcode'] as $name) {
            $queries = array_map(fn($row) => $row[$name]['queries'], $results);
            $this->assertLessThanOrEqual(
                2,
                max($queries) - min($queries),
                "$name: queries for K = 0/20/100 " . json_encode($queries)
            );
        }
        // The payload of the shortcode: one header per further attempt, never its feedback.
        $perattempt = ($results[100]['shortcode']['bytes'] - $results[20]['shortcode']['bytes']) / 80;
        $this->assertLessThan(1500, $perattempt, 'Bytes per further attempt: a header and an empty region.');
        $regions = feedback_page_meter::lazy_regions($results[100]['shortcode']['html'])['feedback'];
        $this->assertCount(100, array_filter($regions, fn($r) => $r[1] === 'attempt'), 'Every attempt but the open one.');
    }

    /**
     * Without lazy loading the shortcode builds every attempt: it grows (what the lazy mode removes).
     */
    public function test_eager_cost_grows_with_the_number_of_attempts(): void {
        $this->resetAfterTest();
        $this->create_feedback_scope();

        $results = $this->measure_all(false);

        $this->assertGreaterThan($results[0]['shortcode']['queries'] + 20, $results[20]['shortcode']['queries']);
    }

    /**
     * A tab loaded later shows what the page showed with it built at once.
     */
    public function test_lazy_tab_equals_the_tab_built_with_the_page(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->create_feedback_scope();
        // Peers, so that the learning progress has a chart to compare.
        foreach ([0.1, 0.3, -0.2] as $i => $ability) {
            $peer = $this->getDataGenerator()->create_and_enrol($this->fixturecourse, 'student');
            $this->create_completed_attempt($peer, time() - (4 - $i) * DAYSECS, $ability);
        }
        $user = $this->getDataGenerator()->create_and_enrol($this->fixturecourse, 'student');
        $this->create_completed_attempt($user, time() - 3 * DAYSECS, 0.2);
        $latest = $this->create_completed_attempt($user, time() - 60, 0.4);
        $this->setUser($user);
        $PAGE->set_url('/local/catquiz/show_attemptfeedback.php');

        set_config('lazyfeedbacktabs', 0, 'local_catquiz');
        $this->forget_feedback_caches();
        [$html, $javascript] = feedback_page_meter::render_result_page_with_javascript($latest);
        $eager = feedback_page_meter::eager_panes($html);

        set_config('lazyfeedbacktabs', 1, 'local_catquiz');
        $this->forget_feedback_caches();
        $regions = feedback_page_meter::lazy_regions(feedback_page_meter::render_result_page($latest))['feedback'];
        $this->assertNotEmpty($regions, 'The page offers tabs to load later.');
        $charts = 0;
        foreach ($regions as $key => [$id, $generator, $audience]) {
            $this->assertArrayHasKey("$generator/$audience", $eager, "$key is a tab of the page built at once.");
            [$tabhtml, $tabjavascript] = feedback_page_meter::render_tab_with_javascript($id, $generator, $audience);
            $charts += substr_count($tabhtml, 'chart-area');
            $this->assertSame(
                feedback_page_meter::fingerprint($eager["$generator/$audience"], $javascript),
                feedback_page_meter::fingerprint($tabhtml, $tabjavascript),
                "$key: the same content and chart data, normalised."
            );
        }
        $this->assertGreaterThan(0, $charts, 'The comparison includes the data of charts.');
        $this->resetDebugging();
    }
}
