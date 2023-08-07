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
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

use html_writer;
use local_catquiz\catscale;
use local_wunderbyte_table\wunderbyte_table;
use context_system;
use local_catquiz\catquiz;
use local_catquiz\local\model\model_item_param;
use local_wunderbyte_table\output\table;
use moodle_url;

/**
 * Search results for managers are shown in a table (student search results use the template searchresults_student).
 */
class catscalequestions_table extends wunderbyte_table {

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

        if (empty($contextid)) {
            $contextid = catquiz::get_default_context_id();
        }
        $this->contextid = $contextid;

        parent::__construct($uniqueid);
    }

    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_userid($values) {
        return $values->id;
    }

    public function col_action($values) {
        global $OUTPUT;

        $url = new moodle_url('manage_catscales.php', [
            'id' => $values->id,
            'contextid' => $this->contextid,
            'scale' => $this->catscaleid ?? 0,
            'component' => $values->component ?? "",
        ], 'questions');

        $data['showactionbuttons'][] = [
            'class' => 'btn btn-plain btn-smaller',
            'iclass' => empty($values->testitemstatus) ? 'fa fa-eye' : 'fa fa-eye-slash',
            'href' => '#',
            'id' => $values->id,
            'methodname' => 'togglestatus', // The method needs to be added to your child of wunderbyte_table class.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'testitemstatus' => $values->testitemstatus,
                'catscaleid' => $values->catscaleid,
                'titlestring' => 'toggleactivity', // Will be shown in modal title
                'bodystring' => 'confirmactivitychange', // Will be shown in modal body
                'component' => 'local_catquiz',
                'labelcolumn' => 'name',
            ]
        ];

        $data['showactionbuttons'][] = [
                'class' => 'btn btn-plain btn-smaller',
                'iclass' => 'fa fa-cog',
                'href' => $url->out(false),
                'methodname' => '',
                'nomodal' => true,
                'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                    'id' => 'id',
                ],
            ];
            $data['showactionbuttons'][] = [
                'class' => 'btn btn-plain btn-smaller',
                'iclass' => 'fa fa-trash',
                'id' => $values->id,
                'href' => '#',
                'methodname' => 'deletequestionfromscale',
                'nomodal' => false,
                'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                    'questionid' => $values->id,
                    'id' => $values->id,
                    'catscaleid' => $values->catscaleid,
                    'titlestring' => 'deletedatatitle', // Will be shown in modal title
                    'bodystring' => 'confirmdeletion', // Will be shown in modal body in case elements are selected
                    'component' => 'local_catquiz',
                    'labelcolumn' => 'name', // Verify value of record that will be deleted.
                ]
            ];

        table::transform_actionbuttons_array($data['showactionbuttons']);
        return $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', $data);
    }

    /**
     * Return value for lastattempttime column.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_lastattempttime($values) {

        if (intval($values->lastattempttime) === 0) {
            return get_string('notyetcalculated', 'local_catquiz');
        }
        return userdate($values->lastattempttime);
    }

    /**
     * Return symbols for status column.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_status($values) {
        $color = "";

        switch ($values->status) {
            case model_item_param::STATUS_SET_MANUALLY:
                $color = 'green';
                break;
            case model_item_param::STATUS_SET_BY_STRATEGY:
                $color = 'yellow';
                break;
            case model_item_param::STATUS_NOT_CALCULATED:
                $color = 'orange';
                break;
            case model_item_param::STATUS_NOT_SET:
                $color = 'red';
                break;
        }
        return sprintf('<i class="fa fa-circle" style="color:%s;"></i>', $color);
    }

    /**
     * Return strings for column type.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_qtype($values) {
        global $OUTPUT;

        $type = $values->qtype;

        switch ($values->qtype) {
            case "multichoice":
                $type = 'MC';
                break;
            case "pmatch":
                $type = 'PMTC';
                break;
            case "match":
                $type = 'MTC';
                break;
            case "truefalse":
                $type = 'TF';
                break;
            case "ddwtos":
                $type = 'DDWT';
                break;
            case "ordering":
                $type = 'ORD';
                break;
            case "ddimageortext":
                $type = 'IOT';
                break;
            case "numerical":
                $type = 'NUM';
                break;
            default:
                break;
        }
        return $type;
    }


    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_name($values) {

        global $OUTPUT;

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
     * Function to delete selected question.
    * @param int $id
    * @param string $data
    * @return array
    */
   public function deletequestionfromscale(int $id, string $data) {

       $jsonobject = json_decode($data);

       $catscaleid = $jsonobject->catscaleid;
       $questionid = $jsonobject->questionid;

        catscale::remove_testitem_from_scale($catscaleid, $questionid);

       return [
           'success' => 1,
           'message' => get_string('success'),
       ];
   }

    /**
    * Toggle status to set item active / inactive.
    * @param int $id
    * @param string $data
    * @return array
    */
    public function togglestatus(int $id, string $data) {

        $jsonobject = json_decode($data);

        $catscaleid = $jsonobject->catscaleid;
        $status = empty($jsonobject->testitemstatus) ? TESTITEM_STATUS_INACTIVE : TESTITEM_STATUS_ACTIVE;

        catscale::add_or_update_testitem_to_scale((int)$catscaleid, $id, $status);

        return [
            'success' => 1,
            'message' => get_string('success'),
        ];
    }

}
