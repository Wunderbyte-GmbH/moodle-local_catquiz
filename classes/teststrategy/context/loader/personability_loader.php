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
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\context\loader;

use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\teststrategy\context\contextloaderinterface;

/**
 * Class pilotquestions_loader for test strategy.
 * 
 * Stores the person ability per scale in the `person_ability` key of the context array.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class personability_loader implements contextloaderinterface {

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
        $personparams = $this->load_saved_personparams($context);
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
        global $DB;
        $catscaleids = [$context['catscaleid']];
        if ($context['includesubscales']) {
            array_push(
                $catscaleids,
                ...catscale::get_subscale_ids($context['catscaleid'])
            );
        }
        $abilities = catquiz::get_person_abilities(
            $context['userid'],
            $context['contextid'],
            $catscaleids
        );

        $personparams = [];
        foreach ($catscaleids as $scaleid) {
            $ability = $abilities[$scaleid]
                ? floatval($abilities[$scaleid])
                : self::DEFAULT_ABILITY
                ;
                $personparams[$scaleid] = $ability;
        }
        return $personparams;
    }
}
