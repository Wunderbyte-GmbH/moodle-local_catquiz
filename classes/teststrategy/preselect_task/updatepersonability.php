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
use local_catquiz\local\model\model_responses;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;
use moodle_exception;

/**
 * Update the person ability based on the result of the previous question
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updatepersonability extends preselect_task {
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
     * Stores the standard deviation of ability in case it is calculated
     *
     * @var ?float
     */
    private ?float $sdability = null;

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
     *
     * @return result
     *
     */
    public function run(array &$context): result {
        $this->progress = $context['progress'];
        $this->context = $context;

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
            return result::ok($context);
        }

        $this->userresponses = model_responses::create_from_array(
            [$context['attemptid'] => ['component' => $this->progress->get_user_responses()]]
        );
        $context['lastresponse'] = $this->userresponses->get_last_response($context['attemptid']);

        if (!empty($this->progress->get_last_question()->is_pilot)) {
            $context['skip_reason'] = 'pilotquestion';
            return result::ok($context);
        }

        $this->arrayresponses = $this->userresponses->get_for_user($context['attemptid']);

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

        return result::ok($context);
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
                    fn($k) => $itemparamlist->offsetExists($k->get_id())
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
        if (!$abilitywascalculated) {
            return $this->calculate_sd_from_past_attempts();
        }

        // If possible, use the calculated ability. Otherwise, use the estimated one.
        $ability = $abilitywascalculated
            ? $this->context['person_ability'][$this->context['catscaleid']]
            : $this->meanability;

        $lastquestion = $this->userresponses->get_last_response($this->context['attemptid']);
        $items = clone ($this->get_item_param_list($this->context['catscaleid']));
        $items->offsetUnset($lastquestion->get_id());

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
        if (!$lastresponse = $this->userresponses->get_last_response($this->context['attemptid'])) {
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
     * Calculates Standard Deviation from past attempts.
     * @return ?float
     */
    private function calculate_sd_from_past_attempts() {
        $existingabilities = $this->get_existing_personparams();

        if (count($existingabilities) < self::NUM_ESTIMATION_THRESHOLD) {
            return 1.0;
        }

        $mean = $this->meanability ?? calculate_mean_from_past_attempts() ?? 0.0;

        $sum = 0.0;
        foreach ($existingabilities as $pp) {
            $sum += floatval(($pp->ability - $mean) ** 2);
        }
        $sd = sqrt($sum / count($existingabilities));
        $this->sdability = $sd;
        return $sd;
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

        [$flippedresponses, $allcorrect, $targetid] = $this->get_flipped_extreme_response($scaleid);
        if (!$flippedresponses) {
            return $originalability;
        }

        // Standardise the flipped probe item's discrimination to 1, keeping its
        // difficulty (and, for polytomous models, its remaining parameters). This
        // makes the boundary probe's influence independent of the possibly extreme
        // or capped discrimination of whichever item happened to be hardest/easiest.
        $items = clone $this->get_item_param_list($scaleid);
        if ($targetid !== null && isset($items[$targetid])) {
            $probe = clone $items[$targetid];
            $params = $probe->get_params_array() ?? [];
            $params['discrimination'] = 1.0;
            $probe->set_parameters($params);
            $items[$targetid] = $probe;
        }

        $alternativeability = catcalc::estimate_person_ability(
            $flippedresponses,
            $items,
            $startvalue,
            $this->parentability,
            $this->parentse,
            $this->get_min_ability_for_scale($scaleid),
            $this->get_max_ability_for_scale($scaleid),
            $this->use_tr_factor()
        );
        // The regularised MAP estimate ($originalability) is shrunk toward the
        // parent for a degenerate (all-correct/all-wrong) pattern. Breaking the
        // degeneracy - by flipping the HARDEST answered item for an all-correct
        // pattern, or the EASIEST for an all-wrong one - yields an estimate that
        // is less regularised and oriented toward the scale end. Prefer it when
        // it lies farther from the parent in the same direction as the MAP.
        $selected = (($alternativeability <=> $originalability) == ($originalability <=> $this->parentability))
            ? $alternativeability
            : $originalability;

        // Monotonicity guard: an all-correct pattern must never store a lower
        // ability than the previous step's value, and an all-wrong pattern never
        // a higher one. This removes residual boundary jitter while preserving
        // the de-regularisation toward the scale end. get_abilities() still holds
        // the previous step's value here, because set_ability() runs afterwards.
        $previous = $this->progress->get_abilities()[$scaleid] ?? null;
        if ($previous !== null && is_numeric($previous)) {
            $selected = $allcorrect ? max($selected, (float) $previous) : min($selected, (float) $previous);
        }

        return $selected;
    }

    /**
     * Returns the responses for the scale with the extreme item's response
     * flipped: the HARDEST answered item for an all-correct pattern, the EASIEST
     * for an all-wrong one. Unlike flipping the last-administered item, this does
     * not depend on which item happened to be shown last, so the resulting
     * alternative ability is stable across steps.
     *
     * @param int $scaleid
     * @return array [array $flippedresponses, bool $allcorrect, ?string $targetid];
     *               $flippedresponses is empty when no answered item is available.
     */
    private function get_flipped_extreme_response(int $scaleid): array {
        if (isset($this->flippedresponses[$scaleid])) {
            return $this->flippedresponses[$scaleid];
        }

        $items = $this->get_item_param_list($scaleid);
        $difficulties = [];
        $fractionsum = 0.0;
        foreach ($this->arrayresponses as $itemid => $response) {
            if (!isset($items[$itemid])) {
                continue;
            }
            $difficulties[$itemid] = $items[$itemid]->get_difficulty();
            $fractionsum += floatval($response->get_response());
        }
        if (!$difficulties) {
            return $this->flippedresponses[$scaleid] = [[], false, null];
        }

        $allcorrect = round($fractionsum / count($difficulties), 0) >= 1.0;
        // Argmax (all-correct) or argmin (all-wrong) over item difficulty. A manual
        // scan avoids float-precision pitfalls of array_keys(..., max(...)).
        $targetid = array_key_first($difficulties);
        foreach ($difficulties as $itemid => $difficulty) {
            if ($allcorrect ? $difficulty > $difficulties[$targetid] : $difficulty < $difficulties[$targetid]) {
                $targetid = $itemid;
            }
        }

        $flipped = $this->arrayresponses;
        $flippedresponse = clone $flipped[$targetid];
        $flippedresponse->set_response(abs(1 - floatval($flipped[$targetid]->get_response())));
        $flipped[$targetid] = $flippedresponse;

        return $this->flippedresponses[$scaleid] = [$flipped, $allcorrect, $targetid];
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

        $questionswithresponse = array_values(array_filter(
            $questions,
            fn ($q) => isset($this->arrayresponses[$q->id])
        ));
        if (count($questionswithresponse) < 3) {
            return false;
        }

        $fraction = array_sum(
            array_map(fn ($q) => floatval($this->arrayresponses[$q->id]->get_response()), $questionswithresponse)
        ) / count($questionswithresponse);
        if (round($fraction, 0) != round($fraction, 6)) {
            return false;
        }

        return true;
    }
}
