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
class updatepersonabilitytesting extends updatepersonability {

    /**
     * Load responses.
     *
     * @param mixed $context
     *
     * @return mixed
     *
     */
    protected function load_responses($context) {
        $responses = new model_responses();
        $responses = $responses->setdata(
            $context['fake_response_data']
        );
        return $responses;
    }

    /**
     * Load cached responses.
     *
     * @return mixed
     *
     */
    protected function load_cached_responses() {
        return [];
    }

    /**
     * Update cached responses.
     *
     * @param mixed $context
     *
     * @return mixed
     *
     */
    protected function update_cached_responses($context) {
        return (new model_responses())->setdata($context['fake_response_data']);
    }

    /**
     * Update person param.
     *
     * @param mixed $a
     * @param mixed $b
     * @param mixed $c
     *
     * @return mixed
     *
     */
    protected function update_person_param($a, $b, $c) {
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
    protected function get_item_param_list($responses, $catscaleid) {
        $itemparamlist = new model_item_param_list();
        if (! array_key_exists('fake_item_params', $this->context)) {
            return $itemparamlist;
        }

        foreach ($this->context['fake_item_params'] as $id => $values) {
            $itemparamlist->add(
                (new model_item_param($id, 'raschbirnbauma'))
                    ->set_difficulty($values['difficulty'])
            );
        }
        return $itemparamlist;
    }
}
