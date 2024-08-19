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
 * Class strategyrelevantscore.
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
class strategyrelevantscore extends strategyscore {

    /**
     * Returns the scale term
     *
     * @param float $testinfo
     * @param float $abilitydifference
     * @return mixed
     */
    protected function get_question_scaleterm(float $testinfo, float $abilitydifference) {
        return 1;
    }

    /**
     * Returns the item term
     *
     * @param float $testinfo
     * @param float $fraction
     * @param mixed $difficulty
     * @param mixed $scaleability
     * @param mixed $scalecount
     * @param int $minattemptsperscale
     * @return mixed
     */
    protected function get_question_itemterm(
        float $testinfo,
        float $fraction,
        $difficulty,
        $scaleability,
        $scalecount,
        int $minattemptsperscale
    ) {
        return (
            1 / (
                1 + exp($testinfo * 2 * (0.5 - $fraction) * ($difficulty - $scaleability)))
            ) ** max(1, $scalecount - $minattemptsperscale + 1);
    }

    /**
     * Returns the score for the given question and scaleid
     *
     * @param stdClass $question
     * @return mixed
     */
    protected function get_score(stdClass $question, int $scaleid) {
                return $question->fisherinformation[$scaleid]
                    * $question->processterm
                    * $question->scaleterm
                    * $question->itemterm
                    * $question->lasttimeplayedpenaltyfactor;
    }
}
