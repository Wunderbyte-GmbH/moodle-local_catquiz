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
use local_catquiz\catquiz;
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
     * @param array $data
     *
     * @return array
     *
     */
    protected function get_teacherfeedback(array $data): array {
        global $OUTPUT;
        $chart = $this->render_chart($data['graphicalsummary']);
        $feedback = $OUTPUT->render_from_template('local_catquiz/feedback/graphicalsummary', ['data' => $chart]);

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

            if ($index > 0) {
                $lastresponse = $data['lastresponse'];
                $lastquestion = $data['lastquestion'];
                $graphicalsummary[$index - 1]['lastresponse'] = $lastresponse['fraction'];
                $graphicalsummary[$index - 1]['difficulty'] = $lastquestion->difficulty;
                $graphicalsummary[$index - 1]['fisherinformation'] = $lastquestion->fisherinformation ?? null;
                $graphicalsummary[$index - 1]['score'] = $lastquestion->score ?? null;
            }
            if ($index === array_key_last($cachedcontexts)) {
                $lastquestion = $cache->get('lastquestion');
                $graphicalsummary[$index]['difficulty'] = $lastquestion->difficulty;
                $graphicalsummary[$index]['fisherinformation'] = $lastquestion->fisherinformation;
                $graphicalsummary[$index]['score'] = $lastquestion->score;
            }

            $nextbestbefore = isset($data['nextbestquestion_before'])
                ? $data['nextbestquestion_before']
                : null;
            $nextbestafter = isset($data['nextbestquestion_after'])
                ? $data['nextbestquestion_after']
                : null;

            $graphicalsummary[$index] = [
                'personability' => $data['person_ability'][$data['catscaleid']],
                'difficultynextbefore' => $nextbestbefore ? $nextbestbefore->difficulty ?? null : null,
                'difficultynextafter' => $nextbestafter ? $nextbestafter->difficulty ?? null : null,
            ];
        }
        return ['graphicalsummary' => $graphicalsummary];
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
        $fractions = array_map(fn($round) => $round['lastresponse'] ?? null, $data);
        $abilities = array_map(fn($round) => $round['personability'], $data);
        $fisherinfo = array_map(fn($round) => $round['fisherinformation'] ?? null, $data);
        $diffnextbefore = array_map(fn($round) => $round['difficultynextbefore'], $data);
        $diffnextafter = array_map(fn($round) => $round['difficultynextafter'], $data);
        $score = array_map(fn($round) => $round['score'] ?? null, $data);

        // Create the graph for difficulty.
        $difficultieschart = new \core\chart_series(
            get_string('difficulty', 'local_catquiz'),
            $difficulties
        );
        $fractionschart = new \core\chart_series(
            get_string('response', 'local_catquiz'),
            $fractions
        );
        $abilitieschart = new \core\chart_series(
            get_string('abilityintestedscale', 'local_catquiz'),
            $abilities
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
            get_string('difficulty_next_easier', 'local_catquiz'),
            $diffnextbefore
        );
        $diffnextafterchart = new \core\chart_series(
            get_string('difficulty_next_more_difficult', 'local_catquiz'),
            $diffnextafter
        );
        $chart->add_series($difficultieschart);
        $chart->add_series($fractionschart);
        $chart->add_series($abilitieschart);
        $chart->add_series($fisherinfochart);
        $chart->add_series($scorechart);
        $chart->add_series($diffnextbeforechart);
        $chart->add_series($diffnextafterchart);

        $labels = range(0, count($difficulties) - 1);
        $chart->set_labels($labels);

        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }
}
