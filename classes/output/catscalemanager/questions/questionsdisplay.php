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

namespace local_catquiz\output\catscalemanager\questions;

use html_writer;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\output\catscalemanager\scaleandcontexselector;
use local_catquiz\table\catscalequestions_table;
use local_wunderbyte_table\filters\types\standardfilter;
use moodle_url;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionsdisplay {
    /**
     * @var int
     */
    private int $catcontextid = 0; // Selected context.

    /**
     * @var int
     */
    private int $scale = 0; // The selected scale.

    /**
     * @var int
     */
    private int $usesubs = 1; // If subscales should be integrated in question display, value is 1.

    /**
     * @var int
     */
    private int $numberofrecords = 0; // Records found in table query.

    /**
     * @var int
     */
    private ?int $testitemid = null; // ID of testitem.

    /**
     * @var string
     */
    private string $componentname = 'question'; // Componentname of the testitem.

    /**
     * Constructor.
     *
     * @param int $testitemid
     * @param int $contextid
     * @param int $catscaleid
     * @param int $usesubs
     * @param string $componentname
     *
     */
    public function __construct(
            int $testitemid,
            int $contextid,
            int $catscaleid = 0,
            int $usesubs = 1,
            string $componentname = 'question') {
        $this->catcontextid = $contextid;
        $this->scale = $catscaleid;
        $this->usesubs = $usesubs;
        $this->testitemid = $testitemid; // ID of record to be displayed in detail instead of table.
        $this->componentname = $componentname;

    }

    /**
     * Render questions table.
     * @return ?string
     */
    public function renderquestionstable() {
        global $DB;
        if ($this->scale === -1) {
            return $this->get_no_table_string();
        }

        // If no context is set, get context from url.
        $catcontext = empty($this->catcontextid) ? optional_param('contextid', 0, PARAM_INT) : $this->catcontextid;
        $catscale = empty($this->scale) ? optional_param('catscale', 0, PARAM_INT) : $this->scale;

        $table = new catscalequestions_table(
            'catscale_' . $catscale . 'context' . $catcontext . ' questionstable');
        $table->set_catscaleid_and_contextid($catscale, $catcontext);

        // If we integrate questions from subscales, we add different ids.
        if ($this->usesubs > 0) {
            $subscaleids = catscale::get_subscale_ids(
                $this->scale
            );
            $idsforquery = array_map('intval', $subscaleids);
            array_push($idsforquery, $this->scale);
        } else {
            $idsforquery = [$this->scale];
        }

        list($select, $from, $where, $filter, $params)
            = catquiz::return_sql_for_catscalequestions($idsforquery, $catcontext, []);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $columnsarray = [
            'status' => get_string('status', 'core'),
            'qtype' => get_string('type', 'local_catquiz'),
            'name' => get_string('name', 'core'),
            'model' => get_string('model', 'local_catquiz'),
            'attempts' => get_string('attempts', 'local_catquiz'),
            'astatlastattempttime' => get_string('lastattempttime', 'local_catquiz'),
            'difficulty' => get_string('difficulty', 'local_catquiz'),
            'discrimination' => get_string('discrimination', 'local_catquiz'),
            'guessing' => get_string('guessing', 'local_catquiz'),
            'action' => get_string('action', 'local_catquiz'),
        ];
        $table->define_columns(array_keys($columnsarray));
        $table->define_headers(array_values($columnsarray));

        $table->define_fulltextsearchcolumns([
            'label',
            'catscalename',
            'categoryname',
            'questionname',
            'questiontext',
            'qtype',
            'model',
            'astatlastattempttime',
        ]);

        $sortcolumns = $columnsarray;
        unset($sortcolumns['action']);
        $table->define_sortablecolumns(array_keys($sortcolumns));

        $standardfilter = new standardfilter('qtype', get_string('questiontype', 'local_catquiz'));
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('model', get_string('model', 'local_catquiz'));
        $table->add_filter($standardfilter);

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
                'noselectionbodystring' => 'selectitem',
                'component' => 'local_catquiz',
                'labelcolumn' => 'idnumber',
                'catscaleid' => $this->scale,
            ],
        ];

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'testitemstable');

        $table->pageable(true);

        $table->showcountlabel = true;
        $table->showdownloadbutton = false;
        $table->showreloadbutton = false;
        $table->showrowcountselect = true;

        $table->filteronloadinactive = true;

        $table->showdownloadbutton = true;
        $table->define_baseurl(new moodle_url('/local/catquiz/downloads/download_testitems.php'));

        list($idstring, $encodedtable, $html) = $table->lazyouthtml(10, true);
        return $html;

    }
    /**
     * Render addtestitems table.
     *
     * @param int $catscaleid
     *
     * @return string
     *
     */
    private function render_addtestitems_table(int $catscaleid) {
        $id = $catscaleid > -1 ? $catscaleid : 0;

        $catcontextid = empty($this->catcontextid) ? optional_param('contextid', 0, PARAM_INT) : $this->catcontextid;

        $table = new catscalequestions_table('catscaleid_' . $id . 'context' . $catcontextid . '_additems');
        $table->set_catscaleid_and_contextid($id, $catcontextid);

        list($select, $from, $where, $filter, $params)
            = catquiz::return_sql_for_addcatscalequestions($catscaleid, $catcontextid);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $table->define_columns([
            'idnumber',
            'questiontext',
            'qtype',
            'categoryname',
            'questioncontextattempts',
            'view',
        ]);
        $table->define_headers([
            get_string('label', 'local_catquiz'),
            get_string('questiontext', 'local_catquiz'),
            get_string('questiontype', 'local_catquiz'),
            get_string('questioncategories', 'local_catquiz'),
            get_string('questioncontextattempts', 'local_catquiz'),
            get_string('view', 'core'),
        ]);

        $standardfilter = new standardfilter('categoryname', get_string('questioncategories', 'local_catquiz'));
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('qtype', get_string('questiontype', 'local_catquiz'));
        $standardfilter->add_options([
            'ddimageortext' => get_string('pluginname', 'qtype_ddimageortext'),
            'essay' => get_string('pluginname', 'qtype_essay'),
            'gapselect' => get_string('pluginname', 'qtype_gapselect'),
            'multianswer' => get_string('pluginname', 'qtype_multianswer'),
            'multichoice' => get_string('pluginname', 'qtype_multichoice'),
            'numerical' => get_string('pluginname', 'qtype_numerical'),
            'shortanswer' => get_string('pluginname', 'qtype_shortanswer'),
            'truefalse' => get_string('pluginname', 'qtype_truefalse'),
        ]);
        $table->add_filter($standardfilter);

        $table->define_fulltextsearchcolumns(['idnumber', 'name', 'questiontext', 'qtype']);
        $table->define_sortablecolumns([
            'idnunber',
            'name',
            'questiontext',
            'qtype',
            'questioncontextattempts',
        ]);

        $table->tabletemplate = 'local_wunderbyte_table/twtable_list';
        $table->define_cache('local_catquiz', 'testitemstable');

        $table->addcheckboxes = true;

        $table->actionbuttons[] = [
            'label' => get_string('addtestitem', 'local_catquiz'), // Name of your action button.
            'class' => 'btn btn-success',
            'href' => '#',
            'formname' => 'local_catquiz\\form\\add_testitem_to_scale',
            'id' => -1, // This makes one Ajax call for all selected item, not one for each.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'catscaleid' => $catscaleid,
                'title' => get_string('addtestitemtitle', 'local_catquiz'),
            ],
        ];

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;
        $table->showrowcountselect = true;

        $table->filteronloadinactive = true;

        list($idstring, $encodedtable, $html) = $table->lazyouthtml(10, true);
        return $html;
    }


    /**
     * When there is no table to display, return the right message.
     * @return string
     */
    private function get_no_table_string() {
        if ($this->scale == 0) {
            return get_string('noscaleselected', 'local_catquiz');
        } else if ($this->numberofrecords == 0) {
            return get_string('norecordsfound', 'local_catquiz');
        } else {
            return "";
        }
    }
    /**
     * Check if we display a table or a detailview of a specific item.
     * @return array
     */
    private function check_tabledisplay() {

        if ($this->scale === -1) {
            return [
                'output' => $this->get_no_table_string(),
                'notable' => true,
            ];
        } else {
            return [
                'output' => $this->renderquestionstable(),
                'notable' => false,
            ];
        }
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_data_array(): array {
        // Scale- and Contextselector will always be displayed.
        $data = [
            'contextselector' => scaleandcontexselector::render_contextselector($this->catcontextid),
            'scaleselectors' =>
                empty(scaleandcontexselector::render_scaleselectors($this->scale))
                ? "" : scaleandcontexselector::render_scaleselectors($this->scale),
            'checkbox' => scaleandcontexselector::render_subscale_checkbox($this->usesubs),
        ];

        // Check if it's a detailview and return tables only if not.
        if (!empty($this->testitemid)) {
            $data['table'] = "";
            $data['notable'] = true;
            $data['modaltable'] = "";
        } else {
            $data['table'] = $this->check_tabledisplay()['output'];
            $data['notable'] = $this->check_tabledisplay()['notable'];
            $data['modaltable'] = $this->render_addtestitems_table($this->scale);
        }
        return $data;
    }
}
