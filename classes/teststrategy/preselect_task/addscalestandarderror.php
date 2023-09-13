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
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use cache;
use local_catquiz\catscale;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Calculates the standarderror for each available catscale.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class addscalestandarderror extends preselect_task implements wb_middleware {

    public function run(array &$context, callable $next): result {
        if (count($context['questions']) === 0) {
                return result::err(status::ERROR_NO_REMAINING_QUESTIONS);
        }

        if (! $context['has_fisherinformation']) {
            return $next($context);
        }

        $questionsperscale = [];
        // Lists for each scale the IDs of the scale itself and its parent scales.
        $scales = [];
        foreach ($context['questions'] as $q) {
            if (!array_key_exists($q->catscaleid, $scales)) {
                $scales[$q->catscaleid] = [
                    $q->catscaleid,
                    ...catscale::get_ancestors($q->catscaleid)
                ];
            }
            foreach ($scales[$q->catscaleid] as $scaleid) {
                if (!array_key_exists($scaleid, $questionsperscale)) {
                    $questionsperscale[$scaleid] = $q->fisherinformation;
                    continue;
                }
                $questionsperscale[$scaleid] += $q->fisherinformation;
            }
        }

        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $threshold = 1; // TODO get from quiz settings
        $excludedscales = $cache->get('excludedscales') ?: [];
        $standarderrorperscale = [];
        foreach ($questionsperscale as $catscaleid => $numquestions) {
            $standarderror = (1 / sqrt($numquestions));
            $standarderrorperscale[$catscaleid] = $standarderror;

            // We already have enough information about that scale.
            if ($standarderror < $threshold) {
                // Questions of this scale will be excluded in the next run.
                $excludedscales[] = $catscaleid;
                $context['questions'] = array_filter($context['questions'], fn ($q) => $q->id != $catscaleid);
            }
        }
        // TODO: Handle case where no questions are left.
        $excludedscales = array_unique($excludedscales);
        $cache->set('excludedscale', $excludedscales);

        $context['standarderrorperscale'] = $standarderrorperscale;

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
            'questions',
            'has_fisherinformation',
        ];
    }
}
