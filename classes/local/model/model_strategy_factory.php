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
 * Class model_strategy_factory
 *
 * @package    local_catquiz
 * @copyright  2025 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');

use local_catquiz\catcontext;
use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;

/**
 * A factory for model_strategy
 *
 * @package    local_catquiz
 * @copyright  2025 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_strategy_factory {
    /**
     * Returns a model_strategy for the given CAT scale ID and context ID
     *
     * @param int $catscaleid
     * @param int $contextid
     *
     * @return model_strategy
     */
    public static function create_for_scale(int $catscaleid, int $contextid): model_strategy {
        $context = catcontext::load_from_db($contextid);
        $responses = model_responses::create_for_context($contextid);
        $options = $context->get_options();
        $installedmodels = model_strategy::get_installed_models();
        $olditemparams = [];
        $catscaleids = [$catscaleid, ...catscale::get_subscale_ids($catscaleid)];
        foreach (array_keys($installedmodels) as $model) {
            $olditemparams[$model] = model_item_param_list::load_from_db($contextid, $model, $catscaleids);
        }
        return new model_strategy($responses, $options, $olditemparams);
    }
}
