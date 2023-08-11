<?php

namespace catmodel_web_raschbirnbauma;

use local_catquiz\catcalc_ability_estimator;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param_list;

/**
 * Uses a webservice to calculate the parameters
 */
class web_raschbirnbauma extends model_model implements catcalc_ability_estimator {


    /**
     * Uses a web API to calculate the item parameters
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
     * @param model_person_param_list $person_params
     * @return model_item_param_list
     */
    public function estimate_item_params(model_person_param_list $personparams): model_item_param_list {
        $estimateditemparams = new model_item_param_list();

        $data = [];
        foreach ($this->responses->as_array() as $components) {
            $dataobj = (object) [];
            foreach ($components as $component) {
                foreach ($component as $itemid => $itemdata) {
                    // We have to prepend the itemid with a letter so that the
                    // result returned by the API contains item names
                    $itemname = sprintf('I%s', $itemid);
                    $dataobj->$itemname = intval($itemdata['fraction']);
                }
            }
            $data[] = $dataobj;
        }
        $host = get_config('catmodel_web_raschbirnbauma', 'hostname');
        $port = get_config('catmodel_web_raschbirnbauma', 'port');
        $path = '/RM';
        $url = sprintf('%s:%s%s', $host, $port, $path);
        $ch = curl_init($url);
        // Setup request to send json via POST.
        $payload = json_encode(["m" => $data]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
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
            $estimateditemparams->add($param);
        }
        return $estimateditemparams;
    }

    /**
     * @return string[]
     */
    public static function get_parameter_names(): array {
        return ['difficulty', ];
    }

    /**
     * Generalisierung von `likelihood`
     * Kann in likelihood umbenannt werden
     * @param float $p
     * @param array<float> $x
     * @return int|float
     */
    public static function likelihood_multi(float $p, array $x) {
        $a = 1;
        $c = 0;
        $b = $x['difficulty'];

        return $c + (1 - $c) * (exp($a * ($p - $b))) / (1 + exp($a * ($p - $b)));
    }

    public static function fisher_info(float $personability, array $params) {
        return self::likelihood_multi($personability, $params) * (1 - self::likelihood_multi($personability, $params));
    }

    public static function log_likelihood_p($p, array $params, float $itemresponse): float {
        if ($itemresponse < 1.0) {
            return self::counter_log_likelihood_p($p, $params);
        }

        $b = $params['difficulty'];

        return exp($b) / (exp($b) + exp($p));
    }

    public static function counter_log_likelihood_p($p, array $params): float {
        $b = $params['difficulty'];
        return -(exp($p) / (exp($b) + exp($p)));
    }

    public static function likelihood($p, array $params, float $itemresponse) {
        $b = $params['difficulty'];

        $a = 1;
        $c = 0;

        $value = $c + (1 - $c) * (exp($a * ($p - $b))) / (1 + exp($a * ($p - $b)));

        if ($itemresponse < 1.0) {
            return 1 - $value;
        }
        return $value;
    }

    public static function log_likelihood($p, array $params, float $itemresponse) {
        if ($itemresponse < 1.0) {
            return self::log_counter_likelihood($p, $params);
        }

        $b = $params['difficulty'];

        $a = 1;
        $c = 0;
        return log($c + ((1 - $c) * exp($a * (-$b + $p))) / (1 + exp($a * (-$b + $p))));

    }
    public static function log_counter_likelihood($p, array $params) {
        $b = $params['difficulty'];

        $a = 1;
        $c = 0;
        return log(1 - $c - ((1 - $c) * exp($a * (-$b + $p))) / (1 + exp($a * (-$b + $p))));
    }

    public static function log_likelihood_p_p($p, array $params, float $itemresponse): float {
        $b = $params['difficulty'];
        $value = -(exp($b + $p) / (exp($b) + exp($p)) ** 2);
        if ($itemresponse < 1.0) {
            return 1 - $value;
        }
        return $value;
    }
}
