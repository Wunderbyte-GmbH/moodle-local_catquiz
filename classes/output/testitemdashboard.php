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

        $modelitemparams = catmodel_info::get_item_parameters(0, $this->testitemid);

        // Example item parameters

        $difficulty = -1.0;
        $discrimination = 1.0;

        // Example person abilities
        $abilities = [-3.0, -2.0, -1.0, 0.0, 1.0, 2.0, 3.0, 4.0, 5.0, 6.0];

        // Calculate the probability correct for each person ability
        $probabilities = [];
        foreach ($abilities as $ability) {
            $logit = $discrimination * ($ability - $difficulty);
            $probability = 1 / (1 + exp(-$logit));
            $probabilities[] = $probability;
        }

        // Output the datapoints
        $datapoints = [];
        for ($i = 0; $i < count($abilities); $i++) {
            $datapoints[] = $probabilities[$i];
        }

        foreach ($modelitemparams as $item) {

            $chart = new \core\chart_line();
            $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.

            // Create the graph for difficulty.
            $series1 = new \core\chart_series(get_string('difficulty', 'local_catquiz'), $datapoints);

            $chart->add_series($series1);
            $label = ["-3.0", "-2.0", "-1.0", "0.0", "1.0", "2.0", "3.0", "4.0", "5.0", "6.0"];

            $chart->set_labels($label);

            $body = html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);

            $returnarray[]= [
                'title' => get_string('pluginname', "catmodel_" . $item['modelname']),
                'body' => $body,
            ];
        }

        return $returnarray;
    }

    /**
     * Render the moodle charts.
     *
     * @return void
     */
    private function render_testitemstats() {

        global $DB;

        list ($sql, $params) = catquiz::get_sql_for_questions_answered($this->testitemid);
        $numberofanswers = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_usages_in_tests($this->testitemid);
        $numberofusagesintests = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_by_distinct_persons($this->testitemid);
        $numberofpersonsanswered = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_correct($this->testitemid);
        $numberofanswerscorrect = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_incorrect($this->testitemid);
        $numberofanswersincorrect = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_partlycorrect($this->testitemid);
        $numberofanswerspartlycorrect = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_average($this->testitemid);
        $averageofallanswers = $DB->get_field_sql($sql, $params);

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
        ];
    }
}
