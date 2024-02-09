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
use coding_exception;
use Exception;
use dml_exception;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\local\result;
use local_catquiz\output\attemptfeedback;
use local_catquiz\teststrategy\info;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;
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
     * @var progress
     */
    protected progress $progress;

    /**
     * Instantioate parameters.
     */
    public function __construct() {
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $this->scoremodifiers = info::get_score_modifiers();
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
        foreach ($this->get_preselecttasks() as $modifier) {
            // When this is called for running tests, check if there is a
            // X_testing class and if so, use that one.
            if ($modifier == getenv('USE_TESTING_CLASS_FOR')) {
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
        $this->progress = $context['progress'];

        $this->update_attempdfeedback($context);

        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        if ($result->isErr()) {
            $cache->set('endtime', time());
            $cache->set('catquizerror', $result->get_status());
            return $result;
        }

        $selectedquestion = $result->unwrap();
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
            $context['includesubscales']
        );
        return result::ok($selectedquestion);
    }

    /**
     * Helper method to update attempt feedback data
     *
     * @param mixed $context
     * @return void
     * @throws coding_exception
     * @throws Exception
     * @throws dml_exception
     */
    private function update_attempdfeedback($context) {
        if (getenv('CATQUIZ_TESTING_SKIP_FEEDBACK')) {
            return;
        }

        // Do not update feedback data if the page was reloaded.
        if (!$this->progress->is_first_question()
            && !$this->progress->has_new_response()
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
}
