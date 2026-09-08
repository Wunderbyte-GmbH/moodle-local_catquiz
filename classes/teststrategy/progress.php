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
use local_catquiz\local\progress_retention;

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
     * @var array $droppedscales
     */
    private array $droppedscales;

    /**
     * If a scale is locked, it can not be activated or deactivated without
     * using a `force` parameter.
     *
     * @var array
     */
    private array $lockedscales;

    /**
     * @var array $responses
     */
    private array $responses;

    /**
     * @var array Holds the abilities indexed by catscale
     */
    private array $abilities;

    /**
     * Ability estimate per scale and step, recorded in trace mode only.
     * @var array
     */
    private array $abilitytrace = [];

    /**
     * @var array Holds the person abilities as they were BEFORE this attempt,
     * indexed by catscale. Captured once at the first question from the loaded
     * priors, so a scale not validly measured in this attempt can be restored to
     * its pre-attempt state at finalisation (issue #9, Phase 2).
     */
    private array $preattemptabilities = [];

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
     * @var ?string
     */
    private ?string $session;

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
            ?: self::load_from_db($attemptid, $contextid)
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

        /* The last administered question is still unanswered - typically after a
           reload or a resume. It STAYS in playedquestions: it was displayed to the
           user, and playedquestions is documented as exactly that ("the questions
           that were already displayed"). Removing it made the structure contradict
           itself, because lastquestion then pointed at a question that had
           supposedly never been played, and it made get_num_playedquestions()
           non-monotonic. The missing response identifies the item as pending, and
           every place that needs "how many questions were ANSWERED" now asks
           get_num_answered_productive_questions() instead of counting this array
           (Issue #6). Keeping it also prevents the pending item from being selected
           again as if it were a new question. */
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
     * @param int $contextid
     * @return progress|false
     */
    private static function load_from_db(int $attemptid, int $contextid) {
        global $DB;
        $record = $DB->get_record(
            'local_catquiz_progress',
            ['attemptid' => $attemptid],
            '*'
        );
        if ($record) {
            $instance = self::populate_from_object($record, $contextid);
            return $instance;
        }
        return false;
    }

    /**
     * Populates the data from an object.
     *
     * @param stdClass $object
     * @param int $contextid Fallback used for old records saved before contextid was persisted.
     * @return self
     */
    private static function populate_from_object(stdClass $object, int $contextid): self {
        global $DB;
        $instance = new self();
        $instance->id = $object->id;
        $instance->userid = $object->userid;
        $instance->component = $object->component;
        $instance->attemptid = $object->attemptid;

        // Set properties from json encoded data.
        $data = json_decode($object->json);
        // Old records saved before contextid was part of the serialized state don't have it.
        $instance->contextid = $data->contextid ?? $contextid;
        $instance->playedquestions = (array) $data->playedquestions;
        foreach ($instance->playedquestions as $pq) {
            if (!$pq->is_pilot) {
                $pq->fisherinformation = (array) $pq->fisherinformation;
            }
        }
        $instance->playedquestionsbyscale = (array) $data->playedquestionsbyscale;
        $instance->isfirstquestion = $data->isfirstquestion;
        $instance->lastquestion = $data->lastquestion;
        if ($instance->playedquestions) {
            if (!$instance->lastquestion->is_pilot) {
                $instance->lastquestion->fisherinformation = (array) $data->lastquestion->fisherinformation;
            }
        }

        $instance->breakend = $data->breakend;
        $instance->activescales = (array) $data->activescales;
        $instance->droppedscales = property_exists($data, 'droppedscales') ? (array) $data->droppedscales : [];

        // Attempts written before the trace was persisted have no such
        // key. Defaulting to an empty array keeps them loadable - refusing them would
        // break running attempts to fix a display detail.
        $instance->abilitytrace = property_exists($data, 'abilitytrace')
            // PHP 8.2 deprecates 'static::method' as a callable string and a later
            // version removes it. This sits on the abilitytrace load path, so the
            // removal would turn a loadable attempt into a fatal.
            ? array_map([static::class, 'to_array'], (array) $data->abilitytrace)
            : [];
        $instance->responses = (array) $data->responses;
        foreach ($instance->responses as $id => $val) {
            $instance->responses[$id] = (array) $val;
        }
        $instance->abilities = (array) $data->abilities;
        $instance->preattemptabilities = property_exists($data, 'preattemptabilities')
            ? (array) $data->preattemptabilities
            : [];
        $instance->forcedbreakend = intval($data->forcedbreakend) ?: null;
        $instance->lockedscales = property_exists($data, 'lockedscales') ? (array) $data->lockedscales : [];
        $instance->usageid = $data->usageid;
        $instance->session = $data->session ?? null;
        $instance->excludedquestions = $data->excludedquestions ?? [];
        $instance->gaveupquestions = $data->gaveupquestions ?? [];
        $instance->starttime = $data->starttime ?? 0;

        // Fallback for old attempts that did not store the quizsettings: use the current ones.
        if (!property_exists($object, 'quizsettings') || $object->quizsettings === null) {
            $attemptjson = $DB->get_record('local_catquiz_attempts', ['attemptid' => $instance->attemptid], 'json')->json;
            $attemptdata = json_decode($attemptjson);
            $quizsettings = $attemptdata->quizsettings ?? false;
            // If not even the attempt has quizsettings, get them from the test table.
            if (!$quizsettings) {
                $componentid = $DB->get_record('adaptivequiz_attempt', ['id' => $instance->attemptid], 'instance')->instance;
                $component = $instance->component;
                $data = (object)['componentid' => $componentid, 'component' => $component];
                $testenvironment = new testenvironment($data);
                $quizsettings = $testenvironment->return_settings();
            }
            $object->quizsettings = json_encode($quizsettings);

            // Save the quiz settings so that in the future we do not have to use the fallback anymore.
            $instance->quizsettings = $quizsettings;
            $instance->save();
        }
        $instance->quizsettings = json_decode($object->quizsettings);

        // Save to the cache.
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cache->set(self::get_cache_key($instance->attemptid), $instance);

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
        $instance->droppedscales = [];
        $instance->responses = [];
        $instance->abilities = [];
        $instance->preattemptabilities = [];
        $instance->forcedbreakend = null;
        $instance->lockedscales = [];
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
            'droppedscales' => $this->droppedscales,
            'contextid' => $this->contextid,
            'responses' => $this->responses,
            'abilities' => $this->abilities,
            'preattemptabilities' => $this->preattemptabilities,
            'forcedbreakend' => $this->forcedbreakend,
            'lockedscales' => $this->lockedscales,
            'usageid' => $this->usageid,
            'session' => $this->session,
            'excludedquestions' => $this->excludedquestions,
            'gaveupquestions' => $this->gaveupquestions,
            'starttime' => $this->starttime,
            // The trace was collected for the whole attempt and then
            // dropped at the end of the request. Everything that reads it - the
            // learning progress chart, the debug view - saw at most the steps of the
            // current page load, which looks like a short attempt rather than a lost
            // history.
            'abilitytrace' => $this->abilitytrace,
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
            $record->quizsettings = json_encode($this->quizsettings);
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
     * Returns the number of ANSWERED productive questions of this attempt.
     *
     * This is the authoritative measure for the configured test length. It is
     * deliberately based on the responses: the played questions only record which
     * items were displayed, and the questionsattempted counter on the adaptivequiz
     * attempt is maintained outside this plugin and can drift across a resume.
     * Pilot items never count towards the productive test length. Passing a scale
     * id restricts the count to the answers attributed to that scale, which is the
     * per-scale N used by the result validator.
     *
     * @param ?int $scaleid Restrict the count to this scale, or null for the whole attempt.
     *
     * @return int
     */
    public function get_num_answered_productive_questions(?int $scaleid = null): int {
        $count = 0;
        foreach (array_keys($this->responses) as $questionid) {
            $question = $this->playedquestions[$questionid] ?? null;
            if ($question !== null && !empty($question->is_pilot)) {
                continue;
            }
            if ($scaleid !== null && !$this->question_belongs_to_scale($questionid, $scaleid)) {
                continue;
            }
            $count++;
        }
        return $count;
    }

    /**
     * Returns the share of points reached on a scale, or null when nothing counts.
     *
     * Same population as get_num_answered_productive_questions(): answered items,
     * pilots excluded. The value is the mean of the per-item fractions, so a scale
     * answered fully correctly gives 1.0 and one answered fully wrongly gives 0.0.
     *
     * Null rather than 0.0 when no item counts - "no productive answer on this scale"
     * and "every answer was wrong" are different statements, and a column that cannot
     * tell them apart is worse than an empty one.
     *
     * @param int|null $scaleid Null counts across all scales.
     * @return float|null
     */
    public function get_fraction_for_scale(?int $scaleid = null): ?float {
        $sum = 0.0;
        $count = 0;

        foreach ($this->responses as $questionid => $response) {
            $question = $this->playedquestions[$questionid] ?? null;
            if ($question !== null && !empty($question->is_pilot)) {
                continue;
            }
            if ($scaleid !== null && !$this->question_belongs_to_scale($questionid, $scaleid)) {
                continue;
            }

            $fraction = is_array($response)
                ? ($response['fraction'] ?? null)
                : ($response->fraction ?? null);

            if ($fraction === null) {
                continue;
            }

            // Clamped: a question may award more than its maximum through overrides,
            // and a share above 1 would misrepresent the scale.
            $sum += min(1.0, max(0.0, (float) $fraction));
            $count++;
        }

        return $count > 0 ? $sum / $count : null;
    }

    /**
     * Shows whether an answered question was counted towards the given scale.
     *
     * @param int $questionid
     * @param int $scaleid
     *
     * @return bool
     */
    private function question_belongs_to_scale(int $questionid, int $scaleid): bool {
        $inscale = $this->playedquestionsbyscale[$scaleid] ?? [];
        foreach ($inscale as $question) {
            if ((int) ($question->id ?? 0) === $questionid) {
                return true;
            }
        }
        return false;
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
     * @param bool $force If set to true, also a locked scale will be set to active.
     * @return self
     */
    public function add_active_scale(int $scaleid, bool $force = false) {
        if ($this->is_dropped_scale($scaleid)) {
            // Skip - a dropped scale can not be re-activated.
            return $this;
        }
        if (
            !in_array($scaleid, $this->activescales)
            && (!array_key_exists($scaleid, $this->lockedscales) || $force)
        ) {
            unset($this->lockedscales[$scaleid]);
            $this->activescales[] = $scaleid;
        }
        return $this;
    }

    /**
     * This will mark the given scales as active.
     *
     * @param array $scales
     * @param bool $force If set to true, also a locked scale will be set to active.
     * @return $this
     */
    public function set_active_scales(array $scales, bool $force = false) {
        foreach ($scales as $scaleid) {
            $this->add_active_scale($scaleid, $force);
        }
        return $this;
    }

    /**
     * Deactivates the given scaleid
     *
     * @param int $scaleid
     * @param bool $lock If true, the scale can only be re-activated with the 'force' parameter.
     * @return $this
     */
    public function deactivate_scale(int $scaleid, bool $lock = false) {
        if ($lock) {
            $this->lockedscales[$scaleid] = true;
        }
        if (!in_array($scaleid, $this->activescales)) {
            return $this;
        }
        unset($this->activescales[array_search($scaleid, $this->activescales)]);
        return $this;
    }

    /**
     * Permanently removes the given scaleid from the list of active scales
     *
     * @param int $scaleid
     * @return $this
     */
    public function drop_scale(int $scaleid) {
        $this->deactivate_scale($scaleid);
        $this->droppedscales[$scaleid] = $scaleid;
        return $this;
    }

    /**
     * Shows if the given scale was dropped.
     *
     * In contrast to a deactivated scale, a dropped scale is removed
     * permanently for the current quiz attempt.
     *
     * @param mixed $scaleid
     * @return bool
     */
    public function is_dropped_scale($scaleid) {
        return array_key_exists($scaleid, $this->droppedscales);
    }

    /**
     * Ensures that the scale with the given sclaeid is not locked.
     *
     * @param int $scaleid
     * @return self
     */
    public function unlock_scale($scaleid): self {
        unset($this->lockedscales[$scaleid]);
        return $this;
    }

    /**
     * Returns if the given scale is locked.
     *
     * @param int $scaleid
     * @return bool
     */
    public function is_locked(int $scaleid): bool {
        return array_key_exists($scaleid, $this->lockedscales);
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
     * @param bool $rounded Round the abilities
     * @param int $precision Desired precision
     *
     * @return array
     */
    public function get_abilities(bool $rounded = false, int $precision = 2): array {
        if (!$rounded) {
            return $this->abilities;
        }
        $roundedabilities = array_map(fn($ab) => round($ab, $precision), $this->abilities);
        return $roundedabilities;
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
        // The empty array initialisation used to be pointless - the very
        // next line replaced it with a scalar, so abilities only ever held the last
        // estimate per scale and no trajectory could be reconstructed.
        //
        // In trace mode the estimates are appended instead. The other modes keep the
        // scalar, so nothing about the existing behaviour or the size of the stored
        // JSON changes for them.
        if (progress_retention::should_trace()) {
            if (!isset($this->abilitytrace[$catscaleid])) {
                $this->abilitytrace[$catscaleid] = [];
            }
            $this->abilitytrace[$catscaleid][] = [
                // The number of questions played so far identifies the step. There is
                // no get_step(); calling it broke every running attempt as soon as
                // trace mode was switched on, because set_ability() is on the hot path
                // of the estimation, not in some rarely used branch.
                'step' => $this->get_num_playedquestions(),
                'ability' => $ability,
            ];
        }

        $this->abilities[$catscaleid] = $ability;

        return $this;
    }

    /**
     * Returns the recorded ability trajectory per scale.
     *
     * Empty unless the retention level is trace. Each entry carries the step number
     * and the estimate, so a trajectory can be reconstructed without depending on
     * store_debug_info.
     *
     * @return array
     */
    public function get_ability_trace(): array {
        return $this->abilitytrace;
    }

    /**
     * Captures the person abilities as they are BEFORE this attempt.
     *
     * Called once at the first question with the loaded priors, before any
     * during-attempt estimate has been written. Only fills scales not captured
     * yet, so repeated calls are safe (issue #9, Phase 2).
     *
     * @param array $abilities Map of catscaleid => ability.
     * @return self
     */
    public function capture_preattempt_abilities(array $abilities): self {
        foreach ($abilities as $catscaleid => $ability) {
            if (!array_key_exists((int) $catscaleid, $this->preattemptabilities)) {
                $this->preattemptabilities[(int) $catscaleid] = (float) $ability;
            }
        }

        return $this;
    }

    /**
     * Returns the person abilities as they were before this attempt, indexed by
     * catscale (issue #9, Phase 2).
     *
     * @return array
     */
    public function get_preattempt_abilities(): array {
        return $this->preattemptabilities;
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
        $this->responses[$this->lastquestion->id] = array_merge(
            $this->responses[$this->lastquestion->id] ?? [],
            [
                'questionid' => $this->lastquestion->id,
                'fraction' => 0.0,
                'userlastattempttime' => time(),
            ],
        );
        return $this;
    }

    /**
     * Returns the last response for the current attempt.
     *
     * @return stdClass|bool
     */
    private function get_last_response_for_attempt() {
        /* Deliberately NOT cached. The cache key used to be
           "lastresponse_<usageid>_<numplayedquestions>", which silently assumed
           that the number of played questions is a monotonically growing version
           indicator of the response history. load() breaks that assumption: when
           the last administered question is still unanswered it is removed from
           playedquestions, so the counter goes 2 -> 1 and back to 2 once the item
           IS answered. The second time the key "..._2" is hit, the cache returns
           the OLD response (the one before the pending item), so the freshly given
           answer never reaches the response accumulation. The attempt then keeps
           administering items past the configured maximum.
           This query is small and targeted (one row via LIMIT 1) and runs a few
           dozen times per attempt at most, so correctness clearly outweighs the
           saved lookup. */
        $response = catquiz::get_last_response_for_attempt($this->get_usage_id());
        if ($response && $response->state === 'gaveup') {
            $response->fraction = 0.0;
        }
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
            if (
                strpos($key, 'catquiz_subscalecheckbox_') !== false
                && $value == "1"
            ) {
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
    /**
     * Casts a decoded json branch back to a nested array.
     *
     * json_decode() returns objects, while the trace is written and read as arrays of
     * arrays. Without the cast the restored value has the right shape but the wrong
     * type, and the first array access on it fails.
     *
     * @param mixed $value
     * @return array
     */
    protected static function to_array($value): array {
        $value = (array) $value;

        foreach ($value as $key => $entry) {
            if (is_object($entry) || is_array($entry)) {
                $value[$key] = (array) $entry;
            }
        }

        return $value;
    }
}
