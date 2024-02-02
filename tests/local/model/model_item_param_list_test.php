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
 * @author     David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use basic_testcase;
use UnexpectedValueException;

/** model_item_param_list_test
 *
 * @package local_catquiz
 *
 * @covers \local_catquiz\local\model\model_item_param_list
 */
class model_item_param_list_test extends basic_testcase {

    public function test_save_or_update_testitem_in_db() {
        $record = [];
        $result = model_item_param_list::save_or_update_testitem_in_db($record);
        $expected = [
            'success' => 1, // Update successfully.
            'message' => get_string('success', 'core'),
            'recordid' => 1,
         ];
        $this->assertEquals($expected, $result);
    }
}
