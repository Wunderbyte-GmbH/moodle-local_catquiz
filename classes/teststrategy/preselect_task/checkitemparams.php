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
 * Class checkitemparams.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_strategy;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Checks if there are item parameters for the given combination of scale and context.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class checkitemparams extends preselect_task implements wb_middleware {

    /**
     * Run.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array &$context, callable $next): result {
        $selectedscales = [$context['catscaleid'], ...$context['progress']->get_selected_subscales()];
        foreach ($selectedscales as $catscaleid) {
            $catscalecontext = catscale::get_context_id($catscaleid);
            $catscaleids = [
                $catscaleid,
                ...catscale::get_subscale_ids($catscaleid),
            ];
            $itemparamlists = [];
            foreach (array_keys(model_strategy::get_installed_models()) as $model) {
                $itemparamlists[$model] = count(model_item_param_list::load_from_db(
                    $catscalecontext,
                    $model,
                    $catscaleids
                ));
            }
            if (array_sum($itemparamlists) === 0) {
                $context['progress']->drop_scale($catscaleid);
                unset($selectedscales[array_search($catscaleid, $selectedscales)]);
            }
        }

        // If there are no active scales left, show a message that the quiz can not be started.
        if (!$selectedscales) {
                return result::err(status::ERROR_NO_ITEMS);
        }

        return $next($context);
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'progress',
        ];
    }
}
