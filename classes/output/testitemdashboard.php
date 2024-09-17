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

namespace local_catquiz\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->libdir . '/questionlib.php');

use coding_exception;
use context_system;
use html_writer;
use local_catquiz\catmodel_info;
use local_catquiz\catquiz;
use local_catquiz\form\item_model_override_selector;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_raschmodel;
use local_catquiz\local\model\model_strategy;
use local_catquiz\output\catscalemanager\questions\questiondetailview;
use local_catquiz\output\catscalemanager\scaleandcontexselector;
use moodle_url;
use qbank_previewquestion\question_preview_options;
use question_bank;
use question_display_options;
use question_engine;
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

    /** @var int of testitemid */
    public int $testitemid = 0;

    /**
     * @var int
     */
    private int $contextid = 0;

    /**
     * @var int
     */
    public int $catscaleid;

    /**
     * @var string
     */
    public string $component;

    /**
     * @var catmodel_info catmodelinfo
     */
    private catmodel_info $catmodelinfo;

    /**
     * Constructor
     *
     * @param int $testitemid
     * @param int $contextid
     * @param int $catscaleid
     * @param string $component
     *
     */
    public function __construct(int $testitemid, int $contextid, int $catscaleid, string $component = 'question') {

        $this->testitemid = $testitemid;
        $this->contextid = $contextid;
        $this->catmodelinfo = new catmodel_info();
        $this->catscaleid = $catscaleid;
        $this->component = $component;
    }

    /**
     * Render the moodle charts.
     *
     * @return array
     */
    private function render_modelcards() {

        global $OUTPUT;

        $returnarray = [];

        list($modelitemparams) = $this
            ->catmodelinfo
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
            $status = get_string('status', 'core') . ': ' . get_string(sprintf('itemstatus_%d', $item->get_status()), 'local_catquiz');
        }
        $heading = html_writer::tag('h3', get_string('pluginname', sprintf('catmodel_%s', $modelname)));
        $chart = html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);

        $returnarray[] = [
            'title' => get_string('likelihood', 'local_catquiz'),
            'heading' => $heading,
            'status' => $status,
            'chart' => $chart,
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
            ],
        ];
    }

    /**
     * Renders overrides form.
     *
     * @return string
     *
     */
    private function render_overrides_form() {
        $form = new item_model_override_selector();
        $form->set_data_for_dynamic_submission($this->contextid);

        return html_writer::div($form->render(), '', ['id' => 'lcq_model_override_form']);
    }

    /**
     * Gets item status.
     *
     * @return array
     * @throws coding_exception
     */
    private function get_itemstatus(): array {
        global $DB;
        list ($sql, $params) = catquiz::get_sql_for_max_status_for_item($this->testitemid, $this->contextid, true);
        $result = $DB->get_record_sql($sql, $params);
        // If we do not have any item parameters for this item, return a status that says that.
        if (!$result) {
            return ['status' => get_string('notavailable', 'core')];
        }
        $maxstatus = $result->status;
        switch ($maxstatus) {
            case LOCAL_CATQUIZ_STATUS_EXCLUDED_MANUALLY:
                $statusstring = get_string('itemstatus_-5', 'local_catquiz');
                break;
            case LOCAL_CATQUIZ_STATUS_NOT_CALCULATED:
                $statusstring = get_string('itemstatus_0', 'local_catquiz');
                break;
            case LOCAL_CATQUIZ_STATUS_CALCULATED:
                $statusstring = get_string('itemstatus_1', 'local_catquiz');
                break;
            case LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY:
                $statusstring = get_string('itemstatus_5', 'local_catquiz');
                break;
            case LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY:
                $statusstring = get_string('itemstatus_4', 'local_catquiz');
                break;
            default:
                $statusstring = get_string('notavailable', 'core');
        }
        $modelname = get_string('pluginname', sprintf('catmodel_%s', $result->model));

        return [
            'model' => $modelname,
            'status' => $statusstring,
        ];
    }
    /**
     * Check if we display a table or a detailview of a specific item.
     */
    private function get_questiondetailview_data() {

        $questiondetailview = new questiondetailview($this->testitemid, $this->contextid, $this->catscaleid, $this->component);
        return $questiondetailview->renderdata();
    }

    /**
     * Renders button to get back to testitem overview table.
     *
     * @return array
     *
     */
    private function get_back_to_table_button() {

        $label = get_string('backtotable', 'local_catquiz');

        return [
            'label' => $label,
            'type' => 'button',
            'class' => "btn-link",
        ];
    }

    /**
     * Export for template.
     *
     * @param \renderer_base $output
     *
     * @return array
     *
     */
    public function export_for_template(\renderer_base $output): array {

        $url = new moodle_url('/local/catquiz/manage_catscales.php');

        $data = [
            'returnurl' => $url->out(),
            'models' => $this->render_modelcards(),
            'statcards' => $this->get_testitems_stats_data(),
            'contextselector' => scaleandcontexselector::render_contextselector($this->contextid),
            'overridesforms' => $this->render_overrides_form(),
            //'itemstatus' => $this->get_itemstatus(),
        ];
        return $data;
    }

    /**
     * Return the detail data of one item.
     * @return array
     */
    public function return_as_array(): array {

        $data = [
            'backtotablelink' => $this->get_back_to_table_button(),
            'questiondetailview' => $this->get_questiondetailview_data(),
            'statcards' => $this->get_testitems_stats_data(),
            // Data for tab_models Template.
            'overridesforms' => $this->render_overrides_form(),
            'itemstatus' => $this->get_itemstatus(),
            'models' => $this->render_modelcards(),
        ];
        return $data;
    }
}
