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

namespace local_catquiz\teststrategy\strategy;

use local_catquiz\teststrategy\preselect_task\fisherinformation;
use local_catquiz\teststrategy\preselect_task\lasttimeplayedpenalty;
use local_catquiz\teststrategy\preselect_task\maximumquestionscheck;
use local_catquiz\teststrategy\preselect_task\noremainingquestions;
use local_catquiz\teststrategy\preselect_task\numberofgeneralattempts;
use local_catquiz\teststrategy\preselect_task\remove_uncalculated;
use local_catquiz\teststrategy\preselect_task\strategyfastestscore;
use local_catquiz\teststrategy\preselect_task\updatepersonability;
use local_catquiz\teststrategy\strategy;

/**
 * Will select questions with the highest fisher information first
 */
class teststrategy_fastest extends strategy {

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = STRATEGY_FASTEST;

    public function requires_score_modifiers(): array {
        return [
            maximumquestionscheck::class,
            updatepersonability::class,
            noremainingquestions::class,
            remove_uncalculated::class,
            lasttimeplayedpenalty::class,
            numberofgeneralattempts::class,
            fisherinformation::class,
            strategyfastestscore::class,
        ];
    }
}
