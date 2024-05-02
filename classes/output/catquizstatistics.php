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

use local_catquiz\catquiz;
use local_catquiz\teststrategy\feedbackgenerator\learningprogress;

/**
 * Renderable class for the catquizstatistics shortcode
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catquizstatistics {

    /**
     * Chart grouping by date and counting attempts.
     *
     * @param array $attemptsbytimerange
     *
     * @return array
     */
    public function render_attemptscounterchart($catscaleid, ?int $courseid, ?int $testid, ?int $contextid, ?int $endtime = null) {
        global $OUTPUT;

        $records = catquiz::get_attempts(
            null,
            $catscaleid,
            $courseid,
            $testid,
            $contextid,
            null,
            null);
        if (count($records) < 2) {
            return [];
        }
        // Get all items of this catscale and catcontext.
        $startingrecord = reset($records);
        if (empty($startingrecord->endtime)) {
            foreach ($records as $record) {
                if (isset($record->endtime) && !empty($record->endtime)) {
                    $startingrecord = $record;
                    break;
                }
            }
        }

        $endtime = $endtime ?? time();
        $beginningoftimerange = intval($startingrecord->endtime);
        $timerange = learningprogress::get_timerange_for_attempts($beginningoftimerange, $endtime);
        $attemptsbytimerange = learningprogress::order_attempts_by_timerange($records, $catscaleid, $timerange);
        $counter = [];
        $labels = [];
        foreach ($attemptsbytimerange as $timestamp => $attempts) {
            $counter[] = count($attempts);
            $labels[] = (string)$timestamp;
        }
        $chart = new \core\chart_line();
        $chart->set_smooth(true);

        $series = new \core\chart_series(
            get_string('numberofattempts', 'local_catquiz'),
            $counter
        );
        $chart->add_series($series);
        $chart->set_labels($labels);
        $out = $OUTPUT->render($chart);

        return [
            'chart' => $out,
            'charttitle' => get_string('numberofattempts', 'local_catquiz'),
        ];
    }
}
