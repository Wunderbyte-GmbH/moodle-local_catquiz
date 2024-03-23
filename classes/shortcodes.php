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
        global $OUTPUT, $COURSE, $USER;

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

        // Get scaleid, it is possible to apply name of catscale, catscaleid or predefined strings (see switch).
        $primaryscale = $args['primaryscale'] ?? LOCAL_CATQUIZ_PRIMARYCATSCALE_DEFAULT;
        switch ($primaryscale) {
            case 'lowest':
                $primaryscale = LOCAL_CATQUIZ_PRIMARYCATSCALE_LOWEST;
                break;
            case 'strongest':
                $primaryscale = LOCAL_CATQUIZ_PRIMARYCATSCALE_STRONGEST;
                break;
            case 'highest':
                $primaryscale = LOCAL_CATQUIZ_PRIMARYCATSCALE_STRONGEST;
                break;
            case 'parent':
                $primaryscale = LOCAL_CATQUIZ_PRIMARYCATSCALE_PARENT;
                break;
        }
        if (isset($primaryscale) && !is_numeric($primaryscale)) {
            $primaryscale = !empty(catscale::return_catscale_by_name($primaryscale))
                ? intval(catscale::return_catscale_by_name($primaryscale)->id) : LOCAL_CATQUIZ_PRIMARYCATSCALE_DEFAULT;
        }

        foreach ($records as $record) {
            if (!$attemptdata = json_decode($record->json)) {
                throw new \moodle_exception("Can not read attempt data");
            }
            $strategyid = $attemptdata->teststrategy;
            $feedbacksettings = new feedbacksettings($strategyid, intval($primaryscale));

            $attemptfeedback = new attemptfeedback($record->attemptid, $record->contextid, $feedbacksettings);
            $feedback = $attemptfeedback->get_feedback_for_attempt() ?? "";
            if (empty($feedback)) {
                return get_string('attemptfeedbacknotavailable', 'local_catquiz');
            }

            $headerstring = get_string('feedbacksheader', 'local_catquiz', $record->attemptid);
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
}
