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
            $cache->set('abilitybeforeattempt', $personparams[$context['catscaleid']]);
            // For the lowest skillgap teststrategy, we need at least the ability of the main scale.
            $this->progress->set_ability($personparams[$context['catscaleid']], $context['catscaleid']);
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
                ...$context['selectedsubscales']
            );
        }
        $personparams = catquiz::get_person_abilities(
            $context['contextid'],
            $catscaleids,
            $context['userid']
        ) ?: [];

        // Index by catscale ID.
        $filteredparams = [];
        foreach (array_filter($personparams, fn ($pp) => in_array($pp->catscaleid, $catscaleids)) as $pp) {
            $filteredparams[$pp->catscaleid] = $pp;
        }

        $abilities = [];
        foreach ($catscaleids as $scaleid) {
            $ability = ! empty($filteredparams[$scaleid])
                ? floatval($filteredparams[$scaleid]->ability)
                : self::DEFAULT_ABILITY;
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
}
