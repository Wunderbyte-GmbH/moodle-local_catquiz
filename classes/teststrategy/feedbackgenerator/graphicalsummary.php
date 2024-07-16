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

use html_table;
use html_writer;
use local_catquiz\catscale;
use local_catquiz\teststrategy\feedback_helper;
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
     * Get student feedback.
     *
     * @param array $feedbackdata
     *
     * @return array
     *
     */
    public function get_studentfeedback(array $feedbackdata): array {
        global $OUTPUT;

        if (isset($feedbackdata['graphicalsummary_data'])) {
            $primaryscaleid = null;
            if (array_key_exists('graphicalsummary_primaryscale', $feedbackdata)) {
                $primaryscaleid = array_key_first($feedbackdata['graphicalsummary_primaryscale']);
            }
            $chart = $this->render_chart(
                $feedbackdata['graphicalsummary_data'],
                $primaryscaleid,
                $feedbackdata['graphicalsummary_otherscales'] ?? []
            );
        }
        if (isset($feedbackdata['graphicalsummary_data'])) {
            $table = $this->render_table($feedbackdata['graphicalsummary_data']);
        }
        $globalscale = catscale::return_catscale_object($this->get_progress()->get_quiz_settings()->catquiz_catscales);
        $globalscalename = $globalscale->name;

        $data['chart'] = $chart ?? "";
        $data['table'] = $table ?? "";
        $data['description'] = get_string(
            'graphicalsummary_description',
            'local_catquiz',
            feedback_helper::add_quotes($globalscalename)
        );
        // If this is a deficit strategy, display more info.
        $additionalinfo = false;
        if (array_key_exists('graphicalsummary_primaryscale', $feedbackdata)
            && isset($feedbackdata['primaryscale']->name)
        ) {
            $primaryscale = reset ($feedbackdata['graphicalsummary_primaryscale']);
            $quoteddeficitscale = feedback_helper::add_quotes($feedbackdata['primaryscale']->name);
            if ($primaryscale
                && array_key_exists('primarybecause', $primaryscale)
                && $primaryscale['primarybecause'] == 'lowestskill'
            ) {
                $additionalinfo = get_string('graphicalsummary_description_lowest', 'local_catquiz', $quoteddeficitscale);
            }
        }
        $data['additional_info'] = $additionalinfo;

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
     * Get teacher feedback.
     *
     * @param array $feedbackdata
     *
     * @return array
     *
     */
    protected function get_teacherfeedback(array $feedbackdata): array {
        return [];
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
        $progress = $this->get_progress();

        // If we already have all the data, just return them instead of adding
        // the last response again.
        $playedquestions = $progress->get_playedquestions();

        if (
            array_key_exists('graphicalsummary_data', $existingdata)
            && count($existingdata['graphicalsummary_data']) === count($playedquestions)
        ) {
            return $existingdata;
        }

        if (!$lastresponse = $progress->get_last_response()) {
            return null;
        }
        $lastquestion = $progress->get_playedquestions()[$lastresponse['qid']];

        $abilitieslist = $this->select_scales_for_report($newdata, $this->feedbacksettings, $existingdata['teststrategy']);
        $primaryscale = array_filter($abilitieslist, fn ($a) => array_key_exists('primary', $a) && $a['primary'] === true);

        // Append the data from the latest response to the existing graphical summary.
        $graphicalsummary = $existingdata['graphicalsummary_data'] ?? [];
        $new = [];
        $new['id'] = $lastquestion->id;
        $new['questionname'] = $lastquestion->name;
        $new['lastresponse'] = round($lastresponse['fraction'], self::PRECISION);
        $new['difficulty'] = $lastquestion->difficulty;
        $new['questionscale'] = $lastquestion->catscaleid;
        $new['questionscale_name'] = catscale::return_catscale_object(
            $lastquestion->catscaleid
        )->name;
        if (property_exists($lastquestion, 'fisherinformation')
            && is_float($lastquestion->fisherinformation)
        ) {
            $new['fisherinformation'] = sprintf('%.2f', $lastquestion->fisherinformation);
        } else {
            $new['fisherinformation'] = $lastquestion->is_pilot
                ? null
                : $this->get_rounded_or_null($lastquestion->fisherinformation, $existingdata['catscaleid']);
        }
        $new['personability_after'] = round($newdata['person_ability'][$newdata['catscaleid']], self::PRECISION);

        $graphicalsummary[] = $new;
        $otherscales = $existingdata['graphicalsummary_otherscales'] ?? [];
        foreach ($this->get_progress()->get_abilities() as $scaleid => $value) {
            $otherscales[$scaleid][] = round($value, self::PRECISION);
        }

        $teststrategyname = get_string(
            'teststrategy',
            'local_catquiz',
            info::get_teststrategy($existingdata['teststrategy'])
        ->get_description());

        $progress = $this->get_progress();
        return [
            'graphicalsummary_data' => $graphicalsummary,
            'teststrategyname' => $teststrategyname,
            'personabilities' => $progress->get_abilities(true),
            'graphicalsummary_primaryscale' => $primaryscale,
            'graphicalsummary_otherscales' => $otherscales,
            'primaryscale' => $this->get_primary_scale($existingdata, $newdata),
        ];
    }

    /**
     * Render the moodle charts.
     *
     * @param array $data
     * @param ?int $primaryscaleid
     * @param array $otherscales
     *
     * @return string
     */
    private function render_chart(array $data, ?int $primaryscaleid, array $otherscales) {
        global $OUTPUT;

        $chart = new \core\chart_line();
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.

        $hasnewabilities = array_key_exists('personability_after', $data[0]);
        if ($hasnewabilities) {
            $abilitiesafter = array_map(fn($round) => $round['personability_after'] ?? null, $data);
            $abilitiesafterchart = new \core\chart_series(
                get_string('abilityinglobalscale', 'local_catquiz'),
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

        $globalscaleid = $this->get_progress()->get_quiz_settings()->catquiz_catscales;
        $addprimary = $primaryscaleid
            && array_key_exists($primaryscaleid, $otherscales)
            && $primaryscaleid != $globalscaleid;
        if ($addprimary) {
            // Fill the missing values from the start with null values.
            $primaryvalues = array_pad($otherscales[$primaryscaleid], -count($data), null);
            $primarychart = new \core\chart_series(
                catscale::return_catscale_object($primaryscaleid)->name,
                $primaryvalues
            );
            $chart->add_series($primarychart);
        }

        $chart->set_labels(range(1, count($abilitiesafter)));

        return html_writer::tag('div', $OUTPUT->render_chart($chart, false), ['dir' => 'ltr']);
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
                $index + 1,
                $values['questionname'],
                $responsestring,
                $values['questionscale_name'],
                sprintf('%.2f', $values['personability_after']),
            ];
        }
        $table->data = $tabledata;
        return html_writer::table($table);
    }
}
