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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Which scales each strategy reports in writing (issue #117).
 *
 * The decision runs through several layers: the reporting setting of the quiz, the strategy's
 * toreport/primary/hidden/excluded, the counts of the attempt, the validator and the one written
 * predicate feedback_helper::is_displayable(). This drives the same chain the feedback generator
 * runs, for each of the six strategies, on one set of scales:
 *
 *  100 root; 101 and 102 valid; 103 reporting switched off; 104 below the minimum N;
 *  105 standard error above the maximum; 106 not measured in this attempt, with a value above
 *  every valid one; 107 not measured either, with a value below every valid one (issue #128):
 *  neither may be picked as the greatest strength or the lowest skill gap.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\feedback_helper
 */
final class written_report_matrix_test extends advanced_testcase {
    /** @var array Ability per scale; distinct, so lowest and highest are unambiguous. */
    private const ABILITY = [100 => 0.2, 101 => -1.0, 102 => 1.5, 103 => -2.0, 104 => -3.0, 105 => 3.0, 106 => 2.5, 107 => -5.0];

    /** @var array Standard error per scale. */
    private const SE = [100 => 0.2, 101 => 0.3, 102 => 0.3, 103 => 0.3, 104 => 0.3, 105 => 0.9, 106 => 0.3, 107 => 0.3];

    /** @var array Productive answered items per scale in this attempt. */
    private const N = [101 => 3, 102 => 3, 103 => 3, 104 => 1, 105 => 3, 106 => 0, 107 => 0];

    /**
     * @var array Items shown but not answered. Counted as played, 104 and 106 would reach the
     *      minimum N of 2; counted as answered (the authoritative N), they do not.
     */
    private const SHOWNONLY = [104 => 1, 106 => 2];

    /**
     * Runs the chain of the feedback generator for one strategy and returns the scales the predicate admits.
     *
     * @param int $strategyid
     * @param string $predicate The feedback_helper predicate: is_displayable (written) or
     *      is_feedback_eligible (detail tab); 'routing' for the scales the stored result routes;
     *      'customrange' for the scales whose range feedback text the participant gets.
     * @param int $nmintest Minimum number of items for the whole test.
     * @param int $nminscale Minimum number of items per scale; 0 for none.
     * @param array $fractions Fraction of every answer by scale id. Half credit by default, so each
     *      scale's fraction lies inside (0, 1); all right or all wrong makes a scale invalid (issue #140).
     * @return int[]
     */
    private function written_scales(
        int $strategyid,
        string $predicate = 'is_displayable',
        int $nmintest = 1,
        int $nminscale = 2,
        array $fractions = []
    ): array {
        $this->setAdminUser();

        // The progress of an attempt that answered these items, each scale its own questions; the
        // root holds all of them.
        // The attempt of the activity behind the progress, as every real attempt has one.
        global $DB;
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => 97001, 'instance' => 1, 'userid' => 2, 'uniqueid' => 597001, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => 3, 'difficultysum' => 0, 'standarderror' => 0.3,
            'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $progress = progress::load(97001, 'mod_adaptivequiz', 9, (object) []);
        $byscale = [];
        $responses = [];
        $played = [];
        $qid = 5000;
        foreach (self::N as $scaleid => $n) {
            $shown = $n + (self::SHOWNONLY[$scaleid] ?? 0);
            for ($i = 0; $i < $shown; $i++) {
                $q = (object) ['id' => ++$qid, 'catscaleid' => $scaleid, 'is_pilot' => false, 'fisherinformation' => []];
                $byscale[$scaleid][$q->id] = $q;
                $byscale[100][$q->id] = $q;
                $played[$q->id] = $q;
                if ($i < $n) {
                    $responses[$q->id] = ['questionid' => $q->id, 'fraction' => (float) ($fractions[$scaleid] ?? 0.5)];
                }
            }
        }
        $state = ['responses' => $responses, 'playedquestions' => $played,
            // A progress with played questions always has a last one.
            'lastquestion' => end($played) ?: null, 'playedquestionsbyscale' => $byscale];
        foreach ($state as $name => $value) {
            $property = new \ReflectionProperty($progress, $name);
            $property->setAccessible(true);
            $property->setValue($progress, $value);
        }
        $progress->save();

        $personabilities = [];
        foreach (self::ABILITY as $scaleid => $value) {
            $personabilities[$scaleid] = ['value' => $value];
        }
        $quizsettings = new \stdClass();
        foreach (array_keys(self::ABILITY) as $scaleid) {
            $quizsettings->{'catquiz_scalereportcheckbox_' . $scaleid} = $scaleid === 103 ? 0 : 1;
        }

        $settings = new feedbacksettings($strategyid);
        $settings->nminscale = $nminscale;
        $settings->nmintest = $nmintest;
        $settings->semax = 0.5;
        $settings->fraction = 0.6;
        $feedbackdata = [
            'attemptid' => 97001,
            'contextid' => 9,
            'catscaleid' => 100,
            'se' => self::SE,
            'progress' => $progress,
        ];

        // The order of feedbackgenerator::select_scales_for_report(): reporting setting, then strategy.
        $personabilities = $settings->filter_excluded_scales($personabilities, $quizsettings);
        $personabilities = \local_catquiz\teststrategy\info::get_teststrategy($strategyid)
            ->select_scales_for_report($settings, $personabilities, $feedbackdata, 100);

        if ($predicate === 'customrange') {
            return $this->custom_range_scales($personabilities, $quizsettings, $feedbackdata);
        }
        if ($predicate === 'routing') {
            // Routing reads the stored result of the finished attempt (issue #129), not the
            // feedback generators: file what the strategy selected, as the attempt would.
            global $DB;
            $DB->set_field('local_catquiz_attempts', 'contextid', 9, ['attemptid' => 97001]);
            $DB->set_field('local_catquiz_attempts', 'json', json_encode([
                'personabilities_abilities' => $personabilities,
                'se' => self::SE,
            ]), ['attemptid' => 97001]);
            $routed = array_keys(\local_catquiz\output\attemptfeedback::routing_scores(97001));
            sort($routed);
            return $routed;
        }

        $result = feedback_helper::build_attempt_result($personabilities, $feedbackdata);
        $written = array_values(array_filter(
            array_keys(self::ABILITY),
            fn($scaleid) => feedback_helper::$predicate($result, $scaleid)
        ));
        sort($written);

        return $written;
    }

    /**
     * The strategies whose written report the issue fixes.
     *
     * @return array
     */
    public static function strategies(): array {
        return [
            'classical cat: every valid one' => [LOCAL_CATQUIZ_STRATEGY_CLASSIC, [100, 101, 102]],
            'fastest: root only' => [LOCAL_CATQUIZ_STRATEGY_FASTEST, [100]],
            'relevant subscales: every valid one' => [LOCAL_CATQUIZ_STRATEGY_RELSUBS, [100, 101, 102]],
            'all subscales: every valid one' => [LOCAL_CATQUIZ_STRATEGY_ALLSUBS, [100, 101, 102]],
            'lowest skill gap: the selected one' => [LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, [101]],
            'greatest strength: the selected one' => [LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB, [102]],
        ];
    }

    /**
     * Each strategy reports exactly the scales the issue's matrix names.
     *
     * Two valid subscales at once (relevant, all); a scale with reporting off, one below the minimum
     * N, one above the maximum standard error and one not measured never appear; a non-primary scale
     * with toreport does; lowest and highest stay with their one selected scale.
     *
     * @dataProvider strategies
     * @param int $strategyid
     * @param int[] $expected
     */
    public function test_each_strategy_reports_what_it_should(int $strategyid, array $expected): void {
        $this->resetAfterTest();

        $this->assertSame($expected, $this->written_scales($strategyid));
    }

    /**
     * What the detail tab shows per strategy (issue #118).
     *
     * @return array
     */
    public static function detail_tab(): array {
        return [
            'classical cat' => [LOCAL_CATQUIZ_STRATEGY_CLASSIC, [100, 101, 102]],
            'fastest: root only' => [LOCAL_CATQUIZ_STRATEGY_FASTEST, [100]],
            'relevant subscales' => [LOCAL_CATQUIZ_STRATEGY_RELSUBS, [100, 101, 102]],
            'all subscales' => [LOCAL_CATQUIZ_STRATEGY_ALLSUBS, [100, 101, 102]],
            'lowest skill gap: the selected one and every other valid one' => [LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, [100, 101, 102]],
            'greatest strength: the selected one and every other valid one' => [LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB, [100, 101, 102]],
        ];
    }

    /**
     * The detail tab shows every enabled, measured and valid scale - not only the reported ones.
     *
     * toreport and primary decide what the written feedback names; the detail tab shows every valid
     * measurement. Lowest and highest report one scale in writing, yet show the other valid ones
     * here. Reporting off, below the minimum N, above the maximum standard error and not measured
     * stay out. The root is in the list once; the template shows it as the reference, not as a row.
     *
     * @dataProvider detail_tab
     * @param int $strategyid
     * @param int[] $expected
     */
    public function test_the_detail_tab_shows_every_valid_measurement(int $strategyid, array $expected): void {
        $this->resetAfterTest();

        $this->assertSame($expected, $this->written_scales($strategyid, 'is_feedback_eligible'));
    }
    /**
     * CAT: the minimum for the whole test counts answered productive items only (issue #128).
     *
     * 13 items are answered, 3 more were shown and not answered. With a minimum of 14 the test does
     * not reach it - counting shown items, it would, and the root would be reported.
     */
    public function test_cat_minimum_for_the_test_counts_answered_items(): void {
        $this->resetAfterTest();

        $this->assertSame([], $this->written_scales(LOCAL_CATQUIZ_STRATEGY_FASTEST, 'is_displayable', 14));
    }
    /**
     * Without a minimum N, lowest and highest still choose among measured scales only (issue #128).
     *
     * 106 and 107 were not measured in this attempt; their values lie above and below every valid
     * one. With no minimum N configured nothing else excludes them - choosing one of them would
     * leave the attempt without any reported scale.
     */
    public function test_selection_ignores_unmeasured_scales_without_a_minimum(): void {
        $this->resetAfterTest();

        // Without a minimum N, 104 (one answered item, -3.0) is the lowest measured scale.
        $this->assertSame([104], $this->written_scales(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, 'is_displayable', 1, 0));
        \cache::make('local_catquiz', 'adaptivequizattempt')->purge();
        global $DB;
        $DB->delete_records('local_catquiz_progress');
        $DB->delete_records('local_catquiz_attempts');
        $DB->delete_records('adaptivequiz_attempt');
        $this->assertSame([102], $this->written_scales(LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB, 'is_displayable', 1, 0));
    }

    /**
     * The fraction rule, per strategy (issue #140).
     *
     * 101 answered all wrong, 102 all right; the root holds every answer, so its fraction lies
     * inside (0, 1). Each strategy treats both as invalid: neither is reported, and the greatest
     * strength and the lowest skill gap choose among the remaining valid scales. The fraction of the
     * whole test plays no part - it used to, differently in each strategy.
     *
     * @return array
     */
    public static function fraction_rule(): array {
        return [
            'classical cat' => [LOCAL_CATQUIZ_STRATEGY_CLASSIC, [100]],
            'fastest: root only' => [LOCAL_CATQUIZ_STRATEGY_FASTEST, [100]],
            'relevant subscales' => [LOCAL_CATQUIZ_STRATEGY_RELSUBS, [100]],
            'all subscales' => [LOCAL_CATQUIZ_STRATEGY_ALLSUBS, [100]],
            // 101 (-1.0) would be the lowest and 102 (1.5) the highest; with both invalid, the root is
            // the one valid scale left to choose.
            'lowest skill gap: not the all-wrong scale' => [LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, [100]],
            'greatest strength: not the all-right scale' => [LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB, [100]],
        ];
    }

    /**
     * Scales answered all wrong or all right are invalid in every strategy (issue #140).
     *
     * @dataProvider fraction_rule
     * @param int $strategyid
     * @param int[] $expected
     */
    public function test_fraction_rule_in_every_strategy(int $strategyid, array $expected): void {
        $this->resetAfterTest();

        $fractions = [101 => 0.0, 102 => 1.0];
        $written = $this->written_scales($strategyid, 'is_displayable', 1, 2, $fractions);
        $this->assertSame($expected, $written);
    }

    /**
     * Each strategy routes exactly the scales it reports in writing (issue #129).
     *
     * Routing reads the stored per-scale result of the attempt; the predicate is the written one,
     * not scale_result::$valid, which asks for the primary scale. Relevant, all and classic route
     * several scales, lowest and highest their one selected scale, CAT the root; reporting off,
     * below the minimum N, above the maximum SE and not measured route nowhere.
     *
     * @dataProvider strategies
     * @param int $strategyid
     * @param int[] $expected
     */
    public function test_each_strategy_routes_what_it_reports(int $strategyid, array $expected): void {
        $this->resetAfterTest();

        $this->assertSame($expected, $this->written_scales($strategyid, 'routing'));
    }

    /**
     * The scales whose custom range feedback text appears, through customscalefeedback itself.
     *
     * Every scale gets one range over the whole scale and the text "FB<scaleid>"; which texts come
     * out is which scales the range feedback admits.
     *
     * @param array $personabilities As the strategy selected them.
     * @param \stdClass $quizsettings
     * @param array $feedbackdata
     * @return int[]
     */
    private function custom_range_scales(array $personabilities, \stdClass $quizsettings, array $feedbackdata): array {
        $settings = (array) $quizsettings + ['numberoffeedbackoptionsselect' => 1];
        foreach (array_keys(self::ABILITY) as $scaleid) {
            $settings['feedback_scaleid_limit_lower_' . $scaleid . '_1'] = -10;
            $settings['feedback_scaleid_limit_upper_' . $scaleid . '_1'] = 10;
            $settings['feedbackeditor_scaleid_' . $scaleid . '_1'] = 'FB' . $scaleid;
        }
        $generator = new \local_catquiz\teststrategy\feedbackgenerator\customscalefeedback(
            new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB),
            new feedback_helper()
        );
        $reflection = new \ReflectionClass($generator);
        foreach (['testid' => 0, 'mainscale' => 100] as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($generator, $value);
        }
        $method = $reflection->getMethod('get_customscalefeedback_for_abilities_in_range');
        $method->setAccessible(true);
        $text = $method->invoke($generator, $personabilities, $settings, [], $feedbackdata);

        preg_match_all('/FB(\d+)/', $text, $matches);
        $shown = array_map('intval', $matches[1]);
        sort($shown);
        return $shown;
    }

    /**
     * The custom range feedback admits exactly the scales of the written report (issue #128).
     *
     * The range path builds its own attempt result; it must judge the scales as the written
     * feedback does - measured, valid, reported - in every strategy.
     *
     * @dataProvider strategies
     * @param int $strategyid
     * @param int[] $expected
     */
    public function test_custom_range_feedback_follows_the_written_report(int $strategyid, array $expected): void {
        $this->resetAfterTest();

        $this->assertSame($expected, $this->written_scales($strategyid, 'customrange'));
    }
}
