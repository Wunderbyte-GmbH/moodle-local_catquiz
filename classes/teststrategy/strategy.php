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
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy;

use cache;
use cache_session;
use local_catquiz\catcontext;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\info;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\preselect_task\updatepersonability;
use local_catquiz\wb_middleware_runner;
use moodle_exception;
use stdClass;

/**
 * Base class for test strategies.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class strategy {

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
     * @var array<preselect_task>
     */
    public array $scoremodifiers;

    /**
     * @var int $maximumquestions The maximum number of questions played per attempt.
     */
    public int $maximumquestions;

    /**
     * @var stdClass $lastquestion The previous question.
     */
    public stdClass $lastquestion;

    /**
     * @var int $userid The userid of the current user.
     */
    public int $userid;

    /**
     * @var cache_session $cache Holds data of the current quiz attempt.
     */
    public cache_session $cache;

    /**
     * Instantioate parameters.
     */
    public function __construct() {
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $this->scoremodifiers = info::get_score_modifiers();
    }

    public static function create_from_adaptivequiz(array $settings): self {
    }

    /**
     * Returns an array of score modifier classes
     *
     * The classes will be called in the given order to calculate the score of a question
     *
     * @return array
     */
    abstract public function get_preselecttasks(): array;

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
     * @return mixed
     *
     */
    public function return_next_testitem(array $context) {
        $this->cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $this->lastquestion = $this->cache->get('lastquestion') ?: null;

        if ($this->reached_max_questions()) {
            // Save the last response so that we can display it as feedback.
            $lastresponse = catcontext::getresponsedatafromdb(
                $context['contextid'],
                [$this->lastquestion->catscaleid],
                $this->lastquestion->id,
                $this->userid
            );
            // TODO: Error handling if no question was answered.
            //$context['lastresponse'] = $lastresponse[$context['userid']]['component'][$context['lastquestion']->id];

            // Update the person ability and then end the quiz.
            $next = fn () => result::err(status::ERROR_REACHED_MAXIMUM_QUESTIONS);
            (new updatepersonability())->run($context, $next);
            return $this->handle_result(result::err(status::ERROR_REACHED_MAXIMUM_QUESTIONS));
        }

        foreach ($this->get_preselecttasks() as $modifier) {
            // When this is called for running tests, check if there is a
            // X_testing class and if so, use that one.
            if (defined('IS_PHPUNIT_TEST') && IS_PHPUNIT_TEST === true) {
                $testingclass = sprintf('%s_testing', $modifier);
                if (array_key_exists($testingclass, $this->scoremodifiers)) {
                    $middlewares[] = $this->scoremodifiers[$testingclass];
                    continue;
                }
            }
            if (!array_key_exists($modifier, $this->scoremodifiers)) {
                throw new moodle_exception(
                    sprintf(
                        'Strategy requires a score modifier that is not available: %s',
                        $modifier
                    )
                );
            }
            $middlewares[] = $this->scoremodifiers[$modifier];
        }

        $result = wb_middleware_runner::run($middlewares, $context);

    }

    public function handle_result(result $result) {
        if ($result->isErr()) {
            $this->cache->set('stopreason', $result->get_status());
            $this->cache->set('endtime', time());
            return $result;
        }

        $selectedquestion = $result->unwrap();
        if (!$selectedquestion) {
            return result::err();
        }

        $now = time();
        $selectedquestion->lastattempttime = $now;
        $selectedquestion->userlastattempttime = $now;

        // Keep track of which question was selected.
        $playedquestions = $this->cache->get('playedquestions') ?: [];
        $playedquestions[$selectedquestion->id] = $selectedquestion;
        $this->cache->set('playedquestions', $playedquestions);
        $this->cache->set('isfirstquestionofattempt', false);
        $this->cache->set('lastquestionreturntime', $now);

        if (! empty($selectedquestion->is_pilot)) {
            $numpilotquestions = $this->cache->get('num_pilot_questions') ?: 0;
            $this->cache->set('num_pilot_questions', ++$numpilotquestions);
        }

        // Keep track of the questions played per scale.
        $playedquestionsperscale = $this->cache->get('playedquestionsperscale') ?: [];
        $updated = $this->update_playedquestionsperscale($selectedquestion, $playedquestionsperscale);
        $this->cache->set('playedquestionsperscale', $updated);

        $this->cache->set('lastquestion', $selectedquestion);

        catscale::update_testitem(
            $this->contextid,
            $selectedquestion,
            $this->catscaleid,
            $this->includesubscales
        );
        return result::ok($selectedquestion);

    }

    public function reached_max_questions(): bool {
        if ($this->maxquestions === -1) {
            return false;
        }
        if ($this->questionsattempted < $this->maxquestions) {
            return false;
        }

        return true;
    }

    /**
     * Retrieves all the available testitems from the current scale.
     *
     * @param int  $catscaleid
     * @param bool $includesubscales
     * @return array
     */
    public function get_all_available_testitems(int $catscaleid, bool $includesubscales = false):array {

        $catscale = new catscale($catscaleid);

        return $catscale->get_testitems($this->catcontextid, $includesubscales);

    }

    /**
     * Set catscale id.
     * @param int $scaleid
     * @return self
     */
    public function set_scale(int $scaleid) {
        $this->scaleid = $scaleid;
        return $this;
    }

    /**
     * Set the CAT context id
     * @param int $catcontextid
     * @return $this
     */
    public function set_catcontextid(int $catcontextid) {
        $this->catcontextid = $catcontextid;
        return $this;
    }

    /**
     * Get feedback generators.
     * @param feedbacksettings $feedbacksettings
     * @return array
     *
     */
    abstract public function get_feedbackgenerators(feedbacksettings $feedbacksettings): array;

    /**
     * Check defined settings and apply specific settings strategy.
     * @param feedbacksettings $feedbacksettings
     *
     */
    abstract public function apply_feedbacksettings(feedbacksettings $feedbacksettings);

    /**
     * Update played questions per scale.
     *
     * @param stdClass $selectedquestion
     * @param array $playedquestionsperscale
     *
     * @return array
     *
     */
    public function update_playedquestionsperscale(
        stdClass $selectedquestion,
        array $playedquestionsperscale = []
    ): array {
        if (!array_key_exists($selectedquestion->catscaleid, $playedquestionsperscale)) {
            $playedquestionsperscale[$selectedquestion->catscaleid] = [];
        }
        $playedquestionsperscale[$selectedquestion->catscaleid][] = $selectedquestion;
        return $playedquestionsperscale;
    }
}
