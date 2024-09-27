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
 * Class model_raschmodel.
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use coding_exception;
use local_catquiz\catcalc_ability_estimator;
use local_catquiz\catcalc_item_estimator;
use MoodleQuickForm;
use stdClass;

/**
 * This class implements model raschmodel.
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class model_multiparam extends model_raschmodel {

    /**
     * Get static param array
     *
     * @param model_item_param $param
     * @return array
     * @throws coding_exception
     */
    public function get_static_param_array(\local_catquiz\local\model\model_item_param $param): array {
        $disclabel = get_string('discrimination', 'local_catquiz');
        $fractions = [];
        $count = 0;
        foreach ($this->get_parameter_fields($param) as $label => $value) {
            if ($label === 'discrimination') {
                continue;
            }
            if (preg_match('/^fraction_(.*)$/', $label, $matches)) {
                $count = $matches[1];
                $fractions['Fraction ' . $count] = $value;
            } else {
                $fractions['Difficulty ' . $count] = $value;
            }
        }
        return array_merge(
            [$disclabel => $param->get_params_array()['discrimination']],
            $fractions,
        );
    }

    /**
     * Get parameter fields
     *
     * @param model_item_param $param
     * @return array
     */
    public function get_parameter_fields(model_item_param $param): array {
        if (!$param->get_params_array()) {
            return $this->get_default_params();
        }
        $parameters = ['discrimination' => $param->get_params_array()['discrimination']];
        $multiparam = $this->get_multi_param_name();
        $counter = 0;
        foreach ($param->get_params_array()[$multiparam] as $frac => $val) {
            $parameters['fraction_' . ++$counter] = $frac;
            $parameters['difficulty_' . $counter] = $val;
        }
        return $parameters;
    }

    /**
     * Converts an form array to a record
     *
     * @param array $formarray
     * @return stdClass
     */
    public function form_array_to_record(array $formarray): stdClass {
        $diffarray = [];
        $multiparam = $this->get_multi_param_name();
        foreach ($formarray as $key => $val) {
            if (preg_match('/^fraction_(.*)/', $key, $matches)) {
                $fraction = $val;
                continue;
            }
            if (preg_match('/^difficulty_(.*)/', $key, $matches)) {
                $diffarray[$fraction] = $val;
            }
        }

        return (object) [
            'discrimination' => $formarray['discrimination'],
            'json' => json_encode([$multiparam => $diffarray]),
        ];
    }

    /**
     * Get multi param name
     *
     * @return string
     */
    abstract protected function get_multi_param_name(): string;
}
