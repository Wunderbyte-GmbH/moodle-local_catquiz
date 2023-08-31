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
 * Class infergreateststrength.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\strategy;

use local_catquiz\teststrategy\feedbackgenerator\customscalefeedback;
use local_catquiz\teststrategy\feedbackgenerator\personabilities;
use local_catquiz\teststrategy\feedbackgenerator\pilotquestions;
use local_catquiz\teststrategy\feedbackgenerator\questionssummary;
use local_catquiz\teststrategy\preselect_task\filterforsubscale;
use local_catquiz\teststrategy\preselect_task\firstquestionselector;
use local_catquiz\teststrategy\preselect_task\fisherinformation;
use local_catquiz\teststrategy\preselect_task\lasttimeplayedpenalty;
use local_catquiz\teststrategy\preselect_task\maximumquestionscheck;
use local_catquiz\teststrategy\preselect_task\mayberemovescale;
use local_catquiz\teststrategy\preselect_task\maybe_return_pilot;
use local_catquiz\teststrategy\preselect_task\noremainingquestions;
use local_catquiz\teststrategy\preselect_task\numberofgeneralattempts;
use local_catquiz\teststrategy\preselect_task\remove_uncalculated;
use local_catquiz\teststrategy\preselect_task\removeplayedquestions;
use local_catquiz\teststrategy\preselect_task\strategyfastestscore;
use local_catquiz\teststrategy\preselect_task\updatepersonability;
use local_catquiz\teststrategy\strategy;

/**
 * This strategy will prefer questions from a CAT scale where the user has the largest person ability values.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class infergreateststrength extends strategy {

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = STRATEGY_HIGHESTSUB;


    /**
     * Returns required score modifiers.
     *
     * @return array
     *
     */
    public function requires_score_modifiers(): array {
        return [
            maximumquestionscheck::class, // Cancel quiz attempt if we reached maximum of questions.
            updatepersonability::class,
            removeplayedquestions::class,
            noremainingquestions::class,
            firstquestionselector::class, // If this is the first question of this attempt, return it here.
            lasttimeplayedpenalty::class,
            numberofgeneralattempts::class,
            mayberemovescale::class, // Remove questions from excluded subscales.
            maybe_return_pilot::class,
            remove_uncalculated::class, // Remove items that do not have item parameters.
            noremainingquestions::class, // Cancel quiz attempt if no questions are left.
            fisherinformation::class, // Add the fisher information to each question.
            filterforsubscale::class, // Keep only questions that are assigned to the subscale where the user has the largest ability values .
            strategyfastestscore::class,
        ];
    }

    public function get_feedbackgenerators(): array {
        return [
            questionssummary::class,
            personabilities::class,
            pilotquestions::class,
            customscalefeedback::class,
        ];
    }
}