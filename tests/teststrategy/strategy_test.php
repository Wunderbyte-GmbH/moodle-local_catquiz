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
 * Tests strategy
 *
 * @package    catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use cache;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\strategy;
use local_catquiz\teststrategy\strategy\classicalcat;
use local_catquiz\teststrategy\strategy\inferallsubscales;
use local_catquiz\teststrategy\strategy\teststrategy_balanced;
use local_catquiz\teststrategy\strategy\teststrategy_fastest;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @package local_catquiz
 * @covers \local_catquiz\teststrategy\strategy\strategy
 */
class strategy_test extends basic_testcase {

    private int $lastgeneratedquestionid = 0;

    protected function tearDown(): void {
        $this->purgecache();
        $this->lastgeneratedquestionid = 0;
    }

    /**
     * Test adding new questions per subscale works as expected.
     *
     * @dataProvider update_played_questions_per_scale_works_provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_update_played_questions_per_scale_works($expected, $questions, $newquestion) {
        $fastest = new teststrategy_fastest();
        $result = $fastest->update_playedquestionsperscale($newquestion, $questions);
        $this->assertEquals($expected, $result);
    }

    public function update_played_questions_per_scale_works_provider() {
        $question1 = (object)[
            'id' => 1,
            'catscaleid' => 1,
        ];
        $question2 = $question1;
        $question2 = (object)[
            'id' => 2,
            'catscaleid' => 1,
        ];
        $question3 = (object)[
            'id' => 3,
            'catscaleid' => 2,
        ];
        return [
            'first question is added' => [
                'expected' => [1 => [$question1]],
                'playedquestions' => [],
                'lastquestion' => $question1,
            ],
            'add second question in same scale' => [
                'expected' => [1 => [$question1, $question2]],
                'playedquestions' => [1 => [$question1]],
                'lastquestion' => $question2,
            ],
            'add second question in different scale' => [
                'expected' => [1 => [$question1], 2 => [$question3]],
                'playedquestions' => [1 => [$question1]],
                'lastquestion' => $question3,
            ],
        ];
    }

    /**
     * @dataProvider teststrategies_return_expected_questions_provider
     * @param mixed $expected
     * @param mixed $attemptcontext
     * @param mixed $cache
     * @return void
     */
    public function test_teststrategies_return_expected_questions(
        $expected,
        strategy $strategy,
        array $attemptcontextdiff = [],
        ?callable $fun = null
    ) {
        $attemptcontext = array_merge($this->getattemptcontext(), $attemptcontextdiff);
        $this->prepareadaptivequizcache($attemptcontext);
        if ($fun) {
            $fun();
        }

        $field = 'id';
        $expectedvalues = $expected;
        // By default, the expected value contains the ID of the expected
        // question. To check other fields, the `field` value is set to the
        // field that should be tested and the expected values are in the
        // `values` array.
        if (array_key_exists('field', $expected)
            && array_key_exists('values', $expected)
        ) {
            $field = $expected['field'];
            $expectedvalues = $expected['values'];
        }

        // Test for expected question IDs.
        foreach ($expectedvalues as $attempt => $exp) {
            $result = $strategy->return_next_testitem($attemptcontext);
            if ($exp instanceof result && $exp->iserr()) {
                $this->assertInstanceOf(result::class, $result);
                $this->assertEquals($exp->get_status(), $result->get_status());
                continue;
            }
            $this->assertEquals(
                $exp,
                $result->unwrap()->$field,
                sprintf("Failed for question number %d", $attempt + 1)
            );
            $attemptcontext['questionsattempted']++;
        }
    }

    public function teststrategies_return_expected_questions_provider() {
        // Create some questions assigned to different scales (3,4,5).
        // The `+` operater does a merge and keeps the indices.
        $infersubscalesquestions = $this->generatequestions(20, 3)
            + $this->generatequestions(20, 4)
            + $this->generatequestions(60, 5)
        ;

        $qstartwith101 = $this->generatequestions(10, 3)
            + $this->generatequestions(10, 4)
            + $this->generatequestions(10, 5)
        ;
        $this->resetgeneratedquestionids();

        // If the arrays are just copied, the questions inside are shared between
        // different test cases, which might lead to unexpected results.
        $deepcopy = fn($array) => array_map(fn ($q) => clone $q, $array);
        $isq2 = $deepcopy($infersubscalesquestions);
        $isq3 = $deepcopy($infersubscalesquestions);

        return [
            'first selected question is the easiest' => [
                'expected_question_id' => [1],
                'strategy' => (new teststrategy_fastest()),
                'attemptcontextdiff' => [],
                'custom_setup_fun' => function () {
                    $cache = cache::make('local_catquiz', 'adaptivequizattempt');
                    $cache->set('isfirstquestionofattempt', true);
                },
            ],
            'first selected question is the first of the second quintil' => [
                'expected_question_id' => [20],
                'strategy' => (new teststrategy_fastest()),
                'attemptcontextdiff' =>
                ['selectfirstquestion' => 'startwithfirstofsecondquintil'],
                'custom_setup_fun' => function () {
                    $cache = cache::make('local_catquiz', 'adaptivequizattempt');
                    $cache->set('isfirstquestionofattempt', true);
                },
            ],
            'first selected question is the first of the second quartil' => [
                'expected_question_id' => [25],
                'strategy' => (new teststrategy_fastest()),
                'attemptcontextdiff' =>
                ['selectfirstquestion' => 'startwithfirstofsecondquartil'],
                'custom_setup_fun' => function () {
                    $cache = cache::make('local_catquiz', 'adaptivequizattempt');
                    $cache->set('isfirstquestionofattempt', true);
                },
            ],
            'first selected question is the most difficult of the second quartil' => [
                'expected_question_id' => [49],
                'strategy' => (new teststrategy_fastest()),
                'attemptcontextdiff' =>
                ['selectfirstquestion' => 'startwithmostdifficultsecondquartil'],
                'custom_setup_fun' => function () {
                    $cache = cache::make('local_catquiz', 'adaptivequizattempt');
                    $cache->set('isfirstquestionofattempt', true);
                },
            ],
            'first selected question is selected by average ability of the test' => [
                'expected_question_id' => [51],
                'strategy' => (new teststrategy_fastest()),
                'attemptcontextdiff' => [
                    'selectfirstquestion' => 'startwithaverageabilityoftest',
                    'fake_personparams_for_test' => [
                        (object) ['ability' => -2],
                        (object) ['ability' => -1],
                        (object) ['ability' => 0],
                        (object) ['ability' => 1],
                        (object) ['ability' => 2],
                    ],
                ],
                'custom_setup_fun' => function () {
                    $cache = cache::make('local_catquiz', 'adaptivequizattempt');
                    $cache->set('isfirstquestionofattempt', true);
                },
            ],
            'first selected question is selected by the user\'s current ability' => [
                'expected_question_id' => [71],
                'strategy' => (new teststrategy_fastest()),
                'attemptcontextdiff' => [
                    'selectfirstquestion' => 'startwithcurrentability',
                    'person_ability' => [1 => 2.0],
                ],
                'custom_setup_fun' => function () {
                    $cache = cache::make('local_catquiz', 'adaptivequizattempt');
                    $cache->set('isfirstquestionofattempt', true);
                },
            ],
            'radical CAT' => [
                'expected_question_id' => [
                    52,
                    53,
                    51,
                    54,
                ],
                'strategy' => (new teststrategy_fastest()),
            ],
            'moderate CAT' => [
                // In the generated fake data, the number of general attempts
                // for each question is the same as its question ID. So we
                // expect questions with the lowest IDs to be selected first.
                'expected_question_id' => [
                    1,
                    2,
                    3,
                    4,
                    5,
                ],
                'strategy' => (new teststrategy_balanced()),
                'attemptcontextdiff' => [
                    'fake_questionattemptcounts' => $this->generatequestionattemptcounts(),
                ],
            ],
            'infer all subscales' => [
                'expected_question_id' => [
                    74,
                    12,
                    75,
                    73,
                    32,
                ],
                'strategy' => (new inferallsubscales()),
                'attemptcontextdiff' => [
                    'standarderrorpersubscale' => 0.5,
                    'min_attempts_per_scale' => 1,
                    'questions' => $infersubscalesquestions,
                    'original_questions' => $infersubscalesquestions,
                    'fake_ancestor_scales' => [
                        1 => [],
                        2 => [1],
                        3 => [2, 1],
                        4 => [2, 1],
                        5 => [2, 1],

                    ],
                    'fake_child_scales' => [
                        1 => [2, 3, 4, 5],
                        2 => [3, 4, 5],
                        3 => [],
                        4 => [],
                        5 => [],
                    ],
                    'person_ability' => [
                        1 => 0.3,
                        2 => 0.3,
                        3 => 0.4,
                        4 => 0.3,
                        5 => 0.5,
                    ]
                ],
            ],
            'infer all subscales minimum attempts 0 max attempts 10' => [
                'expected' => [
                    (result::err(status::ERROR_NO_REMAINING_QUESTIONS)),
                ],
                'strategy' => (new inferallsubscales()),
                'attemptcontextdiff' => [
                    'standarderrorpersubscale' => 0.5,
                    'min_attempts_per_scale' => 0,
                    'max_attempts_per_scale' => 10,
                    'questions' => $isq2,
                    'original_questions' => $isq2,
                    'fake_ancestor_scales' => [
                        1 => [],
                        2 => [1],
                        3 => [2, 1],
                        4 => [2, 1],
                        5 => [2, 1],

                    ],
                    'fake_child_scales' => [
                        1 => [2, 3, 4, 5],
                        2 => [3, 4, 5],
                        3 => [],
                        4 => [],
                        5 => [],
                    ],
                    'person_ability' => [
                        1 => 0.3,
                        2 => 0.3,
                        3 => 0.4,
                        4 => 0.3,
                        5 => 0.5,
                    ]
                ],
            ],
            'infer all subscales minimum attempts 0 max attempts 20' => [
                'expected' => [
                    74,
                ],
                'strategy' => (new inferallsubscales()),
                'attemptcontextdiff' => [
                    'standarderrorpersubscale' => 0.5,
                    'min_attempts_per_scale' => 0,
                    'max_attempts_per_scale' => 20,
                    'questions' => $isq3,
                    'original_questions' => $isq3,
                    'fake_ancestor_scales' => [
                        1 => [],
                        2 => [1],
                        3 => [2, 1],
                        4 => [2, 1],
                        5 => [2, 1],

                    ],
                    'fake_child_scales' => [
                        1 => [2, 3, 4, 5],
                        2 => [3, 4, 5],
                        3 => [],
                        4 => [],
                        5 => [],
                    ],
                    'person_ability' => [
                        1 => 0.3,
                        2 => 0.3,
                        3 => 0.4,
                        4 => 0.3,
                        5 => 0.5,
                    ]
                ],
            ],
            // In this test, questions are just returned in ascending order of
            // the question ID.
            'classical CAT' => [
                'expected' => [
                    'field' => 'itemid',
                    'values' => [
                        111,
                        112,
                        113,
                        114,
                        115,
                    ]
                ],
                'strategy' => (new classicalcat()),
                'attemptcontextdiff' => [
                    'questions' => $qstartwith101,
                    'original_questions' => $qstartwith101,
                    'fake_ancestor_scales' => [
                        1 => [],
                        2 => [1],
                        3 => [2, 1],
                        4 => [2, 1],
                        5 => [2, 1],

                    ],
                    'fake_child_scales' => [
                        1 => [2, 3, 4, 5],
                        2 => [3, 4, 5],
                        3 => [],
                        4 => [],
                        5 => [],
                    ],
                    'person_ability' => [
                        1 => 0.3,
                        2 => 0.3,
                        3 => 0.4,
                        4 => 0.3,
                        5 => 0.5,
                    ]
                ],
            ],
        ];
    }

    private function purgecache() {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cache->purge();
    }

    private function prepareadaptivequizcache($attemptcontext) {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cachekey = sprintf('testitems_%s_%s', $attemptcontext['contextid'], $attemptcontext['includesubscales']);
        $cache->set($cachekey, $attemptcontext['original_questions']);
        return $cache;
    }

    private function getattemptcontext() {
        $questions = $this->generatequestions();
        return [
            'questionsattempted' => 0,
            'maximumquestions' => 10,
            'questions' => $questions,
            'original_questions' => $questions,
            'selectfirstquestion' => 'startwitheasiestquestion',
            'questions_ordered_by' => 'difficulty',
            'testid' => 1,
            'contextid' => 1,
            'catscaleid' => 1,
            'lastquestion' => null,

            'penalty_threshold' => 60 * 60 * 24 * 30 - 90,
            'penalty_time_range' => 60 * 60 * 24 * 30,

            'installed_models' => [
                'raschbirnbauma' => 'catmodel_raschbirnbauma\raschbirnbauma',
            ],
            'person_ability' => [1 => 0.1234],
            'includesubscales' => true,

            'max_attempts_per_scale' => 50,
            //'breakduration' => 60,
            //'breakinfourl' => 'xxx',
            //'maxtimeperquestion' => 30,
            'fake_personparams_for_test' => [],
            'pilot_ratio' => 0.0,
        ];
    }

    private function generatequestions(
        int $num = 100,
        int $catscaleid = 1,
        string $difficultydistribution = 'uniform'
    ) {
        $questions = [];
        $mindifficulty = -5;
        $maxdifficulty = 5;
        for (
            $i = $this->lastgeneratedquestionid + 1, $j = 0;
            $i <= $this->lastgeneratedquestionid + $num;
            $i++, $j++
        ) { // Use 1-based indexing to facilitate interpreting quantile ranges.
            if ($difficultydistribution === 'uniform') {
                $difficulty = $mindifficulty + ($j * ($maxdifficulty - $mindifficulty)) / ($num);
            }
            $questions[$i] = (object) [
                'id' => $i,
                'model' => 'raschbirnbauma',
                'userlastattempttime' => time() - 100,
                'difficulty' => $difficulty,
                'catscaleid' => $catscaleid,
                'is_pilot' => false,
                'attempts' => $i,
                // Set it to qid + 10 just to emphasize its different.
                'itemid' => $i+10,
            ];
        }
        $this->lastgeneratedquestionid = $i-1;
        return $questions;
    }

    private function resetgeneratedquestionids() {
        $this->lastgeneratedquestionid = 0;
    }

    private function generatequestionattemptcounts() {
        $attemptcounts = [];
        $questions = $this->generatequestions();
        foreach ($questions as $qid => $question) {
            $attemptcounts[$qid] = (object)['count' => $qid];
        }
        return $attemptcounts;
    }
}
