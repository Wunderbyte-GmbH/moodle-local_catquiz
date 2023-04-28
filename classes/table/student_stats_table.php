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
use moodle_url;

/**
 * Search results for managers are shown in a table (student search results use the template searchresults_student).
 */
class student_stats_table extends wunderbyte_table {

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

    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_firstname($values) {
        return $values->firstname;
    }

    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_lastname($values) {
        return $values->lastname;
    }

    /**
     * Overrides the output for this column.
     * @param object $values
     */
    public function col_studentattempts($values) {
        return $values->studentattempts;
    }

    public function col_personabilities($values) {
        return $values->max;
    }

    public function col_action($values) {
        global $OUTPUT;

        $url = new moodle_url('/local/catquiz/show_student.php', [
            'id' => $values->id,
            'catscaleid' => $this->catscaleid ?? 0,
            'contextid' => $this->contextid,
        ]);

        $data['showactionbuttons'][] = [
            'label' => get_string('view', 'core'), // Name of your action button.
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

        return $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', $data);;
    }
}
