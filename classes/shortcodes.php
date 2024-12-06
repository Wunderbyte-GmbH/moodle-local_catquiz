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

use Exception;
use context_course;
use dml_missing_record_exception;
use local_catquiz\data\dataapi;
use local_catquiz\output\catquizstatistics;
use local_catquiz\output\catscalemanager\quizattempts\quizattemptsdisplay;
use local_catquiz\teststrategy\feedback_helper;
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

        $context = context_course::instance($COURSE->id);
        $output = feedback_helper::get_feedback_data($args, $context, $USER, $COURSE, $DB, $CFG);

        if (isset($output['error'])) {
            return $output['error'];
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
        global $CFG, $OUTPUT;

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
        $starttime = $args['starttime'] ?? null;

        try {
            $heading = self::get_heading($courseid, $globalscale, $testid, $starttime, $endtime);
        } catch (\Exception $e) {
            return $OUTPUT->render_from_template(
                'local_catquiz/catscaleshortcodes/catscalestatistics',
                ['error' => $e->getMessage()]
            );
        }

        try {
            $catquizstatistics = new catquizstatistics($courseid, $testid, $globalscale, $endtime, $starttime);
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
        } catch (\Throwable $t) {
            $errormsg = 'Catquizstatistics can not be displayed due to an internal error';
            if ($CFG->debug > 0) {
                $errormsg = sprintf(
                    'Catquizstatistics can not be displayed due to an internal error: "%s" at %s:%d',
                    $t->getMessage(),
                    $t->getFile(),
                    $t->getLine()
                );
            }
            return $OUTPUT->render_from_template(
                'local_catquiz/catscaleshortcodes/catscalestatistics',
                ['error' => $errormsg]
            );
        }

        $data = [
            'heading' => $heading,
            'abilityprofilechart' => $catquizstatistics->render_abilityprofilechart(),
            'detectedscales' => $catquizstatistics->render_detected_scales_chart(),
            'attemptspertimerangechart' => $catquizstatistics->render_attempts_per_timerange_chart(),
            'attemptsperpersonchart' => $catquizstatistics->render_attempts_per_person_chart(),
            'learningprogress' => $catquizstatistics->render_learning_progress(),
            'exportbutton' => $catquizstatistics->render_export_button() ?: false,
            // This ID will be appended to the navigation tab links, so that
            // those links are unique for shortcodes with different arguments.
            'shortcodeid' => implode('-', $args),
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
        global $COURSE;
        if ($args['testid'] ?? null) {
            $cmid = $args['testid'];
            // The 'testid' is actually the cmid. But we want the id of our test instance.
            $testid = self::get_test_id($cmid);
            $test = catquiz::get_test_by_component_id($testid);
            $courseid = $test->courseid;
            if ($args['course'] ?? null && $args['course'] != $courseid) {
                throw new Exception(get_string('catquizstatistics_scale_course_conflict', 'local_catquiz'));
            }
            $globalscale = $test->catscaleid;
            if ($globalscale && array_key_exists('globalscale', $args) && $args['globalscale'] != $globalscale) {
                throw new Exception(get_string('catquizstatistics_scale_testid_conflict', 'local_catquiz'));
            }
            return ['course' => $courseid, 'globalscale' => $globalscale, 'testid' => $testid];
        }

        $globalscale = $args['globalscale'] ?? null;
        $currentcourseid = 0;
        if (isset($COURSE) && !empty($COURSE->id) && $COURSE->id > 1) {
            $currentcourseid = $COURSE->id;
        }
        $courseid = $args['courseid'] ?? $currentcourseid;
        if (!$globalscale) {
            if ($courseid == 0) {
                throw new Exception(get_string('catquizstatistics_askforparams', 'local_catquiz'));
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
            throw new Exception(get_string('catquizstatistics_nodataforcourse', 'local_catquiz'));
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
     * @param ?int $starttime
     * @param ?int $endtime
     * @return array
     */
    private static function get_heading(?int $courseid, ?int $scaleid, ?int $testid, ?int $starttime, ?int $endtime) {
        $test = null;
        if ($starttime && $endtime) {
            $start = userdate($starttime, get_string('strftimedatetime', 'core_langconfig'));
            $end = userdate($endtime, get_string('strftimedatetime', 'core_langconfig'));
            $timerangeaddition = get_string(
                'catquizstatistics_timerange_both',
                'local_catquiz',
                (object) ['starttime' => $start, 'endtime' => $end]);
        } else if ($starttime) {
            $start = userdate($starttime, get_string('strftimedatetime', 'core_langconfig'));
            $timerangeaddition = get_string('catquizstatistics_timerange_start', 'local_catquiz', (object) ['starttime' => $start]);
        } else if ($endtime) {
            $end = userdate($endtime, get_string('strftimedatetime', 'core_langconfig'));
            $timerangeaddition = get_string('catquizstatistics_timerange_end', 'local_catquiz', (object) ['endtime' => $end]);
        } else {
            $timerangeaddition = "";
        }
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
            $h2 .= ' ' . $timerangeaddition;
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
            $course = get_course($courseid);
            $linkedcourses = array_map(function ($test) {
                $testname = json_decode($test->json)->name;
                list($course, $cm) = get_course_and_cm_from_instance($test->componentid, 'adaptivequiz');
                $testurl = new moodle_url(
                    '/mod/adaptivequiz/view.php',
                    ['id' => $cm->id]
                );
                $link = feedback_helper::add_quotes(sprintf('<a href="%s">%s</a>', $testurl->out(), $testname));
                return $link;
            }, $tests);
            $h1 = get_string('catquizstatistics_h1_scale', 'local_catquiz', (object) [
                'scalename' => $scale->name,
                'coursename' => $course->fullname,
            ]);
            if (count($linkedcourses) > 1) {
                $h2 = get_string('catquizstatistics_h2_scale', 'local_catquiz', (object) [
                    'linkedcourses' => implode(', ', $linkedcourses),
                    'scale' => $scale->name,
                    'coursename' => $course->fullname,
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
                'description' => sprintf('%s %s', $h2, $timerangeaddition),
            ];
        }

        // This is the global case.
        $h1 = get_string('catquizstatistics_h1_global', 'local_catquiz', $scale->name);
        $h2 = get_string('catquizstatistics_h2_global', 'local_catquiz', $scale->name);
        $h2 .= ' ' . $timerangeaddition;

        return [
            'title' => $h1,
            'description' => $h2,
        ];
    }
}
