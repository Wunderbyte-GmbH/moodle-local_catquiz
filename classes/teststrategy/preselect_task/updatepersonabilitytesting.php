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
 * @package    catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\model\model_responses;
use local_catquiz\teststrategy\preselect_task\updatepersonability;

class updatepersonabilitytesting extends updatepersonability {

    protected function load_responses($context) {
        $responses = new model_responses();
        $responses = $responses->setdata(
            $context['fake_response_data']
        );
        return $responses;
    }

    protected function load_cached_responses() {
        return [];
    }

    protected function update_cached_responses($userresponses) {
    }

    protected function update_person_param($a, $b) {
    }
}
