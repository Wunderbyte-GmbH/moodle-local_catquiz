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

use core\chart_bar;
use core\chart_series;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_strategy;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedbacksettings;

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
     * We only show a graph if we have results for at least that many users.
     *
     * @var int
     */
    const MIN_USERS = 3;

    /**
     * Get student feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    public function get_studentfeedback(array $data): array {
        global $OUTPUT;

        $abilityprofilechart = $this->render_abilityprofile_chart(
            (array) $data,
            ['id' => $data['catscaleid']]
        );
        $data['abilityprofile'] = $abilityprofilechart;
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
            'testaverageability',
            'userability',
            'testaverageposition',
            'userabilityposition',
            'comparisontext',
            'colorbar',
            'colorbarlegend',
            'comparetotestaverage_has_worse',
            'comparetotestaverage_has_enough_peers',
        ];
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return get_string('comparetotestaverage', 'local_catquiz');
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
     * Write information about colorgradient for colorbar.
     *
     * @param object $quizsettings
     * @param string|int $catscaleid
     * @return string
     *
     */
    private function get_colorgradientstring(object $quizsettings, $catscaleid): string {
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
        $progress = $this->get_progress();
        $quizsettings = $progress->get_quiz_settings();

        if (!$progress->get_playedquestions()) {
            return [];
        }

        $personparams = catquiz::get_person_abilities(
            $existingdata['contextid'],
            array_keys($newdata['updated_personabilities'])
        );

        $catscaleid = $quizsettings->catquiz_catscales;
        $abilities = $progress->get_abilities();
        if (!array_key_exists($catscaleid, $abilities)) {
            return [];
        }
        $ability = $abilities[$catscaleid];

        // Just keep the parameters for the global scale, because that's the one we want to compare.
        $personparams = array_filter($personparams, fn ($pp) => $pp->catscaleid == $catscaleid);

        // If we do not have enough data to show a meaningful comparison, don't display this feedback.
        $distinctusers = array_unique(
            array_map(
                fn ($pp) => $pp->userid,
                $personparams
            )
        );

        $catscale = catscale::return_catscale_object($catscaleid);

        $worseabilities = array_filter(
            $personparams,
            fn ($pp) => $pp->ability < round($ability, 4)
        );

        $quantile = count($personparams) <= 1
            ? 0
            : (count($worseabilities) / (count($personparams) - 1)) * 100;
        $testaverage = array_sum(array_map(fn ($pp) => $pp->ability, $personparams)) / count($personparams);

        $catscaleclass = new catscale($catscaleid);
        $abilityrange = $catscaleclass->get_ability_range();
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

        $text = get_string(
            'feedbackcomparetoaverage',
            'local_catquiz',
            [
                'quantile' => round($quantile, 0),
                'quotedscale' => feedback_helper::add_quotes($catscale->name),
                'ability_global' => feedback_helper::localize_float($abilityinrange),
                'se_global' => feedback_helper::localize_float($newdata['se'][$catscaleid]),
                'average_ability' => feedback_helper::localize_float($testaverageinrange),
                'scale_min' => feedback_helper::localize_float($abilityrange['minscalevalue']),
                'scale_max' => feedback_helper::localize_float($abilityrange['maxscalevalue']),
            ]);

        return [
            'contextid' => $existingdata['contextid'],
            'testaverageability' => sprintf('%.2f', $testaverageinrange),
            'userability' => sprintf('%.2f', $abilityinrange),
            'testaverageposition' => $testaverageposition,
            'userabilityposition' => $userabilityposition,
            'comparisontext' => $text,
            'colorbar' => [
                'colorgradestring' => $this->get_colorgradientstring((object) $quizsettings, $catscaleid),
            ],
            'colorbarlegend' => [
                'feedbackbarlegend' => feedback_helper::get_colorbarlegend((object) $quizsettings, $catscaleid),
            ],
            'currentability' => get_string('currentability', 'local_catquiz', $catscale->name),
            'currentabilityfellowstudents' => get_string('currentabilityfellowstudents', 'local_catquiz', $catscale->name),
            'lowerscalelimit' => $abilityrange['minscalevalue'],
            'upperscalelimit' => $abilityrange['maxscalevalue'],
            'middle' => $middle,
            'comparetotestaverage_has_worse' => count($worseabilities) > 0,
            'comparetotestaverage_has_enough_peers' => count($distinctusers) >= self::MIN_USERS,
            'personabilities_abilities' => $this->get_restructured_abilities($existingdata, $newdata),
        ];
    }

    /**
     * Render chart for histogram of personabilities.
     *
     * @param array $initialcontext
     * @param array $primarycatscale
     *
     *
     * @return array
     *
     */
    private function render_abilityprofile_chart(array $initialcontext, array $primarycatscale) {
        global $OUTPUT, $DB;

        $abilitysteps = [];
        $abilitystep = 0.25;
        $interval = $abilitystep * 2;
        if (isset($initialcontext['personabilities_abilities'][$primarycatscale['id']]['abilityrange'])) {
            $abilityrange = $initialcontext['personabilities_abilities'][$primarycatscale['id']]['abilityrange'];
        } else {
            $abilityrange = $this->feedbackhelper->get_ability_range($primarycatscale['id']);
        };

        $ul = (float) $abilityrange['maxscalevalue'];
        $ll = (float) $abilityrange['minscalevalue'];
        for ($i = $ll + $abilitystep; $i <= ($ul - $abilitystep); $i += $interval) {
            $abilitysteps[] = $i;
        }
        $items = $this->feedbackhelper->get_testitems_for_catscale($primarycatscale['id'], $initialcontext['contextid'], true);
        // Prepare data for test information line.

        $models = model_strategy::get_installed_models();
        $fisherinfos = [];
        foreach ($items as $item) {
            // We can not calculate the fisher information for items without a model.
            if (!$item->model) {
                continue;
            }
            $model = model_model::get_instance($item->model);
            foreach ($model::get_parameter_names() as $paramname) {
                $params[$paramname] = floatval($item->$paramname);
            }
            foreach ($abilitysteps as $ability) {
                $fisherinformation = $model->fisher_info(
                    ['ability' => $ability],
                    $params
                );
                $stringkey = strval($ability);

                if (!isset($fisherinfos[$stringkey])) {
                    $fisherinfos[$stringkey] = $fisherinformation;
                } else {
                    $fisherinfos[$stringkey] += $fisherinformation;
                }
            }

        }

        $fisherinfos = $this->feedbackhelper->get_fisherinfos_of_items($items, $models, $abilitysteps);
        // Prepare data for scorecounter bars.
        $abilityrecords = $DB->get_records('local_catquiz_personparams', ['catscaleid' => $primarycatscale['id']]);
        $abilityseries = [];
        foreach ($abilitysteps as $as) {
            $counter = 0;
            foreach ($abilityrecords as $record) {
                $a = floatval($record->ability);
                $ability = $this->feedbackhelper->round_to_customsteps($a, $abilitystep, $interval);
                if ($ability != $as) {
                    continue;
                } else {
                    $counter ++;
                }
            }
            $colorvalue = $this->feedbackhelper->get_color_for_personability(
                (array) $this->get_progress()->get_quiz_settings(),
                $as,
                intval($primarycatscale['id'])
                );
            $abilitystring = strval($as);
            $abilityseries['counter'][$abilitystring] = $counter;
            $abilityseries['colors'][$abilitystring] = $colorvalue;
        }
        // Scale the values of $fisherinfos before creating chart series.
        $scaledtiseries = $this->feedbackhelper->scalevalues(array_values($fisherinfos), array_values($abilityseries['counter']));

        $aserieslabel = "";
        if (array_key_exists('personabilities_abilities', $initialcontext)) {
            $scalename = $initialcontext['personabilities_abilities'][$primarycatscale['id']]['name'];
            $aserieslabel = get_string('scalescorechartlabel', 'local_catquiz', $scalename);
        }
        $aseries = new chart_series($aserieslabel, array_values($abilityseries['counter']));
        $aseries->set_colors(array_values($abilityseries['colors']));
        $chart = new chart_bar();
        $chart->add_series($aseries);

        if ($this->has_extended_view_permissions()) {
            $testinfolabel = get_string('testinfolabel', 'local_catquiz');
            $tiseries = new chart_series($testinfolabel, $scaledtiseries);
            $tiseries->set_type(chart_series::TYPE_LINE);
            $tiseries->set_smooth(true);
            $chart->add_series($tiseries);
        }

        $chart->set_labels(array_keys($fisherinfos));

        $out = $OUTPUT->render_chart($chart, $this->has_extended_view_permissions());
        return [
            'chart' => $out,
            'charttitle' => get_string('abilityprofile_title', 'local_catquiz'),
        ];
    }

}
