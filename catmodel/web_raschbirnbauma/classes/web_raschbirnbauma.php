<?php

namespace catmodel_web_raschbirnbauma;

use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param_list;

/**
 * Uses a webservice to calculate the parameters
 */
class web_raschbirnbauma extends model_model
{

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
    public function estimate_item_params(model_person_param_list $person_params): model_item_param_list {
        $estimated_item_params = new model_item_param_list();
        $item_responses = $this->responses->get_item_response($person_params);

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
        # Setup request to send json via POST.
        $payload = json_encode(["m" => $data]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        # Return response instead of printing.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        # Send request.
        $result = curl_exec($ch);
        // TODO: Should we tell if the request did not work?
        foreach (json_decode($result) as $itemdata) {
            $itemid = intval(ltrim($itemdata->_row, 'I'));
            $param = $this
                ->create_item_param($itemid)
                ->set_parameters(['difficulty' => $itemdata->xsi])
                ;
            $estimated_item_params->add($param);
        }
        curl_close($ch);
        return $estimated_item_params;
    }

    /**
     * @return string[]
     */
    public static function get_parameter_names(): array {
        return ['difficulty',];
    }

    public static function fisher_info(float $person_ability, array $params) {
        return self::likelihood_multi($person_ability,$params) * (1-self::likelihood_multi($person_ability,$params));
    }

    public static function log_likelihood_p($p, array $params): float {
        $b = $params['difficulty'];

        return exp($b)/(exp($b) + exp($p));
    }

    public static function counter_log_likelihood_p($p, array $params): float {
        $b = $params['difficulty'];
        return -(exp($p)/(exp($b) + exp($p)));
    }

    public static function log_likelihood_p_p($p, array $params): float {
        $b = $params['difficulty'];
        return -(exp($b + $p)/(exp($b) + exp($p))**2);
    }

    public static function counter_log_likelihood_p_p($p, array $params): float {
        $b = $params['difficulty'];
        return -(exp($b + $p)/(exp($b) + exp($p))**2);
    }
}
