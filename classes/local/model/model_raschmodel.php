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
    public static function likelihood_1pl($personability, $itemdifficulty ) {

        $discrimination = 1; // Hardcode override because of 1pl.
        return (1 / (1 + exp($discrimination * ($itemdifficulty - $personability))));
    }

    /**
     * Gets information criteria
     *
     * @param string $criterion
     * @param model_person_param_list $personabilities
     * @param model_item_param $itemparams
     * @param model_responses $k
     *
     * @return float
     *
     */
    public function get_information_criterion(
        string $criterion,
        model_person_param_list $personabilities,
        model_item_param $itemparams,
        model_responses $k): float {

        switch ($criterion) {
            case 'aic':
                return $this->calc_aic_item($personabilities, $itemparams, $k);

            default:
                throw new \UnexpectedValueException("Unknown information criterium" . $criterion);
        }
    }

    /**
     * Calculates the Information-Criterion Deviance (DIC) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model_person_param_list $personabilities
     * @param model_item_param $item
     * @param model_responses $k
     * @return float
     */
    public function calc_dic_item(model_person_param_list $personabilities, model_item_param $item, model_responses $k) {
        $result = 0;
        foreach ($personabilities->only_valid() as $pp) {
            $userresponse = $k->get_item_response_for_person($item->get_id(), $pp->get_id());
            if (is_null($userresponse)) {
                continue;
            }
            $result += $this->log_likelihood(
                $pp->to_array(),
                $item->get_params_array(),
                $userresponse
            );
        }
        return -2 * $result;
    }

    /**
     * Calculates the Aiken Information-Criterion (AIC, Akaike 1987) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model_person_param_list $personabilities
     * @param model_item_param $item
     * @param model_responses $k
     * @return float
     */
    public function calc_aic_item($personabilities, $item, model_responses $k) {
        $numberofparameters = $this->get_model_dim() - 1;
        return 2 * $numberofparameters + $this->calc_dic_item($personabilities, $item, $k);
    }

    /**
     * Calculates the Bayes Information-Criterion (BIC, Schwarz 1978) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model_person_param_list $personabilities
     * @param model_item_param $item
     * @param model_responses $k
     * @return float
     */
    public function calc_bic_item(model_person_param_list $personabilities, model_item_param $item, model_responses $k) {
        $numberofparameters = $this->get_model_dim() - 1;
        $numberofcases = count($personabilities->only_valid());
        return $numberofparameters * log($numberofcases) + $this->calc_dic_item($personabilities, $item, $k);
    }

    /**
     * Calculates the Consistent Akaike Information-Criterion (CAIC, Bozdogan 1987) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model_person_param_list $personabilities
     * @param model_item_param $item
     * @param model_responses $k
     * @return float
     */
    public function calc_caic_item(model_person_param_list $personabilities, model_item_param $item, model_responses $k) {
        $numberofparameters = $this->get_model_dim() - 1;
        $numberofcases = count($personabilities->only_valid());
        return $numberofparameters * (log($numberofcases + 1)) + $this->calc_dic_item($personabilities, $item, $k);
    }

    /**
     * Calculates the bias corrected Akaike Information-Criterion (AICc, Sugiura 1987) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model_person_param_list $personabilities
     * @param model_item_param $item
     * @param model_responses $k
     * @return float
     */
    public function calc_aicc_item(model_person_param_list $personabilities, model_item_param $item, model_responses $k) {
        $numberofparameters = $this->get_model_dim() - 1;
        $numberofcases = count($personabilities->only_valid());
        if ($numberofcases - $numberofparameters - 1 <= 0) {
            return 0;
        }
        return 2 * $numberofparameters + (2 * $numberofparameters * ($numberofparameters + 1))
            / ($numberofcases - $numberofparameters - 1)
            + $this->calc_dic_item($personabilities, $item, $k);
    }

    /**
     * Calculates the sample size adjusted Bayes Information-Criterion (saBIC, Sclove 1987) for a given item
     * under the condition that item-parameters have been optimized for the
     * given person abilities
     *
     * @param model_person_param_list $personabilities
     * @param model_item_param $item
     * @param model_responses $k
     * @return float
     */
    public function calc_sabic_item(model_person_param_list $personabilities, model_item_param $item, model_responses $k) {
        $numberofparameters = $this->get_model_dim() - 1;
        $numberofcases = count($personabilities->only_valid());
        if ($numberofcases - $numberofparameters - 1 <= 0) {
            return 0;
        }
        return $numberofparameters * log(($numberofcases + 2) / 24) + $this->calc_dic_item($personabilities, $item, $k);
    }

    /**
     * Estimate item params.
     *
     * @param model_person_param_list $personparams
     * @param model_item_param_list|null $olditemparams
     *
     * @return model_item_param_list
     *
     */
    public function estimate_item_params(
        model_person_param_list $personparams,
        ?model_item_param_list $olditemparams = null): model_item_param_list {
        $estimateditemparams = new model_item_param_list();
        foreach ($this->responses->get_item_response($personparams) as $itemid => $itemresponse) {
            $oldparam = $olditemparams[$itemid] ?? null;
            if ($oldparam && $oldparam->get_status() >= STATUS_CALCULATED) {
                $estimateditemparams->add($oldparam);
                continue;
            }
            $parameters = $this->calculate_params($itemresponse);
            // Now create a new item difficulty object (param).
            $param = $this
                ->create_item_param($itemid)
                ->set_parameters($parameters)
                ->set_status(STATUS_CALCULATED);

            if ($oldparam) {
                $param->set_status($olditemparams[$itemid]->get_status());
            }
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
     * @param array $pp
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    abstract public static function likelihood(array $pp, array $itemparams, float $itemresponse);

    /**
     * Log likelihood.
     *
     * @param array $pp
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    abstract public static function log_likelihood(array $pp, array $itemparams, float $itemresponse);

    /**
     * Log likelihood_p_p.
     *
     * @param array $x
     * @param array $itemparams
     * @param float $itemresponse
     *
     * @return mixed
     *
     */
    abstract public static function log_likelihood_p_p(array $x, array $itemparams, float $itemresponse);
}
