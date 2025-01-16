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

use local_catquiz\local\result;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage;
use local_catquiz\teststrategy\feedbackgenerator\customscalefeedback;
use local_catquiz\teststrategy\feedbackgenerator\debuginfo;
use local_catquiz\teststrategy\feedbackgenerator\graphicalsummary;
use local_catquiz\teststrategy\feedbackgenerator\learningprogress;
use local_catquiz\teststrategy\feedbackgenerator\questionssummary;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\preselect_task\strategyfastestscore;
use local_catquiz\teststrategy\strategy;

/**
 * Will select questions with the highest fisher information first
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teststrategy_fastest extends strategy {

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = LOCAL_CATQUIZ_STRATEGY_FASTEST;

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
        return new strategyfastestscore();
    }

    /**
     * If true, the check page reload is called before updating the ability.
     *
     * Quickfix, could probabily be removed.
     *
     * @return bool
     */
    protected function pre_check_page_reload(): bool {
        return true;
    }

    /**
     * If true, the first question selector is called before updating the ability.
     *
     * Quickfix, could probabily be removed.
     *
     * @return bool
     */
    protected function pre_check_first_question_selector(): bool {
        return true;
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
            new questionssummary($this->feedbacksettings, $feedbackhelper),
            new learningprogress($this->feedbacksettings, $feedbackhelper),
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

        $newabilities = [];
        $rootscaleid = (int) $feedbackdata['catscaleid'];

        // Only parentscale to be selected.
        foreach ($personabilities as $scaleid => $abilityvalue) {
            if ($scaleid != $rootscaleid) {
                $newabilities[$scaleid] = [
                    'value' => $abilityvalue['value'],
                    'excluded' => true,
                    ];
                $newabilities[$scaleid]['error']['rootonly'] = [
                        'rootscaleid' => $rootscaleid,
                        'currentscaleid' => $scaleid,
                ];
                continue;
            }
            $newabilities[$scaleid] = [
                'value' => $abilityvalue['value'],
                'primary' => true,
                'toreport' => true,
                'primarybecause' => 'rootscale',
            ];
        };
        // Minimum of questions per test applied.
        $newabilities = $feedbacksettings->filter_nmintest($newabilities, $feedbackdata);

        // Fraction can not be 1 (all answers correct) or 0 (all answers incorrect).
        if ($feedbacksettings->fraction >= 1 || $feedbacksettings->fraction <= 0) {
            foreach ($personabilities as $scaleid => $abilityvalue) {
                $newabilities[$scaleid] = [
                    'value' => $abilityvalue['value'],
                    'excluded' => true,
                    ];
                $newabilities[$scaleid]['error']['fraction'] = [
                        'fraction' => $feedbacksettings->fraction,
                        'expected' => '0 < f < 1',
                ];
            }
        }
        return $newabilities;
    }

    /**
     * In this strategy, we do not need to calculate this.
     *
     * @return result
     */
    protected function filterbytestinfo(): result {
        return result::ok($this->context);
    }
}
