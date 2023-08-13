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
 * Class demo.
 *
 * @package catmodel_demo
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace catmodel_demo;

use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param_list;

/**
 * Class demo just for demonstration purposes.
 *
 * @package catmodel_demo
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class demo extends model_model {


    /**
     * Estimate item params.
     *
     * @param model_person_param_list $personparams
     *
     * @return model_item_param_list
     *
     */
    public function estimate_item_params(model_person_param_list $personparams): model_item_param_list {
        return new model_item_param_list();
    }

    /**
     * Get parameter names.
     *
     * @return string[]
     */
    public static function get_parameter_names(): array {
        return ['difficulty', ];
    }

    /**
     * Fisher info.
     *
     * @param mixed $personability
     * @param mixed $params
     *
     * @return int
     *
     */
    public static function fisher_info($personability, $params) {
        return 1;
    }
}
