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
use local_catquiz\feedback\feedbackclass;
use local_catquiz\teststrategy\feedbackgenerator;
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
     * @var int $parentscaleid
     */
    private int $parentscaleid;

    /**
     * @var int $courseid
     */
    private int $courseid;

    /**
     * @var int $parentscaleid
     */
    private int $testid;

    /**
     * @var ?int $contextid
     */
    private ?int $contextid;

    /**
     * @var int $quizsettings
     */
    private object $quizsettings;

    /**
     * @var ?int $endtime
     */
    private ?int $endtime;

    /**
     * @var array $attemptsbytimerange
     */
    private array $attemptsbytimerange = [];

    /**
     * Create a new catquizstatistics object
     *
     * @param int $testid
     * @param ?int $contextid
     * @param ?int $endtime
     * @param ?int $parentscaleid
     *
     * @return self
     */
    public function __construct(int $testid, ?int $contextid, ?int $endtime, ?int $parentscaleid) {
        global $DB;

        $this->testid = $testid;
        $this->contextid = $contextid;
        $this->endtime = $endtime ?? time();
        $this->parentscaleid = $parentscaleid;

        $test = $DB->get_record('local_catquiz_tests', ['componentid' => $testid], 'json, courseid', MUST_EXIST);
        $this->quizsettings = json_decode($test->json);
        $this->courseid = intval($test->courseid);
    }

    /**
     * Chart grouping by date and counting attempts.
     *
     * @return array
     */
    public function render_attemptscounterchart() {
        global $OUTPUT;

        $counter = [];
        $labels = [];
        $attemptsbytimerange = $this->get_attempts_by_timerange();
        if (!$attemptsbytimerange) {
            return [
                'chart' => get_string('catquizstatisticsnodata', 'local_catquiz'),
            ];
        }
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
        ];
    }

    /**
     * Chart grouping by date showing attempt results.
     *
     * @param array $attemptsbytimerange
     * @param int $catscaleid
     *
     * @return array
     */
    public function render_attemptresultstackchart(int $catscaleid) {
        global $OUTPUT;
        $series = [];
        $labels = [];
        $attemptsbytimerange = $this->get_attempts_by_timerange();
        if (!$attemptsbytimerange) {
            return [
                'chart' => get_string('catquizstatisticsnodata', 'local_catquiz'),
            ];
        }
        foreach ($this->get_attempts_by_timerange() as $timestamp => $attempts) {
            $labels[] = (string)$timestamp;
            foreach ($attempts as $attempt) {
                if (is_object($attempt)) {
                    // This is to stay backwards compatible.
                    $attempt = (float) $attempt->value;
                }
                $color = feedbackgenerator::get_color_for_personability((array)$this->quizsettings, $attempt, $catscaleid);

                if (!isset($series[$timestamp][$color])) {
                        $series[$timestamp][$color] = 1;
                } else {
                        $series[$timestamp][$color] += 1;
                }
            }
        }

        $chart = new \core\chart_bar();
        $chart->set_stacked(true);

        $colorsarray = $this->get_defined_feedbackcolors_for_scale($catscaleid);

        foreach ($colorsarray as $colorcode => $rangesarray) {
            $serie = [];
            foreach ($series as $timestamp => $cc) {
                $valuefound = false;
                foreach ($cc as $cc => $elementscounter) {
                    if ($colorcode != $cc) {
                        continue;
                    }
                    $valuefound = true;
                    $serie[] = $elementscounter;
                }
                if (!$valuefound) {
                    $serie[] = 0;
                }
            }
            $rangestart = $rangesarray['rangestart'];
            $rangeend = $rangesarray['rangeend'];
            $labelstring = get_string(
                'personabilityrangestring',
                'local_catquiz',
                ['rangestart' => $rangestart, 'rangeend' => $rangeend]);
            $s = new \core\chart_series(
                $labelstring,
                $serie
            );
            $s->set_colors([0 => $colorcode]);
            $chart->add_series($s);
        }

        $chart->set_labels($labels);
        $out = $OUTPUT->render($chart);

        return [
            'chart' => $out,
        ];
    }

    /**
     * Return attempts for the time range of this object
     *
     * @return array
     */
    private function get_attempts_by_timerange(): array {
        if ($this->attemptsbytimerange) {
            return $this->attemptsbytimerange;
        }

        $records = catquiz::get_attempts(
            null,
            $this->parentscaleid,
            $this->courseid,
            $this->testid,
            $this->contextid,
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

        $beginningoftimerange = intval($startingrecord->endtime);
        $timerange = learningprogress::get_timerange_for_attempts($beginningoftimerange, $this->endtime);
        $this->attemptsbytimerange = learningprogress::order_attempts_by_timerange($records, $this->parentscaleid, $timerange);
        return $this->attemptsbytimerange;
    }


    /**
     * Return all colors defined in feedbacksettings for this scale.
     *
     * @return array
     */
    private function get_defined_feedbackcolors_for_scale() {

        $colors = [];

        $numberoffeedbackoptions = intval($this->quizsettings->numberoffeedbackoptionsselect) ?? 8;
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);

        for ($i = 1; $i <= $numberoffeedbackoptions; $i++) {
            $colorkey = 'wb_colourpicker_' . $this->parentscaleid . '_' . $i;
            $rangestartkey = "feedback_scaleid_limit_lower_" . $this->parentscaleid . "_" . $i;
            $rangeendkey = "feedback_scaleid_limit_upper_" . $this->parentscaleid . "_" . $i;
            $colorname = $this->quizsettings->$colorkey;
            if (isset($colorarray[$colorname])) {
                    $colors[$colorarray[$colorname]]['rangestart'] = $this->quizsettings->$rangestartkey;
                    $colors[$colorarray[$colorname]]['rangeend'] = $this->quizsettings->$rangeendkey;
            }

        }
        return $colors;
    }
}
