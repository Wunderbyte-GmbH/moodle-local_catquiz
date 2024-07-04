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
 * Tests the feedbackgenerator personability.
 *
 * @package    local_catquiz
 * @author     Magdalena Holczik
 * @copyright  2023 onwards Georg Maißer <info@wunderbyte.at>
 * @license    http =>//www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator\personabilities;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\progress;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests the feedbackgenerator personability.
 *
 * @package    local_catquiz
 * @author     David Szkiba, Magdalena Holczik
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http =>//www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\teststrategy\feedbackgenerator\personabilities
 */
class personabilities_test extends advanced_testcase {

    /**
     * Test that questions of subscales are removed as needed.
     *
     * @param array $feedbackdata
     * @param array $expected
     * @param array $abilityrange
     * @param array $testitemsforcatscale
     * @param array $fisherinfo
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @dataProvider get_studentfeedback_provider
     */
    public function test_get_studentfeedback(
        array $feedbackdata,
        array $expected,
        array $abilityrange,
        array $testitemsforcatscale,
        array $fisherinfo) {

        $progressmock = $this->getMockBUilder(progress::class)
            ->onlyMethods([
                'get_quiz_settings',
                'get_abilities',
            ])
            ->getMock();

        $progressmock
            ->method('get_quiz_settings')
            ->willReturn((object) $feedbackdata['quizsettings']);

        $primaryscaleid = $feedbackdata['primaryscale']['id'];
        $primaryscalevalue = $feedbackdata['personabilities_abilities'][$primaryscaleid]['value'];
        $progressmock
            ->method('get_abilities')
            ->willReturn([$primaryscaleid => $primaryscalevalue]);

        $feedbackhelpermock = $this->getMockBUilder(feedback_helper::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'get_ability_range',
            ])
            ->getMock();

        $feedbackhelpermock
            ->method('get_ability_range')
            ->willReturn($abilityrange);
        $feedbacksettings = new feedbacksettings(LOCAL_CATQUIZ_STRATEGY_LOWESTSUB);
        $personabilities = $this->getMockBuilder(personabilities::class)
            ->onlyMethods([
                'get_testitems_for_catscale',
                'get_progress',
                'get_global_scale',
            ])
            ->setConstructorArgs([$feedbacksettings, $feedbackhelpermock])
            ->getMock();

        // Configure the stub.
        $personabilities
            ->method('get_testitems_for_catscale')
            ->willReturn($testitemsforcatscale);
        $personabilities
            ->method('get_progress')
            ->willReturn($progressmock);
        $personabilities
            ->method('get_global_scale')
            ->willReturn((object)['name' => 'Global scale name']);

        $output = $personabilities->get_feedback($feedbackdata)['studentfeedback'];
        // For the moment, this tests only the heading, not the whole rendered data.
        $this->assertEquals($expected['heading'], $output['heading']);
    }

    /**
     * Data provider for test_get_studentfeedback.
     *
     * @return array
     *
     */
    public static function get_studentfeedback_provider(): array {

        return [
            'lowestskillgap' => [
                'feedbackdata' => [
                    'catscaleid' => 271,
                    'attemptid' => 1,
                    'se' => [],
                    'progress' => [],
                    'userid' => '2',
                    'models' => [
                        "raschbirnbauma" => "catmodel_raschbirnbauma\raschbirnbauma",
                        "raschbirnbaumb" => "catmodel_raschbirnbaumb\raschbirnbaumb",
                        "raschbirnbaumc" => "catmodel_raschbirnbaumc\raschbirnbaumc",
                        "web_raschbirnbauma" => "catmodel_web_raschbirnbauma\\web_raschbirnbauma",
                    ],
                    'contextid' => '1817',
                    'personabilities_abilities' => [
                        '271' => [
                            'value' => "-2.5175",
                            'primary' => "true",
                            'toreport' => "true",
                            'name' => "Simulation",
                        ],
                        '272' => [
                            'value' => "-2.51755",
                            'name' => "Skala2",
                        ],
                        '273' => [
                            'value' => "-2.51755",
                            'name' => "Skala1",
                        ],
                    ],
                    'primaryscale' => [
                        'id' => '271',
                        'name' => 'Simulation',
                        'parentid' => '0',
                    ],
                    'quizsettings' => self::return_quizsettings(),
                    'abilitieslist' => [
                        [
                            "standarderror" => "0.38",
                            "ability" => "2.42",
                            "name" => "SimB04",
                            "catscaleid" => 284,
                            "numberofitemsplayed" => [
                                "noplayed" => 0,
                            ],
                            "isselectedscale" => true,
                            "tooltiptitle" => "[[lowestskill =>tooltiptitle]]",
                        ],
                        [
                            "standarderror" => "0.26",
                            "ability" => "2.53",
                            "name" => "SimB",
                            "catscaleid" => 280,
                            "numberofitemsplayed" => "",
                            "isselectedscale" => false,
                            "tooltiptitle" => "SimB",
                        ],
                        [
                            "standarderror" => "0.11",
                            "ability" => "2.54",
                            "name" => "Simulation",
                            "catscaleid" => 271,
                            "numberofitemsplayed" => [
                                "noplayed" => 0,
                            ],
                            "isselectedscale" => false,
                            "tooltiptitle" => "Simulation",
                        ],
                        [
                            "standarderror" => "0.13",
                            "ability" => "2.76",
                            "name" => "SimA",
                            "catscaleid" => 272,
                            "numberofitemsplayed" => "",
                            "isselectedscale" => false,
                            "tooltiptitle" => "SimA",
                        ],
                        [
                            "standarderror" => "0.13",
                            "ability" => "2.76",
                            "name" => "SimA01",
                            "catscaleid" => 273,
                            "numberofitemsplayed" => "",
                            "isselectedscale" => false,
                            "tooltiptitle" => "SimA01",
                        ],
                        [
                            "standarderror" => "0.47",
                            "ability" => "2.78",
                            "name" => "SimB02",
                            "catscaleid" => 282,
                            "numberofitemsplayed" => "",
                            "isselectedscale" => false,
                            "tooltiptitle" => "SimB02",
                        ],
                    ],
                ],
                'expected' => [
                    'heading' => "Details of your result",
                    'comment' => '',
                    'content' => '<h5>Details of your results</h5>
                    <div class="container">
                        <div class="row">
                            <div
                                    class="font-weight-bold col-4 text-right"
                                    data-toggle="tooltip"
                                    data-placement="top"
                                    title="[[lowestskill =&gt;tooltiptitle]]"
                    >
                                SimB04 :
                            </div>
                            <div
                                    class="font-weight-bold col-4 text-left"
                                    data-toggle="tooltip"
                                    data-placement="top"
                                    title="[[lowestskill =&gt;tooltiptitle]]"
                    >
                                2.42 (Standarderror: 0.38)
                            </div>
                            <div class="modal fade bd-example-modal-xl
                            catquizfeedbackabilitiesplayedquestions_284" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                </div>
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="modal fade bd-example-modal-xl
                            catquizfeedbackabilitiesplayedquestions_280" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                </div>
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div                 class="font-weight-normal col-4 text-right"
                                >
                                Simulation :
                            </div>
                            <div                 class="font-weight-normal col-4 text-left"
                                >
                                2.54 (Standarderror: 0.11)
                            </div>
                            <div class="modal fade bd-example-modal-xl
                            catquizfeedbackabilitiesplayedquestions_271" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                </div>
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="modal fade bd-example-modal-xl
                            catquizfeedbackabilitiesplayedquestions_272" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                </div>
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="modal fade bd-example-modal-xl
                            catquizfeedbackabilitiesplayedquestions_273" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                </div>
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="modal fade bd-example-modal-xl
                            catquizfeedbackabilitiesplayedquestions_282" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                        <h5>
                        Relative ability score in subscales compared to Simulation
                        </h5>
                        <div class="chart-area" id="chart-area-65e9d27ff251a65e9d27ff251e1">
                        <div class="chart-image" role="presentation"
                        aria-describedby="chart-table-data-65e9d27ff251a65e9d27ff251e1"></div>
                        <div class="chart-table ">
                            <p class="chart-table-expand">
                                <a href="#" aria-controls="chart-table-data-65e9d27ff251a65e9d27ff251e1" role="button">
                                    Show chart data
                                </a>
                            </p>
                            <div class="chart-table-data" id="chart-table-data-65e9d27ff251a65e9d27ff251e1"
                            role="complementary" aria-expanded="false"></div>
                        </div>
                    </div>',
                ],
                'abilityrange' => [
                    'minscalevalue' => -3,
                    'maxscalevalue' => 3,
                ],
                'testitemsforscale' => self::return_testitemsforscale(),
                'fisherinfos' => [
                    "-2.75" => 0.053868579223771237,
                    "-2.25" => 0.073312336374749279,
                    "-1.75" => 0.10032462382790783,
                    "-1.25" => 0.13812600089502194,
                    "-0.75" => 0.19163446614525015,
                    "-0.25" => 0.26912623945535352,
                    "0.25" => 0.38739080216675159,
                    "0.75" => 0.59133863637170458,
                    "1.25" => 1.0436635576797371,
                    "1.75" => 2.5218760624002234,
                    "2.25" => 9.5792619084903645,
                    "2.75" => 36.639597288816844],
                ],
        ];
    }

    /**
     * Return testitem for scale.
     *
     * @return array
     *
     */
    private static function return_testitemsforscale(): array {
        return [
            "10728" => (object) [
                "id" => "10728",
                "componentid" => "10728",
                "label" => "SIMA05-00",
                "idnumber" => "SIMA05-00",
                "name" => "Testfrage SIMA05-00",
                "questiontext" =>
                "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.8<\/p>
                <p>Trennsch\u00e4rtfe=> 3.46<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5219",
                "catscalename" => "SimA05",
                "attempts" => "1",
                "lastattempttime" => "1709211291",
                "userattempts" => "1",
                "userlastattempttime" => "1709211291",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.8000",
                "discrimination" => "3.4600",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10729" => (object) [
                "id" => "10729",
                "componentid" => "10729",
                "label" => "SIMA05-01",
                "idnumber" => "SIMA05-01",
                "name" => "Testfrage SIMA05-01",
                "questiontext" =>
                "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.17<\/p><p>Trennsch\u00e4rtfe=> 0.42<\/p>
                <p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5220",
                "catscalename" => "SimA05",
                "attempts" => null,
                "lastattempttime" => "0",
                "userattempts" => null,
                "userlastattempttime" => "0",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.1700",
                "discrimination" => "0.4200",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10730" => (object) [
                "id" => "10730",
                "componentid" => "10730",
                "label" => "SIMA05-02",
                "idnumber" => "SIMA05-02",
                "name" => "Testfrage SIMA05-02",
                "questiontext" =>
                "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.64<\/p><p>Trennsch\u00e4rtfe=> 4.98<\/p>
                <p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5221",
                "catscalename" => "SimA05",
                "attempts" => "1",
                "lastattempttime" => "1709208924",
                "userattempts" => "1",
                "userlastattempttime" => "1709208924",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.6400",
                "discrimination" => "4.9800",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10731" => (object) [
                "id" => "10731",
                "componentid" => "10731",
                "label" => "SIMA05-03",
                "idnumber" => "SIMA05-03",
                "name" => "Testfrage SIMA05-03",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.65<\/p>
                <p>Trennsch\u00e4rtfe=> 5.76<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5222",
                "catscalename" => "SimA05",
                "attempts" => "2",
                "lastattempttime" => "1709807631",
                "userattempts" => "2",
                "userlastattempttime" => "1709807631",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.6500",
                "discrimination" => "5.7600",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10732" => (object) [
                "id" => "10732",
                "componentid" => "10732",
                "label" => "SIMA05-04",
                "idnumber" => "SIMA05-04",
                "name" => "Testfrage SIMA05-04",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.1<\/p>
                <p>Trennsch\u00e4rtfe=> 5.98<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5223",
                "catscalename" => "SimA05",
                "attempts" => "5",
                "lastattempttime" => "1709653764",
                "userattempts" => "5",
                "userlastattempttime" => "1709653764",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.1000",
                "discrimination" => "5.9800",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10733" => (object) [
                "id" => "10733",
                "componentid" => "10733",
                "label" => "SIMA05-05",
                "idnumber" => "SIMA05-05",
                "name" => "Testfrage SIMA05-05",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.46<\/p>
                <p>Trennsch\u00e4rtfe=> 2.67<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5224",
                "catscalename" => "SimA05",
                "attempts" => "1",
                "lastattempttime" => "1709211692",
                "userattempts" => "1",
                "userlastattempttime" => "1709211692",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.4600",
                "discrimination" => "2.6700",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10734" => (object) [
                "id" => "10734",
                "componentid" => "10734",
                "label" => "SIMA05-06",
                "idnumber" => "SIMA05-06",
                "name" => "Testfrage SIMA05-06",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.48<\/p>
                <p>Trennsch\u00e4rtfe=> 3.65<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5225",
                "catscalename" => "SimA05",
                "attempts" => "1",
                "lastattempttime" => "1709211300",
                "userattempts" => "1",
                "userlastattempttime" => "1709211300",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.4800",
                "discrimination" => "3.6500",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10735" => (object) [
                "id" => "10735",
                "componentid" => "10735",
                "label" => "SIMA05-07",
                "idnumber" => "SIMA05-07",
                "name" => "Testfrage SIMA05-07",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.84<\/p>
                <p>Trennsch\u00e4rtfe=> 4.57<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5226",
                "catscalename" => "SimA05",
                "attempts" => "3",
                "lastattempttime" => "1709807640",
                "userattempts" => "3",
                "userlastattempttime" => "1709807640",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.8400",
                "discrimination" => "4.5700",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10736" => (object) [
                "id" => "10736",
                "componentid" => "10736",
                "label" => "SIMA05-08",
                "idnumber" => "SIMA05-08",
                "name" => "Testfrage SIMA05-08",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.5<\/p>
                <p>Trennsch\u00e4rtfe=> 0.43<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5227",
                "catscalename" => "SimA05",
                "attempts" => null,
                "lastattempttime" => "0",
                "userattempts" => null,
                "userlastattempttime" => "0",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.5000",
                "discrimination" => "0.4300",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10737" => (object) [
                "id" => "10737",
                "componentid" => "10737",
                "label" => "SIMA05-09",
                "idnumber" => "SIMA05-09",
                "name" => "Testfrage SIMA05-09",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.55<\/p>
                <p>Trennsch\u00e4rtfe=> 1.49<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5228",
                "catscalename" => "SimA05",
                "attempts" => null,
                "lastattempttime" => "0",
                "userattempts" => null,
                "userlastattempttime" => "0",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.5500",
                "discrimination" => "1.4900",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10738" => (object) [
                "id" => "10738",
                "componentid" => "10738",
                "label" => "SIMA05-10",
                "idnumber" => "SIMA05-10",
                "name" => "Testfrage SIMA05-10",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.42<\/p>
                <p>Trennsch\u00e4rtfe=> 3.93<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5229",
                "catscalename" => "SimA05",
                "attempts" => "6",
                "lastattempttime" => "1709807627",
                "userattempts" => "6",
                "userlastattempttime" => "1709807627",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.4200",
                "discrimination" => "3.9300",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10739" => (object) [
                "id" => "10739",
                "componentid" => "10739",
                "label" => "SIMA05-11",
                "idnumber" => "SIMA05-11",
                "name" => "Testfrage SIMA05-11",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.53<\/p>
                <p>Trennsch\u00e4rtfe=> 4.81<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5230",
                "catscalename" => "SimA05",
                "attempts" => "1",
                "lastattempttime" => "1709211295",
                "userattempts" => "1",
                "userlastattempttime" => "1709211295",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.5300",
                "discrimination" => "4.8100",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10740" => (object) [
                "id" => "10740",
                "componentid" => "10740",
                "label" => "SIMA05-12",
                "idnumber" => "SIMA05-12",
                "name" => "Testfrage SIMA05-12",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.13<\/p>
                <p>Trennsch\u00e4rtfe=> 2.09<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5231",
                "catscalename" => "SimA05",
                "attempts" => "1",
                "lastattempttime" => "1709212179",
                "userattempts" => "1",
                "userlastattempttime" => "1709212179",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.1300",
                "discrimination" => "2.0900",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10741" => (object) [
                "id" => "10741",
                "componentid" => "10741",
                "label" => "SIMA05-13",
                "idnumber" => "SIMA05-13",
                "name" => "Testfrage SIMA05-13",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.39<\/p>
                <p>Trennsch\u00e4rtfe=> 2.94<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5232",
                "catscalename" => "SimA05",
                "attempts" => "1",
                "lastattempttime" => "1709211418",
                "userattempts" => "1",
                "userlastattempttime" => "1709211418",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.3900",
                "discrimination" => "2.9400",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10742" => (object) [
                "id" => "10742",
                "componentid" => "10742",
                "label" => "SIMA05-14",
                "idnumber" => "SIMA05-14",
                "name" => "Testfrage SIMA05-14",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.55<\/p>
                <p>Trennsch\u00e4rtfe=> 2.32<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5233",
                "catscalename" => "SimA05",
                "attempts" => "1",
                "lastattempttime" => "1709211696",
                "userattempts" => "1",
                "userlastattempttime" => "1709211696",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.5500",
                "discrimination" => "2.3200",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10743" => (object) [
                "id" => "10743",
                "componentid" => "10743",
                "label" => "SIMA05-15",
                "idnumber" => "SIMA05-15",
                "name" => "Testfrage SIMA05-15",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.85<\/p>
                <p>Trennsch\u00e4rtfe=> 1.91<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5234",
                "catscalename" => "SimA05",
                "attempts" => "1",
                "lastattempttime" => "1709212024",
                "userattempts" => "1",
                "userlastattempttime" => "1709212024",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.8500",
                "discrimination" => "1.9100",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10744" => (object) [
                "id" => "10744",
                "componentid" => "10744",
                "label" => "SIMA05-16",
                "idnumber" => "SIMA05-16",
                "name" => "Testfrage SIMA05-16",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.2<\/p>
                <p>Trennsch\u00e4rtfe=> 4.35<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5235",
                "catscalename" => "SimA05",
                "attempts" => "5",
                "lastattempttime" => "1709653762",
                "userattempts" => "5",
                "userlastattempttime" => "1709653762",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.2000",
                "discrimination" => "4.3500",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10745" => (object) [
                "id" => "10745",
                "componentid" => "10745",
                "label" => "SIMA05-17",
                "idnumber" => "SIMA05-17",
                "name" => "Testfrage SIMA05-17",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.12<\/p>
                <p>Trennsch\u00e4rtfe=> 1.27<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5236",
                "catscalename" => "SimA05",
                "attempts" => null,
                "lastattempttime" => "0",
                "userattempts" => null,
                "userlastattempttime" => "0",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.1200",
                "discrimination" => "1.2700",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10746" => (object) [
                "id" => "10746",
                "componentid" => "10746",
                "label" => "SIMA05-18",
                "idnumber" => "SIMA05-18",
                "name" => "Testfrage SIMA05-18",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -3.16<\/p>
                <p>Trennsch\u00e4rtfe=> 0.56<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5237",
                "catscalename" => "SimA05",
                "attempts" => null,
                "lastattempttime" => "0",
                "userattempts" => null,
                "userlastattempttime" => "0",
                "model" => "raschbirnbaumb",
                "difficulty" => "-3.1600",
                "discrimination" => "0.5600",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
            "10747" => (object) [
                "id" => "10747",
                "componentid" => "10747",
                "label" => "SIMA05-19",
                "idnumber" => "SIMA05-19",
                "name" => "Testfrage SIMA05-19",
                "questiontext" => "<p dir=\"ltr\" style=\"text-align=> left;\">Schwierigkeit=> -2.41<\/p>
                <p>Trennsch\u00e4rtfe=> 0.6<\/p><p><b>Skala=> A\/A05<\/b><\/p>",
                "qtype" => "multichoice",
                "categoryname" => "Simulation",
                "catscaleid" => "277",
                "testitemstatus" => "0",
                "component" => "question",
                "itemid" => "5238",
                "catscalename" => "SimA05",
                "attempts" => null,
                "lastattempttime" => "0",
                "userattempts" => null,
                "userlastattempttime" => "0",
                "model" => "raschbirnbaumb",
                "difficulty" => "-2.4100",
                "discrimination" => "0.6000",
                "guessing" => "0.0000",
                "timecreated" => "1707311393",
                "timemodified" => "1707311393",
                "status" => "4",
            ],
        ];

    }

    /**
     * Returns array of quizsettings.
     *
     * @return array
     *
     */
    private static function return_quizsettings(): array {
        return [
            "name" => "Neuer Test Test",
            "showdescription" => "0",
            "attempts" => "0",
            "password" => "",
            "browsersecurity" => "0",
            "attemptfeedback" => "",
            "showattemptprogress" => "0",
            "catmodel" => "catquiz",
            "choosetemplate" => "0",
            "testenvironment_addoredittemplate" => "0",
            "catquiz_catscales" => "271",
            "catquiz_subscalecheckbox_272" => "1",
            "catquiz_subscalecheckbox_273" => "1",
            "catquiz_subscalecheckbox_274" => "1",
            "catquiz_subscalecheckbox_275" => "1",
            "catquiz_subscalecheckbox_277" => "1",
            "catquiz_subscalecheckbox_278" => "1",
            "catquiz_subscalecheckbox_279" => "1",
            "catquiz_subscalecheckbox_276" => "1",
            "catquiz_subscalecheckbox_280" => "1",
            "catquiz_subscalecheckbox_281" => "1",
            "catquiz_subscalecheckbox_282" => "1",
            "catquiz_subscalecheckbox_283" => "1",
            "catquiz_subscalecheckbox_284" => "1",
            "catquiz_subscalecheckbox_285" => "1",
            "catquiz_subscalecheckbox_286" => "1",
            "catquiz_subscalecheckbox_287" => "1",
            "catquiz_subscalecheckbox_288" => "1",
            "catquiz_subscalecheckbox_289" => "1",
            "catquiz_subscalecheckbox_290" => "1",
            "catquiz_subscalecheckbox_291" => "1",
            "catquiz_subscalecheckbox_292" => "1",
            "catquiz_subscalecheckbox_293" => "1",
            "catquiz_subscalecheckbox_294" => "1",
            "catquiz_subscalecheckbox_295" => "1",
            "catquiz_passinglevel" => "",
            "catquiz_selectteststrategy" => "4",
            "catquiz_includepilotquestions" => "0",
            "catquiz_selectfirstquestion" => "startwitheasiestquestion",
            "maxquestionsgroup" => [
                "catquiz_minquestions" => 2,
                "catquiz_maxquestions" => 7,
            ],
            "maxquestionsscalegroup" => [
                "catquiz_minquestionspersubscale" => 2,
                "catquiz_maxquestionspersubscale" => 7,
            ],
            "catquiz_standarderrorgroup" => [
                "catquiz_standarderror_min" => 0.1,
                "catquiz_standarderror_max" => 2,
            ],
            "catquiz_includetimelimit" => "0",
            "numberoffeedbackoptionsselect" => "2",
            "catquiz_scalereportcheckbox_271" => "1",
            "feedback_scaleid_limit_lower_271_1" => "-3",
            "feedback_scaleid_limit_upper_271_1" => "0",
            "feedbackeditor_scaleid_271_1" => [
                "text" => "<p dir=\"ltr\" style=\"text-align => left;\">Feedback f\u00fcr Simulation!<\/p>",
                "format" => "1",
                "itemid" => "456277333",
            ],
            "feedbacklegend_scaleid_271_1" => "rot",
            "wb_colourpicker_271_1" => "3",
            "selectedcolour" => "",
            "catquiz_courses_271_1" => [],
            "catquiz_group_271_1" => "",
            "enrolment_message_checkbox_271_1" => "0",
            "feedback_scaleid_limit_lower_271_2" => "0",
            "feedback_scaleid_limit_upper_271_2" => "3",
            "feedbackeditor_scaleid_271_2" => [
                "text" => "<p dir=\"ltr\" style=\"text-align => left;\">Simulation Feedback gr\u00fcn!<\/p>",
                "format" => "1",
                "itemid" => "729257697",
            ],
            "feedbacklegend_scaleid_271_2" => "gr\u00fcn!",
            "wb_colourpicker_271_2" => "6",
            "catquiz_courses_271_2" => [],
            "catquiz_group_271_2" => "",
            "enrolment_message_checkbox_271_2" => "0",
            "catquiz_scalereportcheckbox_272" => "1",
            "feedback_scaleid_limit_lower_272_1" => "-3",
            "feedback_scaleid_limit_upper_272_1" => "0",
            "feedbackeditor_scaleid_272_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "438620153",
            ],
            "feedbacklegend_scaleid_272_1" => "",
            "wb_colourpicker_272_1" => "3",
            "catquiz_courses_272_1" => [],
            "catquiz_group_272_1" => "",
            "enrolment_message_checkbox_272_1" => "0",
            "feedback_scaleid_limit_lower_272_2" => "0",
            "feedback_scaleid_limit_upper_272_2" => "3",
            "feedbackeditor_scaleid_272_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "739292679",
            ],
            "feedbacklegend_scaleid_272_2" => "",
            "wb_colourpicker_272_2" => "6",
            "catquiz_courses_272_2" => [],
            "catquiz_group_272_2" => "",
            "enrolment_message_checkbox_272_2" => "0",
            "catquiz_scalereportcheckbox_273" => "1",
            "feedback_scaleid_limit_lower_273_1" => "-3",
            "feedback_scaleid_limit_upper_273_1" => "0",
            "feedbackeditor_scaleid_273_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "604232012",
            ],
            "feedbacklegend_scaleid_273_1" => "",
            "wb_colourpicker_273_1" => "3",
            "catquiz_courses_273_1" => [],
            "catquiz_group_273_1" => "",
            "enrolment_message_checkbox_273_1" => "0",
            "feedback_scaleid_limit_lower_273_2" => "0",
            "feedback_scaleid_limit_upper_273_2" => "3",
            "feedbackeditor_scaleid_273_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "852008641",
            ],
            "feedbacklegend_scaleid_273_2" => "",
            "wb_colourpicker_273_2" => "6",
            "catquiz_courses_273_2" => [],
            "catquiz_group_273_2" => "",
            "enrolment_message_checkbox_273_2" => "0",
            "catquiz_scalereportcheckbox_274" => "1",
            "feedback_scaleid_limit_lower_274_1" => "-3",
            "feedback_scaleid_limit_upper_274_1" => "0",
            "feedbackeditor_scaleid_274_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "790306459",
            ],
            "feedbacklegend_scaleid_274_1" => "",
            "wb_colourpicker_274_1" => "3",
            "catquiz_courses_274_1" => [],
            "catquiz_group_274_1" => "",
            "enrolment_message_checkbox_274_1" => "0",
            "feedback_scaleid_limit_lower_274_2" => "0",
            "feedback_scaleid_limit_upper_274_2" => "3",
            "feedbackeditor_scaleid_274_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "602522636",
            ],
            "feedbacklegend_scaleid_274_2" => "",
            "wb_colourpicker_274_2" => "6",
            "catquiz_courses_274_2" => [],
            "catquiz_group_274_2" => "",
            "enrolment_message_checkbox_274_2" => "0",
            "catquiz_scalereportcheckbox_275" => "1",
            "feedback_scaleid_limit_lower_275_1" => "-3",
            "feedback_scaleid_limit_upper_275_1" => "0",
            "feedbackeditor_scaleid_275_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "158336160",
            ],
            "feedbacklegend_scaleid_275_1" => "",
            "wb_colourpicker_275_1" => "3",
            "catquiz_courses_275_1" => [],
            "catquiz_group_275_1" => "",
            "enrolment_message_checkbox_275_1" => "0",
            "feedback_scaleid_limit_lower_275_2" => "0",
            "feedback_scaleid_limit_upper_275_2" => "3",
            "feedbackeditor_scaleid_275_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "427741918",
            ],
            "feedbacklegend_scaleid_275_2" => "",
            "wb_colourpicker_275_2" => "6",
            "catquiz_courses_275_2" => [],
            "catquiz_group_275_2" => "",
            "enrolment_message_checkbox_275_2" => "0",
            "catquiz_scalereportcheckbox_277" => "1",
            "feedback_scaleid_limit_lower_277_1" => "-3",
            "feedback_scaleid_limit_upper_277_1" => "0",
            "feedbackeditor_scaleid_277_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "236941169",
            ],
            "feedbacklegend_scaleid_277_1" => "",
            "wb_colourpicker_277_1" => "3",
            "catquiz_courses_277_1" => [],
            "catquiz_group_277_1" => "",
            "enrolment_message_checkbox_277_1" => "0",
            "feedback_scaleid_limit_lower_277_2" => "0",
            "feedback_scaleid_limit_upper_277_2" => "3",
            "feedbackeditor_scaleid_277_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "272879686",
            ],
            "feedbacklegend_scaleid_277_2" => "",
            "wb_colourpicker_277_2" => "6",
            "catquiz_courses_277_2" => [],
            "catquiz_group_277_2" => "",
            "enrolment_message_checkbox_277_2" => "0",
            "catquiz_scalereportcheckbox_278" => "1",
            "feedback_scaleid_limit_lower_278_1" => "-3",
            "feedback_scaleid_limit_upper_278_1" => "0",
            "feedbackeditor_scaleid_278_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "986841251",
            ],
            "feedbacklegend_scaleid_278_1" => "",
            "wb_colourpicker_278_1" => "3",
            "catquiz_courses_278_1" => [],
            "catquiz_group_278_1" => "",
            "enrolment_message_checkbox_278_1" => "0",
            "feedback_scaleid_limit_lower_278_2" => "0",
            "feedback_scaleid_limit_upper_278_2" => "3",
            "feedbackeditor_scaleid_278_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "438443767",
            ],
            "feedbacklegend_scaleid_278_2" => "",
            "wb_colourpicker_278_2" => "6",
            "catquiz_courses_278_2" => [],
            "catquiz_group_278_2" => "",
            "enrolment_message_checkbox_278_2" => "0",
            "catquiz_scalereportcheckbox_279" => "1",
            "feedback_scaleid_limit_lower_279_1" => "-3",
            "feedback_scaleid_limit_upper_279_1" => "0",
            "feedbackeditor_scaleid_279_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "916584383",
            ],
            "feedbacklegend_scaleid_279_1" => "",
            "wb_colourpicker_279_1" => "3",
            "catquiz_courses_279_1" => [],
            "catquiz_group_279_1" => "",
            "enrolment_message_checkbox_279_1" => "0",
            "feedback_scaleid_limit_lower_279_2" => "0",
            "feedback_scaleid_limit_upper_279_2" => "3",
            "feedbackeditor_scaleid_279_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "23475802",
            ],
            "feedbacklegend_scaleid_279_2" => "",
            "wb_colourpicker_279_2" => "6",
            "catquiz_courses_279_2" => [],
            "catquiz_group_279_2" => "",
            "enrolment_message_checkbox_279_2" => "0",
            "catquiz_scalereportcheckbox_276" => "1",
            "feedback_scaleid_limit_lower_276_1" => "-3",
            "feedback_scaleid_limit_upper_276_1" => "0",
            "feedbackeditor_scaleid_276_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "70206690",
            ],
            "feedbacklegend_scaleid_276_1" => "",
            "wb_colourpicker_276_1" => "3",
            "catquiz_courses_276_1" => [],
            "catquiz_group_276_1" => "",
            "enrolment_message_checkbox_276_1" => "0",
            "feedback_scaleid_limit_lower_276_2" => "0",
            "feedback_scaleid_limit_upper_276_2" => "3",
            "feedbackeditor_scaleid_276_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "870359857",
            ],
            "feedbacklegend_scaleid_276_2" => "",
            "wb_colourpicker_276_2" => "6",
            "catquiz_courses_276_2" => [],
            "catquiz_group_276_2" => "",
            "enrolment_message_checkbox_276_2" => "0",
            "catquiz_scalereportcheckbox_280" => "1",
            "feedback_scaleid_limit_lower_280_1" => "-3",
            "feedback_scaleid_limit_upper_280_1" => "0",
            "feedbackeditor_scaleid_280_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "243898310",
            ],
            "feedbacklegend_scaleid_280_1" => "",
            "wb_colourpicker_280_1" => "3",
            "catquiz_courses_280_1" => [],
            "catquiz_group_280_1" => "",
            "enrolment_message_checkbox_280_1" => "0",
            "feedback_scaleid_limit_lower_280_2" => "0",
            "feedback_scaleid_limit_upper_280_2" => "3",
            "feedbackeditor_scaleid_280_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "528033077",
            ],
            "feedbacklegend_scaleid_280_2" => "",
            "wb_colourpicker_280_2" => "6",
            "catquiz_courses_280_2" => [],
            "catquiz_group_280_2" => "",
            "enrolment_message_checkbox_280_2" => "0",
            "catquiz_scalereportcheckbox_281" => "1",
            "feedback_scaleid_limit_lower_281_1" => "-3",
            "feedback_scaleid_limit_upper_281_1" => "0",
            "feedbackeditor_scaleid_281_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "622311123",
            ],
            "feedbacklegend_scaleid_281_1" => "",
            "wb_colourpicker_281_1" => "3",
            "catquiz_courses_281_1" => [],
            "catquiz_group_281_1" => "",
            "enrolment_message_checkbox_281_1" => "0",
            "feedback_scaleid_limit_lower_281_2" => "0",
            "feedback_scaleid_limit_upper_281_2" => "3",
            "feedbackeditor_scaleid_281_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "220586735",
            ],
            "feedbacklegend_scaleid_281_2" => "",
            "wb_colourpicker_281_2" => "6",
            "catquiz_courses_281_2" => [],
            "catquiz_group_281_2" => "",
            "enrolment_message_checkbox_281_2" => "0",
            "catquiz_scalereportcheckbox_282" => "1",
            "feedback_scaleid_limit_lower_282_1" => "-3",
            "feedback_scaleid_limit_upper_282_1" => "0",
            "feedbackeditor_scaleid_282_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "866185775",
            ],
            "feedbacklegend_scaleid_282_1" => "",
            "wb_colourpicker_282_1" => "3",
            "catquiz_courses_282_1" => [],
            "catquiz_group_282_1" => "",
            "enrolment_message_checkbox_282_1" => "0",
            "feedback_scaleid_limit_lower_282_2" => "0",
            "feedback_scaleid_limit_upper_282_2" => "3",
            "feedbackeditor_scaleid_282_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "882967691",
            ],
            "feedbacklegend_scaleid_282_2" => "",
            "wb_colourpicker_282_2" => "6",
            "catquiz_courses_282_2" => [],
            "catquiz_group_282_2" => "",
            "enrolment_message_checkbox_282_2" => "0",
            "catquiz_scalereportcheckbox_283" => "1",
            "feedback_scaleid_limit_lower_283_1" => "-3",
            "feedback_scaleid_limit_upper_283_1" => "0",
            "feedbackeditor_scaleid_283_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "870174961",
            ],
            "feedbacklegend_scaleid_283_1" => "",
            "wb_colourpicker_283_1" => "3",
            "catquiz_courses_283_1" => [],
            "catquiz_group_283_1" => "",
            "enrolment_message_checkbox_283_1" => "0",
            "feedback_scaleid_limit_lower_283_2" => "0",
            "feedback_scaleid_limit_upper_283_2" => "3",
            "feedbackeditor_scaleid_283_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "293702434",
            ],
            "feedbacklegend_scaleid_283_2" => "",
            "wb_colourpicker_283_2" => "6",
            "catquiz_courses_283_2" => [],
            "catquiz_group_283_2" => "",
            "enrolment_message_checkbox_283_2" => "0",
            "catquiz_scalereportcheckbox_284" => "1",
            "feedback_scaleid_limit_lower_284_1" => "-3",
            "feedback_scaleid_limit_upper_284_1" => "0",
            "feedbackeditor_scaleid_284_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "812838393",
            ],
            "feedbacklegend_scaleid_284_1" => "",
            "wb_colourpicker_284_1" => "3",
            "catquiz_courses_284_1" => [],
            "catquiz_group_284_1" => "",
            "enrolment_message_checkbox_284_1" => "0",
            "feedback_scaleid_limit_lower_284_2" => "0",
            "feedback_scaleid_limit_upper_284_2" => "3",
            "feedbackeditor_scaleid_284_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "966612654",
            ],
            "feedbacklegend_scaleid_284_2" => "",
            "wb_colourpicker_284_2" => "6",
            "catquiz_courses_284_2" => [],
            "catquiz_group_284_2" => "",
            "enrolment_message_checkbox_284_2" => "0",
            "catquiz_scalereportcheckbox_285" => "1",
            "feedback_scaleid_limit_lower_285_1" => "-3",
            "feedback_scaleid_limit_upper_285_1" => "0",
            "feedbackeditor_scaleid_285_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "866586526",
            ],
            "feedbacklegend_scaleid_285_1" => "",
            "wb_colourpicker_285_1" => "3",
            "catquiz_courses_285_1" => [],
            "catquiz_group_285_1" => "",
            "enrolment_message_checkbox_285_1" => "0",
            "feedback_scaleid_limit_lower_285_2" => "0",
            "feedback_scaleid_limit_upper_285_2" => "3",
            "feedbackeditor_scaleid_285_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "739680193",
            ],
            "feedbacklegend_scaleid_285_2" => "",
            "wb_colourpicker_285_2" => "6",
            "catquiz_courses_285_2" => [],
            "catquiz_group_285_2" => "",
            "enrolment_message_checkbox_285_2" => "0",
            "catquiz_scalereportcheckbox_286" => "1",
            "feedback_scaleid_limit_lower_286_1" => "-3",
            "feedback_scaleid_limit_upper_286_1" => "0",
            "feedbackeditor_scaleid_286_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "70410854",
            ],
            "feedbacklegend_scaleid_286_1" => "",
            "wb_colourpicker_286_1" => "3",
            "catquiz_courses_286_1" => [],
            "catquiz_group_286_1" => "",
            "enrolment_message_checkbox_286_1" => "0",
            "feedback_scaleid_limit_lower_286_2" => "0",
            "feedback_scaleid_limit_upper_286_2" => "3",
            "feedbackeditor_scaleid_286_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "909817799",
            ],
            "feedbacklegend_scaleid_286_2" => "",
            "wb_colourpicker_286_2" => "6",
            "catquiz_courses_286_2" => [],
            "catquiz_group_286_2" => "",
            "enrolment_message_checkbox_286_2" => "0",
            "catquiz_scalereportcheckbox_287" => "1",
            "feedback_scaleid_limit_lower_287_1" => "-3",
            "feedback_scaleid_limit_upper_287_1" => "0",
            "feedbackeditor_scaleid_287_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "179566669",
            ],
            "feedbacklegend_scaleid_287_1" => "",
            "wb_colourpicker_287_1" => "3",
            "catquiz_courses_287_1" => [],
            "catquiz_group_287_1" => "",
            "enrolment_message_checkbox_287_1" => "0",
            "feedback_scaleid_limit_lower_287_2" => "0",
            "feedback_scaleid_limit_upper_287_2" => "3",
            "feedbackeditor_scaleid_287_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "379981478",
            ],
            "feedbacklegend_scaleid_287_2" => "",
            "wb_colourpicker_287_2" => "6",
            "catquiz_courses_287_2" => [],
            "catquiz_group_287_2" => "",
            "enrolment_message_checkbox_287_2" => "0",
            "catquiz_scalereportcheckbox_288" => "1",
            "feedback_scaleid_limit_lower_288_1" => "-3",
            "feedback_scaleid_limit_upper_288_1" => "0",
            "feedbackeditor_scaleid_288_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "555403158",
            ],
            "feedbacklegend_scaleid_288_1" => "",
            "wb_colourpicker_288_1" => "3",
            "catquiz_courses_288_1" => [],
            "catquiz_group_288_1" => "",
            "enrolment_message_checkbox_288_1" => "0",
            "feedback_scaleid_limit_lower_288_2" => "0",
            "feedback_scaleid_limit_upper_288_2" => "3",
            "feedbackeditor_scaleid_288_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "438134489",
            ],
            "feedbacklegend_scaleid_288_2" => "",
            "wb_colourpicker_288_2" => "6",
            "catquiz_courses_288_2" => [],
            "catquiz_group_288_2" => "",
            "enrolment_message_checkbox_288_2" => "0",
            "catquiz_scalereportcheckbox_289" => "1",
            "feedback_scaleid_limit_lower_289_1" => "-3",
            "feedback_scaleid_limit_upper_289_1" => "0",
            "feedbackeditor_scaleid_289_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "301714681",
            ],
            "feedbacklegend_scaleid_289_1" => "",
            "wb_colourpicker_289_1" => "3",
            "catquiz_courses_289_1" => [],
            "catquiz_group_289_1" => "",
            "enrolment_message_checkbox_289_1" => "0",
            "feedback_scaleid_limit_lower_289_2" => "0",
            "feedback_scaleid_limit_upper_289_2" => "3",
            "feedbackeditor_scaleid_289_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "399438379",
            ],
            "feedbacklegend_scaleid_289_2" => "",
            "wb_colourpicker_289_2" => "6",
            "catquiz_courses_289_2" => [],
            "catquiz_group_289_2" => "",
            "enrolment_message_checkbox_289_2" => "0",
            "catquiz_scalereportcheckbox_290" => "1",
            "feedback_scaleid_limit_lower_290_1" => "-3",
            "feedback_scaleid_limit_upper_290_1" => "0",
            "feedbackeditor_scaleid_290_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "644817590",
            ],
            "feedbacklegend_scaleid_290_1" => "",
            "wb_colourpicker_290_1" => "3",
            "catquiz_courses_290_1" => [],
            "catquiz_group_290_1" => "",
            "enrolment_message_checkbox_290_1" => "0",
            "feedback_scaleid_limit_lower_290_2" => "0",
            "feedback_scaleid_limit_upper_290_2" => "3",
            "feedbackeditor_scaleid_290_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "237433644",
            ],
            "feedbacklegend_scaleid_290_2" => "",
            "wb_colourpicker_290_2" => "6",
            "catquiz_courses_290_2" => [],
            "catquiz_group_290_2" => "",
            "enrolment_message_checkbox_290_2" => "0",
            "catquiz_scalereportcheckbox_291" => "1",
            "feedback_scaleid_limit_lower_291_1" => "-3",
            "feedback_scaleid_limit_upper_291_1" => "0",
            "feedbackeditor_scaleid_291_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "705139951",
            ],
            "feedbacklegend_scaleid_291_1" => "",
            "wb_colourpicker_291_1" => "3",
            "catquiz_courses_291_1" => [],
            "catquiz_group_291_1" => "",
            "enrolment_message_checkbox_291_1" => "0",
            "feedback_scaleid_limit_lower_291_2" => "0",
            "feedback_scaleid_limit_upper_291_2" => "3",
            "feedbackeditor_scaleid_291_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "142604445",
            ],
            "feedbacklegend_scaleid_291_2" => "",
            "wb_colourpicker_291_2" => "6",
            "catquiz_courses_291_2" => [],
            "catquiz_group_291_2" => "",
            "enrolment_message_checkbox_291_2" => "0",
            "catquiz_scalereportcheckbox_292" => "1",
            "feedback_scaleid_limit_lower_292_1" => "-3",
            "feedback_scaleid_limit_upper_292_1" => "0",
            "feedbackeditor_scaleid_292_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "150833516",
            ],
            "feedbacklegend_scaleid_292_1" => "",
            "wb_colourpicker_292_1" => "3",
            "catquiz_courses_292_1" => [],
            "catquiz_group_292_1" => "",
            "enrolment_message_checkbox_292_1" => "0",
            "feedback_scaleid_limit_lower_292_2" => "0",
            "feedback_scaleid_limit_upper_292_2" => "3",
            "feedbackeditor_scaleid_292_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "791078276",
            ],
            "feedbacklegend_scaleid_292_2" => "",
            "wb_colourpicker_292_2" => "6",
            "catquiz_courses_292_2" => [],
            "catquiz_group_292_2" => "",
            "enrolment_message_checkbox_292_2" => "0",
            "catquiz_scalereportcheckbox_293" => "1",
            "feedback_scaleid_limit_lower_293_1" => "-3",
            "feedback_scaleid_limit_upper_293_1" => "0",
            "feedbackeditor_scaleid_293_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "583549774",
            ],
            "feedbacklegend_scaleid_293_1" => "",
            "wb_colourpicker_293_1" => "3",
            "catquiz_courses_293_1" => [],
            "catquiz_group_293_1" => "",
            "enrolment_message_checkbox_293_1" => "0",
            "feedback_scaleid_limit_lower_293_2" => "0",
            "feedback_scaleid_limit_upper_293_2" => "3",
            "feedbackeditor_scaleid_293_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "202968023",
            ],
            "feedbacklegend_scaleid_293_2" => "",
            "wb_colourpicker_293_2" => "6",
            "catquiz_courses_293_2" => [],
            "catquiz_group_293_2" => "",
            "enrolment_message_checkbox_293_2" => "0",
            "catquiz_scalereportcheckbox_294" => "1",
            "feedback_scaleid_limit_lower_294_1" => "-3",
            "feedback_scaleid_limit_upper_294_1" => "0",
            "feedbackeditor_scaleid_294_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "742602749",
            ],
            "feedbacklegend_scaleid_294_1" => "",
            "wb_colourpicker_294_1" => "3",
            "catquiz_courses_294_1" => [],
            "catquiz_group_294_1" => "",
            "enrolment_message_checkbox_294_1" => "0",
            "feedback_scaleid_limit_lower_294_2" => "0",
            "feedback_scaleid_limit_upper_294_2" => "3",
            "feedbackeditor_scaleid_294_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "442729251",
            ],
            "feedbacklegend_scaleid_294_2" => "",
            "wb_colourpicker_294_2" => "6",
            "catquiz_courses_294_2" => [],
            "catquiz_group_294_2" => "",
            "enrolment_message_checkbox_294_2" => "0",
            "catquiz_scalereportcheckbox_295" => "1",
            "feedback_scaleid_limit_lower_295_1" => "-3",
            "feedback_scaleid_limit_upper_295_1" => "0",
            "feedbackeditor_scaleid_295_1" => [
                "text" => "",
                "format" => "1",
                "itemid" => "532234971",
            ],
            "feedbacklegend_scaleid_295_1" => "",
            "wb_colourpicker_295_1" => "3",
            "catquiz_courses_295_1" => [],
            "catquiz_group_295_1" => "",
            "enrolment_message_checkbox_295_1" => "0",
            "feedback_scaleid_limit_lower_295_2" => "0",
            "feedback_scaleid_limit_upper_295_2" => "3",
            "feedbackeditor_scaleid_295_2" => [
                "text" => "",
                "format" => "1",
                "itemid" => "754853655",
            ],
            "feedbacklegend_scaleid_295_2" => "",
            "wb_colourpicker_295_2" => "6",
            "catquiz_courses_295_2" => [],
            "catquiz_group_295_2" => "",
            "enrolment_message_checkbox_295_2" => "0",
            "catmodelfieldsmarker" => 0,
            "gradecat" => "8",
            "gradepass" => null,
            "grademethod" => "1",
            "visible" => 1,
            "visibleoncoursepage" => 1,
            "cmidnumber" => "",
            "lang" => "",
            "groupmode" => "0",
            "groupingid" => "0",
            "availabilityconditionsjson" => "{\"op\" =>\"&\",\"c\" =>[],\"showc\" =>[]}",
            "completionunlocked" => 0,
            "completion" => "1",
            "completionexpected" => 0,
            "tags" => [],
            "coursemodule" => "1019",
            "module" => 30,
            "modulename" => "adaptivequiz",
            "add" => "0",
            "update" => 1019,
            "return" => 1,
            "sr" => 0,
            "beforemod" => 0,
            "competencies" => [],
            "competency_rule" => "0",
            "override_grade" => 0,
            "submitbutton" => "Speichern und anzeigen",
            "mform_isexpanded_id_catmodelheading" => 1,
            "mform_isexpanded_id_catquiz_header" => 1,
            "mform_isexpanded_id_catquiz_teststrategy" => 1,
            "mform_isexpanded_id_catquiz_feedback" => 1,
            "frontend" => true,
            "completionview" => 0,
            "completionpassgrade" => 0,
            "completiongradeitemnumber" => null,
            "conditiongradegroup" => [],
            "conditionfieldgroup" => [],
            "downloadcontent" => 1,
            "intro" => "",
            "introformat" => "1",
            "timemodified" => 1709737727,
            "showabilitymeasure" => 0,
        ];
    }

}
