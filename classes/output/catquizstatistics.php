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

use core\chart_bar;
use core\chart_line;
use core\chart_series;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\local\model\model_strategy;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator\learningprogress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

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
     * @var ?int $courseid
     */
    private ?int $courseid;

    /**
     * @var ?int $testid
     */
    private ?int $testid;

    /**
     * @var ?int $scaleid
     */
    private ?int $scaleid;

    /**
     * @var int $endtime
     */
    private int $endtime;

    /**
     * @var ?int $starttime
     */
    private ?int $starttime;

    /**
     * @var null|int $contextid
     */
    private ?int $contextid;

    /**
     * @var array $quizsettings
     */
    private array $quizsettings;

    /**
     * @var array $attemptsbytimerange
     */
    private array $attemptsbytimerange = [];

    /**
     * @var array $attempts
     */
    private array $attempts = [];

    /**
     * @var bool $quizsettingcompatibility
     */
    private bool $quizsettingcompatibility;

    /**
     * @var int $maxrange
     */
    private int $maxrange;

    /**
     * Create a new catquizstatistics object
     *
     * @param ?int $courseid
     * @param ?int $testid
     * @param int $scaleid
     * @param ?int $endtime
     * @param ?int $starttime
     *
     * @return self
     */
    public function __construct(?int $courseid, ?int $testid, int $scaleid, ?int $endtime = null, ?int $starttime = null) {
        global $DB;

        $this->courseid = $courseid;
        $this->testid = $testid;
        $this->endtime = $endtime ?? time();
        $this->starttime = $starttime;
        $this->scaleid = $scaleid;
        $this->courseid = intval($courseid);
        $scale = catscale::return_catscale_object($this->scaleid);
        $this->contextid = $scale->contextid;

        if ($testid) {
            $tests = $DB->get_records('local_catquiz_tests', ['componentid' => $testid]);
        } else {
            $params = ['catscaleid' => $scaleid];
            if ($courseid) {
                $params['courseid'] = $courseid;
            }
            $tests = $DB->get_records('local_catquiz_tests', $params);
        }
        foreach ($tests as $test) {
            $this->quizsettings[$test->componentid] = json_decode($test->json);
        }
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
        $chart = new \core\chart_bar();

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
     * @param int $catscaleid
     *
     * @return array
     */
    public function render_attemptresultstackchart(int $catscaleid) {
        global $OUTPUT;
        $series = [];
        $labels = [];
        $attemptsbytimerange = $this->get_attempts_by_timerange();
        $fh = new feedback_helper();
        $chart = new \core\chart_bar();
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
                $color = $fh->get_color_for_personability((array)$this->quizsettings, $attempt, $catscaleid);

                if (!isset($series[$timestamp][$color])) {
                        $series[$timestamp][$color] = 1;
                } else {
                        $series[$timestamp][$color] += 1;
                }
            }
        }

        $chart->set_stacked(true);
        $chart->set_labels($labels);
        $out = $OUTPUT->render($chart);

        return [
            'chart' => $out,
        ];
    }

    /**
     * Render chart for histogram of person abilities
     *
     * @return array
     */
    public function render_abilityprofilechart() {
        global $OUTPUT, $DB;

        $abilitysteps = [];
        $stepsize = 0.25;
        $interval = $stepsize * 2;
        $feedbackhelper = new feedback_helper();
        $abilityrange = $feedbackhelper->get_ability_range($this->scaleid);

        $ul = (float) $abilityrange['maxscalevalue'];
        $ll = (float) $abilityrange['minscalevalue'];
        for ($i = $ll + $stepsize; $i <= ($ul - $stepsize); $i += $interval) {
            $abilitysteps[] = $i;
        }
        $items = $feedbackhelper->get_testitems_for_catscale($this->scaleid, $this->contextid, true);
        // Prepare data for test information line.

        $models = model_strategy::get_installed_models();
        $fisherinfos = $feedbackhelper->get_fisherinfos_of_items($items, $models, $abilitysteps);
        $attempts = $this->get_attempts();
        // Prepare data for scorecounter bars.
        $userids = array_unique(array_map(fn ($attempt) => $attempt->userid, $attempts));
        $abilityrecords = catquiz::get_person_abilities($this->contextid, [$this->scaleid], $userids);
        $abilityseries = [];
        $quizsettings = reset($this->quizsettings); // TODO: check if the settings match for all tests.
        foreach ($abilitysteps as $as) {
            $counter = 0;
            foreach ($abilityrecords as $record) {
                $a = floatval($record->ability);
                if ($a <= $as - $stepsize || $a > $as + $stepsize) {
                    continue;
                }
                $counter++;
            }

            $colorvalue = $feedbackhelper->get_color_for_personability(
                (array) $quizsettings,
                $as,
                intval($this->scaleid)
                );
            $abilitystring = strval($as);
            $abilityseries['counter'][$abilitystring] = $counter;
            $abilityseries['colors'][$abilitystring] = $colorvalue;
        }
        // Scale the values of $fisherinfos before creating chart series.
        $scaledtiseries = $feedbackhelper->scalevalues(array_values($fisherinfos), array_values($abilityseries['counter']));

        $scalename = catscale::return_catscale_object($this->scaleid)->name;
        $aserieslabel = get_string('scalescorechartlabel', 'local_catquiz', $scalename);
        $aseries = new chart_series($aserieslabel, array_values($abilityseries['counter']));
        $aseries->set_colors(array_values($abilityseries['colors']));

        $testinfolabel = get_string('testinfolabel', 'local_catquiz');
        $tiseries = new chart_series($testinfolabel, $scaledtiseries);
        $tiseries->set_type(chart_series::TYPE_LINE);
        $tiseries->set_smooth(true);

        $chart = new chart_bar();
        $isteacher = true; // TOOD: check if user is teacher.
        if ($isteacher) {
            $chart->add_series($tiseries);
        }
        $chart->add_series($aseries);
        $chart->set_labels(array_keys($fisherinfos));

        $out = $OUTPUT->render($chart);
        return [
            'chart' => $out,
            'charttitle' => get_string('abilityprofile', 'local_catquiz', $scalename),
        ];
    }

    /**
     * Returns a chart that shows how often a scale was selected as primary scale
     *
     * Selects only the last relevant attempts (i.e. according to testid,
     * courseid, etc). For each scale that was selected as primary scale in
     * those attempts, it indicates for how many users this scale was selected
     * and what ability those users had when the scale was selected.
     *
     * Returns an array with a 'title' and 'chart' element.
     *
     * @return array
     */
    public function render_detected_scales_chart(): array {
        global $CFG, $OUTPUT;

        if (!$attempts = $this->get_attempts()) {
            return [
            'title' => get_string('catquizstatisticsnodata', 'local_catquiz'),
            'chart' => '',
            ];
        }
        $latestattempts = [];
        foreach ($attempts as $attempt) {
            $latestattempts[$attempt->userid] = $attempt;
        }
        $chartdata = [];
        $quizsettings = reset($this->quizsettings); // TODO: fix for multiple.
        foreach ($latestattempts as $userid => $attempt) {
            // Skip old attempts that do not yet have the personabilities_abilities property.
            $json = json_decode($attempt->json);
            if (!property_exists($json, 'personabilities_abilities')) {
                continue;
            }
            $primaryscale = array_filter((array) $json->personabilities_abilities, fn ($scale) => $scale->primary ?? false);
            if (count($primaryscale) != 1) {
                continue;
            }
            $primaryscaleid = array_key_first($primaryscale);
            $value = $primaryscale[$primaryscaleid]->value;

            // Get the range of the selected value.
            $i = 0;
            do {
                $i++;
                $ranglow = sprintf('feedback_scaleid_limit_lower_%d_%d', $primaryscaleid, $i);
                $rangup = sprintf('feedback_scaleid_limit_upper_%d_%d', $primaryscaleid, $i);

            } while (
                !($quizsettings->$ranglow <= $value && $quizsettings->$rangup >= $value)
                && $i <= $quizsettings->numberoffeedbackoptionsselect
            );
            if ($i > $quizsettings->numberoffeedbackoptionsselect) {
                continue;
            }
            $range = $i;
            $chartdata[$primaryscaleid][$range][] = $userid;
        }

        $chart = new chart_bar();
        $chart->set_stacked(true);
        $chart->set_horizontal(true);
        // Add each range as separate chart series.
        if ($this->check_quizsettings_are_compatible()) {
            $colors = array_values(feedbackclass::get_array_of_colors($this->get_max_range()));
            foreach (range(1, $this->get_max_range()) as $range) {
                $counts = [];
                foreach (array_keys($chartdata) as $scaleid) {
                    $counts[] = count($chartdata[$scaleid][$range] ?? []);
                }
                $series = new chart_series(get_string('feedbackrange', 'local_catquiz', $range), $counts);
                $color = $colors[$range - 1];
                $series->set_color($color);
                $chart->add_series($series);
            }
        } else {
            // If the quiz settings are not compatible (e.g. different scale ranges), show the total numbers without range info.
            $counts = [];
            foreach (array_keys($chartdata) as $scaleid) {
                $counts[] = array_sum(array_map(fn ($range) => count($range), $chartdata[$scaleid]));
            }
            $series = new chart_series(get_string('selected_scales_all_ranges_label', 'local_catquiz'), $counts);
            $series->set_color(LOCAL_CATQUIZ_DEFAULT_GREY);
            $chart->add_series($series);
        }

        $labels = array_map(fn ($scaleid) => catscale::return_catscale_object($scaleid)->name, array_keys($chartdata));
        $chart->set_labels($labels);

        $out = $OUTPUT->render($chart);

        return [
            'title' => get_string('chart_detectedscales_title', 'local_catquiz'),
            'chart' => $out,
        ];
    }

    /**
     * Render the charts to display the learning progress.
     *
     * @return array
     */
    public function render_learning_progress() {
        global $USER;

        $userid = $USER->id;

        // Compare to other courses.
        // Find all courses before the end of the day of this attempt.
        $records = $this->get_attempts();
        // Compare records to define range for average.
        // Minimum 3 records required to display progress charts.
        if (count($records) < 3) {
            return [
                'individual' => '',
                'comparison' => '',
            ];
        }
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
        $timerange = feedback_helper::get_timerange_for_attempts($beginningoftimerange, $this->endtime);
        $attemptsofuser = array_filter($records, fn($r) => $r->userid == $userid);
        $attemptsofpeers = array_filter($records, fn($r) => $r->userid != $userid);

        $progressindividual = $this->render_chart_for_individual_user($attemptsofuser, (array) $this->scaleid);
        if (count($attemptsofpeers) < 3) {
            return [
                'individual' => $progressindividual,
                'comparison' => '',
            ];
        }
        $progresscomparison = $this->render_chart_for_comparison(
                $attemptsofuser,
                $attemptsofpeers,
                (array) $this->scaleid,
                $timerange,
                [$beginningoftimerange, $this->endtime]);

        return [
            'individual' => $progressindividual,
            'comparison' => $progresscomparison,
        ];
    }

    /**
     * Render the charts that show the number of questions answered by users.
     *
     * @return array
     */
    public function render_responses_by_users_chart() {
        global $DB;
        list($sql, $params) = catquiz::get_sql_for_questions_answered_per_person($this->contextid);
        $questions = $DB->get_records_sql($sql, $params);

        return [];
    }

    /**
     * Returns the attempts for the given parameters (courseid, scaleid, testid, starttime, endtime)
     *
     * @return array
     */
    private function get_attempts(): array {
        if ($this->attempts) {
            return $this->attempts;
        }

        $attempts = catquiz::get_attempts(
            null,
            $this->scaleid,
            $this->courseid,
            $this->testid,
            $this->contextid,
            $this->starttime,
            $this->endtime
        );
        $this->attempts = $attempts;
        return $attempts;
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
            $this->scaleid,
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
        $timerange = feedback_helper::get_timerange_for_attempts($beginningoftimerange, $this->endtime);
        $this->attemptsbytimerange = feedback_helper::order_attempts_by_timerange($records, $this->scaleid, $timerange);
        return $this->attemptsbytimerange;
    }

    /**
     * If rendering statistics for multiple tests, check whether their settings are compatible
     *
     * @return bool
     */
    private function check_quizsettings_are_compatible(): bool {
        global $CFG;

        if (isset($this->quizsettingcompatibility)) {
            return $this->quizsettingcompatibility;
        }

        if (count($this->quizsettings) === 1) {
            $this->quizsettingcompatibility = true;
            return true;
        }

        // Check if the ranges match.
        $lastranges = null;
        foreach ($this->quizsettings as $testid => $qs) {
            if ($lastranges === null) {
                $lastranges = $qs->numberoffeedbackoptionsselect;
                continue;
            }
            if ($qs->numberoffeedbackoptionsselect !== $lastranges) {
                $this->quizsettingcompatibility = false;
                if ($CFG->debug > 0) {
                    echo sprintf(
                        "Quiz settings are not compatible: different number of ranges in test %d. Has %d but previous has %d",
                        $testid,
                        $qs->numberoffeedbackoptionsselect,
                        $lastranges
                    );
                }
                return false;
            }
        }

        // If we are here, there are multiple tests and they all have the same number of ranges.
        // Now we need to check if the ranges have the same limits.
        foreach (range(1, $lastranges) as $r) {
            $rangestart = null;
            $rangeend = null;
            $startkey = sprintf("feedback_scaleid_limit_lower_%d_%d", $this->scaleid, $r);
            $endkey = sprintf("feedback_scaleid_limit_upper_%d_%d", $this->scaleid, $r);
            foreach ($this->quizsettings as $testid => $qs) {
                // Check if we are in the first iteration of the loop.
                if ($rangestart === null) {
                    $rangestart = $qs->$startkey;
                    $rangeend = $qs->$endkey;
                }
                if ($qs->$startkey !== $rangestart || $qs->$endkey !== $rangeend) {
                    $this->quizsettingcompatibility = false;
                    if ($CFG->debug > 0) {
                        echo sprintf(
                            "Quiz settings are not compatible: different range values [%f, %f] for test %d",
                            $qs->$startkey, $qs->$endkey, $testid
                        );
                    }
                    return false;
                }
            }
        }

        $this->quizsettingcompatibility = true;
        return true;
    }

    /**
     * Returns the largest number of ranges of all the selected tests
     *
     * Each quizsettings defines a number of ranges. When we have multiple settings, they might differ.
     * Here, the largest range is returned.
     *
     * @return int
     */
    private function get_max_range(): int {
        if (isset($this->maxrange)) {
            return $this->maxrange;
        }
        if (count($this->quizsettings) === 1 || $this->check_quizsettings_are_compatible()) {
            $qs = reset($this->quizsettings);
            $this->maxrange = $qs->numberoffeedbackoptionsselect;
            return $this->maxrange;
        }

        // When we are here, there are multiple tests with incompatible quiz settings.
        $maxrange = 0;
        foreach ($this->quizsettings as $qs) {
            if (($m = $qs->numberoffeedbackoptionsselect) > $maxrange) {
                $maxrange = $m;
            }
        }
        $this->maxrange = $maxrange;
        return $this->maxrange;
    }

    /**
     * Render chart for individual progress.
     *
     * @param array $attemptsofuser
     * @param array $primarycatscale
     *
     * @return array
     *
     */
    private function render_chart_for_individual_user(array $attemptsofuser, array $primarycatscale) {
        global $OUTPUT;
        $scaleid = $this->scaleid;
        $scalename = catscale::return_catscale_object($this->scaleid)->name;

        $chart = new chart_line();
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.

        $personabilities = [];
        foreach ($attemptsofuser as $attempt) {
            $data = json_decode($attempt->json);
            if (isset($data->personabilities->$scaleid)) {
                $personabilities[] = $data->personabilities->$scaleid;
            }
        }
        if (count($personabilities) < 2) {
            return '';
        }

        $chartserie = new \core\chart_series(
            get_string('personabilityinscale', 'local_catquiz', $scalename),
            $personabilities
        );

        $labels = range(1, count($personabilities));

        $chart->add_series($chartserie);
        $chart->set_labels($labels);
        $out = $OUTPUT->render($chart);

        return [
            'chart' => $out,
            'charttitle' => get_string('progress', 'local_catquiz'),
        ];
    }

    /**
     * Render chart for progress compared to peers and grouped by date.
     *
     * @param array $attemptsofuser
     * @param array $attemptsofpeers
     * @param array $primarycatscale
     * @param int $timerange
     * @param array $beginningandendofrange
     *
     * @return array
     *
     */
    private function render_chart_for_comparison(
        array $attemptsofuser,
        array $attemptsofpeers,
        array $primarycatscale,
        int $timerange,
        array $beginningandendofrange
    ) {
        global $OUTPUT;
        $scalename = catscale::return_catscale_object($this->scaleid)->name;

        $chart = new chart_line();
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.

        $orderedattemptspeers = feedback_helper::order_attempts_by_timerange($attemptsofpeers, $this->scaleid, $timerange);
        $pa = $this->assign_average_result_to_timerange($orderedattemptspeers);
        $orderedattemptsuser = feedback_helper::order_attempts_by_timerange($attemptsofuser, $this->scaleid, $timerange);
        $ua = $this->assign_average_result_to_timerange($orderedattemptsuser);

        // If we do not have enough data, return.
        $numpeervalues = count(array_filter($pa, fn ($v) => $v !== null));
        $numuservalues = count(array_filter($ua, fn ($v) => $v !== null));
        if ($numpeervalues === 0 && $numuservalues === 0) {
            return [
                'chart' => '',
                'charttitle' => '',
            ];
        }

        $alldates = feedback_helper::get_timerangekeys($timerange, $beginningandendofrange);
        $peerattemptsbydate = [];
        $userattemptsbydate = [];
        $firstvalue = true;
        foreach ($alldates as $index => $key) {

            if (!isset($pa[$key]) && !isset($ua[$key]) && $firstvalue) {
                unset($alldates[$index]);
                continue;
            }
            $firstvalue = false;
            if (isset($pa[$key])) {
                $peerattemptsbydate[$key] = $pa[$key];
            } else {
                $peerattemptsbydate[$key] = null;
            }

            if (isset($ua[$key])) {
                $userattemptsbydate[$key] = $ua[$key];
            } else {
                $userattemptsbydate[$key] = null;
            }
        }

        $peerattempts = new chart_series(
            get_string('scoreofpeers', 'local_catquiz'),
            array_values($peerattemptsbydate)
        );
        $peerattempts->set_labels(array_values($peerattemptsbydate));

        $userattempts = new chart_series(
            get_string('yourscorein', 'local_catquiz', $scalename),
            array_values($userattemptsbydate)
        );
        $userattempts->set_labels(array_values($userattemptsbydate));

        $labels = array_values($alldates);

        $chart->add_series($userattempts);
        $chart->add_series($peerattempts);
        $chart->set_labels($labels);
        $out = $OUTPUT->render($chart);

        return [
            'chart' => $out,
            'charttitle' => get_string('progress', 'local_catquiz'),
        ];
    }

    /**
     * Assign average of result for each period.
     * @param array $attemptsbytimerange
     * @param int   $min The minimum number of results required to calculate the average.
     *
     * @return array
     */
    private function assign_average_result_to_timerange(array $attemptsbytimerange, int $min = 3) {
        // Calculate average personability of this period.
        foreach ($attemptsbytimerange as $date => $attempt) {
            if (count($attempt) < $min) {
                $attemptsbytimerange[$date] = null;
                continue;
            }
            $floats = array_map('floatval', $attempt);
            $average = array_sum($floats) / count($floats);
            $attemptsbytimerange[$date] = $average;
        }
        return $attemptsbytimerange;
    }

    /**
     * In order to make the chartvalues connected, we need to calculate averages between entries, if there are no values set.
     *
     * @param array $attemptswithnulls
     *
     * @return array
     *
     */
    private function fill_empty_values_with_average(array $attemptswithnulls) {
        $result = [];

        $keys = array_keys($attemptswithnulls);

        foreach ($keys as $key) {
            // If the current value is null.
            if ($attemptswithnulls[$key] === null) {
                $neighborvalues = $this->find_non_nullable_value($keys, $attemptswithnulls, $key);
                $prevvalue = $neighborvalues['prevvalue'];
                $nextvalue = $neighborvalues['nextvalue'];

                $average = null;
                if ($prevvalue !== null && $nextvalue !== null) {
                    $average = ($prevvalue + $nextvalue) / 2;
                } else if ($prevvalue !== null) {
                    $average = $prevvalue;
                } else if ($nextvalue !== null) {
                    $average = $nextvalue;
                }

                // Replace the null value with the calculated average.
                $result[$key] = $average;
            } else {
                // If the current value is not null, keep it unchanged.
                $result[$key] = $attemptswithnulls[$key];
            }
        }
        return $result;

    }

}
