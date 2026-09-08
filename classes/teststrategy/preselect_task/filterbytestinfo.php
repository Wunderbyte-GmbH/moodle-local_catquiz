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
 * Class filterbystandarderror.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;

/**
 * Includes or excludes scales based on their information
 *
 * Enables or disables scales depending on their standarderror and number of played questions.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filterbytestinfo extends preselect_task {
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
        $this->context = $context;
        $this->progress = $context['progress'];

        if (
            !in_array($context['teststrategy'], [
            LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB,
            LOCAL_CATQUIZ_STRATEGY_RELSUBS,
            LOCAL_CATQUIZ_STRATEGY_ALLSUBS,
            ])
        ) {
            return result::ok($context);
        }

        foreach ($this->progress->get_abilities() as $scaleid => $ability) {
            if ($context['se'][$scaleid] < $context['se_min']) {
                continue;
            }

            if (!$ability) {
                continue;
            }

            // We could have an ability for a scale that is not longer active
            // in this attempt.
            if (!array_key_exists($scaleid, $this->context['questionsperscale'])) {
                continue;
            }

            $allitems = model_item_param_list::from_array(
                array_filter(
                    $this->context['questionsperscale'][$scaleid],
                    fn ($q) => !$q->is_pilot
                )
            );
            $remainingitems = clone ($allitems);
            $playeditems = model_item_param_list::from_array(
                $this->progress->without_pilots()->get_playedquestions(true, $scaleid)
            );
            foreach ($remainingitems as $i) {
                if (in_array($i->get_componentid(), $playeditems->get_item_ids())) {
                    $remainingitems->offsetUnset($i->get_componentid());
                }
            }

            $remaining = $this->context['max_attempts_per_scale'] === -1
                ? count($remainingitems)
                : $this->context['max_attempts_per_scale'] - count($playeditems);
            $testpotential = catscale::get_testpotential(
                $ability,
                $remainingitems,
                $remaining
            );
            $testinformation = catscale::get_testinformation(
                $ability,
                $playeditems
            );

            $enable = $testpotential + $testinformation > 1 / $this->context['se_max'] ** 2;
            // A scale must never be deactivated before at least one question has
            // actually been administered from it. On the very first question the
            // ability is only the configured starting guess (e.g. "very easy" =>
            // -2). At such an extreme guess the test potential can fall below the
            // se_max threshold, which — when min_attempts_per_scale is 0 — used to
            // deactivate the one and only active scale with zero played questions,
            // leaving no active scale and aborting the whole attempt with
            // 'attemptnofirstquestion'. Require at least one played question (and
            // the configured minimum) before a scale may be excluded.
            $playedinscale = $this->progress->get_num_answered_productive_questions($scaleid);
            $playedintest = $this->progress->get_num_answered_productive_questions();
            $minimumperscale = max(1, (int) $this->context['min_attempts_per_scale']);
            $ismainscale = $scaleid === (int) $this->context['catscaleid'];
            // The main scale may only be excluded once the globally configured
            // minimum number of questions has actually been administered. This
            // mirrors filterbystandarderror so that catquiz_minquestions is
            // respected here too; otherwise a low test potential at the starting
            // guess would deactivate the main scale after question 1 and end the
            // whole attempt before the minimum is reached.
            $minimumreached = $playedinscale >= $minimumperscale
                && (!$ismainscale || $playedintest >= (int) ($this->context['minimumquestions'] ?? 0));
            $exclude = $testpotential + $testinformation <= 1 / $this->context['se_max'] ** 2
                && $minimumreached;
            if ($exclude && $this->progress->is_active_scale($scaleid)) {
                $this->progress->deactivate_scale($scaleid, true);
                getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf(
                    "%d: [TI] deact %s%s",
                    count($this->progress->get_playedquestions()),
                    (catscale::return_catscale_object($scaleid))->name,
                    PHP_EOL
                );
                continue;
            }
            if ($enable  && !$this->progress->is_dropped_scale($scaleid) && !$this->progress->is_active_scale($scaleid)) {
                // For allsubs, do not directly activate the scale but remove the lock so that it can be activated again
                // if all scales have reached the minimum questions per scale.
                if ($context['teststrategy'] === LOCAL_CATQUIZ_STRATEGY_ALLSUBS) {
                    $this->progress->unlock_scale($scaleid);
                    continue;
                }
                // Enable the scale.
                $this->progress->add_active_scale($scaleid, true);
                getenv('CATQUIZ_CREATE_TESTOUTPUT') && printf(
                    "%d: [TI] enact %s (%f >= %f)%s",
                    count($this->progress->get_playedquestions()),
                    (catscale::return_catscale_object($scaleid))->name,
                    $testpotential + $testinformation,
                    1 / $this->context['se_max'] ** 2,
                    PHP_EOL
                );
                continue;
            }
        }

        return result::ok($context);
    }
}
