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
 * Tests teststrategy_fastest
 *
 * @package    catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use cache;
use local_catquiz\importer\testitemimporter;
use local_catquiz\local\result;
use local_catquiz\teststrategy\strategy\teststrategy_fastest;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @package local_catquiz
 * @covers \local_catquiz\teststrategy\strategy\teststrategy_fastest
 */
class teststrategy_fastest_test extends basic_testcase
{

    /**
     * Test it can be instantiated
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_teststrategy_fastest_can_be_instantiated() {
        $fastest = new teststrategy_fastest();
        $this->assertInstanceOf(teststrategy_fastest::class, $fastest);
    }

    /**
     * Test it can run
     *
     * @dataProvider test_radical_CAT_provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_radical_CAT($expected, $attemptcontext) {
        // Some test datasets need a cache, others do not.
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        // Use default values if they are not set in the test dataset.
        $cachekeydata = array_merge($attemptcontext, ['contextid' => 1, 'includesubscales' => true,]);
        $cachekey = sprintf('testitems_%s_%s', $cachekeydata['contextid'], $cachekeydata['includesubscales']);
        $cache->set($cachekey, [1 => (object)[]]);

        $radicalcatstrategy = new teststrategy_fastest();
        $result = $radicalcatstrategy->return_next_testitem($attemptcontext);
        $this->assertEquals($expected, $result);
    }

    public function test_radical_CAT_provider() {
        $question1 = (object) [
            'id' => 1,
            'model' => 'raschbirnbauma',
            'userlastattempttime' => time() - 100,
            'difficulty' => 1.23,
            'catscaleid' => 1,
        ];

        return [
            'maximum questions reached' => [
                'expected' => result::err('reachedmaximumquestions'),
                'attemptcontext' => [
                    'questionsattempted' => 10,
                    'maximumquestions' => 10,
                ]
            ],
            'no remaining questions' => [
                'expected' => result::err('noremainingquestions'),
                'attemptcontext' => [
                    'questionsattempted' => 0,
                    'maximumquestions' => 10,
                    'questions' => [],
                ]
            ],
            'returns a question' => [
                'expected' => result::ok($question1),
                'attemptcontext' => [
                    'questionsattempted' => 0,
                    'maximumquestions' => 10,
                    'questions' => [
                        1 => $question1,
                    ],
                    'original_questions' => [
                        1 => $question1,
                    ],
                    'selectfirstquestion' => 'startwitheasiestquestion',
                    'questions_ordered_by' => 'difficulty',
                    'testid' => 1,
                    'contextid' => 1,
                    'catscaleid' => 1,
                    'lastquestion' => null,

                    'penalty_threshold' => 60*60*24*30-90,
                    'penalty_time_range' => 60*60*24*30,

                    'installed_models' => [
                        'raschbirnbauma' => 'catmodel_raschbirnbauma\raschbirnbauma',
                    ],
                    'person_ability' => [ 1 => 0.1234],
                    'includesubscales' => true,

                    'max_attempts_per_scale' => 10,
                ]
            ],
        ];
    }

    public function test_import() {
        global $DB;
        $importer = new testitemimporter();
        $content = file_get_contents(__DIR__ . '/../../fixtures/params_import.csv');
        $importresult = $importer->execute_testitems_csv_import(
            (object) [
                'delimiter_name' => null,
                'encoding' => null,
                'dateparseformat' => null,
            ],
            $content
        );

        $result = $DB->get_records('local_catquiz_itemparams');
        $this->assertEquals(123, count($result));
    }
}
