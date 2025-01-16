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
 * Class numberofgeneralattempts.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;

/**
 * Adds a `numberofgeneralattempts` property to each question
 *
 * This information can be used to update the score, so that eventually all
 * questions will have a similar number of attempts.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class numberofgeneralattempts extends preselect_task {

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
        $records = $this->getquestionswithattemptscount($context);

        $maxattempts = 0;
        foreach ($context['questions'] as $id => &$question) {
            $attempts = array_key_exists($id, $records) ? intval($records[$id]->count) : null;
            if ($attempts > $maxattempts) {
                $maxattempts = $attempts;
            }
            $question->numberofgeneralattempts = $attempts;
        }
        $context['generalnumberofattempts_max'] = $maxattempts;

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
            'questions',
        ];
    }

    /**
     * Can be overwritten in the _testing class to prevent access to the DB.
     * @param mixed $context
     * @return array
     */
    protected function getquestionswithattemptscount($context): array {
        global $DB;
        $sql = "SELECT questionid, COUNT(*) AS count
                FROM {question_attempts}
                GROUP BY questionid";

        return $DB->get_records_sql($sql);
    }
}
