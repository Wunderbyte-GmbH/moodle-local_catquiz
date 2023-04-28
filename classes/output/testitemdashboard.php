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

use context_system;
use html_writer;
use local_catquiz\catmodel_info;
use local_catquiz\catquiz;
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

    /**
     * Either returns one tree or treearray for every parentnode
     *
     * @param int $fulltree
     * @param boolean $allowedit
     * @param int $contextid
     * @return array
     */
    public function __construct(int $testitemid, int $contextid) {

        $this->testitemid = $testitemid;
        $this->contextid = $contextid;
    }

    /**
     * Render the moodle charts.
     *
     * @return void
     */
    private function render_modelcards() {

        global $OUTPUT;

        $returnarray = [];

        $catmodel_info = new catmodel_info();
        list($modelitemparams, $modelpersonparams) = $catmodel_info->get_context_parameters($this->contextid);

        foreach ($modelitemparams as $modelname => $itemparamlist) {

            $item = $itemparamlist[$this->testitemid];
            if (! $item) {
                continue;
            }

            $discrimination = 1.0;

            // Calculate the probability correct for each person ability
            $probabilities = [];
            foreach ($modelpersonparams[$modelname] as $p) {
                $logit = $discrimination * ($p->get_ability() - $item->get_difficulty());
                $probability = 1 / (1 + exp(-$logit));
                $probabilities[$p->get_id()] = $probability;
            }

            $chart = new \core\chart_line();
            $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.

            // Create the graph for difficulty.
            $series1 = new \core\chart_series(get_string('difficulty', 'local_catquiz'), array_values($probabilities));
            $labels = array_keys($probabilities);
            $chart->add_series($series1);
            $chart->set_labels($labels);

            $body = html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);

            $returnarray[]= [
                'title' => get_string('pluginname', "catmodel_" . $modelname),
                'body' => $body,
                'difficulty' => $item->get_difficulty(),
            ];
        }

        return $returnarray;
    }

    /**
     * Render the moodle charts.
     *
     * @return array
     */
    private function render_testitemstats() {

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
        $averageofallanswers = $DB->get_field_sql($sql, $params) ?? get_string('notavailable', 'core');

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
    private function render_contextselector() {
        $form = new \local_catquiz\form\contextselector(null, null, 'post', '', [], true, ['contextid' => $this->contextid]);
        // Set the form data with the same method that is called when loaded from JS. It should correctly set the data for the supplied arguments.
        $form->set_data_for_dynamic_submission();
        // Render the form in a specific container, there should be nothing else in the same container.
        return html_writer::div($form->render(), '', ['id' => 'select_context_form']);
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        $url = new moodle_url('/local/catquiz/manage_catscales.php');

        return [
            'returnurl' => $url->out(),
            'models' => $this->render_modelcards(),
            'statcards' => $this->render_testitemstats(),
            'contextselector' => $this->render_contextselector(),
        ];
    }
}
