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