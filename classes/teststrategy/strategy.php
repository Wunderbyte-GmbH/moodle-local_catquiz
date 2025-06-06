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
 * Abstract class strategy.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy;

use cache;
use coding_exception;
use Exception;
use dml_exception;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_strategy;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\output\attemptfeedback;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\preselect_task\addscalestandarderror;
use local_catquiz\teststrategy\preselect_task\filterbystandarderror;
use local_catquiz\teststrategy\preselect_task\filterbytestinfo;
use local_catquiz\teststrategy\preselect_task\firstquestionselector;
use local_catquiz\teststrategy\preselect_task\fisherinformation;
use local_catquiz\teststrategy\preselect_task\lasttimeplayedpenalty;
use local_catquiz\teststrategy\preselect_task\maximumquestionscheck;
use local_catquiz\teststrategy\preselect_task\maybe_return_pilot;
use local_catquiz\teststrategy\preselect_task\maybe_return_pilot_testing;
use local_catquiz\teststrategy\preselect_task\mayberemovescale;
use local_catquiz\teststrategy\preselect_task\noremainingquestions;
use local_catquiz\teststrategy\preselect_task\remove_uncalculated;
use local_catquiz\teststrategy\preselect_task\removeplayedquestions;
use local_catquiz\teststrategy\preselect_task\updatepersonability;
use local_catquiz\teststrategy\preselect_task\updatepersonability_testing;
use local_catquiz\teststrategy\progress;
use moodle_url;

/**
 * Base class for test strategies.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class strategy {
    /**
     * This can be overwritten by strategies to make them unavailable.
     */
    public const ACTIVE = true;

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = 0; // Administrativ.

    /**
     *
     * @var int $id scaleid.
     */
    public int $scaleid;

    /**
     *
     * @var int $catcontextid
     */
    public int $catcontextid;

    /**
     * @var progress
     */
    protected progress $progress;

    /**
     * @var cache $cache
     */
    private cache $cache;

    /**
     * @var result $result
     */
    private $result;

    /**
     * These data provide the context for the selection of the next question.
     *
     * In a previous implementation, this object was passed between middlewares
     * and allowed them to decide what to do.
     * It would be good to refactor this in such a way that the different
     * elements of this array become class properties of this (strategy) class.
     *
     * @var array
     */
    protected array $context;

    /**
     * Instantioate parameters.
     */
    public function __construct() {
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        $this->cache = cache::make('local_catquiz', 'adaptivequizattempt');
    }

    /**
     * Returns the class that will select the next question.
     *
     * @return preselect_task
     */
    abstract public function get_selector(): preselect_task;

    /**
     * Returns the actual question or an error.
     *
     * @return result
     */
    public function select_question(): result {
        $selector = $this->get_selector();
        return $selector->run($this->context, fn ($c) => 'never_called');
    }

    /**
     * Returns the translated description of this strategy
     *
     * @return string
     */
    public function get_description(): string {

        $classname = get_class($this);

        $parts = explode('\\', $classname);
        $classname = array_pop($parts);
        return get_string($classname, 'local_catquiz');
    }

    /**
     * Strategy specific way of returning the next testitem.
     *
     * @param array $context
     *
     * @return result
     */
    public function return_next_testitem(array $context): result {
        $this->context = $context;
        $maxtime = $context['progress']->get_starttime() + $context['max_attempttime_in_sec'];
        if (time() > $maxtime) {
            // The 'endtime' holds the last timepoint that is relevant for the
            // result. So if a student tries to answer a question after the
            // time limit, this value is set to the time limit (starttime + max
            // attempt time).
            $this->cache->set('endtime', $maxtime);
            $this->cache->set('catquizerror', status::EXCEEDED_MAX_ATTEMPT_TIME);
            return result::err(status::EXCEEDED_MAX_ATTEMPT_TIME);
        }

        try {
            $this->check_item_params();
        } catch (Exception $e) {
            return result::err($e->getMessage());
        }

        // If checkbreak returns a value other than null, it is the question
        // that we should display again after a page reload.
        $checkbreakres = $this->check_break();
        if ($checkbreakres->unwrap()) {
            return $checkbreakres;
        }

        $res = $this->check_page_reload();
        if ($res->unwrap()) {
            return $res;
        }

        // Core methods called in every strategy.
        $res = $this->update_personability()
            ->and_then(fn () => $this->first_question_selector())
            ->or_else(fn ($res) => $this->after_error($res));

        $val = $res->unwrap();
        // If the result contains a single object, this is the question to be returned.
        // Othewise, it contains the updated $context array with the ability and standarderror set in such a way, that the
        // teststrategy will return the correct question (e.g. the question corresponding to mean ability of all students).
        if (is_object($val)) {
            return $res;
        }

        try {
            $this->add_scale_standarderror()
                ->and_then(fn () => $this->maximumquestionscheck())
                ->and_then(fn () => $this->removeplayedquestions())
                ->and_then(fn () => $this->noremainingquestions())
                ->and_then(fn () => $this->fisherinformation())
                ->or_else(fn($res) => $this->after_error($res))
                ->expect();
        } catch (Exception $e) {
            return $this->result;
        }

        $res = $this->maybereturnpilot();
        $val = $res->unwrap();
        // If the value is an object, it is the pilot question that should be returned.
        if (is_object($val)) {
            return $res;
        }

        try {
            $selectedquestion = $this->remove_uncalculated()
                ->and_then(fn() => $this->mayberemovescale())
                ->and_then(fn() => $this->last_time_played_penalty())
                ->and_then(fn() => $this->filterbystandarderror())
                ->or_else(fn($res) => $this->after_error($res))
                ->expect()
                ->and_then(fn() => $this->filterbytestinfo())
                ->and_then(fn() => $this->filterbyquestionsperscale())
                ->and_then(fn() => $this->select_question())
                ->and_then(
                    function ($res) {
                        $this->progress->save();
                        $this->update_attemptfeedback($this->context);
                        return $res;
                    }
                )
                ->or_else(fn ($res) => $this->after_error($res))
                ->expect()
                ->unwrap();
        } catch (Exception $e) {
            return $this->result;
        }

        if (!$selectedquestion) {
            return result::err();
        }

        $this->progress
            ->add_playedquestion($selectedquestion)
            ->save();

        catscale::update_testitem(
            $context['contextid'],
            $selectedquestion,
            $context['catscaleid'],
            $context['includesubscales'],
            $context['progress']->get_selected_subscales()
        );
        return result::ok($selectedquestion);
    }

    /**
     * Executes steps that are always performed after an error
     *
     * @param result $result
     *
     * @return result
     */
    private function after_error(result $result) {
        $this->progress->save();
        $this->update_attemptfeedback($this->context);
        $this->cache->set('endtime', time());
        $this->cache->set('catquizerror', $result->get_status());
        $this->result = $result;
        return $result;
    }

    /**
     * If true, the first question selector is called before updating the ability.
     *
     * Quickfix, could probabily be removed.
     *
     * @return bool
     */
    protected function pre_check_first_question_selector(): bool {
        return false;
    }

    /**
     * Helper method to update attempt feedback data
     *
     * @param mixed $context
     *
     * @return void
     *
     * @throws coding_exception
     * @throws Exception
     * @throws dml_exception
     */
    private function update_attemptfeedback($context) {
        if (getenv('CATQUIZ_TESTING_SKIP_FEEDBACK')) {
            return;
        }

        // Do not update feedback data if the page was reloaded.
        if (
            $this->progress->get_ignore_last_response()
            || (!$this->progress->is_first_question()
                && !$this->progress->has_new_response()
                && !$this->progress->get_force_new_question()
            )
        ) {
            return;
        }

        $attemptfeedback = new attemptfeedback($context['attemptid'], $context['contextid']);

        $attemptfeedback->update_feedbackdata($context);
    }

    /**
     * Retrieves all the available testitems from the current scale.
     *
     * @param int  $catscaleid
     * @param bool $includesubscales
     *
     * @return array
     */
    public function get_all_available_testitems(int $catscaleid, bool $includesubscales = false): array {

        $catscale = new catscale($catscaleid);

        return $catscale->get_testitems($this->catcontextid, $includesubscales);
    }

    /**
     * Set catscale id.
     *
     * @param int $scaleid
     *
     * @return self
     */
    public function set_scale(int $scaleid) {
        $this->scaleid = $scaleid;
        return $this;
    }

    /**
     * Set the CAT context id
     *
     * @param int $catcontextid
     *
     * @return $this
     */
    public function set_catcontextid(int $catcontextid) {
        $this->catcontextid = $catcontextid;
        return $this;
    }

    /**
     * Get feedback generators.
     *
     * @param feedbacksettings|null $feedbacksettings
     *
     * @return array
     */
    abstract public function get_feedbackgenerators(?feedbacksettings $feedbacksettings): array;

    /**
     * Check defined settings and apply specific settings strategy.
     *
     * @param feedbacksettings $feedbacksettings
     */
    abstract public function apply_feedbacksettings(feedbacksettings $feedbacksettings);

    /**
     * Adapt personabilites array: add excluded, error and primary keys in case these cases apply.
     *
     * @param feedbacksettings $feedbacksettings
     * @param array $personabilities
     * @param array $feedbackdata
     * @param int $catscaleid
     * @param bool $feedbackonlyfordefinedscaleid
     *
     * @return array
     *
     */
    abstract public function select_scales_for_report(
        feedbacksettings $feedbacksettings,
        array $personabilities,
        array $feedbackdata,
        int $catscaleid = 0,
        bool $feedbackonlyfordefinedscaleid = false
    ): array;

    /**
     * Checks if there are item params for the given combination of scale and context
     */
    protected function check_item_params() {
        $selectedscales = [$this->context['catscaleid'], ...$this->context['progress']->get_selected_subscales()];
        foreach ($selectedscales as $catscaleid) {
            $catscalecontext = catscale::get_context_id($catscaleid);
            $catscaleids = [
                $catscaleid,
                ...catscale::get_subscale_ids($catscaleid),
            ];
            $itemparamlists = [];
            foreach (array_keys(model_strategy::get_installed_models()) as $model) {
                $itemparamlists[$model] = model_item_param_list::get(
                    $catscalecontext,
                    $model,
                    $catscaleids
                )->count();
            }
            if (array_sum($itemparamlists) === 0) {
                $this->context['progress']->drop_scale($catscaleid);
                unset($selectedscales[array_search($catscaleid, $selectedscales)]);
            }
        }

        // If there are no active scales left, show a message that the quiz can not be started.
        if (!$selectedscales) {
                throw new Exception(status::ERROR_NO_ITEMS);
        }
    }

    /**
     * Checks if it took the user too long to answer the last question.
     *
     * If so, the user is forced to take a break and redirected to a page that shows
     * that information.
     *
     * @return result
     */
    protected function check_break(): result {
        $this->progress = $this->context['progress'];
        $now = time();
        $lastquestion = $this->progress->get_last_question();

        $lastquestionreturntime = $lastquestion->userlastattempttime ?? false;
        if (!$lastquestionreturntime || $now - $lastquestionreturntime <= $this->context['max_itemtime_in_sec']) {
            if (!$this->progress->page_was_reloaded()) {
                return result::ok();
            }
            return result::ok($lastquestion);
        }

        // If we are at this point, it means the maximum time was exceeded.
        // If the session is not the same as when the quiz was started, ignore
        // that last question and present a new one.
        if (!$this->progress->check_session()) {
            $this->progress->set_current_session()
                ->exclude_question($lastquestion->id)
                ->force_new_question()
                ->set_ignore_last_response(true);
            unset($this->context['questions'][$lastquestion->id]);
            return result::ok();
        }

        // If the session is the same, mark the last question as failed if the page was reloaded.
        if ($this->progress->page_was_reloaded()) {
            catquiz::mark_last_question_failed($this->progress->get_usage_id());
            $this->progress
                ->add_playedquestion($lastquestion)
                ->mark_lastquestion_failed()
                ->save();
            redirect(
                new moodle_url(
                    '/mod/adaptivequiz/attempt.php',
                    [
                        'cmid' => required_param('cmid', PARAM_INT),
                    ]
                )
            );
        }

        // If the page was NOT reloaded but the timeout was exceeded, we can not
        // do anything here because it is not possible to grade a response as
        // wrong in hindsight.
        return result::ok();
    }

    /**
     * Checks if we have a new response. If not, presents the previous question again.
     *
     * @return result
     */
    protected function check_page_reload(): result {
        $this->progress = $this->context['progress'];
        if (
            ($this->progress->is_first_question() && !$this->progress->get_last_question())
            || $this->progress->has_new_response()
            || $this->progress->get_force_new_question()
        ) {
            return result::ok(null);
        }

        return result::ok($this->progress->get_last_question());
    }

    /**
     * Update the person ability of the user taking the quiz.
     *
     * @return result
     */
    protected function update_personability(): result {
        $updateabilitytask = new updatepersonability();
        // When this is called for running tests, use the testing class.
        if (getenv('USE_TESTING_CLASS_FOR')) {
            $updateabilitytask = new updatepersonability_testing();
        }
        return $updateabilitytask->run($this->context);
    }

    /**
     * Add scale standarderror
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     *
     * @return result
     */
    protected function add_scale_standarderror(): result {
        $addscalestderrtask = new addscalestandarderror();
        return $addscalestderrtask->run($this->context);
    }

    /**
     * Maximum questions check
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     *
     * @return result
     */
    protected function maximumquestionscheck(): result {
        $maximumquestionscheck = new maximumquestionscheck();
        return $maximumquestionscheck->run($this->context);
    }

    /**
     * Remove played questions
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     *
     * @return result
     */
    protected function removeplayedquestions(): result {
        $removeplayedquestions = new removeplayedquestions();
        return $removeplayedquestions->run($this->context);
    }

    /**
     * Check for no remaining questions
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     *
     * @return result
     */
    protected function noremainingquestions(): result {
        $noremainingquestions = new noremainingquestions();
        return $noremainingquestions->run($this->context);
    }

    /**
     * Check if scales should be removed.
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     *
     * @return result
     */
    protected function mayberemovescale(): result {
        $mayberemovescale = new mayberemovescale();
        return $mayberemovescale->run($this->context);
    }

    /**
     * Calculate Fisher information
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     *
     * @return result
     */
    protected function fisherinformation(): result {
        $fisherinformation = new fisherinformation();
        return $fisherinformation->run($this->context);
    }

    /**
     * Maybe return a pilot question
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     *
     * @return result
     */
    protected function maybereturnpilot(): result {
        $maybereturnpilot = new maybe_return_pilot();
        if (getenv('USE_TESTING_CLASS_FOR')) {
            $maybereturnpilot = new maybe_return_pilot_testing();
        }
        return $maybereturnpilot->run($this->context);
    }

    /**
     * First question selector
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     *
     * @return result
     */
    protected function first_question_selector(): result {
        $firstquestionselector = new firstquestionselector();
        return $firstquestionselector->run($this->context);
    }

    /**
     * Adds last-time-played penalty
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     *
     * @return result
     */
    protected function last_time_played_penalty(): result {
        $lasttimeplayedpenalty = new lasttimeplayedpenalty();
        return $lasttimeplayedpenalty->run($this->context);
    }

    /**
     * Removes questions for which no item parameters were calculated yet
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     *
     * @return result
     */
    protected function remove_uncalculated(): result {
        $removeuncalculated = new remove_uncalculated();
        return $removeuncalculated->run($this->context);
    }

    /**
     * Removes played questions from the context
     *
     * @return result
     */
    protected function remove_played(): result {
        return (new removeplayedquestions())->run($this->context);
    }

    /**
     * Filter by standarderror
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     */
    protected function filterbystandarderror(): result {
        $filterbystandarderror = new filterbystandarderror();
        return $filterbystandarderror->run($this->context);
    }

    /**
     * Filter by test info
     *
     * Now, this is just a wrapper to call the respective pre-select task.
     */
    protected function filterbytestinfo(): result {
        $filterbytestinfo = new filterbytestinfo();
        return $filterbytestinfo->run($this->context);
    }

    /**
     * Filter by questions per scale
     *
     * By default does nothing
     *
     * @return result
     */
    protected function filterbyquestionsperscale(): result {
        return result::ok($this->context);
    }
}
