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

use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\output\attemptfeedback;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/local/catquiz/lib.php');

/**
 * Class feedbacksettings teststrategy and feedbackgenerator.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedbacksettings {

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
     * Constructor for feedbackclass.
     *
     * @param int $strategyid
     */
    public function __construct(int $strategyid) {

        $this->strategyid = $strategyid;

        // Default sortorder is descendent.
        $this->sortorder = LOCAL_CATQUIZ_SORTORDER_DESC;

        $this->areashiddenbydefault = ['questionssummary'];

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
     * Return all colors defined in feedbacksettings for this scale.
     *
     * @param array $quizsettings
     * @param int $catscaleid
     *
     * @return array
     */
    public function get_defined_feedbackcolors_for_scale(array $quizsettings, int $catscaleid) {

        $colors = [];

        $numberoffeedbackoptions = intval($quizsettings['numberoffeedbackoptionsselect']) ?? 8;
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);

        for ($i = 1; $i <= $numberoffeedbackoptions; $i++) {
            $colorkey = 'wb_colourpicker_' . $catscaleid . '_' . $i;
            $rangestartkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_" . $i;
            $rangeendkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $i;
            $colorname = $quizsettings[$colorkey];
            if (isset($colorarray[$colorname])) {
                    $colors[$colorarray[$colorname]]['rangestart'] = $quizsettings[$rangestartkey];
                    $colors[$colorarray[$colorname]]['rangeend'] = $quizsettings[$rangeendkey];
            }

        }
        return $colors;
    }

    /**
     * Exclude scales that don't meet minimum of items required in quizsettings.
     *
     * @param array $personabilities
     * @param array $feedbackdata
     *
     * @return array
     *
     */
    public function filter_nminscale(array $personabilities, array $feedbackdata): array {
        $progress = progress::load(
            $feedbackdata['attemptid'],
            'mod_adaptivequiz',
            $feedbackdata['contextid']
        );
        $nminscale = $this->nminscale;
        if (!empty($nminscale)) {
            foreach ($personabilities as $scaleid => $array) {
                $ninscale = count($progress->get_playedquestions(true, $scaleid));
                if ($ninscale < $nminscale) {
                    $personabilities[$scaleid]['error']['nminscale'] = [
                        'nminscaledefined' => $nminscale,
                        'nscalecurrent' => $ninscale,
                    ];
                    $personabilities[$scaleid]['excluded'] = true;
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
     *
     * @return array
     *
     */
    public function filter_nmintest(array $personabilities, array $feedbackdata): array {
        $nmintest = $this->nmintest;
        if (!empty($nmintest)) {
            $nintest = $feedbackdata['progress']->get_num_playedquestions();
            if ($nintest < $nmintest) {
                foreach ($personabilities as $scaleid => $scalearray) {
                    $personabilities[$scaleid]['error']['nminscale'] = [
                        'nmintestdefined' => $nmintest,
                        'ntestcurrent' => $nintest,
                    ];
                    $personabilities[$scaleid]['excluded'] = true;
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
    public function filter_semax(array $personabilities, array $feedbackdata): array {
        global $CFG;
        if (!isset($this->semax)) {
            return $personabilities;
        }
        $semax = $this->semax;
        if (!empty($semax)) {
            foreach ($personabilities as $scaleid => $array) {
                $se = $feedbackdata['se'][$scaleid] ?? INF;
                if ($se === INF && $CFG->debug > 0) {
                    throw new \Exception(sprintf('No standarderror is set for scale %s', $scaleid));
                }
                if ($se > $semax) {
                    $personabilities[$scaleid]['error']['se'] = [
                        'semaxdefined' => $semax,
                        'securrent' => $se,
                    ];
                    $personabilities[$scaleid]['excluded'] = true;
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
     * @param array $quizsettings
     *
     * @return array
     *
     */
    public function filter_excluded_scales(array $personabilities, \stdClass $quizsettings): array {
        foreach ($personabilities as $catscale => $array) {
            $propertyname = sprintf('catquiz_scalereportcheckbox_%s', $catscale);
            if (empty($quizsettings->$propertyname)) {
                $personabilities[$catscale]['excluded'] = true;
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
     * @param array $quizsettings
     *
     * @return void
     *
     */
    public function set_params_from_attempt(array $newdata, \stdClass $quizsettings): void {
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
            $i ++;
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

}

