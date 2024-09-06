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
 * Tests the person ability estimator that uses catcalc.
 *
 * @package    local_catquiz
 * @author     Magdalena Holczik <david.szkiba@wunderbyte.at>
 * @copyright  2024 Wunderbyte <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use advanced_testcase;
use UnexpectedValueException;

/** model_item_param_test
 *
 * @package local_catquiz
 *
 *
 * @covers \local_catquiz\local\model\model_item_param
 */
final class model_item_param_test extends advanced_testcase {
    /**
     * Test import of files
     *
     * @dataProvider read_item_param_from_db_provider
     *
     * @param array $record
     * @param array $parameters
     *
     * @group large
     */
    public function test_read_item_param_from_db(array $record, array $parameters) {
        global $DB;
        $this->resetAfterTest();
        // First insert an item param so that we can later read one.
        $id = $DB->insert_record('local_catquiz_itemparams', $record);
        $itemparam = model_item_param::get($id);
        $this->assertEquals($parameters, $itemparam->get_params_array());
    }

    /**
     * Provide Data for test of save_or_update_testitem_in_db.
     *
     * @return array
     *
     */
    public static function read_item_param_from_db_provider(): array {

        $json = json_encode(['0.00' => 0.12, '0.33' => 0.35, '0.66' => 0.68, '1.00' => 0.83]);
        $pcmgeneralizedjson = json_encode([
            'intercept' => [
                '0.000' => 0.00,
                '0.333' => 0.42,
                '0.666' => 0.57,
                '1.000' => 0.98,
            ]
        ]);
        $pcmjson = json_encode([
            'intercept' => [
                '0.000' => 0.10,
                '0.333' => 0.48,
                '0.666' => 0.53,
                '1.000' => 0.88,
            ]
        ]);
        return [
            'grmgeneralized' => [
                'record' => [
                        'componentid' => 0,
                        'componentname' => "question",
                        'contextid' => 1,
                        'model' => 'grmgeneralized',
                        'difficulty' => -4.45,
                        'discrimination' => 5.92,
                        'guessing' => 0,
                        'status' => 4,
                        'itemid' => 0,
                        'json' => $json,
                    ],
                'parameters' => [
                    'difficulty' => ['0.00' => 0.12, '0.33' => 0.35, '0.66' => 0.68, '1.00' => 0.83],
                    'discrimination' => 5.92,
                ],
            ],
            'rasch' => [
                'record' => [
                        'componentid' => 0,
                        'componentname' => "question",
                        'contextid' => 1,
                        'model' => 'rasch',
                        'difficulty' => -4.45,
                        'discrimination' => 0,
                        'guessing' => 0,
                        'status' => 4,
                        'itemid' => 0,
                        'json' => null,
                    ],
                'parameters' => ['difficulty' => -4.45],
            ],
            'raschbirnbaum' => [
                'record' => [
                        'componentid' => 0,
                        'componentname' => "question",
                        'contextid' => 1,
                        'model' => 'raschbirnbaum',
                        'difficulty' => -1.24,
                        'discrimination' => 0.32,
                        'guessing' => 0,
                        'status' => 4,
                        'itemid' => 0,
                        'json' => null,
                    ],
                'parameters' => ['difficulty' => -1.24, 'discrimination' => 0.32],
            ],
            'mixedraschbirnbaum' => [
                'record' => [
                        'componentid' => 0,
                        'componentname' => "question",
                        'contextid' => 1,
                        'model' => 'mixedraschbirnbaum',
                        'difficulty' => 1.03,
                        'discrimination' => 0.32,
                        'guessing' => 0.42,
                        'status' => 4,
                        'itemid' => 0,
                        'json' => null,
                    ],
                'parameters' => ['difficulty' => 1.03, 'discrimination' => 0.32, 'guessing' => 0.42],
            ],
            'pcmgeneralized' => [
                'record' => [
                        'componentid' => 0,
                        'componentname' => "question",
                        'contextid' => 1,
                        'model' => 'pcmgeneralized',
                        'difficulty' => 0.12,
                        'discrimination' => 2.1,
                        'guessing' => 0,
                        'status' => 4,
                        'itemid' => 0,
                        'json' => $pcmgeneralizedjson,
                    ],
                'parameters' => [
                    'difficulty' => 0.0,
                    'discrimination' => 2.1,
                    'intercept' => ['0.000' => 0.0, '0.333' => 0.42, '0.666' => 0.57, '1.000' => 0.98],
                ],
            ],
            'pcm' => [
                'record' => [
                        'componentid' => 0,
                        'componentname' => "question",
                        'contextid' => 1,
                        'model' => 'pcm',
                        'difficulty' => 0.12,
                        'discrimination' => 2.1,
                        'guessing' => 0,
                        'status' => 4,
                        'itemid' => 0,
                        'json' => $pcmjson,
                    ],
                'parameters' => [
                    'difficulty' => 0.0,
                    'discrimination' => 2.1,
                    'intercept' => ['0.000' => 0.10, '0.333' => 0.48, '0.666' => 0.53, '1.000' => 0.88],
                ],
            ],
        ];
    }

    /**
     * Check if an item param can be saved to the database.
     *
     * @dataProvider write_item_param_to_db_provider
     * @return void
     */
    public function test_write_item_param_to_db($itemparam) {
        $this->resetAfterTest();
        $itemparam->save();

        // Now read the saved parameter and make sure it equals the itemparam we just created.
        $fromdb = model_item_param::get($itemparam->get_id());
        $this->assertEquals($itemparam->get_status(), $fromdb->get_status());
        $this->assertEquals($itemparam->get_componentid(), $fromdb->get_componentid());
        $this->assertEquals($itemparam->get_params_array(), $fromdb->get_params_array());
        $this->assertEquals($itemparam->get_difficulty(), $fromdb->get_difficulty());
        $this->assertEquals($itemparam->get_itemid(), $fromdb->get_itemid());
    }

    /**
     * Provide data for the test_write_item_param_to_db test
     * 
     * @return array
     */
    public static function write_item_param_to_db_provider(): array {
        $grmgeneralizedjson = json_encode([
            'difficulties' => [
                '0.00' => 0.12,
                '0.33' => 0.35,
                '0.66' => 0.68,
                '1.00' => 0.83,
            ]
        ]);
        $pcmgeneralizedjson = json_encode([
            'intercepts' => [
                '0.000' => 0.00,
                '0.333' => 0.42,
                '0.666' => 0.57,
                '1.000' => 0.98,
            ]
        ]);
        $pcmjson = json_encode([
            'intercepts' => [
                '0.000' => 0.10,
                '0.333' => 0.48,
                '0.666' => 0.53,
                '1.000' => 0.88,
            ]
        ]);
        $defaultrecord = [
            'discrimination' => '1.2',
            'contextid' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $grmgeneralizedrecord = (object) array_merge($defaultrecord, ['itemid' => 1, 'json' => $grmgeneralizedjson]);
        $pcmgeneralizedrecord = (object) array_merge($defaultrecord, ['itemid' => 2, 'json' => $pcmgeneralizedjson]);
        $pcmrecord = (object) array_merge($defaultrecord, ['itemid' => 3, 'json' => $pcmjson]);
        $grmgeneralizedparam = new model_item_param(1, 'grmgeneralized', [], 4, $grmgeneralizedrecord);
        $pcmgeneralizedparam = new model_item_param(1, 'pcmgeneralized', [], 4, $pcmgeneralizedrecord);
        $pcmparam = new model_item_param(1, 'pcm', [], 4, $pcmrecord);

        return [
            'grmgeneralized' => [
                'itemparam' => $grmgeneralizedparam,
            ],
            'pcmgeneralized' => [
                'itemparam' => $pcmgeneralizedparam,
            ],
            'pcm' => [
                'itemparam' => $pcmparam,
            ],
        ];
    }
}


