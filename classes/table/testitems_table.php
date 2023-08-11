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
use stdClass;

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

        // try {
        // $question = question_bank::load_question($values->id);
        // } catch (Exception $e) {
        // return $values->questiontext;
        // }

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

        if (!empty($values->qtype)) {
            return get_string('pluginname', 'qtype_' . $values->qtype);
        }

        return "problem with $values->id, no qtype";
    }

    public function col_questioncontextattempts($values) {
        return $values->questioncontextattempts;
    }
    public function col_action($values) {

        global $OUTPUT;

        $url = new moodle_url('/local/catquiz/edit_testitem.php', [
            'id' => $values->id,
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
     * Override  model value with get_string.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_model($values): string {
        if (is_null($values->model)) {
            return get_string('notyetattempted', 'local_catquiz');
        }
        return get_string('pluginname', 'catmodel_' . $values->model);
    }


    /**
     * Override  model value with get_string.
     *
     * @param stdClass $values
     * @return void
     */
    public function col_status($values) {

        switch ($values->status) {
            case -5:

                break;
            case -1:

                break;
            case 0:

                break;
            case 1:

                break;
            case 5:

                break;
        }

        return get_string('pluginname', 'catmodel_' . $values->model);
    }

    /**
     * Return value for lastattempttime column.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_lastattempttime(stdClass $values) {

        if (intval($values->lastattempttime) === 0) {
            return get_string('notyetcalculated', 'local_catquiz');
        }
        return userdate($values->lastattempttime);
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
            $result[] = catscale::add_or_update_testitem_to_scale($catscaleid, $id);
        }
        $failed = array_filter($result, fn($r) => $r->isErr());

        // All items were added successfully
        if (empty($failed)) {
            return [
                'success' => 1,
                'message' => get_string('success'),
            ];
        }

        // If a single item could not be added, show a specific error message
        if (count($idarray) === 1 && count($failed) === 1) {
            return [
                'success' => 0,
                'message' => $failed[0]->getErrorMessage(),
            ];
        }

        // Multiple items could not be added.
        $numadded = count($result) - count($failed);
        $failedids = array_map(fn($f) => $f->unwrap(), $failed);
        return [
            'success' => 0,
            'message' => get_string(
                'failedtoaddmultipleitems',
                'local_catquiz',
                [
                    'numadded' => $numadded,
                    'numfailed' => count($failed),
                    'failedids' => implode(',', $failedids),
                ]
            )
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
            if (empty($idarray)) {
                $idarray = [$jsonobject->checkedids[0]];
            }
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
