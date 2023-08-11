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

namespace local_catquiz\output;

use coding_exception;
use context_system;
use html_writer;
use local_catquiz\catmodel_info;
use local_catquiz\catquiz;
use local_catquiz\form\item_model_override_selector;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_raschmodel;
use local_catquiz\local\model\model_strategy;
use local_catquiz\table\testitems_table;
use moodle_url;
use templatable;
use renderable;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testitemdashboard implements renderable, templatable {

    /** @var integer of testitemid */
    public int $testitemid = 0;

    /**
     * @var integer
     */
    private int $contextid = 0;

    public int $catscaleid;

    private catmodel_info $catmodelinfo;

    /**
     * Either returns one tree or treearray for every parentnode
     *
     * @param int $fulltree
     * @param boolean $allowedit
     * @param int $contextid
     * @return array
     */
    public function __construct(int $testitemid, int $contextid, int $catscaleid) {

        $this->testitemid = $testitemid;
        $this->contextid = $contextid;
        $this->catmodel_info = new catmodel_info();
        $this->catscaleid = $catscaleid;
    }

    /**
     * Render the moodle charts.
     *
     * @return void
     */
    private function render_modelcards() {

        global $OUTPUT;

        $returnarray = [];

        list($modelitemparams) = $this
            ->catmodel_info
            ->get_context_parameters($this->contextid, $this->catscaleid);

        $chart = new \core\chart_line();
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.

        foreach ($modelitemparams as $modelname => $itemparamlist) {

            $item = $itemparamlist[$this->testitemid];
            if (! $item) {
                continue;
            }

            $difficulty = $item->get_difficulty();
            $likelihoods = [];
            for ($ability = -5; $ability <= 5; $ability += 0.5) {
                $likelihoods[] = model_raschmodel::likelihood_1pl($ability, $difficulty);
            }

            // Create the graph for difficulty.
            $series1 = new \core\chart_series(
                sprintf(
                    '%s: %s',
                    get_string('pluginname', sprintf('catmodel_%s', $modelname)),
                    $difficulty
                ),
                array_values($likelihoods));
            $labels = range(-5, 5, 0.5);
            $chart->add_series($series1);
            $chart->set_labels($labels);
            $chart->get_xaxis(0, true)->set_label(get_string('personability', 'local_catquiz'));

        }
        $body = html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);

        $returnarray[] = [
            'title' => get_string('likelihood', 'local_catquiz'),
            'body' => $body,
        ];

        return $returnarray;
    }

    /**
     * Render the moodle charts.
     *
     * @return array
     */
    private function get_testitems_stats_data() {

        global $DB;

        list ($sql, $params) = catquiz::get_sql_for_questions_answered([$this->testitemid], [$this->contextid]);
        $numberofanswers = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_usages_in_tests([$this->testitemid], [$this->contextid]);
        $numberofusagesintests = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_by_distinct_persons([$this->testitemid], [$this->contextid]);
        $numberofpersonsanswered = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_correct([$this->testitemid], [$this->contextid]);
        $numberofanswerscorrect = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_incorrect([$this->testitemid], [$this->contextid]);
        $numberofanswersincorrect = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_partlycorrect([$this->testitemid], [$this->contextid]);
        $numberofanswerspartlycorrect = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_average([$this->testitemid], [$this->contextid]);
        $averageofallanswers = $DB->get_field_sql($sql, $params) ?: get_string('notavailable', 'core');

        return [
            [
                'title' => get_string('numberofanswers', 'local_catquiz'),
                'body' => $numberofanswers,
            ],
            [
                'title' => get_string('numberofusagesintests', 'local_catquiz'),
                'body' => $numberofusagesintests,
            ],
            [
                'title' => get_string('numberofpersonsanswered', 'local_catquiz'),
                'body' => $numberofpersonsanswered,
            ],
            [
                'title' => get_string('numberofanswerscorrect', 'local_catquiz'),
                'body' => $numberofanswerscorrect,
            ],
            [
                'title' => get_string('numberofanswersincorrect', 'local_catquiz'),
                'body' => $numberofanswersincorrect,
            ],
            [
                'title' => get_string('numberofanswerspartlycorrect', 'local_catquiz'),
                'body' => $numberofanswerspartlycorrect,
            ],
            [
                'title' => get_string('averageofallanswers', 'local_catquiz'),
                'body' => $averageofallanswers,
            ]
        ];
    }

    private function render_overrides_form() {
        $form = new item_model_override_selector();
        $form->set_data_for_dynamic_submission();
        return html_writer::div($form->render(), '', ['id' => 'model_override_form']);
    }
    private function render_contextselector() {
        $form = new \local_catquiz\form\contextselector(null, null, 'post', '', [], true, ['contextid' => $this->contextid]);
        // Set the form data with the same method that is called when loaded from JS. It should correctly set the data for the supplied arguments.
        $form->set_data_for_dynamic_submission();
        // Render the form in a specific container, there should be nothing else in the same container.
        return html_writer::div($form->render(), '', ['id' => 'select_context_form']);
    }

    /**
     * @return string
     * @throws coding_exception
     */
    private function get_itemstatus() {
        global $DB;
        list ($sql, $params) = catquiz::get_sql_for_max_status_for_item($this->testitemid, $this->contextid);
        $maxstatus = intval($DB->get_field_sql($sql, $params));
        switch ($maxstatus) {
            case model_item_param::STATUS_NOT_SET:
                return get_string('statusnotset', 'local_catquiz');
            case model_item_param::STATUS_NOT_CALCULATED:
                return get_string('statusnotcalculated', 'local_catquiz');
            case model_item_param::STATUS_SET_BY_STRATEGY:
                return get_string('statussetautomatically', 'local_catquiz');
            case model_item_param::STATUS_SET_MANUALLY:
                return get_string('statussetmanually', 'local_catquiz');

            default:
                return get_string('notavailable', 'core');
        }
    }
    /**
     * Check if we display a table or a detailview of a specific item.
     */
    private function get_detail_data() {
        global $DB;
        if (empty($this->testitemid)) {
            return;
        }
        $catcontext = empty($this->contextid) ? catquiz::get_default_context_id() : $this->contextid; // If no context is set, get default context from DB.

        // Get the record for the specific userid (fetched from optional param).
        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_catscalequestions([$this->catscaleid], $catcontext, [], [], $this->testitemid);
        $idcheck = "id=:userid";
        $sql = "SELECT $select FROM $from WHERE $where AND $idcheck";
        $recordinarray = $DB->get_records_sql($sql, $params, IGNORE_MISSING);

        if (empty($recordinarray)) {
            // Throw error: no record was found with id: $params['userid'];
        }
        $record = $recordinarray[$this->testitemid];

        // Output for testitem details card.
        $detailcardoutput = $this->render_detailcard_of_testitem($record); // return array
        return $detailcardoutput;
    }

    private function render_detailcard_of_testitem(object $record) {

        $title = get_string('general', 'core');

        $body['id'] = $record->id;
        $body['type'] = $record->qtype;
        $body['status'] = $record->status;
        $body['model'] = $record->model;
        $body['attempts'] = $record->attempts;

        return [
            'title' => $title,
            'body' => $body,
        ];
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        $url = new moodle_url('/local/catquiz/manage_catscales.php');

        $data = [
            'returnurl' => $url->out(),
            'models' => $this->render_modelcards(),
            'statcards' => $this->get_testitems_stats_data(),
            'contextselector' => $this->render_contextselector(),
            'overridesforms' => $this->render_overrides_form(),
            'itemstatus' => $this->get_itemstatus(),
        ];
        return $data;
    }

    /**
     * Return the detail data of one item.
     * @return array
     */
    public function return_as_array(): array {

        $data = [
            'detailview' => $this->get_detail_data(),
           'models' => $this->render_modelcards(),
           'statcards' => $this->get_testitems_stats_data(),
           'overridesforms' => $this->render_overrides_form(),
           'itemstatus' => $this->get_itemstatus(),
        ];
        return $data;
    }
}
