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
 * Class filterforsubscale.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\catscale;
use local_catquiz\local\result;
use local_catquiz\teststrategy\context\loader\personability_loader;
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
class filterforsubscale extends preselect_task implements wb_middleware {

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

        if (! in_array($context['teststrategy'], [STRATEGY_LOWESTSUB, STRATEGY_HIGHESTSUB])) {
            global $CFG;
            if ($CFG->debug > 0) {
                throw new \UnexpectedValueException(
                    "Filterforsubscale encountered unknown teststrategy " . $context['teststrategy']
                );
            }
            return $next($context);
        }

        // If there is no information about ability per scale, select a question
        // from the top-most scale via weighted fisher information.
        $numdefaultabilities = count($this->get_default_abilies($context['person_ability']));
        $alldefault = count($context['person_ability']) === $numdefaultabilities;
        if ($alldefault) {
            return $next($context);
        }

        // The difference to itself is 0.
        $abilitydifference = [$context['catscaleid'] => 0];
        foreach (array_keys($abilities) as $catscaleid) {
            // For each scale, calculate the relative difference of its person ability compared to its direct ancestor.
            $childscaleids = $this->getsubscaleids($catscaleid, $context);
            foreach ($childscaleids as $childscaleid) {
                $abilitydifference[$childscaleid] = $abilities[$childscaleid] - $abilities[$catscaleid];
            }
        }

        switch ($context['teststrategy']) {
            case STRATEGY_HIGHESTSUB:
                arsort($abilitydifference);
                break;
            case STRATEGY_LOWESTSUB:
                asort($abilitydifference);
                break;
        };

        $catscaleids = array_keys($abilitydifference);
        foreach ($catscaleids as $catscaleid) {
            $questions = array_filter($context['questions'], fn ($q) => $q->catscaleid == $catscaleid);
            if (count($questions) > 0) {
                $context['questions'] = $questions;
                return $next($context);
            } 
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
            'questions',
        ];
    }

    protected function getsubscaleids($catscaleid, $context) {
        return array_keys(
            catscale::get_next_level_subscales_ids_from_parent([$catscaleid])
        );
    }

    private function get_default_abilies($abilities) {
        return array_filter(
            $abilities,
            fn ($p) => $p === personability_loader::DEFAULT_ABILITY
        );
    }
}
