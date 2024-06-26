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
 * Class personabilities.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @author Magdalena Holczik, David Sziba
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use core\chart_bar;
use core\chart_series;
use local_catquiz\catscale;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\local\model\model_strategy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/local/catquiz/lib.php');

/**
 * Returns rendered person abilities.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class personabilities extends feedbackgenerator {

    /**
     *
     * @var int $primaryscaleid // The scale to be displayed in detail in the colorbar.
     */
    public int $primaryscaleid;

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
     * Get student feedback.
     *
     * @param array $feedbackdata
     *
     * @return array
     *
     */
    public function get_studentfeedback(array $feedbackdata): array {
        global $OUTPUT;

        $abilitieschart = $this->render_chart(
            $feedbackdata['personabilities_abilities'],
            (array) $this->get_progress()->get_quiz_settings(),
            $feedbackdata['primaryscale'],
        );

        $feedback = $OUTPUT->render_from_template(
        'local_catquiz/feedback/personabilities',
            [
            'abilities' => $feedbackdata['abilitieslist'],
            'chartdisplay' => $abilitieschart,
            ]
        );

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
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'primaryscale',
            'personabilities_abilities',
            'se',
            'abilitieslist',
        ];
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return get_string('personabilitytitletab', 'local_catquiz');
    }

    /**
     * Get generatorname.
     *
     * @return string
     *
     */
    public function get_generatorname(): string {
        return 'personabilities';
    }

    /**
     * Loads data personability, number of items played per subscale and standarderrorpersubscale.
     *
     * @param int $attemptid
     * @param array $existingdata
     * @param array $newdata
     *
     * @return array|null
     *
     */
    public function load_data(int $attemptid, array $existingdata, array $newdata): ?array {
        return $this->generate_feedback($existingdata, $newdata, true);
    }

    /**
     * Loads data personability, number of items played per subscale and standarderrorpersubscale.
     *
     * @param array $existingdata
     * @param array $newdata
     * @param bool $dataonly
     *
     * @return array|null
     *
     */
    private function generate_feedback(array $existingdata, $newdata, $dataonly = false): ?array {
        global $OUTPUT;
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $progress = $this->get_progress();
        $personabilities = $progress->get_abilities();

        if ($personabilities === []) {
            return null;
        }
        $catscales = $newdata['catscales'];

        // Make sure that only feedback defined by strategy is rendered.
        $personabilitiesfeedbackeditor = $this->select_scales_for_report(
            $newdata,
            $this->feedbacksettings,
            $existingdata['teststrategy']
        );

        $personabilities = [];
        // Ability range is the same for all scales with same root scale.
        $abiltiyrange = $this->feedbackhelper->get_ability_range(array_key_first($catscales));
        foreach ($personabilitiesfeedbackeditor as $catscale => $personability) {
            if (isset($personability['excluded']) && $personability['excluded']) {
                continue;
            }
            if (isset($personability['primary'])) {
                $selectedscaleid = $catscale;
            }
            $personabilities[$catscale] = $personability;
            $catscaleobject = catscale::return_catscale_object($catscale);
            $personabilities[$catscale]['name'] = $catscaleobject->name;
            $personabilities[$catscale]['abilityrange'] = $abiltiyrange;

        }
        if ($personabilities === []) {
            return [];
        }

        $this->apply_sorting($personabilities, $selectedscaleid);

        $abilitieslist = [];

        foreach ($personabilities as $catscaleid => $abilityarray) {
            $abilitieslist[] = $this->generate_data_for_scale(
                    $abilitieslist,
                    $catscaleid,
                    $selectedscaleid,
                    $abilityarray,
                    $catscales,
                    $newdata
                );
        }

        return [
            'primaryscale' => $catscales[$selectedscaleid],
            'personabilities_abilities' => $personabilities,
            'se' => $newdata['se'] ?? null,
            'abilitieslist' => $abilitieslist,
        ];
    }

    /**
     * Sort personabilites array according to feedbacksettings.
     *
     * @param array $personabilities
     * @param int $selectedscaleid
     *
     */
    private function apply_sorting(array &$personabilities, int $selectedscaleid) {
        // Sort the array and put primary scale first.
        if ($this->feedbacksettings->is_sorted_ascending()) {
            asort($personabilities);
        } else {
            arsort($personabilities);
        }

        // Put selected element first.
        $value = $personabilities[$selectedscaleid];
        unset($personabilities[$selectedscaleid]);
        $personabilities = [$selectedscaleid => $value] + $personabilities;
    }

    /**
     * Generate data array for values of each catsacle.
     *
     * @param array $data
     * @param int $catscaleid
     * @param int $selectedscaleid
     * @param array $abilityarray
     * @param array $catscales
     * @param array $newdata
     *
     *
     */
    private function generate_data_for_scale(
        array &$data,
        int $catscaleid,
        int $selectedscaleid,
        array $abilityarray,
        array $catscales,
        array $newdata
    ) {
        $ability = $abilityarray['value'];
        if (abs(floatval($ability)) === abs(floatval(LOCAL_CATQUIZ_PERSONABILITY_MAX))) {
            if ($ability < 0) {
                $ability = get_string('allquestionsincorrect', 'local_catquiz');
            } else {
                $ability = get_string('allquestionscorrect', 'local_catquiz');
            }
        } else {
            $ability = sprintf("%.2f", $ability);
        }
        if ($catscaleid == $selectedscaleid) {
            $isselectedscale = true;
            $tooltiptitle = $catscales[$catscaleid]->name;
            if (isset($abilityarray['primarybecause'])) {
                $tooltiptitle = get_string(
                    $abilityarray['primarybecause'] . ':tooltiptitle',
                    'local_catquiz',
                    $catscales[$catscaleid]->name
                ) ?? $catscales[$catscaleid]->name;
            };

        } else {
            $isselectedscale = false;
            $tooltiptitle = $catscales[$catscaleid]->name;
        }
        // If defined in settings, display only feedbacks if items were played...
        // ...and parentscale and primaryscale.
        if ($questionsinscale = $this->get_progress()->get_playedquestions(true, $catscaleid)) {
            $numberofitems = ['itemsplayed' => count($questionsinscale)];
        } else if ($this->feedbacksettings->displayscaleswithoutitemsplayed
            || $catscaleid == $selectedscaleid
            || $catscales[$catscaleid]->parentid == 0) {
            $numberofitems = ['noplayed' => 0];
        } else if ($catscaleid != $selectedscaleid) {
            $numberofitems = "";
        }
        if (isset($newdata['se'][$catscaleid])) {
            $standarderror = sprintf("%.2f", $newdata['se'][$catscaleid]);
        } else {
            $standarderror = "";
        }

        return [
            'standarderror' => $standarderror,
            'ability' => $ability,
            'name' => $catscales[$catscaleid]->name,
            'catscaleid' => $catscaleid,
            'numberofitemsplayed' => $numberofitems,
            'isselectedscale' => $isselectedscale,
            'tooltiptitle' => $tooltiptitle,
        ];

    }

    /**
     * For testing, this is called here.
     *
     * @param int $catscaleid
     * @param int $contextid
     * @param bool $includesubscales
     *
     * @return array
     *
     */
    public function get_testitems_for_catscale(int $catscaleid, int $contextid, bool $includesubscales) {
        $catscale = new catscale($catscaleid);
        // Prepare data for test information line.
        return $catscale->get_testitems($contextid, $includesubscales);
    }

    /**
     * Round float to steps as defined.
     *
     * @param float $number
     * @param float $step
     * @param float $interval
     *
     * @return float
     */
    private function round_to_customsteps(float $number, float $step, float $interval): float {
        $roundedvalue = round($number / $step) * $step;

        // Exclude rounding to steps defined in $interval.
        if ($roundedvalue - floor($roundedvalue) == $interval) {
            $roundedvalue = floor($roundedvalue) + $step;
        }

        return $roundedvalue;
    }

    /**
     * Scale values of testinfo (sum of fisherinfos) for better display in chart.
     *
     * @param array $fisherinfos
     * @param array $attemptscounter
     *
     * @return array
     */
    private function scalevalues($fisherinfos, $attemptscounter) {
        // Find the maximum values in arrays.
        $maxattempts = max($attemptscounter);
        $maxfisherinfo = max($fisherinfos);

        // Avoid division by zero.
        if ($maxfisherinfo == 0 || $maxattempts == 0) {
            return $fisherinfos;
        }

        $scalingfactor = $maxattempts / $maxfisherinfo;

        // Scale the values in $fisherinfos based on the scaling factor.
        foreach ($fisherinfos as &$value) {
            $value *= $scalingfactor;
        }
        return $fisherinfos;
    }

    /**
     * In order to make the chartvalues connected, we need to calculate averages between entries, if there are no values set.
     *
     * @param array $attemptswithnulls
     *
     * @return array
     *
     */

    /**
     * Render chart for personabilities.
     *
     * @param array $personabilities
     * @param array $quizsettings
     * @param array $primarycatscale
     *
     * @return array
     *
     */
    private function render_chart(array $personabilities, array $quizsettings, array $primarycatscale): array {
        global $OUTPUT;

        if (count($personabilities) < 2) {
            return [
                'chart' => get_string('nothingtocompare', 'local_catquiz'),
                'charttitle' => '',
            ];
        }

        $primarycatscaleid = intval($primarycatscale['id']);
        $primaryability = $this->get_progress()->get_abilities()[$quizsettings['catquiz_catscales']];

        $chart = new chart_bar();
        $chart->set_horizontal(true);
        $this->apply_sorting($personabilities, $primarycatscaleid);
        foreach ($personabilities as $abilityarray) {
            $subscaleability = (float) $abilityarray['value'];
            $subscalename = $abilityarray['name'];
            $difference = round($subscaleability - $primaryability, 2);
            $series = new chart_series($subscalename, [0 => $difference]);

            $stringforchartlegend = get_string(
                'chartlegendabilityrelative',
                'local_catquiz',
                [
                    'ability' => strval($subscaleability),
                    'difference' => strval($difference),
                ]);
            $series->set_labels([0 => $stringforchartlegend]);

            $colorvalue = $this->feedbackhelper->get_color_for_personability(
                $quizsettings,
                floatval($subscaleability),
                intval($primarycatscaleid)
            );
            $series->set_colors([0 => $colorvalue]);
            $chart->add_series($series);
            $chart->set_labels([0 => get_string('labelforrelativepersonabilitychart', 'local_catquiz')]);
        };
        $out = $OUTPUT->render($chart);

        return [
            'chart' => $out,
            'charttitle' => get_string('personabilitycharttitle', 'local_catquiz', $primarycatscale['name']),
        ];

    }
}
