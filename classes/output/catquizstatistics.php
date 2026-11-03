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
use local_catquiz\local\statistics\attempt_population;
use local_catquiz\local\statistics\chart_output;
use local_catquiz\local\statistics\learning_progress_series;
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
 * Every chart is built from one population of attempts (issue #88, attempt_population): completed
 * attempts of the root scale in course, test and period, optionally the first or last attempt of each
 * person. Each chart has its data as plain series (get_chart_data()), which is what the tests compare;
 * the rendering only draws them.
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

    /** @var string Histogram of the results per person, with the test information. */
    const CHART_ABILITYPROFILE = 'abilityprofile';

    /** @var string The reported scales of the latest results. */
    const CHART_DETECTEDSCALES = 'detectedscales';

    /** @var string Attempts per period, by range. */
    const CHART_ATTEMPTSPERTIMERANGE = 'attemptspertimerange';

    /** @var string People per number of attempts, by range. */
    const CHART_ATTEMPTSPERPERSON = 'attemptsperperson';

    /** @var string The viewer's progress and that of the cohort. */
    const CHART_LEARNINGPROGRESS = 'learningprogress';

    /**
     * The charts of the shortcode, by name: the key of the template that shows each (issue #85).
     *
     * @var string[]
     */
    const CHARTS = [
        self::CHART_ABILITYPROFILE => 'abilityprofilechart',
        self::CHART_DETECTEDSCALES => 'detectedscales',
        self::CHART_ATTEMPTSPERTIMERANGE => 'attemptspertimerangechart',
        self::CHART_ATTEMPTSPERPERSON => 'attemptsperpersonchart',
        self::CHART_LEARNINGPROGRESS => 'learningprogress',
    ];

    /**
     * The minimum number of people for the learning progress (issue #88: people, not attempts).
     *
     * @var int
     */
    const MIN_PEOPLE_FOR_PROGRESS = 3;

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
     * Attempts a usage chart classified by the reported result of a subscale, attempt id => scale (issue #160).
     *
     * @var array
     */
    private array $usageclassbyprimary = [];

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
     * @var string[] Names of the tests, by adaptivequiz.id.
     */
    private array $testnames = [];

    /**
     * @var array $attemptsbytimerange
     */
    private array $attemptsbytimerange = [];

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
    private array $timerangekeys = [];

    /**
     * @var array Stores the names of teststrategies
     */
    private array $teststrategynames = [];

    /**
     * The attempts every chart is built from (issue #88).
     *
     * @var attempt_population
     */
    private attempt_population $population;

    /**
     * Create a new catquizstatistics object
     *
     * @param ?int $courseid
     * @param ?int $testid
     * @param int $scaleid
     * @param ?int $endtime
     * @param ?int $starttime
     * @param string $selection attempt_population::ALL, FIRST or LAST: every attempt, or the first or last
     *                          completed attempt of each person (issue #88).
     *
     * @return self
     */
    public function __construct(
        ?int $courseid,
        ?int $testid,
        int $scaleid,
        ?int $endtime = null,
        ?int $starttime = null,
        string $selection = attempt_population::ALL
    ) {
        global $DB;

        $this->courseid = $courseid;
        $this->testid = $testid;
        $this->endtime = $endtime ?? time();
        $this->starttime = $starttime;
        $this->scaleid = $scaleid;
        $this->rootscaleid = attempt_population::root_of($this->scaleid);
        $this->contextid = catscale::get_context_id($this->scaleid);
        // No CAT context: it records the calibration an attempt was scored under, not whether it took
        // place - after a recalibration the history would vanish (as in the attempts per person chart and
        // the export, which never filtered by it; issue #88).
        $this->population = new attempt_population(
            $this->scaleid,
            $courseid,
            $testid,
            null,
            $starttime,
            $this->endtime,
            $selection
        );

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
            $this->testnames[$test->componentid] = (string) ($test->name ?? '');
        }
    }

    /**
     * The CAT context the test information of the statistics refers to (issue #150, re-audit 27.10.2026).
     *
     * The context the results in the chart were measured in: that of the latest completed attempt of the population
     * - an attempt is played in the context of its scale at the time (catquiz_handler, catscale::get_context_id()),
     * and the feedback takes the context of its attempt the same way. Without attempts, the context of the scale.
     * Before, the statistics always took the context the scale has now: after the scale moved to another context -
     * a recalibration not yet calculated - "no item of this scale has calculated parameters" stood beside bars
     * measured in a context with parameters for every item.
     *
     * @return int
     */
    public function get_testinfo_contextid(): int {
        $attempts = $this->get_attempts();
        for ($i = count($attempts) - 1; $i >= 0; $i--) {
            if (!empty($attempts[$i]->contextid)) {
                return (int) $attempts[$i]->contextid;
            }
        }
        return (int) $this->contextid;
    }

    /**
     * The population the charts are built from.
     *
     * @return attempt_population
     */
    public function get_population(): attempt_population {
        return $this->population;
    }

    /**
     * The scale of an export: the one asked for, or the one the test or the course uses (issue #141).
     *
     * The export stopped with "required parameter scaleid missing" when it was opened without a scale.
     * A test has its scale; a course whose tests all use one scale has that one. Where that leaves more
     * than one, nothing is guessed - the scale has to be chosen.
     *
     * @param int $scaleid The scale asked for, 0 for none.
     * @param int|null $courseid
     * @param int|null $testid Instance id of the test (local_catquiz_tests.componentid).
     * @return int
     * @throws \moodle_exception When the scale asked for does not exist or none can be determined.
     */
    public static function resolve_scale_id(int $scaleid, ?int $courseid, ?int $testid): int {
        global $DB;

        if ($scaleid > 0) {
            if (!$DB->record_exists('local_catquiz_catscales', ['id' => $scaleid])) {
                throw new \moodle_exception('error:exportscaleunresolved', 'local_catquiz');
            }
            return $scaleid;
        }

        $params = ['component' => 'mod_adaptivequiz'];
        $where = 'component = :component AND catscaleid IS NOT NULL AND catscaleid > 0';
        if ($testid) {
            $where .= ' AND componentid = :testid';
            $params['testid'] = $testid;
        } else if ($courseid) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        } else {
            throw new \moodle_exception('error:exportscaleunresolved', 'local_catquiz');
        }
        $scaleids = $DB->get_fieldset_sql("SELECT DISTINCT catscaleid FROM {local_catquiz_tests} WHERE $where", $params);
        if (count($scaleids) !== 1) {
            throw new \moodle_exception('error:exportscaleunresolved', 'local_catquiz');
        }
        return (int) reset($scaleids);
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
     * Renders one chart by name (issue #85: the charts of the shortcode load one by one).
     *
     * @param string $chart One of the keys of CHARTS.
     * @return array The template data of the chart.
     */
    public function render_chart(string $chart): array {
        switch ($chart) {
            case self::CHART_ABILITYPROFILE:
                return $this->render_abilityprofilechart();
            case self::CHART_DETECTEDSCALES:
                return $this->render_detected_scales_chart();
            case self::CHART_ATTEMPTSPERTIMERANGE:
                return $this->render_attempts_per_timerange_chart();
            case self::CHART_ATTEMPTSPERPERSON:
                return $this->render_attempts_per_person_chart();
            case self::CHART_LEARNINGPROGRESS:
                return $this->render_learning_progress();
        }
        throw new \invalid_parameter_exception('Unknown chart: ' . $chart);
    }

    /**
     * The data of a chart: labels and series, plus the population it was built from (issue #88).
     *
     * 'population' holds 'attempts' (attempts counted) and/or 'persons' (people with a value), so that
     * charts of one scope can be compared. An empty array means the chart has no data to show.
     *
     * @param string $chart One of the keys of CHARTS.
     * @return array ['labels' => array, 'series' => array, 'population' => array, ...]
     */
    public function get_chart_data(string $chart): array {
        switch ($chart) {
            case self::CHART_ABILITYPROFILE:
                return $this->get_abilityprofile_data();
            case self::CHART_DETECTEDSCALES:
                return $this->get_detected_scales_data();
            case self::CHART_ATTEMPTSPERTIMERANGE:
                return $this->get_attempts_per_timerange_data();
            case self::CHART_ATTEMPTSPERPERSON:
                return $this->get_attempts_per_person_data();
            case self::CHART_LEARNINGPROGRESS:
                return $this->get_learning_progress_data();
        }
        throw new \invalid_parameter_exception('Unknown chart: ' . $chart);
    }

    /**
     * The data of the chart of attempts per period.
     *
     * Every attempt of the population in the period its end time falls in; range 0 for an attempt without
     * a valid result for the scale (aborted, invalid). A result of exactly 0.0 is a result (issue #88).
     *
     * @return array
     */
    private function get_attempts_per_timerange_data(): array {
        // Sets the periods of the page (timerangekeys); the attempts themselves are classified below.
        if (!$this->get_attempts_by_timerange(true)) {
            return [];
        }
        $qs = $this->get_quizsettings();
        // Without usable settings: range 1 for "a result", as before.
        $maxrange = $qs ? (int) $qs->numberoffeedbackoptionsselect : 1;
        $countsbyrange = [];
        for ($i = 0; $i <= $maxrange; $i++) {
            foreach ($this->timerangekeys as $timepoint) {
                $countsbyrange[$i][$timepoint] = 0;
            }
        }
        $total = 0;
        $records = $this->get_attempts();
        $timerange = feedback_helper::get_timerange_for_attempts((int) reset($records)->endtime, $this->endtime);
        foreach ($records as $record) {
            $bucket = feedback_helper::return_datestring_label($timerange, (int) $record->endtime);
            if (!isset($countsbyrange[0][$bucket])) {
                continue;
            }
            $total++;
            // Issue #160: by the reported result of the attempt, not only by a measurement of the requested scale.
            $range = $this->usage_class($record, $qs);
            if ($range === null) {
                // A value outside every configured range: left out, as before.
                continue;
            }
            $countsbyrange[$range][$bucket]++;
        }
        return [
            'labels' => array_values($this->timerangekeys),
            'series' => array_map('array_values', $countsbyrange),
            'population' => ['attempts' => $total, 'byprimary' => $this->usageclassbyprimary],
        ];
    }

    /**
     * The class an attempt has in the usage charts ("Anzahl an Testversuchen", "Testversuche pro Person";
     * issue #160).
     *
     * Two modes, chosen by the scale asked for:
     *  - the root scale: the reported primary result of the attempt. Its valid measurement of the root scale when
     *    there is one; otherwise its valid primary result on a subscale (LOWESTSUB, RELSUBS ...), classified by
     *    the feedback ranges of THAT scale - abilities of different scales are never put on one axis;
     *  - a subscale: the valid measurement of this very scale, nothing else.
     * 0 is "no valid result" in the sense of the mode (see usage_noresult_label()); null a value outside every
     * configured range. The ranges are those of the current test settings: no earlier state of them is stored.
     *
     * @param \stdClass $record A record of attempt_population::attempts().
     * @param \stdClass|null $qs The settings of the tests of the page, null when they do not agree.
     * @return int|null
     */
    private function usage_class(\stdClass $record, ?\stdClass $qs): ?int {
        $scaleid = $this->scaleid;
        $value = $record->value;
        if ($value === null && $this->scaleid === $this->rootscaleid && ($record->primaryvalue ?? null) !== null) {
            $scaleid = (int) $record->primaryscaleid;
            $value = (float) $record->primaryvalue;
        }
        if ($value === null) {
            return 0;
        }
        if (!$qs) {
            return 1;
        }
        $range = feedback_helper::get_range_of_value($qs, $scaleid, $value);
        if ($range && $scaleid !== $this->scaleid) {
            $this->usageclassbyprimary[(int) $record->id] = $scaleid;
        }
        return $range ?: null;
    }

    /**
     * The label of class 0 in the usage charts: what "grey" means in the mode of the page (issue #160).
     *
     * @return string
     */
    private function usage_noresult_label(): string {
        return $this->scaleid === $this->rootscaleid
            ? get_string('noreportedresult', 'local_catquiz')
            : get_string('novalidmeasurementofscale', 'local_catquiz');
    }

    /**
     * The note under a usage chart: how it classifies, and that the ranges are today's (issue #160).
     *
     * @param int $byprimary Number of attempts classified by the reported result of a subscale.
     * @return string HTML
     */
    private function usage_note(int $byprimary): string {
        $text = $this->scaleid === $this->rootscaleid
            ? get_string('usagechart_note_reported', 'local_catquiz', $byprimary)
            : get_string('usagechart_note_scale', 'local_catquiz');
        return \html_writer::tag('p', $text, ['class' => 'text-muted small catquiz-usagechart-note']);
    }

    /**
     * Chart grouping by date and counting attempts.
     *
     * @return array
     */
    public function render_attempts_per_timerange_chart() {
        global $OUTPUT;

        $data = $this->get_attempts_per_timerange_data();
        if (!$data) {
            return [
                'charttitle' => get_string('catquizstatistics_numattempts_title', 'local_catquiz'),
                'chart' => $this->get_nodata_body(),
            ];
        }
        $qs = $this->get_quizsettings();
        $colors = $qs
            ? array_values(feedbackclass::get_array_of_colors($qs->numberoffeedbackoptionsselect))
            : [LOCAL_CATQUIZ_DEFAULT_BLACK];
        $chart = new chart_bar();
        $chart->set_stacked(true);
        $chart->set_labels($data['labels']);
        $colors[-1] = LOCAL_CATQUIZ_DEFAULT_GREY;
        $serieslabels = is_null($qs)
            ? [$this->usage_noresult_label(), get_string('hasability', 'local_catquiz')]
            : array_map(fn ($r) => get_string('feedbackrange', 'local_catquiz', $r), array_keys($data['series']));
        $serieslabels[0] = $this->usage_noresult_label();
        foreach ($data['series'] as $range => $counts) {
            $series = new chart_series($serieslabels[$range], $counts);
            $series->set_color($colors[$range - 1]);
            $chart->add_series($series);
        }
        $chart->get_xaxis(0, true)->set_label(get_string('date'));
        $chart->get_yaxis(0, true)->set_label(get_string('numberofattempts', 'local_catquiz'));
        $chart->set_legend_options(['display' => false]);
        // With its data table (issue #151): the counts per day readable without the picture, as the histograms.
        $out = $OUTPUT->render_chart($chart, true);
        $out .= $this->usage_note(count($data['population']['byprimary'] ?? []));

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
        foreach ($attemptsbytimerange as $timestamp => $attempts) {
            $labels[] = (string)$timestamp;
            foreach ($attempts as $attempt) {
                $color = $fh->get_color_for_personability((array)$this->quizsettings, (float) $attempt, $catscaleid);

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
        // Deliberately no restriction. get_allowed_userids() answers "which people
        // may this viewer see as individuals" - the right question for the CSV
        // export, for a participant list, for personal feedback. It is the wrong
        // question for a histogram: "60 people had one attempt" names nobody.
        //
        // Applying it here made the aggregate depend on who is looking. A teacher in
        // one small group saw a course statistic of seven people instead of 87, and
        // the same page contradicted itself because other charts were not filtered.
        //
        // Access to the aggregate is governed by the capability instead, checked
        // before anything is rendered. The person-level paths keep their filter -
        // export and individual feedback below still call get_allowed_userids().
        return null;
    }

    /**
     * The ability steps of the profile: the centres of its bars.
     *
     * @return float[]
     */
    private function get_ability_steps(): array {
        $abilitysteps = [];
        $stepsize = 0.25;
        $interval = $stepsize * 2;
        $abilityrange = (new feedback_helper())->get_ability_range((int) $this->scaleid);
        $ul = (float) $abilityrange['maxscalevalue'];
        $ll = (float) $abilityrange['minscalevalue'];
        for ($i = $ll + $stepsize; $i <= ($ul - $stepsize); $i += $interval) {
            $abilitysteps[] = $i;
        }
        return $abilitysteps;
    }

    /**
     * The data of the ability profile: people per ability step, one value per person.
     *
     * Each person counts once, with their latest valid result for the scale in the population (issue #88)
     * - the value they were shown at the time, not the current estimate. Where the values come from, and how many
     * persons have a valid reported result at all, is counted along (issue #150, re-audit 28.10.2026).
     *
     * @return array
     */
    private function get_abilityprofile_data(): array {
        $stepsize = 0.25;
        $attempts = $this->get_attempts();
        $values = attempt_population::value_per_person($attempts, 'last');
        // The source of each person's value: the same attempt value_per_person() took - the latest with a value.
        $sources = [];
        $attemptpersons = [];
        $primarypersons = [];
        $noresult = 0;
        foreach ($attempts as $attempt) {
            $attemptpersons[(int) $attempt->userid] = true;
            if (!empty($attempt->primaryvalid)) {
                $primarypersons[(int) $attempt->userid] = true;
            }
            if (($attempt->provenance ?? null) === \local_catquiz\local\result\result_provenance::NORESULT) {
                $noresult++;
            }
            if ($attempt->value !== null) {
                $sources[(int) $attempt->userid] = $attempt->valuesource ?? 'reported';
            }
        }
        $bysource = array_merge(['reported' => 0, 'measured' => 0, 'legacy' => 0], array_count_values($sources));
        $steps = $this->get_ability_steps();
        // The bars (step - 0.25, step + 0.25]; the first also holds the minimum of the scale (issue #150).
        $counts = chart_output::counts_per_step($steps, $values, $stepsize);
        return [
            'labels' => chart_output::step_labels($steps),
            'series' => ['counter' => $counts],
            'population' => [
                'persons' => count($values),
                'values' => $values,
                'attempts' => count($attempts),
                'attemptpersons' => count($attemptpersons),
                'withoutvalue' => count(array_filter($attempts, fn($a) => $a->value === null)),
                'noresult' => $noresult,
                'primarypersons' => count($primarypersons),
                'bysource' => $bysource,
            ],
        ];
    }

    /**
     * The scope of the statistics in words: which tests, which period (issue #150, re-audit 28.10.2026).
     *
     * Without a test the statistics take every test of the scale in the course (or on the site) - not only one.
     *
     * @return string
     */
    private function scope_description(): string {
        if ($this->testid) {
            $name = $this->testnames[$this->testid] ?? '';
            $tests = get_string('abilityprofilescopetest', 'local_catquiz', $name !== '' ? $name : (string) $this->testid);
        } else {
            $tests = get_string(
                $this->courseid ? 'abilityprofilescopetestscourse' : 'abilityprofilescopetestssite',
                'local_catquiz',
                count($this->quizsettings)
            );
        }
        $to = userdate($this->endtime, get_string('strftimedatetimeshort', 'langconfig'));
        $period = $this->starttime
            ? get_string('abilityprofilescopebetween', 'local_catquiz', (object) [
                'from' => userdate($this->starttime, get_string('strftimedatetimeshort', 'langconfig')), 'to' => $to,
            ])
            : get_string('abilityprofilescopeuntil', 'local_catquiz', $to);
        return get_string('abilityprofilescope', 'local_catquiz', (object) ['tests' => $tests, 'period' => $period]);
    }

    /**
     * Render chart for histogram of person abilities
     *
     * @return array
     */
    public function render_abilityprofilechart() {
        $feedbackhelper = new feedback_helper();
        $abilitysteps = $this->get_ability_steps();
        // Build the histogram from the historical results of the attempts, not from the person's current
        // parameter. Person-weighted: one value per person, the latest valid one in the selection.
        $data = $this->get_abilityprofile_data();
        $scalename = catscale::return_catscale_object($this->scaleid)->name;
        // Teachers and CAT managers can see the test information in addition to the ability.
        // Judged in the context these statistics refer to (the quiz module
        // or the course), never in the system context alone.
        $canviewall = feedback_access::can_view_other_users($this->get_statistics_context());

        // Who the bars are (re-audit 27.10.2026): persons, one value each, out of how many attempts; where the values
        // come from, and the scope (re-audit 28.10.2026).
        $population = \html_writer::div(
            s(get_string('abilityprofilepopulation', 'local_catquiz', (object) [
                'persons' => $data['population']['persons'],
                'scale' => $scalename,
                'attempts' => $data['population']['attempts'],
                'attemptpersons' => $data['population']['attemptpersons'],
                'withoutvalue' => $data['population']['withoutvalue'],
                'reported' => $data['population']['bysource']['reported'],
                'measured' => $data['population']['bysource']['measured'],
                'legacy' => $data['population']['bysource']['legacy'],
            ]) . ' ' . $this->scope_description()),
            'small text-muted',
            ['data-region' => 'local_catquiz-abilityprofile-population']
        );

        if ($data['population']['persons'] === 0 || array_sum($data['series']['counter']) === 0) {
            // No bar: say why (issue #150, re-audit 28.10.2026). When the attempts report other scales - the subscales
            // of a test under "lowest skill gap" -, say so: there are results, only none in this scale. The test
            // information of the pool does not depend on the persons: for those who may see it, it is shown on its
            // own (re-audit 02.11.2026).
            $notice = $data['population']['persons'] === 0 && $data['population']['primarypersons'] > 0
                ? get_string('abilityprofilenoreportedvalue', 'local_catquiz', (object) [
                    'scale' => $scalename,
                    'attempts' => $data['population']['attempts'],
                    'primarypersons' => $data['population']['primarypersons'],
                ])
                : get_string('abilityprofilenodata', 'local_catquiz');
            return [
                'chart' => \html_writer::div(
                    chart_output::notice($notice),
                    '',
                    ['data-region' => 'local_catquiz-abilityprofile-novalue']
                ) . $population . ($canviewall
                    ? $this->testinfo_without_bars($feedbackhelper, $abilitysteps, $data, $scalename)
                    : ''),
                'charttitle' => get_string('abilityprofile', 'local_catquiz', $scalename),
            ];
        }

        // Issue #150: the test information covers every step or is left out, not invented - an empty pool, only
        // pilot items, parameters not calculated yet. The bars do not depend on it. Re-audit 27.10.2026: in the
        // CAT context of the tests, with the reason when it is missing. Only for those who see it (28.10.2026).
        $testinfodata = $canviewall
            ? $feedbackhelper->testinfo_for_scale((int) $this->scaleid, $this->get_testinfo_contextid(), $abilitysteps)
            : null;
        $testinfo = $testinfodata['values'] ?? null;
        $quizsettings = reset($this->quizsettings); // TODO: check if the settings match for all tests.
        $colors = [];
        foreach ($abilitysteps as $as) {
            $colors[] = $feedbackhelper->get_color_for_personability(
                (array) $quizsettings,
                $as,
                intval($this->scaleid)
            );
        }

        $aserieslabel = get_string('scalescorechartlabel', 'local_catquiz', $scalename);
        $aseries = new chart_series($aserieslabel, $data['series']['counter']);
        $aseries->set_colors($colors);

        $chart = new chart_bar();
        $notes = '';
        if ($canviewall && $testinfo !== null) {
            $testinfolabel = get_string('testinfolabel', 'local_catquiz');
            $tiseries = new chart_series($testinfolabel, $feedbackhelper->scalevalues($testinfo, $data['series']['counter']));
            $tiseries->set_type(chart_series::TYPE_LINE);
            $tiseries->set_smooth(true);
            $chart->add_series($tiseries);
        }
        if ($canviewall) {
            $notes = feedback_helper::testinfo_note($testinfodata, $scalename);
            // Re-audit 28.10.2026: attempts of several calibrations - the information refers to one of them only.
            $contexts = array_unique(array_filter(array_map(fn($a) => (int) $a->contextid, $this->get_attempts())));
            if (count($contexts) > 1) {
                global $DB;
                $contextname = $DB->get_field('local_catquiz_catcontext', 'name', ['id' => $testinfodata['contextid']]);
                $notes .= \html_writer::div(
                    s(get_string('testinfomixedcontexts', 'local_catquiz', (object) [
                        'count' => count($contexts),
                        'context' => ($contextname !== false && $contextname !== null && $contextname !== '')
                            ? $contextname : (string) $testinfodata['contextid'],
                    ])),
                    'small text-muted',
                    ['data-region' => 'local_catquiz-testinfo-contexts']
                );
            }
        }

        $chart->set_legend_options(['display' => $canviewall]);
        $chart->add_series($aseries);
        // Issue #150: the bars are the ability steps - not the keys of the test information, which may be empty.
        $chart->set_labels($data['labels']);
        $chart->get_xaxis(0, true)->set_label(get_string('personability', 'local_catquiz'));
        // Re-audit 27.10.2026: what the height of a bar counts.
        $chart->get_yaxis(0, true)->set_label(get_string('abilityprofilepersons', 'local_catquiz'));

        $nodata = get_string('abilityprofilenodata', 'local_catquiz');
        return [
            // With the data table: the values of the bars for screen readers and to check (issue #150).
            'chart' => chart_output::render($chart, true, $nodata) . $population . $notes,
            'charttitle' => get_string('abilityprofile', 'local_catquiz', $scalename),
        ];
    }

    /**
     * The test information of the pool when no person has a valid value of the scale (re-audit 02.11.2026, #150).
     *
     * The information of the items with their active parameters does not depend on who took the test: it is shown
     * on its own, unscaled, as a line over the ability steps - not with the bars it would otherwise be scaled to.
     * Without it, the reason (no items, no parameters, not computable), as with bars.
     *
     * @param feedback_helper $feedbackhelper
     * @param array $abilitysteps
     * @param array $data get_abilityprofile_data()
     * @param string $scalename
     * @return string HTML
     */
    private function testinfo_without_bars(
        feedback_helper $feedbackhelper,
        array $abilitysteps,
        array $data,
        string $scalename
    ): string {
        $testinfodata = $feedbackhelper->testinfo_for_scale(
            (int) $this->scaleid,
            $this->get_testinfo_contextid(),
            $abilitysteps
        );
        $notes = feedback_helper::testinfo_note($testinfodata, $scalename);
        $testinfo = $testinfodata['values'] ?? null;
        if ($testinfo === null) {
            return $notes;
        }
        $chart = new \core\chart_line();
        $series = new chart_series(get_string('testinfolabel', 'local_catquiz'), array_values($testinfo));
        $series->set_smooth(true);
        $chart->add_series($series);
        $chart->set_labels($data['labels']);
        $chart->set_legend_options(['display' => true]);
        $chart->get_xaxis(0, true)->set_label(get_string('personability', 'local_catquiz'));
        $chart->get_yaxis(0, true)->set_label(get_string('testinfolabel', 'local_catquiz'));
        return \html_writer::div(
            \html_writer::div(s(get_string('testinfowithoutbars', 'local_catquiz', $scalename)), 'small text-muted')
                . chart_output::render($chart, true, get_string('abilityprofilenodata', 'local_catquiz'))
                . $notes,
            '',
            ['data-region' => 'local_catquiz-testinfo-withoutbars']
        );
    }

    /**
     * The data of the detected scales: people per reported scale and range.
     *
     * One attempt per person: the latest whose reported (primary) scale has a valid result (issue #88) -
     * an invalid repetition does not hide the valid result before it.
     *
     * @return array
     */
    private function get_detected_scales_data(): array {
        $latestattempts = [];
        foreach ($this->get_attempts() as $attempt) {
            if (empty($attempt->primaryvalid)) {
                continue;
            }
            $latestattempts[$attempt->userid] = $attempt;
        }
        $chartdata = [];
        $quizsettings = $this->get_quizsettings();
        foreach ($latestattempts as $userid => $attempt) {
            // Skip old attempts that do not yet have the personabilities_abilities property.
            $json = json_decode($attempt->json);
            if (!isset($json->personabilities_abilities)) {
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
            return [];
        }

        // Sort the chart in descending order of attempts across all ranges.
        $tmp = [];
        foreach ($chartdata as $scaleid => $rangearray) {
            $num = array_sum(array_map(fn ($range) => count($range), $rangearray));
            $tmp[$scaleid] = $num;
        }
        arsort($tmp);
        $chartdatasorted = [];
        foreach (array_keys($tmp) as $scaleid) {
            $chartdatasorted[$scaleid] = $chartdata[$scaleid];
        }

        // Keep only the top 10.
        $chartdatasorted = array_slice($chartdatasorted, 0, self::MAX_DETECTED_SCALES, true);

        $series = [];
        if ($this->check_quizsettings_are_compatible()) {
            foreach (range(1, $this->get_max_range()) as $range) {
                $series[$range] = [];
                foreach (array_keys($chartdatasorted) as $scaleid) {
                    $series[$range][] = count($chartdatasorted[$scaleid][$range] ?? []);
                }
            }
        } else {
            $series[0] = [];
            foreach (array_keys($chartdatasorted) as $scaleid) {
                $series[0][] = array_sum(array_map(fn ($range) => count($range), $chartdatasorted[$scaleid]));
            }
        }
        $persons = 0;
        foreach ($chartdata as $rangearray) {
            $persons += array_sum(array_map(fn ($range) => count($range), $rangearray));
        }

        return [
            'labels' => array_map(fn ($scaleid) => catscale::return_catscale_object($scaleid)->name, array_keys($chartdatasorted)),
            'scaleids' => array_keys($chartdatasorted),
            'series' => $series,
            'population' => ['persons' => $persons],
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
        global $OUTPUT;

        $data = $this->get_detected_scales_data();
        if (!$data) {
            return [
                'charttitle' => get_string('chart_detectedscales_title', 'local_catquiz', self::MAX_DETECTED_SCALES),
                'chart' => $this->get_nodata_body(),
            ];
        }

        $chart = new chart_bar();
        $chart->set_stacked(true);
        $chart->set_horizontal(true);
        // Add each range as separate chart series.
        if ($this->check_quizsettings_are_compatible()) {
            $colors = array_values(feedbackclass::get_array_of_colors($this->get_max_range()));
            foreach ($data['series'] as $range => $counts) {
                $series = new chart_series(get_string('feedbackrange', 'local_catquiz', $range), $counts);
                $series->set_color($colors[$range - 1]);
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
            $series = new chart_series(get_string('selected_scales_all_ranges_label', 'local_catquiz'), $data['series'][0]);
            $series->set_color(LOCAL_CATQUIZ_DEFAULT_GREY);
            $chart->add_series($series);
            $colorbarlegend = false;
        }

        $chart->set_labels($data['labels']);
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
     * The data of the learning progress: the viewer's series and that of the cohort.
     *
     * At least MIN_PEOPLE_FOR_PROGRESS people, and as many others than the viewer - people, not attempts
     * (issue #88): three attempts of one person were enough before.
     *
     * @return array
     */
    private function get_learning_progress_data(): array {
        global $USER;

        $userid = (int) $USER->id;
        $records = $this->get_attempts();
        $people = array_unique(array_map(fn($r) => (int) $r->userid, $records));
        if (count($people) < self::MIN_PEOPLE_FOR_PROGRESS) {
            return [];
        }
        $peers = array_filter($people, fn($id) => $id !== $userid);
        if (count($peers) < self::MIN_PEOPLE_FOR_PROGRESS) {
            return [];
        }
        // The periods begin with the earliest finished attempt (issue #119); the population is ordered by it.
        $beginningoftimerange = (int) reset($records)->endtime;
        $timerange = feedback_helper::get_timerange_for_attempts($beginningoftimerange, $this->endtime);
        $series = learning_progress_series::comparison(
            $records,
            $this->scaleid,
            $userid,
            $timerange,
            [$beginningoftimerange, $this->endtime]
        );
        // If we do not have enough data, return.
        if (!array_filter($series['user'], fn($v) => $v !== null) && !array_filter($series['peers'], fn($v) => $v !== null)) {
            return [];
        }
        return [
            'labels' => $series['labels'],
            'series' => ['peers' => $series['peers'], 'user' => $series['user']],
            'population' => ['persons' => count($people)],
        ];
    }

    /**
     * Render the charts to display the learning progress.
     *
     * @return array
     */
    public function render_learning_progress() {
        global $OUTPUT;

        $scalename = catscale::return_catscale_object($this->scaleid)->name;
        $data = $this->get_learning_progress_data();
        if (!$data) {
            return [
                'charttitle' => get_string('progress', 'local_catquiz', $scalename),
                'chart' => $this->get_nodata_body(),
            ];
        }

        // Both series are cumulative: for each period the latest valid result of every person up to
        // then (issue #119, learning_progress_series) - the same series as the personal feedback.
        $chart = new chart_line();
        // Straight lines: a smoothed curve between periods suggests values in between (issue #119).
        $chart->set_smooth(false);
        // The title names the scale; it was shown with its placeholder "{$a}".
        $peerstitle = get_string('catquizstatistics_progress_peers_title', 'local_catquiz', $scalename);
        $personaltitle = get_string('catquizstatistics_progress_personal_title', 'local_catquiz');
        $chart->add_series(new chart_series($peerstitle, $data['series']['peers']));
        $chart->add_series(new chart_series($personaltitle, $data['series']['user']));
        $chart->set_labels($data['labels']);
        $chart->get_xaxis(0, true)->set_label(get_string('date'));
        $chart->get_yaxis(0, true)->set_label(get_string('personability', 'local_catquiz'));

        return [
            'charttitle' => get_string('progress', 'local_catquiz', $scalename),
            // With the table of values: the chart can be read without seeing it.
            'chart' => $OUTPUT->render_chart($chart, true),
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
        // only the finished counts come back. Test and period reach the query (issue #88).
        $maxattempts = catquiz::get_max_questions_answered_per_person(
            $this->contextid,
            $this->scaleid,
            $this->courseid,
            $this->get_allowed_userids_for_charts(),
            $this->testid,
            $this->starttime,
            $this->endtime
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
            $this->get_allowed_userids_for_charts(),
            $this->testid,
            $this->starttime,
            $this->endtime
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
     * The attempts of the population, in the order of their end time (issue #88).
     *
     * Each record carries the valid result for the scale ('value') and whether the reported scale of the
     * attempt has a valid result ('primaryvalid'); see attempt_population::attempts(). The aggregate charts
     * apply no personal group list: the totals must not depend on who is looking.
     *
     * @return array
     */
    private function get_attempts(): array {
        return $this->population->attempts();
    }

    /**
     * Return attempts for the time range of this object
     *
     * The values per period, by the period the end time of an attempt falls in - not its start (issue
     * #88): an attempt begun before midnight and finished after it belongs to the second day.
     *
     * @param bool $allowempty Also attempts without a valid result, as null.
     * @param bool $perperson If true, reduce to one value per person and period.
     * @return array
     */
    private function get_attempts_by_timerange(bool $allowempty = false, bool $perperson = false): array {
        $cachekey = ($allowempty ? '1' : '0') . ($perperson ? '1' : '0');
        if (isset($this->attemptsbytimerange[$cachekey])) {
            return $this->attemptsbytimerange[$cachekey];
        }

        $records = $this->get_attempts();
        // One attempt is enough for a chart: it is counted by every other chart of the page as well.
        if (!$records) {
            return [];
        }
        // The population holds completed attempts only, ordered by their end time.
        $beginningoftimerange = (int) reset($records)->endtime;
        $timerange = feedback_helper::get_timerange_for_attempts($beginningoftimerange, $this->endtime);
        $this->timerangekeys = feedback_helper::get_timerangekeys($timerange, [$beginningoftimerange, $this->endtime]);

        $bytimerange = [];
        $perpersonvalues = [];
        foreach ($records as $record) {
            $bucket = feedback_helper::return_datestring_label($timerange, (int) $record->endtime);
            if ($perperson) {
                if ($record->value !== null) {
                    // Ordered by end time: the latest valid value of a person in the period wins.
                    $perpersonvalues[$bucket][(int) $record->userid] = $record->value;
                }
                continue;
            }
            if ($record->value === null && !$allowempty) {
                continue;
            }
            $bytimerange[$bucket][] = $record->value;
        }
        if ($perperson) {
            $bytimerange = array_map('array_values', $perpersonvalues);
        }
        $this->attemptsbytimerange[$cachekey] = $bytimerange;
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
     * The data of the attempts per person: people per number of attempts, by range.
     *
     * Counted in the database from the same population (issue #88). Class 0 holds the people who could
     * have taken a test of the scope and have no attempt in it (issue #132, eligible_population) - the one
     * deliberate difference to the other charts, which count attempts and results.
     *
     * @return array
     */
    private function get_attempts_per_person_data(): array {
        if (!$qs = $this->get_quizsettings()) {
            // Use range 0 for missing person ability and range 1 for everything else.
            $maxrange = 1;
        } else {
            $maxrange = $qs->numberoffeedbackoptionsselect;
        }

        // Issue #160: per person from the population of the page - the same scope, selection and groups as every
        // other chart, without a query of its own. The class is that of the person's latest attempt with a valid
        // result in the mode of the page (usage_class()); the number counts all their attempts in the scope.
        $persons = [];
        $this->usageclassbyprimary = [];
        // This chart shows people: it keeps the group restriction it always had.
        $allowed = $this->get_allowed_userids_for_charts();
        foreach ($this->population->attempts('', $allowed === null ? null : array_map('intval', $allowed)) as $record) {
            $userid = (int) $record->userid;
            $persons[$userid] = $persons[$userid] ?? ['attempts' => 0, 'range' => 0];
            $persons[$userid]['attempts']++;
            $range = $this->usage_class($record, $qs);
            if ($range) {
                // Ordered by end time: the latest classified attempt wins.
                $persons[$userid]['range'] = $range;
            }
        }
        // Who could have taken a test of the scope and has no attempt in it (issue #132).
        $eligible = \local_catquiz\local\statistics\eligible_population::userids(
            $this->scaleid,
            $this->courseid ?: null,
            $this->testid ?: null
        );
        if ($allowed !== null) {
            $eligible = array_intersect($eligible, array_map('intval', $allowed));
        }
        $noattempt = count(array_diff(array_map('intval', $eligible), array_keys($persons)));

        $maxattempts = $persons ? max(array_column($persons, 'attempts')) : 0;
        if ($maxattempts == 0) {
            $maxattempts = self::DEFAULT_MAX_ATTEMPTS;
        }

        // Display a maximum of self::ATTEMPTS_PER_PERSON_CLASSES bars. This
        // means, that each bar covers a range of $classwidth attempts.
        $classwidth = (int) ceil($maxattempts / self::ATTEMPTS_PER_PERSON_CLASSES);

        $counts = [];
        if ($noattempt > 0) {
            $counts[0][0] = $noattempt;
        }
        foreach ($persons as $person) {
            $bin = (int) ceil($person['attempts'] / $classwidth);
            $counts[$person['range']][$bin] = ($counts[$person['range']][$bin] ?? 0) + 1;
        }

        if (empty($counts)) {
            return [];
        }

        // Initialize all ranges of all possible attempt counts to 0.
        $chartdata = [];
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

        $labels = [get_string('notyetattempted', 'local_catquiz')];
        for ($i = 1; $i <= self::ATTEMPTS_PER_PERSON_CLASSES; $i++) {
            if ($classwidth == 1) {
                $labels[$i] = sprintf('%d', $i * $classwidth);
            } else {
                $labels[$i] = sprintf('%d .. %d', $i * $classwidth - $classwidth + 1, $i * $classwidth);
            }
        }

        // What the chart is built from: with one attempt per class, the attempts can be counted back.
        $persons = 0;
        $withvalue = 0;
        $attempts = 0;
        foreach ($chartdata as $range => $bins) {
            foreach ($bins as $bin => $frequency) {
                if ($bin === 0) {
                    continue;
                }
                $persons += $frequency;
                $withvalue += $range > 0 ? $frequency : 0;
                $attempts += $bin * $frequency;
            }
        }

        return [
            'labels' => array_values($labels),
            'series' => $chartdata,
            'classwidth' => $classwidth,
            'population' => [
                'attempts' => $classwidth === 1 ? $attempts : null,
                'persons' => $persons,
                'personswithvalue' => $withvalue,
                'noattempt' => array_sum(array_map(fn($bins) => $bins[0], $chartdata)),
                'byprimary' => count($this->usageclassbyprimary),
            ],
        ];
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
        $data = $this->get_attempts_per_person_data();
        if (!$data) {
            return [
                'charttitle' => get_string('catquizstatistics_numattemptsperperson_title', 'local_catquiz'),
                'chart' => $this->get_nodata_body(),
            ];
        }
        $chartdata = $data['series'];
        if (!$qs = $this->get_quizsettings()) {
            $colors = [LOCAL_CATQUIZ_DEFAULT_BLACK];
        } else {
            $colors = array_values(feedbackclass::get_array_of_colors($qs->numberoffeedbackoptionsselect));
        }
        $colors[-1] = LOCAL_CATQUIZ_DEFAULT_GREY;

        $serieslabels = is_null($qs)
            ? [$this->usage_noresult_label(), get_string('hasability', 'local_catquiz')]
            : array_map(fn ($r) => get_string('feedbackrange', 'local_catquiz', $r), array_keys($chartdata));
        $serieslabels[0] = $this->usage_noresult_label();
        $chart = new chart_bar();
        $chart->set_stacked(true);
        $chart->set_labels($data['labels']);

        foreach (array_keys($chartdata) as $range) {
            $series = new chart_series($serieslabels[$range], array_values($chartdata[$range]));
            $series->set_color($colors[$range - 1]);
            $chart->add_series($series);
        }

        $chart->get_xaxis(0, true)->set_label(get_string('numberofattempts', 'local_catquiz'));
        $chart->get_yaxis(0, true)->set_label(sprintf('# %s', get_string('students')));
        $chart->set_legend_options(['display' => false]);
        // With the table of values: the chart can be read without seeing it (issue #132).
        $out = $OUTPUT->render_chart($chart, true);
        $out .= $this->usage_note((int) ($data['population']['byprimary'] ?? 0));

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

        $colorbarlegend = false;
        if ($qs) {
            try {
                $legend = feedback_helper::get_colorbarlegend(
                    $qs,
                    $this->scaleid,
                    $this->check_quizsettings_are_compatible(self::COMPATIBILITY_LEVEL_DESCRIPTION),
                    true,
                    $this->usage_noresult_label()
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
            '<a class="btn btn-info" style="margin: 1em 0" href="%s">%s</a>',
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
