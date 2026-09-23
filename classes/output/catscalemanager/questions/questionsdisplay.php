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
        string $componentname = 'question'
    ) {
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
        global $DB, $PAGE;
        if ($this->scale === -1) {
            return $this->get_no_table_string();
        }

        // If no context is set, get context from url.
        $catcontext = empty($this->catcontextid) ? optional_param('contextid', 0, PARAM_INT) : $this->catcontextid;
        $catscale = empty($this->scale) ? optional_param('catscale', 0, PARAM_INT) : $this->scale;

        $table = new catscalequestions_table(
            'catscale_' . $catscale . 'context' . $catcontext . ' questionstable'
        );

        // The question text is fetched on demand when a preview is
        // opened, instead of being embedded into every row.
        $PAGE->requires->js_call_amd('local_catquiz/questionpreview', 'init');
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

        [$select, $from, $where, $filter, $params]
            = catquiz::return_sql_for_catscalequestions($idsforquery, $catcontext, []);

        $table->set_filter_sql($select, $from, $where, $filter, $params);

        // The helper existed but was never called, so the table kept
        // counting its rows with the full query - the one carrying the per question
        // and per user aggregates, computed only to be thrown away by COUNT().
        //
        // The statistics are LEFT JOINs: they can neither add nor remove a row, so
        // a light query returns the same number. Passing the scale ids and the
        // context here is what lets the table build it.
        $table->set_count_context($idsforquery, (int) $catcontext);

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
            // Whether these parameters can actually be used for the model.
            'itemparamvalidity' => get_string('itemparamvalidity', 'local_catquiz'),
            'action' => get_string('action', 'local_catquiz'),
        ];
        $table->define_columns(array_keys($columnsarray));
        $table->define_headers(array_values($columnsarray));

        $table->define_fulltextsearchcolumns([
            'label',
            'catscalename',
            'categoryname',
            'questionname',
            'qtype',
            'model',
            'astatlastattempttime',
        ]);

        $sortcolumns = $columnsarray;
        unset($sortcolumns['action']);
        // The visible column is named itemparamvalidity, so that is the
        // name the header sends when it is clicked. Registering only the underlying
        // field "usable" left the visible header unsortable - the click referred to a
        // column the table did not know as sortable.
        //
        // Both are registered: the visible name for the header, and the field itself
        // for anything that sorts by it directly. The column renderer derives its
        // output from the same row, so either name yields the same order.
        $sortablecolumns = array_keys($sortcolumns);
        if (!in_array('usable', $sortablecolumns, true)) {
            $sortablecolumns[] = 'usable';
        }
        $table->define_sortablecolumns($sortablecolumns);

        $standardfilter = new standardfilter('qtype', get_string('questiontype', 'local_catquiz'));
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('model', get_string('model', 'local_catquiz'));
        $table->add_filter($standardfilter);

        // Filtering on the persisted flag, so that a maintainer can pull
        // up exactly the items whose parameters cannot be used. The labels spell the
        // states out; the stored values are 1 and 0.
        $usablefilter = new standardfilter('usable', get_string('itemparamvalidity', 'local_catquiz'));
        $usablefilter->add_options([
            '1' => get_string('itemparams_usable', 'local_catquiz'),
            '0' => get_string('itemparams_unusable', 'local_catquiz'),
        ]);
        $table->add_filter($usablefilter);

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

        [$idstring, $encodedtable, $html] = $table->lazyouthtml(10, true);
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
        global $PAGE;

        $id = $catscaleid > -1 ? $catscaleid : 0;

        $catcontextid = empty($this->catcontextid) ? optional_param('contextid', 0, PARAM_INT) : $this->catcontextid;

        $table = new catscalequestions_table('catscaleid_' . $id . 'context' . $catcontextid . '_additems');

        // The attempt count is loaded for the visible page instead of
        // being aggregated over the whole context inside the main query.
        $table->set_contextattempts_context((int) $catcontextid);

        // The question text is fetched on demand when a preview is
        // opened, instead of being embedded into every row.
        $PAGE->requires->js_call_amd('local_catquiz/questionpreview', 'init');
        $table->set_catscaleid_and_contextid($id, $catcontextid);

        [$select, $from, $where, $filter, $params]
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
            get_string('name', 'core'),
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

        $table->define_fulltextsearchcolumns(['idnumber', 'name', 'qtype']);
        // Two name spaces meet here. define_columns() names *display* columns -
        // 'questiontext' is one, rendered by col_questiontext(). This list names
        // columns the database can sort by, and they must exist in the statement this
        // table runs: return_sql_for_addcatscalequestions() selects the question name
        // as 'name'.
        //
        // The distinction matters because the two lists differ per table: the main
        // question list runs return_sql_for_catscalequestions(), which calls the same
        // value 'questionname'. Verified against each query rather than assumed -
        // 'questiontext' fails on both, 'questionname' fails on this one.
        $table->define_sortablecolumns([
            'idnumber',
            'name',
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

        [$idstring, $encodedtable, $html] = $table->lazyouthtml(10, true);
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
     * Returns the page parameters that a GET form has to carry over.
     *
     * These are read from the request rather than from $PAGE->url, because the
     * scale manager passes them as optional_param and does not register them on the
     * page URL - $PAGE->url->params() is empty here. Without carrying them,
     * submitting the search would drop the selected scale and context and silently
     * show a different list than the one the user was looking at.
     *
     * @return array
     */
    private static function current_page_params(): array {
        $definitions = [
            // Tab selection happens on the server: only the active tab is
            // built, and the tab travels in the URL. A form that does not carry it
            // therefore returns to the default tab, and the question list the user
            // was working in is simply gone from the response.
            'tab' => PARAM_ALPHA,
            'contextid' => PARAM_INT,
            'scaleid' => PARAM_INT,
            'usesubs' => PARAM_INT,
            'sdv' => PARAM_INT,
            'component' => PARAM_TEXT,
        ];

        $params = [];
        foreach ($definitions as $name => $type) {
            $value = optional_param($name, null, $type);
            if ($value === null || $value === '') {
                continue;
            }
            $params[] = ['name' => $name, 'value' => $value];
        }

        return $params;
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

            // The question texts are no longer part of the list, so
            // searching them runs as its own step with its own input field.
            $qtsearch = trim(optional_param('qtsearch', '', PARAM_TEXT));
            $data['qtsearch'] = $qtsearch;
            $data['qtsearchtoomany'] = $qtsearch !== ''
                && catscalequestions_table::resolve_questiontext_matches($qtsearch) === null;
            $data['hiddenparams'] = self::current_page_params();
        }
        return $data;
    }
}
