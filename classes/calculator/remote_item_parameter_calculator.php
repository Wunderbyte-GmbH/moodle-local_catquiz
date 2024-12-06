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

namespace local_catquiz\calculator;

use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\data\dataapi;
use local_catquiz\event\calculation_executed;
use local_catquiz\event\calculation_skipped;
use local_catquiz\local\model\model_responses;
use local_catquiz\local\model\model_strategy;

/**
 * Handles calculation of remote item parameters.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remote_item_parameter_calculator {
    /**
     * Calculate parameters for a specific scale.
     * @param int $scaleid The ID of the scale to process
     * @param int|null $userid Optional user ID for events
     * @return bool True if calculation was performed, false if skipped
     */
    public function calculate_for_scale(int $scaleid, ?int $userid = null) {
        $repo = new catquiz();
        $scale = catscale::return_catscale_object($scaleid);
        $catscaleids = [$scale->id, ...catscale::get_subscale_ids($scale->id)];

        try {
            $newresponses = $repo->count_unprocessed_remote_responses($catscaleids, $scale->contextid);
            if ($newresponses == 0) {
                mtrace("No new remote responses for scale {$scale->id} - skipping.");
                $event = calculation_skipped::create([
                    'context' => \context_system::instance(),
                    'userid' => $userid,
                    'other' => [
                        'catscaleid' => $scale->id,
                        'contextid' => $scale->contextid,
                        'reason' => get_string('nonewresponses', 'local_catquiz'),
                    ],
                ]);
                $event->trigger();
                return false;
            }

            mtrace("Found $newresponses new responses - starting recalculation...");
            $responses = (new catquiz())->get_remote_responses($scale->id, $scale->contextid);
            $modelresponses = model_responses::create_from_remote_responses($responses, $scale->id);
            if (empty($modelresponses->get_item_ids())) {
                mtrace("No responses could be created for scale {$scale->id} - skipping.");
                return false;
            }

            $strategy = new model_strategy($modelresponses);
            [$itemdifficulties, $personabilities] = $strategy->run_estimation();
            $newcontext = dataapi::create_new_context_for_updated_parameters($scale);

            $updatedmodels = [];
            foreach ($itemdifficulties as $modelname => $itemparamlist) {
                $itemparamlist
                    ->use_hashes()
                    ->convert_hashes_to_ids()
                    ->save_to_db($newcontext->id);
                $updatedmodels[$modelname] = count($itemparamlist->itemparams);
                mtrace("Saved {$updatedmodels[$modelname]} item parameters for model $modelname.");
            }

            $scale->contextid = $newcontext->id;
            dataapi::update_catscale($scale);
            $repo->mark_remote_responses_processed($catscaleids, $scale->contextid);

            if ($userid) {
                $event = calculation_executed::create([
                    'context' => \context_system::instance(),
                    'userid' => $userid,
                    'other' => [
                        'catscaleid' => $scale->id,
                        'contextid' => $scale->contextid,
                        'updatedmodelsjson' => json_encode($updatedmodels),
                    ],
                ]);
                $event->trigger();
            }

            return true;
        } catch (\Exception $e) {
            mtrace("Error processing scale {$scale->id}: " . $e->getMessage());
            return false;
        }
    }
}
