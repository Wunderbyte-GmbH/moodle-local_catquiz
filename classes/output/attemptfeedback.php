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
     * @param int $attemptid
     * @param int $contextid
     */
    public function __construct(int $attemptid, int $contextid)
    {
        global $USER;
        if ($attemptid === 0) {
            // This can still return nothing. In that case, we show a message that the user has no attempts yet
            $attemptid = catquiz::get_last_user_attemptid($USER->id);
        }
        $this->attemptid = $attemptid;

        if ($this->attemptid && $contextid === 0) {
            // Get the contextid from the attempt
            $testenvironment = catquiz::get_testenvironment_by_attemptid($attemptid);
            if (!$testenvironment) {
                return;
            }
            $settings = json_decode($testenvironment->json);
            $contextid = intval($settings->catquiz_catcontext);
        }

        $this->contextid = $contextid;
    }

    private function render_question_stats(int $attemptid)
    {
        // 2. If an attemptid is given and belongs to the current user (or the user has permissions to see it), return that one
        $attempt = catquiz::get_attempt_statistics($attemptid);
        if ($attempt) {
            return [
                'gradedright' => $attempt['gradedright']->count ?? '-',
                'gradedwrong' => $attempt['gradedwrong']->count ?? '-',
                'gradedpartial' => $attempt['gradedpartial']->count ?? '-',
            ];
        }

        // 4. If there is not a single attempt, display a message with that information
        // TODO implement

        return get_string('attemptfeedback', 'local_catquiz');
    }

    private function render_person_ability(int $contextid) {
        global $USER;
        if (!$contextid) {
            return '-'; //TODO string that indicates we cant return a person ability
        }
        $ability = catquiz::get_person_ability($USER->id, $contextid);
        return $ability->ability;
    }

    public function export_for_template(\renderer_base $output): array
    {
        return [
            'stats' => $this->render_question_stats($this->attemptid),
            'ability' => $this->render_person_ability($this->contextid),
        ];
    }
}
