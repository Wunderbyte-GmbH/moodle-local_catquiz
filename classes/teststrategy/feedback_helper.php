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
 * Class feedback_helper.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy;

use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;

/**
 * Contains helper functions for quiz feedback.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_helper {

    /**
     * Write information about colorgradient for colorbar.
     *
     * @param array $quizsettings
     * @param float $personability
     * @param int $catscaleid
     * @return string
     *
     */
    public function get_color_for_personability(array $quizsettings, float $personability, int $catscaleid): string {
        $default = LOCAL_CATQUIZ_DEFAULT_GREY;
        $abilityrange = $this->get_ability_range($catscaleid);
        if (!$quizsettings ||
            $personability < (float) $abilityrange['minscalevalue'] ||
            $personability > (float) $abilityrange['maxscalevalue']) {
            return $default;
        }
        $numberoffeedbackoptions = intval($quizsettings['numberoffeedbackoptionsselect'])
            ?? LOCAL_CATQUIZ_MAX_SCALERANGE;
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
     * For testing this is called in seperate function.
     *
     * @param mixed $catscaleid
     *
     * @return array
     *
     */
    public function get_ability_range($catscaleid): array {
        $cs = new catscale($catscaleid);
        // Ability range is the same for all scales with same root scale.
        return $cs->get_ability_range();
    }

}
