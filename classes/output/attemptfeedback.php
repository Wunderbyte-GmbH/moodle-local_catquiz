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
        $this->teststrategy = intval($settings->catquiz_selectteststrategy);

        if (!isset($feedbacksettings)) {
            $this->feedbacksettings = new feedbacksettings($this->teststrategy);
        } else {
            $this->feedbacksettings = $feedbacksettings;
        }

        if ($contextid === 0) {
            // Get the contextid of the catscale.
            $contextid = catscale::get_context_id(intval($settings->catquiz_catscales));
        }
        $this->contextid = $contextid;
    }

    /**
     * Returns the progress object for this attempt
     *
     * @return progress
     * @throws coding_exception
     * @throws Exception
     */
    public function get_progress(): progress {
        if (!$this->progress) {
            $this->progress = progress::load($this->attemptid, 'mod_adaptivequiz', $this->contextid);
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
            'component' => 'mod_adaptivequiz',
            'userid' => $USER->id,
            'teststrategy' => $this->teststrategy,
            'starttime' => $cache->get('starttime'),
            'endtime' => $cache->get('endtime'),
            'total_number_of_testitems' => $cache->get('totalnumberoftestitems'),
            'number_of_testitems_used' => null,
            'ability_before_attempt' => $cache->get('abilitybeforeattempt'),
            'catquizerror' => $cache->get('catquizerror'),
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
            $generatordata = $generator->update_data($this->attemptid, $existingdata, $newdata);
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

        $personabilities = $progress->get_abilities();

        if (!$personabilities) {
            return $newdata;
        }
        foreach ($personabilities as $scaleid => $abilityfloat) {
            $newarray[$scaleid]['value'] = $abilityfloat;
        };
        $newdata['updated_personabilities'] = $newarray;
        $newdata['catscaleid'] = intval($this->get_quiz_settings()->catquiz_catscales);
        $newdata['catscales'] = catquiz::get_catscales([$newdata['catscaleid'], ...$progress->get_selected_subscales()]);
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

        return [
            'feedback' => $this->get_feedback_for_attempt(),
        ];
    }

    /**
     * Triggers tasks when attempt finished
     */
    public function attempt_finished_tasks() {
        global $USER;

        $quizsettings = $this->get_quiz_settings();
        $feedbackdata = $this->load_feedbackdata();
        $coursestoenrol = $this->get_courses_to_enrol($feedbackdata, $quizsettings);
        $groupstoenrol = $this->get_groups_to_enrol($feedbackdata, $quizsettings);
        $enrolementmsg = catquiz::enrol_user((array) $quizsettings, $coursestoenrol, $groupstoenrol);
        $courseandinstance = catquiz::return_course_and_instance_id(
            $quizsettings['modulename'],
            $this->attemptid
        );

        // Trigger attempt_completed event.
        $event = attempt_completed::create([
            'objectid' => $this->attemptid,
            'context' => context_system::instance(),
            'other' => [
                'attemptid' => $this->attemptid,
                'catscaleid' => $quizsettings['catquiz_catscales'],
                'userid' => $USER->id,
                'contextid' => $this->contextid,
                'component' => $quizsettings['modulename'],
                'instanceid' => $courseandinstance['instanceid'],
                'teststrategy' => $this->teststrategy,
                'status' => LOCAL_CATQUIZ_ATTEMPT_OK,
            ],
        ]);
        $event->trigger();
        return $enrolementmsg;
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
    public function get_courses_to_enrol(
    ): array {
        $quizsettings = (array) $this->get_quiz_settings();
        $feedbackdata = $this->load_feedbackdata();

        // TODO: make sure that we can re-use the existing logic of inscribing
        // to all scales and not only primary scales. Use a plugin setting for
        // this.
        $inscribetoallscales = true;
        $candidatescales = $feedbackdata['personabilities_abilities'];
        if (!$inscribetoallscales) {
            // Use only the primary scale.
            $candidatescales = array_filter(
                $feedbackdata['personabilities_abilities'],
                fn($v) => array_key_exists('primary', $v) && $v['primary'] === true
            );
        }

        $coursestoenrol = [];
        foreach ($candidatescales as $scaleid => $data) {
            $i = 0;
            $coursestoenrol[$scaleid] = [
                'course_ids' => []
            ];
            while (isset($quizsettings['feedback_scaleid_limit_lower_' . $scaleid . '_'. ++$i])) {
                $lowerlimit = $quizsettings['feedback_scaleid_limit_lower_' . $scaleid . '_'. $i];
                $upperlimit = $quizsettings['feedback_scaleid_limit_upper_' . $scaleid. '_'. $i];
                if ($data['value'] < (float) $lowerlimit || $data['value'] > (float) $upperlimit) {
                    continue;
                }
                if (!($courses = $quizsettings['catquiz_courses_' . $scaleid . '_' . $i] ?? [])) {
                    continue;
                }
                // The first element at array key 0 is a dummy value to
                // display some message like "please select course" in the
                // form and has a course ID of 0.
                $courses = array_filter($courses, fn ($v) => $v != 0);
                $showenrolmentmessage = !empty($quizsettings["enrolment_message_checkbox_" . $scaleid . "_" . $i]);
                $coursestoenrol[$scaleid] = [
                    'range' => $i,
                    'show_message' => $showenrolmentmessage,
                    'course_ids' => $courses,
                ];
            }
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
     * @param array $feedbackdata
     * @param array $quizsettings
     * @return array
     */
    public function get_groups_to_enrol(
    ): array {
        $quizsettings = (array) $this->get_quiz_settings();
        $feedbackdata = $this->load_feedbackdata();

        // TODO: make sure that we can re-use the existing logic of inscribing
        // to all scales and not only primary scales. Use a plugin setting for
        // this.
        $inscribetoallscales = true;
        $candidatescales = $feedbackdata['personabilities_abilities'];
        if (!$inscribetoallscales) {
            // Use only the primary scale.
            $candidatescales = array_filter(
                $feedbackdata['personabilities_abilities'],
                fn($v) => array_key_exists('primary', $v) && $v['primary'] === true
            );
        }

        // Check if there is a course associated with that value and if so, return it.
        $groupstoenrol = [];
        $i = 0;
        foreach ($candidatescales as $scaleid => $data) {
            $groupstoenrol[$scaleid] = [];
            while (isset($quizsettings['feedback_scaleid_limit_lower_' . $scaleid . '_' . ++$i])) {
                $lowerlimit = $quizsettings['feedback_scaleid_limit_lower_' . $scaleid . '_' . $i];
                $upperlimit = $quizsettings['feedback_scaleid_limit_upper_' . $scaleid . '_' . $i];
                if ($data['value'] < (float) $lowerlimit || $data['value'] > (float) $upperlimit) {
                    continue;
                }
                if (!($groups = $quizsettings['catquiz_group_' . $scaleid . '_' . $i] ?? "")) {
                    continue;
                }
                $groups = explode(",", $groups);
                array_push($groupstoenrol[$scaleid], ...$groups);
            }
        }
        return $groupstoenrol;
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
        $primaryfeedbackname = 'customscalefeedback';

        // Set primary generator element (customscalefeedback) first.
        usort($generators, function($a, $b) use ($primaryfeedbackname) {
            if ($a->get_generatorname() == $primaryfeedbackname) {
                return -1;
            } else if ($b->get_generatorname() == $primaryfeedbackname) {
                return 1;
            } else {
                return 0;
            }
        });
        $context = [];
        foreach ($generators as $generator) {
            $feedbacks = $generator->get_feedback($feedbackdata);
            // Loop over studentfeedback and teacherfeedback.
            foreach ($feedbacks as $fbtype => $feedback) {
                if (!$feedback || !is_array($feedback)) {
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
        return $context;
    }
}
