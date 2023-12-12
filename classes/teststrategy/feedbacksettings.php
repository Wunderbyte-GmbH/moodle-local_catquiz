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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/lib.php');

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
    public $areastohide;

    /**
     * @var ?array
     */
    public $definedareastohidekeys;


    /**
     * Constructor for feedbackclass.
     *
     * @param int $primaryscaleid
     * @param ?array $areastohide
     */
    public function __construct($primaryscaleid = LOCAL_CATQUIZ_PRIMARYCATSCALE_DEFAULT, ?array $areastohide = []) {
        $this->primaryscaleid = $primaryscaleid;

        // Default sortorder is descendent.
        $this->sortorder = LOCAL_CATQUIZ_SORTORDER_DESC;

        if (count($areastohide) > 0) {
            $this->areastohide = $areastohide;
        }

    }

    /**
     * Return the right catscaleid and information about type.
     *
     * @param array $personabilities
     */
    public function set_areas_to_hide_keys(array $areanames) {
        $this->definedareastohidekeys = $areanames;
    }

    /**
     * Define and set areanamed to feedback data keys.
     *
     * @param string $generatorname
     * @return array
     */
    private function get_feedbackdatakeys_to_exclude(string $generatorname): array {

        if (empty($this->definedareastohidekeys)) {
            return [];
        }

        return $this->definedareastohidekeys[$generatorname];
    }

    /**
     * Remove unwanted areas (keys) from array.
     *
     * @param array $feedbackdata
     * @param string $generatorname
     */
    public function hide_defined_elements(array $feedbackdata, string $generatorname) {
        if (empty($this->areastohide[$generatorname])) {
            return $feedbackdata;
        } else if (count($this->areastohide[$generatorname]) < 1) {
            // If there is the key of the feedbackgenerator set without subentries, data of the whole generator is hidden.
            return [];
        }

        // Some exclusion keys are not equivalent to the keys in $feedbackdata.
        // Therefore we get the definition from each generator.
        $areanamesforfeedbackdatakeys = $this->get_feedbackdatakeys_to_exclude($generatorname);
        $excludedkeys = [];

        if (!empty($feedbackdata) && !empty($areanamesforfeedbackdatakeys)) {
            // Match fieldnames with keys as defined in areanamesforfeedbackdatakeys.
            foreach ($areanamesforfeedbackdatakeys as $areaname => $keys) {
                if (in_array($areaname, $this->areastohide[$generatorname])) {
                    $excludedkeys = array_merge($excludedkeys, $keys);
                }
            }
            // Add the areas named identically as in feedbackdata.
            foreach ($this->areastohide[$generatorname] as $keytoexclude) {
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
     */
    public function get_scaleid_and_stringkey(array $personabilities, object $quizsettings, int $catscaleid) {

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
}
