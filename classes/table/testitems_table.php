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

namespace local_catquiz\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../../lib.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

use context_module;
use context_system;
use html_writer;
use local_catquiz\catscale;
use local_wunderbyte_table\wunderbyte_table;
use mod_booking\booking;
use moodle_url;
use question_bank;

/**
 * Search results for managers are shown in a table (student search results use the template searchresults_student).
 */
class testitems_table extends wunderbyte_table {

    /** @var context_module $buyforuser */
    private $context = null;

    /** @var integer $catscaleid */
    private $catscaleid = 0;

    /**
     * Constructor
     * @param string $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param integer $catscaleid
     */
    public function __construct(string $uniqueid, int $catscaleid = 0) {

        $this->catscaleid = $catscaleid;

        parent::__construct($uniqueid);

    }

    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_idnumber($values) {
        return html_writer::tag('span', $values->idnumber, ['class' => 'badge badge-primary']);
    }

    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_questiontext($values) {

        global $OUTPUT;

        $question = question_bank::load_question($values->id);

        $context = context_system::instance();

        $questiontext = question_rewrite_question_urls(
            $values->questiontext,
            'pluginfile.php',
            $context->id,
            'question',
            'questiontext',
            [],
            $values->id);

        $fulltext = format_text($questiontext);
        $questiontext = strip_tags($fulltext);
        $shorttext = substr($questiontext, 0, 30);
        $shorttext .= strlen($shorttext) < strlen($questiontext) ? '...' : '';

        $data = [
            'shorttext' => $shorttext,
            'fulltext' => $fulltext,
            'id' => $values->id,
        ];

        return $OUTPUT->render_from_template('local_catquiz/modals/modal_questionpreview', $data);
    }

    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_qtype($values) {
        return get_string('pluginname', 'qtype_' . $values->qtype);
    }

    public function col_action($values) {

        global $OUTPUT;

        $url = new moodle_url('/local/catquiz/edit_testitem.php', [
            'id' => $values->id,
            'catscaleid' => $this->catscaleid ?? 0,
            'component' => $values->component,
        ]);

        $data['showactionbuttons'][] = [
            'label' => get_string('view', 'core'), // Name of your action button.
            'class' => 'btn btn-plain btn-smaller',
            'iclass' => 'fa fa-edit',
            'href' => $url->out(false),
            'id' => $values->id,
            'methodname' => '', // The method needs to be added to your child of wunderbyte_table class.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'key' => 'id',
                'value' => $values->id,
            ]
        ];

        return $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', $data);;
    }

    /**
     * Function to handle the action buttons.
     * @param integer $testitemid
     * @param string $data
     * @return array
     */
    public function addtestitem(int $testitemid, string $data) {

        $jsonobject = json_decode($data);

        $catscaleid = $jsonobject->catscaleid;

        if ($testitemid == -1) {
            $idarray = explode(',', $jsonobject->checkedids);
        } else if ($testitemid > 0) {
            $idarray = [$testitemid];
        }

        foreach ($idarray as $id) {
            catscale::add_or_update_testitem_to_scale($catscaleid, $id);
        }

        return [
            'success' => 1,
            'message' => get_string('success'),
        ];
    }

    /**
     * Function to handle the action buttons.
     * @param integer $testitemid
     * @param string $data
     * @return array
     */
    public function removetestitem(int $testitemid, string $data) {

        $jsonobject = json_decode($data);

        $catscaleid = $jsonobject->catscaleid;

        if ($testitemid == -1) {
            $idarray = explode(',', $jsonobject->checkedids);
        } else if ($testitemid > 0) {
            $idarray = [$testitemid];
        }

        foreach ($idarray as $id) {
            catscale::remove_testitem_from_scale($catscaleid, $id);
        }

        return [
            'success' => 1,
            'message' => get_string('success'),
        ];
    }
}
