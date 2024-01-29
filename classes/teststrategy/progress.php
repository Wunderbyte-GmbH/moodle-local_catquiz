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
use local_catquiz\catscale;
use stdClass;

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
     * @var array $playedquestionsbyscale The questions that were already displayed to the user.
     */
    private array $playedquestionsbyscale;

    /**
     * @var bool $isfirstquestion Indicates if this is the first question in the current attempt.
     */
    private bool $isfirstquestion;

    /**
     * @var null|stdClass The previous question.
     */
    private ?stdClass $lastquestion;

    /**
     * @var int $breakend If a user is forced to take a break, this stores the end of the break.
     */
    private int $breakend;

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
            $instance = self::populate_from_object($cachedata);
            return $instance;
        }

        global $DB;
        $record = $DB->get_record(
            'local_catquiz_progress',
            ['attemptid' => $attemptid],
            '*'
        );
        if ($record) {
            $instance = self::populate_from_object($record);
            return $instance;
        }

        // If we are here, this must be a new attempt.
        return self::create_new($attemptid, $component);
    }

    /**
     * Populates the data from an object.
     *
     * @param stdClass $object
     * @return self
     */
    private static function populate_from_object(stdClass $object): self {
        $instance = new self();
        $instance->id = $object->id;
        $instance->userid = $object->userid;
        $instance->component = $object->component;
        $instance->attemptid = $object->attemptid;
        $data = json_decode($object->json);
        $instance->playedquestions = (array) $data->playedquestions;
        $instance->playedquestionsbyscale = (array) $data->playedquestionsbyscale;
        $instance->isfirstquestion = $data->isfirstquestion;
        $instance->lastquestion = $data->lastquestion;
        $instance->lastquestion->fisherinformation = (array) $instance->lastquestion->fisherinformation;
        return $instance;
    }

    /**
     * This sets default data for a new instance.
     *
     * @param int $attemptid
     * @param ?string $component
     * @return self
     */
    private static function create_new(int $attemptid, ?string $component): self {
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
        $instance->playedquestionsbyscale = [];
        $instance->isfirstquestion = true;
        $instance->lastquestion = null;
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
            'playedquestionsbyscale' => $this->playedquestionsbyscale,
            'isfirstquestion' => $this->isfirstquestion,
            'lastquestion' => $this->lastquestion,
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
     * @param bool $byscale Return questions per scale.
     * @param ?int $scaleid If given, only return questions from that scale.
     * @return array
     */
    public function get_playedquestions(bool $byscale = false, ?int $scaleid = null) {
        if (! $byscale) {
            return $this->playedquestions;
        }

        if (! $scaleid) {
            return $this->playedquestionsbyscale;
        }

        if (! array_key_exists($scaleid, $this->playedquestionsbyscale)) {
            return [];
        }

        return $this->playedquestionsbyscale[$scaleid];
    }

    /**
     * Returns the number of questions played in this attempt.
     *
     * @return int
     */
    public function get_num_playedquestions() {
        return count($this->playedquestions);
    }

    /**
     * Shows if this is the first question in the current attempt.
     *
     * @return bool
     */
    public function is_first_question() {
        return $this->isfirstquestion;
    }

    public function set_first_question_played() {
        $this->isfirstquestion = false;
        return $this;
    }

    /**
     * Adds a new question to the array of played questions.
     *
     * @param stdClass $q A question
     * @return self
     */
    public function add_playedquestion(stdClass $q): self {
        $this->playedquestions[$q->id] = $q;

        // Keep track of questions played per scale.
        $affectedscales = [
            $q->catscaleid,
            ...catscale::get_ancestors($q->catscaleid),
        ];
        foreach ($affectedscales as $scaleid) {
            if (!array_key_exists($scaleid, $this->playedquestionsbyscale)) {
                $this->playedquestionsbyscale[$scaleid] = [$q];
                continue;
            }
            $this->playedquestionsbyscale[$scaleid][] = $q;
        }

        $this->lastquestion = $q;

        return $this;
    }

    /**
     * Returns the previous question.
     *
     * @return null|stdClass
     */
    public function get_last_question(): ?stdClass {
        return $this->lastquestion;
    }

    public function force_break($duration) {
        $now = time();
        $this->breakend = $now + $duration;
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
