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

use local_catquiz\catmodel_info;
use local_catquiz\catquiz;
use templatable;
use renderable;

/**
 * Renderable class for student details
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Szkiba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class studentdetails implements renderable, templatable {

    /**
     * @var int
     */
    private int $studentid;

    /**
     * @param int $studentid
     */
    public function __construct(int $studentid) {
        $this->studentid = $studentid;
    }

    /**
     * Render the student details
     *
     * @return array
     */
    private function render_studentstats() {

        // Get the ability. At the moment there can be different abilities for each model.
        // This might change later. For now, just take the ability of the first model.
        $catmodel_info = new catmodel_info();
        list(,$person_params) = $catmodel_info->get_context_parameters(1); //TODO dynamic context?
        $selected_model = reset($person_params);
        $student_param = $selected_model[$this->studentid];
        $ability = get_string('personabilitiesnodata', 'local_catquiz');
        if ($student_param) {
            $ability = $student_param->get_ability();
        }

        global $DB;

        list ($sql, $params) = catquiz::get_sql_for_questions_answered([], [], [$this->studentid]);
        $numberofanswers = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_correct([], [], [$this->studentid]);
        $numberofanswerscorrect = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_incorrect([], [], [$this->studentid]);
        $numberofanswersincorrect = $DB->count_records_sql($sql, $params);

        return [
            [
                'title' => get_string('numberofanswers', 'local_catquiz'),
                'body' => $numberofanswers,
            ],
            [
                'title' => get_string('personability', 'local_catquiz'),
                'body' => $ability,
            ],
            [
                'title' => get_string('numberofanswerscorrect', 'local_catquiz'),
                'body' => $numberofanswerscorrect,
            ],
            [
                'title' => get_string('numberofanswersincorrect', 'local_catquiz'),
                'body' => $numberofanswersincorrect,
            ],
        ];
    }

    /**
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        return [
            'statcards' => $this->render_studentstats(),
        ];
    }
}
