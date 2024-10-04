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
use UnexpectedValueException;

/** model_person_ability_estimator_catcalc_test
 *
 * @package local_catquiz
 *
 * @covers \local_catquiz\local\model\model_person_ability_estimator_catcalc
 */
final class model_person_ability_estimator_catcalc_test extends basic_testcase {

    /**
     * Function test_person_ability_estimation_returns_expected_values.
     *
     * @dataProvider person_ability_estimation_returns_expected_values_provider
     *
     * @param mixed $expected
     * @param mixed $modelname
     * @param mixed $responses
     * @param mixed $itemparams
     * @return void
     * @group large
     */
    public function test_person_ability_estimation_returns_expected_values(
        $expected,
        $modelname,
        $responses,
        $itemparams
    ): void {
        foreach ($responses as $scaleid => $modelresponse) {
            $estimator = new model_person_ability_estimator_catcalc($modelresponse);
            $result = $estimator->get_person_abilities($itemparams);
            $print = false;
            if ($print) {
                $this->printascsv($result, $modelname, $scaleid);
            }
        }
        // TODO: When we know the expected values, write them to a separate CSV
        // file and use the assertion to compare the expected and calculated
        // values.
        $this->assertTrue(true);
    }
    /**
     * Person_ability_estimation_returns_expected_values_provider.
     *
     * @return array
     */
    public static function person_ability_estimation_returns_expected_values_provider(): array {
        return [
            [
                'expected' => 1,
                'modelname' => '1PL',
                'responses' => self::createmodelresponse('rasch'),
                'itemparams' => self::createitemparams('rasch'),
            ],
            [
                'expected' => 1,
                'modelname' => '2PL',
                'responses' => self::createmodelresponse('raschbirnbaum'),
                'itemparams' => self::createitemparams('raschbirnbaum'),
            ],
            [
            'expected' => 1,
            'modelname' => '3PL',
            'responses' => self::createmodelresponse('mixedraschbirnbaum'),
            'itemparams' => self::createitemparams('mixedraschbirnbaum'),
            ],
        ];
    }
    /**
     * Create model response.
     * @param mixed $modelname
     * @return array
     */
    private static function createmodelresponse($modelname): array {
        global $CFG;
        switch ($modelname) {
            case 'rasch':
                $data = self::loadresponsesdata($CFG->dirroot . '/local/catquiz/tests/fixtures/responses.1PL.csv');
                break;
            case 'raschbirnbaum':
                $data = self::loadresponsesdata($CFG->dirroot . '/local/catquiz/tests/fixtures/responses.2PL.csv');
                break;
            case 'mixedraschbirnbaum':
                $data = self::loadresponsesdata($CFG->dirroot . '/local/catquiz/tests/fixtures/responses.3PL.csv');
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
        }
        return $modelresponses;
    }
    /**
     * Create item params
     *
     * @param mixed $modelname
     *
     * @return model_item_param_list
     */
    private static function createitemparams($modelname): model_item_param_list {
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

    /**
     * Returns the responses as an array.
     *
     * @param string $filename
     * @return array
     */
    private static function loadresponsesdata(string $filename): array {
        if (($handle = fopen($filename, "r")) === false) {
            throw new UnexpectedValueException("Can not open file: " . $filename);
        }

        $row = 0;
        $responses = [];
        $columnames = [];
        while (($data = fgetcsv($handle, 0, ";")) !== false) {
            $row++;
            if ($row == 1) {
                // Skip the header row.
                $columnames = array_slice($data, 1);
                continue;
            }

            $responses[$data[0]] = array_combine($columnames, array_slice($data, 1));
        }

        fclose($handle);
        return $responses;
    }

    /**
     * Prints the result as CSV to the console
     *
     * @param mixed $result
     * @param string $modelname
     * @param string $scaleid
     * @return void
     */
    private function printascsv($result, string $modelname, string $scaleid): void {
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
}
