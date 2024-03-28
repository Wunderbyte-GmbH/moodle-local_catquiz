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
use Exception;
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
abstract class model_raschmodel extends model_model implements catcalc_item_estimator, catcalc_ability_estimator {

    /**
     * @var int PRECISION
     *
     * The precision used for the item parameters.
     */
    public const PRECISION = 3;

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
     * Helper class that gets the key of an ip-array by given fraction
     *
     * @param float $frac
     * @param array $array (e.g. $ip['difficulties'])
     *
     * @return int
     *
     */
    protected static function get_key_by_fractions(float $frac, array $array): int {
        $n = 0;
        foreach ($array as $key => $val) {
            if ((float) $key < $frac) {
                $n++;
            } else {
                return $n;
            }
        }
        return $n;
    }

    /**
     * Helper class that sort array with fractions as key
     *
     * @param array $array (e.g. $ip['difficulties'])
     * @param int $precission
     *
     * @return array
     *
     */
    protected static function sort_fractions(array $array, int $precission = self::PRECISION): array {
        // Make sure the array is sanitized first.
        $array = self::sanitize_fractions($array, $precission);

        $tmpkey = [];
        $tmpval = [];

        foreach ($array as $key => $val) {
            $tmpkey[] = (float) $key;
            $tmpval[] = (float) $val;
        }

        asort($tmpkey);

        $tmp = [];
        foreach ($tmpkey as $arraykey => $frac) {
            $key = (string) sprintf("%1.". $precission . "f", (float) $frac);
            $tmp[$key] = $tmpval[$arraykey];
        }

        return $tmp;
    }

    /**
     * Helper class that sanitizes an array with fractions as key
     *
     * @param array $array (e.g. $ip['difficulties'])
     * @param int $precission
     *
     * @return array
     *
     */
    protected static function sanitize_fractions(array $array, int $precission = self::PRECISION): array {
        $tmp = [];

        foreach ($array as $key => $val) {
            $key = (string) sprintf("%1.". $precission . "f", (float) $key);
            $tmp[$key] = (float) $val;
        }

        return $tmp;
    }

    /**
     * Get all fractions out of parts of ip array
     *
     * @param array $array
     * @return array of fractions as strings
     */
    protected static function get_fractions(array $array): array {
        $a = self::sanitize_fractions($array);
        $frac = [];

        foreach ($a as $fraction => $val) {
            $frac[] = $fraction;
        }
        return $frac;
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
            $userresponse = $k->get_item_response_for_person($item->get_componentid(), $pp->get_userid());
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
     * @param model_responses $responses
     * @param model_person_param_list $personparams
     * @param model_item_param_list|null $olditemparams
     *
     * @return model_item_param_list
     *
     */
    public function estimate_item_params(
        model_responses $responses,
        model_person_param_list $personparams,
        ?model_item_param_list $olditemparams = null): model_item_param_list {
        $estimateditemparams = new model_item_param_list();
        $estimationstart = microtime(true);
        $personids = array_keys($personparams->get_person_params());
        $testcount = 0;
        foreach ($this->responses->limit_to_users($personids, true)->get_item_response() as $itemid => $itemresponse) {
            $testcount++;
            if ($testcount > 25) {
                break;
            }
            $itemstart = microtime(true);
            $oldparam = $olditemparams[$itemid] ?? null;
            if ($oldparam && $oldparam->get_status() >= LOCAL_CATQUIZ_STATUS_CALCULATED) {
                $estimateditemparams->add($oldparam);
                continue;
            }
            $parameters = $this->calculate_params($itemresponse);
            // Now create a new item difficulty object (param).
            $param = $this
                ->create_item_param($itemid)
                ->set_parameters($parameters)
                ->set_status(LOCAL_CATQUIZ_STATUS_CALCULATED);

            if ($oldparam) {
                $param->set_status($olditemparams[$itemid]->get_status());
            }
            // ... and append it to the list of calculated item difficulties
            $estimateditemparams->add($param);
            $itemduration = microtime(true) - $itemstart;
            echo "Duration for item $itemid: $itemduration" . PHP_EOL;
        }
        $totalduration = microtime(true) - $estimationstart;
        echo "Duration for whole calculation: $totalduration" . PHP_EOL;
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

     /**
      * Calculates the 1st derivative trusted regions for person ability
      *
      * @param array $pp - person parameter ('ability')
      * @param float $mean - mean of the estimated destribution
      * @param float $sd - standard deviation e.g. standard error of distribution
      * @return float 1st derivative of TR function with respect to $pp
      */
    public static function get_ability_tr_jacobian(array $pp, float $mean = 0, float $sd = 1): float {
        return
            (($mean - $pp['ability']) / ($sd ** 2)); // The d/dpp .

    }

    /**
     * Calculates the 2nd derivative trusted regions for person ability
     *
     * @param array $pp - person parameter ('ability')
     * @param float $mean - mean of the estimated destribution
     * @param float $sd - standard deviation e.g. standard error of distribution
     * @return float 2nd derivative of TR function with respect to $ip
     */
    public static function get_ability_tr_hessian(array $pp, $mean = 0, float $sd = 1): float {

        return
            (- 1 / ($sd ** 2)); // Calculate d/dpp d/dpp.

    }

    /**
     * Implements a Filter Function for trusted regions in the person ability parameter estimation
     *
     * @param array $pp - person parameter ('ability')
     * @param float $ppmin
     * @param float $ppmax
     * @param float $pptr
     * @param float $mean - mean of the estimated destribution
     * @param float $sd - standard deviation e.g. standard error of distribution
     * @param bool $usetrfactor If not set, ignore the $tr factor and restrict to the whole range [ppmin, ppmax]
     * @return array - chunked item parameter
     */
    public static function restrict_to_trusted_region_pp(
        array $pp,
        float $ppmin,
        float $ppmax,
        float $pptr = 3,
        $mean = 0,
        float $sd = 1,
        bool $usetrfactor = false
    ): array {
        if (!$usetrfactor) {
            if ($pp['ability'] < $ppmin) {
                $pp['ability'] = $ppmin;
            }
            if ($pp['ability'] > $ppmax) {
                $pp['ability'] = $ppmax;
            }
            return $pp;
        }

        if ($pp['ability'] < max($mean - ($pptr * $sd), $ppmin)) {
            $pp['ability'] = max($mean - ($pptr * $sd), $ppmin);
        }
        if ($pp['ability'] > min($pptr * $sd + $mean, $ppmax)) {
            $pp['ability'] = min($pptr * $sd + $mean, $ppmax);
        }

        return $pp;
    }

    /**
     * Definition after data callback
     *
     * @param MoodleQuickForm $mform
     * @param model_item_param $param
     * @param string $groupid
     * @return void
     * @throws coding_exception
     */
    public function definition_after_data_callback(MoodleQuickForm &$mform, model_item_param $param, string $groupid): void {
        $group = [];
        $fields = $this->get_parameter_fields($param);
        $newfields = ['fraction', 'difficulty'];
        $newfielddata = implode(
            ';',
            array_map(
                fn ($fieldname) => sprintf(
                    '%s:%s',
                    $fieldname,
                    get_string($fieldname, 'local_catquiz')
                ),
                $newfields
            )
        );
        foreach ($fields as $label => $val) {
            $this->add_element_to_group($label, $groupid, $group, $mform);
        }
        if ($this->supports_parameter_edits()) {
            $addparamsbutton = $mform->createElement(
                'submit',
                'additemparams',
                get_string('add'),
                [
                    'name' => 'additemparam',
                    'value' => $param->get_model_name(),
                    'data-action' => 'additemparams',
                    'data-model' => $param->get_model_name(),
                    'data-fields' => $newfielddata,
                ]
            );
            $group[] = $addparamsbutton;
        }
        $mform->addGroup($group, $groupid, '', '<span class="break"></span>', true, ['class' => 'param-group']);
    }

    /**
     * Add element to group
     *
     * @param string $name
     * @param string $id
     * @param array $group
     * @param mixed $mform
     * @return void
     * @throws coding_exception
     */
    protected function add_element_to_group(string $name, string $id, array &$group, &$mform) {
        if (preg_match('/(.*)_(\d+)$/', $name, $matches)) {
            $label = get_string($matches[1], 'local_catquiz') . ' ' . $matches[2];
        } else {
            $label = get_string($name, 'local_catquiz');
        }
        $value = $mform->createElement('text', $name, $label, ["class" => 'form-control param-input']);
        $value->setType($name, PARAM_FLOAT);
        $group[] = $value;
    }

    /**
     * Get parameter fields
     *
     * @param model_item_param $param
     * @return array
     */
    public function get_parameter_fields(model_item_param $param): array {
        if (!$params = $param->get_params_array()) {
            return $this->get_default_params();
        }
        return $params;
    }

    /**
     * Get default params
     * @return array
     */
    public function get_default_params(): array {
        return ['difficulty' => 0.0, 'discrimination' => 0.0];
    }

    /**
     * Convert form array to record
     *
     * @param array $formarray
     * @return stdClass
     */
    public function form_array_to_record(array $formarray): stdClass {
        return (object) $formarray;
    }

    /**
     * Allows extending the itemparam with new fields.
     *
     * This is used for multiparameter models and allows to add a new
     * [fraction:difficulty] or [intercept:difficulty] entry.
     *
     * @param array $existingparams
     * @param stdClass $newparam
     * @return array
     * @throws Exception
     */
    public function add_new_param(array $existingparams, stdClass $newparam): array {
        throw new Exception("Not implemented for this class");
    }

    /**
     * Allows removal of a parameter value.
     *
     * This is used for multiparameter models and allows removing a
     * [fraction:difficulty] or [intercept:difficulty] pair of the entry.
     * The value to be removed is identified by a 0-based index of the
     * respective multiparam array.
     *
     * @param array $existingparams
     * @param int $index
     * @throws \Exception
     * @return array
     */
    public function drop_param_at(array $existingparams, int $index): array {
        throw new Exception("Not implemented for this class");
    }
}
