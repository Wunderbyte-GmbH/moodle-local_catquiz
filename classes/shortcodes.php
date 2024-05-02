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
use local_catquiz\output\catquizstatistics;

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
        global $OUTPUT, $COURSE, $USER, $DB;

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
                throw new \moodle_exception("Can not read attempt data");
            }
            $strategyid = $attemptdata->teststrategy;
            $feedbacksettings = new feedbacksettings($strategyid);

            $attemptfeedback = new attemptfeedback($record->attemptid, $record->contextid, $feedbacksettings);
            try {
                $feedback = $attemptfeedback->get_feedback_for_attempt() ?? "";
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
        if (!array_key_exists('catscaleid', $args)) {
            return $OUTPUT->render_from_template(
                'local_catquiz/catscaleshortcodes/catscalestatistics',
                ['error' => 'Please provide the catscaleid Parameter']
            );
        }

        $catscaleid = $args['catscaleid'];
        $courseid = $args['courseid'] ?? null;
        $testid = $args['testid'] ?? null;
        $contextid = $args['contextid'] ?? null;
        $endtime = $args['endtime'] ?? null;
        $catquizstatistics = new catquizstatistics();
        $data = $catquizstatistics->render_attemptscounterchart(
            $catscaleid,
            $courseid,
            $testid,
            $contextid,
            $endtime
        );

        return $OUTPUT->render_from_template('local_catquiz/catscaleshortcodes/catscalestatistics', $data);
    }
}
