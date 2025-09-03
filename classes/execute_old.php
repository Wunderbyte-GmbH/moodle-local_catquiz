/**
  * Executes the scheduled task.
  *
  * Processes all unsubmitted responses from the local_catquiz_rresponses table
  * and marks them as submitted after processing.
  *
  * @return void
  */
public function execute() {
    $config = get_config('local_catquiz');
    if (empty($config->central_host) || empty($config->central_token)) {
        throw new moodle_exception('nocentralconfig', 'local_catquiz');
    }

    if (!$labels = array_filter(explode("\n", $config->node_scale_labels ?? ''))) {
        mtrace('No active scales found - nothing to do.');
        return;
    }

    foreach ($labels as $label) {
        $submission = new \local_catquiz\remote\client\response_submitter(
            $config->central_host,
            $config->central_token,
            $label
        );
        $result = $submission->submit_responses();

        if ($result->success) {
            mtrace(get_string(
                'submission_success',
                'local_catquiz',
                (object)[
                    'total' => $result->processed,
                    'added' => $result->added,
                    'skipped' => $result->skipped,
                ]
            ));
        } else {
            mtrace(get_string('submission_error', 'local_catquiz', $result->error));
        }
    }

    mtrace('All responses submitted successfully.');
}