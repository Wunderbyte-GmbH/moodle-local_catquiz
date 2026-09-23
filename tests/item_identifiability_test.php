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
 * Tests for the per-item identifiability report (experiment consequence K5).
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
 * Tests for the per-item identifiability report (experiment consequence K5).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\catcalc::item_identifiability_report
 */
final class item_identifiability_test extends advanced_testcase {
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
     * Rebuilds responses from a fixture id.
     *
     * @param string $id
     * @return array [model_model, model_item_response[]]
     */
    private function fixture(string $id): array {
        $fixtures = require(__DIR__ . '/fixtures/edgecase_ip_fixtures.php');
        $fixture = $fixtures[$id];
        $model = model_model::get_instance($fixture['model']);
        $responses = [];
        foreach ($fixture['responses'] as $i => [$ability, $response]) {
            $personparam = new model_person_param((string) $i, 1);
            $personparam->set_ability((float) $ability);
            $responses[] = new model_item_response('item1', (float) $response, $personparam);
        }
        return [$model, $responses];
    }

    /**
     * A cleanly identified item reports well-identified with a small residual.
     *
     * @return void
     */
    public function test_well_identified_item(): void {
        $this->resetAfterTest(true);
        $this->set_trusted_region_config();

        [$model, $responses] = $this->fixture('ip_grmgeneralized_abilitymixture_75_25');
        $ip = catcalc::estimate_item_params($responses, $model);
        $report = catcalc::item_identifiability_report($responses, $model, $ip);

        $this->assertSame(2, $report['observedcategories']);
        $this->assertTrue($report['wellidentified'], 'A converged interior optimum must be well identified.');
        $this->assertFalse($report['atbound']);
        $this->assertEmpty($report['warnings']);
    }

    /**
     * A boundary optimum is reported as at-bound with a warning.
     *
     * @return void
     */
    public function test_boundary_item_is_flagged(): void {
        $this->resetAfterTest(true);
        $this->set_trusted_region_config();

        [$model, $responses] = $this->fixture('ip_grmgeneralized_two_of_five');
        $ip = catcalc::estimate_item_params($responses, $model);
        $report = catcalc::item_identifiability_report($responses, $model, $ip);

        $this->assertSame(2, $report['observedcategories']);
        $this->assertTrue($report['atbound'], 'The discrimination sits at the trusted-region bound.');
        $this->assertFalse($report['wellidentified']);
        $this->assertNotEmpty($report['warnings']);
    }
}
