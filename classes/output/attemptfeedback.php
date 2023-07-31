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

namespace local_catquiz\output;

use local_catquiz\catquiz;
use local_catquiz\teststrategy\info;
use templatable;
use renderable;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attemptfeedback implements renderable, templatable
{

    /**
     * @var ?int
     */
    public int $attemptid;

    /**
     * @var ?int
     */
    public int $contextid;

    /**
     * @var ?int
     */
    public int $catscaleid;

    public int $teststrategy;

    /**
     * @param int $attemptid
     * @param int $contextid
     */
    public function __construct(int $attemptid, int $contextid = 0, int $catscaleid = 0)
    {
        global $USER;
        if ($attemptid === 0) {
            // This can still return nothing. In that case, we show a message that the user has no attempts yet
            if (!$attemptid = catquiz::get_last_user_attemptid($USER->id)) {
                return;
            }
        }
        $this->attemptid = $attemptid;


        if (!$testenvironment = catquiz::get_testenvironment_by_attemptid($attemptid)) {
            return;
        }

        $settings = json_decode($testenvironment->json);
        $this->teststrategy = intval($settings->catquiz_selectteststrategy);

        if ($contextid === 0) {
            // Get the contextid from the attempt
            $contextid = intval($settings->catquiz_catcontext);
        }
        $this->contextid = $contextid;

        if ($catscaleid === 0) {
            $catscaleid = intval($settings->catquiz_catscaleid);
        }
        $this->catscaleid = $catscaleid;
    }

    private function render_question_stats(int $attemptid)
    {
        // 2. If an attemptid is given and belongs to the current user (or the user has permissions to see it), return that one
        $attempt = catquiz::get_attempt_statistics($attemptid);
        if ($attempt) {
            return [
                'gradedright' => $attempt['gradedright']->count ?? 0,
                'gradedwrong' => $attempt['gradedwrong']->count ?? 0,
                'gradedpartial' => $attempt['gradedpartial']->count ?? 0,
            ];
        }

        return get_string('attemptfeedbacknotavailable', 'local_catquiz');
    }

    private function render_person_ability() {
        global $USER;
        if (!$this->contextid || !$this->catscaleid) {
            return get_string('notavailable', 'core');
        }
        $ability = catquiz::get_person_ability($USER->id, $this->contextid, $this->catscaleid);
        return $ability->ability;
    }

    private function render_strategy_feedback() {
        if (!$this->teststrategy) {
            return '';
        }

        $available_teststrategies = info::return_available_strategies();
        $filtered_strategies = array_filter(
            $available_teststrategies,
            fn ($strategy) => $strategy->id === $this->teststrategy
        );

        if (!$attempt_strategy = reset($filtered_strategies)) {
            return '';
        }

        return $attempt_strategy::attempt_feedback();
    }

    public function export_for_template(\renderer_base $output): array
    {
        return [
            'stats' => $this->render_question_stats($this->attemptid),
            'ability' => $this->render_person_ability(),
            'strategy_feedback' => $this->render_strategy_feedback(),
        ];
    }
}
