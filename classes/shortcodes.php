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
 * Shortcodes for local_catquiz
 *
 * @package local_catquiz
 * @subpackage db
 * @since Moodle 3.11
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use local_catquiz\data\dataapi;
use local_catquiz\output\attemptfeedback;
use local_catquiz\output\catscalemanager\quizattempts\quizattemptsdisplay;
use local_catquiz\teststrategy\feedbacksettings;
use context_course;
use dml_missing_record_exception;
use Exception;
use local_catquiz\output\catquizstatistics;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Deals with local_shortcodes regarding catquiz.
 */
class shortcodes {

    /**
     * Prints out list of catquiz attempts.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function allquizattempts($shortcode, $args, $content, $env, $next) {
        return (new quizattemptsdisplay())->render_table();
    }

    /**
     * Displays feedback of attempts
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function catquizfeedback($shortcode, $args, $content, $env, $next) {
        global $OUTPUT, $COURSE, $USER, $DB, $CFG;

        // Students get to see only feedback for their own attempts, teacher see all attempts of this course.
        $context = context_course::instance($COURSE->id);
        $capability = has_capability('local/catquiz:view_users_feedback', $context);

        if (!$capability) {
            $userid = $USER->id;
        }

        $courseid = optional_param('id', 0, PARAM_INT);
        $records = catquiz::return_data_from_attemptstable(
            intval($args['numberofattempts'] ?? 1),
            intval($args['instanceid'] ?? 0),
            intval($args['courseid'] ?? $courseid),
            intval($userid ?? -1)
            );
        if (!$records) {
            return get_string('attemptfeedbacknotyetavailable', 'local_catquiz');
        }
        $output = [
            'attempt' => [],
        ];

        foreach ($records as $record) {
            if (!$attemptdata = json_decode($record->json)) {
                if ($CFG->debug > 0) {
                    throw new \moodle_exception(sprintf('Can not read attempt data of attempt %d', $record->attemptid));
                } else {
                    continue;
                }
            }
            $strategyid = $attemptdata->teststrategy;
            $feedbacksettings = new feedbacksettings($strategyid);

            $attemptfeedback = new attemptfeedback($record->attemptid, $record->contextid, $feedbacksettings);
            try {
                $feedback = $attemptfeedback->get_feedback_for_attempt($record->json, $record->debug_info) ?? "";
            } catch (\Throwable $t) {
                $feedback = get_string('attemptfeedbacknotavailable', 'local_catquiz');
            }

            $timestamp = !empty($record->endtime) ? intval($record->endtime) : intval($record->timemodified);
            $timeofattempt = userdate($timestamp, get_string('strftimedatetime', 'core_langconfig'));
            if ($record->userid == $USER->id) {
                $headerstring = get_string(
                    'ownfeedbacksheader',
                    'local_catquiz',
                    $timeofattempt);
            } else if (isset($record->userid)) {
                $userrecord = $DB->get_record('user', ['id' => $record->userid], 'firstname, lastname', IGNORE_MISSING);

                $headerstring = get_string(
                    'userfeedbacksheader',
                    'local_catquiz',
                    [
                        'attemptid' => $record->attemptid,
                        'time' => $timeofattempt,
                        'firstname' => $userrecord->firstname,
                        'lastname' => $userrecord->lastname,
                        'userid' => $record->userid,

                    ]);
            } else {
                $headerstring = "";
            }

            $data = [
                'feedback' => $feedback,
                'header' => $headerstring,
                'attemptid' => $record->attemptid,
                'active' => empty($output['attempt']) ? true : false,
            ];
            $output['attempt'][] = $data;
        }
        return $OUTPUT->render_from_template('local_catquiz/feedback/collapsablefeedback', $output);
    }

    /**
     * Prints out list of catquiz attempts.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function catscalesoverview($shortcode, $args, $content, $env, $next) {
        global $OUTPUT;

        $catscaleandchildren = dataapi::get_catscale_and_children(0, true, [], true);
        foreach ($catscaleandchildren as $index => $catscale) {
            if ($catscale['depth'] > 0) {
                $catscaleandchildren[$index]['padding'] = $catscale['depth'] * 30;
                $catscaleandchildren[$index]['ischild'] = 1;
            }
        }
        $data = [
            'itemtree' => $catscaleandchildren,
        ];

        return $OUTPUT->render_from_template('local_catquiz/catscaleshortcodes/catscaleshortcodetable', $data);
    }

    /**
     * Prints catquiz statistics.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function catquizstatistics($shortcode, $args, $content, $env, $next) {
        global $OUTPUT;

        try {
            $populated = self::populate_arguments($args);
        } catch (\Exception $e) {
            $data = [
                'error' => $e->getMessage(),
            ];
            return $OUTPUT->render_from_template('local_catquiz/catscaleshortcodes/catscalestatistics', $data);
        }
        $courseid = $populated['course'];
        $globalscale = $populated['globalscale'];
        $testid = $populated['testid'];
        $endtime = $args['endtime'] ?? null;

        $heading = self::get_heading($courseid, $globalscale, $testid);

        try {
            $catquizstatistics = new catquizstatistics($courseid, $testid, $globalscale, $endtime);
        } catch (dml_missing_record_exception $e) {
            return $OUTPUT->render_from_template(
                'local_catquiz/catscaleshortcodes/catscalestatistics',
                ['error' => 'Can not find a test with the given testid']
            );
        } catch (\Exception $e) {
            return $OUTPUT->render_from_template(
                'local_catquiz/catscaleshortcodes/catscalestatistics',
                ['error' => $e->getMessage()]
            );
        }

        $data = [
            'heading' => $heading,
            'charttitle' => get_string('numberofattempts', 'local_catquiz'),
            'abilityprofilechart' => $catquizstatistics->render_abilityprofilechart(),
            'attemptspertimerangechart' => $catquizstatistics->render_attempts_per_timerange_chart(),
            'attemptsperpersonchart' => $catquizstatistics->render_attempts_per_person_chart(),
            'stackchart' => $catquizstatistics->render_attemptresultstackchart($globalscale),
            'detectedscales' => $catquizstatistics->render_detected_scales_chart(),
            'learningprogress' => $catquizstatistics->render_learning_progress(),
            'numresponsesbyusers' => $catquizstatistics->render_responses_by_users_chart(),
        ];

        return $OUTPUT->render_from_template('local_catquiz/catscaleshortcodes/catscalestatistics', $data);
    }

    /**
     * Populate shortcode arguments
     *
     * @param array $args
     * @return array
     * @throws Exception
     */
    private static function populate_arguments(array $args): array {
        if ($args['testid'] ?? null) {
            $cmid = $args['testid'];
            // The 'testid' is actually the cmid. But we want the id of our test instance.
            $testid = self::get_test_id($cmid);
            $test = catquiz::get_test_by_component_id($testid);
            $courseid = $test->courseid;
            if ($args['course'] ?? null && $args['course'] != $courseid) {
                throw new Exception("The testid is not in the given course");
            }
            $globalscale = $test->catscaleid;
            if ($globalscale && array_key_exists('globalscale', $args) && $args['globalscale'] != $globalscale) {
                throw new Exception("The testid is not using the given global scale");
            }
            return ['course' => $courseid, 'globalscale' => $globalscale, 'testid' => $testid];
        }

        $globalscale = $args['globalscale'] ?? null;
        $courseid = optional_param('id', $args['courseid'] ?? 0, PARAM_INT);
        if (!$globalscale) {
            if ($courseid == 0) {
                throw new Exception('Please provide either a "globalscale" or "course" parameter');
            }
            $globalscale = self::get_global_scale($courseid);
        }

        if ($courseid && ($args['scope'] ?? null != "all")) {
            return ['course' => $courseid, 'globalscale' => $globalscale, 'testid' => null];
        }
        if (!$courseid || ($courseid && $args['scope'] == "all")) {
            return ['course' => null,  'globalscale' => $globalscale, 'testid' => null];
        }
    }

    /**
     * Returns the global scale for the tests in a course
     *
     * @param int $courseid
     * @return int
     * @throws Exception
     */
    private static function get_global_scale(int $courseid) {
        // The cmid comes from the course_modules table.
        // In this table:
        // - The course matches the given courseid.
        // - The module matches the module number of adaptivequiz.
        // - The instance matches the id in the adaptivequiz table.

        // If there is only one CAT in that course, use the cmid of that course.
        if (!$cats = catquiz::get_tests_for_course($courseid)) {
            throw new Exception('no CAT tests for the given course'); // TODO: translate or handle.
        }

        if (count($cats) === 1) {
            // Just one course. We use the global scale of this one.
            $cat = reset($cats);
            $globalscale = json_decode($cat->json)->catquiz_catscales;
            return $globalscale;
        }

        foreach ($cats as $cat) {
            $globalscale = json_decode($cat->json)->catquiz_catscales;
            $globalscales[$globalscale] = true;
        }

        if (count($globalscales) === 1) {
            // All courses use the same global scale, so no problem.
            $first = reset($cats);
            $globalscale = json_decode($first->json)->catquiz_catscales;
            return $globalscale;
        }

        // Now there are multiple courses with different global scales. Throw exception to indicate that the user should
        // explicitely set a scale.
        throw new Exception(
            sprintf(
                'please select one of the following global scales: %s',
                implode(', ', array_keys($globalscales))
            )
        );
    }

    /**
     * Returns the CAT test id for the given cmid
     *
     * @param ?int $cmid
     * @return ?int
     */
    private static function get_test_id(?int $cmid) {
        if (!$cmid) {
            return null;
        }
        list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'adaptivequiz');
        return $cm->instance;
    }

    /**
     * Returns an array with strings for the shortcode heading
     *
     * @param ?int $courseid
     * @param ?int $scaleid
     * @param ?int $testid
     * @return array
     */
    private static function get_heading(?int $courseid, ?int $scaleid, ?int $testid) {
        $test = null;
        if ($testid) {
            $test = catquiz::get_test_by_component_id($testid);
        }
        if ($test) {
            $testname = json_decode($test->json)->name;
            list($course, $cm) = get_course_and_cm_from_instance($test->componentid, 'adaptivequiz');
            $testurl = new moodle_url(
                '/mod/adaptivequiz/view.php',
                ['id' => $cm->id]
            );
            $link = sprintf('<a href="%s">%s</a>', $testurl->out(), $testname);

            $scale = catscale::return_catscale_object($test->catscaleid);
            $h1 = get_string('catquizstatistics_h1_single', 'local_catquiz', $testname);
            $h2 = get_string('catquizstatistics_h2_single', 'local_catquiz', ['link' => $link, 'scale' => $scale->name]);
            return [
                'title' => $h1,
                'description' => $h2,
            ];
        }

        // If there is no testid given, we need at least a scale id.
        if (!$scaleid) {
            return;
        }

        $scale = catscale::return_catscale_object($scaleid);

        if ($courseid) {
            $tests = catquiz::get_tests_for_scale($courseid, $scaleid);
            $linkedcourses = array_map(function ($test) {
                $testname = json_decode($test->json)->name;
                list($course, $cm) = get_course_and_cm_from_instance($test->componentid, 'adaptivequiz');
                $testurl = new moodle_url(
                    '/mod/adaptivequiz/view.php',
                    ['id' => $cm->id]
                );
                $link = sprintf('<a href="%s">%s</a>', $testurl->out(), $testname);
                return $link;
            }, $tests);
            $h1 = get_string('catquizstatistics_h1_scale', 'local_catquiz', $scale->name);
            if (count($linkedcourses) > 1) {
                $h2 = get_string('catquizstatistics_h2_scale', 'local_catquiz', (object) [
                    'linkedcourses' => implode(', ', $linkedcourses),
                    'scale' => $scale->name,
                ]);
            } else {
                $link = reset($linkedcourses);
                $h2 = get_string('catquizstatistics_h2_single', 'local_catquiz', (object) [
                    'link' => $link,
                    'scale' => $scale->name,
                ]);
            }
            return [
                'title' => $h1,
                'description' => $h2,
            ];
        }

        // This is the global case.
        $h1 = get_string('catquizstatistics_h1_global', 'local_catquiz', $scale->name);
        $h2 = get_string('catquizstatistics_h2_global', 'local_catquiz', $scale->name);

        return [
            'title' => $h1,
            'description' => $h2,
        ];
    }
}
