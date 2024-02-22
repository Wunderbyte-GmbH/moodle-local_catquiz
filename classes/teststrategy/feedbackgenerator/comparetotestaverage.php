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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use cache;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\preselect_task\firstquestionselector;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/local/catquiz/lib.php');

/**
 * Compare the ability of this attempt to the average abilities of other students that took this test.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comparetotestaverage extends feedbackgenerator {

    /**
     *
     * @var int $primaryscaleid // The scale to be displayed in detail in the colorbar.
     */
    public int $primaryscaleid;

    /**
     *
     * @var stdClass $feedbacksettings.
     */
    public feedbacksettings $feedbacksettings;

    /**
     * Creates a new customscale feedback generator.
     *
     * @param feedbacksettings $feedbacksettings
     */
    public function __construct(feedbacksettings $feedbacksettings) {

        // Will be 0 if no scale set correctly.
        if (isset($feedbacksettings->primaryscaleid)) {
            $this->primaryscaleid = $feedbacksettings->primaryscaleid;
        } else {
            $this->primaryscaleid = LOCAL_CATQUIZ_PRIMARYCATSCALE_DEFAULT;
        }
        $this->feedbacksettings = $feedbacksettings;
    }

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

        if (empty($feedback)) {
            return [];
        } else {
            return [
                'heading' => $this->get_heading(),
                'content' => $feedback,
            ];
        }
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
     * For specific feedbackdata defined in generators.
     *
     * @param array $feedbackdata
     */
    public function apply_settings_to_feedbackdata(array $feedbackdata) {
        return $this->feedbacksettings->hide_defined_elements(
            $feedbackdata,
            $this->get_generatorname()
        );
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
            'testaverageability',
            'userability',
            // Used for positioning in the progress bar. 0 is left, 50 middle and 100 right.
            // This assumes that all values are in the range [-5, 5].
            'testaverageposition',
            'userabilityposition',
            'comparisontext',
            'colorbar',
            'colorbarlegend',
            'quizsettings',
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
     * Get generatorname.
     *
     * @return string
     *
     */
    public function get_generatorname(): string {
        return 'comparetotestaverage';
    }

    /**
     * Write string to define color gradiant bar.
     *
     * @param object $quizsettings
     * @param string|int $catscaleid
     * @return array
     *
     */
    private function get_colorbarlegend($quizsettings, $catscaleid): array {
        if (!$quizsettings) {
            return [];
        }
        // We collect the feedbackdata only for the parentscale.
        $feedbacks = [];
        $numberoffeedbackoptions = intval($quizsettings->numberoffeedbackoptionsselect);
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);

        for ($j = 1; $j <= $numberoffeedbackoptions; $j++) {
            $colorkey = 'wb_colourpicker_' . $catscaleid . '_' . $j;
            $feedbacktextkey = 'feedbacklegend_scaleid_' . $catscaleid . '_' . $j;
            $lowerlimitkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_" . $j;
            $upperlimitkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $j;

            $feedbackrangestring = get_string(
                'subfeedbackrange',
                'local_catquiz',
                [
                    'upperlimit' => round($quizsettings->$upperlimitkey, 2),
                    'lowerlimit' => round($quizsettings->$lowerlimitkey, 2),
                ]);

            $text = $quizsettings->$feedbacktextkey ?? "";

            $colorname = $quizsettings->$colorkey;
            $colorvalue = $colorarray[$colorname];

            $feedbacks[] = [
                'subcolorcode' => $colorvalue,
                'subfeedbacktext' => $text,
                'subfeedbackrange' => $feedbackrangestring,
            ];
        }

        return $feedbacks;
    }

    /**
     * Write information about colorgradient for colorbar.
     *
     * @param object $quizsettings
     * @param string|int $catscaleid
     * @return string
     *
     */
    private function get_colorgradientstring($quizsettings, $catscaleid): string {
        if (!$quizsettings) {
            return "";
        }

        $numberoffeedbackoptions = intval($quizsettings->numberoffeedbackoptionsselect);
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);
        $gradient = LOCAL_CATQUIZ_COLORBARGRADIENT;

        $output = "";

        for ($i = 1; $i <= $numberoffeedbackoptions; $i++) {
            // Keys of the lowest and highest values in range...
            // Since it's already defined via scale min max range, no more need to sanitize here.
            $lowestlimitkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_1";
            $highestlimitkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $numberoffeedbackoptions;
            $rangestart = (float) $quizsettings->$lowestlimitkey;
            $rangeend = (float) $quizsettings->$highestlimitkey;

            $lowerlimitkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_" . $i;
            $upperlimitkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $i;

            $lowerlimit = (float) $quizsettings->$lowerlimitkey;
            $upperlimit = (float) $quizsettings->$upperlimitkey;

            $lowerpercentage = (($lowerlimit - $rangestart) / ($rangeend - $rangestart)) * 100 + $gradient;
            $upperpercentage = (($upperlimit - $rangestart) / ($rangeend - $rangestart)) * 100 - $gradient;

            $colorkey = 'wb_colourpicker_' . $catscaleid . '_' . $i;
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
     * Load data.
     *
     * @param int $attemptid
     * @param array $existingdata
     * @param array $newdata
     *
     * @return array|null
     *
     */
    public function load_data(int $attemptid, array $existingdata, array $newdata): ?array {
        $progress = $newdata['progress'];
        $quizsettings = (object) $existingdata['quizsettings'];
        $personabilities = $progress->get_abilities();

        if (!$progress->get_abilities()) {
            return [];
        }

        $personparams = catquiz::get_person_abilities(
            $existingdata['contextid'],
            array_keys($personabilities)
        );

        $selectedscalearray = $this->feedbacksettings->get_scaleid_and_stringkey(
                $personabilities,
                $quizsettings,
                $this->primaryscaleid);

        $catscaleid = $selectedscalearray['selectedscaleid'];

        $catscale = catscale::return_catscale_object($catscaleid);
        $ability = $personabilities[$catscaleid]['value'];
        if (! $ability) {
            return null;
        }
        $worseabilities = array_filter(
            $personparams,
            fn ($pp) => $pp->ability < $ability
        );

        if (!$worseabilities) {
            return null;
        }

        $quantile = (count($worseabilities) / count($personparams)) * 100;
        $text = get_string(
            'feedbackcomparetoaverage',
            'local_catquiz',
            [
                'quantile' => sprintf('%.2f', $quantile),
                'scaleinfo' => $catscale->name,
            ]);

        $testaverage = (new firstquestionselector())->get_median_ability_of_test($personparams);

        $abilityrange = catscale::get_ability_range($catscaleid);
        $middle = ($abilityrange['minscalevalue'] + $abilityrange['maxscalevalue']) / 2;

        $testaverageinrange = feedbacksettings::sanitize_range_min_max(
            $testaverage,
            $abilityrange['minscalevalue'],
            $abilityrange['maxscalevalue']);

        $abilityinrange = feedbacksettings::sanitize_range_min_max(
            $ability,
            $abilityrange['minscalevalue'],
            $abilityrange['maxscalevalue']);

        $b = $middle - (float) $abilityrange['minscalevalue'];
        $testaverageposition = ($b + $testaverageinrange) / $b * 50;
        $userabilityposition = ($b + $abilityinrange) / $b * 50;

        return [
            'contextid' => $existingdata['contextid'],
            'testaverageability' => sprintf('%.2f', $testaverageinrange),
            'userability' => sprintf('%.2f', $abilityinrange),
            'testaverageposition' => $testaverageposition,
            'userabilityposition' => $userabilityposition,
            'comparisontext' => $text,
            'colorbar' => [
                'colorgradestring' => $this->get_colorgradientstring($quizsettings, $catscaleid),
            ],
            'colorbarlegend' => [
                'feedbackbarlegend' => $this->get_colorbarlegend($quizsettings, $catscaleid),
            ],
            'currentability' => get_string('currentability', 'local_catquiz', $catscale->name),
            'currentabilityfellowstudents' => get_string('currentabilityfellowstudents', 'local_catquiz', $catscale->name),
            'lowerscalelimit' => $abilityrange['minscalevalue'],
            'upperscalelimit' => $abilityrange['maxscalevalue'],
            'middle' => $middle,
        ];
    }
}
