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

use context_course;
use context_system;
use core\chart_bar;
use core\chart_line;
use core\chart_series;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\local\model\model_strategy;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\info;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');


/**
 * Renderable class for the catquizstatistics shortcode
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @author     David Szkiba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catquizstatistics {

    /**
     * @var int
     */
    const ATTEMPTS_PER_PERSON_CLASSES = 7;

    /**
     * For incompatible quiz settings, set this as the detected range.
     * @var int
     */
    const FALLBACK_RANGE = 1;

    /**
     * This is used as fallback if there are no attempts yet to make the histogram legend look ok.
     *
     * @var int
     */
    const DEFAULT_MAX_ATTEMPTS = 7;

    /**
     * Display at most this number of detected scales.
     *
     * @var int
     */
    const MAX_DETECTED_SCALES = 10;

    /**
     * Require ranges to be the same to consider quiz settings as compatible
     *
     * @var string
     */
    const COMPATIBILITY_LEVEL_DEFAULT = 'default';

    /**
     * Require range descriptions to be the same to consider quiz settings as compatible
     *
     * @var string
     */
    const COMPATIBILITY_LEVEL_DESCRIPTION = 'description';

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
     * The root scale of the provided scaleid.
     *
     * If the provided scaleid is the root scale, this has the same value.
     *
     * @var int $rootscaleid
     */
    private int $rootscaleid;

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
     * @var array $quizsettingcompatibility
     */
    private array $quizsettingcompatibility;

    /**
     * @var int $maxrange
     */
    private int $maxrange;

    /**
     * @var array $timerangekeys
     */
    private array $timerangekeys;

    /**
     * @var array Stores the names of teststrategies
     */
    private array $teststrategynames = [];

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
        $scale = catscale::return_catscale_object($this->scaleid);
        $this->rootscaleid = $scale->parentid == 0
            ? $scale->id
            : catscale::get_ancestors($this->scaleid, 3)['mainscale'];
        $this->contextid = catscale::get_context_id($this->scaleid);

        if ($testid) {
            $tests = $DB->get_records('local_catquiz_tests', ['componentid' => $testid]);
        } else {
            // If a subscale is given as scaleid, we still need the root scale to get the associated tests.
            $params = ['catscaleid' => $this->rootscaleid];
            if ($courseid) {
                $params['courseid'] = $courseid;
            }
            $tests = $DB->get_records('local_catquiz_tests', $params);
        }
        if (count($tests) === 0) {
            throw new \Exception('catquizstatistics shortcode: no tests can be found for the given arguments');
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
    public function render_attempts_per_timerange_chart() {
        global $OUTPUT;

        $attemptsbytimerange = $this->get_attempts_by_timerange(true);
        if (!$attemptsbytimerange) {
            return [
                'charttitle' => get_string('catquizstatistics_numattempts_title', 'local_catquiz'),
                'chart' => $this->get_nodata_body(),
            ];
        }
        if (!$qs = $this->get_quizsettings()) {
            // We use only one range '1' if the quizsettings do not match between different quizzes.
            $colors = [LOCAL_CATQUIZ_DEFAULT_BLACK];
            foreach ($this->timerangekeys as $timepoint) {
                $countsbyrange[0][$timepoint] = 0;
                $countsbyrange[1][$timepoint] = 0;
            }
            foreach ($attemptsbytimerange as $timestamp => $attempts) {
                foreach ($attempts as $attempt) {
                    if ($attempt) {
                        $countsbyrange[1][$timestamp]++;
                        continue;
                    }
                    // We do not have a value.
                    $countsbyrange[0][$timestamp]++;
                }
            }
        } else {
            $colors = array_values(feedbackclass::get_array_of_colors($qs->numberoffeedbackoptionsselect));
            for ($i = 0; $i <= $qs->numberoffeedbackoptionsselect; $i++) {
                foreach ($this->timerangekeys as $timepoint) {
                    $countsbyrange[$i][$timepoint] = 0;
                }
            }
            foreach ($attemptsbytimerange as $timestamp => $attempts) {
                foreach ($attempts as $attempt) {
                    if (is_null($attempt)) {
                        $countsbyrange[0][$timestamp]++;
                        continue;
                    }
                    if (!$range = feedback_helper::get_range_of_value($this->get_quizsettings(), $this->scaleid, $attempt)) {
                        continue;
                    }
                    $countsbyrange[$range][$timestamp]++;
                }
            }
        }
        $chart = new chart_bar();
        $chart->set_stacked(true);
        $chart->set_labels(array_values($this->timerangekeys));
        $colors[-1] = LOCAL_CATQUIZ_DEFAULT_GREY;
        $serieslabels = is_null($qs)
            ? [get_string('noresult', 'local_catquiz'), get_string('hasability', 'local_catquiz')]
            : array_map(fn ($r) => get_string('feedbackrange', 'local_catquiz', $r), array_keys($countsbyrange));
        $serieslabels[0] = get_string('noresult', 'local_catquiz');
        foreach ($countsbyrange as $range => $counts) {
            $series = new chart_series(
                $serieslabels[$range],
                array_values($counts)
            );
            $series->set_color($colors[$range - 1]);
            $chart->add_series($series);
        }
        $chart->get_xaxis(0, true)->set_label(get_string('date'));
        $chart->get_yaxis(0, true)->set_label(get_string('numberofattempts', 'local_catquiz'));
        $chart->set_legend_options(['display' => false]);
        $out = $OUTPUT->render_chart($chart, false);

        return [
            'charttitle' => get_string('catquizstatistics_numattempts_title', 'local_catquiz'),
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
                'chart' => '',
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
        $chart->set_legend_options(['display' => false]);
        $out = $OUTPUT->render_chart($chart, false);

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
        $abilityrecords = [];
        if ($userids) {
            $abilityrecords = catquiz::get_person_abilities($this->contextid, [$this->scaleid], $userids);
        }
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

        // Teachers and CAT managers can see the test information in addition to the ability.
        $canviewcourse = false;
        if ($this->courseid) {
            $context = context_course::instance($this->courseid);
            $canviewcourse = has_capability('local/catquiz:view_users_feedback', $context);
        }
        $ismanager = has_capability('local/catquiz:canmanage', context_system::instance());
        $canviewall = $ismanager || $canviewcourse;
        if ($canviewall) {
            $chart->add_series($tiseries);
        }

        $chart->set_legend_options(['display' => $canviewall]);
        $chart->add_series($aseries);
        $chart->set_labels(array_keys($fisherinfos));
        $chart->get_xaxis(0, true)->set_label(get_string('personability', 'local_catquiz'));

        $out = $OUTPUT->render_chart($chart, false);
        return [
            'chart' => $out,
            'charttitle' => get_string('abilityprofile', 'local_catquiz', feedback_helper::add_quotes($scalename)),
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
                'charttitle' => get_string('chart_detectedscales_title', 'local_catquiz', self::MAX_DETECTED_SCALES),
                'chart' => $this->get_nodata_body(),
            ];
        }
        $latestattempts = [];
        foreach ($attempts as $attempt) {
            $latestattempts[$attempt->userid] = $attempt;
        }
        $chartdata = [];
        $quizsettings = $this->get_quizsettings();
        foreach ($latestattempts as $userid => $attempt) {
            // Skip old attempts that do not yet have the personabilities_abilities property.
            $json = json_decode($attempt->json);
            if (!property_exists($json, 'personabilities_abilities')) {
                continue;
            }
            $primaryscalearray = array_filter((array) $json->personabilities_abilities, fn ($scale) => $scale->primary ?? false);
            if (count($primaryscalearray) != 1) {
                continue;
            }
            $primaryscaleid = array_key_first($primaryscalearray);
            $primaryscale = $primaryscalearray[$primaryscaleid];
            if (!property_exists($primaryscale, 'toreport') || !$primaryscale->toreport) {
                continue;
            }
            $value = $primaryscale->value;

            // Get the range of the selected value.
            if (!$range = feedback_helper::get_range_of_value($quizsettings, $primaryscaleid, $value)) {
                $range = self::FALLBACK_RANGE;
            }
            $chartdata[$primaryscaleid][$range][] = $userid;
        }

        if (!$chartdata) {
            return [
                'charttitle' => get_string('chart_detectedscales_title', 'local_catquiz', self::MAX_DETECTED_SCALES),
                'chart' => $this->get_nodata_body(),
            ];
        }

        // Sort the chart in descending order of attempts across all ranges.
        $tmp = [];
        foreach ($chartdata as $scaleid => $rangearray) {
            $num = array_sum(array_map(fn ($range) => count($range), $rangearray));
            $tmp[$scaleid] = $num;
        }
        arsort($tmp);
        foreach (array_keys($tmp) as $scaleid) {
            $chartdatasorted[$scaleid] = $chartdata[$scaleid];
        }

        // Keep only the top 10.
        $chartdatasorted = array_slice($chartdatasorted, 0, self::MAX_DETECTED_SCALES, true);

        $chart = new chart_bar();
        $chart->set_stacked(true);
        $chart->set_horizontal(true);
        // Add each range as separate chart series.
        if ($this->check_quizsettings_are_compatible()) {
            $colors = array_values(feedbackclass::get_array_of_colors($this->get_max_range()));
            foreach (range(1, $this->get_max_range()) as $range) {
                $counts = [];
                foreach (array_keys($chartdatasorted) as $scaleid) {
                    $counts[] = count($chartdatasorted[$scaleid][$range] ?? []);
                }
                $series = new chart_series(get_string('feedbackrange', 'local_catquiz', $range), $counts);
                $color = $colors[$range - 1];
                $series->set_color($color);
                $chart->add_series($series);
            }

            $legend = feedback_helper::get_colorbarlegend(
                    $this->get_quizsettings(),
                    $this->scaleid,
                    $this->check_quizsettings_are_compatible(self::COMPATIBILITY_LEVEL_DESCRIPTION)
                );
            $colorbarlegend = ['feedbackbarlegend' => $legend];
        } else {
            // If the quiz settings are not compatible (e.g. different scale ranges), show the total numbers without range info.
            $counts = [];
            foreach (array_keys($chartdatasorted) as $scaleid) {
                $counts[] = array_sum(array_map(fn ($range) => count($range), $chartdatasorted[$scaleid]));
            }
            $series = new chart_series(get_string('selected_scales_all_ranges_label', 'local_catquiz'), $counts);
            $series->set_color(LOCAL_CATQUIZ_DEFAULT_GREY);
            $chart->add_series($series);
            $colorbarlegend = false;
        }

        $labels = array_map(fn ($scaleid) => catscale::return_catscale_object($scaleid)->name, array_keys($chartdatasorted));
        $chart->set_labels($labels);
        $chart->get_xaxis(0, true)->set_label(sprintf('# %s', get_string('users')));
        // Hide the legend.
        $chart->set_legend_options(['display' => false]);

        $out = $OUTPUT->render_chart($chart, false);

        return [
            'colorbarlegend' => $colorbarlegend,
            'charttitle' => get_string('chart_detectedscales_title', 'local_catquiz', self::MAX_DETECTED_SCALES),
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
        $scalename = catscale::return_catscale_object($this->scaleid)->name;
        // Compare records to define range for average.
        // Minimum 3 records required to display progress charts.
        if (count($records) < 3) {
            return [
                'charttitle' => get_string('progress', 'local_catquiz', feedback_helper::add_quotes($scalename)),
                'chart' => $this->get_nodata_body(),
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
        $scalename = catscale::return_catscale_object($this->scaleid)->name;

        if (count($attemptsofpeers) < 3) {
            return [
                'charttitle' => get_string('progress', 'local_catquiz', feedback_helper::add_quotes($scalename)),
                'chart' => $this->get_nodata_body(),
            ];
        }
        $progresscomparison = $this->render_chart_for_comparison(
                $attemptsofuser,
                $attemptsofpeers,
                (array) $this->scaleid,
                $timerange,
                [$beginningoftimerange, $this->endtime]);

        return [
            'charttitle' => get_string('progress', 'local_catquiz', feedback_helper::add_quotes($scalename)),
            'chart' => $progresscomparison,
        ];
    }

    /**
     * Render the charts that show the number of questions answered by users.
     *
     * @return array
     */
    public function render_responses_by_users_chart() {
        global $DB, $OUTPUT;
        list($sql, $params) = catquiz::get_sql_for_questions_answered_per_person($this->contextid, $this->scaleid, $this->courseid);
        if (!$results = $DB->get_records_sql($sql, $params)) {
            return [
                'charttitle' => get_string('responsesbyusercharttitle', 'local_catquiz'),
                'chart' => $this->get_nodata_body(),
            ];
        }
        $maxattempts = 0;
        foreach ($results as $r) {
            if ($r->total_answered > $maxattempts) {
                $maxattempts = $r->total_answered;
            }
        }

        if ($maxattempts === 0) {
            $maxattempts = self::DEFAULT_MAX_ATTEMPTS;
        }
        $classwidth = ceil($maxattempts / self::ATTEMPTS_PER_PERSON_CLASSES);

        if (!$qs = $this->get_quizsettings()) {
            $numranges = 1;
        } else {
            $numranges = $qs->numberoffeedbackoptionsselect;
        }

        // Initialize the data to 0 for all ranges and bins.
        for ($i = 0; $i <= $numranges; $i++) {
            for ($j = 0; $j <= self::ATTEMPTS_PER_PERSON_CLASSES; $j++) {
                $data[$i][$j] = [];
            }
        }

        foreach ($results as $r) {
            if (intval($r->total_answered) === 0) {
                $bin = 0;
            } else {
                $bin = feedback_helper::get_histogram_bin($r->total_answered, $classwidth);
                // Bar 0 is reserved for 0 answers. Spread the rest across bin 1 ... n.
                $bin = $bin + 1;
            }
            if (!$r->ability) {
                $data[0][$bin][] = $r;
            } else {
                // If we have incompatible quiz settings, assing all values to the fallback range.
                $range = $qs
                    ? feedback_helper::get_range_of_value($qs, $this->scaleid, $r->ability)
                    : self::FALLBACK_RANGE;
                if (!$range) {
                    // Ability is outside defined range. TODO: how to handle?
                    continue;
                }
                $data[$range][$bin][] = $r;
            }
        }

        $chart = new \core\chart_bar();
        $chart->set_stacked(true);

        $colors = $qs ? array_values(feedbackclass::get_array_of_colors($numranges)) : [LOCAL_CATQUIZ_DEFAULT_BLACK];
        $colors['-1'] = LOCAL_CATQUIZ_DEFAULT_GREY;
        for ($range = 0; $range <= $numranges; $range++) {
            if ($range == 0) {
                $serieslabel = get_string('noresult', 'local_catquiz');
            } else {
                $serieslabel = $qs
                    ? get_string('feedbackrange', 'local_catquiz', $range)
                    : get_string('hasability', 'local_catquiz');
            }
            $color = $colors[$range - 1];
            $series = new \core\chart_series(
                $serieslabel,
                array_map(fn ($x) => count($x), $data[$range])
            );
            $series->set_color($color);
            $chart->add_series($series);
        }

        $labels = [];
        $labels[0] = get_string('notyetattempted', 'local_catquiz');
        for ($i = 1; $i <= self::ATTEMPTS_PER_PERSON_CLASSES; $i++) {
            if ($classwidth == 1) {
                $labels[$i] = sprintf("%d", $i * $classwidth);
            } else {
                $labels[$i] = sprintf("%d .. %d", $i * $classwidth - $classwidth + 1, $i * $classwidth);
            }
        }
        $chart->set_labels($labels);
        $chart->get_xaxis(0, true)->set_label(get_string('catquizstatistics_numberofresponses', 'local_catquiz'));
        $chart->get_yaxis(0, true)->set_label(sprintf('# %s', get_string('students')));
        $chart->set_legend_options(['display' => false]);

        $out = $OUTPUT->render_chart($chart, false);

        return [
            'charttitle' => get_string('responsesbyusercharttitle', 'local_catquiz'),
            'chart' => $out,

        ];
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

        $attempts = [];
        foreach (catquiz::get_attempts(
            null,
            $this->scaleid,
            $this->courseid,
            $this->testid,
            $this->contextid,
            $this->starttime,
            $this->endtime
        ) as $record) {
            $json = json_decode($record->json);
            $prunedrecord = $record;
            $prunedrecord->json = json_encode((object) [
                'personabilities_abilities' => $json->personabilities_abilities ?? null,
                'personabilities' => $json->personabilities ?? null,
            ]);
            $attempts[] = $prunedrecord;
        }
        $this->attempts = $attempts;
        return $attempts;
    }

    /**
     * Return attempts for the time range of this object
     *
     * @param bool $allowempty
     * @return array
     */
    private function get_attempts_by_timerange(bool $allowempty = false): array {
        if (isset($this->attemptsbytimerange[$allowempty])) {
            return $this->attemptsbytimerange[$allowempty];
        }

        $records = [];
        foreach (catquiz::get_attempts(
            null,
            $this->rootscaleid,
            $this->courseid,
            $this->testid,
            $this->contextid,
            $this->starttime,
            $this->endtime) as $record) {
            // Store a subset of the json to save memory.
            $json = json_decode($record->json);
            $prunedrecord = $record;
            $prunedrecord->json = json_encode((object) [
                'personabilities' => $json->personabilities,
            ]);
            $records[] = $prunedrecord;
        }

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
        $this->timerangekeys = feedback_helper::get_timerangekeys($timerange, [$beginningoftimerange, $this->endtime]);
        $this->attemptsbytimerange[$allowempty] = feedback_helper::order_attempts_by_timerange(
            $records,
            $this->scaleid,
            $timerange,
            $allowempty
        );
        return $this->attemptsbytimerange[$allowempty];
    }

    /**
     * If rendering statistics for multiple tests, check whether their settings are compatible
     *
     * @param string $level Controls how strict the compatibility check is.
     *
     * @return bool
     */
    private function check_quizsettings_are_compatible(string $level = self::COMPATIBILITY_LEVEL_DEFAULT): bool {
        global $CFG;

        if (isset($this->quizsettingcompatibility[$level])) {
            return $this->quizsettingcompatibility[$level];
        }

        if (count($this->quizsettings) === 1) {
            $this->quizsettingcompatibility[$level] = true;
            return true;
        }

        // Check if the ranges match.
        $lastranges = null;
        $prevtestid = null;
        foreach ($this->quizsettings as $testid => $qs) {
            if ($lastranges === null) {
                $lastranges = $qs->numberoffeedbackoptionsselect;
                $prevtestid = $testid;
                continue;
            }
            if ($qs->numberoffeedbackoptionsselect !== $lastranges) {
                $this->quizsettingcompatibility[$level] = false;
                return false;
            }
        }

        // If we are here, there are multiple tests and they all have the same
        // number of ranges. Now we need to check if the ranges have the same
        // limits and, depending on the $level, descriptions.
        foreach (range(1, $lastranges) as $r) {
            $rangestart = null;
            $rangeend = null;
            $startkey = sprintf("feedback_scaleid_limit_lower_%d_%d", $this->scaleid, $r);
            $endkey = sprintf("feedback_scaleid_limit_upper_%d_%d", $this->scaleid, $r);
            $textkey = sprintf('feedbacklegend_scaleid_%d_%d', $this->scaleid, $r);
            foreach ($this->quizsettings as $testid => $qs) {
                // Check if we are in the first iteration of the loop.
                if ($rangestart === null) {
                    $rangestart = $qs->$startkey;
                    $rangeend = $qs->$endkey;
                    $rangetext = $qs->$textkey;
                }
                if ($qs->$startkey !== $rangestart || $qs->$endkey !== $rangeend
                    || ($level === self::COMPATIBILITY_LEVEL_DESCRIPTION && $qs->$textkey !== $rangetext)
                ) {
                    $this->quizsettingcompatibility[$level] = false;
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

        $this->quizsettingcompatibility[$level] = true;
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
                return $this->get_nodata_body();
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
            get_string('catquizstatistics_progress_peers_title', 'local_catquiz'),
            array_values($peerattemptsbydate)
        );
        $peerattempts->set_labels(array_values($peerattemptsbydate));

        $userattempts = new chart_series(
            get_string('catquizstatistics_progress_personal_title', 'local_catquiz'),
            array_values($userattemptsbydate)
        );
        $userattempts->set_labels(array_values($userattemptsbydate));

        $labels = array_values($alldates);

        $chart->add_series($peerattempts);
        $chart->add_series($userattempts);
        $chart->set_labels($labels);
        $chart->get_xaxis(0, true)->set_label(get_string('date'));
        $chart->get_yaxis(0, true)->set_label(get_string('personability', 'local_catquiz'));
        $out = $OUTPUT->render_chart($chart, false);
        return $out;
    }

    /**
     * Returns an array of with the chart in the 'chart' key.
     *
     * The chart displays how many students made X attempts and the corresponding ability range.
     * It displays the number of students having 1 attempt, the number of students having 2 attempts, etc.
     *
     * @return array
     */
    public function render_attempts_per_person_chart(): array {
        global $DB, $OUTPUT;
        list($sql, $params) = catquiz::get_sql_for_attempts_per_person($this->contextid, $this->scaleid, $this->courseid);
        if (!$records = $DB->get_records_sql($sql, $params)) {
            return [
                'charttitle' => get_string('catquizstatistics_numattemptsperperson_title', 'local_catquiz'),
                'chart' => $this->get_nodata_body(),
            ];
        }

        $chartdata = [];
        if (!$qs = $this->get_quizsettings()) {
            // Use range 0 for missing person ability and range 1 for everything else.
            $maxrange = 1;
            $colors = [LOCAL_CATQUIZ_DEFAULT_BLACK];
        } else {
            $maxrange = $qs->numberoffeedbackoptionsselect;
            $colors = array_values(feedbackclass::get_array_of_colors($qs->numberoffeedbackoptionsselect));
        }
        $colors[-1] = LOCAL_CATQUIZ_DEFAULT_GREY;
        $maxattempts = $records[array_key_last($records)]->attempts;
        if ($maxattempts == 0) {
            $maxattempts = self::DEFAULT_MAX_ATTEMPTS;
        }

        // Display a maximum of self::ATTEMPTS_PER_PERSON_CLASSES bars. This
        // means, that each bar covers a range of $classwidth attempts.
        $classwidth = ceil($maxattempts / self::ATTEMPTS_PER_PERSON_CLASSES);

        // Initialize all ranges of all possible attempt counts to 0.
        for ($i = 0; $i <= $maxrange; $i++) {
            for ($j = 0; $j <= self::ATTEMPTS_PER_PERSON_CLASSES; $j++) {
                $chartdata[$i][$j] = 0;
            }
        }

        // Set the number of attempts per range.
        foreach ($records as $r) {
            if (!$qs) {
                $range = $r->ability ? 1 : 0;
            } else if (!$range = feedback_helper::get_range_of_value($this->get_quizsettings(), $this->scaleid, $r->ability)) {
                $range = 0;
            }
            if ($r->attempts == 0) {
                $chartdata[$range][0]++;
            } else {
                $bin = feedback_helper::get_histogram_bin($r->attempts, $classwidth);
                $chartdata[$range][$bin + 1]++;
            }
        }

        $serieslabels = is_null($qs)
            ? [get_string('noresult', 'local_catquiz'), get_string('hasability', 'local_catquiz')]
            : array_map(fn ($r) => get_string('feedbackrange', 'local_catquiz', $r), array_keys($chartdata));
        $serieslabels[0] = get_string('noresult', 'local_catquiz');
        $chart = new chart_bar();
        $chart->set_stacked(true);
        $chartlabels[0] = get_string('notyetattempted', 'local_catquiz');
        for ($i = 1; $i <= self::ATTEMPTS_PER_PERSON_CLASSES; $i++) {
            if ($classwidth == 1) {
                $chartlabels[$i] = sprintf(
                    '%d',
                    $i * $classwidth
                );
            } else {
                $chartlabels[$i] = sprintf(
                    '%d .. %d',
                    $i * $classwidth - $classwidth + 1,
                    $i * $classwidth
                );
            }
        }
        $chart->set_labels($chartlabels);

        foreach (array_keys($chartdata) as $range) {
            $series = new chart_series($serieslabels[$range], $chartdata[$range]);
            $series->set_color($colors[$range - 1]);
            $chart->add_series($series);
        }

        $chart->get_xaxis(0, true)->set_label(get_string('numberofattempts', 'local_catquiz'));
        $chart->get_yaxis(0, true)->set_label(sprintf('# %s', get_string('students')));
        $chart->set_legend_options(['display' => false]);
        $out = $OUTPUT->render_chart($chart, false);

        if (
            optional_param('debug', false, PARAM_BOOL)
            && has_capability('local/catquiz:manage_catscales', context_system::instance())
        ) {
            $thead = "
                <thead>
                  <tr>
                    <th>userid</th>
                    <th>ability</th>
                    <th>attempts</th>
                  </tr>
                </thead>";
            $tr = "";
            foreach ($records as $r) {
                $ability = $r->ability ?? '-';
                $tr .= "<tr><td>$r->userid</td><td>$ability</td><td>$r->attempts</td></tr>";
            }
            $table = "<table class=\"table\">$thead<tbody>$tr</tbody></table>";
            $out .= $table;
        }

        $colorbarlegend = false;
        if ($this->get_quizsettings()) {
            $legend = feedback_helper::get_colorbarlegend(
                    $this->get_quizsettings(),
                    $this->scaleid,
                    $this->check_quizsettings_are_compatible(self::COMPATIBILITY_LEVEL_DESCRIPTION),
                    true
                );
            $colorbarlegend = ['feedbackbarlegend' => $legend];
        }
        return [
            'colorbarlegend' => $colorbarlegend,
            'charttitle' => get_string('catquizstatistics_numattemptsperperson_title', 'local_catquiz'),
            'chart' => $out,
        ];
    }

    /**
     * Returns an URL to download a CSV.
     *
     * If the user does not have the catquiz 'canmanage' capability, an empty
     * string is returned instead of an URL.
     *
     * @return string
     */
    public function render_export_button(): string {
        $hasglobalaccess = has_capability('local/catquiz:canmanage', context_system::instance());
        $haslocalaccess = $this->courseid && has_capability(
            'local/catquiz:view_users_feedback',
            context_course::instance($this->courseid)
        );
        if (!$hasglobalaccess && !$haslocalaccess) {
            return '';
        }

        $params = [
            'scaleid' => $this->scaleid,
            'courseid' => $this->courseid,
            'testid' => $this->testid,
            'starttime' => $this->starttime,
            'endtime' => $this->endtime,
        ];
        return (new moodle_url('/local/catquiz/export_shortcode_csv.php', $params))->out(false);
    }

    /**
     * Returns the data that can be downloaded as csv.
     *
     * @return array
     */
    public function get_export_data(): array {
        global $DB;

        if (!has_capability('local/catquiz:canmanage', context_system::instance())) {
            return [];
        }

        list ($sql, $params) = catquiz::get_sql_for_csv_export(
            $this->contextid,
            $this->scaleid,
            $this->courseid,
            $this->testid,
            $this->starttime,
            $this->endtime
        );

        $data = [];
        foreach ($DB->get_recordset_sql($sql, $params) as $r) {

            // TODO: To be implemented: 'Ergebnis-Range', 'N global', 'frac global', 'N Ergebnisskala', 'frac Ergebnisskala'.
            $additionalresults = json_decode($r->json);

            $r->testid = $additionalresults->testid;

            $globalscale = $additionalresults->catscaleid;
            $r->globalid = $globalscale;
            $r->globalname = $additionalresults->catscales->$globalscale->name;
            $r->globalpp = $additionalresults->personabilities->$globalscale;
            $r->globalse = $additionalresults->se->$globalscale;
            // phpcs:disable
            /*
            $r->globaln = $additionalresults->n->$globalscale;
            $r->globalf = $additionalresults->frac->$globalscale;
            */
            // phpcs:enable

            $primaryscale = $additionalresults->cprimaryscale;
            $r->primaryid = $globalscale;
            $r->primaryname = $additionalresults->catscales->$primaryscale->name;
            $r->primarypp = $additionalresults->personabilities->$primaryscale;
            $r->primaryse = $additionalresults->se->$primaryscale;
            // phpcs:disable
            /*
            $r->primaryn = $additionalresults->n->$primaryscale;
            $r->primaryf = $additionalresults->frac->$primaryscale;
            */
            // phpcs:enable

            unset($r->json);
            $r->starttime = userdate($r->starttime, get_string('strftimedatetime', 'core_langconfig'));
            if (!$r->endtime || $r->endtime == 0) {
                $r->endtime = '';
            } else {
                $r->endtime = userdate($r->endtime, get_string('strftimedatetime', 'core_langconfig'));
            }
            $r->teststrategy = $this->get_teststrategy_name($r->teststrategy);

            // TODO: Process all results.

            $data[] = $r;
        }
        return $data;
    }

    /**
     * Assign average of result for each period.
     * @param array $attemptsbytimerange
     * @param int   $min The minimum number of results required to calculate the average.
     *
     * @return array
     */
    private function assign_average_result_to_timerange(array $attemptsbytimerange, int $min = 0) {
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

    /**
     * Get quiz settings
     *
     * When rendering charts for multiple quizzes, we have multiple quiz settings.
     * There could be conflicts between those quiz settings.
     *
     * If the quiz settings are compatible, it returns one of them.
     * Otherwise, null is returned.
     *
     * @return ?stdClass
     */
    private function get_quizsettings(): ?stdClass {
        if ($this->check_quizsettings_are_compatible()) {
            $first = reset($this->quizsettings);
            return $first;
        }
        return null;
    }

    /**
     * Returns a string that explains there are not enough data to display the chart
     *
     * @return string
     */
    private function get_nodata_body() {
        return get_string('catquizstatisticsnodata', 'local_catquiz');
    }

    /**
     * Retrieves the name of a teststrategy
     *
     * @param int $id
     * @return string
     */
    private function get_teststrategy_name(int $id): string {
        if (array_key_exists($id, $this->teststrategynames)) {
            return $this->teststrategynames[$id];
        }
        if (!$teststrategy = info::get_teststrategy($id, false)) {
            throw new \Exception(sprintf('Unknown teststrategy %d', $id));
        }
        // Gets the unqualified classname without namespace.
        // See https://stackoverflow.com/a/27457689.
        $classname = substr(strrchr(get_class($teststrategy), '\\'), 1);
        $this->teststrategynames[$id] = get_string($classname, 'local_catquiz');
        return $this->teststrategynames[$id];
    }
}
