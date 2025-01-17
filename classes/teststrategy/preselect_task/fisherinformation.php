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
 * Class fisherinformation.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_model;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;

/**
 * Test strategy fisherinformation.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fisherinformation extends preselect_task {
    /**
     * Run preselect task.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array &$context): result {
        foreach ($context['questions'] as $item) {
            // Just skip questions where we can not calculate the fisher information.
            if (!array_key_exists($item->model, $context['installed_models'])) {
                continue;
            }

            $model = $context['installed_models'][$item->model];

            $item->fisherinformation = [];
            foreach ($context['progress']->get_abilities() as $catscaleid => $ability) {
                $fisherinformation = $this->get_fisherinformation($item, $ability, $model);
                $item->fisherinformation[$catscaleid] = $fisherinformation;
                // In order to calculate the standarderror per scale, we need the
                // fisher information for all questions there.
                $context['original_questions'][$item->id]->fisherinformation[$catscaleid] = $fisherinformation;
            }
        }

        $context['has_fisherinformation'] = true;
        return result::ok($context);
    }

    /**
     * Returns the fisherinformation.
     *
     * @param \stdClass $question
     * @param float $ability
     *
     * @return ?float
     */
    public function get_fisherinformation(\stdClass $question, float $ability): ?float {
        $itemparam = model_item_param::from_record($question);
        $model = model_model::get_instance($question->model);

        $fisherinformation = $model->fisher_info(
            ['ability' => $ability],
            $itemparam->get_params_array()
        );
        return $fisherinformation;
    }
}
