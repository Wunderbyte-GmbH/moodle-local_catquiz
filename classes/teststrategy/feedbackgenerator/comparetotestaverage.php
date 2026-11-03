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
 * Class comparetotestaverage.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use core\chart_bar;
use core\chart_series;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\local\statistics\chart_output;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedbacksettings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Compare the ability of this attempt to the average abilities of other students that took this test.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comparetotestaverage extends feedbackgenerator {
    /**
     *
     * @var int $primaryscaleid // The scale to be displayed in detail in the colorbar.
     */
    public int $primaryscaleid;

    /**
     * We only show a graph if we have results for at least that many users.
     *
     * @var int
     */
    const MIN_USERS = 3;
    /**
     * Limit to show comparison text
     *
     * We only show the comparison text "you are better than XX percent of
     * users" if XX is greater than this value.
     *
     * @var int
     */
    const MIN_BETTER_THAN_LIMIT = 15;

    /**
     * Get student feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    public function get_studentfeedback(array $data): array {
        global $OUTPUT;

        $abilityprofilechart = $this->render_abilityprofile_chart(
            (array) $data,
            ['id' => $data['catscaleid']]
        );
        $data['abilityprofile'] = $abilityprofilechart;
        $feedback = $OUTPUT->render_from_template('local_catquiz/feedback/comparetotestaverage', $data);

        return [
            'heading' => $this->get_heading(),
            'content' => $feedback,
        ];
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
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'contextid',
            'personabilities',
            'testaverageability',
            'userability',
            'testaverageposition',
            'userabilityposition',
            'comparisontext',
            'colorbar',
            'colorbarlegend',
            'comparetotestaverage_has_worse',
            'comparetotestaverage_has_enough_peers',
        ];
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return get_string('comparetotestaverage', 'local_catquiz');
    }

    /**
     * Get generatorname.
     *
     * @return string
     *
     */
    public function get_generatorname(): string {
        return 'comparetotestaverage';
    }

    /**
     * Minimum number of distinct peers required to show the comparison.
     *
     * Configurable via the plugin setting minpeersforcomparison;
     * falls back to MIN_USERS when the setting is absent or invalid.
     *
     * @return int
     */
    public static function get_min_peers(): int {
        $configured = (int) get_config('local_catquiz', 'minpeersforcomparison');
        return $configured >= 1 ? $configured : self::MIN_USERS;
    }

    /**
     * Write information about colorgradient for colorbar.
     *
     * @param object $quizsettings
     * @param string|int $catscaleid
     * @return string
     *
     */
    private function get_colorgradientstring(object $quizsettings, $catscaleid): string {
        if (!$quizsettings) {
            return "";
        }

        $numberoffeedbackoptions = intval($quizsettings->numberoffeedbackoptionsselect);
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);
        $gradient = LOCAL_CATQUIZ_COLORBARGRADIENT;

        $output = "";

        // Keys of the lowest and highest values in range...
        // Since it's already defined via scale min max range, no more need to sanitize here.
        $lowestlimitkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_1";
        $highestlimitkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $numberoffeedbackoptions;

        // A scale without feedback ranges has neither key; that is the error below, not a warning.
        $rangestart = (float) ($quizsettings->$lowestlimitkey ?? 0);
        $rangeend = (float) ($quizsettings->$highestlimitkey ?? 0);

        if (!($rangeend > $rangestart)) {
            throw new \moodle_exception('error:minmaxrangeequal', 'local_catquiz');
        }

        for ($i = 1; $i <= $numberoffeedbackoptions; $i++) {
            $lowerlimitkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_" . $i;
            $upperlimitkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $i;

            $lowerlimit = (float) ($quizsettings->$lowerlimitkey ?? $rangestart);
            $upperlimit = (float) ($quizsettings->$upperlimitkey ?? $rangeend);

            $lowerpercentage = (($lowerlimit - $rangestart) / ($rangeend - $rangestart)) * 100 + $gradient;
            $upperpercentage = (($upperlimit - $rangestart) / ($rangeend - $rangestart)) * 100 - $gradient;

            // A range without a chosen colour gets the colour the form offers for its position, not a
            // warning: this runs with every answer, inside the selection of the next question.
            $colorkey = 'wb_colourpicker_' . $catscaleid . '_' . $i;
            $colorname = $quizsettings->$colorkey ?? (array_keys($colorarray)[$i - 1] ?? null);
            $colorvalue = $colorarray[$colorname] ?? reset($colorarray);

            $output .= "{$colorvalue} {$lowerpercentage}%, ";
            $output .= "{$colorvalue} {$upperpercentage}%, ";
        }
        // Remove the last comma.
        $output = rtrim($output, ", ");
        return $output;
    }


    /**
     * Load data.
     *
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param array $existingdata
     * @param array $newdata
     *
     * @return array|null
     *
     */
    public function load_data(int $adaptiveattemptid, array $existingdata, array $newdata): ?array {
        global $DB;
        $progress = $this->get_progress();
        $quizsettings = $progress->get_quiz_settings();

        if (!$progress->get_playedquestions()) {
            return [];
        }

        $catscaleid = $quizsettings->catquiz_catscales;
        $abilities = $progress->get_abilities();
        if (!array_key_exists($catscaleid, $abilities)) {
            return [];
        }
        $ability = $abilities[$catscaleid];

        // Compute the peer comparison against a context-true reference
        // group via SQL aggregates: same context and scale, exactly one value per
        // person, the compared user excluded. This replaces loading every
        // personparam into PHP, computing a mean that included the user, and a
        // percentile that ignored ties.
        $attemptuserid = (int) $DB->get_field('adaptivequiz_attempt', 'userid', ['id' => $adaptiveattemptid]);
        $stats = catquiz::get_peer_comparison_stats(
            (int) $existingdata['contextid'],
            (int) $catscaleid,
            round($ability, 4),
            $attemptuserid
        );
        $npeers = (int) $stats->n;

        $catscale = catscale::return_catscale_object($catscaleid);

        // Midrank percentile: 100 * (n_lower + 0.5 * n_equal) / n_peers. Ties are
        // split evenly, and the compared user is not part of n_peers.
        $quantile = $npeers <= 0
            ? 0
            : (((float) $stats->lowercount + 0.5 * (float) $stats->equalcount) / $npeers) * 100;

        $testaverage = $npeers > 0 ? (float) $stats->meanvalue : 0;

        $catscaleclass = new catscale($catscaleid);
        $abilityrange = $catscaleclass->get_ability_range();
        $middle = ($abilityrange['minscalevalue'] + $abilityrange['maxscalevalue']) / 2;

        $testaverageinrange = feedbacksettings::sanitize_range_min_max(
            $testaverage,
            $abilityrange['minscalevalue'],
            $abilityrange['maxscalevalue']
        );

        $abilityinrange = feedbacksettings::sanitize_range_min_max(
            $ability,
            $abilityrange['minscalevalue'],
            $abilityrange['maxscalevalue']
        );

        if (!($abilityrange['minscalevalue'] < $abilityrange['maxscalevalue'])) {
            throw new \moodle_exception('error:minmaxrangeequal', 'local_catquiz');
        }
        $scalingfactor = 1 / ((float) $abilityrange['maxscalevalue'] - (float) $abilityrange['minscalevalue']) * 100;
        $testaverageposition = $scalingfactor * (-(float) $abilityrange['minscalevalue'] + $testaverageinrange);
        $userabilityposition = $scalingfactor * (-(float) $abilityrange['minscalevalue'] + $abilityinrange);
        $betterthan = '';
        if (round($quantile, 0) >= self::MIN_BETTER_THAN_LIMIT) {
            $betterthan = get_string('feedbackcomparison_betterthan', 'local_catquiz', ['quantile' => round($quantile, 0)]);
        }

        $hasenoughpeers = $npeers >= self::get_min_peers();
        $text = get_string(
            $hasenoughpeers ? 'feedbackcomparetoaverage' : 'feedbackcomparetoaverage_nopeers',
            'local_catquiz',
            [
                'betterthan' => $betterthan,
                'quotedscale' => $catscale->name,
                'ability_global' => feedback_helper::localize_float($abilityinrange),
                'se_global' => feedback_helper::localize_float($newdata['se'][$catscaleid]),
                'average_ability' => feedback_helper::localize_float($testaverageinrange),
                'scale_min' => feedback_helper::localize_float($abilityrange['minscalevalue']),
                'scale_max' => feedback_helper::localize_float($abilityrange['maxscalevalue']),
            ]
        );

        return [
            'contextid' => $existingdata['contextid'],
            'testaverageability' => sprintf('%.2f', $testaverageinrange),
            'userability' => sprintf('%.2f', $abilityinrange),
            'testaverageposition' => $testaverageposition,
            'userabilityposition' => $userabilityposition,
            'comparisontext' => $text,
            'colorbar' => [
                'colorgradestring' => $this->get_colorgradientstring((object) $quizsettings, $catscaleid),
            ],
            'colorbarlegend' => [
                'feedbackbarlegend' => feedback_helper::get_colorbarlegend((object) $quizsettings, $catscaleid),
            ],
            'currentability' => get_string('currentability', 'local_catquiz', $catscale->name),
            'currentabilityfellowstudents' => get_string('currentabilityfellowstudents', 'local_catquiz', $catscale->name),
            'lowerscalelimit' => $abilityrange['minscalevalue'],
            'upperscalelimit' => $abilityrange['maxscalevalue'],
            'middle' => $middle,
            'comparetotestaverage_has_worse' => (int) $stats->lowercount > 0,
            'comparetotestaverage_has_enough_peers' => $hasenoughpeers,
            'personabilities_abilities' => $this->get_restructured_abilities($existingdata, $newdata),
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
        global $DB;

        $abilitysteps = [];
        $abilitystep = 0.25;
        $interval = $abilitystep * 2;
        if (isset($initialcontext['personabilities_abilities'][$primarycatscale['id']]['abilityrange'])) {
            $abilityrange = $initialcontext['personabilities_abilities'][$primarycatscale['id']]['abilityrange'];
        } else {
            $abilityrange = $this->feedbackhelper->get_ability_range((int) $primarycatscale['id']);
        };

        $ul = (float) $abilityrange['maxscalevalue'];
        $ll = (float) $abilityrange['minscalevalue'];
        for ($i = $ll + $abilitystep; $i <= ($ul - $abilitystep); $i += $interval) {
            $abilitysteps[] = $i;
        }
        // The test information line, from the items with parameters. Issue #150: it may be missing - an empty
        // pool, only pilot items, parameters not calculated yet - and then it is left out, not invented; the bars
        // do not depend on it. Re-audit 27.10.2026: why it is missing, for the scale and CAT context of the attempt.
        $testinfodata = $this->feedbackhelper->testinfo_for_scale(
            (int) $primarycatscale['id'],
            (int) $initialcontext['contextid'],
            $abilitysteps
        );
        $testinfo = $testinfodata['values'];

        // Prepare data for scorecounter bars.
        // Scope the histogram to the current CAT context so it does
        // not mix person parameters from other contexts.
        /* One row per person, not one per estimate: local_catquiz_personparams keeps the history,
           so counting every row would count a person as often as they have been measured.
           Issue #88: the same population as the percentile of the text above the chart (peer_population);
           the histogram shows everybody, the person compared included. */
        [$peersql, $peerparams] = \local_catquiz\local\statistics\peer_population::sql(
            (int) $initialcontext['contextid'],
            (int) $primarycatscale['id']
        );
        $abilityrecords = $DB->get_records_sql($peersql, $peerparams);
        // Issue #150: the bars of the statistics, (step - 0.25, step + 0.25]. Rounding to a grid of 0.25 left
        // out every value next to an integer.
        $counts = chart_output::counts_per_step(
            $abilitysteps,
            array_map(fn($record) => (float) $record->ability, $abilityrecords),
            $abilitystep
        );
        $colors = [];
        foreach ($abilitysteps as $as) {
            $colors[] = $this->feedbackhelper->get_color_for_personability(
                (array) $this->get_progress()->get_quiz_settings(),
                $as,
                intval($primarycatscale['id'])
            );
        }

        $aserieslabel = "";
        if (isset($initialcontext['personabilities_abilities'][$primarycatscale['id']]['name'])) {
            $scalename = $initialcontext['personabilities_abilities'][$primarycatscale['id']]['name'];
            $aserieslabel = get_string('scalescorechartlabel', 'local_catquiz', $scalename);
        }
        $aseries = new chart_series($aserieslabel, $counts);
        $aseries->set_colors($colors);
        $chart = new chart_bar();
        $chart->add_series($aseries);

        $extended = $this->has_extended_view_permissions();
        $notes = '';
        if ($extended && $testinfo !== null) {
            $testinfolabel = get_string('testinfolabel', 'local_catquiz');
            $tiseries = new chart_series($testinfolabel, $this->feedbackhelper->scalevalues($testinfo, $counts));
            $tiseries->set_type(chart_series::TYPE_LINE);
            $tiseries->set_smooth(true);
            $chart->add_series($tiseries);
        }
        if ($extended) {
            $notename = $initialcontext['personabilities_abilities'][$primarycatscale['id']]['name']
                ?? catscale::return_catscale_object((int) $primarycatscale['id'])->name;
            $notes = feedback_helper::testinfo_note($testinfodata, (string) $notename);
        }

        // Issue #150: the bars are the ability steps - not the keys of the test information, which may be empty.
        $chart->set_labels(chart_output::step_labels($abilitysteps));
        // Re-audit 27.10.2026: the axes, as in the statistics.
        $chart->get_xaxis(0, true)->set_label(get_string('personability', 'local_catquiz'));
        $chart->get_yaxis(0, true)->set_label(get_string('abilityprofilepersons', 'local_catquiz'));

        $nodata = get_string('abilityprofilenodata', 'local_catquiz');
        // With the data table for everybody (issue #150): the bars without it are not readable for a screen reader,
        // and the table shows nothing the bars do not.
        $out = array_sum($counts) > 0 ? chart_output::render($chart, true, $nodata) . $notes : chart_output::notice($nodata);
        return [
            'chart' => $out,
            'charttitle' => get_string('abilityprofile_title', 'local_catquiz'),
        ];
    }

    /**
     * Shows the result of the global scale: needs it valid, not a reported scale (2026-10-14).
     *
     * @return string
     */
    public function get_result_dependency(): string {
        return self::DEPENDS_ON_GLOBAL;
    }
}
