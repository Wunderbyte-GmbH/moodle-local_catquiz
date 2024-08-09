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
 * Class strategystrengthscore.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use stdClass;

/**
 * Add a score to each question and sort questions descending by score
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class strategystrengthscore extends strategyscore {

    protected function get_question_scaleterm(float $testinfo, float $abilitydifference): float {
        return 1 / (1 + exp(-1 * $testinfo * $abilitydifference));
    }

    protected function get_question_itemterm(float $testinfo, float $fraction, $difficulty, $scaleability, $scalecount) {
        return (1 / (
            1 + exp($testinfo * 2 * (0.5 - $fraction) * ($difficulty - $scaleability))
        )) ** $scalecount;
    }

    protected function get_score(stdClass $question, int $scaleid) {
        return $question->fisherinformation[$scaleid]
            * $question->processterm
            * $question->scaleterm
            * $question->itemterm;
    }
}
