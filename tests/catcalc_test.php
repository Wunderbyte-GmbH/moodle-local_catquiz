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
 * Tests the catcalc functionality.
 *
 * @package    catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * @package local_catquiz
 * @covers \local_catquiz\catcalc
 */
class catcalc_test extends basic_testcase
{

    /**
     * Test if the person ability is calculated correctly.
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_estimate_person_ability_returns_expected_values() {
        $personresponses = [
            5 => ['fraction' => 0.0],
            33 => ['fraction' => 1.0],
            50 => ['fraction' => 1.0],
            58 => ['fraction' => 1.0],
        ];
        $itemparamlist = new model_item_param_list();
        $itemparamlist
            ->add((new model_item_param(5, 'web_raschbirnbauma'))->set_parameters(['difficulty' => 0.7758]))
            ->add((new model_item_param(33, 'web_raschbirnbauma'))->set_parameters(['difficulty' => -37.7967]))
            ->add((new model_item_param(50, 'web_raschbirnbauma'))->set_parameters(['difficulty' => -37.7967]))
            ->add((new model_item_param(58, 'web_raschbirnbauma'))->set_parameters(['difficulty' => -37.7967]));
        $result = catcalc::estimate_person_ability($personresponses, $itemparamlist);
        $this->assertEquals(12345, $result);
    }
}
