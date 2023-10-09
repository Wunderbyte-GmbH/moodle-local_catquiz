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

use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_responses;

/**
 * Class demo just for demonstration purposes.
 *
 * @package catmodel_demo
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class demo extends model_model {

    public function get_information_criterion(
        string $criterion,
        model_person_param_list $personabilities,
        model_item_param $itemparams,
        model_responses $k): float {
        return 0.0;
    }

    public function estimate_item_params(
        model_person_param_list $personparams,
        ?model_item_param_list $olditemparams = null): model_item_param_list {
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

    public static function fisher_info($personability, $params) {
        return 1;
    }
}
