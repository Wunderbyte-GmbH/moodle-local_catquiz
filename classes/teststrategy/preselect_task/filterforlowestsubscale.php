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
 * Class filterforlowestsubscale.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\catscale;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Keep only questions that belong to the subscale that has the largest negative
 * difference in person ability to its direct parent scale.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filterforlowestsubscale extends preselect_task implements wb_middleware {

    /**
     * Run preselect task.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array $context, callable $next): result {
        $abilities = $context['person_ability'];
        // The difference to itself is 0.
        $abilitydifference = [$context['catscaleid'] => 0];
        foreach (array_keys($abilities) as $catscaleid) {
            // For each scale, calculate the relative difference of its person ability compared to its direct ancestor.
            $childscaleids = array_keys(
                catscale::get_next_level_subscales_ids_from_parent([$catscaleid])
            );
            foreach ($childscaleids as $childscaleid) {
                $abilitydifference[$childscaleid] = $abilities[$childscaleid] - $abilities[$catscaleid];
            }
        }

        asort($abilitydifference);
        $catscaleids = array_keys($abilitydifference);
        foreach ($catscaleids as $catscaleid) {
            $questions = array_filter($context['questions'], fn ($q) => $q->catscaleid == $catscaleid);
            if (count($questions) > 0) {
                $context['questions'] = $questions;
                return $next($context);
            } 
        }
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'questions',
        ];
    }
}
