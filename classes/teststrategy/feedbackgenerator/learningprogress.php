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
use local_catquiz\output\catscalemanager\questions\cards\questionpreview;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\local\model\model_strategy;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/local/catquiz/lib.php');

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

        // The charts showing past and present personabilities (in relation to peers).
        $abilityprogress = $this->render_abilityprogress(
            (array) $feedbackdata,
            $feedbackdata['primaryscale']
        );
        $globalscale = catscale::return_catscale_object($this->get_progress()->get_quiz_settings()->catquiz_catscales);
        $globalscalename = $globalscale->name;
        $feedback = $OUTPUT->render_from_template(
        'local_catquiz/feedback/learningprogress',
            [
            'title' => get_string('learningprogress_title', 'local_catquiz'),
            'description' => get_string(
                'learningprogress_description',
                'local_catquiz',
                feedback_helper::add_quotes($globalscalename)
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
        return [
            'primaryscale',
        ];
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
        $records = [];
        foreach (catquiz::get_attempts(
                null,
                $initialcontext['catscaleid'],
                $courseid,
                $initialcontext['testid'],
                $initialcontext['contextid'],
                null,
                $end
        ) as $record) {
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
        $out = $OUTPUT->render_chart($chart, false);

        return [
            'chart' => $out,
            'charttitle' => get_string('learningprogress_title', 'local_catquiz', feedback_helper::add_quotes($scalename)),
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

        $orderedattemptspeers = feedback_helper::order_attempts_by_timerange($attemptsofpeers, $scaleid, $timerange);
        $pa = $this->assign_average_result_to_timerange($orderedattemptspeers);
        $orderedattemptsuser = feedback_helper::order_attempts_by_timerange($attemptsofuser, $scaleid, $timerange);
        $ua = $this->assign_average_result_to_timerange($orderedattemptsuser);

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
            'charttitle' => get_string('progress', 'local_catquiz', feedback_helper::add_quotes($scalename)),
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
