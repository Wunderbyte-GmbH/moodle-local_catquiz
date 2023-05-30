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

    public function calculate_params($item_response) {
        return 0.1;
    }
}
