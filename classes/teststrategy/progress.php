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

use local_catquiz\local\attempt\progress_integrity;
use cache;
use coding_exception;
use JsonSerializable;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\testenvironment;
use Random\RandomException;
use stdClass;
use local_catquiz\local\progress_retention;

/**
 * Stores the progress of a catquiz attempt that is not yet finished.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress implements JsonSerializable {
    /** @var string A scale became active (issue #133). */
    public const SCALE_ACTIVATED = 'activated';

    /** @var string A scale stopped being active. */
    public const SCALE_DEACTIVATED = 'deactivated';

    /** @var string A scale was locked: only a forced activation brings it back. */
    public const SCALE_LOCKED = 'locked';

    /** @var string A lock was lifted. */
    public const SCALE_UNLOCKED = 'unlocked';

    /** @var string A scale was removed for the rest of the attempt. */
    public const SCALE_REMOVED = 'removed';

    /** @var int JSON format that refers to played questions by id (issue #143). */
    public const FORMAT_COMPACT = 2;

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
     * @var int|null $catattemptid The CAT attempt this progress belongs to - local_catquiz_attempts.id.
     *
     * This is what local_catquiz_progress.attemptid holds. The property above keeps the id of the
     * attempt of the component - for mod_adaptivequiz that is adaptivequiz_attempt.id - because the
     * callers work with it; that one is never written to local_catquiz_progress.
     */
    private ?int $catattemptid = null;

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
     * Changes of the scales' state, recorded in trace mode only (issue #133): step, scaleid, event.
     * @var array
     */
    private array $scalestatetrace = [];

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
     * Issue #159: rebuilt from what was stored with a finished attempt after its progress was removed - read only.
     *
     * @var bool
     */
    private bool $historical = false;

    /**
     * Files the CAT attempt for a test that is starting.
     *
     * Only what is known at the start is written: the person, the attempt of the component and a
     * running status. Scale, context and course stay empty until the result page saves the attempt
     * through catquiz::save_attempt_to_db(), which updates this very row - it looks the attempt up by
     * its component id. Leaving them empty is deliberate: statistics select by scale, context or
     * course, and a running attempt is excluded from them without every query having to know.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param string $component The component the attempt belongs to.
     * @return int|null The id of the new row in local_catquiz_attempts, null if none can be filed.
     */
    private static function start_cat_attempt(int $adaptiveattemptid, string $component): ?int {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        // The CAT attempt belongs to the person whose attempt it is - also when somebody else is the
        // first to touch it (issue #95). For an activity attempt that is its user, not $USER.
        $ownerid = (int) $USER->id;
        if (in_array('mod_adaptivequiz', catquiz::component_names($component), true)) {
            $owner = $DB->get_field('adaptivequiz_attempt', 'userid', ['id' => $adaptiveattemptid]);
            if ($owner !== false) {
                $ownerid = (int) $owner;
            }
        }

        $existing = $DB->get_record('local_catquiz_attempts', ['attemptid' => $adaptiveattemptid]);
        if (!$existing) {
            $now = time();
            try {
                return (int) $DB->insert_record('local_catquiz_attempts', (object) [
                    'userid' => $ownerid,
                    'attemptid' => $adaptiveattemptid,
                    'component' => $component,
                    'status' => LOCAL_CATQUIZ_ATTEMPT_RUNNING,
                    'json' => '{}',
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            } catch (\dml_write_exception $e) {
                // Issue #95: a request in parallel was first - its row is taken over below. Before, the
                // unique index failed the second request with an error page.
                $existing = $DB->get_record('local_catquiz_attempts', ['attemptid' => $adaptiveattemptid]);
                if (!$existing) {
                    throw $e;
                }
            }
        }

        /* local_catquiz_attempts.attemptid is unique on its own (issue #5), not per component. A row of the
           same component and the same person is this attempt's CAT attempt; another component or person
           holding the number gets nothing of it - the progress then stays in the cache, as before issue #101,
           and is never handed someone else's. Resolving the namespace properly is issue #105. */
        if (
            in_array((string) $existing->component, catquiz::component_names($component), true)
            && (int) $existing->userid === $ownerid
        ) {
            return (int) $existing->id;
        }
        debugging(
            "Attempt {$adaptiveattemptid} of {$component} shares its number with a CAT attempt of another "
                . 'component or person; no CAT attempt was filed for it.',
            DEBUG_DEVELOPER
        );
        return null;
    }

    /**
     * Returns a new progress instance.
     *
     * If we already have data in the cache or DB, the instance is populated with those data.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param string $component
     * @param int $contextid
     * @param ?stdClass $quizsettings
     * @return progress
     */
    public static function load(int $adaptiveattemptid, string $component, int $contextid, ?stdClass $quizsettings = null): self {
        $catattemptid = catquiz::get_cat_attempt_id($adaptiveattemptid, $component);

        /* Issue #101: the CAT attempt exists before the first progress access. Until now it was
           written only by the result page at the end of a test, so during the whole attempt there
           was nothing to attach the progress to - it lived in the cache only and was lost to anyone
           else and to any cache flush. A caller that brings quiz settings is starting or continuing
           a test; that is where the CAT attempt is filed. A caller without settings only reads, and
           a reader never creates an attempt. */
        if ($catattemptid === null && $quizsettings !== null) {
            $catattemptid = self::start_cat_attempt($adaptiveattemptid, $component);
        }

        $instance = self::load_from_cache($catattemptid, $adaptiveattemptid, $component)
            ?: self::load_from_db($catattemptid, $contextid, $quizsettings !== null);
        if (!$instance) {
            /* Issue #149: a reader - no settings - never creates a progress, a CAT attempt or a cache entry. It
               used to go on to create_new() with settings null and ended in a TypeError on the result page. */
            if ($quizsettings === null) {
                throw new progress_missing_exception($adaptiveattemptid, $component);
            }
            $instance = self::create_new($adaptiveattemptid, $component, $contextid, $quizsettings);
        }

        // The CAT attempt may appear after this progress was first built - the component starts its
        // attempt, the CAT model files its own right after. Pick it up when it does.
        $instance->catattemptid = $catattemptid ?? $instance->catattemptid;
        // Re-audit 28.10.2026 (#149): a reader leaves the cache as it is - the move to the key of the CAT attempt is a
        // write, and only a writer makes it.
        if ($quizsettings !== null) {
            $instance->move_to_canonical_cache_key();
        }

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
     * The progress of a finished attempt whose progress was removed by the retention task, rebuilt read-only from
     * what was stored with the attempt when it was finalised (issue #159).
     *
     * Nothing is played again, nothing is estimated again with today's item parameters, nothing is written: the
     * questions in their order with the answer, scale and Fisher information they had, the abilities of the result,
     * the context and the start of the attempt. The settings come as for any progress stored without them (the
     * test's). What the stored data do not hold (the per-step trace, the state of the selection) stays empty; the
     * sections that would need it say so.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id.
     * @param string $component
     * @return self|null Null while the attempt is running, without stored data, or without a CAT attempt.
     */
    public static function load_historical(int $adaptiveattemptid, string $component): ?self {
        global $DB;
        $catattemptid = catquiz::get_cat_attempt_id($adaptiveattemptid, $component);
        if ($catattemptid === null) {
            return null;
        }
        $attempt = $DB->get_record('local_catquiz_attempts', ['id' => $catattemptid]);
        if (!$attempt || empty($attempt->endtime)) {
            return null;
        }
        $stored = json_decode((string) $attempt->json);
        if (!is_object($stored) || empty($stored->personabilities)) {
            return null;
        }
        $root = (int) ($stored->catscaleid ?? $attempt->scaleid);
        $parents = $DB->get_records_menu('local_catquiz_catscales', null, '', 'id, parentid');
        $played = [];
        $byscale = [];
        $responses = [];
        foreach ((array) ($stored->graphicalsummary_data ?? []) as $row) {
            $row = (object) $row;
            if (empty($row->id)) {
                continue;
            }
            $pilot = !empty($row->pilot);
            $question = (object) [
                'id' => (string) $row->id,
                'componentid' => (string) $row->id,
                'label' => (string) ($row->questionname ?? ''),
                'idnumber' => (string) ($row->questionname ?? ''),
                'name' => (string) ($row->questiontitle ?? ''),
                'catscaleid' => (string) ($row->questionscale ?? ''),
                'difficulty' => $row->difficulty ?? null,
                'is_pilot' => $pilot,
                'fisherinformation' => $pilot ? [] : [$root => (float) ($row->fisherinformation ?? 0)],
            ];
            $played[$question->id] = $question;
            // As the progress files a question: under its scale and every ancestor.
            for ($scaleid = (int) $question->catscaleid, $guard = 0; $scaleid > 0 && $guard < 50; $guard++) {
                $byscale[$scaleid][] = $question->id;
                $scaleid = (int) ($parents[$scaleid] ?? 0);
            }
            if (isset($row->lastresponse)) {
                $responses[$question->id] = ['questionid' => $question->id, 'fraction' => (float) $row->lastresponse];
            }
        }
        $abilities = [];
        foreach ((array) ($stored->updated_personabilities ?? []) as $scaleid => $entry) {
            if (is_object($entry) && isset($entry->value) && is_numeric($entry->value)) {
                $abilities[$scaleid] = (float) $entry->value;
            }
        }
        foreach ((array) $stored->personabilities as $scaleid => $value) {
            if (is_numeric($value)) {
                $abilities[$scaleid] = (float) $value;
            }
        }
        $data = [
            'progressformat' => self::FORMAT_COMPACT, 'playedquestions' => $played, 'playedquestionsbyscale' => $byscale,
            'isfirstquestion' => false, 'lastquestion' => null, 'breakend' => null, 'activescales' => [],
            'droppedscales' => [], 'contextid' => (int) $attempt->contextid, 'responses' => $responses,
            'abilities' => $abilities, 'preattemptabilities' => [], 'forcedbreakend' => null, 'lockedscales' => [],
            'usageid' => null, 'session' => null, 'excludedquestions' => [], 'gaveupquestions' => [],
            'starttime' => (int) $attempt->starttime, 'abilitytrace' => [],
        ];
        $object = (object) [
            'id' => null, 'userid' => (int) $attempt->userid, 'component' => $component, 'attemptid' => $catattemptid,
            'json' => json_encode($data), 'quizsettings' => null,
        ];
        // Read only: populate_from_object() without the cache neither saves nor caches (issue #149).
        $instance = self::populate_from_object($object, (int) $attempt->contextid, false);
        $instance->historical = true;
        $instance->hasnewresponse = false;
        $instance->ignorelastresponse = false;
        return $instance;
    }

    /**
     * Whether this progress was rebuilt from a finished attempt after its own was removed (issue #159).
     *
     * @return bool
     */
    public function is_historical(): bool {
        return $this->historical;
    }

    /**
     * Try to load a progress object from the cache.
     *
     * Under the key of the CAT attempt; before it exists - or for a progress built just before it was
     * filed - under the key of the component and its attempt (issue #95).
     *
     * @param int|null $catattemptid Id in local_catquiz_attempts, null while there is none.
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param string $component Component asking; empty to skip the component check.
     * @return progress|false
     * @throws coding_exception
     */
    private static function load_from_cache(?int $catattemptid, int $adaptiveattemptid, string $component = '') {
        global $USER;

        $attemptcache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cachekey = self::get_cache_key($catattemptid, $adaptiveattemptid, $component);
        $cached = $attemptcache->get($cachekey);
        if (!$cached instanceof self && $catattemptid !== null) {
            $cachekey = self::get_cache_key(null, $adaptiveattemptid, $component);
            $cached = $attemptcache->get($cachekey);
        }

        if (!$cached instanceof self) {
            return false;
        }

        /* A cached progress is taken over only after it has been shown to belong here. The cache
           key carries the user, but a stale entry could still name another one, and the component
           has never been part of the key at all. */
        if (
            (int) $cached->userid !== (int) $USER->id
                || ($component !== '' && $cached->component !== $component)
        ) {
            $attemptcache->delete($cachekey);

            return false;
        }

        return $cached;
    }

    /**
     * Reads the stored progress of a CAT attempt, for looking at it only.
     *
     * No cache, no ownership check against the current user and nothing created: a monitor reads
     * the progress of other people's running attempts, and a missing progress stays missing. The
     * caller is responsible for deciding who may see it.
     *
     * @param int $catattemptid Id in local_catquiz_attempts.
     * @return self|null
     */
    public static function load_for_reading(int $catattemptid): ?self {
        global $DB;

        $record = $DB->get_record('local_catquiz_progress', ['attemptid' => $catattemptid]);
        if (!$record) {
            return null;
        }
        $catattempt = $DB->get_record('local_catquiz_attempts', ['id' => $catattemptid]) ?: null;
        $contextid = $catattempt ? (int) $catattempt->contextid : 0;
        // Issue #96: a reader gets no progress rather than a foreign one; operations get an event.
        $reason = progress_integrity::check($record, $catattempt);
        if ($reason !== null) {
            progress_integrity::report($record, $catattempt, $reason, $contextid);
            return null;
        }

        return self::populate_from_object($record, $contextid, false);
    }

    /**
     * Try to load a progress object from the database.
     *
     * @param int|null $catattemptid Id of the CAT attempt - local_catquiz_attempts.id.
     * @param int $contextid
     * @param bool $cache Whether to keep it in the cache - not for a reader (issue #149).
     * @return progress|false
     */
    private static function load_from_db(?int $catattemptid, int $contextid, bool $cache = true) {
        global $DB;

        if ($catattemptid === null) {
            // No CAT attempt, so there cannot be progress belonging to one.
            return false;
        }

        // The attemptid of this table is the CAT attempt, not the attempt of the component.
        $record = $DB->get_record('local_catquiz_progress', ['attemptid' => $catattemptid], '*');

        if (!$record) {
            return false;
        }

        /* Issue #96: progress is taken over only if it belongs to its CAT attempt - same person, same
           component - and the CAT attempt exists. The comparison is against the owner of the CAT
           attempt, not against whoever is looking: a teacher opening a student's result reads that
           student's progress legitimately. A foreign row fails closed: the test stops with a generic
           message, the row is neither used nor overwritten, and operations get an event. Going on
           with a fresh progress instead would have collided with the foreign row on save. */
        $catattempt = $DB->get_record('local_catquiz_attempts', ['id' => $catattemptid]) ?: null;
        $reason = progress_integrity::check($record, $catattempt);
        if ($reason !== null) {
            progress_integrity::fail($record, $catattempt, $reason, $contextid);
        }
        return self::populate_from_object($record, $contextid, $cache);
    }

    /**
     * Populates the data from an object.
     *
     * @param stdClass $object
     * @param int $contextid Fallback used for old records saved before contextid was persisted.
     * @param bool $cache Whether to keep it in the cache; a reader does not write the cache (issue #149).
     * @return self
     */
    private static function populate_from_object(stdClass $object, int $contextid, bool $cache = true): self {
        global $DB;
        $instance = new self();
        $instance->id = $object->id;
        $instance->userid = $object->userid;
        $instance->component = $object->component;
        $instance->catattemptid = isset($object->attemptid) ? (int) $object->attemptid : null;

        // The external id is not stored here any more; it comes from the CAT attempt this progress
        // belongs to.
        $instance->attemptid = (int) $DB->get_field(
            'local_catquiz_attempts',
            'attemptid',
            ['id' => $instance->catattemptid]
        );

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
        // Issue #143: entries by id point at the very same played question again.
        $instance->playedquestionsbyscale = [];
        foreach ((array) $data->playedquestionsbyscale as $scaleid => $entries) {
            $instance->playedquestionsbyscale[$scaleid] = [];
            foreach ((array) $entries as $entry) {
                $question = self::resolve_played($entry, $instance->playedquestions);
                if ($question !== null) {
                    $instance->playedquestionsbyscale[$scaleid][] = $question;
                }
            }
        }
        $instance->isfirstquestion = $data->isfirstquestion;
        $instance->lastquestion = self::resolve_played($data->lastquestion ?? null, $instance->playedquestions);
        if ($instance->lastquestion !== null && empty($instance->lastquestion->is_pilot)) {
            $instance->lastquestion->fisherinformation = (array) ($instance->lastquestion->fisherinformation ?? []);
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
        // Issue #133: progress written before the scale state was traced has none.
        $instance->scalestatetrace = property_exists($data, 'scalestatetrace')
            ? array_values(array_map([static::class, 'to_array'], (array) $data->scalestatetrace))
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
            // The CAT attempt of this progress, by its own id - its external number may be another component's.
            $attemptjson = $DB->get_field('local_catquiz_attempts', 'json', ['id' => $instance->catattemptid]);
            $attemptdata = $attemptjson ? json_decode($attemptjson) : null;
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

            /* Save the quiz settings so that in the future we do not have to use the fallback anymore - but only
               for a writer: a reader (issue #149) writes nothing, it gets the settings for this request only. */
            $instance->quizsettings = $quizsettings;
            if ($cache) {
                $instance->save();
            }
        }
        $instance->quizsettings = json_decode($object->quizsettings);

        if ($cache) {
            $instance->save_to_cache();
        }

        return $instance;
    }

    /**
     * This sets default data for a new instance.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param string $component
     * @param int $contextid
     * @param stdClass $quizsettings
     * @return self
     */
    private static function create_new(int $adaptiveattemptid, string $component, int $contextid, stdClass $quizsettings): self {
        global $DB, $USER;
        $instance = new self();
        $instance->id = null;
        $instance->component = $component;
        $instance->attemptid = $adaptiveattemptid;
        $instance->catattemptid = catquiz::get_cat_attempt_id($adaptiveattemptid, $component);
        // The progress belongs to the person of its CAT attempt, as the CAT attempt itself - also when somebody
        // else is the first to touch it (issue #95). Before, it was filed under that somebody, and the owner's
        // next request was refused as somebody else's progress.
        $owner = $instance->catattemptid !== null
            ? $DB->get_field('local_catquiz_attempts', 'userid', ['id' => $instance->catattemptid])
            : false;
        $instance->userid = $owner !== false ? (int) $owner : $USER->id;
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
        /* Issue #143: every administered question once. playedquestionsbyscale held the full
           question again for its scale and every ancestor, lastquestion once more - in the JSON
           written on every answer, three to five copies of each question. Both now refer to the
           played questions by id; a question that is not among them (none should be) is still
           written in full, so nothing is lost. */
        $byscale = [];
        foreach ($this->playedquestionsbyscale as $scaleid => $questions) {
            $byscale[$scaleid] = [];
            foreach ($questions as $question) {
                $byscale[$scaleid][] = $this->played_reference($question);
            }
        }
        $data = [
            'progressformat' => self::FORMAT_COMPACT,
            'playedquestions' => $this->playedquestions,
            'playedquestionsbyscale' => $byscale,
            'isfirstquestion' => $this->isfirstquestion,
            'lastquestion' => $this->lastquestion === null ? null : $this->played_reference($this->lastquestion),
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
        // Issue #133: outside trace mode the stored JSON stays exactly as it was - not even an empty key.
        if ($this->scalestatetrace) {
            $data['scalestatetrace'] = $this->scalestatetrace;
        }
        return $data;
    }

    /**
     * A played question as the JSON refers to it: its id when it is among the played questions.
     *
     * @param stdClass $question
     * @return int|string|stdClass
     */
    private function played_reference(stdClass $question) {
        if (isset($question->id) && isset($this->playedquestions[$question->id])) {
            return $question->id;
        }
        return $question;
    }

    /**
     * The played question an entry of the JSON stands for: by id, or written in full (issue #143).
     *
     * Older records hold full copies; those are taken as they are.
     *
     * @param mixed $entry
     * @param array $played Played questions by id.
     * @return stdClass|null
     */
    private static function resolve_played($entry, array $played): ?stdClass {
        if (is_object($entry)) {
            return $entry;
        }
        if ((is_int($entry) || is_string($entry)) && isset($played[$entry])) {
            return $played[$entry];
        }
        return null;
    }

    /**
     * Deletes entries of this instance from the database and cache.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param string $component Component asking; empty to skip the component check.
     * @return void
     */
    public static function delete(int $adaptiveattemptid, string $component = 'mod_adaptivequiz'): void {
        global $DB;

        $catattemptid = catquiz::get_cat_attempt_id($adaptiveattemptid, $component);

        // Delete cache - under both keys.
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cache->delete(self::get_cache_key(null, $adaptiveattemptid, $component));
        if ($catattemptid !== null) {
            $cache->delete(self::get_cache_key($catattemptid, $adaptiveattemptid, $component));
        }

        if ($catattemptid !== null) {
            $DB->delete_records('local_catquiz_progress', ['attemptid' => $catattemptid]);
        }
    }

    /**
     * Saves the object to the cache and DB so that it can be re-used later.
     * @return void
     */
    public function save(): void {
        global $DB;

        // Issue #159: a progress rebuilt from a finished attempt is never written - neither to the table nor the cache.
        if ($this->historical) {
            return;
        }

        // Save to the DB.
        $record = (object) [
            // When this progress was last persisted (issue #122) - for live monitoring.
            'timemodified' => time(),
            'attemptid' => $this->catattemptid,
            'userid' => $this->userid,
            'component' => $this->component,
            'json' => json_encode($this),
        ];
        /* The settings go along with every save, not only the first: a progress stored before they were kept with
           it gets them on its next save (populate_from_object()), as intended - an update without them left the
           fallback running on every load (issue #149). */
        if (isset($this->quizsettings)) {
            $record->quizsettings = json_encode($this->quizsettings);
        }

        if ($this->catattemptid === null) {
            /* Without a CAT attempt there is nothing to attach the progress to, and guessing a
               reference is what issue #95 is about. The state stays in the cache; the next save
               once the CAT model has filed its attempt persists it. */
            $this->save_to_cache();

            return;
        }

        // If it does not exist yet, insert a new record.
        if (! $this->id) {
            $record->quizsettings = json_encode($this->quizsettings);
            try {
                $id = $DB->insert_record('local_catquiz_progress', $record);
            } catch (\dml_write_exception $e) {
                // Issue #95: a request in parallel saved the first progress of this attempt - the unique index
                // on attemptid let only one insert through. That row is this attempt's progress; it is updated
                // instead of failing the request with an error page.
                $id = $DB->get_field('local_catquiz_progress', 'id', ['attemptid' => $this->catattemptid]);
                if ($id === false) {
                    throw $e;
                }
                $id = (int) $id;
                $this->refuse_a_foreign_row($id);
                $record->id = $id;
                $DB->update_record('local_catquiz_progress', $record);
            }
            if (! is_int($id)) {
                throw new \Exception(sprintf("Could not save quiz progress of attempt %d to the database", $this->attemptid));
            }
            $this->id = $id;
        } else {
            // Otherwise, just update - the row of this attempt, as long as it is still ours (issue #96).
            $this->refuse_a_foreign_row((int) $this->id);
            $record->id = $this->id;
            $DB->update_record('local_catquiz_progress', $record);
        }

        $this->save_to_cache();
    }

    /**
     * Stops a save that would overwrite a stored progress of somebody else (issue #96, re-audit 27.10.2026).
     *
     * The progress in the session cache is checked against the person when it is loaded, not against the row it is
     * written back to. A row that belongs to another person or component by now - an id met by a foreign row, data
     * moved by hand or by a migration - was overwritten with the state of the session, and its owner set back. Here
     * the stored owner is compared first, with one read by primary key; on a mismatch nothing is written, the cached
     * state is dropped, and the attempt stops with the integrity message, as a load from the database does.
     *
     * @param int $id local_catquiz_progress.id
     * @return void
     */
    private function refuse_a_foreign_row(int $id): void {
        global $DB;
        $stored = $DB->get_record('local_catquiz_progress', ['id' => $id], 'id, attemptid, userid, component');
        if (!$stored) {
            return;
        }
        $samecomponent = in_array((string) $stored->component, catquiz::component_names((string) $this->component), true);
        if ((int) $stored->userid === (int) $this->userid && $samecomponent) {
            return;
        }
        cache::make('local_catquiz', 'adaptivequizattempt')
            ->delete(self::get_cache_key($this->catattemptid, (int) $this->attemptid, (string) $this->component));
        $catattempt = $DB->get_record('local_catquiz_attempts', ['id' => (int) $stored->attemptid]) ?: null;
        $reason = progress_integrity::check($stored, $catattempt)
            ?? ((int) $stored->userid !== (int) $this->userid
                ? progress_integrity::USER_MISMATCH
                : progress_integrity::COMPONENT_MISMATCH);
        progress_integrity::fail($stored, $catattempt, $reason, (int) $this->contextid);
    }

    /**
     * Writes this progress to the cache.
     */
    private function save_to_cache(): void {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cache->set(self::get_cache_key($this->catattemptid, (int) $this->attemptid, (string) $this->component), $this);
    }

    /**
     * Moves a progress cached before its CAT attempt existed to the key of the CAT attempt.
     */
    private function move_to_canonical_cache_key(): void {
        if ($this->catattemptid === null) {
            return;
        }
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $prekey = self::get_cache_key(null, (int) $this->attemptid, (string) $this->component);
        if ($cache->get($prekey) !== false) {
            $cache->delete($prekey);
            $this->save_to_cache();
        }
    }

    /**
     * The CAT context the attempt plays in: the one of its scale when the progress was created (issue #157, T10).
     *
     * A running attempt stays in it, also when the scale moves to another context in between - its questions,
     * parameters and estimates are of one calibration. 0 for a progress stored before the context was kept with it.
     *
     * @return int
     */
    public function get_contextid(): int {
        return (int) ($this->contextid ?? 0);
    }

    /**
     * Returns the id of the CAT attempt this progress belongs to.
     *
     * @return int|null
     */
    public function get_catattemptid(): ?int {
        return $this->catattemptid;
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
     * Returns the number of ANSWERED questions of this attempt, pilot items included.
     *
     * The measure for the test's maximum (issue #141): the maximum limits every question a person
     * is asked, productive or pilot, while the minimum - and every result - counts productive items
     * only ({@see self::get_num_answered_productive_questions()}). Based on the responses, like the
     * productive count, so a pending item after a resume does not count.
     *
     * @return int
     */
    public function get_num_answered_questions(): int {
        return count($this->responses);
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
            if (array_key_exists($scaleid, $this->lockedscales)) {
                unset($this->lockedscales[$scaleid]);
                $this->trace_scale_state($scaleid, self::SCALE_UNLOCKED);
            }
            $this->activescales[] = $scaleid;
            $this->trace_scale_state($scaleid, self::SCALE_ACTIVATED);
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
        if ($lock && !array_key_exists($scaleid, $this->lockedscales)) {
            $this->lockedscales[$scaleid] = true;
            $this->trace_scale_state($scaleid, self::SCALE_LOCKED);
        }
        if (!in_array($scaleid, $this->activescales)) {
            return $this;
        }
        unset($this->activescales[array_search($scaleid, $this->activescales)]);
        $this->trace_scale_state($scaleid, self::SCALE_DEACTIVATED);
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
        if (!isset($this->droppedscales[$scaleid])) {
            $this->droppedscales[$scaleid] = $scaleid;
            $this->trace_scale_state($scaleid, self::SCALE_REMOVED);
        }
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
        if (array_key_exists($scaleid, $this->lockedscales)) {
            unset($this->lockedscales[$scaleid]);
            $this->trace_scale_state((int) $scaleid, self::SCALE_UNLOCKED);
        }
        return $this;
    }

    /**
     * Records a change of a scale's state, in trace mode only (issue #133).
     *
     * The step is the one of the ability trace: the number of questions played so far.
     *
     * @param int $scaleid
     * @param string $event One of the SCALE_* constants.
     * @return void
     */
    private function trace_scale_state(int $scaleid, string $event): void {
        if (!progress_retention::should_trace()) {
            return;
        }
        $this->scalestatetrace[] = [
            'step' => $this->get_num_playedquestions(),
            'scaleid' => $scaleid,
            'event' => $event,
        ];
    }

    /**
     * The recorded changes of the scales' state: step, scaleid, event. Empty unless in trace mode.
     *
     * @return array
     */
    public function get_scale_state_trace(): array {
        return $this->scalestatetrace;
    }

    /**
     * The state the scales are in after a trace: active, dropped and locked scale ids, sorted.
     *
     * @param array $trace As get_scale_state_trace() returns it.
     * @return array active, dropped, locked
     */
    public static function replay_scale_state_trace(array $trace): array {
        $active = [];
        $dropped = [];
        $locked = [];
        foreach ($trace as $entry) {
            $scaleid = (int) $entry['scaleid'];
            switch ($entry['event']) {
                case self::SCALE_ACTIVATED:
                    $active[$scaleid] = $scaleid;
                    break;
                case self::SCALE_DEACTIVATED:
                    unset($active[$scaleid]);
                    break;
                case self::SCALE_LOCKED:
                    $locked[$scaleid] = $scaleid;
                    break;
                case self::SCALE_UNLOCKED:
                    unset($locked[$scaleid]);
                    break;
                case self::SCALE_REMOVED:
                    unset($active[$scaleid]);
                    $dropped[$scaleid] = $scaleid;
                    break;
            }
        }
        foreach ([&$active, &$dropped, &$locked] as &$ids) {
            $ids = array_values($ids);
            sort($ids);
        }
        unset($ids);
        return ['active' => $active, 'dropped' => $dropped, 'locked' => $locked];
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
     * Returns the cache key (issue #95).
     *
     * The canonical key is the CAT attempt - one id space, no collision between components. Before the
     * CAT attempt exists the key is the component with its own attempt id: the same number of two
     * components no longer pushes the other out. The cache is per session; whose progress it is, is
     * still checked when an entry is taken.
     *
     * @param int|null $catattemptid Id in local_catquiz_attempts, null while there is none.
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param string $component
     * @return string
     */
    private static function get_cache_key(?int $catattemptid, int $adaptiveattemptid, string $component): string {
        if ($catattemptid !== null) {
            return 'progress_cat_' . $catattemptid;
        }
        return sprintf('progress_pre_%s_%d', preg_replace('/[^a-z0-9_]/', '', strtolower($component)), $adaptiveattemptid);
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
