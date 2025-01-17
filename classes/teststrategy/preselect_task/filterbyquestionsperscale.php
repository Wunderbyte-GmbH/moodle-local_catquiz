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
 * Class filterbyquestionsperscale.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\catscale;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;

/**
 * Includes or excludes scales based on the number of questions played
 *
 * This is used in the "infer all subscales" strategy. To allow for evenly distributed selections of scales, we do the following:
 * - If a scale has the minimum number of questions per scale and there are other scales that do not have it, deactivate the scale.
 * - If all scales have the minimum number of questions per scale, re-activate all of them.
 *
 * This step should be executed _before_ the "filterbytestinfo" task, so that it overrides it.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filterbyquestionsperscale extends preselect_task {
    /**
     * @var progress
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

        if (!in_array($context['teststrategy'], [LOCAL_CATQUIZ_STRATEGY_ALLSUBS])) {
            return result::ok($context);
        }

        $minquestionsperscale = $context['min_attempts_per_scale'];
        $allhaveminquestions = true;
        foreach (array_keys($this->progress->get_abilities()) as $scaleid) {
            if (count($this->progress->get_playedquestions(true, $scaleid)) < $minquestionsperscale) {
                $allhaveminquestions = false;
                break;
            }
        }

        // If all scales have the minimum questions, activate them.
        if ($allhaveminquestions) {
            foreach ($this->progress->get_abilities() as $scaleid => $ability) {
                if ($this->progress->is_active_scale($scaleid)) {
                    continue;
                }
                $this->progress->add_active_scale($scaleid);
                getenv('CATQUIZ_CREATE_TESTOUTPUT') && !$this->progress->is_locked($scaleid) && printf(
                    "%d: [MinQ] enact %s%s",
                    count($this->progress->get_playedquestions()),
                    (catscale::return_catscale_object($scaleid))->name,
                    PHP_EOL
                );
            }
            return result::ok($context);
        }

        // If we are here, not all scales have the minimum questions.
        // Deactivate the active ones that have the minimum questions.
        foreach ($this->progress->get_playedquestions(true) as $scaleid => $qps) {
            if (count($qps) >= $minquestionsperscale && $this->progress->is_active_scale($scaleid)) {
                $this->progress->deactivate_scale($scaleid);
                getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf(
                    "%d: [MinQ] deaact %s%s",
                    count($this->progress->get_playedquestions()),
                    (catscale::return_catscale_object($scaleid))->name,
                    PHP_EOL
                );
            }
        }
        return result::ok($context);
    }
}
