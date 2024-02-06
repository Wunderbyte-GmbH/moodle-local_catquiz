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
use Exception;
use JsonSerializable;
use local_catquiz\catcontext;
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
     * @var int $contextid The context ID
     */
    private int $contextid;

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
     * @var ?int $breakend If a user is forced to take a break, this stores the end of the break.
     */
    private ?int $breakend;

    /**
     * @var array $activescales
     */
    private array $activescales;

    /**
     * @var array $responses
     */
    private array $responses;

    /**
     * @var array Holds the abilities indexed by catscale
     */
    private array $abilities;

    /**
     * If the user is forced to take a break, this holds the timestamp of the end of the break.
     *
     * If no break is enforced, it has a value of null.
     *
     * @var ?int $forcedbreakend
     */
    private ?int $forcedbreakend;

    /**
     * Returns a new progress instance.
     *
     * If we already have data in the cache or DB, the instance is populated with those data.
     *
     * @param int $attemptid
     * @param string $component
     * @param int $contextid
     * @return progress
     */
    public static function load(int $attemptid, string $component, int $contextid): self {
        $attemptcache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cachekey = self::get_cache_key($attemptid);
        if ($instance = $attemptcache->get($cachekey)) {
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
        return self::create_new($attemptid, $component, $contextid);
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

        // Set properties from json encoded data.
        $data = json_decode($object->json);
        $instance->contextid = $data->contextid;
        $instance->playedquestions = (array) $data->playedquestions;
        $instance->playedquestionsbyscale = (array) $data->playedquestionsbyscale;
        $instance->isfirstquestion = $data->isfirstquestion;
        $instance->lastquestion = $data->lastquestion;
        $instance->lastquestion->fisherinformation = (array) $instance->lastquestion->fisherinformation;
        $instance->breakend = $data->breakend;
        $instance->activescales = (array) $data->activescales;
        $instance->responses = (array) $data->responses;
        foreach ($instance->responses as $id => $val) {
            $instance->responses[$id] = (array) $val;
        }
        $instance->abilities = (array) $data->abilities;
        $instance->forcedbreakend = intval($data->forcedbreakend) ?: null;

        return $instance;
    }

    /**
     * This sets default data for a new instance.
     *
     * @param int $attemptid
     * @param string $component
     * @param int $contextid
     * @return self
     */
    private static function create_new(int $attemptid, string $component, int $contextid): self {
        global $USER;
        $instance = new self();
        $instance->id = null;
        $instance->userid = $USER->id;
        $instance->component = $component;
        $instance->attemptid = $attemptid;
        $instance->contextid = $contextid;

        $instance->playedquestions = [];
        $instance->playedquestionsbyscale = [];
        $instance->isfirstquestion = true;
        $instance->lastquestion = null;
        $instance->breakend = null;
        $instance->activescales = [];
        $instance->responses = [];
        $instance->abilities = [];
        $instance->forcedbreakend = null;
        return $instance;
    }

    /**
     * Returns a representation of this instance that can be serialized to json
     *
     * This does not include all data, just the ones that will be saved to the
     * 'json' column in the database.
     */
    public function jsonSerialize(): array {
        return [
            'playedquestions' => $this->playedquestions,
            'playedquestionsbyscale' => $this->playedquestionsbyscale,
            'isfirstquestion' => $this->isfirstquestion,
            'lastquestion' => $this->lastquestion,
            'breakend' => $this->breakend,
            'activescales' => $this->activescales,
            'contextid' => $this->contextid,
            'responses' => $this->responses,
            'abilities' => $this->abilities,
            'forcedbreakend' => $this->forcedbreakend,
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
        $now = time();
        $q->lastattempttime = $now;
        $q->userlastattempttime = $now;

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

    /**
     * Force user to take a break for $duration seconds.
     *
     * @param int $duration
     * @return $this
     */
    public function force_break(int $duration) {
        $now = time();
        $this->breakend = $now + $duration;
        return $this;
    }

    /**
     * Returns the scales that are currently active
     *
     * This is not used by all teststrategies, but some strategies keep a list
     * of scales from which they are returning questions.
     *
     * @return array
     */
    public function get_active_scales() {
        return $this->activescales;
    }

    public function is_active_scale(int $scaleid) {
        return in_array($scaleid, $this->activescales);
    }

    /**
     * Adds the given scale to the list of active scales.
     *
     * @param int $scaleid The scale ID
     * @return self
     */
    public function add_active_scale(int $scaleid) {
        if (! in_array($scaleid, $this->activescales)) {
            $this->activescales[] = $scaleid;
        }
        return $this;
    }

    /**
     * This will mark the given scales as active.
     *
     * @param array $scales
     * @return $this
     */
    public function set_active_scales(array $scales) {
        $this->activescales = $scales;
        return $this;
    }

    /**
     * Removes the given scaleid from the list of active scales
     *
     * @param int $scaleid
     * @return $this
     */
    public function drop_scale(int $scaleid) {
        unset($this->activescales[array_search($scaleid, $this->activescales)]);
        return $this;
    }

    /**
     * Returns the responses in this attempt.
     * @return array
     */
    public function get_user_responses() {
        return $this->responses;
    }

    /**
     * Returns the pilot questions that were shown to the user.
     * @return array
     */
    public function get_played_pilot_questions(): array {
        return array_filter(
            $this->playedquestions,
            fn ($q) => !empty($q->is_pilot)
        );
    }

    /**
     * Returns the abilities calculated during the current attempt.
     *
     * @return array
     */
    public function get_abilities(): array {
        return $this->abilities;
    }

    /**
     * Sets the ability for the given CAT scale.
     *
     * @param float $ability
     * @param int $catscaleid
     *
     * @return self
     */
    public function set_ability(float $ability, int $catscaleid): self {
        $this->abilities[$catscaleid] = $ability;
        return $this;
    }

    /**
     * Returns the end of the user's break.
     *
     * If no break is enforced, returns null.
     *
     * @return ?int
     */
    public function get_forced_break_end(): ?int {
        return $this->forcedbreakend;
    }

    /**
     * Shows if a user just completed a break.
     *
     * @return bool
     */
    public function break_completed(): bool {
        $now = time();

        // User was not in a break.
        if (!$this->forcedbreakend) {
            return false;
        }

        // User did not end the break.
        if ($this->forcedbreakend > $now) {
            return false;
        }

        // Ok: reset breakend to null and indicate the break finished.
        $this->forcedbreakend = null;
        return true;
    }

    /**
     * Shows if the user still has a break.
     *
     * @return bool
     */
    public function has_break(): bool {
        $now = time();
        if ($this->forcedbreakend && $this->forcedbreakend <= $now) {
            $this->forcedbreakend = null;
            return false;
        }
        return true;
    }

    /**
     * Update cached responses.
     *
     * @return mixed
     */
    public function update_cached_responses() {
        $lastresponse = catcontext::getresponsedatafromdb(
            $this->contextid,
            [$this->lastquestion->catscaleid],
            $this->lastquestion->id,
            $this->userid
        );
        if (! $lastresponse) {
            throw new Exception(sprintf(
                "Could not find the last response. user=%d lastquestion=%d contextid=%d",
                $this->userid,
                $this->lastquestion->id,
                $this->contextid
            ));
        }
        $this->responses[$this->lastquestion->id] = $lastresponse[$this->userid]['component'][$this->lastquestion->id];

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
