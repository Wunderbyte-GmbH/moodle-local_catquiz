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

/**
 * Class representing a sync event (syncing a local CAT scale with a central instance)
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local;

use local_catquiz\catquiz;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * This class holds a single sync event
 */
class sync_event {
    /**
     * The context ID where this sync event occurs
     * @var int
     */
    private int $contextid;

    /**
     * The ID of the CAT scale being synced
     * @var int
     */
    private int $catscaleid;

    /**
     * Number of parameters fetched during sync
     * @var int
     */
    private int $numfetchedparams;

    /**
     * The ID of the user performing the sync
     * @var int
     */
    private int $userid;

    /**
     * The catquiz repository instance
     * @var catquiz
     */
    private catquiz $repo;

    /**
     * Creates a new sync event
     *
     * @param int $contextid The context ID where this sync event occurs
     * @param int $catscaleid The ID of the CAT scale being synced
     * @param int $numfetchedparams Number of parameters fetched during sync
     * @param catquiz|null $repo Optional catquiz repository instance
     */
    public function __construct(
        int $contextid,
        int $catscaleid,
        int $numfetchedparams,
        ?catquiz $repo = null
    ) {
        global $USER;
        $this->userid = $USER->id;
        $this->contextid = $contextid;
        $this->catscaleid = $catscaleid;
        $this->numfetchedparams = $numfetchedparams;
        $this->repo = new catquiz();
        if ($repo) {
            $this->repo = $repo;
        }
    }

    /**
     * Saves this sync event to the database
     *
     * @return void
     */
    public function save() {
        $this->repo->save_sync_event($this->as_record());
    }

    /**
     * Converts this sync event to a database record
     *
     * @return stdClass The sync event as a database record
     */
    private function as_record(): stdClass {
        return (object) [
            'contextid' => $this->contextid,
            'catscaleid' => $this->catscaleid,
            'num_fetched_params' => $this->numfetchedparams,
            'userid' => $this->userid,
        ];
    }
}
