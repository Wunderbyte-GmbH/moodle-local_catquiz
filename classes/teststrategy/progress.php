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
 * Progress stores the progress of a catquiz attempt that is not yet finished.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy;

use cache;
use JsonSerializable;

/**
 * Stores the progress of a catquiz attempt that is not yet finished.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress implements JsonSerializable {
    /**
     * @var ?int $id The ID in the database.
     */
    private ?int $id;

    /**
     * @var int $userid The ID of the user.
     */
    private int $userid;

    /**
     * @var string $component The name of the component. E.g. mod_adaptivequiz.
     */
    private string $component;

    /**
     * @var int $attemptid ID to identify the quiz attempt.
     */
    private int $attemptid;

    /**
     * @var array $playedquestions The questions that were already displayed to the user.
     */
    private array $playedquestions;

    /**
     * Returns a new progress instance.
     *
     * If we already have data in the cache or DB, the instance is populated with those data.
     *
     * @param int $attemptid
     * @param ?string $component
     * @return progress
     */
    public static function load(int $attemptid, ?string $component = null): self {
        $attemptcache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cachekey = self::get_cache_key($attemptid);
        $cachedata = $attemptcache->get($cachekey);
        $cacheisfresh = false;
        if ($cacheisfresh) {
            $instance = self::populate_from_cache($cachedata);
            return $instance;
        }

        global $DB;
        $record = $DB->get_record(
            'local_catquiz_progress',
            ['attemptid' => $attemptid],
            '*'
        );
        if ($record) {
            $instance = self::populate_from_db($record);
            return $instance;
        }

        // If we are here, this must be a new attempt.
        return self::populate_new($attemptid, $component);
    }

    /**
     * Populates the data from a cache object.
     *
     * @param \stdClass $cacheobject
     * @return self
     */
    private static function populate_from_cache(\stdClass $cacheobject): self {
        $instance = new self();
        $instance->id = $cacheobject->id;
        $instance->userid = $cacheobject->userid;
        $instance->component = $cacheobject->component;
        $instance->attemptid = $cacheobject->attemptid;
        $data = json_decode($cacheobject->json);
        $instance->playedquestions = (array) $data->playedquestions;
        return $instance;
    }

    /**
     * Populates the data from a database record.
     *
     * @param \stdClass $record A database record.
     * @return self
     */
    private static function populate_from_db(\stdClass $record): self {
        $instance = new self();
        $instance->id = $record->id;
        $instance->userid = $record->userid;
        $instance->component = $record->component;
        $instance->attemptid = $record->attemptid;
        $data = json_decode($record->json);
        $instance->playedquestions = (array) $data->playedquestions;
        return $instance;
    }

    /**
     * This sets default data for a new instance.
     *
     * @param int $attemptid
     * @param ?string $component
     * @return self
     */
    private static function populate_new(int $attemptid, ?string $component): self {
        if (! $component) {
            throw new \Exception(
                "Creating a new quiz progress failed due to missing component name"
            );
        }

        global $USER;
        $instance = new self();
        $instance->id = null;
        $instance->userid = $USER->id;
        $instance->component = $component;
        $instance->attemptid = $attemptid;

        $instance->playedquestions = [];
        return $instance;
    }

    /**
     * Returns a representation of this instance that can be serialized to json
     *
     * This does not include all data, just the ones that will be saved to the
     * 'json' column in the database.
     */
    public function jsonSerialize(): mixed {
        return [
            'playedquestions' => $this->playedquestions,
        ];
    }

    /**
     * Deletes entries of this instance from the database and cache.
     *
     * @param int $attemptid
     * @return void
     */
    public static function delete(int $attemptid): void {
        // Delete cache.
        $cachekey = self::get_cache_key($attemptid);
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cache->delete($cachekey);

        // Remove the database entry.
        global $DB;
        $DB->delete_records('local_catquiz_progress', ['attemptid' => $attemptid]);
    }

    /**
     * Saves the object to the cache and DB so that it can be re-used later.
     * @return void
     */
    public function save(): void {
        global $DB;

        // Save to the DB.
        $record = (object) [
            'attemptid' => $this->attemptid,
            'userid' => $this->userid,
            'component' => $this->component,
            'json' => json_encode($this),
        ];

        // If it does not exist yet, insert a new record.
        if (! $this->id) {
            $id = $DB->insert_record('local_catquiz_progress', $record);
            if (! is_int($id)) {
                throw new \Exception(sprintf("Could not save quiz progress of attempt %d to the database", $this->attemptid));
            }
            $this->id = $id;
        } else {
            // Otherwise, just update.
            $record->id = $this->id;
            $DB->update_record('local_catquiz_progress', $record);
        }

        // Save to the cache.
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cache->set($this->get_cache_key($this->attemptid), $this);
    }

    /**
     * Returns the ID.
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Returns the userid.
     *
     * @return int
     */
    public function get_userid() {
        return $this->userid;
    }

    /**
     * Returns the component name.
     *
     * @return string
     */
    public function get_component() {
        return $this->component;
    }

    /**
     * Returns the attempt ID.
     *
     * @return int
     */
    public function get_attemptid() {
        return $this->attemptid;
    }

    /**
     * Returns the questions played in this attempt.
     *
     * @return array
     */
    public function get_playedquestions() {
        return $this->playedquestions;
    }

    /**
     * Adds a new question to the array of played questions.
     *
     * @param \stdClass $q A question
     * @return self
     */
    public function add_playedquestion(\stdClass $q): self {
        $this->playedquestions[$q->id] = $q;
        return $this;
    }

    /**
     * Returns the cache key.
     *
     * @param int $attemptid
     * @return string
     */
    private static function get_cache_key(int $attemptid): string {
        global $USER;
        return sprintf('progress_user_%d_id_%d', $USER->id, $attemptid);
    }
}
