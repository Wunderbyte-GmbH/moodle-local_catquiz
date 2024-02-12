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
        $lastquestion = $this->progress->get_last_question();

        $lastquestionreturntime = $lastquestion->userlastattempttime ?? false;
        if (!$lastquestionreturntime || $now - $lastquestionreturntime <= $context['max_itemtime_in_sec']) {
            if (!$this->progress->page_was_reloaded()) {
                return $next($context);
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
            return $next($context);
        }

        // If the session is the same, mark the last question as failed if the page was reloaded.
        if ($this->progress->page_was_reloaded()) {
            catquiz::mark_question_failed($lastquestion->id, $this->progress->get_usage_id());
            $this->progress
                ->add_playedquestion($lastquestion)
                ->mark_lastquestion_failed()
                ->save();
            redirect(
                new moodle_url(
                    '/mod/adaptivequiz/attempt.php',
                    [
                        'cmid' => $_REQUEST['cmid'],
                    ]
                )
            );
        }

        // If the page was NOT reloaded but the timeout was exceeded, we can not
        // do anything here because it is not possible to grade a response as
        // wrong in hindsight.
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
            'max_itemtime_in_sec',
            'progress',
        ];
    }
}
