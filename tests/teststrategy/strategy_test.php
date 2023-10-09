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
use local_catquiz\teststrategy\strategy;
use local_catquiz\teststrategy\strategy\teststrategy_fastest;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @package local_catquiz
 * @covers \local_catquiz\teststrategy\strategy\strategy
 */
class strategy_test extends basic_testcase {


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
            $cache = $this->prepareadaptivequizcache($attemptcontext);
        if ($fun) {
            $fun();
        }
            $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        foreach ($expected as $attempt => $exp) {
            $result = $strategy->return_next_testitem($attemptcontext);
            $this->assertEquals($exp, $result->unwrap()->id, sprintf("Failed for question number %d", $attempt + 1));
            $lastquestion = $cache->get('lastquestion');
            $this->assertEquals($lastquestion->id, $exp);
        }
    }

    public function teststrategies_return_expected_questions_provider() {
        return [
            'first selected question is the easiest' =>
            [
                'expected_question_id' => [1],
                'strategy' => (new teststrategy_fastest()),
                'attemptcontextdiff' => [],
                'custom_setup_fun' => function () {
                    $cache = cache::make('local_catquiz', 'adaptivequizattempt');
                    $cache->set('isfirstquestionofattempt', true);
                },
            ],
            'first selected question is the first of the second quintil' =>
            [
                'expected_question_id' => [20],
                'strategy' => (new teststrategy_fastest()),
                'attemptcontextdiff' =>
                    ['selectfirstquestion' => 'startwithfirstofsecondquintil'],
                'custom_setup_fun' => function () {
                    $cache = cache::make('local_catquiz', 'adaptivequizattempt');
                    $cache->set('isfirstquestionofattempt', true);
                },
            ],
            'first selected question is the first of the second quartil' =>
            [
                'expected_question_id' => [25],
                'strategy' => (new teststrategy_fastest()),
                'attemptcontextdiff' =>
                    ['selectfirstquestion' => 'startwithfirstofsecondquartil'],
                'custom_setup_fun' => function () {
                    $cache = cache::make('local_catquiz', 'adaptivequizattempt');
                    $cache->set('isfirstquestionofattempt', true);
                },
            ],
            'first selected question is the most difficult of the second quartil' =>
            [
                'expected_question_id' => [49],
                'strategy' => (new teststrategy_fastest()),
                'attemptcontextdiff' =>
                    ['selectfirstquestion' => 'startwithmostdifficultsecondquartil'],
                'custom_setup_fun' => function () {
                    $cache = cache::make('local_catquiz', 'adaptivequizattempt');
                    $cache->set('isfirstquestionofattempt', true);
                },
            ],
            'first selected question is selected by average ability of the test' =>
            [
                'expected_question_id' => [50],
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
            'first selected question is selected by the user\'s current ability' =>
            [
                'expected_question_id' => [70],
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
            'radical CAT' =>
            [
                'expected_question_id' => [
                    51,
                    52,
                    50,
                    53,
                ],
                'strategy' => (new teststrategy_fastest()),
            ],
        ];
    }

    private function prepareadaptivequizcache($attemptcontext) {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cache->purge();
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

            'max_attempts_per_scale' => 10,
            'breakduration' => 60,
            'breakinfourl' => 'xxx',
            'maxtimeperquestion' => 30,
            'fake_personparams_for_test' => [],
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
        for ($i = 1; $i <= $num; $i++) { // Use 1-based indexing to facilitate interpreting quantile ranges.
            if ($difficultydistribution === 'uniform') {
                $difficulty = $mindifficulty + ($i * ($maxdifficulty - $mindifficulty)) / ($num);
            }
            $questions[$i] = (object) [
                'id' => $i,
                'model' => 'raschbirnbauma',
                'userlastattempttime' => time() - 100,
                'difficulty' => $difficulty,
                'catscaleid' => $catscaleid,
                'is_pilot' => false,
            ];
        }
        return $questions;
    }
}
