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

namespace local_catquiz\teststrategy;

use advanced_testcase;
use local_catquiz\teststrategy\strategy\classicalcat;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * CLASSIC reports each scale as valid or not by the reporting thresholds alone (issue #135).
 *
 * The minimum number of questions per scale and the maximum standard error decide, scale by scale,
 * whether a result is reported as valid. They are reporting thresholds: the fixed form is played in
 * full whatever they say (strategy_semantics_test::test_classic_plays_the_whole_form).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\strategy\classicalcat
 */
final class classic_reporting_thresholds_test extends advanced_testcase {
    /**
     * A finished attempt over root 10 with subscales 11, 12, 13 and its stored progress.
     *
     * Scale 11: four answers, mixed. Scale 12: two answers, mixed. Scale 13: four answers, mixed.
     *
     * @return array feedback data as the strategy gets it
     */
    private function attempt(): array {
        global $DB, $USER;
        $this->setAdminUser();
        $now = time();
        $adaptiveid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 1, 'userid' => $USER->id, 'uniqueid' => 0, 'attemptstate' => 'completed',
            'attemptstopcriteria' => '', 'questionsattempted' => 10, 'difficultysum' => 0,
            'standarderror' => 1, 'measure' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $USER->id, 'attemptid' => $adaptiveid, 'component' => 'mod_adaptivequiz',
            'contextid' => 9, 'status' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $progress = progress::load($adaptiveid, 'mod_adaptivequiz', 9, (object) []);
        $played = [];
        $byscale = [];
        $responses = [];
        $id = 100;
        foreach ([11 => 4, 12 => 2, 13 => 4] as $scaleid => $n) {
            for ($i = 0; $i < $n; $i++) {
                $q = (object) ['id' => ++$id, 'catscaleid' => $scaleid, 'is_pilot' => false, 'fisherinformation' => []];
                $played[$q->id] = $q;
                $byscale[$scaleid][] = $q;
                $byscale[10][] = $q;
                $responses[$q->id] = ['questionid' => $q->id, 'fraction' => (float) ($i % 2)];
            }
        }
        $state = ['playedquestions' => $played, 'playedquestionsbyscale' => $byscale, 'responses' => $responses];
        foreach ($state as $name => $value) {
            $property = new \ReflectionProperty($progress, $name);
            $property->setAccessible(true);
            $property->setValue($progress, $value);
        }
        $progress->save();

        return [
            'attemptid' => $adaptiveid,
            'contextid' => 9,
            'catscaleid' => 10,
            'progress' => $progress,
            // Scale 13 is not precise enough.
            'se' => [10 => 0.3, 11 => 0.4, 12 => 0.4, 13 => 0.9],
        ];
    }

    /**
     * Each scale is judged on its own: one valid, one below the minimum, one above the error.
     */
    public function test_each_scale_is_reported_by_its_own_thresholds(): void {
        $this->resetAfterTest();
        $feedbackdata = $this->attempt();
        $settings = new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_CLASSIC);
        $settings->nminscale = 3;
        $settings->semax = 0.5;
        $settings->fraction = 0.5;

        $abilities = [];
        foreach ([10, 11, 12, 13] as $scaleid) {
            $abilities[$scaleid] = ['value' => 0.1 * $scaleid];
        }
        $report = (new classicalcat())->select_scales_for_report($settings, $abilities, $feedbackdata);

        $this->assertTrue(empty($report[10]['excluded']), 'Root: ten answers, error 0.3.');
        $this->assertTrue(!empty($report[10]['primary']));
        $this->assertTrue(empty($report[11]['excluded']), 'Scale 11: four answers, error 0.4.');
        $this->assertTrue(!empty($report[12]['excluded']), 'Scale 12: two answers, below the minimum of 3.');
        $this->assertSame(['nminscaledefined' => 3, 'nscalecurrent' => 2], $report[12]['error']['nminscale']);
        $this->assertTrue(!empty($report[13]['excluded']), 'Scale 13: error 0.9, above 0.5.');
        $this->assertSame(0.9, $report[13]['error']['se']['securrent']);
        foreach ($report as $scaleid => $entry) {
            $this->assertTrue($entry['toreport'], "Scale $scaleid is reported, valid or not.");
        }
    }

    /**
     * Without reporting thresholds every measured scale is valid.
     */
    public function test_without_thresholds_every_scale_is_valid(): void {
        $this->resetAfterTest();
        $feedbackdata = $this->attempt();
        $settings = new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_CLASSIC);
        $settings->nminscale = null;
        $settings->semax = null;
        $settings->fraction = 0.5;

        $abilities = array_fill_keys([10, 11, 12, 13], ['value' => 0.0]);
        $report = (new classicalcat())->select_scales_for_report($settings, $abilities, $feedbackdata);

        foreach ($report as $scaleid => $entry) {
            $this->assertTrue(empty($entry['excluded']), "Scale $scaleid");
        }
    }
}
