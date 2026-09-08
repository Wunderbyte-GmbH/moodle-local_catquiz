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
 * Deterministic item-parameter recovery test for the dichotomous models.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param;

/**
 * Deterministic item-parameter recovery test for the dichotomous models.
 *
 * Generates responses from known ("ground truth") item parameters under a fixed
 * seed, estimates the parameters back through catcalc::estimate_item_params(),
 * and asserts recovery within a tolerance. This is a self-contained replacement
 * for the former CSV-based recovery fixtures: it needs no external data file.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\catcalc::estimate_item_params
 */
final class itemparam_recovery_test extends advanced_testcase {
    /**
     * Sets the trusted-region settings explicitly so the run is deterministic
     * and independent of whichever plugin defaults happen to be installed.
     *
     * @return void
     */
    private function set_trusted_region_config(): void {
        set_config('trusted_region_factor_sd_a', 3, 'catmodel_rasch');
        set_config('trusted_region_min_a', -5, 'catmodel_rasch');
        set_config('trusted_region_max_a', 5, 'catmodel_rasch');

        foreach (['catmodel_raschbirnbaum', 'catmodel_mixedraschbirnbaum'] as $plugin) {
            set_config('trusted_region_factor_sd_a', 3, $plugin);
            set_config('trusted_region_min_a', -5, $plugin);
            set_config('trusted_region_max_a', 5, $plugin);
            set_config('trusted_region_factor_max_b', 3, $plugin);
            set_config('trusted_region_min_b', 0, $plugin);
            set_config('trusted_region_max_b', 6, $plugin);
            set_config('trusted_region_placement_b', 3, $plugin);
            set_config('trusted_region_slope_b', 3, $plugin);
        }
        set_config('trusted_region_max_c', 0.5, 'catmodel_mixedraschbirnbaum');

        set_config('trusted_region_min_a', -10, 'catmodel_grm');
        set_config('trusted_region_max_a', 10, 'catmodel_grm');
    }

    /**
     * Builds deterministic item responses for a single item under a model.
     *
     * @param model_model $model
     * @param array $truth Ground-truth item parameters.
     * @param int $seed
     * @param int $n Number of simulated persons.
     * @return array Array of model_item_response.
     */
    private function generate_responses(model_model $model, array $truth, int $seed, int $n): array {
        mt_srand($seed);
        $polytomous = $model::is_polytomous();
        $categoryfractions = $polytomous ? array_keys($truth['difficulties']) : [];
        $responses = [];
        for ($i = 0; $i < $n; $i++) {
            $ability = -3.5 + 7.0 * $i / ($n - 1);
            if ($polytomous) {
                // Sample a graded category from its probability under the true params.
                $roll = mt_rand() / mt_getrandmax();
                $cumulative = 0.0;
                $response = (float) end($categoryfractions);
                foreach ($categoryfractions as $fraction) {
                    $cumulative += (float) $model::likelihood(['ability' => $ability], $truth, (float) $fraction);
                    if ($roll <= $cumulative) {
                        $response = (float) $fraction;
                        break;
                    }
                }
            } else {
                $probability = $model::likelihood(['ability' => $ability], $truth, 1.0);
                $response = (mt_rand() / mt_getrandmax()) < $probability ? 1.0 : 0.0;
            }
            $personparam = new model_person_param((string) $i, 1);
            $personparam->set_ability($ability);
            $responses[] = new model_item_response('item1', $response, $personparam);
        }
        return $responses;
    }

    /**
     * Estimated item parameters recover the ground truth within tolerance.
     *
     * @dataProvider recovery_provider
     *
     * @param string $modelname
     * @param array $truth Ground-truth item parameters.
     * @param float $tolerance Maximum accepted absolute deviation per parameter.
     * @return void
     */
    public function test_item_params_recovered(string $modelname, array $truth, float $tolerance): void {
        $this->resetAfterTest(true);
        $this->set_trusted_region_config();

        $model = model_model::get_instance($modelname);
        $responses = $this->generate_responses($model, $truth, 12345, 800);

        $estimated = catcalc::estimate_item_params($responses, $model);

        if (isset($truth['difficulties'])) {
            // Polytomous: compare the free thresholds (fraction > 0) by fraction.
            foreach ($truth['difficulties'] as $fraction => $value) {
                if ((float) $fraction <= 0.0) {
                    continue;
                }
                $estimatedvalue = null;
                foreach ($estimated['difficulties'] as $estfraction => $estvalue) {
                    if (abs((float) $estfraction - (float) $fraction) < 1e-9) {
                        $estimatedvalue = $estvalue;
                        break;
                    }
                }
                $this->assertNotNull($estimatedvalue, "Threshold at fraction $fraction missing for '$modelname'.");
                $this->assertEqualsWithDelta(
                    $value,
                    $estimatedvalue,
                    $tolerance,
                    "Threshold at fraction $fraction not recovered within tolerance for '$modelname'."
                );
            }
        } else {
            foreach ($truth as $name => $value) {
                $this->assertArrayHasKey($name, $estimated);
                $this->assertEqualsWithDelta(
                    $value,
                    $estimated[$name],
                    $tolerance,
                    "Parameter '$name' not recovered within tolerance for model '$modelname'."
                );
            }
        }
    }

    /**
     * Ground-truth parameters and tolerances per dichotomous model.
     *
     * Tolerances are set with a safety margin above the deviations measured on
     * this deterministic dataset (1PL ~0.065, 2PL ~0.075, 3PL ~0.277; the larger
     * 3PL deviation reflects the weak identifiability of the guessing parameter).
     *
     * @return array
     */
    public static function recovery_provider(): array {
        return [
            '1PL' => ['rasch', ['difficulty' => 0.7], 0.15],
            '2PL' => ['raschbirnbaum', ['difficulty' => 0.7, 'discrimination' => 1.3], 0.20],
            '3PL' => [
                'mixedraschbirnbaum',
                ['difficulty' => 0.7, 'discrimination' => 1.3, 'guessing' => 0.2],
                0.40,
            ],
            'GRM' => [
                'grm',
                ['difficulties' => ['0.0' => 0.0, '0.5' => -0.7, '1.0' => 0.9]],
                0.35,
            ],
        ];
    }
}
