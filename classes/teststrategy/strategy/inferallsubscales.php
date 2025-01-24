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
 * Class inferallsubscales.
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
use local_catquiz\teststrategy\feedbackgenerator\questionssummary;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\preselect_task\filterbyquestionsperscale;
use local_catquiz\teststrategy\preselect_task\inferallsubscalesscore;
use local_catquiz\teststrategy\strategy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * This strategy will test all scales.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class inferallsubscales extends strategy {

    /**
     *
     * @var int $id
     */
    public int $id = LOCAL_CATQUIZ_STRATEGY_ALLSUBS;

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
        return new inferallsubscalesscore();
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

        $this->feedbacksettings = $feedbacksettings->set_sort_by_name();
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

        // Display scales that do not meet the minimum requirements.
        $feedbacksettings->displayscaleswithoutitemsplayed = true;
        // Filter scales, but instead of excluding a scale, mark it as hidden.
        $personabilities = $feedbacksettings->filter_nminscale($personabilities, $feedbackdata, true);
        $personabilities = $feedbacksettings->filter_semax($personabilities, $feedbackdata, true);
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
     * Filter by questions per scale
     *
     * @return result
     */
    protected function filterbyquestionsperscale(): result {
        $filterbyquestionsperscale = new filterbyquestionsperscale();
        $result = $filterbyquestionsperscale->run($this->context, fn ($context) => result::ok($context));
        return $result;
    }
}
