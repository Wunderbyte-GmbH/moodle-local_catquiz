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
 * @copyright 2024 Wunderbyte GmbH
 * @author Magdalena Holczik, David Sziba
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
use local_catquiz\teststrategy\progress;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/local/catquiz/lib.php');

/**
 * Returns rendered person abilities.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class personabilities extends feedbackgenerator {

    /**
     * @var string
     */
    const FALLBACK_MODEL = 'mixedraschbirnbaum';

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
    public function get_studentfeedback(array $feedbackdata): array {
        global $OUTPUT;

        $abilitieschart = $this->render_chart(
            $feedbackdata['personabilities_abilities'],
            $feedbackdata['quizsettings'],
            $feedbackdata['primaryscale'],
        );

        $abilityprofilechart = $this->render_abilityprofile_chart(
            (array) $feedbackdata,
            $feedbackdata['primaryscale']
        );

        // The charts showing past and present personabilities (in relation to peers).
        $abilityprogress = $this->render_abilityprogress(
            (array) $feedbackdata,
            $feedbackdata['primaryscale']
        );
        $feedback = $OUTPUT->render_from_template(
        'local_catquiz/feedback/personabilities',
            [
            'abilities' => $feedbackdata['abilitieslist'],
            'progressindividual' => $abilityprogress['individual'],
            'progresscomparison' => $abilityprogress['comparison'],
            'abilityprofile' => $abilityprofilechart,
            'chartdisplay' => $abilitieschart,
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
            'quizsettings',
            'primaryscale',
            'personabilities_abilities',
            'se',
            'abilitieslist',
            'models',
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
    private function generate_feedback(array $existingdata, $newdata, $dataonly = false): ?array {
        global $OUTPUT;
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $progress = progress::load($existingdata['attemptid'], 'mod_adaptivequiz', $existingdata['contextid']);
        $personabilities = $progress->get_abilities();

        if ($personabilities === []) {
            return null;
        }
        $quizsettings = $existingdata['quizsettings'];
        $catscales = $newdata['catscales'];

        // Make sure that only feedback defined by strategy is rendered.
        $personabilitiesfeedbackeditor = $this->select_scales_for_report(
            $newdata,
            $this->feedbacksettings,
            $quizsettings,
            $existingdata['teststrategy']
        );

        $personabilities = [];
        // Ability range is the same for all scales with same root scale.
        $abiltiyrange = $this->get_ability_range(array_key_first($catscales));
        foreach ($personabilitiesfeedbackeditor as $catscale => $personability) {
            if (isset($personability['excluded']) && $personability['excluded']) {
                continue;
            }
            if (isset($personability['primary'])) {
                $selectedscaleid = $catscale;
            }
            $personabilities[$catscale] = $personability;
            $catscaleobject = catscale::return_catscale_object($catscale);
            $personabilities[$catscale]['name'] = $catscaleobject->name;
            $cs = new catscale($catscale);
            $personabilities[$catscale]['abilityrange'] = $abiltiyrange;

        }
        if ($personabilities === []) {
            return [];
        }

        $this->apply_sorting($personabilities, $selectedscaleid);

        $abilitieslist = [];

        foreach ($personabilities as $catscaleid => $abilityarray) {
            $abilitieslist[] = $this->generate_data_for_scale(
                    $abilitieslist,
                    $catscaleid,
                    $selectedscaleid,
                    $abilityarray,
                    $catscales,
                    $newdata
                );
        }
        $models = model_strategy::get_installed_models();

        return [
            'quizsettings' => (array) $quizsettings,
            'primaryscale' => $catscales[$selectedscaleid],
            'personabilities_abilities' => $personabilities,
            'se' => $newdata['se'] ?? null,
            'abilitieslist' => $abilitieslist,
            'models' => $models,
        ];
    }

    /**
     * Sort personabilites array according to feedbacksettings.
     *
     * @param array $personabilities
     * @param int $selectedscaleid
     *
     */
    private function apply_sorting(array &$personabilities, int $selectedscaleid) {
        // Sort the array and put primary scale first.
        if ($this->feedbacksettings->sortorder == LOCAL_CATQUIZ_SORTORDER_ASC) {
            asort($personabilities);
        } else {
            arsort($personabilities);
        }

        // Put selected element first.
        $value = $personabilities[$selectedscaleid];
        unset($personabilities[$selectedscaleid]);
        $personabilities = [$selectedscaleid => $value] + $personabilities;
    }

    /**
     * Generate data array for values of each catsacle.
     *
     * @param array $data
     * @param int $catscaleid
     * @param int $selectedscaleid
     * @param array $abilityarray
     * @param array $catscales
     * @param array $newdata
     *
     *
     */
    private function generate_data_for_scale(
        array &$data,
        int $catscaleid,
        int $selectedscaleid,
        array $abilityarray,
        array $catscales,
        array $newdata
    ) {
        $ability = $abilityarray['value'];
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
            $tooltiptitle = $catscales[$catscaleid]->name;
            if (isset($abilityarray['primarybecause'])) {
                $tooltiptitle = get_string(
                    $abilityarray['primarybecause'] . ':tooltiptitle',
                    'local_catquiz',
                    $catscales[$catscaleid]->name
                ) ?? $catscales[$catscaleid]->name;
            };

        } else {
            $isselectedscale = false;
            $tooltiptitle = $catscales[$catscaleid]->name;
        }
        // If defined in settings, display only feedbacks if items were played...
        // ...and parentscale and primaryscale.
        $questionpreviews = "";
        if (isset($newdata['progress']->playedquestionsbyscale[$catscaleid])) {
            $questionsinscale = $newdata['progress']->playedquestionsbyscale[$catscaleid];
            $numberofitems = ['itemsplayed' => count($questionsinscale)];
            $questionpreviews = array_map(fn($q) => [
                'preview' => $this->render_questionpreview((object) $q)['body']['question']],
                $questionsinscale
            );
        } else if ($this->feedbacksettings->displayscaleswithoutitemsplayed
            || $catscaleid == $selectedscaleid
            || $catscales[$catscaleid]->parentid == 0) {
            $numberofitems = ['noplayed' => 0];
        } else if ($catscaleid != $selectedscaleid) {
            $numberofitems = "";
        }
        if (isset($newdata['se'][$catscaleid])) {
            $standarderror = sprintf("%.2f", $newdata['se'][$catscaleid]);
        } else {
            $standarderror = "";
        }

        return [
            'standarderror' => $standarderror,
            'ability' => $ability,
            'name' => $catscales[$catscaleid]->name,
            'catscaleid' => $catscaleid,
            'numberofitemsplayed' => $numberofitems,
            'questionpreviews' => $questionpreviews ?: "",
            'isselectedscale' => $isselectedscale,
            'tooltiptitle' => $tooltiptitle,
        ];

    }

    /**
     * Render chart for histogram of personabilities.
     *
     * @param array $initialcontext
     * @param array $primarycatscale
     *
     *
     * @return array
     *
     */
    private function render_abilityprofile_chart(array $initialcontext, array $primarycatscale) {
        global $OUTPUT, $DB;

        $abilitysteps = [];
        $abilitystep = 0.25;
        $interval = $abilitystep * 2;
        if (isset($initialcontext['personabilities_abilities'][$primarycatscale['id']]['abilityrange'])) {
            $abilityrange = $initialcontext['personabilities_abilities'][$primarycatscale['id']]['abilityrange'];
        } else {
            $abilityrange = $this->get_ability_range($primarycatscale['id']);
        };

        $ul = (float) $abilityrange['maxscalevalue'];
        $ll = (float) $abilityrange['minscalevalue'];
        for ($i = $ll + $abilitystep; $i <= ($ul - $abilitystep); $i += $interval) {
            $abilitysteps[] = $i;
        }
        $items = $this->get_testitems_for_catscale($primarycatscale['id'], $initialcontext['contextid'], true);
        // Prepare data for test information line.

        $models = model_strategy::get_installed_models();
        $fisherinfos = [];
        foreach ($items as $item) {
            $key = $item->model;
            $model = $models[$key] ?? LOCAL_CATQUIZ_FALLBACK_MODEL;
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

        $fisherinfos = $this->get_fisherinfos_of_items($items, $models, $abilitysteps);
        $fi = json_encode($fisherinfos);
        // Prepare data for scorecounter bars.
        $abilityrecords = $DB->get_records('local_catquiz_personparams', ['catscaleid' => $primarycatscale['id']]);
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
                intval($primarycatscale['id'])
                );
            $abilitystring = strval($as);
            $abilityseries['counter'][$abilitystring] = $counter;
            $abilityseries['colors'][$abilitystring] = $colorvalue;
        }
        // Scale the values of $fisherinfos before creating chart series.
        $scaledtiseries = $this->scalevalues(array_values($fisherinfos), array_values($abilityseries['counter']));

        $scalename = $initialcontext['personabilities_abilities'][$primarycatscale['id']]['name'];
        $aserieslabel = get_string('scalescorechartlabel', 'local_catquiz', $scalename);
        $aseries = new chart_series($aserieslabel, array_values($abilityseries['counter']));
        $aseries->set_colors(array_values($abilityseries['colors']));

        $testinfolabel = get_string('testinfolabel', 'local_catquiz');
        $tiseries = new chart_series($testinfolabel, $scaledtiseries);
        $tiseries->set_type(chart_series::TYPE_LINE);

        $chart = new chart_bar();
        $chart->add_series($tiseries);
        $chart->add_series($aseries);
        $chart->set_labels(array_keys($fisherinfos));

        $out = $OUTPUT->render($chart);
        return [
            'chart' => $out,
            'charttitle' => get_string('abilityprofile', 'local_catquiz', $primarycatscale['name']),
        ];
    }

    /**
     * Get fisherinfos of item for each abilitystep.
     *
     * @param array $items
     * @param array $models
     * @param array $abilitysteps
     *
     * @return array
     */
    public function get_fisherinfos_of_items(array $items, array $models, array $abilitysteps): array {
        $fisherinfos = [];
        foreach ($items as $item) {
            $key = $item->model;
            $model = $models[$key] ?? $models[self::FALLBACK_MODEL];
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
        return $fisherinfos;
    }

    /**
     * For testing, this is called here.
     *
     * @param int $catscaleid
     * @param int $contextid
     * @param bool $includesubscales
     *
     * @return array
     *
     */
    public function get_testitems_for_catscale(int $catscaleid, int $contextid, bool $includesubscales) {
        $catscale = new catscale($catscaleid);
        // Prepare data for test information line.
        return $catscale->get_testitems($contextid, $includesubscales);
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
    private function round_to_customsteps(float $number, float $step, float $interval): float {
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
    public function render_abilityprogress(array $initialcontext, $primarycatscale) {
        $userid = $initialcontext['userid'];

        // If there is no endtime, use timestamp.
        $endtime = empty($initialcontext['endtime']) ?
            intval($initialcontext['timestamp']) : intval($initialcontext['endtime']);
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

        $progressindividual = $this->render_chart_for_individual_user($attemptsofuser, (array) $primarycatscale);
        if (count($attemptsofpeers) < 3) {
            return [
                'individual' => $progressindividual,
                'comparison' => '',
            ];
        }
        $progresscomparison = $this->render_chart_for_comparison(
                $attemptsofuser,
                $attemptsofpeers,
                (array) $primarycatscale,
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
     * @param array $primarycatscale
     *
     * @return array
     *
     */
    private function render_chart_for_individual_user(array $attemptsofuser, array $primarycatscale) {
        global $OUTPUT;
        $scalename = $primarycatscale['name'];
        $scaleid = $primarycatscale['id'];

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
        array $beginningandendofrange) {
        global $OUTPUT;
        $scalename = $primarycatscale['name'];
        $scaleid = $primarycatscale['id'];

        $chart = new chart_line();
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

        $peerattempts = new chart_series(
            get_string('scoreofpeers', 'local_catquiz'),
            array_values($peerattempts)
        );
        $peerattempts->set_labels(array_values($peerattemptsbydate));

        $userattempts = new chart_series(
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
     * @return array
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

        // Create new array with endtime and sort. Create entry for each day.

        foreach ($attempts as $attempt) {
            $data = json_decode($attempt->json);
            if (empty($attempt->endtime)) {
                continue;
            }
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
     * @param array $primarycatscale
     *
     * @return array
     *
     */
    private function render_chart(array $personabilities, array $quizsettings, array $primarycatscale) {
        global $OUTPUT;

        $primarycatscaleid = $primarycatscale['id'];
        $primaryability = 0;
        // First we get the personability of the primaryscale.
        foreach ($personabilities as $subscaleid => $abilityarray) {
            $ability = $abilityarray['value'];
            if ($subscaleid == $primarycatscaleid) {
                $primaryability = floatval($ability);
                break;
            }
        }
        $chart = new chart_bar();
        $chart->set_horizontal(true);
        foreach ($personabilities as $subscaleid => $abilityarray) {
            $subscaleability = (float) $abilityarray['value'];
            $subscalename = $abilityarray['name'];
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

            $colorvalue = $this->get_color_for_personability(
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
            'charttitle' => get_string('personabilitycharttitle', 'local_catquiz', $primarycatscale['name']),
        ];

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
