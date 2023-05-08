<?php

namespace catmodel_demo2;

use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;

/**
 * Just for demonstration purposes
 */
class demo2 extends model_model
{

    public function estimate_item_params(model_person_param_list $person_params): model_item_param_list {

        $estimated_item_params = new model_item_param_list();
        foreach ($this->responses->get_item_response($person_params) as $item_id => $item_response) {
            $item_difficulty = 0.1;
            $param = $this
                ->create_item_param($item_id)
                ->set_difficulty($item_difficulty);
            $estimated_item_params->add($param);
        }
        return $estimated_item_params;
    }
}
