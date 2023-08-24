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

namespace local_catquiz\output\catscalemanager\questions\cards;

use local_catquiz\catquiz;
use renderable;

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
class datacard implements renderable {

    /**
     * @var object
     */
    private object $record;

    /** @var integer of testitemid */
    public int $testitemid = 0;

    /**
     * @var integer
     */
    private int $contextid = 0;

    /**
    * @var integer
    */
    private int $catscaleid = 0;

    /**
    * @var string
    */
    private string $component = "";


    /**
     * @param int $testitemid
     * @param int $contextid
     * @param int $catscaleid
     * @param string $component
     * @param ?object $record
     * @return void
     */
    public function __construct(
        int $testitemid,
        int $contextid,
        int $catscaleid,
        string $component,
        object $record = null) {

        $this->testitemid = $testitemid;
        $this->contextid = $contextid;
        $this->catscaleid = $catscaleid;
        $this->component = $component;

        if (empty($record)) {
            $this->record = $this->getrecord();
        } else {
            $this->record = $record;
        }
    }

    private function getrecord() {
        global $DB;
        // If no context is set, get default context from DB.
        $catcontext = empty($this->contextid) ? catquiz::get_default_context_id() : $this->contextid;

        // Get the record for the specific userid (fetched from optional param).
        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_catscalequestions([$this->catscaleid],
                                                                                                    $catcontext,
                                                                                                    [], [],
                                                                                                    $this->testitemid);
        $idcheck = "id=:userid";
        $sql = "SELECT $select FROM $from WHERE $where AND $idcheck";
        $recordinarray = $DB->get_records_sql($sql, $params, IGNORE_MISSING);

        if (empty($recordinarray)) {
            // Throw error: no record was found with id: $params['userid'].
        }
        $record = $recordinarray[$this->testitemid];
        return $record;
    }

    /**
     * Renders datacard of testitem.
     *
     *
     * @return array
     *
     */
    public function export_for_template(): array {

        $title = get_string('general', 'core');
        $record = $this->record;

        // We are displaying two types of status.
        // Information about model status...
        switch ($record->status) {
            case STATUS_EXCLUDED_MANUALLY:
                $modelstatus = get_string('statusnotset', 'local_catquiz');
                $statuscircleclass = STATUS_EXCLUDED_MANUALLY_COLOR_CLASS;
                break;
            case STATUS_NOT_CALCULATED:
                $modelstatus = get_string('statusnotcalculated', 'local_catquiz');
                $statuscircleclass = STATUS_NOT_CALCULATED_COLOR_CLASS;
                break;
            case STATUS_CALCULATED:
                $modelstatus = get_string('statussetautomatically', 'local_catquiz');
                $statuscircleclass = STATUS_CALCULATED_COLOR_CLASS;
                break;
            case STATUS_SET_MANUALLY:
                $modelstatus = get_string('statussetmanually', 'local_catquiz');
                $statuscircleclass = STATUS_SET_MANUALLY_COLOR_CLASS;
                break;
        }
        // Information about activity status...
        switch ($record->testitemstatus) {
            case TESTITEM_STATUS_ACTIVE:
                $closedeye = '';
                $testitemstatus = get_string('active', 'core');
                break;
            case TESTITEM_STATUS_INACTIVE:
                $closedeye = '-slash';
                $testitemstatus = get_string('inactive', 'core');
                break;
        }

        // Join strings for status display
        $status = " ($testitemstatus, $modelstatus)";

        // Use localization for type
        $type = get_string('pluginname', 'qtype_' . $record->qtype);

        $body['id'] = $record->id;
        $body['type'] = $type;
        $body['status'] = $status;
        $body['model'] = $record->model;
        $body['attempts'] = $record->attempts;
        $body['closedeye'] = $closedeye;
        $body['statuscircle'] = $statuscircleclass;
        $body['statustitle'] = $modelstatus;

        return [
            'title' => $title,
            'body' => $body,
            'testitemid' => $this->testitemid,
            'contextid' => $this->contextid,
            'component' => $this->component,
            'scaleid' => $this->catscaleid,
        ];
    }


}