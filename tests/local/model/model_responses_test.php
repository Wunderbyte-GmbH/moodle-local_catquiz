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
 * Tests the person ability estimator that uses catcalc.
 *
 * @package    local_catquiz
 * @author     Magdalena Holczik <david.szkiba@wunderbyte.at>
 * @copyright  2024 Wunderbyte <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use basic_testcase;

/**
 * Contains tests for the model_responses class.
 *
 * @package local_catquiz
 * @covers \local_catquiz\local\model\model_item_param_list
 */
class model_responses_test extends basic_testcase {

    public function test_filtering_values_works_as_expected() {
        $mr = new model_responses();
        $mr->set('P1', 'A1', 1.0);
        $mr->set('P2', 'A2', 1.0);
        $responses = $mr
            ->limit_to_users(['P1'])
            ->get_item_response();
        $this->assertEquals(
            $responses,
            ['A1' => ['P1' => new model_item_response('A1', 1, (new model_person_param('P1'))->set_ability(0.34))]]
        );
    }

    public function test_setting_values_works_as_expected() {
        $mr = new model_responses();
        $mr->set('P1', 'A1', 1.0);
        // Now update the response for P1. Sums should be changed.
        $mr->set('P1', 'A1', 0.0);
        $this->assertEquals(0.0, $mr->get_item_fraction('A1'));
        $mr->set('P1', 'A1', 1.0);
        $mr->set('P2', 'A1', 1.0);
        $mr->set('P3', 'A1', 0.0);
        $this->assertEquals(2 / 3, $mr->get_item_fraction('A1'));
    }
}
