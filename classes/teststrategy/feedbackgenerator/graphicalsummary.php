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
 * Class graphicalsummary.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use cache;
use html_writer;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\info;

/**
 * Compare the ability of this attempt to the average abilities of other students that took this test.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graphicalsummary extends feedbackgenerator {

    /**
     * Get student feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    protected function get_studentfeedback(array $data): array {
        return [];
    }

    /**
     * Get teacher feedback.
     *
     * @param array $feedbackdata
     *
     * @return array
     *
     */
    protected function get_teacherfeedback(array $feedbackdata): array {
        global $OUTPUT;
        $chart = $this->render_chart($feedbackdata['graphicalsummary']);
        $data['chart'] = $chart;
        if (array_key_exists('teststrategyname', $feedbackdata)) {
            $data['strategyname'] = $feedbackdata['teststrategyname'];
            $data['hasstrategyname'] = true;
        }
        $feedback = $OUTPUT->render_from_template(
            'local_catquiz/feedback/graphicalsummary',
            $data
        );

        return [
            'heading' => $this->get_heading(),
            'content' => $feedback,
        ];
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return get_string('quizgraphicalsummary', 'local_catquiz');
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return ['graphicalsummary'];
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
        if (! $cachedcontexts = $cache->get('context')) {
            return null;
        }
        $graphicalsummary = [];
        foreach ($cachedcontexts as $index => $data) {
            if ($index === 0) {
                continue;
            }

            $lastresponse = $data['lastresponse'];
            $lastquestion = $data['lastquestion'];
            $graphicalsummary[$index - 1]['id'] = $lastquestion->id;
            $graphicalsummary[$index - 1]['lastresponse'] = $lastresponse['fraction'];
            $graphicalsummary[$index - 1]['difficulty'] = $lastquestion->difficulty;
            $graphicalsummary[$index - 1]['questionscale'] = $lastquestion->catscaleid;
            $graphicalsummary[$index - 1]['fisherinformation'] = $lastquestion->fisherinformation ?? null;
            $graphicalsummary[$index - 1]['score'] = $lastquestion->score ?? null;
            [$before, $after] = $this->getneighborquestions(
                $lastquestion,
                $cachedcontexts[$index - 1]['questions']
            );
            $graphicalsummary[$index - 1]['difficultynextbefore'] = $before->difficulty;
            $graphicalsummary[$index - 1]['difficultynextafter'] = $after->difficulty;
            $graphicalsummary[$index - 1]['personability_after'] = $data['person_ability'][$data['catscaleid']];
            $graphicalsummary[$index - 1]['personability_before'] =
                $cachedcontexts[$index - 1]['person_ability'][$data['catscaleid']];
        }

        return [
            'graphicalsummary' => $graphicalsummary,
            'teststrategyname' => info::get_teststrategy($initialcontext['teststrategy'])
                ->get_description(),
        ];
    }

    /**
     * Render the moodle charts.
     *
     * @param array $data
     *
     * @return string
     */
    private function render_chart(array $data) {
        global $OUTPUT;

        $chart = new \core\chart_line();
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.
        $difficulties = array_map(fn($round) => $round['difficulty'] ?? null, $data);
        $questionscales = array_map(fn($round) => $round['questionscale'] ?? null, $data);
        $fractions = array_map(fn($round) => $round['lastresponse'] ?? null, $data);

        $hasnewabilities = array_key_exists('personability_after', $data[0]) && array_key_exists('personability_before', $data[0]);
        if ($hasnewabilities) {
            $abilitiesafter = array_map(fn($round) => $round['personability_after'] ?? null, $data);
            $abilitiesafterchart = new \core\chart_series(
                get_string('abilityintestedscale_after', 'local_catquiz'),
                $abilitiesafter
            );
            $chart->add_series($abilitiesafterchart);
            $abilitiesbefore = array_map(fn($round) => $round['personability_before'] ?? null, $data);
            $abilitiesbeforechart = new \core\chart_series(
                get_string('abilityintestedscale_before', 'local_catquiz'),
                $abilitiesbefore
            );
            $chart->add_series($abilitiesbeforechart);
        } else {
            $abilities = array_map(fn($round) => $round['personability'] ?? null, $data);
            $abilitieschart = new \core\chart_series(
                get_string('abilityintestedscale', 'local_catquiz'),
                $abilities
            );
            $chart->add_series($abilitieschart);
        }
        $fisherinfo = array_map(fn($round) => $round['fisherinformation'] ?? null, $data);
        $diffnextbefore = array_map(fn($round) => $round['difficultynextbefore'] ?? null, $data);
        $diffnextafter = array_map(fn($round) => $round['difficultynextafter'] ?? null, $data);
        $score = array_map(fn($round) => $round['score'] ?? null, $data);

        // Create the graph for difficulty.
        $difficultieschart = new \core\chart_series(
            get_string('difficulty', 'local_catquiz'),
            $difficulties
        );
        $questionscalechart = new \core\chart_series(
            'scale of selected question',
            $questionscales
        );
        $fractionschart = new \core\chart_series(
            get_string('response', 'local_catquiz'),
            $fractions
        );
        $fisherinfochart = new \core\chart_series(
            get_string('fisherinformation', 'local_catquiz'),
            $fisherinfo
        );
        $scorechart = new \core\chart_series(
            get_string('score', 'local_catquiz'),
            $score
        );
        $diffnextbeforechart = new \core\chart_series(
            get_string('difficulty_next_more_difficult', 'local_catquiz'),
            $diffnextbefore
        );
        $diffnextafterchart = new \core\chart_series(
            get_string('difficulty_next_easier', 'local_catquiz'),
            $diffnextafter
        );
        $chart->add_series($difficultieschart);
        $chart->add_series($fractionschart);
        $chart->add_series($fisherinfochart);
        $chart->add_series($scorechart);
        $chart->add_series($diffnextbeforechart);
        $chart->add_series($diffnextafterchart);
        $chart->add_series($questionscalechart);

        if (array_key_exists('id', $data[0])) {
            $chart->set_labels(array_map(fn($round) => $round['id'], $data));
        } else {
            $chart->set_labels(range(0, count($difficulties) - 1));
        }

        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }

    /**
     * Returns the next-more-difficult and next-easier questions surounding the
     * selected question.
     *
     * @param mixed $selectedquestion
     * @param array $questionpool
     * @param string $property Sort by this property before finding the neighbor questions.
     * @return array
     */
    private function getneighborquestions($selectedquestion, $questionpool, $property = "difficulty") {
        uasort($questionpool, fn($q1, $q2) => $q1->$property <=> $q2->$property);
        if (count($questionpool) === 1) {
            return [reset($questionpool), reset($questionpool)];
        }

        // We find the position of the selected question within the
        // $property-sorted question list, so that we can find the
        // neighboring questions.
        $pos = array_search($selectedquestion->id, array_keys($questionpool));

        $afterindex = $pos === count($questionpool) - 1 ? $pos : $pos + 1;
        [$after] = array_slice($questionpool, $afterindex, 1);

        $beforeindex = $pos === 0 ? 0 : $pos - 1;
        [$before] = array_slice($questionpool, $beforeindex, 1);

        return [$before, $after];
    }
}
