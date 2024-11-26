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
 * Class catmodel_info.
 *
 * @package local_catquiz
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use core\task\manager;
use local_catquiz\catcontext;
use local_catquiz\data\dataapi;
use local_catquiz\event\calculation_executed;
use local_catquiz\event\calculation_skipped;
use local_catquiz\local\model\model_strategy;
use local_catquiz\task\adhoc_recalculate_cat_model_params;
use local_catquiz\task\recalculate_cat_model_params;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_person_param_list;
use moodle_exception;
use moodle_url;

/**
 * Entities Class to display list of entity records.
 *
 * @package local_catquiz
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catmodel_info {

    /**
     * Returns the saved item parameters for the given context.
     *
     * The first element constains an associative array of model_item_param_lists,
     * indexed by the respective model name. The second element is a
     * model_person_param_list.
     *
     * @param int $contextid
     * @param int $catscaleid
     * @param bool $calculate Trigger a re-calculation of the item parameters
     * @return array
     */
    public function get_context_parameters(int $contextid = 0, int $catscaleid = 0, bool $calculate = false) {
        // Trigger calculation in the background but do not wait for it to finish.
        if ($calculate) {
            $this->trigger_parameter_calculation($contextid, $catscaleid);
        }

        $models = model_strategy::get_installed_models();
        $catscaleids = [$catscaleid, ...catscale::get_subscale_ids($catscaleid)];
        foreach (array_keys($models) as $modelname) {
            $estdifficulties[$modelname] = model_item_param_list::load_from_db(
                $contextid,
                $modelname,
                $catscaleids
            );
        }
        $personabilities = model_person_param_list::load_from_db($contextid, $catscaleids);
        return [$estdifficulties, $personabilities];
    }

    /**
     * Triggers parameter_calculation.
     *
     * @param mixed $contextid
     * @param mixed $catscaleid
     *
     * @return void
     *
     */
    public function trigger_parameter_calculation($contextid, $catscaleid) {
        global $USER;
        $adhocrecalculatecatmodelparams = new adhoc_recalculate_cat_model_params();
        $adhocrecalculatecatmodelparams->set_custom_data([
            'contextid' => $contextid,
            'catscaleid' => $catscaleid,
            'userid' => $USER->id,
        ]);
        manager::queue_adhoc_task($adhocrecalculatecatmodelparams);
    }

    /**
     * Update params.
     *
     * @param int $contextid
     * @param int $catscaleid
     * @param int $userid
     *
     * @return void
     *
     */
    public function update_params($contextid, $catscaleid, $userid = 0) {
        global $USER;
        if (!$userid) {
            $userid = $USER->id;
        }

        $context = catcontext::load_from_db($contextid);
        $catscale = catscale::return_catscale_object($catscaleid);
        $strategy = $context->get_strategy($catscaleid);
        $initialabilities = model_person_param_list::load_from_db($contextid, [$catscaleid]);
        $strategy->get_responses()->set_person_abilities($initialabilities);
        try {
            [$itemdifficulties, $personabilities] = $strategy->run_estimation();
        } catch (moodle_exception $e) {
            $errorcode = 'noresponsestoestimate';
            // Only handle our own exception.
            if (!($e->errorcode == $errorcode)) {
                throw $e;
            }

            // Trigger event.
            $event = calculation_skipped::create([
                'context' => \context_system::instance(),
                'userid' => $userid,
                'other' => [
                    'catscaleid' => $catscaleid,
                    'contextid' => $contextid,
                    'reason' => get_string($errorcode, 'local_catquiz'),
                ],
            ]);
            $event->trigger();
            return;
        }
        $newcontext = dataapi::create_new_context_for_updated_parameters($catscale);
        $updatedmodels = [];
        foreach ($itemdifficulties as $modelname => $itemparamlist) {
            $itemcounter = 0;
            /** @var model_item_param_list $itemparamlist */
            $itemparamlist->save_to_db($newcontext->id);
            $personabilities->save_to_db($newcontext->id, $catscaleid);
            $itemcounter += count($itemparamlist->itemparams);
            $model = get_string('pluginname', 'catmodel_' . $modelname);
            $updatedmodels[$model] = $itemcounter;
        }

        $updatedmodelsjson = json_encode($updatedmodels);
        // Trigger event.
        $event = calculation_executed::create([
            'context' => \context_system::instance(),
            'userid' => $userid,
            'other' => [
                'catscaleid' => $catscaleid,
                'contextid' => $contextid,
                'userid' => $userid,
                'updatedmodelsjson' => $updatedmodelsjson,
            ],
        ]);
        $event->trigger();

        $context->save_or_update((object)['timecalculated' => time()]);
    }

    /**
     * Checks if there are new responses to the questions associated with a CAT
     * context and a CAT scale.
     *
     * @param catcontext $context
     * @param int $catscaleid
     * @return bool
     */
    public function needs_update(catcontext $context, int $catscaleid): bool {
        global $DB;
        $subscales = catscale::get_subscale_ids($catscaleid);
        [$sql, $params] = catquiz::get_sql_for_new_responses(
            $context->id,
            [$catscaleid, ...$subscales],
            $context->gettimecalculated()
        );
        $newresponses = intval(($DB->get_record_sql($sql, $params))->count);

        return $newresponses > 0;
    }
}
