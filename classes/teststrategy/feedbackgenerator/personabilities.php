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
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use cache;
use core\chart_axis;
use core\chart_bar;
use core\chart_series;
use local_catquiz\catquiz;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\teststrategy\feedbackgenerator;
use stdClass;

/**
 * Returns rendered person abilities.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class personabilities extends feedbackgenerator {

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
        // Feedback data is rendered from template.

        $chart = $this->render_chart($data);

        $feedback = $OUTPUT->render_from_template(
            'local_catquiz/feedback/personabilities',
            [
                'abilities' => $data['feedback_personabilities'],
                'chartdisplay' => $chart,
            ]
        );

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
            'feedback_personabilities',
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
     * Loads data.
     *
     * @param int $attemptid
     * @param array $initialcontext
     *
     * @return array|null
     *
     */
    public function load_data(int $attemptid, array $initialcontext): ?array {

        // We get the data here.
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $personabilities = $initialcontext['personabilities'] ?? $cache->get('personabilities') ?: [];
        if ($personabilities === []) {
            return null;
        }

        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        // Check how many questions have been played whithin each subscale.
        if (! $cachedcontexts = $cache->get('context')) {
            return null;
        }
        $countscales = [];
        foreach ($cachedcontexts as $index => $data) {
            if ($index === 0) {
                continue;
            }
            $lastquestion = $data['lastquestion'];
            $scaleid = $lastquestion->catscaleid;
            if (isset($countscales[$scaleid])) {
                $countscales[$scaleid] ++;
            } else {
                $countscales[$scaleid] = 1;
            }

        }
        $catscales = catquiz::get_catscales(array_keys($personabilities));

        // Write scaleid counter into personabilites array.

        $data = [];
        foreach ($personabilities as $catscaleid => $ability) {
            if (abs(floatval($ability)) === abs(floatval(LOCAL_CATQUIZ_PERSONABILITY_MAX))) {
                if ($ability < 0) {
                    $ability = get_string('allquestionsincorrect', 'local_catquiz');
                } else {
                    $ability = get_string('allquestionscorrect', 'local_catquiz');
                }
            } else {
                $ability = sprintf("%.2f", $ability);
            }
            $data[] = [
                'ability' => $ability,
                'name' => $catscales[$catscaleid]->name,
                'catscaleid' => $catscaleid,
                'numberofitemsplayed' => isset($countscales[$catscaleid]) ? $countscales[$catscaleid] : 0,
            ];
        }
        return ['feedback_personabilities' => $data];
    }

    /**
     * Render chart for personabilities.
     *
     * @param array $data
     *
     * @return array
     *
     */
    private function render_chart(array $data) {
        global $OUTPUT;

        if (gettype($data['quizsettings']) != "array") {
            $quizsettings = (array)$data['quizsettings'];
        } else {
            $quizsettings = $data['quizsettings'];
        }
        $parentscaleid = $quizsettings['catquiz_catscales'];

        $parentability = 0;
        // First we get the personability of the parentscale.
        foreach ($data['feedback_personabilities'] as $dataitem) {
            if ($dataitem['catscaleid'] == $parentscaleid) {
                $parentability = floatval($dataitem['ability']);
            }
        }
        $chart = new chart_bar();
        $chart->set_horizontal(true);
        $chartseries = [];
        $chartseries['series'] = [];
        $chartseries['labels'] = [];
        foreach ($data['feedback_personabilities'] as $dataitem) {
            $subscaleability = floatval($dataitem['ability']);
            $subscalename = $dataitem['name'];
            $difference = round($subscaleability - $parentability, 2);
            $series = new chart_series($subscalename, [0 => $difference]);

            $stringforchartlegend = get_string(
                'chartlegendabilityrelative',
                'local_catquiz',
                [
                    'ability' => strval($subscaleability),
                    'difference' => strval($difference),
                ]);
            $series->set_labels([0 => $stringforchartlegend]);

            $colorvalue = $this->get_color_for_personabily(
                $quizsettings,
                floatval($subscaleability),
                floatval($dataitem['catscaleid'])
            );
            $series->set_colors([0 => $colorvalue]);
            $chart->add_series($series);
            $chart->set_labels([0 => get_string('labelforrelativepersonabilitychart', 'local_catquiz')]);
        };
        $out = $OUTPUT->render($chart);

        return [
            'chart' => $out,
            'charttitle' => get_string('personabilitycharttitle', 'local_catquiz'),
        ];

    }

    /**
     * Write information about colorgradient for colorbar.
     *
     * @param array $quizsettings
     * @param float $personability
     * @param float $catscaleid
     * @return string
     *
     */
    private function get_color_for_personabily(array $quizsettings, float $personability, float $catscaleid): string {
        $default = "#000000";
        if (!$quizsettings ||
            $personability < PERSONABILITY_LOWER_LIMIT ||
            $personability > PERSONABILITY_UPPER_LIMIT) {
            return $default;
        }
        $numberoffeedbackoptions = intval($quizsettings['numberoffeedbackoptionsselect']) ?? 8;
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);

        for ($i = 1; $i <= $numberoffeedbackoptions; $i++) {
            $rangestartkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_" . $i;
            $rangeendkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $i;
            $rangestart = floatval($quizsettings[$rangestartkey]);
            $rangeend = floatval($quizsettings[$rangeendkey]);

            if ($personability >= $rangestart && $personability <= $rangeend) {
                $colorkey = 'wb_colourpicker_' . $catscaleid . '_' . $i;
                $colorname = $quizsettings[$colorkey];
                return $colorarray[$colorname];
            }

        }
        return $default;
    }
}
