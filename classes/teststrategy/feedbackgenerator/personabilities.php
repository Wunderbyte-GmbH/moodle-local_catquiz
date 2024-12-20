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
use local_catquiz\teststrategy\feedback_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

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
            (array) $this->get_progress()->get_quiz_settings()
        );

        $scaleinfo = false;
        $primaryscaleid = isset($feedbackdata['primaryscale'])
            ? $feedbackdata['primaryscale']->id
            : false;
        if ($primaryscaleid && array_key_exists($primaryscaleid, $feedbackdata['personabilities_abilities'])) {
            $primaryscale = $feedbackdata['personabilities_abilities'][$primaryscaleid];
            if (
                array_key_exists('primarybecause', $primaryscale)
                && $primaryscale['primarybecause'] === 'lowestskill'
            ) {
                $scaleinfo = get_string(
                    'feedback_details_lowestskill',
                    'local_catquiz',
                    [
                        'name' => $primaryscale['name'],
                        'value' => feedback_helper::localize_float($primaryscale['value']),
                        'se' => feedback_helper::localize_float($feedbackdata['se'][$primaryscaleid]),
                    ]
                );
            }

            if (
                array_key_exists('primarybecause', $primaryscale)
                && $primaryscale['primarybecause'] === 'highestskill'
            ) {
                $scaleinfo = get_string(
                    'feedback_details_highestskill',
                    'local_catquiz',
                    [
                        'name' => $primaryscale['name'],
                        'value' => feedback_helper::localize_float($primaryscale['value']),
                        'se' => feedback_helper::localize_float($feedbackdata['se'][$primaryscaleid]),
                    ]
                );
            }
        }

        $pseudoindex = 0;
        $globalscalename = get_string('global_scale', 'local_catquiz');
        foreach ($feedbackdata['abilitieslist'] as $key => $v) {
            if ($v['catscaleid'] == $feedbackdata['catscaleid']) {
                $globalscalename = $v['name'];
                $feedbackdata['abilitieslist'][$key]['is_global'] = true;
                continue;
            }
            $pseudoindex++;
            $feedbackdata['abilitieslist'][$key]['pseudo_index'] = $pseudoindex;
            $feedbackdata['abilitieslist'][$key]['is_global'] = false;
        }
        $chartdescription = get_string(
            'detected_scales_chart_description',
            'local_catquiz',
            feedback_helper::add_quotes($globalscalename)
        );
        $description = get_string(
            'feedback_details_description',
            'local_catquiz',
            $globalscalename
        );
        $referencescale = [
            'name' => $globalscalename,
            'ability' => feedback_helper::localize_float(
                $this->get_progress()->get_abilities()[$feedbackdata['catscaleid']]
            ),
            'standarderror' => feedback_helper::localize_float(
                $feedbackdata['se'][$feedbackdata['catscaleid']]
            ),
            'itemsplayed' => $this->get_progress()->get_num_playedquestions(),
        ];

        // Create an abilityscore entry to be displayed in a table.
        // If the scale is marked as hidden, the value is empty ('-'), otherwise it is Ability (± SE).
        $abilities = array_map(
            function ($abilityarray) {
                $ishidden = array_key_exists('hidden', $abilityarray) && $abilityarray['hidden'];
                $abilityarray['abilityscore'] = $ishidden
                    ? '-'
                    : sprintf('%.2f (±%.2f)', $abilityarray['ability'], $abilityarray['standarderror']);
                return $abilityarray;
            },
            $feedbackdata['abilitieslist']
        );

        $feedback = $OUTPUT->render_from_template(
            'local_catquiz/feedback/personabilities',
            [
                'feedback_details_description' => $description,
                'scale_info' => $scaleinfo,
                'abilities' => $abilities,
                'referencescale' => $referencescale,
                'chartdisplay' => $abilitieschart,
                'chart_description' => $chartdescription,
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
        return get_string('feedback_details_heading', 'local_catquiz');
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
        if ($progress->get_abilities() === []) {
            return null;
        }

        $catscales = $newdata['catscales'];

        // Make sure that only feedback defined by strategy is rendered.
        if (!$personabilities = $this->get_restructured_abilities($existingdata, $newdata)) {
            return [];
        }
        $abilitieslist = [];
        $selectedscaleid = $this->get_primary_scale($existingdata, $newdata)->id ?? null;
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
            'primaryscale' => $this->get_primary_scale($existingdata, $newdata),
            'personabilities_abilities' => $personabilities,
            'se' => $newdata['se'] ?? null,
            'abilitieslist' => $abilitieslist,
        ];
    }

    /**
     * Generate data array for values of each catsacle.
     *
     * @param array $data
     * @param int $catscaleid
     * @param ?int $selectedscaleid
     * @param array $abilityarray
     * @param array $catscales
     * @param array $newdata
     *
     *
     */
    private function generate_data_for_scale(
        array &$data,
        int $catscaleid,
        ?int $selectedscaleid,
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
            $ability = feedback_helper::localize_float($ability);
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
        } else if (
            $this->feedbacksettings->displayscaleswithoutitemsplayed
            || $catscaleid == $selectedscaleid
            || $catscales[$catscaleid]->parentid == 0
        ) {
            $numberofitems = ['noplayed' => 0];
        } else if ($catscaleid != $selectedscaleid) {
            $numberofitems = "";
        }
        if (isset($newdata['se'][$catscaleid])) {
            $standarderror = feedback_helper::localize_float($newdata['se'][$catscaleid]);
        } else {
            $standarderror = "";
        }

        return [
            'hidden' => array_key_exists('hidden', $abilityarray) && $abilityarray['hidden'],
            'standarderror' => $standarderror,
            'ability' => $ability,
            'name' => $catscales[$catscaleid]->name,
            'catscaleid' => $catscaleid,
            'numberofitemsplayed' => $numberofitems,
            'isselectedscale' => $isselectedscale,
            'tooltiptitle' => $tooltiptitle,
            'is_global' => $catscaleid == $this->get_progress()->get_quiz_settings()->catquiz_catscales,
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
     *
     * @return array
     */
    private function render_chart(array $personabilities, array $quizsettings): array {
        global $OUTPUT;

        if (count($personabilities) < 2) {
            return [
                'chart' => get_string('nothingtocompare', 'local_catquiz'),
                'charttitle' => '',
            ];
        }
        $primarycatscaleid = null;
        foreach ($personabilities as $id => $pa) {
            if (isset($pa['primary']) && $pa['primary']) {
                $primarycatscaleid = intval($id);
                break;
            }
        }

        $primaryability = $this->get_progress()->get_abilities()[$quizsettings['catquiz_catscales']];

        $chart = new chart_bar();
        $chart->set_horizontal(true);
        $this->apply_sorting($personabilities, $primarycatscaleid);
        foreach ($personabilities as $scaleid => $abilityarray) {
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
                ]
            );
            $series->set_labels([0 => $stringforchartlegend]);

            $colorvalue = $this->feedbackhelper->get_color_for_personability(
                $quizsettings,
                floatval($subscaleability),
                intval($scaleid)
            );
            $series->set_colors([0 => $colorvalue]);
            $chart->add_series($series);
            $chart->set_labels([0 => get_string('labelforrelativepersonabilitychart', 'local_catquiz')]);
        };
        $chart->set_legend_options(['display' => false]);
        $out = $OUTPUT->render_chart($chart, false);
        $quizsettings = $this->get_progress()->get_quiz_settings();
        $globalscale = $this->get_global_scale();
        $globalscalename = $globalscale->name;
        return [
            'chart' => $out,
            'charttitle' => get_string(
                'personabilitycharttitle',
                'local_catquiz',
                $globalscalename
            ),
            'colorbar_legend' => [
                'feedbackbarlegend' => feedback_helper::get_colorbarlegend($quizsettings, $quizsettings->catquiz_catscales),
            ],
        ];
    }
}
