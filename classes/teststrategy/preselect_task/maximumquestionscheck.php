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
 * Class maximumquestionscheck.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\catcontext;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;
use local_catquiz\wb_middleware;

/**
 * Test strategy maximumquestionscheck.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class maximumquestionscheck extends preselect_task implements wb_middleware {

    /**
     * @var progress $progress
     */
    private progress $progress;

    /**
     * Run preselect task.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array &$context, callable $next): result {
        $this->progress = $context['progress'];

        $maxquestions = $context['maximumquestions'];
        if (($maxquestions != -1) && ($context['questionsattempted'] >= $maxquestions)) {
            // Save the last response so that we can display it as feedback.
            $lastquestion = $this->progress->get_last_question();
            $lastresponse = catcontext::getresponsedatafromdb(
                $context['contextid'],
                [$lastquestion->catscaleid],
                $lastquestion->id,
                $context['userid']
            );
            // TODO: Error handling if no question was answered.
            $context['lastresponse'] = $lastresponse[$context['userid']]['component'][$lastquestion->id];

            // Update the person ability and then end the quiz.
            $next = fn () => result::err(status::ERROR_REACHED_MAXIMUM_QUESTIONS);
            $updatepersonability = new updatepersonability();
            return $updatepersonability->process($context, $next);
        }

        return $next($context);
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'questionsattempted',
            'maximumquestions',
            'progress',
        ];
    }
}
