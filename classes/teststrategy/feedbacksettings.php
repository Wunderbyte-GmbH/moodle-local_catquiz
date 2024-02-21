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
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedbacksettings {
    /** The scale for which detailed feedback will be displayed. Can be a single scaleid or an array of scales.
     * @var int
     */
    public $primaryscaleid;

    /** The id of the teststrategy.
     * @var int
     */
    public $strategyid;

    /**
     * @var boolean
     */
    public $displayscaleswithoutitemsplayed = false;

    /**
     * @var boolean
     */
    public $overridesettings = true;

    /**
     * @var int
     */
    public $sortorder;

    /**
     * @var ?array
     */
    public $areastohide = [];

    /**
     * @var ?array
     */
    public $areashiddenbydefault = [];

    /**
     * @var ?array
     */
    public $definedareastohidekeys;

    /**
     * @var ?int
     */
    public $nmintest;

    /**
     * @var ?int
     */
    public $nminscale;

    /**
     * @var ?int
     */
    public $rootscale;

    /**
     * @var ?float
     */
    public $semax;

    /**
     * @var ?float
     */
    public $fraction;

    /**
     * Constructor for feedbackclass.
     *
     * @param int $strategyid
     * @param int $primaryscaleid
     */
    public function __construct(
        $strategyid,
        $primaryscaleid = LOCAL_CATQUIZ_PRIMARYCATSCALE_DEFAULT
        ) {

        $this->strategyid = $strategyid;
        $this->primaryscaleid = $primaryscaleid;

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
     * Return array with catscaleids and personabilites according to teststrategy.
     *
     * @param array $personabilities
     * @param array $newdata
     * @param array $quizsettings
     * @param int $teststrategy
     * @param int $catscaleid
     * @param bool $feedbackonlyfordefinedscaleid
     *
     * @return array
     */
    public function return_scales_according_to_strategy(
        array $personabilities,
        array $newdata,
        array $quizsettings,
        int $teststrategyid = LOCAL_CATQUIZ_DEFAULT_NONSENSE_TESTSTRATEGY,
        int $catscaleid = 0,
        bool $feedbackonlyfordefinedscaleid = false): array {

        $this->set_params_from_attempt($newdata, $quizsettings);

        // As default we use the strategy defined via constructor.
        if ($teststrategyid === LOCAL_CATQUIZ_DEFAULT_NONSENSE_TESTSTRATEGY) {
            $teststrategyid = $this->strategyid;
        }

        if ($feedbackonlyfordefinedscaleid) {
            foreach ($personabilities as $key => $value) {
                if ($key == $catscaleid) {
                    $selectedscale[$key] = $value;
                }
            }
            return $selectedscale;
        }

        $teststrategy = info::get_teststrategy($teststrategyid);
        // TODO: parameter richtig mitgeben
        // TODO: in allen funktionen implementieren
        // TODO: apply_feedbacksettings raus nehmen und funktionalität einbauen
        // TODO: abgleich mit liste, stimmt das so?
        // TODO: make sure, sorting is applied.
        // In jedem relevanten Feedbackgenerator aufrufen. ? abstrakte methode?
        // TODO: root / parentscale nicht anzeigen (? nirgends ?)

        // Returns array with scales corresponding to settings of strategy.
        // If scales do not meet all requirements, ['error'] will be returned.
        // For scale selected for report, a key ['primary'] is added.

        // Apply exclusion of scales from personabilities.
        $personabilities = $this->filter_excluded_scales($personabilities, $quizsettings);

        $personabilities = $teststrategy->select_scales_for_report(
            $this,
            $personabilities,
            $newdata,
            $catscaleid,
            $feedbackonlyfordefinedscaleid
        );

        return $personabilities;
    }

    /**
     * Exclude scales that don't meet minimum of items required in quizsettings.
     *
     * @param mixed $personabilities
     * @param mixed $feedbackdata
     *
     * @return array
     *
     */
    public function filter_nminscale($personabilities, $feedbackdata): array {
        $nminscale = $this->nminscale;
        if (!empty($nminscale)) {
            foreach ($personabilities as $scaleid => $array) {
                $ninscale = count($feedbackdata['questionsperscale'][$scaleid]);
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
     * Exclude scales where standarderror is not in range.
     *
     * @param mixed $personabilities
     * @param mixed $feedbackdata
     *
     * @return array
     *
     */
    public function filter_semax($personabilities, $feedbackdata): array {
        $semax = $this->semax;
        if (!empty($semax)) {
            foreach ($personabilities as $scaleid => $array) {
                $se = $feedbackdata['se'][$scaleid];
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
     * Filter the results to check if reporting is enabled in quizsettings.
     *
     * @param array $personabilities
     * @param array $quizsettings
     *
     * @return array
     *
     */
    private function filter_excluded_scales(array $personabilities, array $quizsettings): array {
        foreach ($personabilities as $catscale => $array) {
            if (empty($quizsettings['catquiz_scalereportcheckbox_' . $catscale])) {
                $personabilities[$catscale]['excluded'] = true;
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
    private function set_params_from_attempt(array $newdata, array $quizsettings): void {
        $this->semax = (float) $newdata['se_max'];
        $this->nmintest = (int) $quizsettings['maxquestionsgroup']['catquiz_maxquestions'];
        $this->nminscale = (int) $quizsettings['maxquestionsscalegroup']['catquiz_maxquestionspersubscale'];
        $this->rootscale = (int) $quizsettings['catquiz_catscales'];
        // Find average fraction.
        $f = 0.0;
        $i = 0;
        $playedquestions = $newdata['progress']->playedquestions;
        foreach ($playedquestions as $componentid => $questionclass) {
            $fraction = $newdata['progress']['responses'][$componentid]['fraction'];
            $f += (float) $fraction;
            $i ++;
        }
        $this->fraction = $f / $i;
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

