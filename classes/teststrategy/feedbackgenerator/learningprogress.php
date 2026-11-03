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

use core\chart_bar;
use core\chart_line;
use core\chart_series;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\local\model\model_model;
use local_catquiz\local\statistics\chart_output;
use local_catquiz\local\statistics\learning_progress_series;
use local_catquiz\output\catscalemanager\questions\cards\questionpreview;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\local\model\model_strategy;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Returns rendered learning progress.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learningprogress extends feedbackgenerator {
    /**
     *
     * @var int $primaryscaleid // The scale to be displayed in detail in the colorbar.
     */
    public int $primaryscaleid;

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

        // The learning-progress charts communicate the development of
        // the GLOBAL scale, so they must follow the global scale (catquiz_catscales)
        // and never the primary scale of the current attempt, which can change
        // between attempts and would produce a factually wrong trajectory.
        $globalscale = catscale::return_catscale_object(
            $this->get_progress()->get_quiz_settings()->catquiz_catscales
        );

        // The charts showing past and present personabilities (in relation to peers).
        $abilityprogress = $this->render_abilityprogress(
            (array) $feedbackdata,
            $globalscale
        );
        if (!$abilityprogress) {
            return [];
        }
        $globalscalename = $globalscale->name;
        $feedback = $OUTPUT->render_from_template(
            'local_catquiz/feedback/learningprogress',
            [
            'description' => get_string(
                'learningprogress_description',
                'local_catquiz',
                $globalscalename
            ),
            'progressindividual' => $abilityprogress['individual'],
            'progresscomparison' => $abilityprogress['comparison'],
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
        // The learning-progress charts no longer depend on the
        // primary scale of the attempt (they follow the global scale), so it is
        // not a required context key any more.
        return [];
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return get_string('learningprogresstitle', 'local_catquiz');
    }

    /**
     * Get generatorname.
     *
     * @return string
     *
     */
    public function get_generatorname(): string {
        return 'learningprogress';
    }

    /**
     * Loads data personability, number of items played per subscale and standarderrorpersubscale.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param array $existingdata
     * @param array $newdata
     *
     * @return array|null
     *
     */
    public function load_data(int $adaptiveattemptid, array $existingdata, array $newdata): ?array {
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
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $progress = $this->get_progress();
        if ($progress->get_abilities() === []) {
            return null;
        }

        return [
            'primaryscale' => $this->get_primary_scale($existingdata, $newdata),
        ];
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
        if ($questionsinscale = $this->get_progress()->get_playedquestions(true, $catscaleid)) {
            $numberofitems = ['itemsplayed' => count($questionsinscale)];
            $questionpreviews = array_map(
                fn($q) => [
                'preview' => $this->render_questionpreview((object) $q)['body']['question']],
                $questionsinscale
            );
        } else if (
            $this->feedbacksettings->displayscaleswithoutitemsplayed
            || $catscaleid == $selectedscaleid
            || $catscales[$catscaleid]->parentid == 0
        ) {
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
     * Render chart for personabilities.
     *
     * @param array $initialcontext
     * @param stdClass $primarycatscale
     *
     * @return array
     *
     */
    public function render_abilityprogress(array $initialcontext, $primarycatscale) {
        if (!$primarycatscale) {
            return [];
        }
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
        $records = [];
        foreach (
            catquiz::get_attempts(
                null,
                $initialcontext['catscaleid'],
                $courseid,
                $initialcontext['testid'],
                $initialcontext['contextid'],
                null,
                $end,
                // The cohort of a past day is who took part then, not who is enrolled today - as in the
                // statistics, so both show the same curve for the same scope (issue #119).
                false
            ) as $record
        ) {
            $json = json_decode($record->json);
            $prunedrecord = $record;
            $prunedrecord->json = json_encode((object) [
                'personabilities_abilities' => $json->personabilities_abilities ?? null,
                'personabilities' => $json->personabilities ?? null,
            ]);
            $records[] = $prunedrecord;
        }
        // Compare records to define range for average.
        // Minimum 3 records required to display progress charts.
        if (count($records) < 3) {
            return [
                'individual' => '',
                'comparison' => '',
            ];
        }
        // The periods begin with the earliest finished attempt - the records are not sorted by it,
        // and an attempt before the first period would be left out of the series (issue #119).
        $endtimes = array_filter(array_map(fn($r) => (int) ($r->endtime ?? 0), $records));
        if (!$endtimes) {
            return [
                'individual' => '',
                'comparison' => '',
            ];
        }
        $beginningoftimerange = min($endtimes);
        $timerange = feedback_helper::get_timerange_for_attempts($beginningoftimerange, $endtime);

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
            $records,
            (int) $userid,
            (array) $primarycatscale,
            $timerange,
            [$beginningoftimerange, $endtime]
        );
        return [
            'individual' => $progressindividual,
            'comparison' => $progresscomparison,
        ];
    }

    /**
     * Render chart for individual progress.
     *
     * The valid results of the person, attempt by attempt (issue #119): an invalid result is not a
     * point of the trajectory.
     *
     * @param array $attemptsofuser
     * @param array $primarycatscale
     *
     * @return array|string
     *
     */
    private function render_chart_for_individual_user(array $attemptsofuser, array $primarycatscale) {
        global $OUTPUT;
        if (!isset($primarycatscale['name'])) {
            return '';
        }

        $scalename = $primarycatscale['name'];
        $scaleid = $primarycatscale['id'];

        $personabilities = learning_progress_series::individual($attemptsofuser, (int) $scaleid);
        // At least two real data points are required to draw a trajectory.
        if (count($personabilities) < 2) {
            return '';
        }

        $chart = new chart_line();
        // Straight lines between the measured points: a smoothed curve bulges between them and shows
        // values that were never measured (issue #119).
        $chart->set_smooth(false);
        $chartserie = new \core\chart_series(
            get_string('personabilityinscale', 'local_catquiz', $scalename),
            $personabilities
        );

        $chart->add_series($chartserie);
        $chart->set_labels(range(1, count($personabilities)));
        // With the table of values: the chart can be read without seeing it.
        $out = $OUTPUT->render_chart($chart, true);

        return [
            'chart' => $out,
            'charttitle' => get_string('learningprogresstitle', 'local_catquiz'),
        ];
    }

    /**
     * Render chart for progress compared to peers and grouped by date.
     *
     * Both series are cumulative: for each period the latest valid result of every person up to
     * then (issue #119, learning_progress_series).
     *
     * @param array $records Attempts of the scope, the viewer's and the others'.
     * @param int $userid The viewer.
     * @param array $primarycatscale
     * @param int $timerange
     * @param array $beginningandendofrange
     *
     * @return array
     *
     */
    private function render_chart_for_comparison(
        array $records,
        int $userid,
        array $primarycatscale,
        int $timerange,
        array $beginningandendofrange
    ) {
        if (!isset($primarycatscale['name']) || !isset($primarycatscale['id'])) {
            return [];
        }
        $scalename = $primarycatscale['name'];
        $series = learning_progress_series::comparison(
            $records,
            (int) $primarycatscale['id'],
            $userid,
            $timerange,
            $beginningandendofrange
        );
        // Issue #150 (re-audit 23.10.2026): the attempts were counted, not their valid results. With none, every
        // period is empty and so are the labels: two series without values, which core/chart_series refuses
        // ("Invalid values received for series."). As in the statistics (catquizstatistics::render_learning_progress()).
        $hasvalue = fn(array $values) => (bool) array_filter($values, fn($value) => $value !== null);
        if (!$series['labels'] || (!$hasvalue($series['user']) && !$hasvalue($series['peers']))) {
            return [];
        }

        $chart = new chart_line();
        // Straight lines: a smoothed curve between periods suggests values in between (issue #119).
        $chart->set_smooth(false);
        $chart->add_series(new chart_series(get_string('yourscorein', 'local_catquiz', $scalename), $series['user']));
        $chart->add_series(new chart_series(get_string('scoreofpeers', 'local_catquiz'), $series['peers']));
        $chart->set_labels($series['labels']);
        // With the table of values: the chart can be read without seeing it. Drawn as the ability profile is: what
        // the browser cannot draw says so (issue #150).
        $out = chart_output::render($chart, true, get_string('catquizstatisticsnodata', 'local_catquiz'));

        return [
            'chart' => $out,
            'charttitle' => get_string('progress', 'local_catquiz', $scalename),
        ];
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
    private function render_chart(array $personabilities, array $quizsettings, array $primarycatscale): array {
        global $OUTPUT;

        if (count($personabilities) < 2) {
            return [
                'chart' => get_string('nothingtocompare', 'local_catquiz'),
                'charttitle' => '',
            ];
        }
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
                ]
            );
            $series->set_labels([0 => $stringforchartlegend]);

            // The method lives on the feedback helper, which the base class provides;
            // calling it on $this raised "undefined method" as soon as this branch
            // ran. The four other call sites in the plugin get it right.
            $colorvalue = $this->feedbackhelper->get_color_for_personability(
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

    /**
     * Shows the valid results over time; an invalid attempt adds no point but keeps the history (2026-10-14).
     *
     * @return string
     */
    public function get_result_dependency(): string {
        return self::DEPENDS_ON_ATTEMPT;
    }
}
