<?php

namespace catmodel_demo;

use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;

/**
 * Just for demonstration purposes
 */
class demo extends model_model
{
    public function run_estimation(): array {
        $cil = $this->responses->to_item_list();
        $cil->estimate_initial_item_difficulties();

        $estimated_person_params = new model_person_param_list();
        foreach($this->responses->get_initial_person_abilities() as $person){
            $person_responses = \local_catquiz\helpercat::get_person_response(
                $this->responses->as_array(),
                $person['id']
            );
            $person_ability = \local_catquiz\catcalc::estimate_person_ability(
                $person_responses,
                $cil->get_item_difficulties()
            );
            $param = new model_person_param($person['id']);
            $param->set_ability($person_ability);
            $estimated_person_params->add($param);
        }

        $estimated_item_params = new model_item_param_list();
        $demo_item_responses = $this->responses->get_item_response(
            $estimated_person_params
        );
        foreach($demo_item_responses as $item_id => $item_response){
            $item_difficulty = \local_catquiz\catcalc::estimate_item_difficulty($item_response);
            $param = new model_item_param($item_id);
            $param->set_difficulty($item_difficulty);
            $estimated_item_params->add($param);
        }

        return [$estimated_item_params, $estimated_person_params];
    }
}
