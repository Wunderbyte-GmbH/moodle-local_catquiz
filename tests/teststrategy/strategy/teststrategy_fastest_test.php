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

use advanced_testcase;
use cache;
use local_catquiz\data\catscale_structure;
use local_catquiz\data\dataapi;
use local_catquiz\importer\testitemimporter;
use local_catquiz\local\result;
use local_catquiz\teststrategy\strategy\teststrategy_fastest;
use PHPUnit\Framework\ExpectationFailedException;
use qformat_xml;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @package local_catquiz
 * @covers \local_catquiz\teststrategy\strategy\teststrategy_fastest
 */
class teststrategy_fastest_test extends advanced_testcase {


    public function setUp(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Import questions.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qformat = $this->create_qformat('questions_la.xml', $course);
        $imported = $qformat->importprocess();
        $this->assertTrue($imported);

        // Add imported questions to a new CAT scale.
        $catscaleid = $this->create_catscale();
        foreach ($qformat->questionids as $qid) {
            catscale::add_or_update_testitem_to_scale($catscaleid, $qid);
        }

        $this->import_itemparams();
    }

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
     * @dataProvider radical_CAT_provider
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_radical_cat($expected, $attemptcontext) {
        // Some test datasets need a cache, others do not.
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        // Use default values if they are not set in the test dataset.
        $cachekeydata = array_merge($attemptcontext, ['contextid' => 1, 'includesubscales' => true]);
        $cachekey = sprintf('testitems_%s_%s', $cachekeydata['contextid'], $cachekeydata['includesubscales']);
        $cache->set($cachekey, [1 => (object)[]]);

        $radicalcatstrategy = new teststrategy_fastest();
        $result = $radicalcatstrategy->return_next_testitem($attemptcontext);
        $this->assertEquals($expected, $result);
    }

    /**
     * Radical_cat_provider
     *
     * @return array
     */
    public function radical_cat_provider() {
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
                    'breakduration' => 60,
                    'breakinfourl' => 'xxx' ,
                    'maxtimeperquestion' => 30,
                ],
            ],
            'no remaining questions' => [
                'expected' => result::err('noremainingquestions'),
                'attemptcontext' => [
                    'questionsattempted' => 0,
                    'maximumquestions' => 10,
                    'questions' => [],
                    'breakduration' => 60,
                    'breakinfourl' => 'xxx' ,
                    'maxtimeperquestion' => 30,
                ],
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

                    'penalty_threshold' => 60 * 60 * 24 * 30 - 90,
                    'penalty_time_range' => 60 * 60 * 24 * 30,

                    'installed_models' => [
                        'raschbirnbauma' => 'catmodel_raschbirnbauma\raschbirnbauma',
                    ],
                    'person_ability' => [ 1 => 0.1234],
                    'includesubscales' => true,

                    'max_attempts_per_scale' => 10,
                    'breakduration' => 60,
                    'breakinfourl' => 'xxx' ,
                    'maxtimeperquestion' => 30,
                ],
            ],
        ];
    }

    /**
     * [Description for import_itemparams]
     *
     * @return void
     */
    private function import_itemparams() {
        $importer = new testitemimporter();
        $content = file_get_contents(__DIR__ . '/../../fixtures/params_la.csv');
        $importer->execute_testitems_csv_import(
            (object) [
                'delimiter_name' => 'semicolon',
                'encoding' => null,
                'dateparseformat' => null,
            ],
            $content
        );
    }

    /**
     * [Description for create_catscale]
     *
     * @return int
     */
    private function create_catscale() {
        $catscalestructure = new catscale_structure(
            [
                'parentid' => 0,
                'timemodified' => time(),
                'timecreated' => time(),
                'minmaxgroup' => [
                    'catquiz_minscalevalue' => 0,
                    'catquiz_maxscalevalue' => 100,
                ],
                'name' => 'UnitTestScale',

            ]
        );
        $catscaleid = dataapi::create_catscale($catscalestructure);
        return $catscaleid;
    }

    /**
     * NOTE: copied from qformat_xml_import_export_test.php
     *
     * Create object qformat_xml for test.
     * @param string $filename with name for testing file.
     * @param \stdClass $course
     * @return qformat_xml XML question format object.
     */
    private function create_qformat($filename, $course) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        $qformat = new qformat_xml();
        $contexts = $DB->get_records('context');
        $importfile = __DIR__ . '/../../fixtures/' .$filename;
        $realfilename = $filename;
        $qformat->setContexts($contexts);
        $qformat->setCourse($course);
        $qformat->setFilename($importfile);
        $qformat->setRealfilename($realfilename);
        $qformat->setMatchgrades('error');
        $qformat->setCatfromfile(1);
        $qformat->setContextfromfile(1);
        $qformat->setStoponerror(1);
        $qformat->setCattofile(1);
        $qformat->setContexttofile(1);
        $qformat->set_display_progress(false);

        return $qformat;
    }
}
