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
use Exception;
use html_writer;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param;
use local_wunderbyte_table\output\table;
use local_wunderbyte_table\wunderbyte_table;
use mod_booking\booking;
use moodle_url;
use question_bank;

/**
 * Search results for managers are shown in a table (student search results use the template searchresults_student).
 */
class testitems_table extends wunderbyte_table {

    /** @var integer $catscaleid */
    private $catscaleid = 0;

    /** @var integer */
    private $contextid = 0;

    /**
     * Constructor
     * @param string $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param integer $catscaleid
     * @param integer $contextid
     */
    public function __construct(string $uniqueid, int $catscaleid = 0, int $contextid = 0) {

        $this->catscaleid = $catscaleid;
        $this->contextid = $contextid;

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

        //try {
        //    $question = question_bank::load_question($values->id);
        //} catch (Exception $e) {
        //    return $values->questiontext;
        //}

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

    public function col_model($values) {
        return $values->model;
    }

    public function col_itemdifficulty($values) {
        return $values->difficulty;
    }
    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_qtype($values) {
        return get_string('pluginname', 'qtype_' . $values->qtype);
    }

    public function col_questioncontextattempts($values) {
        return $values->questioncontextattempts;
    }

    public function col_status($values) {
        global $OUTPUT;

        // No need to display checkboxes if there are no item params for this
        // item
        if (!$values->model) {
            return;
        }

        $data['showactionbuttons'][] = [
            'label' => get_string('excluded', 'local_catquiz'), // Name of your action button.
            'id' => $values->id,
            'name' => $this->uniqueid.'-'.$values->id,
            'methodname' => 'update_item_status', // The method needs to be added to your child of wunderbyte_table class.
            'ischeckbox' => true,
            'checked' => $values->status == model_item_param::STATUS_EXCLUDE,
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'id' => $values->id,
                'componentid' => $values->qid,
                'status' => model_item_param::STATUS_EXCLUDE,
                'model' => $values->model,
                'labelcolumn' => 'username',
            ]
        ];

        $data['showactionbuttons'][] = [
            'label' => get_string('included', 'local_catquiz'), // Name of your action button.
            'id' => $values->id,
            'name' => $this->uniqueid.'-'.$values->id,
            'methodname' => 'update_item_status', // The method needs to be added to your child of wunderbyte_table class.
            'ischeckbox' => true,
            'checked' => $values->status == model_item_param::STATUS_SET_MANUALLY,
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'id' => $values->id,
                'componentid' => $values->qid,
                'status' => model_item_param::STATUS_SET_MANUALLY,
                'model' => $values->model,
                'labelcolumn' => 'username',
            ]
        ];

        // This transforms the array to make it easier to use in mustache template.
        table::transform_actionbuttons_array($data['showactionbuttons']);

        return $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', $data);


    }
    public function col_action($values) {
        if (!property_exists($values, 'qid')) {
            return;
        }

        global $OUTPUT;

        $url = new moodle_url('/local/catquiz/edit_testitem.php', [
            'id' => $values->qid,
            'catscaleid' => $this->catscaleid ?? 0,
            'component' => $values->component,
            'contextid' => $this->contextid,
        ]);

        $data['showactionbuttons'][] = [
            'label' => get_string('view', 'core'), // Name of your action button.
            'class' => 'btn btn-plain btn-smaller',
            'iclass' => 'fa fa-edit',
            'href' => $url->out(false),
            'id' => $values->id,
            'methodname' => '', // The method needs to be added to your child of wunderbyte_table class.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'id' => $values->id,
            ]
        ];


        // This transforms the array to make it easier to use in mustache template.
        table::transform_actionbuttons_array($data['showactionbuttons']);

        return $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', $data);
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
            $idarray = $jsonobject->checkedids;
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

    /**
     * Toggle Checkbox
     *
     * @param integer $id
     * @param string $data
     * @return array
     */
    public function update_item_status(int $id, string $data): array {
        $dataobject = json_decode($data);

        // If the checkbox is unchecked, set the status to "not set".
        // Otherwise, keep the selected status.
        $dataobject->status = $dataobject->state == 'false'
            ? model_item_param::STATUS_NOT_SET
            : $dataobject->status;

        try {
            model_item_param::update_in_db(
                $dataobject->id,
                $dataobject->componentid,
                $dataobject->model,
                $this->contextid,
                $dataobject
            );
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Could not update item in the DB',
            ];
        }

        return [
            'success' => 1,
        ];
    }
}
