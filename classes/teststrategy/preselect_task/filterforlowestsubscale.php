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

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\preselect_task\lasttimeplayedpenalty;
use local_catquiz\wb_middleware;

/**
 * Keep only questions that belong to the subscale where the user has the lowest ability.
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
        // If there is no information about ability per scale, just pick a
        // question from the top-most scale via weighted fisher information
        $abilities = $context['person_ability'];
        asort($abilities);
        $catscaleids = array_keys($abilities);

        foreach ($catscaleids as $catscaleid) {
            $questions = array_filter($context['questions'], fn ($q) => $q->catscaleid == $catscaleid);
            if (count($questions) > 0) {
                $context['questions'] = $questions;
                return $next($context);
            } 
        }

        return next($context['questions']);
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'penalty_threshold',
            'questions',
        ];
    }
}
