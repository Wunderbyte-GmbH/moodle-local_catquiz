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
 * Testing class for updatepersonability.
 *
 * This class overrides methods that access the DB or caches but leaves everything else as is.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_responses;
use local_catquiz\teststrategy\preselect_task\updatepersonability;

/**
 * Update person ability testing.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updatepersonability_testing extends updatepersonability {

    /**
     * Update person param.
     *
     * @param int $a
     * @param float $b
     *
     * @return void
     *
     */
    protected function update_person_param(int $a, float $b): void {
        if (! array_key_exists('fake_item_params', $this->context)) {
            parent::update_person_param($a, $b);
        }
    }

    /**
     * Get item param list.
     *
     * @param mixed $responses
     * @param mixed $catscaleid
     *
     * @return mixed
     *
     */
    protected function get_item_param_list($responses, $catscaleid): model_item_param_list {
        if (! array_key_exists('fake_item_params', $this->context)) {
            return parent::get_item_param_list($responses, $catscaleid);
        }

        $itemparamlist = new model_item_param_list();
        foreach ($this->context['fake_item_params'] as $id => $values) {
            $itemparamlist->add(
                (new model_item_param($id, 'raschbirnbauma'))
                    ->set_difficulty($values['difficulty'])
            );
        }
        return $itemparamlist;
    }

    /**
     * Get initial ability
     * @return float
     */
    protected function get_initial_ability() {
        return floatval(getenv('CATQUIZ_TESTING_ABILITY', true) ?: 0.00);
    }

    /**
     * Get initial standarderror
     * @return float
     */
    protected function get_initial_standarderror() {
        return floatval(getenv('CATQUIZ_TESTING_STANDARDERROR', true) ?: 1.0);
    }

    /**
     * Overwrites the parent class for testing.
     *
     * @param int $scaleid Not used
     * @return float
     */
    protected function get_min_ability_for_scale(int $scaleid): float {
        return -10.0;
    }

    /**
     * Overwrites the parent class for testing.
     *
     * @param int $scaleid Not used
     * @return float
     */
    protected function get_max_ability_for_scale(int $scaleid): float {
        return 10.0;
    }
}
