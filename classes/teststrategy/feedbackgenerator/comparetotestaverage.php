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
 * Class comparetotestaverage.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use cache;
use local_catquiz\catquiz;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\preselect_task\firstquestionselector;

/**
 * Compare the ability of this attempt to the average abilities of other students that took this test.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comparetotestaverage extends feedbackgenerator {

    /**
     * Get student feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    protected function get_studentfeedback(array $data): array {
        global $OUTPUT;
        $feedback = $OUTPUT->render_from_template('local_catquiz/feedback/comparetotestaverage', $data);

        return [
            'heading' => $this->get_heading(),
            'content' => $feedback,
        ];
    }

    /**
     * Get teacher feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    protected function get_teacherfeedback(array $data): array {
        return [];
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'contextid',
            'personabilities',
            'quizsettings',
            'needsimprovementthreshold',
            'testaverageability',
            'userability',
            // Used for positioning in the progress bar. 0 is left, 50 middle and 100 right.
            // This assumes that all values are in the range [-5, 5].
            'testaverageposition',
            'userabilityposition',
            'text',
        ];
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return get_string('personability', 'local_catquiz');
    }

    /**
     * Write string to define color gradiant bar.
     *
     * @param object $quizsettings
     * @return array
     *
     */
    private function get_colorbarlegend($quizsettings): array {
        if (!$quizsettings) {
            return [];
        }
        // We collect the feedbackdata only for the parentscale.
        $feedbacks = [];
        $parentscaleid = $quizsettings->catquiz_catscales;
        $numberoffeedbackoptions = intval($quizsettings->numberoffeedbackoptionsselect);
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);

        for ($j = 1; $j <= $numberoffeedbackoptions; $j++) {
            $colorkey = 'wb_colourpicker_' . $parentscaleid . '_' . $j;
            $feedbacktextkey = 'feedbacklegend_scaleid_' . $parentscaleid . '_' . $j;

            $text = $quizsettings->$feedbacktextkey ?? "";

            $colorname = $quizsettings->$colorkey;
            $colorvalue = $colorarray[$colorname];

            $feedbacks[] = [
                'subcolorcode' => $colorvalue,
                'subfeedbacktext' => $text,
            ];
        }

        return $feedbacks;
    }

    /**
     * Get feedbackdata.
     *
     * @param object $quizsettings
     * @return string
     *
     */
    private function get_colorgradientstring($quizsettings): string {
        if (!$quizsettings) {
            // TODO maybe return default.
            // Default: $interval = 100 / ($totalvalues - 1);.
            return "";
        }

        $parentscaleid = $quizsettings->catquiz_catscales;
        $numberoffeedbackoptions = intval($quizsettings->numberoffeedbackoptionsselect);
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);
        $gradient = COLORBARGRADIENT;

        $output = "";

        for ($i = 1; $i <= $numberoffeedbackoptions; $i++) {
            $lowestlimitkey = "feedback_scaleid_limit_lower_" . $parentscaleid . "_1";
            $highestlimitkey = "feedback_scaleid_limit_upper_" . $parentscaleid . "_" . $numberoffeedbackoptions;
            $rangestart = $quizsettings->$lowestlimitkey;
            $rangeend = $quizsettings->$highestlimitkey;

            $lowerlimitkey = "feedback_scaleid_limit_lower_" . $parentscaleid . "_" . $i;
            $upperlimitkey = "feedback_scaleid_limit_upper_" . $parentscaleid . "_" . $i;

            $lowerlimit = $quizsettings->$lowerlimitkey;
            $upperlimit = $quizsettings->$upperlimitkey;

            $lowerpercentage = (($lowerlimit - $rangestart) / ($rangeend - $rangestart)) * 100 + $gradient;
            $upperpercentage = (($upperlimit - $rangestart) / ($rangeend - $rangestart)) * 100 - $gradient;

            $colorkey = 'wb_colourpicker_' . $parentscaleid . '_' . $i;
            $colorname = $quizsettings->$colorkey;
            $colorvalue = $colorarray[$colorname];

            $output .= "{$colorvalue} {$lowerpercentage}%, ";
            $output .= "{$colorvalue} {$upperpercentage}%, ";
        }
        // Remove the last comma.
        $output = rtrim($output, ", ");
        return $output;

    }

    /**
     * @param stdClass $quizsettings
     * @param int $parentscaleid
     * @param int $i
     * @param float $rangestart
     * @param float $rangeend
     *
     * @return float
     */
    private function calculate_percentage($quizsettings, $parentscaleid, $i, $rangestart, $rangeend) {
        $lowerlimitkey = "feedback_scaleid_limit_lower_" . $parentscaleid . "_" . $i;
        $upperlimitkey = "feedback_scaleid_limit_upper_" . $parentscaleid . "_" . $i;

        $lowerlimit = isset($quizsettings->$lowerlimitkey) ? $quizsettings->$lowerlimitkey : 0;
        $upperlimit = isset($quizsettings->$upperlimitkey) ? $quizsettings->$upperlimitkey : 100;

        $limit = ($lowerlimit + $upperlimit) / 2;

        $percentage = (($limit - $rangestart) / ($rangeend - $rangestart)) * 100;
        return number_format($percentage, 2);
    }

    /**
     * Load data.
     *
     * @param int $attemptid
     * @param array $initialcontext
     *
     * @return array|null
     *
     */
    public function load_data(int $attemptid, array $initialcontext): ?array {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        if (! $quizsettings = $cache->get('quizsettings')) {
            return null;
        }

        if (! $catscaleid = $quizsettings->catquiz_catscales) {
            return null;
        }

        $personabilities = $initialcontext['personabilities'];
        $ability = $personabilities[$catscaleid];
        if (! $ability) {
            return null;
        }

        $personparams = catquiz::get_person_abilities(
            $initialcontext['contextid'],
            array_keys($personabilities)
        );
        $worseabilities = array_filter(
            $personparams,
            fn ($pp) => $pp->ability < $ability
        );

        if (!$worseabilities) {
            return null;
        }

        $quantile = (count($worseabilities) / count($personparams)) * 100;
        $text = get_string('feedbackcomparetoaverage', 'local_catquiz', sprintf('%.2f', $quantile));
        if ($needsimprovementthreshold = $initialcontext['needsimprovementthreshold']) {
            if ($quantile < $needsimprovementthreshold) {
                $text .= " " . get_string('feedbackneedsimprovement', 'local_catquiz');
            }
        }

        $testaverage = (new firstquestionselector())->get_median_ability_of_test($personparams);

        return [
            'contextid' => $initialcontext['contextid'],
            'quizsettings' => $quizsettings,
            'needsimprovementthreshold' => $needsimprovementthreshold,
            'testaverageability' => sprintf('%.2f', $testaverage),
            'userability' => sprintf('%.2f', $ability),
            'testaverageposition' => ($testaverage + 5) * 10,
            'userabilityposition' => ($ability + 5) * 10,
            'text' => $text,
            'colorgradestring' => $this->get_colorgradientstring($quizsettings),
            'feedbackbarlegend' => $this->get_colorbarlegend($quizsettings),
        ];
    }
}
