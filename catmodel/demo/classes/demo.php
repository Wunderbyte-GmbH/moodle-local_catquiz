<?php

namespace catmodel_demo;

use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param_list;

/**
 * Just for demonstration purposes
 */
class demo extends model_model
{
    public function estimate_item_params(model_person_param_list $person_params): model_item_param_list {
        $demo_item_responses = $this->responses->get_item_response(
            $person_params
        );
        $estimated_item_params = new model_item_param_list();
        foreach($demo_item_responses as $item_id => $item_responses){
            $item_difficulty = 0.5; // Just for demonstration. Set something meaningful here.
            $param = $this
                ->create_item_param(
                    $item_id,
                    [ // Add some metadata
                        'mymetadata' => [
                            'number_responses' => count($item_responses)
                        ]
                    ]
                )
                ->set_difficulty($item_difficulty);
            $estimated_item_params->add($param);
        }

        return $estimated_item_params;
    }
}
