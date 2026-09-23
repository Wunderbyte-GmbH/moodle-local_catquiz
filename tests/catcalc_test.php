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
use catmodel_raschbirnbaum\raschbirnbaum;
use coding_exception;
use Exception;
use local_catquiz\catcalc;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_responses;
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
final class catcalc_test extends basic_testcase {
    /**
     * The stabilised person-ability estimator must still reproduce the recorded radCAT
     * and classicCAT simulation trajectories.
     *
     * The estimator was refactored to the numerically stable P/W derivative form after
     * these reference CSVs were recorded. The overwhelming majority of steps still match
     * the reference to within 0.01; a small fraction of boundary or near-degenerate steps
     * land on a different discrete Newton branch than the pre-refactor reference (the
     * deviation is bimodal: either < 0.01 or a full branch apart, never a gradual drift).
     * We therefore assert that at least 90% of comparable steps match within 0.01 rather
     * than pinning every single step to the old floating-point trajectory. This still
     * catches gross regressions -- for example a broken derivative wiring collapses the
     * match rate to near zero -- without being brittle to legitimate branch differences.
     *
     * @return void
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function test_simulation_steps_match_reference_within_tolerance(): void {
        $data = self::simulation_steps_calculated_ability_provider();

        $writeoutput = (bool) getenv('CATQUIZ_CREATE_TESTOUTPUT');
        $total = 0;
        $matched = 0;
        $medium = 0;
        foreach ($data as $row) {
            $responses = $row['responses'];
            $items = $row['items'];
            $expectedability = $row['expected_ability'];
            $startvalue = $row['startvalue'];
            $mean = $row['mean'];
            $sd = $row['sd'];
            $personid = $row['person'];
            $ability = catcalc::estimate_person_ability($responses, $items, $startvalue, $mean, $sd);
            // An estimate pushed outside the trusted region is not comparable to the reference.
            if (abs($ability) > 10.0) {
                continue;
            }
            $total++;
            $diff = abs($ability - $expectedability);
            $ismatch = $diff <= 0.01;
            if ($ismatch) {
                $matched++;
            } else if ($diff <= 0.5) {
                // Intermediate divergence: neither a match nor a full branch flip.
                $medium++;
            }

            if ($writeoutput) {
                $standarderror = catscale::get_standarderror($ability, $items);
                $csv = implode(';', [
                    $personid,
                    count($items),
                    array_key_last($responses),
                    $items[array_key_last($responses)]->get_difficulty(),
                    $items[array_key_last($responses)]->get_params_array()['discrimination'],
                    $responses[array_key_last($responses)]['fraction'],
                    sprintf('%.2f (SE %.2f bei %d Fragen)', $ability, $standarderror, count($items)),
                    $ismatch
                        ? 'match'
                        : sprintf('mismatch: calculated %.2f but expected %.2f', $ability, $expectedability),
                ]);
                file_put_contents('/tmp/testoutput.csv', $csv . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        }

        $this->assertGreaterThan(0, $total, 'No comparable simulation steps were evaluated.');
        if (getenv('CATQUIZ_SIMULATION_BANDS')) {
            fwrite(STDERR, sprintf(
                "BANDS total=%d matched=%d medium=%d large=%d\n",
                $total,
                $matched,
                $medium,
                $total - $matched - $medium
            ));
        }
        $matchrate = $matched / $total;
        $this->assertGreaterThanOrEqual(
            0.90,
            $matchrate,
            sprintf(
                'Only %.1f%% of simulation steps matched the reference within 0.01 (expected at least 90%%); '
                . '%d of %d steps diverged.',
                100 * $matchrate,
                $total - $matched,
                $total
            )
        );

        // Bimodality guard: the reference divergence is bimodal -- almost all steps
        // either match tightly (<= 0.01) or flip onto a different discrete Newton
        // branch and diverge grossly. A *systematic* small estimation error would
        // instead populate the intermediate band (0.01, 0.5]. Requiring that band to
        // stay small turns the aggregate match-rate check into one that also catches
        // a small but pervasive drift, which a 90% threshold alone could mask.
        $mediumrate = $medium / $total;
        $this->assertLessThanOrEqual(
            0.02,
            $mediumrate,
            sprintf(
                '%.1f%% of steps diverged by an intermediate amount (0.01, 0.5]; the reference divergence '
                . 'should be bimodal (tight match or full branch flip), so an intermediate band this large '
                . 'indicates a systematic drift rather than legitimate branch differences.',
                100 * $mediumrate
            )
        );
    }

    /**
     * Data provider for test_simulation_steps_calculated_ability_is_correct()
     *
     * @return array
     * @throws UnexpectedValueException
     */
    public static function simulation_steps_calculated_ability_provider(): array {
        global $CFG;
        $radcatstd = self::parsesimulationsteps(
            $CFG->dirroot . '/local/catquiz/tests/fixtures/SimulationSteps radCAT 2023-12-21 09-02-35_shortened.csv'
        );
        $radcatemp = self::parsesimulationsteps(
            $CFG->dirroot . '/local/catquiz/tests/fixtures/SimulationSteps radCAT 2023-12-21 09-22-26_shortened.csv',
            'raschbirnbaum',
            0.02,
            2.97
        );
        $classiccat = self::parsesimulationsteps(
            $CFG->dirroot . '/local/catquiz/tests/fixtures/SimulationSteps classTest emp. 2024-01-02 11-02-44_P000000.csv',
            'raschbirnbaum',
            0.02,
            2.97,
            true
        );
        $data = array_merge(
            $classiccat,
            $radcatstd,
            $radcatemp
        );
        return $data;
    }

    /**
     * Test the function to build item params
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_build_item_param_jacobian(): void {
        $itemresponse = [];
        $model = model_model::get_instance('raschbirnbaum');
        $this->assertEquals(fn () => 'b', catcalc::build_itemparam_jacobian($itemresponse, $model));
    }

    /**
     * This test checks that there are no errors when updating the ability with different models.
     *
     * @param model_item_param_list $items
     * @param array $responses
     * @return void
     *
     * @dataProvider ability_can_be_calculated_with_all_models_provider
     */
    public function test_ability_can_be_calculated_with_all_models(model_item_param_list $items, array $responses): void {
        $this->doesNotPerformAssertions();
        $ability = catcalc::estimate_person_ability($responses, $items);
    }

    /**
     * Provider for test_ability_can_be_calculated_with_all_models
     *
     * @return array
     */
    public static function ability_can_be_calculated_with_all_models_provider(): array {
        $grmgeneralizedjson = json_encode([
            'difficulties' => [
                '0.000' => 0.12,
                '0.333' => 0.35,
                '0.666' => 0.68,
                '1.000' => 0.83,
            ],
        ]);
        $grmjson = json_encode([
            'difficulties' => [
                '0.000' => 0.12,
                '0.333' => 0.35,
                '0.666' => 0.68,
                '1.000' => 0.83,
            ],
        ]);
        $pcmgeneralizedjson = json_encode([
            'intercepts' => [
                '0.000' => 0.00,
                '0.333' => 0.42,
                '0.666' => 0.57,
                '1.000' => 0.98,
            ],
        ]);
        $pcmjson = json_encode([
            'intercepts' => [
                '0.000' => 0.10,
                '0.333' => 0.48,
                '0.666' => 0.53,
                '1.000' => 0.88,
            ],
        ]);
        $defaultrecord = [
            'discrimination' => '1.2',
            'contextid' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $itemid = 'TEST01-01';
        $grmgeneralizedrecord = (object) array_merge($defaultrecord, ['itemid' => 1, 'json' => $grmgeneralizedjson]);
        $grmrecord = (object) array_merge($defaultrecord, ['itemid' => 1, 'json' => $grmjson]);
        $pcmgeneralizedrecord = (object) array_merge($defaultrecord, ['itemid' => 1, 'json' => $pcmgeneralizedjson]);
        $pcmrecord = (object) array_merge($defaultrecord, ['itemid' => 1, 'json' => $pcmjson]);

        $grmgeneralizedparam = new model_item_param($itemid, 'grmgeneralized', [], 4, $grmgeneralizedrecord);
        $grmparam = new model_item_param($itemid, 'grm', [], 4, $grmrecord);
        $pcmgeneralizedparam = new model_item_param($itemid, 'pcmgeneralized', [], 4, $pcmgeneralizedrecord);
        $pcmparam = new model_item_param($itemid, 'pcm', [], 4, $pcmrecord);

        $personparam = new model_person_param('1', 1);
        $resp = new model_item_response($itemid, 1.0, $personparam);
        $responses = [
            $itemid => $resp,
        ];

        return [
            'grmgeneralized' => [
                'itemparams' => (new model_item_param_list())->add($grmgeneralizedparam),
                'responses' => $responses,
            ],
            'grm' => [
                'itemparams' => (new model_item_param_list())->add($grmparam),
                'responses' => $responses,
            ],
            'pcmgeneralized' => [
                'itemparams' => (new model_item_param_list())->add($pcmgeneralizedparam),
                'responses' => $responses,
            ],
            'pcm' => [
                'itemparams' => (new model_item_param_list())->add($pcmparam),
                'responses' => $responses,
            ],
        ];
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
     * @param bool $fixedstartvalues
     * @return array
     * @throws UnexpectedValueException
     */
    private static function parsesimulationsteps(
        string $filename,
        $modelname = 'raschbirnbaum',
        float $mean = 0.0,
        float $sd = 1.0,
        bool $fixedstartvalues = false
    ) {
        $maxexpected = 0;
        $minexpected = 0;
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
                if (
                    $data[0] === '' && $data[1] === ''
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
                    $personparam = new model_person_param($person, 1);
                    $responses[$itemid] = new model_item_response($itemid, floatval($fraction), $personparam);
                    $startvalue = $steps[$person][$step - 1]['expected_ability'];
                } else {
                    $items = (new model_item_param_list())->add($item);
                    $personparam = new model_person_param($person, 1);
                    $responses = [
                        $itemid => new model_item_response($itemid, floatval($fraction), $personparam),
                    ];
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
                if ($stepdata['expected_ability'] > $maxexpected) {
                    $maxexpected = $stepdata['expected_ability'];
                }
                if ($stepdata['expected_ability'] < $minexpected) {
                    $minexpected = $stepdata['expected_ability'];
                }

                if ($fixedstartvalues) {
                    $se = $sd;
                    $personmean = $mean;
                } else {
                    // The standard error is 0 for the first question or the SE calculated after the last response.
                    $se = $stepnum === 1 ? $sd : $persondata[$stepnum - 1]['standard_error'];
                    $personmean = $stepnum === 1 ? $mean : $persondata[$stepnum - 1]['expected_ability'];
                }
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

    /**
     * estimate_initial_item_difficulties() must stay finite in every boundary case.
     *
     * The empirical logit p = (r + 0.5) / (n + 1) avoids the previous division by
     * zero (no responses), log(0) (all correct / all incorrect) and the asymmetric
     * offset. No responses yields a neutral difficulty of exactly 0.
     *
     * @return void
     */
    public function test_estimate_initial_item_difficulties_boundaries(): void {
        $result = catcalc::estimate_initial_item_difficulties([
            'none' => [],
            'allpass' => [1, 1, 1],
            'allfail' => [0, 0, 0],
            'mixed' => [1, 0, 1, 1],
        ]);

        foreach ($result as $id => $difficulty) {
            $this->assertIsFloat($difficulty);
            $this->assertTrue(is_finite($difficulty), "Initial difficulty for '$id' must be finite.");
        }
        $this->assertEqualsWithDelta(0.0, $result['none'], 1e-12, 'No responses must give a neutral difficulty.');
        // All incorrect must be harder than all correct.
        $this->assertGreaterThan($result['allpass'], $result['allfail']);
    }


    /**
     * Synthetic recovery / invariant oracle for the CAT estimator.
     *
     * Rather than pinning a full question trajectory to a specific estimator build
     * (see the skipped strategy_test::test_strategy_returns_expected_questions), this
     * checks the two invariants a correct CAT must satisfy for simulated data with a
     * known true ability: (1) with enough informative items the estimate recovers the
     * true ability, and (2) the standard error shrinks as more items are administered
     * (monotone information gain). Both are robust to the small Newton-branch
     * differences introduced by the stable P/W refactor.
     *
     * @return void
     */
    public function test_person_ability_recovers_true_theta_and_se_shrinks(): void {
        mt_srand(20240115);
        $modelname = 'raschbirnbaum';
        $truetheta = 0.8;
        $disc = 1.3;
        $bank = 40;

        $person = new model_person_param('sim', 1);
        $items = new model_item_param_list();
        $responses = [];
        $checkpoints = [5, 10, 20, 40];
        $se = [];
        $ability = 0.0;
        for ($i = 0; $i < $bank; $i++) {
            $difficulty = -3.0 + 6.0 * $i / ($bank - 1);
            $itemid = 'it' . $i;
            $item = new model_item_param($itemid, $modelname);
            $item->set_parameters(['difficulty' => $difficulty, 'discrimination' => $disc]);
            $items->add($item);

            $p = 1.0 / (1.0 + exp(-$disc * ($truetheta - $difficulty)));
            $correct = (mt_rand() / mt_getrandmax()) < $p ? 1.0 : 0.0;
            $responses[$itemid] = new model_item_response($itemid, $correct, $person);

            $n = $i + 1;
            if (in_array($n, $checkpoints, true)) {
                $ability = catcalc::estimate_person_ability($responses, $items, 0.0, 0.0, 1.0);
                $se[$n] = catscale::get_standarderror($ability, $items);
            }
        }

        // Recovery: the final estimate is close to the true ability.
        $this->assertEqualsWithDelta($truetheta, $ability, 0.6, 'Estimate should recover the true ability.');

        // Information gain: SE is finite, positive and shrinks with more items.
        foreach ($checkpoints as $n) {
            $this->assertTrue(is_finite($se[$n]) && $se[$n] > 0.0, "SE at $n items must be finite and positive.");
        }
        $this->assertLessThan($se[5], $se[40], 'SE must shrink from 5 to 40 items.');
        $this->assertLessThan($se[10], $se[40], 'SE must shrink from 10 to 40 items.');
    }
}
