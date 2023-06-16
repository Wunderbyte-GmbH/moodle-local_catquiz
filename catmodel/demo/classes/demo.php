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
    public function calculate_params($item_response): array {
        return ['difficulty' => 0.5];
    }

    /**
     * @return string[] 
     */
    public static function get_parameter_names(): array {
        return ['difficulty',];
    }
}
