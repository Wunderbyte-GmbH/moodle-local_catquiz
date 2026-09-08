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
 * Regression tests over the pathological IP edge-case fixtures.
 *
 * The fixtures (tests/fixtures/edgecase_ip_fixtures.php) persist the immutable
 * responses of hard estimation geometries (near-zero discrimination, missing /
 * empty polytomous categories, bimodal ability support). These tests assert the
 * behaviour established experimentally in session 013:
 *  - weakly identified (W) / not-identified (N) cases: a robust optimiser (BFGS)
 *    reaches a finite objective and never diverges below the start value;
 *  - degenerate cases (GRM-family missing bottom category): the empirical start
 *    thresholds are non-finite. This is documented, not silently accepted -- a
 *    future fix of empirical_start_thresholds() will flip this assertion.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param;

/**
 * Regression tests over the pathological IP edge-case fixtures.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\catcalc::build_itemparam_objective
 * @covers \local_catquiz\catcalc::build_itemparam_jacobian
 */
final class edgecase_ip_test extends advanced_testcase {
    /**
     * Sets a deterministic, wide trusted region for the polytomous models.
     *
     * @return void
     */
    private function set_trusted_region_config(): void {
        foreach (['catmodel_grmgeneralized', 'catmodel_pcmgeneralized', 'catmodel_grm', 'catmodel_pcm'] as $plugin) {
            set_config('trusted_region_factor_sd_a', 3, $plugin);
            set_config('trusted_region_min_a', 0.01, $plugin);
            set_config('trusted_region_max_a', 10, $plugin);
            set_config('trusted_region_factor_max_b', 3, $plugin);
            set_config('trusted_region_min_b', -10, $plugin);
            set_config('trusted_region_max_b', 10, $plugin);
            set_config('trusted_region_placement_b', 0, $plugin);
            set_config('trusted_region_slope_b', 3, $plugin);
        }
    }

    /**
     * Rebuilds model_item_response objects from a fixture's stored pairs.
     *
     * @param array $fixture
     * @return array
     */
    private function responses_from_fixture(array $fixture): array {
        $responses = [];
        foreach ($fixture['responses'] as $i => [$ability, $response]) {
            $personparam = new model_person_param((string) $i, 1);
            $personparam->set_ability((float) $ability);
            $responses[] = new model_item_response('item1', (float) $response, $personparam);
        }
        return $responses;
    }

    /**
     * The fixtures behave as their persisted expectation class prescribes.
     *
     * @dataProvider fixture_provider
     *
     * @param array $fixture
     * @return void
     */
    public function test_edgecase_behaviour(array $fixture): void {
        $this->resetAfterTest(true);
        $this->set_trusted_region_config();

        $model = model_model::get_instance($fixture['model']);
        $responses = $this->responses_from_fixture($fixture);

        $startip = $model::get_start_ip($responses);
        $thresholdkey = isset($startip['difficulties']) ? 'difficulties' : 'intercepts';
        $fractions = array_keys($startip[$thresholdkey]);
        $z0 = $model::convert_ip_to_vector($startip);

        $objective = catcalc::build_itemparam_objective($responses, $model);
        $jacobian = catcalc::build_itemparam_jacobian($responses, $model);
        $tofrac = fn($vector) => $model::convert_vector_to_ip($vector, $fractions);
        $lstart = $objective($tofrac($z0));

        if ($fixture['expected'] === 'reduced_structure_limitation') {
            // K2 guarantees a finite start objective even when the bottom category
            // is unobserved. Estimating the resulting reduced (baseline-shifted)
            // category structure is a separate, documented limitation (follow-up
            // K3: the graded jacobian and codec disagree on the free dimension).
            $this->assertTrue(
                is_finite((float) $lstart),
                "K2 must give a finite start objective for {$fixture['id']}."
            );
            return;
        }

        if ($fixture['expected'] === 'degenerate_start_thresholds') {
            // Documented known failure: the GRM-family start thresholds are not
            // finite when the baseline category is unobserved. A future fix of
            // empirical_start_thresholds() must update this expectation.
            $this->assertFalse(
                is_finite((float) $lstart),
                "Fixture {$fixture['id']} is expected to have non-finite start thresholds "
                . '(remove it from the degenerate set once the start heuristic is fixed).'
            );
            return;
        }

        $this->assertTrue(is_finite((float) $lstart), "Start objective must be finite for {$fixture['id']}.");

        // A robust optimiser must reach a finite objective without diverging below
        // the start value on these hard-but-solvable geometries (W and N).
        $objectivevec = fn($vector) => $objective($tofrac($vector));
        $jacobianvec = fn($vector) => $jacobian($tofrac($vector));
        $restrict = fn($vector) => $model::convert_ip_to_vector(
            $model::restrict_to_trusted_region($tofrac($vector))
        );

        $solution = mathcat::bfgs($objectivevec, $jacobianvec, $z0, 6, 100, $restrict);
        $lsolution = $objectivevec(array_values($solution));

        $this->assertTrue(
            is_finite((float) $lsolution),
            "BFGS must return a finite objective for {$fixture['id']}."
        );
        $this->assertGreaterThanOrEqual(
            (float) $lstart - 1e-6,
            (float) $lsolution,
            "BFGS must not diverge below the start objective for {$fixture['id']}."
        );
    }

    /**
     * Provides every persisted edge-case fixture.
     *
     * @return array
     */
    public static function fixture_provider(): array {
        $fixtures = require(__DIR__ . '/fixtures/edgecase_ip_fixtures.php');
        $cases = [];
        foreach ($fixtures as $id => $fixture) {
            $cases[$id] = [$fixture];
        }
        return $cases;
    }
}
