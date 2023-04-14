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
 * Entities Class to display list of entity records.
 *
 * @package local_catquiz
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use moodle_exception;
use stdClass;

require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Class catcontext
 *
 * @author Georg Maißer
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catcontext {

    /**
     * $id
     *
     * @var integer
     */
    public ?int $id = null;

    /**
     * $name
     *
     * @var string
     */
    private string $name = '';

    /**
     * $description
     *
     * @var string
     */
    private string $description = '';

    /**
     * $description format
     *
     * @var integer
     */
    private int $descriptionformat = 1;

    /**
     * $json
     *
     * @var string
     */
    private string $json = '';

    /**
     * $starttimestamp
     *
     * @var integer
     */
    private int $starttimestamp = 0;

    /**
     * $endtimestamp
     *
     * @var integer
     */
    private int $endtimestamp = 0;

    /**
     * $usermodified
     *
     * @var integer
     */
    private int $usermodified = 0;

    /**
     * $timecreated
     *
     * @var integer
     */
    private int $timecreated = 0;

    /**
     * $timemodified
     *
     * @var integer
     */
    private int $timemodified = 0;

    /**
     * Catcontext constructor.
     * @param stdClass $newrecord
     */
    public function __construct(stdClass $newrecord = null) {

        global $DB;

        if (empty($this->timecreated)) {
            $this->timecreated = time();
        }

        if ($newrecord && isset($newrecord->id)) {
            // If we have a new record
            if ($oldrecord = $DB->get_record('local_catquiz_catcontext', ['id' => $newrecord->id])) {
                $this->apply_values($oldrecord);
            }
        }

        if ($newrecord) {

            $this->apply_values($newrecord);
        }
    }

    /**
     * Save or update catcontext class.
     *
     * @param stdClass $newrecord
     * @return void
     */
    public function save_or_update(stdClass $newrecord = null) {

        global $DB, $USER;

        if ($newrecord) {
            $this->apply_values($newrecord);
        }

        $this->timemodified = time();
        $this->usermodified = $USER->id;

        if (!empty($this->id)) {
            $DB->update_record('local_catquiz_catcontext', $this->return_as_class());
        } else {
            $DB->insert_record('local_catquiz_catcontext', $this->return_as_class());
        }
    }

    /**
     * Return all the values of this class as stdClass.
     *
     * @return stdClass
     */
    public function return_as_class() {

        $record = (object)[
            'name' => $this->name,
            'description' => $this->description,
            'descriptionformat' => $this->descriptionformat,
            'starttimestamp' => $this->starttimestamp,
            'endtimestamp' => $this->endtimestamp,
            'json' => $this->json,
            'usermodified' => $this->usermodified,
            'timecreated' => $this->timecreated,
            'timemodified' => $this->timemodified,
        ];

        // Only if the id is not empty, we add the id key.
        if (!empty($this->id)) {
            $record->id = $this->id;
        }

        return $record;
    }

    /**
     * Apply values from record.
     *
     * @param stdClass $record
     * @return void
     */
    public function apply_values(stdClass $record) {
        $this->id = $record->id ?? $this->id ?? null;
        $this->name = $record->name ?? $this->name ?? '';
        $this->description = $record->description ?? $this->description ?? '';
        $this->descriptionformat = $record->descriptionformat ?? $this->descriptionformat ?? 1;
        $this->starttimestamp = $record->starttimestamp ?? $this->starttimestamp ?? 0;
        $this->endtimestamp = $record->endtimestamp ?? $this->endtimestamp ?? 0;
        $this->json = $record->json ?? $this->json ?? '';
        $this->usermodified = $record->usermodified ?? $this->usermodified ?? 0;
        $this->timecreated = $record->timecreated ?? $this->timecreated ?? time();
        $this->timemodified = $record->timemodified ?? $this->timemodified ?? time();
    }

    // Add a default context that contains all test items
    public function create_default_context() {
        global $DB;
        $context = $DB->get_record_sql(
            "SELECT * FROM {local_catquiz_catcontext} WHERE json LIKE :default",
            [
                'default' => 'default',
            ]
        );
        if (!$context) {
            $context = (object) array(
                'name' => get_string('defaultcontextname', 'local_catquiz'),
                'description' => get_string('defaultcontextdescription', 'local_catquiz'),
                'descriptionformat' => 1,
                'starttimestamp' => 0,
                'endtimestamp' => 0,
                'json' => 'default',
            );
            $this->save_or_update($context);
        }
    }
}
