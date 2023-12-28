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
 * Tests the catcalc functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use coding_exception;
use Exception;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use moodle_exception;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;
use UnexpectedValueException;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/local/catquiz/tests/lib.php');

/**
 * Tests the catcalc functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\catcalc
 *
 */
class catcalc_test extends basic_testcase {

    /**
     * Tests if the ability is calculated correctly
     *
     * @param array                 $responses        The response pattern
     * @param model_item_param_list $items            A list of item params
     * @param float                 $expectedability  The expected ability.
     *
     * @dataProvider estimate_person_ability_provider
     */
    public function test_estimate_person_ability($responses, model_item_param_list $items, float $expectedability) {
        $ability = catcalc::estimate_person_ability($responses, $items);
        // Remove the next line when ability calculation works.
        $this->markTestIncomplete('The ability estimation does not yet calculate the expected values.');
        $this->assertEquals($expectedability, sprintf('%.2f', $ability));
    }

    /**
     * Dataprovider for the associated test function.
     *
     * Loads person responses and expected abilities from CSV files to create
     * data expected by the test function.
     */
    public static function estimate_person_ability_provider(): array {
        global $CFG;
        $person = 0;
        $responses = loadresponsesdata($CFG->dirroot . '/local/catquiz/tests/fixtures/responses.2PL.csv', $person);
        $abilities = self::loadabilities($CFG->dirroot . '/local/catquiz/tests/fixtures/persons.csv', $person);
        foreach ($responses as $label => $correct) {
            $responses[$label] = ['fraction' => floatval($correct)];
        }
        $items = self::parseitemparams($CFG->dirroot . '/local/catquiz/tests/fixtures/simulation.csv');
        return [
            'all' => [
                $responses,
                $items,
                $abilities['Gesamt'],
            ],
            'A01' => [
                self::filterforlabel('A01', $responses),
                $items,
                $abilities['A01'],
            ],
            'A02' => [
                self::filterforlabel('A02', $responses),
                $items,
                $abilities['A02'],
            ],
            'A03' => [
                self::filterforlabel('A03', $responses),
                $items,
                $abilities['A03'],
            ],
            'A04' => [
                self::filterforlabel('A04', $responses),
                $items,
                $abilities['A04'],
            ],
            'A05' => [
                self::filterforlabel('A05', $responses),
                $items,
                $abilities['A05'],
            ],
            'A06' => [
                self::filterforlabel('A06', $responses),
                $items,
                $abilities['A06'],
            ],
            'A07' => [
                self::filterforlabel('A07', $responses),
                $items,
                $abilities['A07'],
            ],
            'B01' => [
                self::filterforlabel('B01', $responses),
                $items,
                $abilities['B01'],
            ],
            'B02' => [
                self::filterforlabel('B02', $responses),
                $items,
                $abilities['B02'],
            ],
            'B03' => [
                self::filterforlabel('B03', $responses),
                $items,
                $abilities['B03'],
            ],
            'B04' => [
                self::filterforlabel('B04', $responses),
                $items,
                $abilities['B04'],
            ],
            'C01' => [
                self::filterforlabel('C01', $responses),
                $items,
                $abilities['C01'],
            ],
            'C02' => [
                self::filterforlabel('C02', $responses),
                $items,
                $abilities['C02'],
            ],
            'C03' => [
                self::filterforlabel('C03', $responses),
                $items,
                $abilities['C03'],
            ],
            'C04' => [
                self::filterforlabel('C04', $responses),
                $items,
                $abilities['C04'],
            ],
            'C05' => [
                self::filterforlabel('C05', $responses),
                $items,
                $abilities['C05'],
            ],
            'C06' => [
                self::filterforlabel('C06', $responses),
                $items,
                $abilities['C06'],
            ],
            'C07' => [
                self::filterforlabel('C07', $responses),
                $items,
                $abilities['C07'],
            ],
            'C08' => [
                self::filterforlabel('C08', $responses),
                $items,
                $abilities['C08'],
            ],
            'C09' => [
                self::filterforlabel('C09', $responses),
                $items,
                $abilities['C09'],
            ],
            'C10' => [
                self::filterforlabel('C10', $responses),
                $items,
                $abilities['C10'],
            ],
        ];
    }

    /**
     * Compares our results with the ones from the SimulatinoSteps radikaler CAT CSV
     *
     * @param mixed $responses
     * @param model_item_param_list $items
     * @param float $expectedability
     * @param float $startvalue
     * @param float $mean
     * @param float $sd
     * @param string $personid
     * @return void
     * @throws coding_exception
     * @throws Exception
     * @throws moodle_exception
     * @throws MatrixException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     * @dataProvider simulation_steps_calculated_ability_provider
     */
    public function test_simulation_steps_calculated_ability(
        $responses,
        model_item_param_list $items,
        float $expectedability,
        float $startvalue,
        float $mean,
        float $sd,
        string $personid
    ) {
        $ability = catcalc::estimate_person_ability($responses, $items, $startvalue, $mean, $sd);
        $standarderror = catscale::get_standarderror($ability, $items);

        // If the CATQUIZ_CREATE_TESTOUTPUT environment variable is set, write a
        // CSV file with information about the test results.
        if (getenv('CATQUIZ_CREATE_TESTOUTPUT')) {
            $csv = implode(';', [
                $personid,
                count($items),
                array_key_last($responses),
                $items[array_key_last($responses)]->get_difficulty(),
                $items[array_key_last($responses)]->get_params_array()['discrimination'],
                $responses[array_key_last($responses)]['fraction'],
                sprintf('%.2f (SE %.2f bei %d Fragen)', $ability, $standarderror, count($items)),
                ($ability - $expectedability) <= 0.01
                    ? 'match'
                    : sprintf('mismatch: calculated %.2f but expected %.2f', $ability, $expectedability),
            ]);

            $file = '/tmp/testoutput.csv';
            file_put_contents($file, $csv . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        $this->assertEqualsWithDelta($expectedability, $ability, 0.01);
    }

    /**
     * Data provider for test_simulation_steps_calculated_ability_is_correct()
     *
     * @return array
     * @throws UnexpectedValueException
     */
    public static function simulation_steps_calculated_ability_provider(): array {
        global $CFG;
        $radcat1 = self::parsesimulationsteps(
            $CFG->dirroot . '/local/catquiz/tests/fixtures/SimulationSteps radCAT 2023-12-21 09-02-35.csv'
        );
        $radcat2 = self::parsesimulationsteps(
            $CFG->dirroot . '/local/catquiz/tests/fixtures/SimulationSteps radCAT 2023-12-21 09-22-26.csv',
            'raschbirnbaumb',
            0.02,
            2.97
        );
        $data = array_merge($radcat1, $radcat2);
        return $data;
    }

    /**
     * Internal function to filter responses to questions with a certain label.
     *
     * @param string $label     The label to filter for.
     * @param array  $responses The responses array.
     */
    private static function filterforlabel(string $label, array $responses): array {
        return array_filter($responses, fn($l) => preg_match(sprintf('/^SIM%s\-/', $label), $l), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Parses the results for the step-wise calculation of person abilities from a CSV file
     *
     * @param string $filename
     * @param string $modelname
     * @param float $mean
     * @param float $sd
     * @return array
     * @throws UnexpectedValueException
     */
    private static function parsesimulationsteps(
        string $filename,
        $modelname = 'raschbirnbaumb',
        float $mean = 0.0,
        float $sd = 1.0
    ) {
        if (($handle = fopen($filename, "r")) === false) {
            throw new UnexpectedValueException("Can not open file: " . $filename);
        }

        $row = 0;
        $inpersonrange = false;
        $steps = [];
        $person = '';
        while (($data = fgetcsv($handle, 0, ";")) !== false) {
            $row++;
            if ($row <= 5) {
                // The first two row contains no relevant data.
                continue;
            }

            if ($data[0] !== '' && empty($data[1])) {
                $inpersonrange = true;
                $person = $data[0];
                continue;
            }

            if ($data[0] === "Time:") {
                continue;
            }

            if ($inpersonrange) {
                if ($data[0] === '' && $data[1] === ''
                || $data[0] === $person && $data[1] !== '' && $data[2] === ''
                || $data[0] === $person && ! is_numeric($data[1])
                ) {
                    $inpersonrange = false;
                    $person = '';
                    continue;
                }

                $step = $data[1];
                $itemid = $data[2];
                $difficulty = floatval($data[3]);
                $discrimination = floatval($data[4]);
                $fraction = floatval($data[5]);
                $item = new model_item_param($itemid, $modelname);
                $item->set_parameters([
                    'difficulty' => $difficulty,
                    'discrimination' => $discrimination,
                ]);

                if ($step > 1) {
                    $items = clone($steps[$person][$step - 1]['items']);
                    $items->add($item);
                    $responses = $steps[$person][$step - 1]['responses'];
                    $responses[$itemid] = ['fraction' => floatval($fraction)];
                    $startvalue = $steps[$person][$step - 1]['expected_ability'];
                } else {
                    $items = (new model_item_param_list())->add($item);
                    $responses = [$itemid => ['fraction' => floatval($fraction)]];
                    $startvalue = $mean;
                }
                $steps[$person][$step]['items'] = $items;
                $steps[$person][$step]['responses'] = $responses;
                preg_match('/(.*)\s\(SE\s(.*)\sbei/', $data[6], $matches);
                $steps[$person][$step]['expected_ability'] = floatval($matches[1]);
                $steps[$person][$step]['standard_error'] = floatval($matches[2]);
                $steps[$person][$step]['startvalue'] = $startvalue;
            }
        }
        fclose($handle);

        $result = [];
        foreach ($steps as $personid => $persondata) {
            foreach ($persondata as $stepnum => $stepdata) {
                // The standard error is 0 for the first question or the SE calculated after the last response.
                $se = $stepnum === 1 ? $sd : $persondata[$stepnum - 1]['standard_error'];
                $personmean = $stepnum === 1 ? $mean : $persondata[$stepnum - 1]['expected_ability'];
                $result[sprintf('%s: %s Step %d', basename($filename), $personid, $stepnum)] = [
                    'responses' => $stepdata['responses'],
                    'items' => $stepdata['items'],
                    'expected_ability' => $stepdata['expected_ability'],
                    'startvalue' => $stepdata['startvalue'],
                    'mean' => $personmean,
                    'sd' => $se,
                    'person' => $personid,
                ];
            }
        }
        return $result;
    }

    /**
     * Creates a model_item_param_list from the data in a CSV file.
     *
     * @param string $filename The path to the CSV file.
     */
    public static function parseitemparams(string $filename): model_item_param_list {
        if (($handle = fopen($filename, "r")) === false) {
            throw new UnexpectedValueException("Can not open file: " . $filename);
        }

        $itemparams = new model_item_param_list();

        $row = 0;
        while (($data = fgetcsv($handle, 0, ";")) !== false) {
            $row++;
            if ($row == 1) {
                // The first row contains the header.
                // Prefix the label with SIM. E.g., A01-01 will become SIMA01-01.
                continue;
            }
            $item = new model_item_param($data[7], $data[3], [], 4);
            $item->set_parameters([
                'difficulty' => floatval($data[4]),
                'discrimination' => floatval($data[5]),
                'guessing' => floatval($data[6]),
            ]);
            $itemparams->add($item);
        }

        fclose($handle);
        return $itemparams;
    }

    /**
     * Loads person abilities from a CSV file.
     *
     * @param string $filename The path to the CSV file.
     * @param int    $personnum If given, load abilities of the Nth person.
     */
    public static function loadabilities($filename, $personnum = 0): array {
        if (($handle = fopen($filename, "r")) === false) {
            throw new UnexpectedValueException("Can not open file: " . $filename);
        }

        $header = [];
        $row = 0;
        while (($data = fgetcsv($handle, 0, ";")) !== false) {
            $row++;
            if ($row == 1) {
                // The first row contains the header.
                // Replace the X/ from the label so that it matches the other data.
                $header = array_map(
                    fn($scale) => preg_replace('/^\w\//', '', $scale),
                    array_slice($data, 1)
                );
                continue;
            }
            if ($row < $personnum) {
                continue;
            }
            $abilities = array_map(
                fn($a) => floatval($a),
                array_slice($data, 1)
            );
            break;
        }

        fclose($handle);
        return array_combine($header, $abilities);
    }
}
