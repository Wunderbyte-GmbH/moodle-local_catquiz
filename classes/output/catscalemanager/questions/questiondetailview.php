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

use local_catquiz\catquiz;
use local_catquiz\output\catscalemanager\questions\cards\datacard;
use local_catquiz\output\catscalemanager\questions\cards\questionpreview;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->libdir . '/questionlib.php');


/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer, Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questiondetailview {

    /** @var int of testitemid */
    public int $testitemid = 0;

    /**
     * @var int
     */
    private int $contextid = 0;

    /**
     * @var int
     */
    private int $catscaleid = 0;

    /**
     * @var string
     */
    private string $component = "";

    /**
     * Constructor.
     *
     * @param int $testitemid
     * @param int $contextid
     * @param int $catscaleid
     * @param string $component
     *
     */
    public function __construct(
        int $testitemid,
        int $contextid,
        int $catscaleid,
        string $component = 'question') {
        $this->testitemid = $testitemid;
        $this->contextid = $contextid;
        $this->catscaleid = $catscaleid;
        $this->component = $component;
    }


    /**
     * Check if we display a table or a detailview of a specific item.
     */
    public function renderdata() {
        global $DB;
        if (empty($this->testitemid)) {
            return;
        }
        // If no context is set, get default context from DB.
        $catcontext = empty($this->contextid) ? catquiz::get_default_context_id() : $this->contextid;

        // Get the record for the specific userid (fetched from optional param).
        list($select, $from, $where, , $params) = catquiz::return_sql_for_catscalequestions([$this->catscaleid],
                                                                                                    $catcontext,
                                                                                                    [],
                                                                                                    $this->testitemid);
        $idcheck = "id=:userid";
        $sql = "SELECT $select FROM $from WHERE $where AND $idcheck";
        $recordinarray = $DB->get_records_sql($sql, $params, IGNORE_MISSING);

        if (empty($recordinarray)) {
            return [];
        }
        $record = $recordinarray[$this->testitemid];

        // Output for testitem details card.
        $datacardoutput = $this->render_datacard_of_testitem($record); // Return array.
        $qpreview = $this->render_questionpreview($record); // Return array.

        return [
            'datacard' => $datacardoutput,
            'questionpreview' => $qpreview,
        ];
    }

    /**
     * Renders datacard of testitem.
     *
     * @param object $record
     *
     * @return array
     *
     */
    private function render_datacard_of_testitem(object $record) {

        $datacard = new datacard(
            $this->testitemid,
            $this->contextid,
            $this->catscaleid,
            $this->component,
            $record);
        return $datacard->export_for_template();
    }

    /**
     * Renders preview of testitem (question).
     *
     * @param object $record
     *
     * @return array
     *
     */
    private function render_questionpreview(object $record) {
        $questionpreview = new questionpreview($record);
        return $questionpreview->render_questionpreview();
    }
}
