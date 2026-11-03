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

namespace local_catquiz\output;

use local_catquiz\local\attempt_cache;
use cache;
use cache_helper;
use coding_exception;
use Exception;
use dml_exception;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\data\catscale_structure;
use local_catquiz\local\result\attempt_result_validator;
use local_catquiz\local\result\global_scale_validity;
use local_catquiz\local\result\scale_result;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\info;
use local_catquiz\teststrategy\progress;
use templatable;
use renderable;
use stdClass;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attemptfeedback implements renderable, templatable {
    /** @var string The generator of the main feedback, always built with the page. */
    public const PRIMARY_GENERATOR = 'customscalefeedback';

    /**
     * @var ?int
     */
    public int $attemptid;

    /**
     * @var ?int
     */
    public int $contextid;

    /**
     * @var ?int
     */
    public int $catscaleid;

    /**
     * @var ?int
     */
    public int $courseid;

    /**
     * @var ?int
     */
    public int $teststrategy;

    /**
     * @var ?progress
     */
    private ?progress $progress = null;

    /**
     * @var ?stdClass
     */
    private ?stdClass $quizsettings = null;

    /**
     * @var ?object
     */
    public feedbacksettings $feedbacksettings;

    /**
     * Constructor of class.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param int $contextid
     * @param ?feedbacksettings $feedbacksettings
     * @param int $courseid
     *
     */
    public function __construct(
        int $adaptiveattemptid,
        int $contextid = 0,
        ?feedbacksettings $feedbacksettings = null,
        $courseid = null
    ) {
        global $USER;

        // These are typed properties without defaults, and the constructor
        // has two early returns - no attempt found, and no test environment for the
        // attempt. Every path that reached one of them left the object half-built, and
        // the next property read threw "must not be accessed before initialization".
        //
        // In the selection pipeline that exception ends the run, so the learner is
        // shown no further question. The symptom appears as "the attempt stops after
        // Q1" and points nowhere near the feedback code that caused it.
        //
        // Initialising first makes a half-built object inert rather than explosive:
        // get_progress() then works against a zero context instead of throwing.
        $this->attemptid = 0;
        $this->contextid = 0;
        $this->courseid = 0;
        $this->teststrategy = 0;

        if ($adaptiveattemptid === 0) {
            // This can still return nothing. In that case, we show a message that the user has no attempts yet.
            if (!$adaptiveattemptid = catquiz::get_last_user_attemptid($USER->id)) {
                return;
            }
        }
        $this->attemptid = $adaptiveattemptid;

        if (!empty($courseid)) {
            $this->courseid = $courseid;
        }

        if (!$testenvironment = catquiz::get_testenvironment_by_attemptid($adaptiveattemptid)) {
            return;
        }

        $settings = json_decode($testenvironment->json);
        $this->teststrategy = intval($settings->catquiz_selectteststrategy);

        if (!isset($feedbacksettings)) {
            $this->feedbacksettings = new feedbacksettings($this->teststrategy);
        } else {
            $this->feedbacksettings = $feedbacksettings;
        }

        if ($contextid === 0) {
            // Issue #157 (T12): the CAT context the attempt was played in, as stored with it; only without one the
            // context of the scale now. After the scale moved to another context, the feedback of an earlier attempt
            // compares in the context its result was measured in.
            global $DB;
            $catattemptid = catquiz::get_cat_attempt_id($adaptiveattemptid, 'mod_adaptivequiz');
            $stored = $catattemptid ? (int) $DB->get_field('local_catquiz_attempts', 'contextid', ['id' => $catattemptid]) : 0;
            $contextid = $stored ?: catscale::get_context_id(intval($settings->catquiz_catscales));
        }
        $this->contextid = $contextid;
    }

    /**
     * Returns the progress object for this attempt - read, never created (issue #149).
     *
     * @return progress
     * @throws \local_catquiz\teststrategy\progress_missing_exception The attempt has no progress.
     * @throws coding_exception
     */
    public function get_progress(): progress {
        if (!$this->progress) {
            try {
                $this->progress = progress::load($this->attemptid, 'mod_adaptivequiz', $this->contextid);
            } catch (\local_catquiz\teststrategy\progress_missing_exception $e) {
                // Issue #159: a finished attempt after the retention task - read from what was stored with it.
                $this->progress = progress::load_historical((int) $this->attemptid, 'mod_adaptivequiz');
                if ($this->progress === null) {
                    throw $e;
                }
            }
        }
        return $this->progress;
    }

    /**
     * Returns the quiz settings for this attempt
     *
     * @return stdClass
     */
    public function get_quiz_settings() {
        if (!$this->quizsettings) {
            $this->quizsettings = $this->get_progress()->get_quiz_settings();
        }
        return $this->quizsettings;
    }

    /**
     * Updates the data that is used to render the feedback
     *
     * This is called after each response of a user.
     *
     * @param array $newdata
     * @return void
     * @throws coding_exception
     * @throws Exception
     * @throws dml_exception
     */
    public function update_feedbackdata(array $newdata = []) {
        $progress = $newdata['progress'];
        if (
            $progress->get_ignore_last_response()
            || (!$progress->is_first_question() && !$progress->has_new_response() && !$progress->get_force_new_question())
        ) {
            return;
        }
        $existingdata = $this->load_feedbackdata();
        if (isset($newdata['catscaleid']) && !isset($existingdata['catscaleid'])) {
            $existingdata['catscaleid'] = $newdata['catscaleid'];
        }
        $existingdata['attemptid'] = $progress->get_attemptid();
        if (!isset($existingdata['quizsettings'])) {
            $existingdata['quizsettings'] = $progress->get_quiz_settings();
        }
        if (!isset($existingdata['progress'])) {
            $existingdata['progress'] = $progress;
        }
        $generators = $this->get_feedback_generators();
        $updateddata = $this->load_data_from_generators($generators, $existingdata, $newdata);

        $this->save_to_cache($updateddata);

        catquiz::save_attempt_to_db($updateddata);
    }

    /**
     * Load feedbackdata
     *
     * The data of a completed attempt as stored (local_catquiz_attempts.json), or the snapshot of a
     * running one from the session cache.
     *
     * Issue #85: JSON handed in is used (it is the stored row the caller already read): no second query,
     * no progress, no cache. It was overwritten with null, so every attempt of a list read its row again,
     * loaded its progress and wrote its snapshot into the session - one per attempt shown.
     *
     * @param ?string $feedbackdata The stored JSON of the attempt, if the caller has it.
     * @param ?string $debugdata The stored debug information, if the caller has it.
     * @param bool $writecache False for read-only requests: the session cache is not written (issue #85).
     *
     * @return array
     */
    public function load_feedbackdata(?string $feedbackdata = null, ?string $debugdata = null, bool $writecache = true): array {
        global $DB;

        $passed = $feedbackdata !== null && $feedbackdata !== '';
        if ($passed) {
            $feedbackdata = json_decode($feedbackdata, true);
            $writecache = false;
        } else {
            // Try to get the attemptfeedback from the cache.
            // For the same number of questions played, the data do not change.
            if (($cached = $this->load_from_cache()) !== null) {
                return $cached;
            }
            $feedbackdata = json_decode(
                (string) $DB->get_field(
                    'local_catquiz_attempts',
                    'json',
                    ['attemptid' => $this->attemptid]
                ),
                true
            );
        }
        if (
            $feedbackdata
            && array_key_exists('primaryscale', $feedbackdata)
            && is_array($feedbackdata['primaryscale'])
        ) {
            $feedbackdata['primaryscale'] = new catscale_structure($feedbackdata['primaryscale']);
        }

        if (empty($feedbackdata)) {
            return $this->create_initial_data();
        }

        if (!get_config('local_catquiz', 'store_debug_info')) {
            return $this->cached($feedbackdata, $writecache);
        }

        // If this is an old version where feedbackdata are stored in the JSON column,
        // then just return it.
        if (array_key_exists('debuginfo', $feedbackdata)) {
            return $this->cached($feedbackdata, $writecache);
        }

        // In newer versions, the debuginfo data are stored in a separate column that can be emptied in case it takes up too much
        // space.
        if (
            !$debugdata && !$debugdata = $DB->get_field(
                'local_catquiz_attempts',
                'debug_info',
                ['attemptid' => $this->attemptid]
            )
        ) {
            return $this->cached($feedbackdata, $writecache);
        }

        $debuginfo = json_decode($debugdata, true) ?? [];
        $feedbackdata['debuginfo'] = $debuginfo;

        return $this->cached($feedbackdata, $writecache);
    }

    /**
     * Stores the data in the session cache when asked to, and returns them.
     *
     * @param array $feedbackdata
     * @param bool $writecache
     * @return array
     */
    private function cached(array $feedbackdata, bool $writecache): array {
        if ($writecache) {
            $this->save_to_cache($feedbackdata);
        }
        return $feedbackdata;
    }

    /**
     * Get feedback generators of teststrategy
     *
     * @return array
     */
    public function get_feedback_generators() {
        return $this->get_feedback_generators_for_teststrategy($this->teststrategy);
    }

    /**
     * Create initial data.
     *
     * @return array
     */
    private function create_initial_data(): array {
        global $USER;
        // Issue #146: the values of this attempt, under its own keys; the session-wide ones only for an
        // attempt that was running during the upgrade.
        $attemptid = (int) $this->attemptid;
        $error = attempt_cache::get('catquizerror', $attemptid);
        if (
            $error !== false
            && attempt_cache::cache()->get(attempt_cache::key('catquizerror', $attemptid)) === false
            && (int) attempt_cache::cache()->get('catquizerrorattempt') !== $attemptid
        ) {
            // The session-wide error of another attempt.
            $error = false;
        }
        return [
            'attemptid' => $this->attemptid,
            'contextid' => $this->contextid,
            'courseid' => $this->courseid ?? 0,
            'component' => 'mod_adaptivequiz',
            'userid' => $USER->id,
            'teststrategy' => $this->teststrategy,
            'starttime' => attempt_cache::get('starttime', $attemptid),
            'endtime' => attempt_cache::get('endtime', $attemptid),
            'total_number_of_testitems' => attempt_cache::get('totalnumberoftestitems', $attemptid),
            'number_of_testitems_used' => null,
            'ability_before_attempt' => attempt_cache::get('abilitybeforeattempt', $attemptid),
            'catquizerror' => $error,
            'studentfeedback' => [],
            'teacherfeedback' => [],
            'personabilities' => null,
            'num_pilot_questions' => null,
        ];
    }

    /**
     * Get the data from the feedbackgenerators.
     *
     * Updates the $context with new data returned by the generators.
     *
     * @param array $generators
     * @param array $existingdata
     * @param array $newdata
     * @return array
     */
    private function load_data_from_generators(array $generators, array $existingdata, array $newdata): array {
        // Get the data required to generate the feedback. This can be saved to
        // the DB.
        $feedbackdata = $existingdata;
        // Remove extra keys that we do not want to store because that can make this too large.
        $excludekeys = ['questions', 'original_questions', 'questionsperscale'];
        $newdata = array_filter($newdata, fn ($k) => !in_array($k, $excludekeys), ARRAY_FILTER_USE_KEY);
        $newdata = $this->add_default_data($newdata);
        foreach ($generators as $generator) {
            try {
                $generatordata = $generator->update_data($this->attemptid, $feedbackdata, $newdata);
            } catch (\moodle_exception $e) {
                /* This runs with every answer, inside the selection of the next question. A feedback
                   area that cannot be built - the colour bar of a scale without feedback ranges, say -
                   ended the whole attempt as a technical problem after the first answer. The area is
                   left out instead; the reason goes to the developer debugging output. */
                debugging(
                    'Feedback area ' . $generator->get_generatorname() . ' left out: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
                continue;
            }
            if (! $generatordata) {
                continue;
            }
            $feedbackdata = array_merge(
                $feedbackdata,
                $newdata,
                $generatordata
            );
        }
        // Data is not merged correctly into feedbackdata at this point.
        return $feedbackdata;
    }

    /**
     * Change format of personabilities.
     *
     * @param array $newdata
     *
     * @return array
     *
     */
    private function add_default_data(array $newdata): array {
        $newarray = [];
        $progress = $this->get_progress();

        $personabilities = $progress->get_abilities(true);

        if (!$personabilities) {
            return $newdata;
        }
        foreach ($personabilities as $scaleid => $abilityfloat) {
            $newarray[$scaleid]['value'] = $abilityfloat;
        };
        $newdata['updated_personabilities'] = $newarray;
        $newdata['catscaleid'] = intval($this->get_quiz_settings()->catquiz_catscales);
        $catscales = catquiz::get_catscales([$newdata['catscaleid'], ...$progress->get_selected_subscales()]);
        // Remove unneeded data from the catscales array.
        $newdata['catscales'] = $catscales;
        return $newdata;
    }

    /**
     * Gets feedback generators for teststrategy.
     *
     * @param int $strategyid
     * @return array<feedbackgenerator>
     */
    public function get_feedback_generators_for_teststrategy(int $strategyid): array {
        if (! $attemptstrategy = info::get_teststrategy($strategyid)) {
            return [];
        }

        if (!isset($this->feedbacksettings)) {
            $this->feedbacksettings = new feedbacksettings($strategyid);
        }
        return $attemptstrategy->get_feedbackgenerators($this->feedbacksettings);
    }

    /**
     * Gets feedback for attempt.
     *
     * @param ?string $feedbackdata
     * @param ?string $debuginfo
     *
     * @return array
     *
     */
    public function get_feedback_for_attempt(?string $feedbackdata = null, ?string $debuginfo = null): array {
        $feedbackdata = $this->load_feedbackdata($feedbackdata, $debuginfo);
        $generators = $this->get_feedback_generators_for_teststrategy($feedbackdata['teststrategy']);
        // Issue #85: the tabs beyond the main feedback load when they are opened, unless switched off.
        return $this->generate_feedback($generators, $feedbackdata, (bool) get_config('local_catquiz', 'lazyfeedbacktabs'));
    }

    /**
     * Export for template.
     *
     * @param \renderer_base $output
     *
     * @return array
     *
     */
    public function export_for_template(\renderer_base $output): array {
        // Below the minimum number of questions there is no result, but the attempt is shown all the
        // same: the notice with the reason, the quiz history and, for teachers, statistics and export
        // (issue #120). The page used to show the notice alone (GH-577).
        $noresult = [
            'noresult' => get_string('noresultforattempt', 'local_catquiz'),
            'attemptid' => (int) $this->attemptid,
        ];
        try {
            // Issue #149: a result comes from the feedback stored with the CAT attempt or from the progress.
            // Without either there is none - said so, instead of feedback built from nothing.
            if (!$this->has_stored_feedbackdata()) {
                $this->get_progress();
            }
            $feedback = $this->get_feedback_for_attempt();
        } catch (\local_catquiz\teststrategy\progress_missing_exception $e) {
            return $noresult;
        }
        return [
            'feedback' => $feedback,
            // The tabs load their content by attempt (issue #85); without it every lazy tab failed.
            'attemptid' => (int) $this->attemptid,
        ];
    }

    /**
     * Whether feedback data are stored with the CAT attempt of this attempt (issue #149).
     *
     * @return bool
     */
    private function has_stored_feedbackdata(): bool {
        global $DB;
        $catattemptid = catquiz::get_cat_attempt_id((int) $this->attemptid, 'mod_adaptivequiz');
        if ($catattemptid === null) {
            return false;
        }
        $json = json_decode((string) $DB->get_field('local_catquiz_attempts', 'json', ['id' => $catattemptid]), true);
        return is_array($json) && $json !== [];
    }

    /**
     * Triggers tasks when attempt finished
     */
    public function attempt_finished_tasks() {
        // Enrolment happens when the attempt is finalised (issue #129), on every way a test can end -
        // the result page may never be opened. Here the message of that enrolment is only shown.
        return \local_catquiz\local\attempt\attempt_enrolment::stored_message((int) $this->attemptid);
    }

    /**
     * The scales that route a participant into courses and groups, with the score that decides the range.
     *
     * Read from the final per-scale result of the attempt (attempt_result_validator::validate), the
     * source finalisation uses: N from productive answers, and the abilities of whichever feedback
     * generator the strategy registers. A scale routes when the written feedback would report it -
     * selected by the strategy (toreport), reporting enabled, not hidden, measured in this attempt and
     * statistically valid (feedback_helper::is_displayable). Not scale_result::$valid: that one is
     * bound to the primary scale and serves completion; Relevant, All and Classical route non-primary
     * scales too.
     *
     * @return array Score by scale id.
     */
    public function get_routing_scores(): array {
        return self::routing_scores((int) $this->attemptid);
    }

    /**
     * The routing scores of an attempt; see get_routing_scores().
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id.
     * @return array Score by scale id.
     */
    public static function routing_scores(int $adaptiveattemptid): array {
        $result = attempt_result_validator::validate($adaptiveattemptid);
        $scores = [];
        foreach ($result->get_scale_results() as $scaleid => $scale) {
            if ($scale->score === null || !feedback_helper::is_displayable($result, (int) $scaleid)) {
                continue;
            }
            $scores[(int) $scaleid] = (float) $scale->score;
        }

        return $scores;
    }

    /**
     * Returns the courses that the user shoule be enrolled to, indexed by scale ID.
     *
     * This depends on both the quiz settings, that contain the information about which ranges in which scales should trigger an
     * inscription to a course.
     * It also depends on the feedbackdata, which contain information about the abilities in the different scales and (depending on
     * the teststrategy) which of the scales was selected as the primary scale.
     *
     * Example return values:
     *
     * [
     *  '1' => [
     *      'range' => 2,
     *      'show_message' => true,
     *      'course_ids' => [1002, 1003]
     *      ]
     * ]
     *
     * @return array
     */
    public function get_courses_to_enrol(): array {
        // Issue #129: the candidates come from the final per-scale result of the attempt, the same
        // for courses and groups - not from the data of one feedback generator.
        return self::courses_to_enrol((array) $this->get_quiz_settings(), $this->get_routing_scores());
    }

    /**
     * Maps routing scores to courses via the ranges of the quiz settings.
     *
     * @param array $quizsettings The quiz settings of the attempt.
     * @param array $candidatescales Score by scale id, as routing_scores() returns them.
     * @return array
     */
    public static function courses_to_enrol(array $quizsettings, array $candidatescales): array {

        $coursestoenrol = [];
        foreach ($candidatescales as $scaleid => $score) {
            $coursestoenrol[$scaleid] = [
                'course_ids' => [],
            ];
            // A score falls into exactly one range (half-open), so the
            // enrolment for a scale is driven by that single range instead of
            // every range whose inclusive bounds contain the value.
            $i = feedback_helper::get_feedback_range_index($quizsettings, (int) $scaleid, (float) $score);
            if ($i === null) {
                continue;
            }
            // Range and message setting are known before any course: a scale that routes into groups
            // only has them too, and its message would otherwise never be shown (issue #130).
            $coursestoenrol[$scaleid] = [
                'range' => $i,
                'show_message' => !empty($quizsettings["enrolment_message_checkbox_" . $scaleid . "_" . $i]),
                'course_ids' => [],
            ];
            if (!($courses = $quizsettings['catquiz_courses_' . $scaleid . '_' . $i] ?? [])) {
                continue;
            }
            // The first element at array key 0 is a dummy value to
            // display some message like "please select course" in the
            // form and has a course ID of 0.
            $coursestoenrol[$scaleid]['course_ids'] = array_filter($courses, fn ($v) => $v != 0);
        }
        return $coursestoenrol;
    }

    /**
     * Returns the groups that the user should be enrolled to, indexed by scale ID.
     *
     * Example:
     * [
     *     '1' => [2,3]
     * ]
     *
     * @return array
     */
    public function get_groups_to_enrol(): array {
        // Issue #129: the candidates come from the final per-scale result of the attempt, the same
        // for courses and groups - not from the data of one feedback generator.
        return self::groups_to_enrol((array) $this->get_quiz_settings(), $this->get_routing_scores());
    }

    /**
     * Maps routing scores to groups via the ranges of the quiz settings.
     *
     * @param array $quizsettings The quiz settings of the attempt.
     * @param array $candidatescales Score by scale id, as routing_scores() returns them.
     * @return array
     */
    public static function groups_to_enrol(array $quizsettings, array $candidatescales): array {

        // Check if there is a course associated with that value and if so, return it.
        $groupstoenrol = [];
        foreach ($candidatescales as $scaleid => $score) {
            $groupstoenrol[$scaleid] = [];
            // A score falls into exactly one range (half-open).
            $i = feedback_helper::get_feedback_range_index($quizsettings, (int) $scaleid, (float) $score);
            if ($i === null) {
                continue;
            }
            if (!($groups = $quizsettings['catquiz_group_' . $scaleid . '_' . $i] ?? "")) {
                continue;
            }
            $groups = explode(",", $groups);
            array_push($groupstoenrol[$scaleid], ...$groups);
        }
        return $groupstoenrol;
    }

    /**
     * Generates feedback.
     *
     * @param array $generators
     * @param array $feedbackdata
     * @param bool $lazy Offer the tabs beyond the main feedback empty, to be loaded when opened (issue #85).
     *
     * @return array
     *
     */
    private function generate_feedback(array $generators, array $feedbackdata, bool $lazy = false): array {
        if (!$feedbackdata) {
            return [];
        }
        $primaryfeedbackname = self::PRIMARY_GENERATOR;

        // Set primary generator element (customscalefeedback) first.
        usort($generators, function ($a, $b) use ($primaryfeedbackname) {
            if ($a->get_generatorname() == $primaryfeedbackname) {
                return -1;
            } else if ($b->get_generatorname() == $primaryfeedbackname) {
                return 1;
            } else {
                return 0;
            }
        });
        // Determine result validity BEFORE running the generators, so
        // that for an invalid result no per-scale STUDENT feedback (peer
        // comparison, learning progress, ...) is assembled at all - the student
        // only ever sees the single central notice below. Teacher feedback is
        // still produced so teachers can inspect the (invalid) details.
        $abilities = $feedbackdata['customscalefeedback_abilities'] ?? null;
        $state = $this->result_state($feedbackdata);
        $hasvalidresult = $state->reportable;

        $context = [];
        foreach ($generators as $generator) {
            $isprimary = $generator->get_generatorname() === $primaryfeedbackname;
            /* For an invalid result, what reads the result stays out - peer comparison, learning
               progress, scale feedback (issue #120). What the attempt contained - summary, quiz
               history - and the export for authorised users do not depend on the result and stay.
               Validity used to switch off every generator but the primary one, and with them the
               quiz history and the export of exactly the attempts that most need looking into.
               The primary generator still runs: it carries the reason for the central notice. */
            if (!$isprimary && !self::shown_in_state($generator, $state)) {
                continue;
            }
            if ($lazy && !$isprimary) {
                // Issue #85: only the tab is offered; its content is built when it is opened.
                foreach ($this->lazy_tabs($generator, $feedbackdata) as $fbtype => $tab) {
                    $context[$fbtype][] = $tab;
                }
                continue;
            }
            $feedbacks = $this->feedback_of($generator, $feedbackdata);
            // Loop over studentfeedback and teacherfeedback.
            foreach ($feedbacks as $fbtype => $feedback) {
                if (!$feedback || !is_array($feedback)) {
                    continue;
                }
                // For an invalid result, the primary generator's scale feedback is not shown.
                if (!$hasvalidresult && $isprimary && $fbtype === 'studentfeedback') {
                    continue;
                }

                $feedback['generatorname'] = $generator->get_generatorname();
                if ($generator->get_generatorname() === $primaryfeedbackname) {
                    $feedback['frontpage'] = "1";
                } else {
                    $feedback['othertabs'] = "1";
                }
                $context[$fbtype][] = $feedback;
            }
        }

        // Bind the student feedback to a valid result. When the report
        // pipeline produced no reportable scale (toreport, not excluded/hidden),
        // the attempt has no valid result: show exactly one central notice that
        // carries the rejection reason, instead of scattering "not available"/
        // exclusion blocks across several tabs.
        if (!$hasvalidresult && is_array($abilities)) {
            $context['studentfeedback'] = $context['studentfeedback'] ?? [];
            $content = $this->notice($state, (int) ($feedbackdata['teststrategy'] ?? $this->teststrategy ?? 0));
            // In front of what remains, not instead of it: the quiz history stays (issue #120).
            array_unshift($context['studentfeedback'], [
                'heading' => get_string('feedbacknovalidresultheading', 'local_catquiz'),
                'content' => $content,
                'generatorname' => 'novalidresult',
                'frontpage' => '1',
            ]);
        }

        return $context;
    }

    /**
     * What the attempt allows the feedback to show (decision of 2026-10-14).
     *
     * reportable: the strategy reached its target - at least one reportable scale. global: the global
     * scale is valid by the criteria of the test (global_scale_validity), the same rule as the grade.
     * With a reportable scale the global scale is valid as well: its N is never smaller.
     *
     * @param array $feedbackdata
     * @return stdClass reportable, global, globalreasons (global_scale_validity), subreasons (of the other scales).
     */
    public function result_state(array $feedbackdata): stdClass {
        $state = (object) ['reportable' => true, 'global' => true, 'globalreasons' => [], 'subreasons' => []];
        $abilities = $feedbackdata['customscalefeedback_abilities'] ?? null;
        if (!is_array($abilities)) {
            return $state;
        }
        // With the per-scale N of this attempt (issue #128): without it every scale counted as measured.
        $adaptiveattemptid = (int) ($feedbackdata['attemptid'] ?? $this->attemptid ?? 0);
        $catattemptid = $adaptiveattemptid ? catquiz::get_cat_attempt_id($adaptiveattemptid, 'mod_adaptivequiz') : null;
        $progress = $catattemptid === null ? null : progress::load_for_reading($catattemptid);
        // Issue #159: after the retention task the finished attempt is read from what was stored with it.
        if ($progress === null && $catattemptid !== null) {
            $progress = progress::load_historical($adaptiveattemptid, 'mod_adaptivequiz');
        }
        $result = feedback_helper::build_attempt_result(
            $abilities,
            ['se' => $feedbackdata['se'] ?? [], 'nbyscale' => $feedbackdata['nbyscale'] ?? []]
                + ($progress ? ['progress' => $progress] : [])
        );
        $state->reportable = $result->has_reportable_result();

        $settings = $progress ? $progress->get_quiz_settings() : null;
        $globalscaleid = (int) ($settings->catquiz_catscales ?? $feedbackdata['catscaleid'] ?? 0);
        $global = null;
        foreach ($result->get_scale_results() as $scaleresult) {
            if ((int) $scaleresult->scaleid === $globalscaleid) {
                $global = $scaleresult;
                continue;
            }
            $state->subreasons[(int) $scaleresult->scaleid] = $scaleresult->rejectionreasons;
        }
        $state->globalreasons = $global === null
            ? [scale_result::REASON_NOT_MEASURED]
            : global_scale_validity::reasons((object) [
                'n' => $global->n,
                'standarderror' => $global->standarderror,
                'fraction' => $global->fraction,
            ], $settings);
        $state->global = $state->reportable || $state->globalreasons === [];
        return $state;
    }

    /**
     * Whether a generator has its tab in a result state.
     *
     * @param feedbackgenerator $generator
     * @param stdClass $state See result_state().
     * @return bool
     */
    private static function shown_in_state(feedbackgenerator $generator, stdClass $state): bool {
        switch ($generator->get_result_dependency()) {
            case feedbackgenerator::DEPENDS_ON_RESULT:
                return $state->reportable;
            case feedbackgenerator::DEPENDS_ON_GLOBAL:
                return $state->global;
            default:
                return true;
        }
    }

    /**
     * The central notice for an attempt whose strategy reached no target (decision of 2026-10-14).
     *
     * Global scale valid: the target was not reached, for this reason, but "Your result" gives a global
     * estimate. Global scale invalid as well: no overall result, for that reason. Quiz history and
     * learning progress stay in both cases.
     *
     * @param stdClass $state See result_state().
     * @param int $strategy
     * @return string
     */
    private function notice(stdClass $state, int $strategy): string {
        $a = (object) [
            'resulttab' => get_string('comparetotestaverage', 'local_catquiz'),
            'historytab' => get_string('quizgraphicalsummary', 'local_catquiz'),
            'progresstab' => get_string('learningprogresstitle', 'local_catquiz'),
        ];
        if ($state->global) {
            $categories = [];
            foreach ($state->subreasons as $reasons) {
                $categories = array_merge($categories, self::reason_categories($reasons));
            }
            $a->target = self::target_string($strategy);
            $a->reason = self::reason_phrase($categories);
            return get_string('feedbacktargetnotreached', 'local_catquiz', $a);
        }
        $a->reason = self::reason_phrase(self::reason_categories($state->globalreasons));
        return get_string('feedbacknoglobalresult', 'local_catquiz', $a);
    }

    /**
     * What a strategy sets out to measure.
     *
     * @param int $strategy
     * @return string
     */
    private static function target_string(int $strategy): string {
        $keys = [
            LOCAL_CATQUIZ_STRATEGY_LOWESTSUB => 'feedbacktarget_lowestsub',
            LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB => 'feedbacktarget_highestsub',
            LOCAL_CATQUIZ_STRATEGY_RELSUBS => 'feedbacktarget_relsubs',
            LOCAL_CATQUIZ_STRATEGY_ALLSUBS => 'feedbacktarget_allsubs',
        ];
        return get_string($keys[$strategy] ?? 'feedbacktarget_default', 'local_catquiz');
    }

    /**
     * The kinds of a scale's reasons that the notice names: too few questions, too imprecise, every
     * answer right, every answer wrong. Reasons about how a scale is shown are none of them.
     *
     * @param string[] $reasons
     * @return string[] nmin, se, allcorrect, allincorrect
     */
    private static function reason_categories(array $reasons): array {
        $map = [
            scale_result::REASON_N_MIN => 'nmin',
            scale_result::REASON_MIN_QUESTIONS => 'nmin',
            scale_result::REASON_NOT_MEASURED => 'nmin',
            scale_result::REASON_SE_MAX => 'se',
            scale_result::REASON_FRACTION_ALL_CORRECT => 'allcorrect',
            scale_result::REASON_FRACTION_ALL_INCORRECT => 'allincorrect',
        ];
        $categories = [];
        foreach ($reasons as $reason) {
            if (isset($map[$reason])) {
                $categories[] = $map[$reason];
            }
        }
        return $categories;
    }

    /**
     * The one reason the notice names: the first that applies of too few questions, every answer right or
     * every answer wrong, too imprecise (decision of 2026-10-14: N, then fraction, then SE).
     *
     * @param string[] $categories
     * @return string
     */
    private static function reason_phrase(array $categories): string {
        foreach (['nmin', 'allcorrect', 'allincorrect', 'se'] as $category) {
            if (in_array($category, $categories, true)) {
                return get_string("feedbackreason_{$category}", 'local_catquiz');
            }
        }
        return get_string('feedbackreason_other', 'local_catquiz');
    }

    /**
     * The tabs a generator offers without building its content (issue #85).
     *
     * A student tab for every generator writing for participants, a teacher tab only for users
     * allowed to see teacher feedback - and none when the attempt data lack what it needs.
     *
     * @param feedbackgenerator $generator
     * @param array $feedbackdata
     * @return array Tab stubs by feedback type (studentfeedback, teacherfeedback).
     */
    private function lazy_tabs(feedbackgenerator $generator, array $feedbackdata): array {
        if (!$generator->has_data_for($feedbackdata)) {
            return [];
        }

        $tabs = [];
        foreach ($generator->get_audiences() as $audience) {
            if ($audience === feedbackgenerator::AUDIENCE_TEACHER && !$generator->may_show_teacher_feedback($feedbackdata)) {
                continue;
            }
            $tabs[$audience . 'feedback'] = [
                'generatorname' => $generator->get_generatorname(),
                'heading' => $generator->get_heading(),
                'content' => '',
                'lazy' => '1',
                'audience' => $audience,
                'othertabs' => '1',
            ];
        }
        return $tabs;
    }

    /**
     * The feedback of one generator for one audience: the content of a tab opened later (issue #85).
     *
     * The same rules as the page: a generator that reads the result shows nothing for an invalid
     * result; teacher feedback needs the teacher permission.
     *
     * @param string $generatorname
     * @param string $audience feedbackgenerator::AUDIENCE_STUDENT or AUDIENCE_TEACHER.
     * @return array ['heading' => ..., 'content' => ...], or [] when there is nothing to show.
     */
    public function get_single_feedback(string $generatorname, string $audience): array {
        if (!$this->attemptid || !$this->teststrategy || $generatorname === self::PRIMARY_GENERATOR) {
            return [];
        }
        // Below the minimum number of questions only what reads the result stays out - as on the page
        // (issue #120): the check of the result dependency below covers it.
        // A read-only request: the session is not written (issue #85). The endpoint holds no session
        // lock, and with read-only sessions a write would be lost or refused.
        $feedbackdata = $this->load_feedbackdata(null, null, false);
        $generators = $this->get_feedback_generators_for_teststrategy((int) ($feedbackdata['teststrategy'] ?? $this->teststrategy));
        foreach ($generators as $generator) {
            if ($generator->get_generatorname() !== $generatorname) {
                continue;
            }
            if (!in_array($audience, $generator->get_audiences(), true)) {
                return [];
            }
            if (!self::shown_in_state($generator, $this->result_state($feedbackdata))) {
                return [];
            }
            $feedback = $this->feedback_of($generator, $feedbackdata)[$audience . 'feedback'] ?? [];
            return is_array($feedback) ? $feedback : [];
        }
        return [];
    }

    /**
     * The feedback of one generator, or a notice in its place when it cannot be built (issue #152).
     *
     * One area that throws - the quiz history of an attempt stored in an older format did - took the whole feedback
     * page down, every other area with it. Now the area says it could not be shown, the reason goes to debugging(),
     * and the rest of the page stays.
     *
     * @param feedbackgenerator $generator
     * @param array $feedbackdata
     * @return array ['studentfeedback' => ..., 'teacherfeedback' => ...] as feedbackgenerator::get_feedback().
     */
    private function feedback_of(feedbackgenerator $generator, array $feedbackdata): array {
        try {
            return $generator->get_feedback($feedbackdata);
        } catch (\Throwable $e) {
            debugging(
                'Feedback area ' . $generator->get_generatorname() . ' could not be built: ' . $e->getMessage(),
                DEBUG_DEVELOPER,
                $e->getTrace()
            );
            $notice = [
                'heading' => $generator->get_heading(),
                'content' => \html_writer::div(
                    get_string('feedbackareanotshown', 'local_catquiz'),
                    'alert alert-warning',
                    ['data-region' => 'local_catquiz-feedbackarea-notice']
                ),
            ];
            $student = in_array(feedbackgenerator::AUDIENCE_STUDENT, $generator->get_audiences(), true);
            return [
                'studentfeedback' => $student ? $notice : [],
                'teacherfeedback' => $student ? [] : $notice,
            ];
        }
    }

    /**
     * The session cache key of the feedback snapshot of this attempt (issue #142).
     *
     * One key per attempt. The snapshot carries the number of questions it was taken at; a snapshot
     * taken at another number is out of date and is not used.
     *
     * Before, every number of played questions had a key of its own (feedbackdata_<attempt>_<n>),
     * and the one meant to release the previous snapshot was computed as
     * `$numplayed = get_num_playedquestions() >= 1` - the comparison binds before the
     * assignment, so $numplayed was true, "_0" was deleted every time and _1 … _n-1 stayed. Each
     * of them holds the full feedback state including the progress, so the session cache grew with
     * the square of the test length - and the session is read on every request.
     *
     * @return string
     */
    private function get_feedback_cache_key(): string {
        return sprintf('feedbackdata_%d', $this->attemptid);
    }

    /**
     * The cached feedback snapshot of this attempt, if it was taken at the current number of
     * played questions.
     *
     * @return array|null
     */
    private function load_from_cache(): ?array {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $entry = $cache->get($this->get_feedback_cache_key());
        if (
            !is_array($entry)
            || !array_key_exists('numplayed', $entry)
            || !is_array($entry['data'] ?? null)
            || (int) $entry['numplayed'] !== $this->get_progress()->get_num_playedquestions()
        ) {
            return null;
        }
        return $entry['data'];
    }

    /**
     * Stores the feedback snapshot of this attempt, replacing the previous one.
     *
     * Retention (issue #142): at most one snapshot per attempt in the session cache, whatever the
     * number of questions, on a cache hit as on a miss. Snapshots of other attempts are not
     * touched. The first write for an attempt also removes the per-question keys the earlier code
     * may have left for it (an attempt that was running during the upgrade).
     *
     * @param array $data
     * @return void
     */
    private function save_to_cache(array $data) {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $key = $this->get_feedback_cache_key();
        $numplayed = $this->get_progress()->get_num_playedquestions();
        if ($cache->get($key) === false) {
            $cache->delete_many(array_map(
                fn(int $n) => sprintf('feedbackdata_%d_%d', $this->attemptid, $n),
                range(0, $numplayed)
            ));
        }
        $cache->set($key, ['numplayed' => $numplayed, 'data' => $data]);
    }
}
