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

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param;

/**
 * Guards the psychometric monotonicity of the base person-ability estimation:
 * for a monotone dichotomous IRT model with valid parameters (positive
 * discrimination), an additional correct answer must never lower the ability and
 * an additional wrong answer must never raise it. This is asserted against
 * catcalc::estimate_person_ability directly, i.e. the regularised (MAP/trusted
 * region) estimate on the REAL responses only - no synthetic response is
 * involved.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catcalc::estimate_person_ability
 */
final class ability_monotonicity_test extends advanced_testcase {
    /**
     * Dichotomous models with valid parameters (positive discrimination).
     *
     * @return array
     */
    public static function model_provider(): array {
        return [
            '1PL' => ['rasch', ['difficulty' => 0.0]],
            '2PL' => ['raschbirnbaum', ['difficulty' => 0.0, 'discrimination' => 1.3]],
            '3PL' => ['mixedraschbirnbaum', ['difficulty' => 0.0, 'discrimination' => 1.3, 'guessing' => 0.2]],
        ];
    }

    /**
     * An all-correct sequence never decreases the estimated ability.
     *
     * @dataProvider model_provider
     * @param string $modelname
     * @param array $baseparams
     */
    public function test_all_correct_never_decreases_ability(string $modelname, array $baseparams): void {
        $abilities = $this->run_sequence($modelname, $baseparams, 1.0, 8);
        for ($i = 1, $c = count($abilities); $i < $c; $i++) {
            $this->assertGreaterThanOrEqual(
                $abilities[$i - 1] - 1e-6,
                $abilities[$i],
                "An additional correct answer must not decrease the ability ($modelname, step $i)."
            );
        }
        $this->assertGreaterThan($abilities[0], end($abilities), "All-correct must raise the ability ($modelname).");
    }

    /**
     * An all-wrong sequence never increases the estimated ability.
     *
     * @dataProvider model_provider
     * @param string $modelname
     * @param array $baseparams
     */
    public function test_all_wrong_never_increases_ability(string $modelname, array $baseparams): void {
        $abilities = $this->run_sequence($modelname, $baseparams, 0.0, 8);
        for ($i = 1, $c = count($abilities); $i < $c; $i++) {
            $this->assertLessThanOrEqual(
                $abilities[$i - 1] + 1e-6,
                $abilities[$i],
                "An additional wrong answer must not increase the ability ($modelname, step $i)."
            );
        }
        $this->assertLessThan($abilities[0], end($abilities), "All-wrong must lower the ability ($modelname).");
    }

    /**
     * Administers $n items of graded difficulty, all answered with $fraction, and
     * returns the estimated ability after each response.
     *
     * @param string $modelname
     * @param array $baseparams
     * @param float $fraction
     * @param int $n
     * @return float[]
     */
    private function run_sequence(string $modelname, array $baseparams, float $fraction, int $n): array {
        $this->resetAfterTest();
        $person = new model_person_param('sim', 1);
        $items = new model_item_param_list();
        $responses = [];
        $abilities = [];
        for ($i = 0; $i < $n; $i++) {
            $difficulty = -2.0 + 4.0 * $i / max(1, $n - 1);
            $params = array_merge($baseparams, ['difficulty' => $difficulty]);
            $itemid = 'it' . $i;
            $item = new model_item_param($itemid, $modelname);
            $item->set_parameters($params);
            $items->add($item);
            $responses[$itemid] = new model_item_response($itemid, $fraction, $person);
            $abilities[] = catcalc::estimate_person_ability($responses, $items, 0.0, 0.0, 1.0);
        }
        return $abilities;
    }
}
