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
 * Class checkbreak.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\catquiz;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;
use local_catquiz\wb_middleware;
use moodle_url;

/**
 * Checks if it took the user too long to answer the last question.
 *
 * If so, the user is forced to take a break and redirected to a page that shows
 * that information.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class checkbreak extends preselect_task implements wb_middleware {

    /**
     * @var progress $progress
     */
    private progress $progress;

    /**
     * Run.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array &$context, callable $next): result {
        $this->progress = $context['progress'];
        $now = time();
        if ($this->progress->break_completed()) {
            return $next($context);
        }
        if ($this->progress->has_break()) {
            $breakinfourl = $this->get_breakinfourl($context, $this->progress->get_forced_break_end());
            redirect($breakinfourl);
        }

        $lastquestionreturntime = $this->progress->get_last_question()->userlastattempttime;
        if (!$lastquestionreturntime || $now - $lastquestionreturntime <= $context['maxtimeperquestion']) {
            return $next($context);
        }

        // If we are at this point, it means the maximum time was exceeded.
        // Force a new question.
        $this->progress->force_new_question();

        // If the session is not the same as when the quiz was started, just ignore that last question.
        if (!$this->progress->check_session()) {
            $this->progress->exclude_question($this->progress->get_last_question()->id);
            return $next($context);
        }

        // If the session is the same, mark the last question as failed.
        catquiz::mark_question_failed($this->progress->get_last_question());
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
            'breakduration',
            'breakinfourl',
            'maxtimeperquestion',
            'progress',
        ];
    }

    /**
     * Gets breakinfo url.
     *
     * @param mixed $context
     * @param mixed $forcedbreakend
     *
     * @return mixed
     *
     */
    private function get_breakinfourl($context, $forcedbreakend) {
        return new moodle_url(
            $context['breakinfourl'],
            [
                'cmid' => $_REQUEST['cmid'],
                'breakend' => usertime($forcedbreakend),
            ]
        );
    }
}
