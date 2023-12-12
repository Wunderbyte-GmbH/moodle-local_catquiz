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
 * Class teststrategy_fastest.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\strategy;

use local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage;
use local_catquiz\teststrategy\feedbackgenerator\customscalefeedback;
use local_catquiz\teststrategy\feedbackgenerator\debuginfo;
use local_catquiz\teststrategy\feedbackgenerator\graphicalsummary;
use local_catquiz\teststrategy\feedbackgenerator\personabilities;
use local_catquiz\teststrategy\feedbackgenerator\questionssummary;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\preselect_task\addscalestandarderror;
use local_catquiz\teststrategy\preselect_task\filterforsubscale;
use local_catquiz\teststrategy\preselect_task\firstquestionselector;
use local_catquiz\teststrategy\preselect_task\fisherinformation;
use local_catquiz\teststrategy\preselect_task\lasttimeplayedpenalty;
use local_catquiz\teststrategy\preselect_task\maximumquestionscheck;
use local_catquiz\teststrategy\preselect_task\maybe_return_pilot;
use local_catquiz\teststrategy\preselect_task\mayberemovescale;
use local_catquiz\teststrategy\preselect_task\noremainingquestions;
use local_catquiz\teststrategy\preselect_task\remove_uncalculated;
use local_catquiz\teststrategy\preselect_task\removeplayedquestions;
use local_catquiz\teststrategy\preselect_task\strategyfastestscore;
use local_catquiz\teststrategy\preselect_task\updatepersonability;
use local_catquiz\teststrategy\strategy;

/**
 * This strategy will prefer questions from a CAT scale where the user has a lower ability.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class inferlowestskillgap extends strategy {

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = LOCAL_CATQUIZ_STRATEGY_LOWESTSUB;

    /**
     *
     * @var stdClass $feedbacksettings.
     */
    public feedbacksettings $feedbacksettings;

    /**
     * Returns required score modifiers.
     *
     * @return array
     *
     */
    public function get_preselecttasks(): array {
        return [
            maximumquestionscheck::class, // Cancel quiz attempt if we reached maximum of questions.
            updatepersonability::class,
            removeplayedquestions::class,
            noremainingquestions::class,
            fisherinformation::class, // Add the fisher information to each question.
            firstquestionselector::class, // If this is the first question of this attempt, return it here.
            lasttimeplayedpenalty::class,
            mayberemovescale::class, // Remove questions from excluded subscales.
            maybe_return_pilot::class,
            remove_uncalculated::class, // Remove items that do not have item parameters.
            noremainingquestions::class, // Cancel quiz attempt if no questions are left.
            // Keep only questions that are assigned to the subscale where the user has the lowest ability.
            addscalestandarderror::class,
            filterforsubscale::class,
            strategyfastestscore::class,
        ];
    }

    /**
     * Get feedback generators.
     *
     * @param feedbacksettings $feedbacksettings
     * @return array
     *
     */
    public function get_feedbackgenerators(feedbacksettings $feedbacksettings = null): array {

        $this->apply_feedbacksettings($feedbacksettings);

        return [
            new comparetotestaverage($this->feedbacksettings),
            new customscalefeedback($this->feedbacksettings),
            new debuginfo($this->feedbacksettings),
            new personabilities($this->feedbacksettings),
            new questionssummary($this->feedbacksettings),
            new graphicalsummary($this->feedbacksettings),
        ];
    }

    /**
     * Gets predefined values and completes them with specific behaviour of strategy.
     *
     * @param feedbacksettings $feedbacksettings
     *
     */
    public function apply_feedbacksettings(feedbacksettings $feedbacksettings) {
        if ($feedbacksettings->overridesettings) {
            $feedbacksettings->sortorder = LOCAL_CATQUIZ_SORTORDER_ASC;
        }
        if ($feedbacksettings->primaryscaleid == LOCAL_CATQUIZ_PRIMARYCATSCALE_DEFAULT) {
            $feedbacksettings->primaryscaleid = LOCAL_CATQUIZ_PRIMARYCATSCALE_LOWEST;
        }

        $this->feedbacksettings = $feedbacksettings;
    }
}
