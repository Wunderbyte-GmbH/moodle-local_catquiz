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

namespace local_catquiz\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_catquiz\catquiz;
use local_catquiz\local\access\context_resolver;
use local_catquiz\local\access\feedback_access;
use local_catquiz\local\statistics\attempt_population;
use local_catquiz\output\catquizstatistics;
use local_catquiz\shortcodes;

/**
 * Renders one chart of the statistics shortcode when its tab is shown (issue #85).
 *
 * The same access rule as the shortcode: the statistics show the results of other people, so the
 * viewer needs to see other users' results in the context of the scope (feedback_access with
 * context_resolver::for_statistics). The scope of a test is the test's own: its course and scale.
 * Read only and without the session lock - the charts of one page load side by side.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class render_statistics_chart extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scope' => new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course, 0 for every course', VALUE_DEFAULT, 0),
                'testid' => new external_value(PARAM_INT, 'Test: adaptivequiz.id, 0 for every test', VALUE_DEFAULT, 0),
                'scaleid' => new external_value(PARAM_INT, 'Scale'),
                'starttime' => new external_value(PARAM_INT, 'Lower bound of the end time, 0 for none', VALUE_DEFAULT, 0),
                'endtime' => new external_value(PARAM_INT, 'Upper bound of the end time, 0 for now', VALUE_DEFAULT, 0),
                'attempts' => new external_value(PARAM_ALPHA, 'all, first or last', VALUE_DEFAULT, attempt_population::ALL),
            ]),
            'chart' => new external_value(PARAM_ALPHANUMEXT, 'Name of the chart'),
        ]);
    }

    /**
     * Renders the chart.
     *
     * @param array $scope
     * @param string $chart
     * @return array
     */
    public static function execute(array $scope, string $chart): array {
        global $OUTPUT, $PAGE;

        ['scope' => $scope, 'chart' => $chart] = self::validate_parameters(
            self::execute_parameters(),
            ['scope' => $scope, 'chart' => $chart]
        );
        if (!array_key_exists($chart, catquizstatistics::CHARTS)) {
            throw new \invalid_parameter_exception('chart');
        }
        if (!in_array($scope['attempts'], [attempt_population::ALL, attempt_population::FIRST, attempt_population::LAST], true)) {
            throw new \invalid_parameter_exception('attempts');
        }

        $courseid = $scope['courseid'] ?: null;
        $testid = $scope['testid'] ?: null;
        $scaleid = (int) $scope['scaleid'];
        if ($testid) {
            // A test brings its course and scale; anything else would mix scopes the page never showed.
            $test = catquiz::get_test_by_component_id($testid);
            if (!$test) {
                throw new \invalid_parameter_exception('testid');
            }
            if ($courseid && (int) $courseid !== (int) $test->courseid) {
                throw new \invalid_parameter_exception('courseid');
            }
            $courseid = (int) $test->courseid;
            if (attempt_population::root_of($scaleid) !== (int) $test->catscaleid) {
                throw new \invalid_parameter_exception('scaleid');
            }
        }

        $context = context_resolver::for_statistics($courseid, $testid);
        // Logged in to the course, not the activity: a hidden test still has its statistics for those who
        // may see them, as on the page with the shortcode.
        $logincontext = $context->get_course_context(false) ?: \context_system::instance();
        self::validate_context($logincontext);
        if (!feedback_access::can_view_other_users($context)) {
            throw new \required_capability_exception($context, 'local/catquiz:view_users_feedback', 'nopermissions', '');
        }
        $PAGE->set_context($context);

        $statistics = new catquizstatistics(
            $courseid,
            $testid,
            $scaleid,
            $scope['endtime'] ?: null,
            $scope['starttime'] ?: null,
            $scope['attempts']
        );

        // Charts add their JavaScript to the page requirements; collect it for the client to run.
        $PAGE->set_pagelayout('embedded');
        $OUTPUT->doctype();
        $PAGE->start_collecting_javascript_requirements();
        $html = $OUTPUT->render_from_template(
            'local_catquiz/catscaleshortcodes/statisticschart',
            shortcodes::render_statistics_chart($statistics, $chart)
        );
        $javascript = $PAGE->requires->get_end_code();

        return ['html' => $html, 'javascript' => $javascript];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'The rendered chart'),
            'javascript' => new external_value(PARAM_RAW, 'JavaScript the chart needs'),
        ]);
    }
}
