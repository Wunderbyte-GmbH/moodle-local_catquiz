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
 * @author     David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_ability_estimator_catcalc;
use local_catquiz\local\model\model_responses;

/** model_person_ability_estimator_catcalc_test
 *
 * @package local_catquiz
 *
 * @covers \local_catquiz\local\model\model_person_ability_estimator_catcalc
 */
class model_person_ability_estimator_catcalc_test extends basic_testcase {

    /**
     * @dataProvider person_ability_estimation_returns_expected_values_provider
     */
    public function test_person_ability_estimation_returns_expected_values(
        $expected,
        $modelname,
        $responses,
        $itemparams
    ) {
        foreach ($responses as $scaleid => $modelresponse) {
            $estimator = new model_person_ability_estimator_catcalc($modelresponse);
            $result = $estimator->get_person_abilities($itemparams);
            foreach ($result as $p) {
                echo sprintf(
                    "%s;%s;%s;%f",
                    $modelname,
                    $scaleid,
                    $p->get_id(),
                    $p->get_ability()
                ) . PHP_EOL;
            }
        }
        return $this->assertEquals($expected, $result);
    }
    /**
     * Person_ability_estimation_returns_expected_values_provider.
     *
     * @return array
     */
    public static function person_ability_estimation_returns_expected_values_provider() {
        return [
            [
                'expected' => 1,
                'modelname' => '1PL',
                'responses' => self::createmodelresponse('raschbirnbauma'),
                'itemparams' => self::createitemparams('raschbirnbauma'),
            ],
            [
                'expected' => 1,
                'modelname' => '2PL',
                'responses' => self::createmodelresponse('raschbirnbaumb'),
                'itemparams' => self::createitemparams('raschbirnbaumb'),
            ],
            [
                'expected' => 1,
                'modelname' => '3PL',
                'responses' => self::createmodelresponse('raschbirnbaumc'),
                'itemparams' => self::createitemparams('raschbirnbaumc'),
            ],
        ];
    }
    /**
     * Create model response.
     * @return array
     */
    private static function createmodelresponse($modelname) {
        global $CFG;
        switch ($modelname) {
            case 'raschbirnbauma':
                require_once(
                    $CFG->dirroot . '/local/catquiz/tests/fixtures/responses 1PL.php'
                );
                $data = responses1PL;
                break;
            case 'raschbirnbaumb':
                require_once(
                    $CFG->dirroot . '/local/catquiz/tests/fixtures/responses 2PL.php'
                );
                $data = responses2PL;
                break;
            case 'raschbirnbaumc':
                require_once(
                    $CFG->dirroot . '/local/catquiz/tests/fixtures/responses 3PL.php'
                );
                $data = responses3PL;
                break;

            default:
                throw new \Exception("Unknown model " . $modelname);
        }
        $responsearr = [];
        foreach ($data as $userid => $resp) {
            foreach ($resp as $itemid => $fraction) {
                $scaleid = explode('-', $itemid)[0];
                if (! array_key_exists($scaleid, $responsearr)) {
                    $responsearr[$scaleid] = [$userid => ['component' => []]];
                } else if (! array_key_exists($userid, $responsearr[$scaleid])) {
                    $responsearr[$scaleid][$userid] = ['component' => []];
                }
                $responsearr[$scaleid][$userid]['component'][$itemid] = [
                    'fraction' => $fraction,
                ];
            }
        }

        // Aggregate for an "all" scale that contains all answers.
        $responsearr['all'] = [];
        foreach ($responsearr as $scaleresponses) {
            foreach ($scaleresponses as $userid => $component) {
                if (! array_key_exists($userid, $responsearr['all'])) {
                    $responsearr['all'][$userid] = ['component' => []];
                }
                foreach ($component as $responses) {
                    foreach ($responses as $itemid => $fraction) {
                        $responsearr['all'][$userid]['component'][$itemid] = $fraction;
                    }
                }
            }
        }

        $modelresponses = [];
        foreach ($responsearr as $scaleid => $responses) {
            $modelresponses[$scaleid] = model_responses::create_from_array($responses);
            echo sprintf(
                "%s - Excluded item ids: %s",
                $scaleid,
                implode(',', $modelresponses[$scaleid]->get_excluded_items())
            ) . PHP_EOL;
            echo sprintf(
                "%s - Excluded user ids: %s",
                $scaleid,
                implode(',', $modelresponses[$scaleid]->get_excluded_users())
            ) . PHP_EOL;
        }
        return $modelresponses;
    }
    /**
     * Create item params
     *
     * @return model_item_param_list
     */
    private static function createitemparams($modelname) {
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/tests/fixtures/items.php');
        $itemparamlist = new model_item_param_list();
        foreach (TEST_ITEMS as $scaleid => $items) {
            foreach ($items as $itemid => $values) {
                $ip = (new model_item_param($itemid, $modelname))
                    ->set_parameters([
                        'difficulty' => $values['a'],
                        'discrimination' => $values['b'],
                        'guessing' => $values['c'],
                    ]);
                $itemparamlist->add($ip);
            }
        }
        return $itemparamlist;
    }
}
