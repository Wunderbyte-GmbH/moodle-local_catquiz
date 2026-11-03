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
 * Class removeplayedquestions.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\attempt\administration_history;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;

/**
 * Class removeplayedquestions removes questions that were already shown to the user in the current quiz attempt.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class removeplayedquestions extends preselect_task {
    /**
     * @var progress
     */
    private progress $progress;

    /**
     * Run preselect task.
     *
     * @param array $context
     *
     * @return result
     *
     */
    public function run(array &$context): result {
        $this->progress = $context['progress'];

        /* Two sources, not one (issue #126). The played questions of the progress are the CAT's
           own record, but a question handed out on a path that did not register it was missing
           there and could be drawn again. The question usage is the host's record of what was
           actually administered, whatever the path; everything in it is excluded as well. */
        $excluded = array_keys($this->progress->get_playedquestions());
        try {
            $usageid = $this->progress->get_usage_id();
        } catch (\dml_missing_record_exception $e) {
            // No attempt of the host behind this progress: nothing administered to add.
            $usageid = null;
        }
        if ($usageid) {
            foreach (administration_history::for_usage((int) $usageid) as $entry) {
                $excluded[] = $entry->questionid;
            }
        }

        foreach (array_unique($excluded) as $qid) {
            if (array_key_exists($qid, $context['questions'])) {
                unset($context['questions'][$qid]);
            }
        }

        return result::ok($context);
    }
}
