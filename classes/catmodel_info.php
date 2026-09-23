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
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_model;
use local_catquiz\catcalc;
use local_catquiz\local\model\model_person_param_list;
use local_catquiz\local\model\model_strategy;
use local_catquiz\local\model\model_strategy_factory;
use local_catquiz\task\adhoc_recalculate_cat_model_params;
use moodle_exception;

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

        return model_strategy::get_params_from_db($contextid, $catscaleid);
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
     * @param bool $inplace When true, item parameters are written into the given
     *                      (existing) context and no new context is created or
     *                      activated; person parameters are left unchanged. This
     *                      is the context-preserving incremental path used by the
     *                      scheduled recalculation (see issue #44). When false, a
     *                      new context is created and item and person parameters
     *                      are written into it (the disruptive path, see issue #43).
     *
     * @return array [models => summary keyed by model display name, targetcontextid => int]
     *
     */
    public function update_params($contextid, $catscaleid, $userid = 0, bool $inplace = false) {
        global $USER, $DB;
        if (!$userid) {
            $userid = $USER->id;
        }

        $catscale = catscale::return_catscale_object($catscaleid);
        $strategy = model_strategy_factory::create_for_scale($catscaleid, $contextid);
        $initialabilities = model_person_param_list::load_from_db($contextid, [$catscaleid]);
        $strategy->get_responses()->set_person_abilities($initialabilities);
        try {
            // The incremental (in-place) path performs exactly one
            // item-parameter pass with the fixed person abilities; the disruptive
            // path iterates person and item parameters.
            if ($inplace) {
                [$itemdifficulties, $personabilities] = $strategy->run_incremental_estimation();
            } else {
                [$itemdifficulties, $personabilities] = $strategy->run_disruptive_estimation();
            }
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
            return ['models' => [], 'targetcontextid' => (int) $contextid, 'identifiability' => null];
        }
        // The incremental path keeps the existing context. It writes item
        // parameters into that context (save_to_db upserts by componentid+model)
        // and leaves person parameters untouched, so contextid before == after and
        // historical statistics/exports stay visible. The disruptive path keeps
        // its versioning behaviour (a new "updatedparams" context).
        if ($inplace) {
            $targetcontextid = $contextid;
        } else {
            $newcontext = dataapi::create_new_context_for_updated_parameters($catscale);
            $targetcontextid = $newcontext->id;
        }
        $updatedmodels = [];
        // Persist the item parameters atomically. A failure must not
        // leave a half-updated item-parameter set in the context.
        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($itemdifficulties as $modelname => $itemparamlist) {
                $itemcounter = 0;
                /** @var model_item_param_list $itemparamlist */
                $itemparamlist->save_to_db($targetcontextid);
                if (!$inplace) {
                    $personabilities->save_to_db($targetcontextid, $catscaleid);
                }
                $itemcounter += count($itemparamlist->itemparams);
                $model = get_string('pluginname', 'catmodel_' . $modelname);
                $updatedmodels[$model] = $itemcounter;
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
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

        catcontext::load_from_db($contextid)
            ->save_or_update((object)['timecalculated' => time()]);

        $identifiability = self::identifiability_summary($strategy, $itemdifficulties);

        // AIC/BIC/CAIC before (old params) and after (new params) over
        // the same person abilities, plus the iteration/convergence metadata.
        $criteriaafter = $strategy->aggregate_information_criteria($itemdifficulties, $personabilities);
        $oldparams = $strategy->get_old_item_params();
        $criteriabefore = !empty($oldparams)
            ? $strategy->aggregate_information_criteria($oldparams, $personabilities)
            : [];
        $iterations = $strategy->get_iterations();
        if ($inplace) {
            $convergencereason = 'single in-place item-parameter pass (person parameters fixed)';
        } else {
            $convergencereason = $strategy->get_convergence_reason();
            if ($strategy->used_initial_rasch()) {
                $convergencereason .= '; seeded via initial 1PL/Rasch estimation';
            }
        }

        $responses = $strategy->get_responses();
        $itemresponsemap = $responses->get_item_response();
        $numresponses = 0;
        foreach ($itemresponsemap as $peritem) {
            $numresponses += count($peritem);
        }
        $counts = [
            'numresponses' => $numresponses,
            'numpersons' => count($responses->get_person_ids()),
            'numitems' => count($responses->get_item_ids()),
        ];

        return [
            'models' => $updatedmodels,
            'targetcontextid' => (int) $targetcontextid,
            'identifiability' => $identifiability,
            'counts' => $counts,
            'criteriabefore' => $criteriabefore,
            'criteriaafter' => $criteriaafter,
            'iterations' => $iterations,
            'convergencereason' => $convergencereason,
        ];
    }

    /**
     * Aggregate per-item identifiability over the estimated items (K5 wiring).
     *
     * Runs catcalc::item_identifiability_report() for every estimated item and
     * returns counts plus a bounded list of human-readable warnings, so a
     * calibration workflow (issue #43) can surface which items are weakly
     * identified or sit at a trusted-region bound. Errors on individual items are
     * ignored so the summary never breaks the calculation.
     *
     * @param model_strategy $strategy
     * @param array $itemdifficulties modelname => model_item_param_list
     * @return array {total:int, wellidentified:int, weaklyidentified:int, atbound:int, warnings:string[]}
     */
    private static function identifiability_summary($strategy, array $itemdifficulties): array {
        $summary = ['total' => 0, 'wellidentified' => 0, 'weaklyidentified' => 0, 'atbound' => 0, 'warnings' => []];
        $maxwarnings = 20;
        try {
            $itemresponses = $strategy->get_responses()->get_item_response();
        } catch (\Throwable $e) {
            return $summary;
        }
        foreach ($itemdifficulties as $modelname => $itemparamlist) {
            $model = model_model::get_instance($modelname);
            foreach ($itemparamlist as $itemparam) {
                $componentid = $itemparam->get_componentid();
                if (!isset($itemresponses[$componentid])) {
                    continue;
                }
                $ip = $itemparam->get_params_array();
                if (!is_array($ip)) {
                    continue;
                }
                try {
                    $report = catcalc::item_identifiability_report($itemresponses[$componentid], $model, $ip);
                } catch (\Throwable $e) {
                    continue;
                }
                $summary['total']++;
                if ($report['wellidentified']) {
                    $summary['wellidentified']++;
                } else {
                    $summary['weaklyidentified']++;
                }
                if ($report['atbound']) {
                    $summary['atbound']++;
                }
                if (!empty($report['warnings']) && count($summary['warnings']) < $maxwarnings) {
                    $summary['warnings'][] = "Item {$componentid} ({$modelname}): " . implode('; ', $report['warnings']);
                }
            }
        }
        return $summary;
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
        // Use count_records_sql for the COUNT(*) query: it reads the count portably.
        // Accessing ->count on the raw record broke on MySQL/MariaDB, where the
        // unaliased COUNT(*) column is named "COUNT(*)" rather than "count", which
        // raised an "undefined property" warning (an exception under test debugging).
        $newresponses = $DB->count_records_sql($sql, $params);

        return $newresponses > 0;
    }
}
