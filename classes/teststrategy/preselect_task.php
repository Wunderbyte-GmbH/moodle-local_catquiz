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
 * Abstract class preselect_task.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy;

use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\wb_middleware;

/**
 * Base class for a pre-select task.
 *
 * Classes that extend this class are executed in order to select a question.
 *
 * To select the next question, we execute a number of pre-select tasks. Each of
 * those tasks will be passed a $context object with required data, such as the
 * list of questions, person ability, etc. Usually the last task will calculate
 * a final score for each question based on the data added by the previous tasks
 * and return a question inside a `result` with status `ok`.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class preselect_task implements wb_middleware {

    /**
     *
     * @var array|null $context
     */
    protected ?array $context;

    /**
     * The next task
     *
     * @var callable
     */
    protected $nexttask;

    /**
     * Process test strategy
     *
     * @param array $context
     * @param callable $nexttask
     *
     * @return result
     *
     */
    public function process(array &$context, callable $nexttask): result {
        foreach ($this->get_required_context_keys() as $key) {
            if (!array_key_exists($key, $context)) {
                return result::err(status::ERROR_FETCH_NEXT_QUESTION);
            }
        }

        $context['lastmiddleware'] = str_replace(__NAMESPACE__ . '\\preselect_task\\', '', get_class($this));
        $this->context = $context;
        $this->nexttask = $nexttask;
        return $this->run($context, $nexttask);
    }

    /**
     * This is the function in which the $context can be modified.
     *
     * To return early and skip the rest of the middleware chain, return a result directly.
     * Otherwise, call $next($context) to let the next middleware do its work.
     *
     * @param array $context The input that can be modified
     * @param callable $next Callable that calls the next middleware
     * @return result
     */
    abstract public function run(array &$context, callable $next): result;

    /**
     * If a middleware requires a specific key to be available in the $context
     * input array, it can be specified here.
     *
     * @return array
     */
    abstract public function get_required_context_keys(): array;
}
