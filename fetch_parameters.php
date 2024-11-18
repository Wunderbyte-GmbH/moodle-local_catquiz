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
 * Simple page for fetching parameters from central instance.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');  // This includes the curl class.
require_login();
require_capability('moodle/site:config', context_system::instance());

use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param;

// Basic page setup.
$PAGE->set_url(new moodle_url('/local/catquiz/fetch_parameters.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Fetch Parameters');
$PAGE->set_heading('Fetch Parameters');

// Hardcoded values for testing.
$centralurl = 'https://192.168.56.6';
$wstoken = '2f36a8dc27525b97a93e50186035d49e';
$scalelabel = 'simulation';

$scale = $DB->get_record('local_catquiz_catscales', ['label' => $scalelabel], '*', MUST_EXIST);
if (!$scale) {
    throw new moodle_exception('scalelabelnotfound', 'local_catquiz', '', $scalelabel);
}

// Prepare the web service call.
$serverurl = rtrim($centralurl, '/') . '/webservice/rest/server.php';
$params = [
    'wstoken' => $wstoken,
    'wsfunction' => 'local_catquiz_fetch_item_parameters',
    'moodlewsrestformat' => 'json',
    'scalelabel' => $scalelabel,
];

$CFG->curlsecurityblockedhosts = '';

// Make the web service call using Moodle's curl class.
$curl = new \curl();
$curl->setopt([
    'CURLOPT_SSL_VERIFYPEER' => false,
    'CURLOPT_SSL_VERIFYHOST' => false,
]);
$response = $curl->post($serverurl, $params);
unset($CFG->curlsecurityblockedhosts);
$result = json_decode($response);

echo $OUTPUT->header();

// Create a mapping of catscale labels to IDs.
$scalemapping = [];

$catscaleids = [$scale->id, ...catscale::get_subscale_ids($scale->id)];
[$inscalesql, $inscaleparams] = $DB->get_in_or_equal($catscaleids, SQL_PARAMS_NAMED, 'scaleid');

$sql = <<<SQL
    SELECT *
    FROM {local_catquiz_catscales} lcs
    WHERE lcs.id $inscalesql
    SQL;

$scalerecords = $DB->get_records_sql($sql, $inscaleparams);
foreach ($scalerecords as $s) {
    $scalemapping[$s->label] = $s->id;
}

// Get all questions assigned to this scale or its subscales.
$sql = "SELECT DISTINCT q.id
        FROM {local_catquiz_items} lci
        JOIN {question} q ON q.id = lci.componentid
        WHERE lci.catscaleid $inscalesql";

$questions = $DB->get_records_sql($sql, $inscaleparams);
$hashmap = [];

// Generate and store hashes for all questions.
foreach ($questions as $question) {
    try {
        $hash = \local_catquiz\remote\hash\question_hasher::generate_hash($question->id);
        $hashmap[$hash] = $question->id;
    } catch (\Exception $e) {
        debugging('Error generating hash for question ' . $question->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

if (!$result) {
    echo html_writer::tag('div', 'Invalid response from server: ' . $response, ['class' => 'alert alert-danger']);
} else if (!empty($result->exception)) {
    echo html_writer::tag('div', 'Web service error: ' . $result->message, ['class' => 'alert alert-danger']);
} else if (!$result->status) {
    echo html_writer::tag('div', 'Error: ' . $result->message, ['class' => 'alert alert-danger']);
} else {
    $source = "Fetch from " . parse_url($centralurl, PHP_URL_HOST);
    $newcontext = \local_catquiz\data\dataapi::create_new_context_for_scale(
        $scale->id,
        $scale->name,
        $source,
        false
    );
    // Store the received parameters.
    $stored = 0;
    $errors = 0;

    foreach ($result->parameters as $param) {
        $questionid = $hashmap[$param->questionhash] ?? null;
        if (!$questionid) {
            debugging('No matching question found for hash: ' . $param->questionhash, DEBUG_DEVELOPER);
            $errors++;
            continue;
        }

        if (!$param->scalelabel) {
            $errors++;
            continue;
        }

        $itemrecord = new stdClass();
        $itemrecord->componentid = $questionid;
        $itemrecord->componentname = 'question';
        $itemrecord->catscaleid = $scalemapping[$param->scalelabel ?? 'unmapped'];
        $itemrecord->contextid = $newcontext->id;
        $itemrecord->status = LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE;  // Or whatever default status you want.
        $itemid = $DB->insert_record('local_catquiz_items', $itemrecord);

        // Create and save the item parameter.
        $record = (object)[
            'itemid' => $itemid,
            'componentid' => $questionid,
            'componentname' => 'question',
            'model' => $param->model,
            'contextid' => $newcontext->id,
            'difficulty' => $param->difficulty,
            'discrimination' => $param->discrimination,
            'status' => $param->status,
            'json' => $param->json,
        ];

        try {
            $itemparam = model_item_param::from_record($record);
            $itemparam->save();
            $stored++;
        } catch (Exception $e) {
            debugging('Error storing parameter: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $errors++;
        }
    }

    // Show simple success/error message.
    if ($stored > 0) {
        echo html_writer::tag(
            'div',
            "Successfully stored $stored parameters" . ($errors ? " ($errors errors)" : ''),
            ['class' => 'alert alert-success']
        );
    } else {
        echo html_writer::tag(
            'div',
            'No parameters were stored' . ($errors ? " ($errors errors)" : ''),
            ['class' => 'alert alert-warning']
        );
    }

    // Show the raw data for debugging.
    echo html_writer::tag('pre', print_r($result, true));
}

echo $OUTPUT->footer();
