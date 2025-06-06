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
 * Class addscalestandarderror.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\catscale;
use local_catquiz\local\model\model_responses;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;

/**
 * Calculates the standarderror for each available catscale.
 *
 * Note: adds the array 'se[$scaleid]' to the $context.
 *
 * The values in 'se' are based on the FI and hence ability in the respective
 * (sub)scale.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addscalestandarderror extends preselect_task {

    /**
     * @var progress $progress
     */
    private progress $progress;

    /**
     * Run method.
     *
     * @param array $context
     *
     * @return result
     *
     */
    public function run(array &$context): result {
        $this->progress = $context['progress'];
        $responses = $this->progress->get_user_responses();
        if (! $responses) {
            return result::ok($context);
        }

        $userresponses = model_responses::create_from_array([$context['userid'] => ['component' => $responses]]);
        foreach ($context['person_ability'] as $catscaleid => $ability) {
            $items = $userresponses->get_items_for_scale($catscaleid, $context['contextid']);
            $se = catscale::get_standarderror($ability, $items, INF);
            $context['se'][$catscaleid] = $se;
        }

        return result::ok($context);
    }
}
