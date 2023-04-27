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
        $initial_person_params = $this->responses->get_initial_person_abilities();
        for($i = 0; $i < count($initial_person_params); $i++) {
            $person = $this->responses->get_initial_person_abilities()[$i];
            $param = new model_person_param($person->get_id());
            $param->set_ability(4*$i/count($this->responses->get_initial_person_abilities()));
            $estimated_person_params->add($param);
        }

        $estimated_item_params = new model_item_param_list();
        $demo_item_responses = $this->responses->get_item_response(
            $estimated_person_params
        );
        foreach($demo_item_responses as $item_id => $item_response){
            $item_difficulty = 0.5;
            $param = new model_item_param($item_id);
            $param->set_difficulty($item_difficulty);
            $estimated_item_params->add($param);
        }

        return [$estimated_item_params, $estimated_person_params];
    }
}
