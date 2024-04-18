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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\strategy;

use local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage;
use local_catquiz\teststrategy\feedbackgenerator\customscalefeedback;
use local_catquiz\teststrategy\feedbackgenerator\debuginfo;
use local_catquiz\teststrategy\feedbackgenerator\graphicalsummary;
use local_catquiz\teststrategy\feedbackgenerator\personabilities;
use local_catquiz\teststrategy\feedbackgenerator\pilotquestions;
use local_catquiz\teststrategy\feedbackgenerator\questionssummary;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\preselect_task\addscalestandarderror;
use local_catquiz\teststrategy\preselect_task\checkbreak;
use local_catquiz\teststrategy\preselect_task\checkitemparams;
use local_catquiz\teststrategy\preselect_task\checkpagereload;
use local_catquiz\teststrategy\preselect_task\filterbystandarderror;
use local_catquiz\teststrategy\preselect_task\filterforsubscale;
use local_catquiz\teststrategy\preselect_task\firstquestionselector;
use local_catquiz\teststrategy\preselect_task\fisherinformation;
use local_catquiz\teststrategy\preselect_task\lasttimeplayedpenalty;
use local_catquiz\teststrategy\preselect_task\maximumquestionscheck;
use local_catquiz\teststrategy\preselect_task\mayberemovescale;
use local_catquiz\teststrategy\preselect_task\maybe_return_pilot;
use local_catquiz\teststrategy\preselect_task\noremainingquestions;
use local_catquiz\teststrategy\preselect_task\remove_uncalculated;
use local_catquiz\teststrategy\preselect_task\removeplayedquestions;
use local_catquiz\teststrategy\preselect_task\strategyfastestscore;
use local_catquiz\teststrategy\preselect_task\updatepersonability;
use local_catquiz\teststrategy\strategy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * This strategy will prefer questions from a CAT scale where the user has the largest person ability values.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class infergreateststrength extends strategy {

    /**
     * Hide until tested.
     */
    public const ACTIVE = false;

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB;

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
            checkitemparams::class,
            checkbreak::class,
            checkpagereload::class,
            updatepersonability::class,
            addscalestandarderror::class,
            maximumquestionscheck::class, // Cancel quiz attempt if we reached maximum of questions.
            removeplayedquestions::class,
            noremainingquestions::class,
            fisherinformation::class, // Add the fisher information to each question.
            firstquestionselector::class, // If this is the first question of this attempt, return it here.
            lasttimeplayedpenalty::class,
            mayberemovescale::class, // Remove questions from excluded subscales.
            maybe_return_pilot::class,
            remove_uncalculated::class, // Remove items that do not have item parameters.
            noremainingquestions::class, // Cancel quiz attempt if no questions are left.
            filterbystandarderror::class,
             // Keep only questions that are assigned to the subscale where the user has the largest ability values.
            filterforsubscale::class,
            strategyfastestscore::class,
        ];
    }

    /**
     * Get feedback generators.
     * @param feedbacksettings $feedbacksettings
     * @return array
     *
     */
    public function get_feedbackgenerators(feedbacksettings $feedbacksettings = null): array {

        $this->apply_feedbacksettings($feedbacksettings);

        return [
            new customscalefeedback($this->feedbacksettings),
            new comparetotestaverage($this->feedbacksettings),
            new questionssummary($this->feedbacksettings),
            new personabilities($this->feedbacksettings),
            new pilotquestions($this->feedbacksettings),
            new graphicalsummary($this->feedbacksettings),
            new debuginfo($this->feedbacksettings),
        ];
    }
    /**
     * Gets predefined values and completes them with specific behaviour of strategy.
     *
     * @param feedbacksettings $feedbacksettings
     *
     */
    public function apply_feedbacksettings(feedbacksettings $feedbacksettings) {

        $this->feedbacksettings = $feedbacksettings;
    }

    /**
     * Gets predefined values and completes them with specific behaviour of strategy.
     *
     * @param feedbacksettings $feedbacksettings
     * @param array $personabilities
     * @param array $feedbackdata
     * @param int $catscaleid
     * @param bool $feedbackonlyfordefinedscaleid
     *
     */
    public function select_scales_for_report(
        feedbacksettings $feedbacksettings,
        array $personabilities,
        array $feedbackdata,
        int $catscaleid = 0,
        bool $feedbackonlyfordefinedscaleid = false
        ): array {
        // Fraction can not be 1 (all answers correct).
        if ($feedbacksettings->fraction <= 0) {
            $returnarray = [];
            foreach ($personabilities as $scaleid => $array) {
                $returnarray[$scaleid] = [
                    'value' => $array['value'],
                    'excluded' => true,
                ];
                $returnarray[$scaleid]['error']['fraction'] = [
                    'fraction' => $feedbacksettings->fraction,
                    'expected' => '> 0',
                ];
            }
            return $returnarray;
        }
        // Exclude scales that don't meet minimum of items required in quizsettings.
        $personabilities = $feedbacksettings->filter_nminscale($personabilities, $feedbackdata);
        // Exclude scales where standarderror is not in range.
        $personabilities = $feedbacksettings->filter_semax($personabilities, $feedbackdata);

        if ($feedbackonlyfordefinedscaleid && !empty($catscaleid)) {
            // Force selected scale. Will also be applied to excluded scales.
            $relevantscale = $personabilities[$catscaleid];
        } else {
            $filterabilities = [];
            foreach ($personabilities as $scaleid => $array) {
                if (!isset($array['error']) && !isset($array['excluded'])) {
                    $filterabilities[$scaleid] = $array['value'];
                }
            }
            if (count($filterabilities) < 1) {
                return $personabilities;
            }
            // In this strategy, the scale with greatest strenght is set primary.
            $relevantscale = array_search(max($filterabilities), $filterabilities);
        }
        $personabilities[$relevantscale]['primary'] = true;
        $personabilities[$relevantscale]['toreport'] = true;
        $personabilities[$relevantscale]['primarybecause'] = 'greateststrenght';

        return $personabilities;
    }
}
