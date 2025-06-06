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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\strategy;

use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage;
use local_catquiz\teststrategy\feedbackgenerator\customscalefeedback;
use local_catquiz\teststrategy\feedbackgenerator\debuginfo;
use local_catquiz\teststrategy\feedbackgenerator\graphicalsummary;
use local_catquiz\teststrategy\feedbackgenerator\learningprogress;
use local_catquiz\teststrategy\feedbackgenerator\personabilities;
use local_catquiz\teststrategy\feedbackgenerator\questionssummary;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\preselect_task\strategydeficitscore;
use local_catquiz\teststrategy\strategy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * This strategy will prefer questions from a CAT scale where the user has a lower ability.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
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
     * Return the next question
     *
     * @return preselect_task
     */
    public function get_selector(): preselect_task {
        return new strategydeficitscore();
    }

    /**
     * Get feedback generators.
     *
     * @param feedbacksettings|null $feedbacksettings
     * @return array
     *
     */
    public function get_feedbackgenerators(?feedbacksettings $feedbacksettings = null): array {

        $this->apply_feedbacksettings($feedbacksettings);
        $feedbackhelper = new feedback_helper();

        return [
            new customscalefeedback($this->feedbacksettings, $feedbackhelper),
            new comparetotestaverage($this->feedbacksettings, $feedbackhelper),
            new personabilities($this->feedbacksettings, $feedbackhelper),
            new learningprogress($this->feedbacksettings, $feedbackhelper),
            new questionssummary($this->feedbacksettings, $feedbackhelper),
            new graphicalsummary($this->feedbacksettings, $feedbackhelper),
            new debuginfo($this->feedbacksettings, $feedbackhelper),
        ];
    }

    /**
     * Gets predefined values and completes them with specific behaviour of strategy.
     *
     * @param feedbacksettings $feedbacksettings
     *
     */
    public function apply_feedbacksettings(feedbacksettings $feedbacksettings) {

        $this->feedbacksettings = $feedbacksettings->set_sort_ascending();
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
        if ($feedbacksettings->fraction >= 1) {
            $returnarray = [];
            foreach ($personabilities as $scaleid => $array) {
                $returnarray[$scaleid] = [
                    'value' => $array['value'],
                    'excluded' => true,
                ];
                $returnarray[$scaleid]['error']['fraction'] = [
                    'fraction' => $feedbacksettings->fraction,
                    'expected' => '< 1',
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
            // In this strategy, the scale with lowest value is set primary.
            $relevantscale = array_search(min($filterabilities), $filterabilities);
        }
        $personabilities[$relevantscale]['primary'] = true;
        $personabilities[$relevantscale]['toreport'] = true;
        $personabilities[$relevantscale]['primarybecause'] = 'lowestskill';

        return $personabilities;
    }
}
