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

/**
 * Search results for managers are shown in a table (student search results use the template searchresults_student).
 */
class testitems_table extends wunderbyte_table {

    /** @var context_module $buyforuser */
    private $context = null;

    /**
     * Constructor
     * @param string $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param booking $booking the booking instance
     */
    public function __construct(string $uniqueid) {
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

        return $OUTPUT->render_from_template('local_catquiz/modal_questionpreview', $data);
    }

    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_qtype($values) {
        return get_string('pluginname', 'qtype_' . $values->qtype);
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
