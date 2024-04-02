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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use cache;
use html_table;
use html_writer;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\info;

/**
 * Compare the ability of this attempt to the average abilities of other students that took this test.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graphicalsummary extends feedbackgenerator {

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
    public function get_studentfeedback(array $data): array {
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

        if (isset($feedbackdata['graphicalsummary_data'])) {
            $chart = $this->render_chart($feedbackdata['graphicalsummary_data']);
        }
        if (isset($feedbackdata['graphicalsummary_data'])) {
            $table = $this->render_table($feedbackdata['graphicalsummary_data']);
        }

        $catscaleid = $feedbackdata['catscaleid'];

        $catscale = catscale::return_catscale_object($catscaleid);

        $participationcharts = $this->render_participationcharts(
            $feedbackdata,
            $catscaleid,
            $feedbackdata['catscaleid'],
            $catscale->name);

        $data['chart'] = $chart ?? "";
        $data['strategyname'] = $feedbackdata['teststrategyname'] ?? "";
        $data['table'] = $table ?? "";

        $data['attemptscounterchart'] = $participationcharts['attemptscounterchart']['chart'] ?? "";
        $data['attemptresultstackchart'] = $participationcharts['attemptresultstackchart']['chart'] ?? "";

        $feedback = $OUTPUT->render_from_template(
            'local_catquiz/feedback/graphicalsummary',
            $data
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
     * For specific feedbackdata defined in generators.
     *
     * @param array $feedbackdata
     */
    public function apply_settings_to_feedbackdata(array $feedbackdata) {

        // Exclude feedbackkeys from feedbackdata.
        $feedbackdata = $this->feedbacksettings->hide_defined_elements($feedbackdata, $this->get_generatorname());
        return $feedbackdata;
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
     * Get generatorname.
     *
     * @return string
     *
     */
    public function get_generatorname(): string {
        return 'graphicalsummary';
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'graphicalsummary_data',
            'teststrategyname',
            'personabilities',
        ];
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

        // If we already have all the data, just return them instead of adding
        // the last response again.
        $playedquestions = $progress->get_playedquestions(true);

        if (
            array_key_exists('graphicalsummary_data', $existingdata)
            && count($existingdata['graphicalsummary_data']) === count($playedquestions)
        ) {
            return $existingdata;
        }

        if (!$lastresponse = $progress->get_last_response(true)) {
            return null;
        }
        $lastquestion = $progress->get_playedquestions()[$lastresponse['qid']];

        // Append the data from the latest response to the existing graphical summary.
        $graphicalsummary = $existingdata['graphicalsummary_data'] ?? [];
        $new = [];
        $new['id'] = $lastquestion->id;
        $new['questionname'] = $lastquestion->name;
        $new['lastresponse'] = $lastresponse['fraction'];
        $new['difficulty'] = $lastquestion->difficulty;
        $new['questionscale'] = $lastquestion->catscaleid;
        $new['questionscale_name'] = catscale::return_catscale_object(
            $lastquestion->catscaleid
        )->name;
        $new['fisherinformation'] = $lastquestion
            ->fisherinformation[$existingdata['catscaleid']] ?? null;
        $new['score'] = $lastquestion->score ?? null;
        $new['difficultynextbefore'] = null;
        $new['difficultynextafter'] = null;
        $new['personability_after'] = $newdata['person_ability'][$newdata['catscaleid']];
        $new['personability_before'] =
            $existingdata['personabilities'][$existingdata['catscaleid']]['value'] ?? null;

            // TODO: Here is always root scale reference for comparison is this wanted?
            // Or should it be compared to same scale?
        $graphicalsummary[] = $new;

        $teststrategyname = get_string(
            'teststrategy',
            'local_catquiz',
            info::get_teststrategy($existingdata['teststrategy'])
        ->get_description());

        return [
            'graphicalsummary_data' => $graphicalsummary,
            'teststrategyname' => $teststrategyname,
            'personabilities' => $newdata['progress']->get_abilities(),
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
        $difficultieschart = new \core\chart_series(
            get_string('difficulty', 'local_catquiz'),
            $difficulties
        );
        $chart->add_series($difficultieschart);

        $fractions = array_map(fn($round) => $round['lastresponse'] ?? null, $data);
        $fractionschart = new \core\chart_series(
            get_string('response', 'local_catquiz'),
            $fractions
        );
        $chart->add_series($fractionschart);

        $hasnewabilities = array_key_exists('personability_after', $data[0]) && array_key_exists('personability_before', $data[0]);
        if ($hasnewabilities) {
            $abilitiesbefore = array_map(fn($round) => $round['personability_before'] ?? null, $data);
            $abilitiesbeforechart = new \core\chart_series(
                get_string('abilityintestedscale_before', 'local_catquiz'),
                $abilitiesbefore
            );
            $chart->add_series($abilitiesbeforechart);

            $abilitiesafter = array_map(fn($round) => $round['personability_after'] ?? null, $data);
            $abilitiesafterchart = new \core\chart_series(
                get_string('abilityintestedscale_after', 'local_catquiz'),
                $abilitiesafter
            );
            $chart->add_series($abilitiesafterchart);
        } else {
            $abilities = array_map(fn($round) => $round['personability'] ?? null, $data);
            $abilitieschart = new \core\chart_series(
                get_string('abilityintestedscale', 'local_catquiz'),
                $abilities
            );
            $chart->add_series($abilitieschart);
        }

        $fisherinfo = array_map(fn($round) => $round['fisherinformation'] ?? null, $data);
        $fisherinfochart = new \core\chart_series(
            get_string('fisherinformation', 'local_catquiz'),
            $fisherinfo
        );
        $chart->add_series($fisherinfochart);

        $diffnextbefore = array_map(fn($round) => $round['difficultynextbefore'] ?? null, $data);
        $diffnextbeforechart = new \core\chart_series(
            get_string('difficulty_next_more_difficult', 'local_catquiz'),
            $diffnextbefore
        );
        $chart->add_series($diffnextbeforechart);

        $diffnextafter = array_map(fn($round) => $round['difficultynextafter'] ?? null, $data);
        $diffnextafterchart = new \core\chart_series(
            get_string('difficulty_next_easier', 'local_catquiz'),
            $diffnextafter
        );
        $chart->add_series($diffnextafterchart);

        $score = array_map(fn($round) => $round['score'] ?? null, $data);
        $scorechart = new \core\chart_series(
            get_string('score', 'local_catquiz'),
            $score
        );
        $chart->add_series($scorechart);

        if (array_key_exists('id', $data[0])) {
            $chart->set_labels(array_map(fn($round) => $round['id'], $data));
        } else {
            $chart->set_labels(range(0, count($difficulties) - 1));
        }

        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }

    /**
     * Render a table with data that do not fit in the chart
     *
     * @param array $data The feedback data
     * @return ?string If all required data are present, the rendered HTML table.
     */
    private function render_table($data): ?string {
        if (! array_key_exists('id', $data[0])) {
            return null;
        }

        $table = new html_table();
        $table->head = [
            get_string('feedback_table_questionnumber', 'local_catquiz'),
            get_string('question'),
            get_string('response', 'local_catquiz'),
            get_string('catscale', 'local_catquiz'),
            get_string('personability', 'local_catquiz'),
        ];

        $tabledata = [];
        foreach ($data as $index => $values) {
            $responsestring = get_string(
                'feedback_table_answerincorrect',
                'local_catquiz'
            );
            if ($values['lastresponse'] == 1) {
                $responsestring = get_string(
                    'feedback_table_answercorrect',
                    'local_catquiz'
                );
            } else if ($values['lastresponse'] > 0) {
                $responsestring = get_string(
                    'feedback_table_answerpartlycorrect',
                    'local_catquiz'
                );
            }
            $tabledata[] = [
                $index,
                $values['questionname'],
                $responsestring,
                $values['questionscale_name'],
                $values['personability_after'],
            ];
        }
        $table->data = $tabledata;
        return html_writer::table($table);
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


    /**
     * Render the charts with data about participation by day.
     *
     * @param array $data
     * @param int $primarycatscaleid
     * @param int $parentscaleid
     * @param string $catscalename
     * @param int $contextid
     *
     * @return array
     */
    private function render_participationcharts(
        array $data,
        int $primarycatscaleid,
        int $parentscaleid,
        string $catscalename,
        int $contextid = 0) {

        // In case you want to make context a changeable param of feedbacksettings, apply logic here.
        if (empty($contextid)) {
            $contextid = $data['contextid'];
        }

        $records = catquiz::get_attempts(
            null,
            $parentscaleid,
            $data['courseid'],
            $contextid,
            null,
            null);
        if (count($records) < 2) {
            return [];
        }
        // Get all items of this catscale and catcontext.
        $startingrecord = reset($records);
        $beginningoftimerange = intval($startingrecord->endtime);
        $timerange = personabilities::get_timerange_for_attempts($beginningoftimerange, $data['endtime']);
        $attemptsbytimerange = personabilities::order_attempts_by_timerange($records, $primarycatscaleid, $timerange);
        $attemptscounterchart = $this->render_attemptscounterchart($attemptsbytimerange);
        $attemptresultstackchart = $this->render_attemptresultstackchart($attemptsbytimerange, $primarycatscaleid, $data);

        return [
            'attemptscounterchart' => $attemptscounterchart,
            'attemptresultstackchart' => $attemptresultstackchart,
            'attemptchartstitle' => get_string('attemptchartstitle', 'local_catquiz', $catscalename),
        ];

    }

    /**
     * Chart grouping by date and counting attempts.
     *
     * @param array $attemptsbytimerange
     *
     * @return array
     */
    private function render_attemptscounterchart(array $attemptsbytimerange) {
        global $OUTPUT;
        $counter = [];
        $labels = [];
        foreach ($attemptsbytimerange as $timestamp => $attempts) {
            $counter[] = count($attempts);
            $labels[] = (string)$timestamp;
        }
        $chart = new \core\chart_line();
        $chart->set_smooth(true);

        $series = new \core\chart_series(
            get_string('numberofattempts', 'local_catquiz'),
            $counter
        );
        $chart->add_series($series);
        $chart->set_labels($labels);
        $out = $OUTPUT->render($chart);

        return [
            'chart' => $out,
            'charttitle' => get_string('numberofattempts', 'local_catquiz'),
        ];
    }

    /**
     * Chart grouping by date showing attempt results.
     *
     * @param array $attemptsbytimerange
     * @param int $catscaleid
     * @param array $attemptdata
     *
     * @return array
     */
    private function render_attemptresultstackchart(array $attemptsbytimerange, int $catscaleid, array $attemptdata) {
        global $OUTPUT;
        $series = [];
        $labels = [];
        $quizsettings = $attemptdata['quizsettings'];

        foreach ($attemptsbytimerange as $timestamp => $attempts) {
            $labels[] = (string)$timestamp;
            foreach ($attempts as $attempt) {
                if (is_object($attempt)) {
                    $a = (float) $attempt->value;
                    $color = $this->get_color_for_personability((array)$quizsettings, $a, $catscaleid);
                } else {
                    // This is to stay backwards compatible.
                    $color = $this->get_color_for_personability((array)$quizsettings, $attempt, $catscaleid);
                }

                if (!isset($series[$timestamp][$color])) {
                        $series[$timestamp][$color] = 1;
                } else {
                        $series[$timestamp][$color] += 1;
                }
            }
        }

        $chart = new \core\chart_bar();
        $chart->set_stacked(true);

        $colorsarray = $this->feedbacksettings->get_defined_feedbackcolors_for_scale((array)$quizsettings, $catscaleid);

        foreach ($colorsarray as $colorcode => $rangesarray) {
            $serie = [];
            foreach ($series as $timestamp => $cc) {
                $valuefound = false;
                foreach ($cc as $cc => $elementscounter) {
                    if ($colorcode != $cc) {
                        continue;
                    }
                    $valuefound = true;
                    $serie[] = $elementscounter;
                }
                if (!$valuefound) {
                    $serie[] = 0;
                }
            }
            $rangestart = $rangesarray['rangestart'];
            $rangeend = $rangesarray['rangeend'];
            $labelstring = get_string(
                'personabilityrangestring',
                'local_catquiz',
                ['rangestart' => $rangestart, 'rangeend' => $rangeend]);
            $s = new \core\chart_series(
                $labelstring,
                $serie
            );
            $s->set_colors([0 => $colorcode]);
            $chart->add_series($s);
        }

        $chart->set_labels($labels);
        $out = $OUTPUT->render($chart);

        return [
            'chart' => $out,
            'charttitle' => get_string('numberofattempts', 'local_catquiz'),
        ];
    }
}
