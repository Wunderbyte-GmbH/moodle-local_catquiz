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
use local_catquiz\catquiz;
use local_catquiz\synthcat;
use local_catquiz\table\testitems_table;
use local_catquiz\table\student_stats_table;
use moodle_url;
use stdClass;
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
class catscaledashboard implements renderable, templatable {

    // For some items, the model returns -INF or INF as difficulty.
    // However, we expect it to be numeric, so we encode those
    // values as -1000 and 1000
    const MODEL_NEG_INF = -1000;
    const MODEL_POS_INF = 1000;

    /** @var integer of catscaleid */
    public int $catscaleid = 0;

    /** @var integer of catcontextid */
    private int $catcontextid = 0;

    /**
     * If set to true, we execute the CAT parameter estimation algorithm.
     *
     * @var boolean
     */
    private bool $triggercalculation;

    /** @var stdClass|bool */
    private $catscale;

    /**
     * Either returns one tree or treearray for every parentnode
     *
     * @param int $fulltree
     * @param boolean $allowedit
     * @return array
     */
    public function __construct(int $catscaleid, int $catcontextid = 0, bool $triggercalculation = false) {
        global $DB;

        $this->catscaleid = $catscaleid;
        $this->catcontextid = $catcontextid;
        $this->triggercalculation = $triggercalculation;
        $this->catscale = $DB->get_record(
            'local_catquiz_catscales',
            ['id' => $catscaleid]
        );
    }

    private function render_title() {
        global $OUTPUT;
        global $PAGE;

        $PAGE->set_heading($this->catscale->name);
        echo $OUTPUT->header();
    }
    private function render_addtestitems_table(int $catscaleid) {

        $table = new testitems_table('addtestitems', $catscaleid);

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_addcatscalequestions($catscaleid);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $table->define_columns(['idnumber', 'questiontext', 'qtype', 'categoryname', 'action']);
        $table->define_headers([
            get_string('label', 'local_catquiz'),
            get_string('questiontext', 'local_catquiz'),
            get_string('questiontype', 'local_catquiz'),
            get_string('questioncategories', 'local_catquiz'),
            get_string('action', 'local_catquiz'),
        ]);

        $table->define_filtercolumns(['categoryname' => [
            'localizedname' => get_string('questioncategories', 'local_catquiz')
        ], 'qtype' => [
            'localizedname' => get_string('questiontype', 'local_catquiz'),
            'ddimageortext' => get_string('pluginname', 'qtype_ddimageortext'),
            'essay' => get_string('pluginname', 'qtype_essay'),
            'gapselect' => get_string('pluginname', 'qtype_gapselect'),
            'multianswer' => get_string('pluginname', 'qtype_multianswer'),
            'multichoice' => get_string('pluginname', 'qtype_multichoice'),
            'numerical' => get_string('pluginname', 'qtype_numerical'),
            'shortanswer' => get_string('pluginname', 'qtype_shortanswer'),
            'truefalse' => get_string('pluginname', 'qtype_truefalse'),
        ]]);
        $table->define_fulltextsearchcolumns(['idnumber', 'name', 'questiontext', 'qtype']);
        $table->define_sortablecolumns(['idnunber', 'name', 'questiontext', 'qtype']);

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'testitemstable');

        $table->addcheckboxes = true;

        $table->actionbuttons[] = [
            'label' => get_string('addtestitem', 'local_catquiz'), // Name of your action button.
            'class' => 'btn btn-success',
            'href' => '#',
            'methodname' => 'addtestitem', // The method needs to be added to your child of wunderbyte_table class.
            'id' => -1, // This makes one Ajax call for all selected item, not one for each.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'titlestring' => 'addtestitemtitle',
                'bodystring' => 'addtestitembody',
                'submitbuttonstring' => 'addtestitemsubmit',
                'component' => 'local_catquiz',
                'labelcolumn' => 'idnumber',
                'catscaleid' => $catscaleid,
            ]
        ];

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;
        $table->showrowcountselect = true;

        return $table->outhtml(10, true);
    }

    /**
     * Function to render the testitems attributed to a given catscale.
     *
     * @param integer $catscaleid
     * @return string
     */
    private function render_testitems_table(int $catscaleid) {

        $table = new testitems_table('testitems', $this->catscaleid, $this->catcontextid);

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_catscalequestions($catscaleid, [], [], $this->catcontextid);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $table->define_columns(['idnumber', 'questiontext', 'qtype', 'categoryname', 'questioncontextattempts', 'action']);
        $table->define_headers([
            get_string('label', 'local_catquiz'),
            get_string('questiontext', 'local_catquiz'),
            get_string('questiontype', 'local_catquiz'),
            get_string('questioncategories', 'local_catquiz'),
            get_string('questioncontextattempts', 'local_catquiz'),
            get_string('action', 'local_catquiz'),
        ]);

        $table->define_filtercolumns(['categoryname' => [
            'localizedname' => get_string('questioncategories', 'local_catquiz'),
        ], 'qtype' => [
            'localizedname' => get_string('questiontype', 'local_catquiz'),
            'truefalse' => get_string('pluginname', 'qtype_truefalse'),
            'ddimageortext' => get_string('pluginname', 'qtype_ddimageortext'),
            'essay' => get_string('pluginname', 'qtype_essay'),
            'gapselect' => get_string('pluginname', 'qtype_gapselect'),
            'multianswer' => get_string('pluginname', 'qtype_multianswer'),
            'multichoice' => get_string('pluginname', 'qtype_multichoice'),
            'numerical' => get_string('pluginname', 'qtype_numerical'),
            'shortanswer' => get_string('pluginname', 'qtype_shortanswer'),
        ]]);
        $table->define_fulltextsearchcolumns(['idnumber', 'name', 'questiontext', 'qtype']);
        $table->define_sortablecolumns(['idnumber', 'name', 'questiontext', 'qtype', 'questioncontextattempts']);

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'testitemstable');

        $table->addcheckboxes = true;

        $table->actionbuttons[] = [
            'label' => get_string('removetestitem', 'local_catquiz'), // Name of your action button.
            'class' => 'btn btn-danger',
            'href' => '#',
            'methodname' => 'removetestitem', // The method needs to be added to your child of wunderbyte_table class.
            'id' => -1, // This makes one Ajax call for all selected item, not one for each.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'titlestring' => 'removetestitemtitle',
                'bodystring' => 'removetestitembody',
                'submitbuttonstring' => 'removetestitemsubmit',
                'component' => 'local_catquiz',
                'labelcolumn' => 'idnumber',
                'catscaleid' => $catscaleid,
            ]
        ];

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;
        $table->showrowcountselect = true;

        return $table->outhtml(10, true);
    }

    private function render_differentialitem() {

        global $OUTPUT;

        $chart = new \core\chart_line();
        $series1 = new \core\chart_series('Series 1 (Line)', [0.2, 0.3, 0.1, 0.4, 0.5, 0.2, 0.1, 0.3, 0.1, 0.4]);
        $series2 = new \core\chart_series('Series 2 (Line)', [0.22, 0.35, 0.09, 0.38, 0.4, 0.24, 0.18, 0.31, 0.09, 0.4]);
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.
        $chart->add_series($series1);
        $chart->add_series($series2);
        $chart->set_labels(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10']);

        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }

    private function render_statindependence() {

        global $OUTPUT;

        $chart = new \core\chart_line(); // Create a bar chart instance.
        $series1 = new \core\chart_series('Series 1 (Line)', [1.26, -0.87, 0.39, 2.31, 1.47, -0.53, 0.02, -1.14, 1.29, -0.04]);
        $series2 = new \core\chart_series('Series 2 (Line)', [0.63, -0.04, -0.42, 1.98, -1.23, 0.53, 0.87, -0.35, -0.64, 0.18]);
        $series2->set_type(\core\chart_series::TYPE_LINE); // Set the series type to line chart.
        $chart->add_series($series2);
        $chart->add_series($series1);
        $chart->set_labels(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10']);

        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }

    private function render_loglikelihood() {

        global $OUTPUT;

        $chart = new \core\chart_line();
        $series = new \core\chart_series('Series 1 (Line)', [-1.53, 0.34, 1.21, 2.64, -0.35, -0.02, -0.56, 1.28, 1.26, 0.09, -0.5]);
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.
        $chart->add_series($series);
        $chart->set_labels(["-5", "-4", "-3", "-2", "-1", "0", "1", "2", "3", "4", "5"]);

        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }
    private function render_estimatedparams($data) {

        global $OUTPUT;

        $data = array_filter($data, function($a) {
            return is_finite($a) && abs($a) != self::MODEL_POS_INF;
        });
        sort($data);

        $chart = new \core\chart_line();
        $series = new \core\chart_series('Series 1 (Line)', array_values($data));
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.
        $chart->add_series($series);
        $chart->set_labels(array_keys($data));

        return html_writer::tag('div', $OUTPUT->render($chart), ['dir' => 'ltr']);
    }
    private function render_contextselector() {
    $ajaxformdata = empty($this->catcontextid) ? [] : ['contextid' => $this->catcontextid];
    $form = new \local_catquiz\form\contextselector(null, null, 'post', '', [], true, $ajaxformdata);
    // Set the form data with the same method that is called when loaded from JS. It should correctly set the data for the supplied arguments.
    $form->set_data_for_dynamic_submission(); 
    // Render the form in a specific container, there should be nothing else in the same container.
    return html_writer::div($form->render(), '', ['id' => 'select_context_form']); 
    }

    private function render_student_stats_table(int $catscaleid, int $catcontextid) {
        $table = new student_stats_table('students', $this->catscaleid, $this->catcontextid);

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_student_stats($catcontextid);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $table->define_columns(['firstname', 'lastname', 'studentattempts',]);
        $table->define_headers([
            get_string('firstname', 'core'),
            get_string('lastname', 'core'),
            get_string('questioncontextattempts', 'local_catquiz'),
        ]);

        $table->define_fulltextsearchcolumns(['firstname', 'lastname']);
        $table->define_sortablecolumns(['firstname', 'lastname', 'studentattempts']);

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'studentstatstable');

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;
        $table->showrowcountselect = true;

        return $table->outhtml(10, true);
    }
    private function render_modelbutton($contextid) {
        return '<button class="btn btn-primary" type="button" data-contextid="1" id="model_button">Calculate</button>';
    }

    private function render_modeloutput($contextid, $calculate, string $model) {
        if (!$calculate) {
            return $this->get_estimated_parameters_from_db($contextid, $model);
        }
        global $DB;

        list ($sql, $params) = catquiz::get_sql_for_model_input($contextid);
        $data = $DB->get_records_sql($sql, $params);
        $inputdata = $this->db_to_modelinput($data);
        list ($estimated_item_difficulties, $estimated_person_abilities) = $this->run_estimation($inputdata);
        $this->save_estimated_item_parameters_to_db($contextid, $estimated_item_difficulties, $model);
        $this->save_estimated_person_parameters_to_db($contextid, $estimated_person_abilities, $model);
        return [$estimated_item_difficulties, $estimated_person_abilities];
    }

    private function save_estimated_item_parameters_to_db(int $contextid, array $estimated_parameters, string $model) {
        global $DB;
        // Get existing records for the given contextid and model.
        $existing_params_rows = $DB->get_records('local_catquiz_itemparams', ['model' => $model, 'contextid' => $contextid,]);
        $existing_params = [];
        foreach ($existing_params_rows as $r) {
            $existing_params[$r->componentid] = $r;
        };

        $records = array_map(
            function ($componentid, $param) use ($contextid, $model) {
                if (!is_finite($param)) {
                    $param = $param < 0 ? self::MODEL_NEG_INF : self::MODEL_POS_INF;
                }
                return [
                    'componentid' => $componentid,
                    'componentname' => 'question',
                    'difficulty' => $param,
                    'model' => $model,
                    'contextid' => $contextid,
                ];
            },
            array_keys($estimated_parameters),
            array_values($estimated_parameters)
        );

        $updated_records = [];
        $new_records = [];
        $now = time();
        foreach ($records as $record) {
            $is_existing_param = array_key_exists($record['componentid'], $existing_params);
            // If record already exists, update it. Otherwise, insert a new record to the DB
            if ($is_existing_param) {
                $record['id'] = $existing_params[$record['componentid']]->id;
                $record['timemodified'] = $now;
                $updated_records[] = $record;
            } else {
                $record['timecreated'] = $now;
                $record['timemodified'] = $now;
                $new_records[] = $record;
            }
        }

        if (!empty($new_records)) {
            $DB->insert_records('local_catquiz_itemparams', $new_records);
            
        }
        // Maybe change to bulk update later
        foreach ($updated_records as $r) {
            $DB->update_record('local_catquiz_itemparams', $r, true);
        }
    }

    private function save_estimated_person_parameters_to_db(int $contextid, array $estimated_parameters, string $model) {
        global $DB;
        // Get existing records for the given contextid and model.
        $existing_params_rows = $DB->get_records('local_catquiz_personparams', ['model' => $model, 'contextid' => $contextid,]);
        $existing_params = [];
        foreach ($existing_params_rows as $r) {
            $existing_params[$r->userid] = $r;
        };

        $records = array_map(
            function ($userid, $param) use ($contextid, $model) {
                if (!is_finite($param)) {
                    $param = $param < 0 ? self::MODEL_NEG_INF : self::MODEL_POS_INF;
                }
                return [
                    'userid' => $userid,
                    'ability' => $param,
                    'model' => $model,
                    'contextid' => $contextid,
                ];
            },
            array_keys($estimated_parameters),
            array_values($estimated_parameters)
        );

        $updated_records = [];
        $new_records = [];
        $now = time();
        foreach ($records as $record) {
            $is_existing_param = array_key_exists($record['userid'], $existing_params);
            // If record already exists, update it. Otherwise, insert a new record to the DB
            if ($is_existing_param) {
                $record['id'] = $existing_params[$record['userid']]->id;
                $record['timemodified'] = $now;
                $updated_records[] = $record;
            } else {
                $record['timecreated'] = $now;
                $record['timemodified'] = $now;
                $new_records[] = $record;
            }
        }

        if (!empty($new_records)) {
            $DB->insert_records('local_catquiz_personparams', $new_records);
        }
        // Maybe change to bulk update later
        foreach ($updated_records as $r) {
            $DB->update_record('local_catquiz_personparams', $r, true);
        }
    }

    private function get_estimated_parameters_from_db(int $contextid, string $model) {
        global $DB;

        $item_rows = $DB->get_records('local_catquiz_itemparams',
            [
                'contextid' => $contextid,
                'model' => $model,
            ],
            'difficulty ASC'
        );
        $items = [];
        foreach ($item_rows as $r) {
            $items[$r->componentid] = $r->difficulty;
        }

        $person_rows = $DB->get_records('local_catquiz_personparams',
            [
                'contextid' => $contextid,
                'model' => $model,
            ],
            'ability ASC'
        );
        $persons = [];
        foreach ($person_rows as $r) {
            $persons[$r->userid] = $r->ability;
        }

        return [$items, $persons];
    }

    private function run_estimation($inputdata) {
        $demo_persons = array_map(
            function($id) {
                return ['id' => $id, 'ability' => 0];
            },
            array_keys($inputdata)
        );

        $item_list = \local_catquiz\helpercat::get_item_list($inputdata);
        $estimated_item_difficulty = \local_catquiz\catcalc::estimate_initial_item_difficulties($item_list);

        $estimated_person_abilities = [];
        foreach($demo_persons as $person){

            $person_id = $person['id'];
            $item_difficulties = $estimated_item_difficulty; // replace by something better
            $person_response = \local_catquiz\helpercat::get_person_response($inputdata, $person_id);
            $person_ability = \local_catquiz\catcalc::estimate_person_ability($person_response, $item_difficulties);

            $estimated_person_abilities[$person_id] = $person_ability;
        }


        $demo_item_responses = \local_catquiz\helpercat::get_item_response($inputdata, $estimated_person_abilities);

        $estimated_item_difficulty_next = [];

        foreach($demo_item_responses as $item_id => $item_response){
            $item_difficulty = \local_catquiz\catcalc::estimate_item_difficulty($item_response);

            $estimated_item_difficulty_next[$item_id] = $item_difficulty;
        }

        return [$estimated_item_difficulty, $estimated_person_abilities];
    }

    /**
     * Returns data in the following format
     * 
     * "1" => Array( //userid
     *     "comp1" => Array( // component
     *         "1" => Array( //questionid
     *             "fraction" => 0,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955326
     *         ),
     *         "2" => Array(
     *             "fraction" => 0,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955332
     *         ),
     *         "3" => Array(
     *             "fraction" => 1,
     *             "max_fraction" => 1,
     *             "min_fraction" => 0,
     *             "qtype" => "truefalse",
     *             "timestamp" => 1646955338
     */
    private function db_to_modelinput($data) {
        $modelinput = [];
        foreach ($data as $row) {
            $entry = [
                'fraction' => $row->fraction,
                'max_fraction' =>  $row->maxfraction,
                'min_fraction' => $row->minfraction,
                'qtype' => $row->qtype,
                'timestamp' => $row->timecreated,
            ];

            if (!array_key_exists($row->userid, $modelinput)) {
                $modelinput[$row->userid] = ["component" => []];
            }

            $modelinput[$row->userid]['component'][$row->questionid] = $entry;
        }
        return $modelinput;
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        $url = new moodle_url('/local/catquiz/manage_catscales.php');
        $testenvironmentdashboard = new testenvironmentdashboard();
        list ($estimated_item_difficulties, $estimated_person_abilities) = $this->render_modeloutput(
            $this->catcontextid,
            $this->triggercalculation,
            'raschbirnbauma'
        );

        return [
            'title' => $this->render_title(),
            'returnurl' => $url->out(),
            'testitemstable' => $this->render_testitems_table($this->catscaleid),
            'addtestitemstable' => $this->render_addtestitems_table($this->catscaleid),
            'statindependence' => $this->render_statindependence(),
            'loglikelihood' => $this->render_loglikelihood(),
            'itemdifficulties' => $this->render_estimatedparams($estimated_item_difficulties),
            'personabilities' => $this->render_estimatedparams($estimated_person_abilities),
            'differentialitem' => $this->render_differentialitem(),
            'contextselector' => $this->render_contextselector(),
            'table' => $testenvironmentdashboard->testenvironmenttable($this->catscaleid),
            'studentstable' => $this->render_student_stats_table($this->catscaleid, $this->catcontextid),
            'modelbutton' => $this->render_modelbutton($this->catcontextid),
        ];
    }
}
