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
 * Class classicalcat.
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
use local_catquiz\teststrategy\feedbackgenerator\personabilities;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\preselect_task\fisherinformation;
use local_catquiz\teststrategy\preselect_task\strategyclassicscore;
use local_catquiz\teststrategy\strategy;

/**
 * Implements a classical/probabilistic testing strategy.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class classicalcat extends strategy {
    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = LOCAL_CATQUIZ_STRATEGY_CLASSIC;

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
        return new strategyclassicscore();
    }

    /**
     * Returns feedback generators.
     *
     * @param feedbacksettings|null $feedbacksettings
     * @return array
     */
    public function get_feedbackgenerators(?feedbacksettings $feedbacksettings = null): array {

        $this->apply_feedbacksettings($feedbacksettings);
        $feedbackhelper = new feedback_helper();

        return [
            new customscalefeedback($this->feedbacksettings, $feedbackhelper),
            new comparetotestaverage($this->feedbacksettings, $feedbackhelper),
            new personabilities($this->feedbacksettings, $feedbackhelper),
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

        // If Fraction is 1 (all answers correct) or 0 (all answers wrong) mark abilities as estimated.
        $estimated = $feedbacksettings->fraction == 1 || $feedbacksettings->fraction == 0;
        $rootscaleid = $feedbackdata['catscaleid'];
        // Minimum N and maximum standard error are prerequisites of a valid result, here as in every
        // other strategy; a scale that misses them is excluded. Maximum N and minimum standard error
        // are stopping criteria of the test and play no part in validity. Without a configured
        // limit both filters leave every scale as it is.
        $personabilities = $feedbacksettings->filter_nminscale($personabilities, $feedbackdata);
        $personabilities = $feedbacksettings->filter_semax($personabilities, $feedbackdata);
        // Neither all productive items of a scale wrong nor all right (issue #140).
        $personabilities = $feedbacksettings->filter_fraction($personabilities, $feedbackdata);
        foreach ($personabilities as $scaleid => $abilitiesarray) {
            $personabilities[$scaleid]['toreport'] = true;
            if ($estimated) {
                $personabilities[$scaleid]['estimated'] = true;
                $personabilities[$scaleid]['fraction'] = $feedbacksettings->fraction;
            }
            if ($scaleid == $rootscaleid) {
                $personabilities[$scaleid]['primary'] = true;
            }
        }

        return $personabilities;
    }

    /**
     * In this strategy, we do not need to calculate this.
     *
     * @return result
     */
    protected function first_question_selector(): result {
        return result::ok($this->context);
    }

    /**
     * No fisher information over the whole pool: CLASSIC chooses by item id, not by information (issue #144).
     *
     * Every remaining item got its information for every scale on every answer, for a choice that
     * never looks at it. The item that is handed out gets it in select_question(), with the same
     * abilities, so the progress and the feedback keep their values. Pilot items are chosen by a
     * score that does use the information; with pilots switched on the pool is annotated as before.
     *
     * @return result
     */
    protected function fisherinformation(): result {
        if (!empty($this->context['pilot_ratio'])) {
            return parent::fisherinformation();
        }
        return result::ok($this->context);
    }

    /**
     * The next item in fixed order, with its fisher information.
     *
     * @return result
     */
    public function select_question(): result {
        $result = parent::select_question();
        $question = $result->unwrap();
        if (is_object($question)) {
            (new fisherinformation())->annotate($question, $this->context);
        }
        return $result;
    }

    /**
     * In this strategy, we do not need to calculate this.
     *
     * @return result
     */
    protected function last_time_played_penalty(): result {
        return result::ok($this->context);
    }

    /**
     * CLASSIC plays a fixed form: no scale is dropped, and no precision ends it (issue #135).
     *
     * Minimum questions and maximum standard error per scale only decide whether a scale is
     * reported as valid (select_scales_for_report()); they never change which questions are asked.
     *
     * @return bool
     */
    public function uses_scale_dropping(): bool {
        return false;
    }

    /**
     * No per-scale maximum shortens the fixed form (issue #135).
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
}
