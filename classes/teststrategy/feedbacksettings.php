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
     * Constructor for feedbackclass.
     *
     * @param int $primaryscaleid
     */
    public function __construct($primaryscaleid = LOCAL_CATQUIZ_PRIMARYCATSCALE_DEFAULT) {
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
     * @param int $teststrategy
     * @param array $personabilities
     * @param array $feedbackdata
     * @param int $catscaleid
     * @param bool $feedbackonlyfordefinedscaleid
     *
     * @return array
     */
    public static function return_scales_according_to_strategy(
        int $teststrategyid,
        array $personabilities,
        array $feedbackdata,
        int $catscaleid = 0,
        bool $feedbackonlyfordefinedscaleid = false): array {

        if ($feedbackonlyfordefinedscaleid) {
            foreach ($personabilities as $key => $value) {
                if ($key == $catscaleid) {
                    $selectedscale[$key] = $value;
                }
            }
            return $selectedscale;
        }
        $teststrategy = info::get_teststrategy($teststrategyid);
        $personabilities = $teststrategy->select_scales_for_report(
            $personabilities,
            $feedbackdata,
            $catscaleid,
            $feedbackonlyfordefinedscaleid
        );


        // switch ($teststrategy) {
        //     case LOCAL_CATQUIZ_STRATEGY_LOWESTSUB:
        //         $minscale = array_search(min($personabilities), $personabilities);
        //         return [$minscale => $personabilities[$minscale]];
        //     case LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB:
        //         $maxscale = array_search(max($personabilities), $personabilities);
        //         return [$maxscale => $personabilities[$maxscale]];
        //     default:
        //     return $personabilities;
        // }
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

