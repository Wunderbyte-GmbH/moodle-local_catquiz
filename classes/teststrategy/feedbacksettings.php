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

namespace local_catquiz\teststrategy;

use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Class feedbacksettings teststrategy and feedbackgenerator.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedbacksettings {
    /**
     * Used to mark a field as hidden.
     *
     * @var string
     */
    const FIELD_HIDDEN = 'hidden';

    /**
     * Marks a scale that is not to be REPORTED - a display decision, not a
     * measurement problem.
     *
     * The generic 'excluded' flag used to carry both meanings, so every consumer
     * had to inspect the accompanying error array to tell "the measurement is
     * unusable" from "the author switched reporting off". Keeping the two apart
     * lets a scale be statistically valid (and therefore persisted and available
     * for carry-over) while simply not being shown (issue #7).
     *
     * @var string
     */
    const FIELD_NOTREPORTED = 'notreported';

    /** The id of the teststrategy.
     * @var int
     */
    public int $strategyid;

    /**
     * @var bool
     */
    public bool $displayscaleswithoutitemsplayed = false;

    /**
     * @var bool
     */
    public bool $overridesettings = true;

    /**
     * @var int
     */
    public int $sortorder;

    /**
     * The scale whose feedback is forced, or 0 for none (issue #10).
     *
     * @var int
     */
    public int $forcedscaleid = 0;

    /**
     * Whether feedback is restricted to the forced scale only (issue #10).
     *
     * @var bool
     */
    public bool $feedbackonlyfordefinedscaleid = false;

    /**
     * @var ?array
     */
    public ?array $areastohide = [];

    /**
     * @var ?array
     */
    public ?array $areashiddenbydefault = [];

    /**
     * @var ?array
     */
    public ?array $definedareastohidekeys;

    /**
     * @var ?int
     */
    public ?int $nmintest;

    /**
     * @var ?int
     */
    public ?int $nminscale;

    /**
     * @var ?int
     */
    public ?int $rootscale;

    /**
     * @var ?float
     */
    public ?float $semax;

    /**
     * @var ?float
     */
    public ?float $semin;

    /**
     * @var ?float
     */
    public ?float $fraction;

    /**
     * Constructor for feedbacksettings.
     *
     * @param int $strategyid
     */
    public function __construct(int $strategyid) {

        $this->strategyid = $strategyid;

        // Default sortorder is descendent.
        $this->sortorder = LOCAL_CATQUIZ_SORTORDER_DESC;

        // The former summary tab (Wunderbyte) was the only area hidden by default; its figures are in the
        // tab "Statistics & export" now (decision of 2026-10-14).
        $this->areashiddenbydefault = [];
    }

    /**
     * Return the right catscaleid and information about type.
     *
     * @param array $areastohide
     * @param array $areastoshow
     */
    public function set_hide_and_show_areas(array $areastohide = [], array $areastoshow = []) {

        if (count($areastoshow) > 0) {
            foreach ($areastoshow as $areatoshow) {
                if (in_array($areatoshow, $this->areashiddenbydefault)) {
                    $index = array_search($areatoshow, $this->areashiddenbydefault);
                    unset($this->areashiddenbydefault[$index]);
                }
            }
        }
        $hideareas = array_merge($this->areashiddenbydefault, $areastohide);
        if (count($hideareas) > 0) {
            $this->areastohide = $hideareas;
        }
    }

    /**
     * Remove unwanted areas (keys) from array.
     *
     * @param array $feedbackdata
     * @param string $generatorname
     */
    public function hide_defined_elements(array $feedbackdata, string $generatorname) {
        $this->areastohide = array_merge($this->areastohide, $this->areashiddenbydefault);
        if (empty($this->areastohide)) {
            return $feedbackdata;
        }
        // If generator is set as exclusion key, hide the whole data from this generator.
        foreach ($this->areastohide as $areatohide) {
            if ($areatohide == $generatorname) {
                return [];
            }
        };
        $excludedkeys = [];

        if (!empty($feedbackdata)) {
            // Add the areas named identically as in feedbackdata.
            foreach ($this->areastohide as $keytoexclude) {
                if (in_array($keytoexclude, array_keys($feedbackdata))) {
                    $excludedkeys[] = $keytoexclude;
                }
            }
            // Remove the keys found in $excludedkeys from $feedbackdata.
            $excludedkeys = array_intersect($excludedkeys, array_keys($feedbackdata));
            $feedbackdata = array_diff_key($feedbackdata, array_flip($excludedkeys));
        }
        return $feedbackdata;
    }

    /**
     * Return the right catscaleid and information about type.
     *
     * @param array $personabilities
     * @param object $quizsettings
     * @param int $catscaleid
     *
     * @return array
     */
    public function get_scaleid_and_stringkey(array $personabilities, object $quizsettings, int $catscaleid) {

        if (empty($personabilities)) {
            return [];
        }
        switch ($catscaleid) {
            // Default is parent.
            case LOCAL_CATQUIZ_PRIMARYCATSCALE_DEFAULT:
                $selectedscaleid = $quizsettings->catquiz_catscales;
                $selectedscale = 'parentscaleselected';
                break;
            case LOCAL_CATQUIZ_PRIMARYCATSCALE_PARENT:
                $selectedscaleid = $quizsettings->catquiz_catscales;
                $selectedscale = 'parentscaleselected';
                break;
            case LOCAL_CATQUIZ_PRIMARYCATSCALE_LOWEST:
                $selectedscaleid = array_search(min($personabilities), $personabilities);
                $selectedscale = 'lowestscaleselected';
                break;
            case LOCAL_CATQUIZ_PRIMARYCATSCALE_STRONGEST:
                $selectedscaleid = array_search(max($personabilities), $personabilities);
                $selectedscale = 'strongestscaleselected';
                break;
            default:
                $selectedscaleid = $catscaleid;
                $selectedscale = 'scaleselected';
                break;
        }

        return [
            'selectedscaleid' => $selectedscaleid,
            'selectedscalestringkey' => $selectedscale,
        ];
    }

    /**
     * Excludes scales without a productive answer in this attempt (issue #128).
     *
     * A value carried over from an earlier attempt is not a result of this one. Strategies that
     * pick one scale - the lowest skill gap, the greatest strength - must choose among measured
     * scales only; otherwise an unmeasured value can win the choice and the attempt ends up with
     * no reported scale at all. Independent of any minimum N.
     *
     * @param array $personabilities
     * @param array $feedbackdata
     * @return array
     */
    public function filter_unmeasured(array $personabilities, array $feedbackdata): array {
        $progress = $feedbackdata['progress'] ?? null;
        if (!$progress instanceof progress) {
            $progress = progress::load(
                $feedbackdata['attemptid'],
                'mod_adaptivequiz',
                $feedbackdata['contextid']
            );
        }
        foreach (array_keys($personabilities) as $scaleid) {
            if ($progress->get_num_answered_productive_questions((int) $scaleid) === 0) {
                $personabilities[$scaleid]['excluded'] = true;
            }
        }
        return $personabilities;
    }

    /**
     * Marks scales whose productive items were all answered wrongly or all correctly (issue #140).
     *
     * The validator decides validity on this rule by itself (attempt_result_validator). This filter
     * lets a strategy see the same rule before it chooses: the greatest strength or the lowest skill
     * gap is picked among scales that can carry a valid result. It used to be decided per strategy on
     * the fraction of the whole test, pilot items included - all wrong for one strategy, all right
     * for another, both for a third - so a subscale answered entirely correctly inside a mixed test
     * passed every check. Only scales measured in this attempt are judged.
     *
     * @param array $personabilities
     * @param array $feedbackdata
     * @param bool $hide If given, the scale is not excluded but instead the value is marked as hidden.
     * @return array
     */
    public function filter_fraction(array $personabilities, array $feedbackdata, bool $hide = false): array {
        $progress = $feedbackdata['progress'] ?? null;
        if (!$progress instanceof progress) {
            $progress = progress::load(
                $feedbackdata['attemptid'],
                'mod_adaptivequiz',
                $feedbackdata['contextid']
            );
        }
        foreach (array_keys($personabilities) as $scaleid) {
            if ($progress->get_num_answered_productive_questions((int) $scaleid) === 0) {
                continue;
            }
            $fraction = $progress->get_fraction_for_scale((int) $scaleid);
            if ($fraction === null || ($fraction > 0.0 && $fraction < 1.0)) {
                continue;
            }
            $personabilities[$scaleid]['error']['fraction'] = [
                'fraction' => $fraction,
                'expected' => '0 < f < 1',
            ];
            $personabilities[$scaleid][$hide ? self::FIELD_HIDDEN : 'excluded'] = true;
        }
        return $personabilities;
    }

    /**
     * Exclude scales that don't meet minimum of items required in quizsettings.
     *
     * @param array $personabilities
     * @param array $feedbackdata
     * @param bool $hide If given, the scale is not excluded but instead the value is marked as hidden.
     *
     * @return array
     *
     */
    public function filter_nminscale(array $personabilities, array $feedbackdata, bool $hide = false): array {
        $progress = progress::load(
            $feedbackdata['attemptid'],
            'mod_adaptivequiz',
            $feedbackdata['contextid']
        );
        $nminscale = $this->nminscale;
        if (!empty($nminscale)) {
            foreach ($personabilities as $scaleid => $array) {
                // Productive items answered in this attempt (issue #117) - the N the validator uses.
                // Played questions would count items shown but not answered, and pilot items.
                $ninscale = $progress->get_num_answered_productive_questions((int) $scaleid);
                if ($ninscale < $nminscale) {
                    $personabilities[$scaleid]['error']['nminscale'] = [
                        'nminscaledefined' => $nminscale,
                        'nscalecurrent' => $ninscale,
                    ];
                    $action = $hide ? self::FIELD_HIDDEN : 'excluded';
                    $personabilities[$scaleid][$action] = true;
                }
            }
        }
        return $personabilities;
    }

    /**
     * Exclude scales that don't meet minimum of items required in quizsettings.
     *
     * @param array $personabilities
     * @param array $feedbackdata
     * @param bool $hide If given, the scale is not excluded but instead the value is marked as hidden.
     *
     * @return array
     *
     */
    public function filter_nmintest(array $personabilities, array $feedbackdata, bool $hide = false): array {
        $nmintest = $this->nmintest;
        if (!empty($nmintest)) {
            // Productive items answered in the whole test (issue #128) - the N the validator uses.
            // Played questions would let pending, unanswered and pilot items meet the minimum.
            $nintest = $feedbackdata['progress']->get_num_answered_productive_questions();
            if ($nintest < $nmintest) {
                foreach ($personabilities as $scaleid => $scalearray) {
                    $personabilities[$scaleid]['error']['nminscale'] = [
                        'nmintestdefined' => $nmintest,
                        'ntestcurrent' => $nintest,
                    ];
                    $action = $hide ? self::FIELD_HIDDEN : 'excluded';
                    $personabilities[$scaleid][$action] = true;
                }
            }
        }
        return $personabilities;
    }
    /**
     * Exclude scales where standarderror is not in range.
     *
     * @param array $personabilities
     * @param array $feedbackdata
     * @param bool $hide If given, the scale is not excluded but instead the value is marked as hidden.
     *
     * @return array
     *
     */
    public function filter_semax(array $personabilities, array $feedbackdata, bool $hide = false): array {
        global $CFG;
        if (!isset($this->semax)) {
            return $personabilities;
        }
        $semax = $this->semax;
        if (!empty($semax)) {
            foreach ($personabilities as $scaleid => $array) {
                if (
                    !is_array($feedbackdata)
                    || !array_key_exists('se', $feedbackdata)
                    || !array_key_exists($scaleid, $feedbackdata['se'])
                ) {
                    $feedbackdata['se'][$scaleid] = INF;
                    if ($CFG->debug > 0) {
                        var_dump($feedbackdata);
                        var_dump($personabilities);
                        throw new \Exception(sprintf('No standarderror is set for scale %s', $scaleid));
                    }
                }
                $se = $feedbackdata['se'][$scaleid];
                if ($se > $semax) {
                    $personabilities[$scaleid]['error']['se'] = [
                        'semaxdefined' => $semax,
                        'securrent' => $se,
                    ];
                    $action = $hide ? self::FIELD_HIDDEN : 'excluded';
                    $personabilities[$scaleid][$action] = true;
                }
            }
        }
        return $personabilities;
    }

    /**
     * Exclude scales where standarderror is not in range.
     *
     * @param array $personabilities
     * @param array $feedbackdata
     *
     * @return array
     *
     */
    public function filter_semin(array $personabilities, array $feedbackdata): array {
        $semin = $this->semin;
        if (empty($semin)) {
            return $personabilities;
        }
        foreach ($personabilities as $scaleid => $array) {
            $se = $feedbackdata['se'][$scaleid];
            if ($se < $semin) {
                $personabilities[$scaleid]['error']['se'] = [
                    'semindefined' => $semin,
                    'securrent' => $se,
                ];
                $personabilities[$scaleid]['excluded'] = true;
            }
        }
        return $personabilities;
    }
    /**
     * Filter the results to check if reporting is enabled in quizsettings.
     *
     * @param array $personabilities
     * @param stdClass $quizsettings
     *
     * @return array
     */
    public function filter_excluded_scales(array $personabilities, stdClass $quizsettings): array {
        foreach ($personabilities as $catscale => $array) {
            $propertyname = sprintf('catquiz_scalereportcheckbox_%s', $catscale);
            if (empty($quizsettings->$propertyname)) {
                /* Reporting switched off is a DISPLAY decision. It must not be
                   reported as 'excluded', which every consumer reads as "the
                   measurement is unusable". */
                $personabilities[$catscale][self::FIELD_NOTREPORTED] = true;
                $personabilities[$catscale]['error']['checkbox'] = [
                    'scalereportcheckboxinquizsettings' => false,
                ];
            }
        }
        return $personabilities;
    }


    /**
     * Set params defined in quizsettings and attemptdata.
     *
     * @param array $newdata
     * @param stdClass $quizsettings
     *
     * @return void
     */
    public function set_params_from_attempt(array $newdata, stdClass $quizsettings): void {
        $this->semax = $newdata['se_max'] ?? null;
        $this->semin = $newdata['se_min'] ?? null;
        $maxquestiongroup = isset($quizsettings->maxquestionsgroup) ? (array) $quizsettings->maxquestionsgroup : null;
        $maxquestionsscalegroup = !empty($quizsettings->maxquestionsscalegroup) ?
            (array) $quizsettings->maxquestionsscalegroup : null;
        $this->nmintest = isset($maxquestiongroup['catquiz_minquestions']) ?
            (int) $maxquestiongroup['catquiz_minquestions'] : null;
        $this->nminscale = isset($maxquestionsscalegroup['catquiz_minquestionspersubscale']) ?
            (int) $maxquestionsscalegroup['catquiz_minquestionspersubscale'] : null;
        $this->rootscale = isset($quizsettings->catquiz_catscales) ? (int) $quizsettings->catquiz_catscales : null;
        // Find average fraction.
        $f = 0.0;
        $i = 0;
        $progress = progress::load($newdata['attemptid'], $newdata['component'], $newdata['contextid']);
        $responses = $progress->get_responses();
        foreach ($responses as $responsearray) {
            $fraction = (float) $responsearray['fraction'];
            $f += $fraction;
            $i++;
        }
        if (!empty($i)) {
            $this->fraction = $f / $i;
        } else {
            $this->fraction = $f;
        }
    }

    /**
     * Check if a value is within a defined min max range and if not return border-value (min or max).
     *
     * @param float $value
     * @param float $min
     * @param float $max
     *
     * @return float
     */
    public static function sanitize_range_min_max(float $value, float $min, float $max): float {
        if ($min > $max) {
            throw new \moodle_exception('minmustbelowerthanmax', 'local_catquiz');
        }

        if ($value <= $min) {
            return $min;
        }
        if ($value >= $max) {
            return $max;
        }
        return $value;
    }

    /**
     * Returns if sortorder is set to ascending
     *
     * @return bool
     */
    public function is_sorted_ascending() {
        return $this->sortorder === LOCAL_CATQUIZ_SORTORDER_ASC;
    }

    /**
     * Returns if sortorder is set to descending
     *
     * @return bool
     */
    public function is_sorted_descending() {
        return $this->sortorder === LOCAL_CATQUIZ_SORTORDER_DESC;
    }

    /**
     * Returns if scales are sorted by name
     *
     * @return bool
     */
    public function is_sorted_by_name() {
        return $this->sortorder = LOCAL_CATQUIZ_SORTORDER_BY_NAME;
    }

    /**
     * Set sort order to ascending
     *
     * @return self
     */
    public function set_sort_ascending() {
        $this->sortorder = LOCAL_CATQUIZ_SORTORDER_ASC;
        return $this;
    }

    /**
     * Sets the sortorder to sort by name
     *
     * @return self
     */
    public function set_sort_by_name() {
        $this->sortorder = LOCAL_CATQUIZ_SORTORDER_BY_NAME;
        return $this;
    }
}
