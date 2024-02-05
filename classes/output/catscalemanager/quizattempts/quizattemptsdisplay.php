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

namespace local_catquiz\output\catscalemanager\quizattempts;

use local_catquiz\catquiz;
use local_catquiz\output\attemptfeedback;
use local_catquiz\table\quizattempts_table;
use local_catquiz\teststrategy\info;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Szkiba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizattemptsdisplay {

    /**
     * Renders table.
     *
     * @return mixed
     *
     */
    public function render_table() {
        $table = new quizattempts_table('quizattemptstable');

        list($select, $from, $where, $filter, $params) = catquiz::return_sql_for_quizattempts();
        $table->set_filter_sql($select, $from, $where, $filter, $params);

        $columns = [
            'username',
            'timecreated',
            'timemodified',
            'catscale',
            'catcontext',
            'course',
            'component',
            'instance',
            'teststrategy',
            'status',
            'total_number_of_testitems',
            'number_of_testitems_used',
            'personability_before_attempt',
            'personability_after_attempt',
            'starttime',
            'endtime',
            'action',
        ];
        $table->define_columns($columns);
        $table->define_headers([
            get_string('username', 'core'),
            get_string('timecreated', 'core'),
            get_string('timemodified', 'local_catquiz'),
            get_string('catscale', 'local_catquiz'),
            get_string('catcontext', 'local_catquiz'),
            get_string('course', 'core'),
            get_string('component', 'local_catquiz'),
            get_string('instance', 'local_catquiz'),
            get_string('teststrategy', 'local_catquiz'),
            get_string('status', 'core'),
            get_string('totalnumberoftestitems', 'local_catquiz'),
            get_string('numberoftestitemsused', 'local_catquiz'),
            get_string('personabilitybeforeattempt', 'local_catquiz'),
            get_string('personabilityafterattempt', 'local_catquiz'),
            get_string('starttime', 'local_catquiz'),
            get_string('endtime', 'local_catquiz'),
            get_string('action', 'core'),
        ]);

        $teststrategyfilter = [
            'localizedname' => get_string('teststrategy', 'local_catquiz'),
        ];
        foreach (info::return_available_strategies() as $strategy) {
            $classname = substr(strrchr(get_class($strategy), '\\'), 1);
            $teststrategyfilter["$strategy->id"] = get_string($classname, 'local_catquiz');
        }

        $table->define_filtercolumns(
            [
                'username' => [
                    'localizedname' => get_string('username', 'core'),
                ],
                'component' => [
                    'localizedname' => get_string('component', 'local_catquiz'),
                ],
                'status' => [
                    'localizedname' => get_string('status'),
                ],
                'instance' => [
                    'localizedname' => get_string('instance', 'local_catquiz'),
                ],
                'teststrategy' => $teststrategyfilter,
                'course' => [
                    'localizedname' => get_string('course'),
                ],
                'catscale' => [
                    'localizedname' => get_string('catscale', 'local_catquiz'),
                ],
                'catcontext' => [
                    'localizedname' => get_string('catcontext', 'local_catquiz'),
                ],
            ]
        );

        $table->define_fulltextsearchcolumns([
            'username',
            'catscale',
            'catcontext',
        ]);

        $table->define_sortablecolumns($columns);

        $table->sort_default_column = 'timemodified';
        $table->sort_default_order = SORT_DESC;

        $table->define_cache('local_catquiz', 'quizattempts');

        $table->pageable(true);

        $table->stickyheader = false;
        $table->showcountlabel = true;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;
        $table->addcheckboxes = true;

        // TODO: lazyload.
        return $table->outhtml(10, true);
    }

    /**
     * Renders attempt details.
     *
     * @param int $attemptid
     *
     * @return mixed
     *
     */
    public function render_attempt_details(int $attemptid) {
        $attemptfeedback = new attemptfeedback($attemptid);
        $feedback = $attemptfeedback->get_feedback_for_attempt();
        return $feedback;
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_data_array(): array {

        $attemptid = optional_param('attemptid', 0, PARAM_INT);
        if ($attemptid) {
            return ['feedback' => $this->render_attempt_details($attemptid)];
        }

        return [
            'table' => $this->render_table(),
        ];
    }
}
