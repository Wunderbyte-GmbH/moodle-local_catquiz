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

use cache;
use coding_exception;
use context_system;
use Exception;
use dml_exception;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\event\attempt_completed;
use local_catquiz\teststrategy\feedbackgenerator;
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
     * @var ?object
     */
    public feedbacksettings $feedbacksettings;

    /**
     * @var ?object
     */
    public stdClass $quizsettings;

    /**
     * Constructor of class.
     *
     * @param int $attemptid
     * @param int $contextid
     * @param ?feedbacksettings $feedbacksettings
     * @param int $courseid
     *
     */
    public function __construct(
        int $attemptid,
        int $contextid = 0,
        ?feedbacksettings $feedbacksettings = null,
        $courseid = null) {
        global $USER;
        if ($attemptid === 0) {
            // This can still return nothing. In that case, we show a message that the user has no attempts yet.
            if (!$attemptid = catquiz::get_last_user_attemptid($USER->id)) {
                return;
            }
        }
        $this->attemptid = $attemptid;

        if (!empty($courseid)) {
            $this->courseid = $courseid;
        }

        if (!$testenvironment = catquiz::get_testenvironment_by_attemptid($attemptid)) {
            return;
        }

        $settings = json_decode($testenvironment->json);
        $this->quizsettings = $settings;
        $this->teststrategy = intval($settings->catquiz_selectteststrategy);

        if (!isset($feedbacksettings)) {
            $this->feedbacksettings = new feedbacksettings($this->teststrategy);
        } else {
            $this->feedbacksettings = $feedbacksettings;
        }
        $catscaleid = intval($this->quizsettings->catquiz_catscales);
        $this->catscaleid = $catscaleid;

        if ($contextid === 0) {
            // Get the contextid of the catscale.
            $contextid = catscale::get_context_id($catscaleid);
        }
        $this->contextid = $contextid;
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
        $generators = $this->get_feedback_generators();
        $updateddata = $this->load_data_from_generators($generators, $existingdata, $newdata);
        catquiz::save_attempt_to_db($updateddata);
    }

    /**
     * Load feedbackdata
     *
     * @return array
     */
    public function load_feedbackdata(): array {
        global $DB;
        $feedbackdata = json_decode(
            $DB->get_field(
                'local_catquiz_attempts',
                'json',
                ['attemptid' => $this->attemptid]
            ),
            true
        );
        if (empty($feedbackdata)) {
            return $this->create_initial_data();
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
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        return [
            'attemptid' => $this->attemptid,
            'contextid' => $this->contextid,
            'courseid' => $this->courseid ?? 0,
            'userid' => $USER->id,
            'catscaleid' => $this->catscaleid,
            'teststrategy' => $this->teststrategy,
            'starttime' => $cache->get('starttime'),
            'endtime' => $cache->get('endtime'),
            'total_number_of_testitems' => $cache->get('totalnumberoftestitems'),
            'number_of_testitems_used' => null,
            'ability_before_attempt' => $cache->get('abilitybeforeattempt'),
            'catquizerror' => $cache->get('catquizerror'),
            'studentfeedback' => [],
            'teacherfeedback' => [],
            'quizsettings' => (array) $this->quizsettings,
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
        foreach ($generators as $generator) {
            $generatordata = $generator->load_data($this->attemptid, $existingdata, $newdata);
            if (! $generatordata) {
                continue;
            }
            $feedbackdata = array_merge(
                $feedbackdata,
                $generatordata
            );
        }

        return $feedbackdata;
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
     * @return array
     *
     */
    public function get_feedback_for_attempt(): array {
        $feedbackdata = $this->load_feedbackdata();
        $generators = $this->get_feedback_generators_for_teststrategy($feedbackdata['teststrategy']);
        return $this->generate_feedback($generators, $feedbackdata);
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
        // 1. Perform attempt-finished tasks.
        $this->attempt_finished_tasks();

        // 2. Return the feedback.
        return [
            'feedback' => $this->get_feedback_for_attempt(),
        ];
    }

    /**
     * Triggers tasks when attempt finished
     */
    private function attempt_finished_tasks() {
        global $USER;
        $progress = progress::load($this->attemptid, 'mod_adaptivequiz', $this->contextid);
        // TODO: UPDATE params here!! we need all infos from attempt (=feedbackdata).
        catquiz::enrol_user($USER->id, (array) $this->quizsettings, $progress->get_abilities());
        $courseandinstance = catquiz::return_course_and_instance_id(
            $this->quizsettings->modulename,
            $this->attemptid
        );

        // Trigger attempt_completed event.
        $event = attempt_completed::create([
            'objectid' => $this->attemptid,
            'context' => context_system::instance(),
            'other' => [
                'attemptid' => $this->attemptid,
                'catscaleid' => $this->catscaleid,
                'userid' => $USER->id,
                'contextid' => $this->contextid,
                'component' => $this->quizsettings->modulename,
                'instanceid' => $courseandinstance['instanceid'],
                'teststrategy' => $this->teststrategy,
                'status' => LOCAL_CATQUIZ_ATTEMPT_OK,
            ],
        ]);
        $event->trigger();
    }

    /**
     * Generates feedback.
     *
     * @param array $generators
     * @param array $feedbackdata
     *
     * @return array
     *
     */
    private function generate_feedback(array $generators, array $feedbackdata): array {
        if (!$feedbackdata) {
            return [];
        }
        foreach ($generators as $generator) {
            $feedbacks = $generator->get_feedback($feedbackdata);
            // Loop over studentfeedback and teacherfeedback.
            foreach ($feedbacks as $fbtype => $feedback) {
                if (!$feedback || !is_array($feedback)) {
                    continue;
                }

                $feedback['generatorname'] = $generator->get_generatorname();
                $primaryfeedbackname = 'customscalefeedback';
                if ($generator->get_generatorname() === $primaryfeedbackname) {
                    $feedback['frontpage'] = "1";
                } else {
                    $feedback['othertabs'] = "1";
                }
                $context[$fbtype][] = $feedback;
            }
        }
        return $context;
    }
}
