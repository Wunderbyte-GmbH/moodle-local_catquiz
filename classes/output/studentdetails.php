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
     * Constructor.
     *
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
        $catmodelinfo = new catmodel_info();
        list(, $personparams) = $catmodelinfo->get_context_parameters(1); // TODO dynamic context?
        $selectedmodel = reset($personparams);
        $ability = get_string('personabilitiesnodata', 'local_catquiz');
        if (isset($selectedmodel[$this->studentid])) {
            $studentparam = $selectedmodel[$this->studentid];
            $ability = $studentparam->get_ability();
        }

        global $DB;

        $users = user_get_users_by_id([$this->studentid]);
        $user = reset($users);

        $courses = enrol_get_all_users_courses($user->id);
        $displaycourses = [];
        foreach ($courses as $course) {
            $displaycourses = (array)$course;
        }

        list ($sql, $params) = catquiz::get_sql_for_questions_answered([], [], [$this->studentid]);
        $numberofanswers = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_correct([], [], [$this->studentid]);
        $numberofanswerscorrect = $DB->count_records_sql($sql, $params);
        list ($sql, $params) = catquiz::get_sql_for_questions_answered_incorrect([], [], [$this->studentid]);
        $numberofanswersincorrect = $DB->count_records_sql($sql, $params);

        // Getting the values for the last access and comparing to now.
        if (isset($user->lastaccess)) {
            $datedifference = floor((usertime(time()) - $user->lastaccess) / 60 / 60 / 24);
            if ($datedifference >= 1) {
                $differencestring = get_string('daysago', 'local_catquiz', $datedifference);
            } else {
                $differencestring
                    = get_string('hoursago', 'local_catquiz', floor((usertime(time()) - $user->lastaccess) / 60 / 60));
            }
            $datestring = date('D, j F Y, g:i a', $user->lastaccess) . ' ('. $differencestring . ')';
        } else {
            $datestring = get_string('noaccessyet', 'local_catquiz');
        }

        return [
            'user' => (array)$user,
            'name' => get_string('name'),
            'emailtitle' => get_string('email'),
            'userdetails' => get_string('userdetails'),
            'enroledcoursestitle' => get_string('enroled_courses', 'local_catquiz'),
            'courses' => $displaycourses,
            'cards' => [
                [
                    'title' => get_string('personability', 'local_catquiz'),
                    'body' => $ability,
                ],
                [
                    'title' => get_string('lastaccess'),
                    'body' => $datestring,
                ],
            ],
            'statstitle' => get_string('questionresults', 'local_catquiz'),
            'stats' => [
                [
                    'numberofanswerstitle' => get_string('numberofanswers', 'local_catquiz'),
                    'numberofanswerscorrecttitle' => get_string('numberofanswerscorrect', 'local_catquiz'),
                    'numberofanswersincorrecttitle' => get_string('numberofanswersincorrect', 'local_catquiz'),
                    'numberofanswers' => $numberofanswers,
                    'numberofanswerscorrect' => $numberofanswerscorrect,
                    'numberofanswersincorrect' => $numberofanswersincorrect,
                ],
            ],
        ];
    }

    /**
     * Export for template.
     *
     * @param \renderer_base $output
     * @return array
     *
     */
    public function export_for_template(\renderer_base $output): array {

        return $this->render_studentstats();
    }
}
