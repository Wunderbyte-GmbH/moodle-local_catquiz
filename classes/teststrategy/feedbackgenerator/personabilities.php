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
 * Class personabilities.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use cache;
use core\chart_bar;
use core\chart_line;
use core\chart_series;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\output\catscalemanager\questions\cards\questionpreview;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\local\model\model_strategy;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/local/catquiz/lib.php');

/**
 * Returns rendered person abilities.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class personabilities extends feedbackgenerator {

    /**
     *
     * @var stdClass $feedbacksettings.
     */
    public feedbacksettings $feedbacksettings;

    /**
     *
     * @var int $primaryscaleid // The scale to be displayed in detail in the colorbar.
     */
    public int $primaryscaleid;

    /**
     * Creates a new personabilities feedback generator.
     *
     * @param feedbacksettings $feedbacksettings
     */
    public function __construct(feedbacksettings $feedbacksettings) {

        if (!isset($feedbacksettings)) {
            return;
        }
        // Will be default if no scale set correctly.
        if (isset($feedbacksettings->primaryscaleid)) {
            $this->primaryscaleid = $feedbacksettings->primaryscaleid;
        } else {
            $this->primaryscaleid = LOCAL_CATQUIZ_PRIMARYCATSCALE_DEFAULT;
        }
        $this->feedbacksettings = $feedbacksettings;
    }

    /**
     * For specific feedbackdata defined in generators.
     *
     * @param array $feedbackdata
     */
    public function apply_settings_to_feedbackdata(array $feedbackdata) {
        return $this->feedbacksettings->hide_defined_elements(
            $feedbackdata,
            $this->get_generatorname()
        );
    }
    /**
     * Get student feedback.
     *
     * @param array $feedbackdata
     *
     * @return array
     *
     */
    protected function get_studentfeedback(array $feedbackdata): array {
        global $OUTPUT;

        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $selectedscalearray = $this->feedbacksettings->get_scaleid_and_stringkey(
            $feedbackdata['personabilities'],
            (object) $feedbackdata['quizsettings'],
            $this->primaryscaleid);
        $selectedscaleid = $selectedscalearray['selectedscaleid'];
        $selectedscalestringkey = $selectedscalearray['selectedscalestringkey'];
        $catscales = catquiz::get_catscales(array_keys($feedbackdata['personabilities']));

        $personabilities = $feedbackdata['personabilities'];
        // Sort the array and put primary scale first.
        if ($this->feedbacksettings->sortorder == LOCAL_CATQUIZ_SORTORDER_ASC) {
            asort($personabilities);
        } else {
            arsort($personabilities);
        }
        if (array_key_exists($selectedscaleid, $personabilities)) {
            $value = $personabilities[$selectedscaleid];
            unset($personabilities[$selectedscaleid]);
            $personabilities = [$selectedscaleid => $value] + $personabilities;
        }

        $data = [];
        foreach ($personabilities as $catscaleid => $ability) {
            if (abs(floatval($ability)) === abs(floatval(LOCAL_CATQUIZ_PERSONABILITY_MAX))) {
                if ($ability < 0) {
                    $ability = get_string('allquestionsincorrect', 'local_catquiz');
                } else {
                    $ability = get_string('allquestionscorrect', 'local_catquiz');
                }
            } else {
                $ability = sprintf("%.2f", $ability);
            }
            if ($catscaleid == $selectedscaleid) {
                $isselectedscale = true;
                $tooltiptitle = get_string($selectedscalestringkey, 'local_catquiz', $catscales[$catscaleid]->name);
            } else {
                $isselectedscale = false;
                $tooltiptitle = $catscales[$catscaleid]->name;
            }
            // If defined in settings, display only feedbacks if items were played...
            // ...and parentscale and primaryscale.
            if (isset($feedbackdata['playedquestions'][$catscaleid])) {
                $numberofitems = ['itemsplayed' => count($feedbackdata['playedquestions'][$catscaleid])];
            } else if ($this->feedbacksettings->displayscaleswithoutitemsplayed
                || $catscaleid == $selectedscaleid
                || $catscales[$catscaleid]->parentid == 0) {
                $numberofitems = ['noplayed' => 0];
            } else if ($catscaleid != $selectedscaleid) {
                $numberofitems = "";
            }
            $questionpreviews = array_map(fn($q) => [
                'preview' => $this->render_questionpreview((object) $q)['body']['question']],
                $feedbackdata['playedquestions'][$catscaleid]
            );

            $data[] = [
                'standarderror' => sprintf("%.2f", $feedbackdata['se'][$catscaleid]),
                'ability' => $ability,
                'name' => $catscales[$catscaleid]->name,
                'catscaleid' => $catscaleid,
                'numberofitemsplayed' => $numberofitems,
                'questionpreviews' => $questionpreviews ?: "",
                'isselectedscale' => $isselectedscale,
                'tooltiptitle' => $tooltiptitle,
            ];
        }

        $catscales = catquiz::get_catscales(array_keys($personabilities));
        // The chart showing all present personabilities in relation to each other.
        $chart = $this->render_chart(
            $personabilities,
            (array) $feedbackdata['quizsettings'],
            $catscales[$selectedscaleid]
        );
        $abilityprofile = $this->render_abilityprofile_chart((array) $feedbackdata, $catscales[$selectedscaleid]);

        // The charts showing past and present personabilities (in relation to peers).
        $abilityprogress = $this->render_abilitiyprogress(
            (array) $feedbackdata,
            $catscales[$selectedscaleid]);

        $feedback = $OUTPUT->render_from_template(
            'local_catquiz/feedback/personabilities',
            [
                'abilities' => $data,
                'progressindividual' => $abilityprogress['individual'],
                'progresscomparison' => $abilityprogress['comparison'],
                'abilityprofile' => $abilityprofile,
                'chartdisplay' => $chart,
            ]
        );

        if (empty($feedback)) {
            return [];
        } else {
            return [
                'heading' => $this->get_heading(),
                'content' => $feedback,
            ];
        }
    }

    /**
     * Get teacher feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    protected function get_teacherfeedback(array $data): array {
        return [];
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'personabilities',
            'se',
            'playedquestions',
        ];
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return get_string('personability', 'local_catquiz');
    }

    /**
     * Get generatorname.
     *
     * @return string
     *
     */
    public function get_generatorname(): string {
        return 'personabilities';
    }

    /**
     * Loads data personability, number of items played per subscale and standarderrorpersubscale.
     *
     * @param int $attemptid
     * @param array $existingdata
     * @param array $newdata
     *
     * @return array|null
     *
     */
    public function load_data(int $attemptid, array $existingdata, array $newdata): ?array {
        return $this->generate_feedback($existingdata, $newdata, true);
    }

    /**
     * Loads data personability, number of items played per subscale and standarderrorpersubscale.
     *
     * @param array $existingdata
     * @param array $newdata
     * @param bool $dataonly
     *
     * @return array|null
     *
     */
    public function generate_feedback(array $existingdata, $newdata, $dataonly = false): ?array {
        $progress = $newdata['progress'];
        $personabilities = $progress->get_abilities();
        if ($personabilities === []) {
            return null;
        }

        return [
            'personabilities' => $personabilities,
            'se' => $newdata['se'],
            'playedquestions' => $progress->get_playedquestions(true),
        ];
    }

    /**
     * Render chart for histogram of personabilities.
     *
     * @param array $initialcontext
     * @param stdClass $primarycatscale
     *
     * @return array
     *
     */
    private function render_abilityprofile_chart(array $initialcontext, $primarycatscale) {
        global $OUTPUT, $DB;

        $abilitysteps = [];
        $abilitystep = 0.25;
        $interval = $abilitystep * 2;
        $abilityrange = catscale::get_ability_range($primarycatscale->id);

        $ul = $abilityrange['minscalevalue'];
        $ll = $abilityrange['maxscalevalue'];
        for ($i = $ll + $abilitystep; $i <= $ul - $abilitystep; $i += $interval) {
            $abilitysteps[] = $i;
        }
        $catscale = new catscale($primarycatscale->id);
        // Prepare data for test information line.
        $items = $catscale->get_testitems($initialcontext['contextid'], true);
        $models = model_strategy::get_installed_models();
        $fisherinfos = [];
        foreach ($items as $item) {
            $key = $item->model;
            $model = $models[$key] ?? $models['raschbirnbaumb'];
            foreach ($model::get_parameter_names() as $paramname) {
                $params[$paramname] = floatval($item->$paramname);
            }
            foreach ($abilitysteps as $ability) {
                $fisherinformation = $model::fisher_info(
                    ['ability' => $ability],
                    $params
                );
                $stringkey = strval($ability);

                if (!isset($fisherinfos[$stringkey])) {
                    $fisherinfos[$stringkey] = $fisherinformation;
                } else {
                    $fisherinfos[$stringkey] += $fisherinformation;
                }
            }

        }

        // Prepare data for scorecounter bars.
        $abilityrecords = $DB->get_records('local_catquiz_personparams', ['catscaleid' => $primarycatscale->id]);
        $abilityseries = [];
        foreach ($abilitysteps as $as) {
            $counter = 0;
            foreach ($abilityrecords as $record) {
                $a = floatval($record->ability);
                $ability = $this->round_to_customsteps($a, $abilitystep, $interval);
                if ($ability != $as) {
                    continue;
                } else {
                    $counter ++;
                }
            }
            $colorvalue = $this->get_color_for_personability(
                (array)$initialcontext['quizsettings'],
                $as,
                intval($primarycatscale->id)
                );
            $abilitystring = strval($as);
            $abilityseries['counter'][$abilitystring] = $counter;
            $abilityseries['colors'][$abilitystring] = $colorvalue;
        }
        // Scale the values of $fisherinfos before creating chart series.
        $scaledtiseries = $this->scalevalues(array_values($fisherinfos), array_values($abilityseries['counter']));

        $aserieslabel = get_string('scalescorechartlabel', 'local_catquiz', $catscale->catscale->name);
        $aseries = new chart_series($aserieslabel, array_values($abilityseries['counter']));
        $aseries->set_colors(array_values($abilityseries['colors']));

        $testinfolabel = get_string('testinfolabel', 'local_catquiz');
        $tiseries = new chart_series($testinfolabel, $scaledtiseries);
        $tiseries->set_type(\core\chart_series::TYPE_LINE);

        $chart = new chart_bar();
        $chart->add_series($tiseries);
        $chart->add_series($aseries);
        $chart->set_labels(array_keys($fisherinfos));

        $out = $OUTPUT->render($chart);
        return [
            'chart' => $out,
            'charttitle' => get_string('abilityprofile', 'local_catquiz', $primarycatscale->name),
        ];
    }

    /**
     * Round float to steps as defined.
     *
     * @param float $number
     * @param float $step
     * @param float $interval
     *
     * @return float
     */
    private function round_to_customsteps(float $number, float $step, float $interval):float {
        $roundedvalue = round($number / $step) * $step;

        // Exclude rounding to steps defined in $interval.
        if ($roundedvalue - floor($roundedvalue) == $interval) {
            $roundedvalue = floor($roundedvalue) + $step;
        }

        return $roundedvalue;
    }

    /**
     * Scale values of testinfo (sum of fisherinfos) for better display in chart.
     *
     * @param array $fisherinfos
     * @param array $attemptscounter
     *
     * @return array
     */
    private function scalevalues($fisherinfos, $attemptscounter) {
        // Find the maximum values in arrays.
        $maxattempts = max($attemptscounter);
        $maxfisherinfo = max($fisherinfos);

        // Avoid division by zero.
        if ($maxfisherinfo == 0 || $maxattempts == 0) {
            return $fisherinfos;
        }

        $scalingfactor = $maxattempts / $maxfisherinfo;

        // Scale the values in $fisherinfos based on the scaling factor.
        foreach ($fisherinfos as &$value) {
            $value *= $scalingfactor;
        }
        return $fisherinfos;
    }

    /**
     * Render chart for personabilities.
     *
     * @param array $initialcontext
     * @param stdClass $primarycatscale
     *
     * @return array
     *
     */
    private function render_abilitiyprogress(array $initialcontext, $primarycatscale) {
        $userid = $initialcontext['userid'];
        $endtime = intval($initialcontext['endtime']);
        $courseid = empty($initialcontext['courseid']) ? null : $initialcontext['courseid'];

        $day = userdate($endtime, '%d');
        $month = userdate($endtime, '%m');
        $year = userdate($endtime, '%Y');
        $end = make_timestamp($year, $month, $day, 23, 59, 59);

        // Compare to other courses.
        // Find all courses before the end of the day of this attempt.
        $records = catquiz::get_attempts(
                null,
                $initialcontext['catscaleid'],
                $courseid,
                $initialcontext['contextid'],
                null,
                $end);
        // Compare records to define range for average.
        // Minimum 3 records required to display progress charts.
        if (count($records) < 3) {
            return [
                'individual' => '',
                'comparison' => '',
            ];
        }
        $startingrecord = reset($records);
        $beginningoftimerange = intval($startingrecord->endtime);
        $timerange = $this->get_timerange_for_attempts($beginningoftimerange, $endtime);

        $attemptsofuser = array_filter($records, fn($r) => $r->userid == $userid);
        $attemptsofpeers = array_filter($records, fn($r) => $r->userid != $userid);

        $progressindividual = $this->render_chart_for_individual_user($attemptsofuser, $primarycatscale);
        if (count($attemptsofpeers) < 3) {
            return [
                'individual' => $progressindividual,
                'comparison' => '',
            ];
        }
        $progresscomparison = $this->render_chart_for_comparison(
                $attemptsofuser,
                $attemptsofpeers,
                $primarycatscale,
                $timerange,
                [$beginningoftimerange, $endtime]);
        return [
            'individual' => $progressindividual,
            'comparison' => $progresscomparison,
        ];

    }
    /**
     * Return value to define range of time average.
     *
     * @param int $beginningoftimerange
     * @param int $endtime
     *
     * @return int
     *
     */
    public static function get_timerange_for_attempts(int $beginningoftimerange, int $endtime) {
        $differenceindays = ($endtime - $beginningoftimerange) / (60 * 60 * 24);
        $range = LOCAL_CATQUIZ_TIMERANGE_DAY;

        if ($differenceindays <= 30) {
            $range = LOCAL_CATQUIZ_TIMERANGE_DAY;
        } else if ($differenceindays > 30 && $differenceindays <= 183) {
            $range = LOCAL_CATQUIZ_TIMERANGE_WEEK;
        } else if ($differenceindays > 183 && $differenceindays <= 730) {
            $range = LOCAL_CATQUIZ_TIMERANGE_MONTH;
        } else {
            $range = LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR;
        }

         return $range;
    }

    /**
     * Render chart for individual progress.
     *
     * @param array $attemptsofuser
     * @param stdClass $primarycatscale
     *
     * @return array
     *
     */
    private function render_chart_for_individual_user(array $attemptsofuser, stdClass $primarycatscale) {
        global $OUTPUT;
        $scalename = $primarycatscale->name;
        $scaleid = $primarycatscale->id;

        $chart = new \core\chart_line();
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
     * @param stdClass $primarycatscale
     * @param int $timerange
     * @param array $beginningandendofrange
     *
     * @return array
     *
     */
    private function render_chart_for_comparison(
        array $attemptsofuser,
        array $attemptsofpeers,
        stdClass $primarycatscale,
        int $timerange,
        array $beginningandendofrange) {
        global $OUTPUT;
        $scalename = $primarycatscale->name;
        $scaleid = $primarycatscale->id;

        $chart = new \core\chart_line();
        $chart->set_smooth(true); // Calling set_smooth() passing true as parameter, will display smooth lines.

        $orderedattemptspeers = self::order_attempts_by_timerange($attemptsofpeers, $scaleid, $timerange);
        $pa = $this->assign_average_result_to_timerange($orderedattemptspeers);
        $orderedattemptsuser = self::order_attempts_by_timerange($attemptsofuser, $scaleid, $timerange);
        $ua = $this->assign_average_result_to_timerange($orderedattemptsuser);

        $alldates = self::get_timerangekeys($timerange, $beginningandendofrange);
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

        // To display the chartserie lines connected, we fill empty keys with average.
        $peerattempts = $this->fill_empty_values_with_average($peerattemptsbydate);
        $userattempts = $this->fill_empty_values_with_average($userattemptsbydate);

        $peerattempts = new \core\chart_series(
            get_string('scoreofpeers', 'local_catquiz'),
            array_values($peerattempts)
        );
        $peerattempts->set_labels(array_values($peerattemptsbydate));

        $userattempts = new \core\chart_series(
            get_string('yourscorein', 'local_catquiz', $scalename),
            array_values($userattempts)
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
     * Return average of personabilities ordered by date of quizattempt.
     *
     * @param array $keys
     * @param array $attemptswithnulls
     * @param string $key
     *
     * @return float
     *
     */
    private function find_non_nullable_value(array $keys, array $attemptswithnulls, string $key) {

        if (!empty($attemptswithnulls[$key])) {
            return $attemptswithnulls[$key];
        }
        $prevkey = null;
        $nextkey = null;
        $stop = false;
        foreach ($keys as $k) {
            if (!empty($attemptswithnulls[$k])) {
                $pk = $k;
            }
            if ($key == $k) {
                $prevkey = $pk ?? null;
                $stop = true;
                continue;
            }
            if ($stop) {
                $nextkey = $k;
                break;
            }
        }

        return [
            'prevvalue' => $attemptswithnulls[$prevkey] ?? null,
            'nextvalue' => $attemptswithnulls[$nextkey] ?? null,
        ];
    }

    /**
     * Return average of personabilities ordered by date of quizattempt.
     *
     * @param array $attempts
     * @param int $scaleid
     * @param int $timerange
     *
     * @return array
     *
     */
    public static function order_attempts_by_timerange(array $attempts, int $scaleid, int $timerange) {

        $attemptsbytimerange = [];

        switch ($timerange) {
            case LOCAL_CATQUIZ_TIMERANGE_DAY:
                $dateformat = '%d.%m.%Y';
                $stringfordate = 'day';
                break;
            case LOCAL_CATQUIZ_TIMERANGE_WEEK:
                $dateformat = '%W';
                $stringfordate = 'week';
                break;
            case LOCAL_CATQUIZ_TIMERANGE_MONTH:
                $dateformat = '%m';
                $stringfordate = 'month';
                break;
            case LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR:
                $dateformat = '%m';
                $stringfordate = 'quarter';
                break;
        }

        foreach ($attempts as $attempt) {
            $data = json_decode($attempt->json);
            $date = userdate($attempt->endtime, $dateformat);
            if ($timerange === LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR) {
                $date = ceil($date / 3);
            };
            if ($timerange === LOCAL_CATQUIZ_TIMERANGE_MONTH) {
                $datestring = get_string('stringdate:month:' . $date, 'local_catquiz');
            } else {
                $datestring = get_string('stringdate:' . $stringfordate, 'local_catquiz', $date);
            }

            if (!empty($data->personabilities->$scaleid)) {
                if (!isset($attemptsbytimerange[$datestring])) {
                    $attemptsbytimerange[$datestring] = [];
                }
                $attemptsbytimerange[$datestring][] = $data->personabilities->$scaleid;
            }
        }
        return $attemptsbytimerange;
    }

    /**
     * Assign average of result for each period.
     * @param array $attemptsbytimerange
     *
     * @return array
     */
    private function assign_average_result_to_timerange(array $attemptsbytimerange) {
        // Calculate average personability of this period.
        foreach ($attemptsbytimerange as $date => $attempt) {
            $floats = array_map('floatval', $attempt);
            $average = count($floats) > 0 ? array_sum($floats) / count($floats) : $attempt;
            $attemptsbytimerange[$date] = $average;
        }
        return $attemptsbytimerange;
    }
    /**
     * Return keys for all moments in defined timerange.
     *
     * @param int $timerange
     * @param array $beginningandendofrange
     *
     * @return array
     *
     */
    public static function get_timerangekeys($timerange, $beginningandendofrange) {
        switch ($timerange) {
            case LOCAL_CATQUIZ_TIMERANGE_DAY:
                $dateformat = '%d.%m.%Y';
                $stringfordate = 'day';
                break;
            case LOCAL_CATQUIZ_TIMERANGE_WEEK:
                $dateformat = '%W';
                $stringfordate = 'week';
                break;
            case LOCAL_CATQUIZ_TIMERANGE_MONTH:
                $dateformat = '%m';
                $stringfordate = 'month';
                break;
            case LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR:
                $dateformat = '%m';
                $stringfordate = 'quarter';
                break;
        }

        $result = [];
        $currenttimestamp = $beginningandendofrange[0];
        $endtimestamp = $beginningandendofrange[1];

        while ($currenttimestamp <= $endtimestamp) {
            $date = userdate($currenttimestamp, $dateformat);

            if ($timerange === LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR) {
                $date = ceil($date / 3);
            };
            if ($timerange === LOCAL_CATQUIZ_TIMERANGE_MONTH) {
                $result[] = get_string('stringdate:month:' . $date, 'local_catquiz');
            } else {
                $result[] = get_string('stringdate:' . $stringfordate, 'local_catquiz', $date);
            }

            $currenttimestamp = strtotime('+1 day', $currenttimestamp);
        }

        return array_unique($result);
    }

    /**
     * Render chart for personabilities.
     *
     * @param array $personabilities
     * @param array $quizsettings
     * @param stdClass $primarycatscale
     *
     * @return array
     *
     */
    private function render_chart(array $personabilities, array $quizsettings, $primarycatscale) {
        global $OUTPUT;

        $primarycatscaleid = $primarycatscale->id;
        $primaryability = 0;
        // First we get the personability of the primaryscale.
        foreach ($personabilities as $subscaleid => $ability) {
            if ($subscaleid == $primarycatscaleid) {
                $primaryability = floatval($ability);
            }
        }
        $chart = new chart_bar();
        $chart->set_horizontal(true);
        $chartseries = [];
        $chartseries['series'] = [];
        $chartseries['labels'] = [];
        foreach ($personabilities as $subscaleid => $ability) {
            $subscaleability = $ability;
            $subscale = catscale::return_catscale_object($subscaleid);
            $subscalename = $subscale->name;
            $difference = round($subscaleability - $primaryability, 2);
            $series = new chart_series($subscalename, [0 => $difference]);

            $stringforchartlegend = get_string(
                'chartlegendabilityrelative',
                'local_catquiz',
                [
                    'ability' => strval($subscaleability),
                    'difference' => strval($difference),
                ]);
            $series->set_labels([0 => $stringforchartlegend]);

            $colorvalue = self::get_color_for_personability(
                $quizsettings,
                floatval($subscaleability),
                intval($primarycatscaleid)
            );
            $series->set_colors([0 => $colorvalue]);
            $chart->add_series($series);
            $chart->set_labels([0 => get_string('labelforrelativepersonabilitychart', 'local_catquiz')]);
        };
        $out = $OUTPUT->render($chart);

        return [
            'chart' => $out,
            'charttitle' => get_string('personabilitycharttitle', 'local_catquiz', $primarycatscale->name),
        ];

    }

    /**
     * Write information about colorgradient for colorbar.
     *
     * @param array $quizsettings
     * @param float $personability
     * @param int $catscaleid
     * @return string
     *
     */
    public static function get_color_for_personability(array $quizsettings, float $personability, int $catscaleid): string {
        $default = "#878787";
        $abilityrange = catscale::get_ability_range($catscaleid);
        if (!$quizsettings ||
            $personability < $abilityrange['minscalevalue'] ||
            $personability > $abilityrange['maxscalevalue']) {
            return $default;
        }
        $numberoffeedbackoptions = intval($quizsettings['numberoffeedbackoptionsselect']) ?? 8;
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);

        for ($i = 1; $i <= $numberoffeedbackoptions; $i++) {
            $rangestartkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_" . $i;
            $rangeendkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $i;
            $rangestart = floatval($quizsettings[$rangestartkey]);
            $rangeend = floatval($quizsettings[$rangeendkey]);

            if ($personability >= $rangestart && $personability <= $rangeend) {
                $colorkey = 'wb_colourpicker_' . $catscaleid . '_' . $i;
                $colorname = $quizsettings[$colorkey];
                return $colorarray[$colorname];
            }

        }
        return $default;
    }

    /**
     * Renders preview of testitem (question).
     *
     * @param object $record
     *
     * @return array
     *
     */
    private function render_questionpreview(object $record) {
        $questionpreview = new questionpreview($record);
        return $questionpreview->render_questionpreview();
    }
}
