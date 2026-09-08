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
 * Class personability_loader.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\context\loader;

use cache;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\result\attemptscale_repository;
use local_catquiz\teststrategy\context\contextloaderinterface;
use local_catquiz\teststrategy\progress;

/**
 * Class pilotquestions_loader for test strategy.
 *
 * Stores the person ability per scale in the `person_ability` key of the context array.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class personability_loader implements contextloaderinterface {
    /**
     * @var progress $progress
     */
    private progress $progress;

    /**
     * DEFAULT_ABILITY
     *
     * @var int
     */
    const DEFAULT_ABILITY = 0.0;

    /**
     * Returns array ['person_ability'].
     *
     * @return array
     *
     */
    public function provides(): array {
        return ['person_ability'];
    }

    /**
     * Returns array of requires.
     *
     * @return array
     *
     */
    public function requires(): array {
        return [
            'contextid',
            'catscaleid',
            'userid',
            'includesubscales',
            'progress',
        ];
    }

    /**
     * Load test items.
     *
     * @param array $context
     *
     * @return array
     *
     */
    public function load(array $context): array {
        $this->progress = $context['progress'];
        $personparams = $this->load_saved_personparams($context);
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        if ($this->progress->is_first_question()) {
            // Issue #9 (Phase 2): capture the loaded priors as the pre-attempt
            // state, before any during-attempt estimate is written, so the
            // finaliser can restore a non-validly-measured scale exactly.
            $this->progress->capture_preattempt_abilities($personparams);

            $cache->set('abilitybeforeattempt', $personparams[$context['catscaleid']]);
            // For the lowest skillgap teststrategy, we need at least the ability of the main scale.
            $this->progress->set_ability($personparams[$context['catscaleid']], $context['catscaleid']);

            if ($context['teststrategy'] == LOCAL_CATQUIZ_STRATEGY_ALLSUBS) {
                foreach ($personparams as $scaleid => $value) {
                    $this->progress->set_ability($value, $scaleid);
                }
            }
        }
        $context['person_ability'] = $personparams;

        return $context;
    }

    /**
     * Loads the person params from the database.
     *
     * @param array $context
     * @return array
     */
    protected function load_saved_personparams(&$context) {
        $catscaleids = [$context['catscaleid']];
        if ($context['includesubscales']) {
            array_push(
                $catscaleids,
                ...$this->progress->get_selected_subscales()
            );
        }
        $personparams = catquiz::get_person_abilities(
            $context['contextid'],
            $catscaleids,
            [$context['userid']]
        ) ?: [];

        // Index by catscale ID.
        $filteredparams = [];
        foreach (array_filter($personparams, fn ($pp) => in_array($pp->catscaleid, $catscaleids)) as $pp) {
            $filteredparams[$pp->catscaleid] = $pp;
        }

        $abilities = [];
        foreach ($catscaleids as $scaleid) {
            /* The attempt history is the authoritative source for a
               carry-over start value. local_catquiz_personparams is written
               DURING an attempt (updatepersonability, filterbystandarderror), so
               it is a living intermediate state rather than a record of finished
               attempts - reading it as a prior can carry over a half-finished
               estimate. attemptscale rows are written once at finalisation and
               only for scales that were validly measured, so they are preferred
               whenever one exists; personparams remains the fallback for scales
               without such a row (older attempts, never measured). */
            $carryover = attemptscale_repository::get_latest_valid(
                (int) $context['userid'],
                (int) $context['contextid'],
                (int) $scaleid
            );
            if ($carryover !== null && $carryover->score !== null && $carryover->score !== '') {
                $abilities[$scaleid] = (float) $carryover->score;
                continue;
            }

            $ability = ! empty($filteredparams[$scaleid])
                ? floatval($filteredparams[$scaleid]->ability)
                : $this->get_default_ability();
                $abilities[$scaleid] = $ability;
        }

        // Replace MAX values with default ability.
        foreach ($abilities as $catscaleid => $ability) {
            if (abs($ability) == LOCAL_CATQUIZ_PERSONABILITY_MAX) {
                $abilities[$catscaleid] = self::DEFAULT_ABILITY;
            }
        }
        return $abilities;
    }

    /**
     * Returns the default ability.
     *
     * Allows tests to override the default ability.
     * @return float
     */
    public static function get_default_ability() {
        return floatval(getenv('CATQUIZ_TESTING_ABILITY', true) ?: self::DEFAULT_ABILITY);
    }
}
