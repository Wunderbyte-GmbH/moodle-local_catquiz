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
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\preselect_task\strategyfastestscore;
use local_catquiz\teststrategy\strategy;

/**
 * Selects globally the question with the highest information score (issue #135).
 *
 * The score is the Fisher information of the item on the root scale, weighted by the penalty for
 * a recent use (last time played). The whole remaining pool is a candidate: FASTEST drops no
 * scale and applies no per-scale limit, so a subscale that is measured well enough neither ends
 * the test nor takes its items out of the selection. The test ends at the maximum number of
 * questions, when no eligible item is left, or - past the minimum - when the standard error of
 * the root scale is at or below the target and its ability is stable.
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

        // Neither all productive items of the root scale wrong nor all right (issue #140).
        $newabilities = $feedbacksettings->filter_fraction($newabilities, $feedbackdata);
        return $newabilities;
    }

    /**
     * FASTEST chooses globally: no scale is dropped (issue #135).
     *
     * @return bool
     */
    public function uses_scale_dropping(): bool {
        return false;
    }

    /**
     * No per-scale maximum cuts down the global pool (issue #135).
     *
     * @return bool
     */
    public function uses_per_scale_selection_limits(): bool {
        return false;
    }

    /**
     * The test information of a scale deactivates nothing (issue #135).
     *
     * @return bool
     */
    public function uses_testinfo_filter(): bool {
        return false;
    }

    /**
     * Ends on the precision of the root scale past the minimum, and only on that (issue #135).
     *
     * @return bool
     */
    public function stops_on_global_precision(): bool {
        return true;
    }
}
