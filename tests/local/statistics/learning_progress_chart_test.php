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
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator\learningprogress;
use local_catquiz\teststrategy\feedbacksettings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The learning progress compared to the peers sends no series without values (issue #150, re-audit 23.10.2026).
 *
 * The feedback asked for three attempts of the others, not for valid results: with attempts whose results of the
 * global scale are all invalid every period was empty, the labels too, and two series without values went to the
 * browser - core/chart_series throws "Invalid values received for series.", the error of the live case. The
 * statistics show the same chart and had the check already (catquizstatistics::render_learning_progress()).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\feedbackgenerator\learningprogress::render_chart_for_comparison
 */
final class learning_progress_chart_test extends advanced_testcase {
    /** @var int */
    private int $scaleid = 150150;

    /**
     * Finished attempts with a stored result of the scale.
     *
     * @param array $results [userid, days ago, ability, valid]
     * @return \stdClass[] The attempts, as the feedback reads them.
     */
    private function attempts(array $results): array {
        global $DB;
        $attempts = [];
        foreach ($results as $i => [$userid, $daysago, $ability, $valid]) {
            $end = time() - $daysago * DAYSECS;
            $id = (int) $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => $userid, 'scaleid' => $this->scaleid, 'contextid' => 1, 'courseid' => 1,
                'attemptid' => 150000 + $i, 'component' => 'mod_adaptivequiz', 'instanceid' => 1,
                'teststrategy' => LOCAL_CATQUIZ_STRATEGY_CLASSIC, 'status' => 0, 'starttime' => $end - 600,
                'endtime' => $end, 'json' => '{}', 'timecreated' => $end, 'timemodified' => $end,
            ]);
            $DB->insert_record('local_catquiz_personparams', (object) [
                'userid' => $userid, 'catscaleid' => $this->scaleid, 'contextid' => 1, 'attemptid' => $id,
                'ability' => $ability, 'isvalid' => $valid ? 1 : 0, 'resultsource' => 'current', 'isprimary' => 1,
                'status' => 0, 'timecreated' => $end, 'timemodified' => $end,
            ]);
            $attempts[] = $DB->get_record('local_catquiz_attempts', ['id' => $id]);
        }
        return $attempts;
    }

    /**
     * The chart of the feedback, and the charts the browser gets.
     *
     * @param \stdClass[] $attempts
     * @return array [result of the method, chart data sent]
     */
    private function comparison(array $attempts): array {
        global $PAGE;
        $begin = min(array_map(fn($a) => (int) $a->endtime, $attempts));
        $generator = new learningprogress(new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_CLASSIC), new feedback_helper());
        $property = new \ReflectionProperty(get_class($PAGE->requires), 'amdjscode');
        $property->setValue($PAGE->requires, ['']);
        $out = (new \ReflectionMethod($generator, 'render_chart_for_comparison'))->invoke(
            $generator,
            $attempts,
            1,
            ['id' => $this->scaleid, 'name' => 'Global'],
            feedback_helper::get_timerange_for_attempts($begin, time()),
            [$begin, time()]
        );
        $code = implode("\n", $property->getValue($PAGE->requires));
        preg_match_all('/var data = (\{.*?\}),\s*\n\s*uniqid/s', $code, $m);
        if ($m[1]) {
            $this->assertStringContainsString("'local_catquiz/chart'", $code, 'Drawn by the module that shows a refusal.');
        }
        return [$out, array_map(fn($json) => json_decode($json, true), $m[1])];
    }

    /**
     * Attempts enough, valid results none: no chart, and no series without values for the browser.
     */
    public function test_attempts_without_a_valid_result_send_no_empty_series(): void {
        $this->resetAfterTest();
        $attempts = $this->attempts([[1, 3, 0.5, false], [2, 3, 0.4, false], [3, 2, null, false], [4, 1, -1.0, false]]);

        [$out, $charts] = $this->comparison($attempts);

        foreach ($charts as $chart) {
            foreach ($chart['series'] as $series) {
                $this->assertNotEmpty($series['values'], "Series '{$series['label']}' without values sent to the browser.");
            }
        }
        $this->assertSame([], $charts, 'Nothing to draw.');
        $this->assertSame([], $out);
    }

    /**
     * Counter-check: with valid results the chart is drawn, every series as long as the labels.
     */
    public function test_with_valid_results_the_chart_is_drawn(): void {
        $this->resetAfterTest();
        $attempts = $this->attempts([[1, 3, 0.5, true], [2, 3, 0.4, true], [3, 2, 1.0, true], [4, 1, -1.0, true]]);

        [$out, $charts] = $this->comparison($attempts);

        $this->assertCount(1, $charts);
        $this->assertNotEmpty($charts[0]['labels']);
        foreach ($charts[0]['series'] as $series) {
            $this->assertCount(count($charts[0]['labels']), $series['values']);
        }
        $this->assertArrayHasKey('chart', $out);
    }
}
