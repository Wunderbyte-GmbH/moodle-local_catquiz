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
 * Page for submitting responses to central instance.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\catscale;
use local_catquiz\data\dataapi;
use local_catquiz\event\calculation_executed;
use local_catquiz\local\model\model_responses;
use local_catquiz\local\model\model_strategy;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Basic security checks.
require_login();

// Setup page.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/catquiz/host/calculate.php'));
$PAGE->set_title(get_string('calculate', 'local_catquiz'));
$PAGE->set_heading(get_string('calculate', 'local_catquiz'));

// Get settings.
// $config = get_config('local_catquiz');
// if (empty($config->central_host) || empty($config->central_token)) {
//     throw new moodle_exception('nocentralconfig', 'local_catquiz');
// }

// Add a button to trigger the submission.
$submiturl = new moodle_url('/local/catquiz/host/calculate.php', ['action' => 'submit']);
$backurl = new moodle_url('/local/catquiz/index.php');

echo $OUTPUT->header();

if (optional_param('action', '', PARAM_ALPHA) === 'submit') {
    $catscaleid = 51;
    $catscale = catscale::return_catscale_object($catscaleid);
    try {
        $modelresponses = model_responses::create_from_remote_responses($catscaleid);
        $strategy = new model_strategy($modelresponses);
        [$itemdifficulties, $personabilities] = $strategy->run_estimation();
        $newcontext = dataapi::create_new_context_for_updated_parameters($catscale);
        $updatedmodels = [];
        foreach ($itemdifficulties as $modelname => $itemparamlist) {
            $itemcounter = 0;
            /** @var model_item_param_list $itemparamlist */
            $itemparamlist
                ->use_hashes()
                ->convert_hashes_to_ids()
                ->save_to_db($newcontext->id);
            $itemcounter += count($itemparamlist->itemparams);
            $model = get_string('pluginname', 'catmodel_' . $modelname);
            $updatedmodels[$model] = $itemcounter;
        }

        $catscale->contextid = $newcontext->id;
        dataapi::update_catscale($catscale);

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

        echo $OUTPUT->notification(
            'Created model_responses',
            'success'
        );
    } catch (\Throwable $th) {
        echo $OUTPUT->notification('There was an error');
    }
}

echo $OUTPUT->single_button($submiturl, 'calculate');
echo $OUTPUT->single_button($backurl, get_string('back'));

echo $OUTPUT->footer();
