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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;

/**
 * Test strategy maximumquestionscheck.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class maximumquestionscheck extends preselect_task {
    /**
     * Run preselect task.
     *
     * @param array $context
     *
     * @return result
     *
     */
    public function run(array &$context): result {
        $maxquestions = (int) $context['maximumquestions'];
        if ($maxquestions === -1) {
            return result::ok($context);
        }

        /* The configured test length means ANSWERED productive items. Neither of
           the two counters used before is authoritative for that:
           - `questionsattempted` lives on the adaptivequiz attempt record and can
             drift across a resume (the pending item is re-rendered uncounted);
           - `playedquestions` counts displayed items, and progress::load() removes
             the still unanswered last question from it on a resume.
           Counting the responses avoids both special cases, so the attempt stops
           after exactly the configured number of answers - never one item later. */
        if (isset($context['progress'])) {
            $answered = $context['progress']->get_num_answered_productive_questions();
        } else {
            // Legacy fallback for contexts that carry no progress.
            $answered = (int) $context['questionsattempted'];
        }

        if ($answered >= $maxquestions) {
            return result::err(status::ERROR_REACHED_MAXIMUM_QUESTIONS);
        }

        return result::ok($context);
    }
}
