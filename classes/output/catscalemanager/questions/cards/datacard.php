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
class datacard {

    /**
     * @var object
     */
    private object $record;

    /**
     * Constructor
     *
     * @param object $object
     *
     */
    public function __construct(object $record) {

        $this->record = $record;
    }

    /**
     * Renders datacard of testitem.
     *
     * @param object $record
     *
     * @return array
     *
     */
    public function render_datacard_of_testitem() {

        $title = get_string('general', 'core');
        $record = $this->record;

        // We are displaying two types of status.
        // Information about model status...
        switch ($record->status) {
            case STATUS_NOT_SET:
                $modelstatus = get_string('statusnotset', 'local_catquiz');
                $statuscircleclass = STATUS_NOT_SET_COLOR_CLASS;
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

        return [
            'title' => $title,
            'body' => $body,
        ];
    }


}