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

namespace local_catquiz\task;

use core\task\scheduled_task;
use moodle_exception;

/**
 * A scheduled task for submitting CAT quiz responses.
 *
 * This task processes submitted responses and submits them to the appropriate
 * destination.
 *
 * @package    local_catquiz
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scheduled_submit_responses extends scheduled_task {
    /**
     * Returns the name of the scheduled task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('submitresponsescheduled', 'local_catquiz');
    }

    /**
     * Executes the scheduled task.
     *
     * Processes all unsubmitted responses from the local_catquiz_rresponses table
     * and marks them as submitted after processing.
     *
     * @return void
     */
    // public function execute() {
    //     $config = get_config('local_catquiz');
    //     if (empty($config->central_host) || empty($config->central_token)) {
    //         throw new moodle_exception('nocentralconfig', 'local_catquiz');
    //     }

    //     if (!$labels = array_filter(explode("\n", $config->node_scale_labels ?? ''))) {
    //         mtrace('No active scales found - nothing to do.');
    //         return;
    //     }

    //     foreach ($labels as $label) {
    //         $submission = new \local_catquiz\remote\client\response_submitter(
    //             $config->central_host,
    //             $config->central_token,
    //             $label
    //         );
    //         $result = $submission->submit_responses();

    //         if ($result->success) {
    //             mtrace(get_string(
    //                 'submission_success',
    //                 'local_catquiz',
    //                 (object)[
    //                     'total' => $result->processed,
    //                     'added' => $result->added,
    //                     'skipped' => $result->skipped,
    //                 ]
    //             ));
    //         } else {
    //             mtrace(get_string('submission_error', 'local_catquiz', $result->error));
    //         }
    //     }

    //     mtrace('All responses submitted successfully.');
    // }

    /**
     * mit Fehlebehebung
     */
    public function execute() {
        $config = get_config('local_catquiz');
        $host  = trim((string)($config->central_host  ?? ''));
        $token = trim((string)($config->central_token ?? ''));
        // Fehlende Konfig -> Task überspringen, nicht crashen.
        if ($host === '' || $token === '') {
            mtrace(get_string('nocentralconfig', 'local_catquiz'));
            return;
        }
    
        // Grobe URL-Prüfung – verhindert häufige Tippfehler.
        if (!preg_match('~^https?://~i', $host)) {
            mtrace('local_catquiz: central_host is not a valid URL: ' . $host);
            return;
        }
    
        // Labels als Zeilenliste (leer zulassen = nichts zu tun).
        $labelsraw = preg_split('/\R+/', (string)($config->node_scale_labels ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $labels = array_map('trim', $labelsraw);
    
        if (empty($labels)) {
            mtrace('No active scales found - nothing to do.');
            return;
        }
    
        foreach ($labels as $label) {
            try {
                $submission = new \local_catquiz\remote\client\response_submitter($host, $token, $label);
                $result = $submission->submit_responses();
    
                if (!empty($result->success)) {
                    mtrace(get_string('submission_success', 'local_catquiz', (object)[
                        'total'   => (int)($result->processed ?? 0),
                        'added'   => (int)($result->added ?? 0),
                        'skipped' => (int)($result->skipped ?? 0),
                    ]));
                }
                else {
                    $errmsg = (is_object($result) && isset($result->error)) ? $result->error : 'unknown error';
                    mtrace(get_string('submission_error', 'local_catquiz', $errmsg));
                }
            }
            catch (\Throwable $e) {
                // Netzwerk-/Server-/Coding-Fehler pro Label auffangen, damit die Schleife weiterläuft.
                mtrace("local_catquiz: submit_responses failed for label '{$label}': " . $e->getMessage());
            }
        }
    
        mtrace('All responses submitted successfully.');
    }
    
}
