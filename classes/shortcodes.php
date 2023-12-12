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
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use local_catquiz\output\attemptfeedback;
use local_catquiz\output\catscalemanager\quizattempts\quizattemptsdisplay;
use local_catquiz\teststrategy\feedbacksettings;

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

        global $OUTPUT;

        $records = catquiz::return_attempt_and_contextid_from_attemptstable(
            intval($args['numberofattempts'] ?? 1),
            intval($args['instanceid'] ?? 0),
            intval($args['courseid'] ?? 0)
            );
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

        $areanames = [
            'personabilitites' => [
                'personabilitieslist',
                'standarderrorpersubscales',
                'chart',
            ],
            'comparetotestaverage' => [
                'comparisontext', // You performed better than ...
                'feedbackbar', // The colorbar.
                'feedbackbarlegend', // Legend of the colorbar.
                'colorbar', // Legend of the colorbar.
            ],
            'customscalefeedback' => [
            ],
            'debuginfo' => [
            ],
            'graphicalsummary' => [
            ],
            'pilotquestions' => [
            ],
            'questionssummary' => [
            ],
        ];

        $hiddenareas = $args['hidden'] ?? [];

        if ($hiddenareas != []) {
            $hiddenareasarray = explode(',', $hiddenareas);
            $areastohide = [];

            if (!empty($areanames)) {
                foreach ($areanames as $key => $values) {
                    // Check if any element in the $values array matches any name in $hiddenareasarray
                    $matchingvalues = array_intersect($values, $hiddenareasarray);
                    if (!empty($matchingvalues) || in_array($key, $hiddenareasarray)) {
                        $areastohide[$key] = array_values($matchingvalues);
                    }
                }
            }
        } else {
            $areastohide = [];
        }

        $feedbacksettings = new feedbacksettings(intval($primaryscale), $areastohide);

        foreach ($records as $record) {
            $attemptfeedback = new attemptfeedback($record->attemptid, $record->contextid, $feedbacksettings);

            $headerstring = get_string('feedbacksheader', 'local_catquiz', $record->attemptid);
            $data = [
                'feedback' => $attemptfeedback->get_feedback_for_attempt($record->attemptid),
                'header' => $headerstring,
                'attemptid' => $record->attemptid,
                'active' => empty($output['attempt']) ? true : false,
            ];
            $output['attempt'][] = $data;
        }
        return $OUTPUT->render_from_template('local_catquiz/feedback/collapsablefeedback', $output);
    }
}
