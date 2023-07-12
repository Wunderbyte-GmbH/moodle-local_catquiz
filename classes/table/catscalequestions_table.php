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

use html_writer;
use local_catquiz\catscale;
use local_wunderbyte_table\wunderbyte_table;
use context_system;
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

        $url = new moodle_url('edit_testitem.php', [
            'id' => $values->id,
            'catscaleid' => $this->catscaleid ?? 0,
            'component' => $values->component, // Set fallback to "question"?
            'contextid' => $this->contextid,
        ]);

        $data['showactionbuttons'][] = [
            //'label' => get_string('view', 'core'), // Name of your action button.
            'class' => 'btn btn-plain btn-smaller',
            'iclass' => 'fa fa-eye',
            'href' => $url->out(false),
            'id' => $values->id,
            'methodname' => '', // The method needs to be added to your child of wunderbyte_table class.
            'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                'key' => 'id',
                'value' => $values->id,
            ]
            ];
        $data['showactionbuttons'][] = [
                //'label' => get_string('delete', 'core'), // 'NoModal, SingleCall, NoSelection'
                'class' => 'btn btn-plain btn-smaller',
                'iclass' => 'fa fa-cog',
                'href' => '#',
                'id' => -1, // This forces single call execution.
                //'formclass' => '', // To open dynamic form, instead of just confirmation modal.
                'methodname' => 'edititem',
                'nomodal' => true,
                'selectionmandatory' => false,
                'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                    'id' => 'id',
                ],
            ];
            $data['showactionbuttons'][] = [
                //'label' => get_string('delete', 'core'), // 'NoModal, SingleCall, NoSelection'
                'class' => 'btn btn-plain btn-smaller',
                'iclass' => 'fa fa-trash',
                'href' => '#',
                'id' => -1, // This forces single call execution.
                //'formclass' => '', // To open dynamic form, instead of just confirmation modal.
                'methodname' => 'deleteitem',
                'nomodal' => true,
                'selectionmandatory' => false,
                'data' => [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                    'id' => 'id',
                ],
            ];

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
    public function col_maxstatus($values) {
        global $OUTPUT;

        $color = "";

        switch ($values->maxstatus) {
            case 5:
                $color = 'green';
                break;
            case 1:
                $color = 'orange';
                break;
            case 0:
                $color = 'red';
                break;
            case -5:
                // This applies when status is not set.
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
}
