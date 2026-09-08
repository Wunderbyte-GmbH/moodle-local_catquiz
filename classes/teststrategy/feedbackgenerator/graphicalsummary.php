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
use html_table_cell;
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
            $showquestions = boolval(
                $this
                    ->get_progress()
                    ->get_quiz_settings()
                    ->catquiz_showquestion ?? false
            );
            $table = $this->render_table($feedbackdata['graphicalsummary_data'], $showquestions);
        }
        $globalscale = catscale::return_catscale_object($this->get_progress()->get_quiz_settings()->catquiz_catscales);
        $globalscalename = $globalscale->name;

        $data['chart'] = $chart ?? "";
        $data['table'] = $table ?? "";
        $data['description'] = get_string(
            'graphicalsummary_description',
            'local_catquiz',
            $globalscalename
        );
        // If this is a deficit strategy, display more info.
        $additionalinfo = false;
        if (
            array_key_exists('graphicalsummary_primaryscale', $feedbackdata)
            && isset($feedbackdata['primaryscale']->name)
        ) {
            $primaryscale = reset($feedbackdata['graphicalsummary_primaryscale']);
            $quoteddeficitscale = feedback_helper::add_quotes($feedbackdata['primaryscale']->name);
            if (
                $primaryscale
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

        $lastresponse = $progress->get_last_response();
        if (!is_array($lastresponse) || !isset($lastresponse['qid'])) {
            return null;
        }

        $playedquestions = $progress->get_playedquestions();
        if (!array_key_exists($lastresponse['qid'], $playedquestions)) {
            return null;
        }

        $lastquestion = $playedquestions[$lastresponse['qid']];
        if (empty($lastquestion)) {
            return null;
        }

        $abilitieslist = $this->select_scales_for_report($newdata, $this->feedbacksettings, $existingdata['teststrategy']);
        $primaryscale = array_filter($abilitieslist, fn ($a) => array_key_exists('primary', $a) && $a['primary'] === true);

        // Append the data from the latest response to the existing graphical summary.
        $graphicalsummary = $existingdata['graphicalsummary_data'] ?? [];
        $new = [];
        $new['id'] = $lastquestion->id;
        $new['questionname'] = $lastquestion->label;
        // The technical CAT item label (questionname) is kept for backward
        // compatibility; questiontitle carries the real Moodle question title so
        // the table can show the title as primary and the label as secondary.
        $new['questiontitle'] = $this->get_question_title((int) $lastquestion->id, (string) $lastquestion->label);
        $new['lastresponse'] = round($lastresponse['fraction'], self::PRECISION);
        // Store the real QUBA slot and question attempt id so the
        // "show question" modal fetches exactly this question attempt instead of
        // reconstructing the slot from the table row index (which is wrong after
        // reloads, duplicate slots or missing rows). responsesummary carries the
        // actually given answer. All three are absent for legacy attempts.
        $new['slot'] = $lastresponse['slot'] ?? null;
        $new['questionattemptid'] = $lastresponse['questionattemptid'] ?? null;
        $new['responsesummary'] = $lastresponse['responsesummary'] ?? null;
        $new['difficulty'] = $lastquestion->difficulty;
        $new['questionscale'] = $lastquestion->catscaleid;
        $new['questionscale_name'] = catscale::return_catscale_object(
            $lastquestion->catscaleid
        )->name;
        if (
            property_exists($lastquestion, 'fisherinformation')
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
            ->get_description()
        );

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
     * @param bool $viewquestion Adds links and modal to display questions.
     * @return ?string If all required data are present, the rendered HTML table.
     */
    private function render_table($data, $viewquestion = true): ?string {
        if (! array_key_exists('id', $data[0])) {
            return null;
        }

        $table = new html_table();
        $table->attributes['class'] = 'generaltable catquiz-graphicalsummary-table';

        // The correctness indicator is only shown when the test is configured to
        // reveal whether the given answer was right ("Indikator zur Korrektheit
        // der gegebenen Antwort anzeigen").
        $showresponse = (bool) (
            $this->get_progress()->get_quiz_settings()
                ->catquiz_questionfeedbacksettings
                ->catquiz_showquestionresponse ?? false
        );

        $table->colclasses = [
            'catquiz-col-number',
        ];
        if ($showresponse) {
            $table->colclasses[] = 'catquiz-col-correctness';
        }
        $table->colclasses = array_merge($table->colclasses, [
            'catquiz-col-question',
            'catquiz-col-scale',
            'catquiz-col-ability',
        ]);
        if ($viewquestion) {
            $table->colclasses[] = 'catquiz-col-action';
        }
        $table->head = [
            get_string('feedback_table_questionnumber', 'local_catquiz'),
        ];
        if ($showresponse) {
            $table->head[] = get_string('feedback_table_correctness', 'local_catquiz');
        }
        $table->head = array_merge($table->head, [
            get_string('question'),
            get_string('catscale', 'local_catquiz'),
            get_string('personability', 'local_catquiz'),
        ]);

        if ($viewquestion) {
            $table->head[] = get_string('showquestion', 'local_catquiz');
        }

        $tabledata = [];
        $filtercontext = \context_system::instance();
        // Resolve legacy rows (stored before the slot/question attempt
        // id were persisted) against the real question usage instead of guessing
        // the slot from the row index. Built once for the whole table.
        $slotmap = $this->build_slot_map_from_quba();
        $occurrences = [];
        foreach ($data as $index => $values) {
            $slot = $values['slot'] ?? null;
            $questionattemptid = $values['questionattemptid'] ?? null;
            $resolvedtitle = $values['questiontitle'] ?? null;
            $questionid = isset($values['id']) ? (int) $values['id'] : null;
            if ($questionid !== null && ($slot === null || $questionattemptid === null)) {
                $occurrence = $occurrences[$questionid] ?? 0;
                if (isset($slotmap[$questionid][$occurrence])) {
                    $resolved = $slotmap[$questionid][$occurrence];
                    $slot = $slot ?? $resolved['slot'];
                    $questionattemptid = $questionattemptid ?? $resolved['questionattemptid'];
                    $resolvedtitle = $resolvedtitle ?? $resolved['name'];
                }
            }
            if ($questionid !== null) {
                $occurrences[$questionid] = ($occurrences[$questionid] ?? 0) + 1;
            }
            // Only if the usage could not resolve the row at all (e.g. the usage
            // has been purged) do we fall back to the row index / zero.
            $slot = $slot ?? ($index + 1);
            $questionattemptid = $questionattemptid ?? 0;
            // Correctness indicator: icon plus a screen-reader accessible text, so
            // the verdict is not conveyed by colour/shape alone. The given answer
            // stays available as a tooltip; the response summary can contain
            // TeX/STACK markup, so it goes through format_text with the active
            // filters (MathJax). format_text also cleans the HTML.
            $responsestring = get_string('feedback_table_answerincorrect', 'local_catquiz');
            $iconclass = 'fa-solid fa-circle-xmark text-danger';
            if ($values['lastresponse'] == 1) {
                $responsestring = get_string('feedback_table_answercorrect', 'local_catquiz');
                $iconclass = 'fa-solid fa-circle-check text-success';
            } else if ($values['lastresponse'] > 0) {
                $responsestring = get_string('feedback_table_answerpartlycorrect', 'local_catquiz');
                $iconclass = 'fa-solid fa-triangle-exclamation text-warning';
            }

            $correctnesscell = null;
            if ($showresponse) {
                $icon = html_writer::tag('i', '', [
                    'class' => $iconclass . ' catquiz-response-icon',
                    'aria-hidden' => 'true',
                    'title' => $responsestring,
                ]);
                $correctnesshtml = $icon . html_writer::tag(
                    'span',
                    $responsestring,
                    ['class' => 'sr-only catquiz-response-verdict']
                );

                $responsesummary = $values['responsesummary'] ?? null;
                if ($responsesummary !== null && $responsesummary !== '') {
                    $answerhtml = format_text(
                        $responsesummary,
                        FORMAT_HTML,
                        ['filter' => true, 'context' => $filtercontext]
                    );
                    $correctnesshtml .= html_writer::tag(
                        'span',
                        get_string('feedback_table_givenanswer', 'local_catquiz') . ' ',
                        ['class' => 'sr-only catquiz-response-answerlabel']
                    ) . html_writer::tag(
                        'span',
                        $answerhtml,
                        ['class' => 'sr-only catquiz-responsesummary']
                    );
                }

                $correctnesscell = new html_table_cell($correctnesshtml);
                $correctnesscell->attributes = ['class' => 'catquiz-col-correctness text-center'];
            }

            // Question column: the real Moodle question title as primary text and
            // the technical CAT item label as secondary information. Legacy rows
            // without a stored title fall back to the label.
            $title = (string) ($resolvedtitle ?? $values['questionname']);
            $itemlabel = (string) $values['questionname'];
            $questionhtml = html_writer::tag('span', s($title), ['class' => 'catquiz-question-title']);
            if ($itemlabel !== '' && $itemlabel !== $title) {
                $questionhtml .= html_writer::empty_tag('br')
                    . html_writer::tag('span', s($itemlabel), ['class' => 'catquiz-question-label text-muted']);
            }
            $dataattributes = [
                'data-name' => $title,
                'data-attemptid' => $this->get_progress()->get_attemptid(),
                'data-slot' => $slot,
                'data-questionattemptid' => $questionattemptid,
            ];
            $questioncell = $viewquestion
                ? html_writer::tag('span', $questionhtml, ['class' => 'clickable'] + $dataattributes)
                : $questionhtml;
            $newrow = [
                $index + 1,
            ];
            if ($correctnesscell !== null) {
                $newrow[] = $correctnesscell;
            }
            $newrow = array_merge($newrow, [
                $questioncell,
                $values['questionscale_name'],
                sprintf('%.2f', $values['personability_after']),
            ]);

            if ($viewquestion) {
                // Real interactive button (not a bare icon) so it works inside a
                // form and is reachable for assistive technology.
                $button = html_writer::tag(
                    'button',
                    html_writer::tag('i', '', ['class' => 'fa fa-search', 'aria-hidden' => 'true']),
                    [
                        'type' => 'button',
                        'class' => 'btn btn-link p-0 questionbutton clickable',
                        'aria-label' => get_string('showquestion', 'local_catquiz'),
                        'title' => get_string('showquestion', 'local_catquiz'),
                    ] + $dataattributes
                );
                $searchcol = new html_table_cell($button);
                $searchcol->attributes = ['class' => 'questionbutton catquiz-col-action'];
                $newrow[] = $searchcol;
            }
            $tabledata[] = $newrow;
        }
        $table->data = $tabledata;
        return html_writer::div(
            html_writer::table($table),
            'table-responsive catquiz-graphicalsummary-table-wrapper'
        );
    }

    /**
     * Returns the real Moodle question title for a question id, falling back to
     * the CAT item label if the question can no longer be loaded.
     *
     * @param int $questionid
     * @param string $fallback
     * @return string
     */
    private function get_question_title(int $questionid, string $fallback): string {
        try {
            $question = \question_bank::load_question($questionid);
            $name = $question->name ?? '';
            return $name !== '' ? $name : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    /**
     * Builds a map from question id to its occurrences in this attempt's question
     * usage, each carrying the real slot, question attempt id and title. Legacy
     * graphical-summary rows stored before the slot and question attempt id were
     * persisted are resolved through this map by question id and occurrence,
     * rather than by guessing the slot from the table row index. Loaded once per
     * table, so no N+1 usage/DB lookups are issued per row.
     *
     * @return array<int, array<int, array{slot: int, questionattemptid: int, name: string}>>
     */
    private function build_slot_map_from_quba(): array {
        global $DB;
        $map = [];
        try {
            $attemptid = $this->get_progress()->get_attemptid();
            $attempt = $DB->get_record('adaptivequiz_attempt', ['id' => $attemptid], 'uniqueid', IGNORE_MISSING);
            if (!$attempt) {
                return $map;
            }
            $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
            foreach ($quba->get_slots() as $slot) {
                $qa = $quba->get_question_attempt($slot);
                $question = $qa->get_question();
                $map[(int) $question->id][] = [
                    'slot' => (int) $slot,
                    'questionattemptid' => (int) $qa->get_database_id(),
                    'name' => (string) $question->name,
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $map;
    }
}
