<?php

namespace catmodel_demo;

use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param_list;

/**
 * Just for demonstration purposes
 */
class demo extends model_model {


    public function estimate_item_params(model_person_param_list $personparams): model_item_param_list {
        return new model_item_param_list();
    }
    /**
     * @return string[]
     */
    public static function get_parameter_names(): array {
        return ['difficulty', ];
    }

    public static function fisher_info($personability, $params) {
        return 1;
    }
}
