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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy;

use cache;
use coding_exception;
use JsonSerializable;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\testenvironment;
use Random\RandomException;
use stdClass;

defined('MOODLE_INTERNAL') || die();
// No login check is expected here because this is already done in the
// adaptivequiz attempt.php file. @codingStandardsIgnoreLine
require_once(__DIR__ . '/../../../../config.php');

/**
 * Stores the progress of a catquiz attempt that is not yet finished.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
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
     * @var ?int $usageid Used to find questions answered in the current attempt.
     */
    private ?int $usageid;

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
     * Indicates if we have a new response and should update feedbackdata etc.
     * @var bool
     */
    private bool $hasnewresponse;

    /**
     * Holds the session key of the session when the quiz was started
     *
     * @var string
     */
    private string $session;

    /**
     * Shows if a new question should be displayed even after a page reload.
     *
     * @var bool
     */
    private bool $forcenewquestion;

    /**
     * Holds a list of questions that should not be returned.
     *
     * @var array
     */
    private array $excludedquestions;

    /**
     * Shows if we should skip updating internal values based on the last response.
     *
     * @var bool
     */
    private bool $ignorelastresponse;

    /**
     * Contains question IDs that the user did not answer.
     *
     * @var array
     */
    private array $gaveupquestions;

    /**
     * Holds the starttime of the attempt.
     *
     * @var int
     */
    private int $starttime;

    /**
     * Holds the quizsetting
     *
     * This holds the settings as given when the attempt was started.
     * @var stdClass
     */
    private stdClass $quizsettings;

    /**
     * Returns a new progress instance.
     *
     * If we already have data in the cache or DB, the instance is populated with those data.
     *
     * @param int $attemptid
     * @param string $component
     * @param int $contextid
     * @param ?stdClass $quizsettings
     * @return progress
     */
    public static function load(int $attemptid, string $component, int $contextid, ?stdClass $quizsettings = null): self {
        $instance = self::load_from_cache($attemptid)
            ?: self::load_from_db($attemptid)
            ?: self::create_new($attemptid, $component, $contextid, $quizsettings);

        $instance->hasnewresponse = false;
        $instance->ignorelastresponse = false;

        if (!$instance->lastquestion) {
            return $instance;
        }

        $lastresponse = $instance->get_last_response_for_attempt();

        // This is the expected default behaviour: the user answered the last
        // question and now we'll return the next one.
        if ($lastresponse && $lastresponse->questionid === $instance->lastquestion->id) {
            $instance->hasnewresponse = true;
            return $instance;
        }

        // If the user gave up, count it as negative response.
        if ($instance->user_gave_up_last_question()) {
            $instance->gaveupquestions[] = $instance->lastquestion->id;
            $instance->mark_lastquestion_failed();
            $instance->hasnewresponse = true;
            return $instance;
        }

        // If there is no response for the last question that was shown to the
        // user, do not count that question as part of the attempt and remove it
        // from the progress. This can happen if a page is reloaded.
        $instance->playedquestions = array_filter(
            $instance->playedquestions,
            fn($q) => $q->id != $instance->lastquestion->id
        );
        foreach ($instance->playedquestionsbyscale as $scaleid => $qps) {
            $instance->playedquestionsbyscale[$scaleid] = array_filter(
                $qps,
                fn($q) => $q->id != $instance->lastquestion->id
            );
            if (count($instance->playedquestionsbyscale[$scaleid]) === 0) {
                unset($instance->playedquestionsbyscale[$scaleid]);
            }
        }

        return $instance;
    }

    /**
     * Try to load a progress object from the cache.
     *
     * @param int $attemptid
     * @return progress
     * @throws coding_exception
     */
    private static function load_from_cache($attemptid) {
        $attemptcache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cachekey = self::get_cache_key($attemptid);
        return $attemptcache->get($cachekey);
    }

    /**
     * Try to load a progress object from the database.
     *
     * @param int $attemptid
     * @return progress|false
     */
    private static function load_from_db(int $attemptid) {
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
        return false;
    }

    /**
     * Populates the data from an object.
     *
     * @param stdClass $object
     * @return self
     */
    private static function populate_from_object(stdClass $object): self {
        global $DB;
        $instance = new self();
        $instance->id = $object->id;
        $instance->userid = $object->userid;
        $instance->component = $object->component;
        $instance->attemptid = $object->attemptid;

        // Set properties from json encoded data.
        $data = json_decode($object->json);
        $instance->contextid = $data->contextid;
        $instance->playedquestions = (array) $data->playedquestions;
        foreach ($instance->playedquestions as $pq) {
            $pq->fisherinformation = (array) $pq->fisherinformation;
        }
        $instance->playedquestionsbyscale = (array) $data->playedquestionsbyscale;
        $instance->isfirstquestion = $data->isfirstquestion;
        $instance->lastquestion = $data->lastquestion;
        if ($instance->playedquestions) {
            $instance->lastquestion->fisherinformation = (array) $data->lastquestion->fisherinformation;
        }

        $instance->breakend = $data->breakend;
        $instance->activescales = (array) $data->activescales;
        $instance->responses = (array) $data->responses;
        foreach ($instance->responses as $id => $val) {
            $instance->responses[$id] = (array) $val;
        }
        $instance->abilities = (array) $data->abilities;
        $instance->forcedbreakend = intval($data->forcedbreakend) ?: null;
        $instance->usageid = $data->usageid;
        $instance->session = $data->session;
        $instance->excludedquestions = $data->excludedquestions;
        $instance->gaveupquestions = $data->gaveupquestions;
        $instance->starttime = $data->starttime;

        // Fallback for old attempts that did not store the quizsettings: use the current ones.
        if (!property_exists($data, 'quizsettings')) {
            $attemptjson = $DB->get_record('local_catquiz_attempts', ['attemptid' => $instance->attemptid], 'json')->json;
            $attemptdata = json_decode($attemptjson);
            $quizsettings = $attemptdata->quizsettings;
            // If not even the attempt has quizsettings, get them from the test table.
            if (!$quizsettings) {
                $componentid = $DB->get_record('adaptivequiz_attempt', ['id' => $instance->attemptid], 'instance')->instance;
                $component = $instance->component;
                $data = (object)['componentid' => $componentid, 'component' => $component];
                $testenvironment = new testenvironment($data);
                $quizsettings = $testenvironment->return_settings();
            }
            $data->quizsettings = $quizsettings;

            // Save the quiz settings so that in the future we do not have to use the fallback anymore.
            $instance->quizsettings = $quizsettings;
            $instance->save();
        }
        $instance->quizsettings = $data->quizsettings;

        return $instance;
    }

    /**
     * This sets default data for a new instance.
     *
     * @param int $attemptid
     * @param string $component
     * @param int $contextid
     * @param stdClass $quizsettings
     * @return self
     */
    private static function create_new(int $attemptid, string $component, int $contextid, stdClass $quizsettings): self {
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
        $instance->usageid = null;
        $instance->hasnewresponse = false;
        $instance->session = sesskey();
        $instance->excludedquestions = [];
        $instance->gaveupquestions = [];
        $instance->starttime = time();
        $instance->quizsettings = $quizsettings;
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
            'usageid' => $this->usageid,
            'session' => $this->session,
            'excludedquestions' => $this->excludedquestions,
            'gaveupquestions' => $this->gaveupquestions,
            'starttime' => $this->starttime,
            'quizsettings' => $this->quizsettings,
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
     * Returns the responses.
     *
     * @return array
     */
    public function get_responses() {
        return $this->responses;
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
     * Returns a clone of the progress class with pilot questions removed
     *
     * @return self
     */
    public function without_pilots() {
        $filteredprogress = clone $this;
        foreach ($filteredprogress->playedquestionsbyscale as $scaleid => $questions) {
            $filteredprogress->playedquestionsbyscale[$scaleid] = array_filter($questions, fn ($q) => !$q->is_pilot);
        }
        $filteredprogress->playedquestions = array_filter($filteredprogress->playedquestions, fn ($q) => !$q->is_pilot);
        return $filteredprogress;
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

    /**
     * Marks, that the first question was already played.
     *
     * @return $this
     */
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

    /**
     * Shows if the given scale is active.
     *
     * @param int $scaleid
     * @return bool
     */
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
        if (!in_array($scaleid, $this->activescales)) {
            return $this;
        }
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
        if (!isset($this->abilities[$catscaleid])) {
            $this->abilities[$catscaleid] = [];
        }
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
     * Update responses.
     *
     * @return self
     */
    public function update_cached_responses() {
        // No new response - maybe a page reload. Do not change anything.
        if (!($lastresponse = $this->get_last_response_for_attempt())) {
            return $this;
        }

        // Do not count a response to the same question twice.
        if (array_key_exists($lastresponse->questionid, $this->responses)) {
            return $this;
        }

        $this->responses[$lastresponse->questionid] = (array) $lastresponse;
        return $this;
    }

    /**
     * Marks the last question as failed
     *
     * @return $this
     */
    public function mark_lastquestion_failed() {
        $this->responses[$this->lastquestion->id] = [
            'questionid' => $this->lastquestion->id,
            'fraction' => 0.0,
            'userlastattempttime' => time(),
        ];
        return $this;
    }

    /**
     * Returns the last response for the current attempt.
     *
     * @return stdClass|bool
     */
    private function get_last_response_for_attempt() {
        $response = catquiz::get_last_response_for_attempt($this->get_usage_id());
        return $response;
    }

    /**
     * Show if the last question was answered.
     *
     * @return bool
     */
    private function user_gave_up_last_question(): bool {
        return catquiz::user_gave_up_question($this->get_usage_id(), $this->lastquestion->id);
    }

    /**
     * Returns the last response, if available.
     *
     * Adds the question ID as 'qid' key.
     *
     * @return null|array
     */
    public function get_last_response(): ?array {
        if (!$this->responses) {
            return null;
        }

        $lastresponse = array_slice($this->responses, -1, 1, true);
        $responseid = array_keys($lastresponse)[0];
        $lastresponse[$responseid]['qid'] = $responseid;
        return $lastresponse[$responseid];
    }

    /**
     * Returns the usage id for the current attempt.
     *
     * @return null|int
     */
    public function get_usage_id() {
        if ($usageid = $this->usageid) {
            return $usageid;
        }

        global $DB;
        $this->usageid = $DB->get_record(
            'adaptivequiz_attempt',
            ['id' => $this->attemptid],
            'uniqueid',
            MUST_EXIST
        )->uniqueid;
        return $this->usageid;
    }

    /**
     * Shows if there is a new response.
     *
     * @return bool
     */
    public function has_new_response() {
        return $this->hasnewresponse;
    }

    /**
     * Shows if the current session matches the one used to start the quiz
     *
     * @return bool
     */
    public function check_session() {
        $currentsess = sesskey();
        return $this->session === $currentsess;
    }

    /**
     * Updates the session key of the attempt to the current session.
     * @return self
     * @throws RandomException
     */
    public function set_current_session() {
        $currentsess = sesskey();
        $this->session = $currentsess;
        return $this;
    }

    /**
     * Sets forcenewquestion to true
     *
     * @return self
     */
    public function force_new_question() {
        $this->forcenewquestion = true;
        // Exclude the last question.
        if ($this->lastquestion) {
            $this->exclude_question($this->lastquestion->id);
        }
        return $this;
    }

    /**
     * Show if a new question should be used
     *
     * @return bool
     */
    public function get_force_new_question() {
        return $this->forcenewquestion ?? false;
    }

    /**
     * Updates the list of question IDs that should be ignored.
     *
     * @param int $qid The ID of the question to exclude
     * @return self
     */
    public function exclude_question(int $qid) {
        $this->excludedquestions[] = $qid;
        return $this;
    }

    /**
     * Returns IDs of excluded questions
     *
     * @return array
     */
    public function get_excluded_questions() {
        return $this->excludedquestions;
    }

    /**
     * Shows if the page was reloaded
     *
     * @return bool
     */
    public function page_was_reloaded() {
        if ($this->is_first_question() && !$this->get_last_question()) {
            return false;
        }
        if ($this->has_new_response()) {
            return false;
        }

        return true;
    }

    /**
     * Set the value of ignorelastresponse
     *
     * @see get_ignore_last_response
     * @param bool $val
     * @return $this
     */
    public function set_ignore_last_response(bool $val) {
        $this->ignorelastresponse = $val;
        return $this;
    }

    /**
     * Indicates if the last response should be ignored

     * Can be used by preselect tasks to check if they can skip their calculations.
     *
     * @return bool
     */
    public function get_ignore_last_response() {
        return $this->ignorelastresponse ?? false;
    }

    /**
     * Returns the timestamp of the quiz start.
     *
     * @return int
     */
    public function get_starttime(): int {
        return $this->starttime;
    }

    /**
     * Returns the quiz settings
     *
     * The settings are given as defined at the beginning of the attempt.
     * @return stdClass
     */
    public function get_quiz_settings(): stdClass {
        return $this->quizsettings;
    }

    /**
     * Gets selected subscales
     *
     * @return array
     */
    public function get_selected_subscales() {
        // Get selected subscales from quizdata.
        $selectedsubscales = [];
        foreach ($this->quizsettings as $key => $value) {
            if (strpos($key, 'catquiz_subscalecheckbox_') !== false
                && $value == "1") {
                    $catscaleid = substr_replace($key, '', 0, 25);
                    $selectedsubscales[] = $catscaleid;
            }
        };
        return $selectedsubscales;
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
