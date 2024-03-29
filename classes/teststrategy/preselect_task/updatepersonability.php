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
 * Class updatepersonability.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use dml_exception;
use coding_exception;
use Exception;
use local_catquiz\catcalc;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;
use local_catquiz\local\model\model_strategy;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;
use local_catquiz\wb_middleware;
use moodle_exception;

/**
 * Update the person ability based on the result of the previous question
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updatepersonability extends preselect_task implements wb_middleware {

    /**
     * Threshold for calculating a mean ability
     *
     * If we have at least that many abilities, we can use them to calculate a mean.
     * Otherwise, we fallback to a default ability of 0.
     *
     * @var int
     */
    const NUM_ESTIMATION_THRESHOLD = 50;

    /**
     *
     * @var mixed $userresponses
     */
    public $userresponses;

    /**
     *
     * @var mixed $arrayresponses
     */
    public $arrayresponses;

    /**
     * Contains IDs of catscales that have at least two different (correct and
     * incorrect) answers.
     *
     * @var array $diverseanswers
     */
    private array $diverseanswers = [];

    /**
     * @var float $parentability
     *
     * Used to temporarily store the ability of a parent scale.
     */
    private float $parentability;

    /**
     * @var float $parentse
     *
     * Used to temporarily store the standard error of a parent scale.
     */
    private float $parentse;

    /**
     * @var float $initialse Initial standard error
     */
    protected float $initialse;

    /**
     * @var array $scalestoupdate
     */
    private array $scalestoupdate;

    /**
     * @var progress $progress
     */
    private progress $progress;

    /**
     * Shows the min- and max-ability for a catscale.
     * @var array
     */
    private array $scaleabilityrange = [];

    /**
     * Stores the itemparamlist for a catscale.
     *
     * @var array
     */
    private array $itemparamlists = [];

    /**
     * Stores the mean ability in case it is calculated
     *
     * @var ?float
     */
    private ?float $meanability = null;

    /**
     * Stores the responses array with the last response flipped.
     *
     * @var array
     */
    private array $flippedresponses;

    /**
     * Helper function to set the context.
     *
     * Used for setting the context in tests.
     *
     * @param array $context
     * @return self
     */
    public function set_context(array $context): self {
        $this->context = $context;
        return $this;
    }

    /**
     * Run preselect task.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array &$context, callable $next): result {
        $this->progress = $context['progress'];

        // If we do not know the answer to the last question, we do not have to
        // update the person ability. Also, pilot questions should not be used
        // to update a student's ability.
        if (
            $this->progress->get_ignore_last_response()
            || (($this->progress->is_first_question() || !$this->progress->has_new_response())
                && !$this->progress->get_force_new_question()
            )
        ) {
            $context['skip_reason'] = 'lastquestionnull';
            return $next($context);
        }

        if (!empty($this->progress->get_last_question()->is_pilot)) {
            $context['skip_reason'] = 'pilotquestion';
            return $next($context);
        }

        $this->userresponses = model_responses::create_from_array(
            [$context['userid'] => ['component' => $this->progress->get_user_responses()]]
        );
        $context['lastresponse'] = $this->userresponses->get_last_response($context['userid']);

        $this->arrayresponses = $this->userresponses->get_for_user($context['userid']);

        $this->parentability = $this->get_initial_ability();
        $this->initialse = $this->set_initial_standarderror();
        $this->parentse = $this->initialse;

        $catscaleid = $this->progress->get_last_question()->catscaleid;
        $this->scalestoupdate = array_reverse(
            [$catscaleid, ...catscale::get_ancestors($catscaleid)]
        );
        try {
            $index = 0;
            foreach ($this->scalestoupdate as $scale) {
                $isancestor = $scale != $catscaleid;
                $parentscale = $index == 0 ? null : $this->scalestoupdate[$index - 1];
                $index++;
                $context = $this->updateability($context, $scale, $isancestor, $parentscale);
            }
        } catch (\Exception $e) {
            if ($e->getMessage() === status::ABORT_PERSONABILITY_NOT_CHANGED) {
                return result::err(status::ABORT_PERSONABILITY_NOT_CHANGED);
            } else {
                throw $e;
            }
        }

        return $next($context);
    }

    /**
     * Update ability.
     *
     * @param array $context
     * @param int $catscaleid
     * @param bool $isancestor
     * @param int $parentscale
     *
     * @return mixed
     *
     */
    private function updateability(array $context, int $catscaleid, $isancestor = false, $parentscale = null) {
        global $CFG;

        $startvalue = $context['person_ability'][$catscaleid] ?? $this->parentability;
        if ($parentscale && $this->ability_was_calculated($parentscale)) {
            $startvalue = $this->parentability;
        }

        try {
            $updatedability = catcalc::estimate_person_ability(
                $this->arrayresponses,
                $this->get_item_param_list($catscaleid),
                $startvalue,
                $this->parentability,
                $this->parentse,
                $this->get_min_ability_for_scale($catscaleid),
                $this->get_max_ability_for_scale($catscaleid),
                $this->use_tr_factor()
            );

            $updatedability = $this->maybe_change_to_alternative_ability(
                $catscaleid,
                $this->arrayresponses,
                $updatedability,
                $startvalue
            );
        } catch (moodle_exception $e) {
            // If we get an excpetion, re-throw it with more information.
            $message = sprintf(
                'Can not update ability for scale %d in context %d: %s',
                $catscaleid,
                catscale::get_context_id($catscaleid),
                $e->getMessage()
            );
            throw new Exception($message);
        }

        if (is_nan($updatedability)) {
            // In a production environment, we can use fallback values. However,
            // during development we want to see when we get unexpected values.
            if ($CFG->debug > 0) {
                throw new moodle_exception('error', 'local_catquiz');
            }
            // If we already have an ability, just continue with that one and do not update it.
            // Otherwise, use 0 as default value.
            $context['skip_reason'] = 'abilityisnan';
            if (!is_nan($context['person_ability'][$catscaleid])) {
                return $context;
            } else {
                $context['person_ability'][$catscaleid] = 0;
                return $context;
            }
        }

        if ($this->ability_was_calculated($catscaleid)) {
            $this->parentability = $updatedability;
            $this->parentse = catscale::get_standarderror($updatedability, $this->get_item_param_list($catscaleid));
        }
        $this->update_person_param($catscaleid, $updatedability);

        $context['prev_ability'][$catscaleid] = $context['person_ability'][$catscaleid];
        $context['person_ability'][$catscaleid] = $updatedability;
        $this->progress->set_ability($updatedability, $catscaleid);

        if ($this->ability_was_calculated($catscaleid)) {
            // Get all scales that are a subscale of the current catscale.
            $scales = array_filter(
                array_keys($this->context['person_ability']),
                fn ($id) => in_array($id, catscale::get_subscale_ids($catscaleid))
            );
            // Exclude scales that will be updated anyway.
            $scales = array_filter($scales, fn ($s) => !in_array($s, $this->scalestoupdate));
            foreach ($scales as $scale) {
                // Exclude scales that have wrong and right answers.
                $itemparamlist = model_item_param_list::get(
                    $context['contextid'],
                    null,
                    [$scale, ...catscale::get_subscale_ids($scale)]
                )->filter_for_responses($this->userresponses); // Filter the list to the items for a response that was given.
                if (count($itemparamlist) === 0) {
                    continue;
                }

                // Remove all responses that are not in the item param list and check again.
                $arrayresponsesforscale = array_filter(
                    $this->arrayresponses,
                    fn($k) => $itemparamlist->offsetExists($k['questionid'])
                );
                $this->diverseanswers[$scale] = $this->has_sufficient_responses($arrayresponsesforscale);
                if ($this->diverseanswers[$scale]) {
                    continue;
                }
                $startvalue = $this->context['person_ability'][$scale] ?? $this->parentability;
                $ability = catcalc::estimate_person_ability(
                    $arrayresponsesforscale,
                    $itemparamlist,
                    $startvalue,
                    $this->parentability,
                    $this->parentse,
                    $this->get_min_ability_for_scale($catscaleid),
                    $this->get_max_ability_for_scale($catscaleid),
                    $this->use_tr_factor()
                );

                $ability = $this->maybe_change_to_alternative_ability(
                    $scale,
                    $arrayresponsesforscale,
                    $ability,
                    $startvalue
                );

                $this->progress->set_ability($ability, $scale);
                $this->update_person_param($scale, $ability);
            }
        }

        return $context;
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'contextid',
            'catscaleid',
            'progress',
        ];
    }

    /**
     * TODO: move this to model_response
     *
     * Test if we can calculate an ability with the given responses.
     * At least two answers with different outcome are needed.
     *
     * Note: Even if this function returns true, we still have to check on a
     * per-scale basis if we have enough answers in that scale.
     *
     * @param array $arrayresponses
     * @return bool
     */
    private function has_sufficient_responses($arrayresponses) {
        if (! $arrayresponses) {
            return false;
        }
        $first = $arrayresponses[array_key_first($arrayresponses)];
        foreach ($arrayresponses as $ur) {
            if ($ur->get_response() !== $first->get_response()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get item param list.
     *
     * @param mixed $catscaleid
     * @return model_item_param_list
     *
     */
    protected function get_item_param_list($catscaleid): model_item_param_list {
        if (array_key_exists($catscaleid, $this->itemparamlists)) {
            return $this->itemparamlists[$catscaleid];
        }

        $catscalecontext = catscale::get_context_id($catscaleid);
        $catscaleids = [
            $catscaleid,
            ...catscale::get_subscale_ids($catscaleid),
        ];
        $itemparamlist = model_item_param_list::get($catscalecontext, null, $catscaleids)
            ->filter_for_responses($this->userresponses);
        $this->itemparamlists[$catscaleid] = $itemparamlist;
        return $this->itemparamlists[$catscaleid];
    }

    /**
     * Update person param.
     *
     * @param int $catscaleid
     * @param float $updatedability
     *
     * @return void
     *
     */
    protected function update_person_param(int $catscaleid, float $updatedability): void {
        catquiz::update_person_param(
            $this->context['userid'],
            catscale::get_context_id($catscaleid),
            $catscaleid,
            $updatedability
        );
    }

    /**
     * If the last answer was correct, increase the ability to the halfway point
     * between the current ability and the maximum value.
     * If the last question is partly correct, e.g. the fraction is 0.6, then
     * the change of the person ability is multiplied by that value.
     *
     * For incorrect answers, the change will update the ability towards the
     * minimum value.
     *
     * @param mixed $catscaleid
     *
     * @return mixed
     *
     */
    public function fallback_ability_update($catscaleid) {
        $fraction = $this->userresponses->get_last_response($this->context['userid'])['fraction'];
        $max = ($fraction < 0.5)
            ? -5 * (1 - $fraction)
            : 5 * $fraction;
        return ($this->context['person_ability'][$catscaleid] + $max) / 2;
    }

    /**
     * Returns the mean value that is used for the ability estimation.
     *
     * @return float
     */
    public function get_initial_ability() {
        // If we already have a value based on a real calculation, use that one.
        if ($this->ability_was_calculated($this->context['catscaleid'], false)) {
            return $this->context['person_ability'][$this->context['catscaleid']];
        }

        // If we already have more than 50 abilities for this test, get the mean from there.
        if ($mean = $this->calculate_mean_from_past_attempts()) {
            $this->meanability = $mean;
            return $mean;
        }

        return 0.0;
    }

    /**
     * Returns the standarderror value that is used for the ability estimation.
     *
     * @return float
     */
    protected function set_initial_standarderror() {
        $abilitywascalculated = $this->ability_was_calculated($this->context['catscaleid'], false);
        // If we can not calculate or estimate the standard error, return a default value.
        if (!$abilitywascalculated && !$this->meanability) {
            return 1.0;
        }

        // If possible, use the calculated ability. Otherwise, use the estimated one.
        $ability = $abilitywascalculated
            ? $this->context['person_ability'][$this->context['catscaleid']]
            : $this->meanability;

        $lastquestionid = $this->userresponses->get_last_response($this->context['userid']);
        $items = clone ($this->get_item_param_list($this->context['catscaleid']));
        $items->offsetUnset($lastquestionid->get_id());

        return catscale::get_standarderror(
            $ability,
            $items
        );
    }

    /**
     * Shows if the ability for the given scale was calculated or just estimated.
     *
     * @param int $catscaleid
     * @param bool $includelastresponse
     * @return bool
     * @throws dml_exception
     * @throws coding_exception
     * @throws Exception
     */
    protected function ability_was_calculated(int $catscaleid, bool $includelastresponse = true) {
        // If we have not at least one previous response, the ability was not calculated.
        if (!$lastresponse = $this->userresponses->get_last_response($this->context['userid'])) {
            return false;
        }
        $items = $this->get_item_param_list($catscaleid)->as_array();
        if (!$includelastresponse) {
            unset($items[$lastresponse->get_id()]);
        }

        // Only keep responses for the current scale.
        $arrayresponsesforscale = array_filter(
            $this->arrayresponses,
            fn ($k) => in_array($k, array_keys($items)),
            ARRAY_FILTER_USE_KEY
        );

        return $this->has_sufficient_responses($arrayresponsesforscale);
    }

    /**
     * Calculates Mean from past attempts.
     * @return ?float
     */
    private function calculate_mean_from_past_attempts() {
        $existingabilities = $this->get_existing_personparams();
        if (count($existingabilities) < self::NUM_ESTIMATION_THRESHOLD) {
            return null;
        }

        $sum = 0.0;
        foreach ($existingabilities as $pp) {
            $sum += floatval($pp->ability);
        }
        $mean = $sum / count($existingabilities);
        $this->meanability = $mean;
        return $mean;
    }

    /**
     * Returns the person params for the selected context in the main scale.
     *
     * @return array
     */
    protected function get_existing_personparams(): array {
        return catquiz::get_person_abilities(
            $this->context['contextid'],
            [$this->context['catscaleid']]
        );
    }

    /**
     * Returns the lower limit for the ability in the given scale.
     *
     * @param int $catscaleid
     * @return float
     */
    protected function get_min_ability_for_scale(int $catscaleid): float {
        if (array_key_exists($catscaleid, $this->scaleabilityrange)) {
            return $this->scaleabilityrange[$catscaleid]['minscalevalue'];
        }
        $catscaleclass = new catscale($catscaleid);
        $this->scaleabilityrange[$catscaleid] = $catscaleclass->get_ability_range();
        return $this->scaleabilityrange[$catscaleid]['minscalevalue'];
    }

    /**
     * Returns the upper limit for the ability in the given scale.
     *
     * @param int $catscaleid
     * @return float
     */
    protected function get_max_ability_for_scale(int $catscaleid): float {
        if (array_key_exists($catscaleid, $this->scaleabilityrange)) {
            return $this->scaleabilityrange[$catscaleid]['maxscalevalue'];
        }

        $catscaleclass = new catscale($catscaleid);
        $this->scaleabilityrange[$catscaleid] = $catscaleclass->get_ability_range();
        return $this->scaleabilityrange[$catscaleid]['maxscalevalue'];
    }

    /**
     * Use the trusted region factor
     *
     * @return bool
     */
    protected function use_tr_factor(): bool {
        return $this->ability_was_calculated($this->context['catscaleid'], false);
    }

    /**
     * Calculates the ability again with the last response flipped and returns
     * either the original value or the one calcualted with the flipped
     * response, whichever is "better".
     *
     * Better is defined here as more far from the parent's ability value.
     *
     * @param int $scaleid
     * @param array $responses
     * @param mixed $originalability
     * @param mixed $startvalue
     * @return mixed
     */
    private function maybe_change_to_alternative_ability(int $scaleid, array $responses, $originalability, $startvalue): ?float {
        if (!$this->should_calculate_alternative($scaleid)) {
            return $originalability;
        }

        $flippedresponses = $this->get_flipped_last_response();
        $alternativeability = catcalc::estimate_person_ability(
            $flippedresponses,
            $this->get_item_param_list($scaleid),
            $startvalue,
            $this->parentability,
            $this->parentse,
            $this->get_min_ability_for_scale($scaleid),
            $this->get_max_ability_for_scale($scaleid),
            $this->use_tr_factor()
        );
        // If both the real updated ability and the ability calculated
        // with the flipped last response differ from the parent ability
        // in the same direction, then take the value calculated by the
        // flipped response as this should be closer to the parent.
        if (($alternativeability <=> $originalability) == ($originalability <=> $this->parentability)) {
            return $alternativeability;
        }

        return $originalability;
    }

    /**
     * Returns the responses array with the last response flipped.
     *
     * @return array
     */
    private function get_flipped_last_response(): array {
        if (isset($this->flippedresponses)) {
            return $this->flippedresponses;
        }
        $lastquestion = $this->progress->get_last_question();
        $this->flippedresponses = $this->arrayresponses;
        $frac = floatval($this->flippedresponses[$lastquestion->id]['fraction']);
        $flipped = abs(1 - $frac);
        $this->flippedresponses[$lastquestion->id]['fraction'] = $flipped;
        return $this->flippedresponses;
    }

    /**
     * Indicates if we should also calculate with the last response flipped.
     *
     * @param int $scaleid
     * @return bool
     */
    private function should_calculate_alternative(int $scaleid): bool {
        $questions = $this->progress->get_playedquestions(true, $scaleid);
        if (!$questions || count($questions) < 3) {
            return false;
        }
        $fraction = array_sum(
            array_map(fn ($q) => floatval($this->arrayresponses[$q->id]['fraction']), $questions)
        ) / count($questions);
        if (round($fraction, 0) != round($fraction, 6)) {
            return false;
        }

        return true;
    }
}
