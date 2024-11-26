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

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Basic security checks.
require_login();

// Setup page.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/catquiz/client/submit_responses.php'));
$PAGE->set_title(get_string('submit_responses', 'local_catquiz'));
$PAGE->set_heading(get_string('submit_responses', 'local_catquiz'));

// Get settings.
$config = get_config('local_catquiz');
if (empty($config->central_host) || empty($config->central_token)) {
    throw new moodle_exception('nocentralconfig', 'local_catquiz');
}

// Add a button to trigger the submission.
$submiturl = new moodle_url('/local/catquiz/client/submit_responses.php', ['action' => 'submit']);
$backurl = new moodle_url('/local/catquiz/index.php');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('submit_responses', 'local_catquiz'));

if (optional_param('action', '', PARAM_ALPHA) === 'submit') {
    $submission = new \local_catquiz\remote\client\response_submitter(
        $config->central_host,
        $config->central_token,
        $config->sync_scale
    );
    $result = $submission->submit_responses();

    if ($result->success) {
        echo $OUTPUT->notification(
            get_string(
                'submission_success',
                'local_catquiz',
                (object)[
                    'total' => $result->processed,
                    'added' => $result->added,
                    'skipped' => $result->skipped,
                ]
            ),
            'success'
        );
    } else {
        echo $OUTPUT->notification(get_string('submission_error', 'local_catquiz', $result->error), 'error');
    }
}

echo $OUTPUT->single_button($submiturl, get_string('submit_responses', 'local_catquiz'));
echo $OUTPUT->single_button($backurl, get_string('back'));

echo $OUTPUT->footer();
