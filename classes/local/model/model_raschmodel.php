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

use local_catquiz\catcalc_ability_estimator;
use local_catquiz\catcalc_item_estimator;

defined('MOODLE_INTERNAL') || die();

/**
 * This class implements model raschmodel.
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class model_raschmodel extends model_model implements catcalc_item_estimator, catcalc_ability_estimator {

    /**
     * Likelihood 1pl.
     *
     * @param mixed $personability
     * @param mixed $itemdifficulty
     *
     * @return float
     *
     */
    static function likelihood_1pl($personability, $itemdifficulty ) {

        $discrimination = 1; // hardcode override because of 1pl
        return (1 / (1 + exp($discrimination * ($itemdifficulty - $personability))));
    }

    /**
     * Executes the model-specific code to estimate item-parameters based
     * on the given person abilities.
     *
     * @param model_person_param_list $personparams
     * @return model_item_param_list
     */
    public function estimate_item_params(model_person_param_list $personparams): model_item_param_list {
        $estimateditemparams = new model_item_param_list();
        foreach ($this->responses->get_item_response($personparams) as $itemid => $itemresponse) {
            $parameters = $this->calculate_params($itemresponse);
            // Now create a new item difficulty object (param)
            $param = $this
                ->create_item_param($itemid)
                ->set_parameters($parameters);
            // ... and append it to the list of calculated item difficulties
            $estimateditemparams->add($param);
        }
        return $estimateditemparams;
    }

    /**
     * Returns the item parameters as associative array, with the parameter name as key.
     *
     * @param mixed $itemresponse
     * @return array
     */
    abstract protected function calculate_params($itemresponse): array;

    /**
     * Likelihood.
     *
     * @param mixed $x
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    abstract public static function likelihood($x, array $itemparams, float $itemresponse);

    /**
     * Log likelihood.
     *
     * @param mixed $x
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    abstract public static function log_likelihood($x, array $itemparams, float $itemresponse);

    /**
     * Log likelihood p.
     *
     * @param mixed $x
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    abstract public static function log_likelihood_p($x, array $itemparams, float $itemresponse);

    /**
     * Log likelihood p p.
     *
     * @param mixed $x
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    abstract public static function log_likelihood_p_p($x, array $itemparams, float $itemresponse);
}
