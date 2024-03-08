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
 * Class web_rasch.
 *
 * @package catmodel_web_rasch
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_web_rasch;

use catmodel_rasch\rasch;
use local_catquiz\catcalc_ability_estimator;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;

/**
 * Class web_rasch uses a webservice to calculate the parameters.
 *
 * @package catmodel_web_rasch
 * @copyright 2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class web_rasch extends model_model implements catcalc_ability_estimator {

    /**
     * Get information criterion.
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
        return 0.0;
    }

    /**
     * Uses a web API to calculate the item parameters.
     *
     * Constructs a JSON object and sends it to the R Web API
     *
     * The JSON object will have the following structure:
     * {
     *  "m": [
     *      {"I1": 0, "I2": 0},
     *      {"I1": 1, "I2": 0},
     *      {"I1": 1, "I3": 1},
     *      ...
     *      ]
     * }
     *
     * Items that have only "0" responses will not be included in the JSON.
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

        $data = [];
        foreach ($this->responses->as_array() as $components) {
            $dataobj = (object) [];
            foreach ($components as $component) {
                foreach ($component as $itemid => $itemdata) {
                    // We have to prepend the itemid with a letter so that the
                    // result returned by the API contains item names.
                    $itemname = sprintf('I%s', $itemid);
                    $oldparam = $olditemparams[$itemid] ?? null;
                    if ($oldparam && $oldparam->get_status() >= LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY) {
                        $estimateditemparams->add($oldparam);
                        continue;
                    }
                    $dataobj->$itemname = intval($itemdata['fraction']);
                }
            }
            $data[] = $dataobj;
        }
        $host = get_config('catmodel_web_rasch', 'hostname');
        $port = get_config('catmodel_web_rasch', 'port');
        $path = '/RM';
        $url = sprintf('%s:%s%s', $host, $port, $path);
        $ch = curl_init($url);
        // Setup request to send json via POST.
        $payload = json_encode(["m" => $data]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        // Return response instead of printing.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Send request.
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response);
        if (!is_array($result)) {
            // TODO: Should we tell if the request did not work?
            return $estimateditemparams;
        }
        foreach ($result as $itemdata) {
            $itemid = intval(ltrim($itemdata->_row, 'I'));
            $param = $this
                ->create_item_param($itemid)
                ->set_parameters(['difficulty' => $itemdata->xsi]);
            if ($oldparam) {
                $param->set_status($oldparam->get_status());
            }
            $estimateditemparams->add($param);
        }
        return $estimateditemparams;
    }

    /**
     * Get parameter names.
     *
     * @return array
     *
     */
    public static function get_parameter_names(): array {
        return ['difficulty'];
    }

    /**
     * Generalisierung von `likelihood` Kann in likelihood umbenannt werden.
     *
     * @param float $p
     * @param array $x
     *
     * @return int|float
     *
     */
    public static function likelihood_multi(float $p, array $x) {
        $a = 1;
        $c = 0;
        $b = $x['difficulty'];

        return $c + (1 - $c) * (exp($a * ($p - $b))) / (1 + exp($a * ($p - $b)));
    }

    /**
     * Fisher info.
     *
     * @param array $pp
     * @param array $params
     *
     * @return mixed
     *
     */
    public static function fisher_info(array $pp, array $params) {
        $personability = $pp['ability'];
        return self::likelihood_multi($personability, $params) * (1 - self::likelihood_multi($personability, $params));
    }

    /**
     * Log likelihood_p.
     *
     * @param mixed $p
     * @param array $params
     * @param float $itemresponse
     *
     * @return float
     *
     */
    public static function log_likelihood_p($p, array $params, float $itemresponse): float {
        return rasch::log_likelihood_p($p, $params, $itemresponse);
    }

    /**
     * Returns likelihood
     *
     * @param mixed $p
     * @param array $params
     * @param float $itemresponse
     *
     * @return float
     *
     */
    public static function likelihood($p, array $params, float $itemresponse) {
        return rasch::likelihood($p, $params, $itemresponse);
    }

    /**
     * Returns log likelihood.
     *
     * @param mixed $p
     * @param array $params
     * @param float $itemresponse
     *
     * @return float
     *
     */
    public static function log_likelihood($p, array $params, float $itemresponse) {
        return rasch::log_likelihood($p, $params, $itemresponse);
    }

    /**
     * Log likelihood_p_p.
     *
     * @param mixed $p
     * @param array $params
     * @param float $itemresponse
     *
     * @return float
     *
     */
    public static function log_likelihood_p_p($p, array $params, float $itemresponse): float {
        return rasch::log_likelihood_p_p($p, $params, $itemresponse);
    }
}
