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

namespace local_catquiz\local\validation;

use advanced_testcase;
use cache;
use local_catquiz\testenvironment;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The examples of issue #60, each with what the validator has to say - errors, warnings, diagnostics.
 *
 * A root K1 without items of its own and subscales K1.1, K1.2, ... with the number of calibrated items each
 * case gives. Every case lists all its findings: a finding that is not expected fails the case as much as a
 * missing one.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\validation\settings_validator
 * @covers \local_catquiz\testenvironment::assert_consistent
 */
final class settings_matrix_test extends advanced_testcase {
    /** @var int */
    private int $contextid;

    /** @var int */
    private int $root;

    /** @var int[] */
    private array $subs = [];

    /** @var int */
    private int $categoryid;

    /**
     * Builds the tree: one subscale per entry, with that many calibrated items.
     *
     * @param int[] $items
     */
    private function tree(array $items): void {
        global $DB;
        $this->contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Matrix', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 100, 'endtimestamp' => time() + 10000,
            'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
        ]);
        $this->root = $this->scale('K1', 0);
        $this->categoryid = (int) $this->getDataGenerator()->get_plugin_generator('core_question')->create_question_category()->id;
        foreach ($items as $i => $count) {
            $scale = $this->scale('K1.' . ($i + 1), $this->root);
            $this->subs[] = $scale;
            for ($j = 0; $j < $count; $j++) {
                $this->item($scale, -2.0 + $j * 0.3);
            }
        }
        cache::make('local_catquiz', 'runtimepool')->purge();
    }

    /**
     * A scale.
     *
     * @param string $name
     * @param int $parentid
     * @return int
     */
    private function scale(string $name, int $parentid): int {
        global $DB;
        return (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => $name, 'parentid' => $parentid, 'contextid' => $parentid ? null : $this->contextid,
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * A calibrated question in a scale.
     *
     * @param int $scaleid
     * @param float $difficulty
     */
    private function item(int $scaleid, float $difficulty): void {
        global $DB;
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('truefalse', null, ['category' => $this->categoryid]);
        $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $question->id, 'componentname' => 'question', 'catscaleid' => $scaleid,
            'contextid' => $this->contextid, 'status' => LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE,
        ]);
        $paramid = $DB->insert_record('local_catquiz_itemparams', (object) [
            'itemid' => $itemid, 'componentid' => $question->id, 'componentname' => 'question',
            'contextid' => $this->contextid, 'model' => 'raschbirnbaum', 'difficulty' => $difficulty,
            'discrimination' => 1.2, 'guessing' => 0, 'json' => '{}', 'status' => LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY,
            'usable' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);
    }

    /**
     * Settings of a test on the tree, all subscales selected.
     *
     * @param array $case
     * @return stdClass
     */
    private function settings(array $case): stdClass {
        $settings = (object) [
            'catquiz_catscales' => $this->root,
            'catquiz_selectteststrategy' => $case['strategy'],
            'maxquestionsgroup' => (object) [
                'catquiz_minquestions' => $case['min'], 'catquiz_maxquestions' => $case['max'],
            ],
            'maxquestionsscalegroup' => (object) [
                'catquiz_minquestionspersubscale' => $case['minperscale'] ?? 0,
                'catquiz_maxquestionspersubscale' => $case['maxperscale'] ?? 0,
            ],
            'catquiz_standarderrorgroup' => (object) [
                'catquiz_standarderror_min' => $case['semin'] ?? 0.0, 'catquiz_standarderror_max' => 2.0,
            ],
        ];
        foreach ($case['more'] ?? [] as $key => $value) {
            $settings->$key = is_array($value) ? (object) $value : $value;
        }
        foreach ($this->subs as $scaleid) {
            $settings->{'catquiz_subscalecheckbox_' . $scaleid} = '1';
        }
        return $settings;
    }

    /**
     * The examples of the issue.
     *
     * @return array
     */
    public static function examples(): array {
        $fastest = LOCAL_CATQUIZ_STRATEGY_FASTEST;
        $lowestsub = LOCAL_CATQUIZ_STRATEGY_LOWESTSUB;
        $allsubs = LOCAL_CATQUIZ_STRATEGY_ALLSUBS;
        $minutes = fn(int $attempt, int $item) => ['catquiz_includetimelimit' => 1, 'catquiz_timelimitgroup' => [
            'catquiz_maxtimeperattempt' => $attempt, 'catquiz_timeselect_attempt' => 'min',
            'catquiz_maxtimeperitem' => $item, 'catquiz_timeselect_item' => 'min',
        ]];
        return [
            'ex. 1: minimum 10, at most 4 in each of 2 subscales' => [[12, 12],
                ['strategy' => $lowestsub, 'min' => 10, 'max' => 20, 'maxperscale' => 4],
                ['minimumunreachable'], [], []],
            'ex. 1, valid: at most 5 in each of 2 subscales' => [[12, 12],
                ['strategy' => $lowestsub, 'min' => 10, 'max' => 20, 'maxperscale' => 5], [], [], []],
            'ex. 1, 0 is no limit' => [[12, 12],
                ['strategy' => $lowestsub, 'min' => 10, 'max' => 0, 'maxperscale' => 0], [], [], []],
            'ex. 1, strategy without per-scale limits' => [[12, 12],
                ['strategy' => $fastest, 'min' => 10, 'max' => 20, 'maxperscale' => 4], [], [], []],
            'ex. 2: ALLSUBS, 3 scales x 3 > maximum 8' => [[12, 12, 12],
                ['strategy' => $allsubs, 'min' => 1, 'max' => 8, 'minperscale' => 3],
                ['coveragebudget'], [], []],
            'ex. 2, valid: maximum 9' => [[12, 12, 12],
                ['strategy' => $allsubs, 'min' => 1, 'max' => 9, 'minperscale' => 3], [], [], []],
            'ex. 2, maximum 0 is no limit' => [[12, 12, 12],
                ['strategy' => $allsubs, 'min' => 1, 'max' => 0, 'minperscale' => 3], [], [], []],
            'ex. 3: a covered scale with 2 items, minimum 3 per scale' => [[12, 2],
                ['strategy' => $allsubs, 'min' => 1, 'max' => 0, 'minperscale' => 3],
                ['scalebelowminimum'], [], []],
            'ex. 3, not covered by the strategy' => [[12, 2],
                ['strategy' => $fastest, 'min' => 1, 'max' => 0, 'minperscale' => 3], [], [], []],
            'ex. 4: unreachable target SE, the maximum ends the test' => [[12, 12],
                ['strategy' => $fastest, 'min' => 1, 'max' => 3, 'semin' => 0.05],
                [], ['precisionunreachable'], []],
            'ex. 6: question time 15 min, attempt 10 min' => [[12, 12],
                ['strategy' => $fastest, 'min' => 10, 'max' => 0, 'more' => $minutes(10, 15)],
                [], ['itemtimenotshorter'], ['timeperquestion']],
            'pool: minimum 10, 7 productive items' => [[7],
                ['strategy' => $fastest, 'min' => 10, 'max' => 0], ['minimumunreachable'], [], []],
            // The root counts the questions of its subscale too: both have 2.
            'scale minimum 3, scale with 2 items (ALLSUBS)' => [[2],
                ['strategy' => $allsubs, 'min' => 1, 'max' => 0, 'minperscale' => 3],
                ['scalebelowminimum', 'scalebelowminimum'], [], []],
            'time: 300 s for 10 questions, 30 s each (diagnostic)' => [[12],
                ['strategy' => $fastest, 'min' => 10, 'max' => 0, 'more' => [
                    'catquiz_includetimelimit' => 1, 'catquiz_timelimitgroup' => [
                        'catquiz_maxtimeperattempt' => 5, 'catquiz_timeselect_attempt' => 'min',
                    ]]], [], [], ['timeperquestion']],
            'minimum x question time above attempt time is no error' => [[12, 12],
                ['strategy' => $fastest, 'min' => 10, 'max' => 0, 'more' => $minutes(5, 2)], [], [], ['timeperquestion']],
            'pilot ratio 50 %: about 20 questions for 10 (diagnostic)' => [[12, 12],
                ['strategy' => $fastest, 'min' => 10, 'max' => 0, 'more' => [
                    'catquiz_includepilotquestions' => 1, 'catquiz_pilotratio' => 50,
                ]], [], [], ['pilotexpectation']],
            'minimum above maximum' => [[12],
                ['strategy' => $fastest, 'min' => 5, 'max' => 4], ['minabovemax', 'minimumunreachable'], [], []],
            'minimum per scale above maximum per scale' => [[12],
                ['strategy' => $lowestsub, 'min' => 1, 'max' => 0, 'minperscale' => 5, 'maxperscale' => 4],
                ['minperscaleabovemax'], [], []],
        ];
    }

    /**
     * Each example: exactly the findings the issue gives it.
     *
     * @param int[] $items Items per subscale.
     * @param array $case Settings.
     * @param string[] $errors
     * @param string[] $warnings
     * @param string[] $diagnostics
     * @dataProvider examples
     */
    public function test_the_examples_of_the_issue(
        array $items,
        array $case,
        array $errors,
        array $warnings,
        array $diagnostics
    ): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->tree($items);

        $findings = settings_validator::validate($this->settings($case));
        $codes = fn(string $level) => array_values(array_column(
            array_filter($findings, fn($f) => $f['level'] === $level),
            'code'
        ));
        $this->assertSame($errors, $codes(settings_validator::ERROR), 'Errors: ' . json_encode($findings));
        $this->assertSame($warnings, $codes(settings_validator::WARNING), 'Warnings');
        $this->assertSame($diagnostics, $codes(settings_validator::DIAGNOSTIC), 'Diagnostics');
        foreach ($findings as $finding) {
            $this->assertStringNotContainsString('[[', $finding['message'], 'Every finding is translated.');
            $this->assertStringNotContainsString('{$a', $finding['message'], 'Every value is filled in.');
        }
    }

    /**
     * The messages name the values: example 2 tells how many questions it takes and how many are allowed.
     */
    public function test_messages_name_the_values(): void {
        $this->resetAfterTest();
        $this->tree([12, 12, 12]);
        $errors = settings_validator::errors(settings_validator::validate($this->settings(
            ['strategy' => LOCAL_CATQUIZ_STRATEGY_ALLSUBS, 'min' => 1, 'max' => 8, 'minperscale' => 3]
        )));
        $this->assertSame(9, $errors[0]['a']['needed']);
        $this->assertStringContainsString('9', $errors[0]['message']);
        $this->assertStringContainsString('8', $errors[0]['message']);
    }

    /**
     * A hierarchy: K1.1 has two subscales of its own, both covered. Their questions count for K1.1 too, so the
     * budget is that of the scales at the bottom - 2 x 3 + 3 for K1.2 = 9 - not 3 for every scale (12).
     */
    public function test_the_budget_counts_a_question_for_its_ancestors(): void {
        $this->resetAfterTest();
        $this->tree([0, 12]);
        $this->subs[] = $a = $this->scale('K1.1.1', $this->subs[0]);
        $this->subs[] = $b = $this->scale('K1.1.2', $this->subs[0]);
        for ($j = 0; $j < 6; $j++) {
            $this->item($a, -1 + $j * 0.3);
            $this->item($b, $j * 0.3);
        }
        cache::make('local_catquiz', 'runtimepool')->purge();
        $case = ['strategy' => LOCAL_CATQUIZ_STRATEGY_ALLSUBS, 'min' => 1, 'minperscale' => 3];

        $this->assertSame([], settings_validator::errors(settings_validator::validate($this->settings($case + ['max' => 9]))));
        $errors = settings_validator::errors(settings_validator::validate($this->settings($case + ['max' => 8])));
        $this->assertSame(['coveragebudget'], array_column($errors, 'code'));
        $this->assertSame(9, $errors[0]['a']['needed']);
    }

    /**
     * Saved through the API - a template, a webservice, an import -, settings no attempt could meet whatever the
     * pool are refused; what depends on the pool is left to the start of the attempt, which checks it for every
     * path (settings_validator_test::test_the_preflight_before_the_first_question).
     */
    public function test_programmatic_saves_apply_the_same_hard_rules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->tree([]);
        $invalid = $this->settings(['strategy' => LOCAL_CATQUIZ_STRATEGY_FASTEST, 'min' => 5, 'max' => 4]);
        try {
            testenvironment::create_template($invalid, $this->root, 'Invalid');
            $this->fail('A template with minimum above maximum is refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('settingsinconsistent', $e->errorcode);
            $this->assertStringContainsString('5', $e->getMessage());
        }
        $test = new testenvironment((object) [
            'componentid' => 4711, 'component' => 'mod_adaptivequiz', 'json' => json_encode($invalid),
            'catscaleid' => $this->root, 'name' => 'Activity',
        ]);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/5/');
        $test->save_or_update();
    }

    /**
     * Without any item the settings can be saved through the API - the item bank may come later - and the
     * form, the preflight, find it.
     */
    public function test_a_test_before_its_items_can_be_saved(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->tree([]);
        $settings = $this->settings(['strategy' => LOCAL_CATQUIZ_STRATEGY_FASTEST, 'min' => 5, 'max' => 10]);
        $id = testenvironment::create_template($settings, $this->root, 'Before the items');
        $this->assertTrue($DB->record_exists('local_catquiz_tests', ['id' => $id]));
        $this->assertSame(
            ['nousableitems'],
            array_column(settings_validator::errors(settings_validator::validate($settings)), 'code')
        );
    }

    /**
     * 200 subscales, all covered: the validation reads the pool once, not once per scale.
     */
    public function test_no_query_per_scale(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->tree(array_fill(0, 20, 1));
        $case = ['strategy' => LOCAL_CATQUIZ_STRATEGY_ALLSUBS, 'min' => 1, 'max' => 0, 'minperscale' => 1];
        settings_validator::validate($this->settings($case));
        $before = $DB->perf_get_reads();
        settings_validator::validate($this->settings($case));
        $twenty = $DB->perf_get_reads() - $before;

        for ($i = 0; $i < 180; $i++) {
            $scale = $this->scale('K1.x' . $i, $this->root);
            $this->subs[] = $scale;
            $this->item($scale, 0.0);
        }
        cache::make('local_catquiz', 'runtimepool')->purge();
        \cache_helper::purge_by_event('changesincatscales');
        settings_validator::validate($this->settings($case));
        $before = $DB->perf_get_reads();
        settings_validator::validate($this->settings($case));
        $twohundred = $DB->perf_get_reads() - $before;

        $this->assertLessThanOrEqual($twenty + 5, $twohundred, "Reads for 20 scales: $twenty, for 200: $twohundred.");
    }
}
