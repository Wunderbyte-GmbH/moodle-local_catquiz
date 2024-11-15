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

// Prepare the web service call.
$serverurl = rtrim($centralurl, '/') . '/webservice/rest/server.php';
$params = [
    'wstoken' => $wstoken,
    'wsfunction' => 'local_catquiz_fetch_item_parameters',
    'moodlewsrestformat' => 'json',
    'scalelabel' => $scalelabel
];

$CFG->curlsecurityblockedhosts = '';

// Make the web service call using Moodle's curl class.
$curl = new \curl();
$curl->setopt([
    'CURLOPT_SSL_VERIFYPEER' => false,
    'CURLOPT_SSL_VERIFYHOST' => false
]);
$response = $curl->post($serverurl, $params);
unset($CFG->curlsecurityblockedhosts);
$result = json_decode($response);

echo $OUTPUT->header();

if (!$result) {
    echo html_writer::tag('div', 'Invalid response from server: ' . $response, ['class' => 'alert alert-danger']);
} else if (!empty($result->exception)) {
    echo html_writer::tag('div', 'Web service error: ' . $result->message, ['class' => 'alert alert-danger']);
} else if (!$result->status) {
    echo html_writer::tag('div', 'Error: ' . $result->message, ['class' => 'alert alert-danger']);
} else {
    // Store the received parameters.
    $stored = 0;
    $errors = 0;
    
    foreach ($result->parameters as $param) {
        // Get the local question ID from hash.
        $questionid = \local_catquiz\remote\hash\question_hasher::get_questionid_from_hash($param->questionhash);
        if (!$questionid) {
            $errors++;
            continue;
        }

        // Create and save the item parameter.
        $record = (object)[
            'componentid' => $questionid,
            'componentname' => 'question',
            'model' => $param->model,
            'contextid' => $result->contextid,
            'difficulty' => $param->difficulty,
            'discrimination' => $param->discrimination,
            'status' => $param->status,
            'json' => $param->json
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
        echo html_writer::tag('div', 
            "Successfully stored $stored parameters" . ($errors ? " ($errors errors)" : ''),
            ['class' => 'alert alert-success']
        );
    } else {
        echo html_writer::tag('div', 
            'No parameters were stored' . ($errors ? " ($errors errors)" : ''),
            ['class' => 'alert alert-warning']
        );
    }

    // Show the raw data for debugging.
    echo html_writer::tag('pre', print_r($result, true));
}

echo $OUTPUT->footer();
