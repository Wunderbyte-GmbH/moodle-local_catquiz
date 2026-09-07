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
use local_catquiz\local\access\context_resolver;
use local_catquiz\local\access\feedback_access;
use local_catquiz\local\model\model_strategy;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\info;
use LogicException;
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
     * Resolved context these statistics refer to (issue #18).
     *
     * @var ?\context
     */
    private ?\context $statisticscontext = null;

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
     * Returns the context these statistics refer to.
     *
     * Resolved once from the most specific scope available (test
     * instance, otherwise course, otherwise system) so that the rendered page,
     * the export button and the exported CSV all judge access identically.
     *
     * @return \context
     */
    public function get_statistics_context(): \context {
        if ($this->statisticscontext === null) {
            $this->statisticscontext = context_resolver::for_statistics($this->courseid, $this->testid);
        }

        return $this->statisticscontext;
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
        $attemptsbytimerange = $this->get_attempts_by_timerange(false, true);
        $fh = new feedback_helper();
        $chart = new \core\chart_bar();
        if (!$attemptsbytimerange) {
            return [
                'chart' => '',
            ];
        }
        foreach ($this->get_attempts_by_timerange(false, true) as $timestamp => $attempts) {
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
     * Returns the users whose data may be shown, or null when nothing is restricted.
     *
     * Review finding on issue #18: the restriction was applied to the CSV export
     * only, so the charts could still aggregate over members of other groups. Every
     * cohort query now receives it.
     *
     * @return array|null
     */
    private function get_allowed_userids_for_charts(): ?array {
        return feedback_access::get_allowed_userids($this->get_statistics_context());
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
        $abilityrange = $feedbackhelper->get_ability_range((int) $this->scaleid);

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
        // Build the histogram from the historical attempt snapshots
        // (personability_after_attempt at the time of the attempt), not from the
        // person's current parameter. Person-weighted: one value per person, the
        // latest attempt in the selected period.
        $historicalabilities = catquiz::get_snapshot_ability_per_person($attempts, 'last');
        $abilityrecords = array_map(fn ($ability) => (object) ['ability' => $ability], $historicalabilities);
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
        // Judged in the context these statistics refer to (the quiz module
        // or the course), never in the system context alone.
        $canviewall = feedback_access::can_view_other_users($this->get_statistics_context());
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
                'charttitle' => get_string('progress', 'local_catquiz', $scalename),
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
                'charttitle' => get_string('progress', 'local_catquiz', $scalename),
                'chart' => $this->get_nodata_body(),
            ];
        }
        $progresscomparison = $this->render_chart_for_comparison(
            $attemptsofuser,
            $attemptsofpeers,
            (array) $this->scaleid,
            $timerange,
            [$beginningoftimerange, $this->endtime]
        );

        return [
            'charttitle' => get_string('progress', 'local_catquiz', $scalename),
            'chart' => $progresscomparison,
        ];
    }

    /**
     * Render the charts that show the number of questions answered by users.
     *
     * @return array
     */
    public function render_responses_by_users_chart() {
        global $OUTPUT;

        // The chart only ever needed the number of people per range and
        // class - it counted the rows it had loaded. Loading one row per enrolled
        // person just to count them made memory and runtime grow with the cohort.
        // Both the maximum and the classification now happen in the database, and
        // only the finished counts come back.
        $maxattempts = catquiz::get_max_questions_answered_per_person(
            $this->contextid,
            $this->scaleid,
            $this->courseid,
            $this->get_allowed_userids_for_charts()
        );

        if ($maxattempts === 0) {
            $maxattempts = self::DEFAULT_MAX_ATTEMPTS;
        }
        $classwidth = (int) ceil($maxattempts / self::ATTEMPTS_PER_PERSON_CLASSES);

        if (!$qs = $this->get_quizsettings()) {
            $numranges = 1;
        } else {
            $numranges = $qs->numberoffeedbackoptionsselect;
        }

        $counts = catquiz::get_answers_per_person_histogram(
            $this->contextid,
            $this->scaleid,
            $this->courseid,
            $classwidth,
            $qs ? feedback_helper::get_feedback_range_bounds($qs, $this->scaleid) : [],
            $this->get_allowed_userids_for_charts()
        );

        if (empty($counts)) {
            return [
                'charttitle' => get_string('responsesbyusercharttitle', 'local_catquiz'),
                'chart' => $this->get_nodata_body(),
            ];
        }

        // Initialize the data to 0 for all ranges and bins.
        $data = [];
        for ($i = 0; $i <= $numranges; $i++) {
            for ($j = 0; $j <= self::ATTEMPTS_PER_PERSON_CLASSES; $j++) {
                $data[$i][$j] = 0;
            }
        }

        foreach ($counts as $range => $bins) {
            // Without usable quiz settings everything with an ability goes to the
            // fallback range, exactly as before.
            if ($range > 0 && !$qs) {
                $range = self::FALLBACK_RANGE;
            }
            foreach ($bins as $bin => $frequency) {
                if (!isset($data[$range][$bin])) {
                    // A class beyond the configured number of classes; the previous
                    // implementation dropped these silently as well.
                    continue;
                }
                $data[$range][$bin] += $frequency;
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
                array_values($data[$range])
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
        // The get_attempts() helper returns a recordset, which holds a database resource until
        // it is closed. Iterating it with foreach and walking away leaves that
        // resource open for the rest of the request - on a page that renders several
        // charts, several at once.
        $recordset = catquiz::get_attempts(
            null,
            $this->scaleid,
            $this->courseid,
            $this->testid,
            $this->contextid,
            $this->starttime,
            $this->endtime,
            // Historical cohorts must not change when a person is
                // later unenrolled -> include by historical participation.
                false,
            // Only the columns the charts actually read. The debug
                // trace field in particular is never used here and can be large.
                // personability_after_attempt is what the charts plot:
                // get_snapshot_ability_per_person() reads it off every attempt, and a
                // missing snapshot is treated as a legacy attempt and dropped. Left
                // out of this list, every value became null and the ability profile
                // rendered with correct axes and no data at all - a chart that looks
                // built rather than broken.
                'a.id, a.userid, a.scaleid, a.contextid, a.courseid, a.attemptid, '
                    . 'a.starttime, a.endtime, a.json, a.timecreated, '
                    . 'a.personability_after_attempt'
        );

        foreach ($recordset as $record) {
            $json = json_decode($record->json);
            $prunedrecord = $record;
            $prunedrecord->json = json_encode((object) [
                'personabilities_abilities' => $json->personabilities_abilities ?? null,
                'personabilities' => $json->personabilities ?? null,
            ]);
            $attempts[] = $prunedrecord;
        }
        $recordset->close();

        $this->attempts = $attempts;
        return $attempts;
    }

    /**
     * Return attempts for the time range of this object
     *
     * @param bool $allowempty
     * @param bool $perperson If true, reduce to one value per person and period.
     * @return array
     */
    private function get_attempts_by_timerange(bool $allowempty = false, bool $perperson = false): array {
        $cachekey = ($allowempty ? '1' : '0') . ($perperson ? '1' : '0');
        if (isset($this->attemptsbytimerange[$cachekey])) {
            return $this->attemptsbytimerange[$cachekey];
        }

        $records = [];
        foreach (
            catquiz::get_attempts(
                null,
                $this->rootscaleid,
                $this->courseid,
                $this->testid,
                $this->contextid,
                $this->starttime,
                $this->endtime,
                // Historical participation (see get_attempts()).
                false
            ) as $record
        ) {
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
        $this->attemptsbytimerange[$cachekey] = feedback_helper::order_attempts_by_timerange(
            $records,
            $this->scaleid,
            $timerange,
            $allowempty,
            $perperson
        );
        return $this->attemptsbytimerange[$cachekey];
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

        $this->quizsettingcompatibility[$level] = true;
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
                    $basetestid = $testid;
                }

                if (
                    round($qs->$startkey, 3) !== round($rangestart, 3) || round($qs->$endkey, 3) !== round($rangeend, 3)
                    || ($level === self::COMPATIBILITY_LEVEL_DESCRIPTION && trim($qs->$textkey) !== trim($rangetext))
                ) {
                    $this->quizsettingcompatibility[$level] = false;
                    if (
                        $CFG->debug > 0 && feedback_access::can_view_other_users($this->get_statistics_context())
                    ) {
                        if (round($qs->$startkey, 3) !== round($rangestart, 3) || round($qs->$endkey, 3) !== round($rangeend, 3)) {
                            echo sprintf(
                                '<div class="alert alert-warning" role="alert">Quiz settings are not compatible:
                                different range values [%f, %f] for test %d and range values [%f, %f] for test %d.</div>',
                                $rangestart,
                                $rangeend,
                                $basetestid,
                                $qs->$startkey,
                                $qs->$endkey,
                                $testid
                            );
                        }
                        if (trim($qs->$textkey) !== trim($rangetext)) {
                            echo sprintf(
                                '<div class="alert alert-warning" role="alert">Quiz settings are not compatible:
                                different range descriptions for test %d and test %d in scale %d and range %d.</div>',
                                $basetestid,
                                $testid,
                                $this->scaleid,
                                $r
                            );
                        }
                    }
                }
            }
        }

        return $this->quizsettingcompatibility[$level];
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

        $orderedattemptspeers = feedback_helper::order_attempts_by_timerange(
            $attemptsofpeers,
            $this->scaleid,
            $timerange,
            false,
            true
        );
        $pa = $this->assign_average_result_to_timerange($orderedattemptspeers);
        $orderedattemptsuser = feedback_helper::order_attempts_by_timerange(
            $attemptsofuser,
            $this->scaleid,
            $timerange,
            false,
            true
        );
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
        global $OUTPUT;

        // This chart loaded one row per person only to count them per
        // range and class. Both the maximum and the classification happen in the
        // database now; only the finished counts come back.
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

        $maxattempts = catquiz::get_max_attempts_per_person(
            $this->contextid,
            $this->scaleid,
            $this->courseid,
            $this->get_allowed_userids_for_charts()
        );
        if ($maxattempts == 0) {
            $maxattempts = self::DEFAULT_MAX_ATTEMPTS;
        }

        // Display a maximum of self::ATTEMPTS_PER_PERSON_CLASSES bars. This
        // means, that each bar covers a range of $classwidth attempts.
        $classwidth = (int) ceil($maxattempts / self::ATTEMPTS_PER_PERSON_CLASSES);

        $counts = catquiz::get_attempts_per_person_histogram(
            $this->contextid,
            $this->scaleid,
            $this->courseid,
            $classwidth,
            $qs ? feedback_helper::get_feedback_range_bounds($qs, $this->scaleid) : [],
            $this->get_allowed_userids_for_charts()
        );

        if (empty($counts)) {
            return [
                'charttitle' => get_string('catquizstatistics_numattemptsperperson_title', 'local_catquiz'),
                'chart' => $this->get_nodata_body(),
            ];
        }

        // Initialize all ranges of all possible attempt counts to 0.
        for ($i = 0; $i <= $maxrange; $i++) {
            for ($j = 0; $j <= self::ATTEMPTS_PER_PERSON_CLASSES; $j++) {
                $chartdata[$i][$j] = 0;
            }
        }

        foreach ($counts as $range => $bins) {
            // Without usable quiz settings everything with an ability goes to range 1,
            // exactly as the previous implementation did.
            if ($range > 0 && !$qs) {
                $range = 1;
            }
            foreach ($bins as $bin => $frequency) {
                if (!isset($chartdata[$range][$bin])) {
                    // A class beyond the configured number of classes; the previous
                    // implementation dropped these as well.
                    continue;
                }
                $chartdata[$range][$bin] += $frequency;
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
            // This chart aggregates in SQL, and $records - the row
            // per person - no longer exists. The debug table now shows what the chart
            // actually draws: the counts per range and class. Leaving the old loop in
            // place was an undefined variable waiting for someone to append ?debug=1.
            $thead = "
                <thead>
                  <tr>
                    <th>range</th>
                    <th>class</th>
                    <th>people</th>
                  </tr>
                </thead>";
            $tr = "";
            foreach ($chartdata as $range => $bins) {
                foreach ($bins as $bin => $frequency) {
                    $tr .= "<tr><td>$range</td><td>$bin</td><td>$frequency</td></tr>";
                }
            }
            $table = "<table class=\"table\">$thead<tbody>$tr</tbody></table>";
            $out .= $table;
        }

        if ($qs = $this->get_quizsettings()) {
            try {
                $legend = feedback_helper::get_colorbarlegend(
                    $qs,
                    $this->scaleid,
                    $this->check_quizsettings_are_compatible(self::COMPATIBILITY_LEVEL_DESCRIPTION),
                    true
                );
                $colorbarlegend = ['feedbackbarlegend' => $legend];
            } catch (LogicException $e) {
                // We should have better validation for valid quiz settings.
                // Until then, if the quizsettings don't have a range for the
                // given scale, we don't show the legend.
                $colorbarlegend = false;
            }
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
        if (!feedback_access::can_view_other_users($this->get_statistics_context())) {
            return sprintf(
                '<div class="alert alert-primary mt-1" role="alert">%s</div>',
                get_string('error:permissionforcsvdownload', 'local_catquiz', 'local/catquiz:view_users_feedback')
            );
        }

        $params = [
            'cid' => $this->courseid,
            'scaleid' => $this->scaleid,
            'courseid' => $this->courseid,
            'testid' => $this->testid,
            'starttime' => $this->starttime,
            'endtime' => $this->endtime,
        ];

        $url = (new moodle_url('/local/catquiz/export_statistics_csv.php', $params))->out(false);
        return sprintf(
            '<a class="btn btn-info" style="margin: 1em 0" id="download-link" href="%s">%s</a>',
            $url,
            get_string('download', 'admin')
        );
    }

    /**
     * Returns the data that can be downloaded as csv.
     *
     * @return array
     */
    public function get_export_data(): array {
        global $DB;

        // The previous check called context_course::instance($this->courseid)
        // unconditionally, which threw as soon as the statistics were not scoped to a
        // course (site wide shortcode). The resolver degrades to the system context
        // instead, and the rule itself now lives in one place.
        if (!feedback_access::can_view_other_users($this->get_statistics_context())) {
            return [];
        }

         [$sql, $params] = catquiz::get_sql_for_csv_export(
             $this->contextid,
             $this->scaleid,
             $this->courseid,
             $this->testid,
             $this->starttime,
             $this->endtime,
             // The export must use the same cohort rule as the charts
             // (historical participation), so unenrolment does not diverge them.
             false
         );

        $data = [];
        // In separate groups mode a teacher without accessallgroups may
        // only export members of their own groups. Null means "no restriction", so
        // the common case costs nothing.
        $alloweduserids = feedback_access::get_allowed_userids($this->get_statistics_context());
        foreach ($DB->get_recordset_sql($sql, $params) as $r) {
            if ($alloweduserids !== null && !in_array((int) $r->userid, $alloweduserids, true)) {
                continue;
            }
            $r->status = get_string('attemptstatus_' . $r->status, 'local_catquiz');
            // phpcs:disable
            // TODO: To be implemented: 'Ergebnis-Range', 'N global', 'frac global', 'N Ergebnisskala', 'frac Ergebnisskala'.
            $additionalresults = json_decode($r->json);

            $r->testid = $additionalresults->testid ?? '';

            $globalscale = $additionalresults->catscaleid ?? null;
            $r->globalid = $globalscale ?? '';
            $r->globalname = $globalscale ? $additionalresults->catscales->$globalscale->name : '';
            $r->globalpp = $additionalresults->personabilities->$globalscale ?? '';
            $r->globalse = $additionalresults->se->$globalscale ?? '';
            /*
            $r->globaln = $additionalresults->n->$globalscale;
            $r->globalf = $additionalresults->frac->$globalscale;
            */
            // phpcs:enable

            $primaryscale = $additionalresults->primaryscale->id ?? null;
            $r->primaryid = $primaryscale ?? '';
            $r->primaryname = $primaryscale ? $additionalresults->catscales->$primaryscale->name : '';
            $r->primarypp = $additionalresults->personabilities->$primaryscale ?? '';
            $r->primaryse = $additionalresults->se->$primaryscale ?? '';

            // phpcs:disable
            /*
            $r->primaryn = $additionalresults->n->$primaryscale;
            $r->primaryf = $additionalresults->frac->$primaryscale;
            */
            // phpcs:enable

            unset($r->json);
            if (!$r->endtime || $r->endtime == 0) {
                $r->endtime = '';
                $r->timediff = '';
            } else {
                $r->timediff = gmdate('H:i:s', $r->endtime - $r->starttime);
                $r->endtime = date("Y-m-d H:i:s", $r->endtime);
            }
            $r->starttime = date("Y-m-d H:i:s", $r->starttime);

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
