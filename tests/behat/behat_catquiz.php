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
 * Defines message providers (types of messages being sent)
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * To create catquiz specific behat scearios.
 * @package local_catquiz
 */
class behat_catquiz extends behat_base {
    /**
     * Opens the CAT attempt feedback review page for a user's most recent attempt.
     *
     * Issue #18: reviewing another user's attempt is the situation in which the
     * permission context matters. The attempt id is generated at runtime, so it
     * cannot be written into a feature file; this step looks it up by username.
     *
     * @Given /^I visit the CAT attempt feedback page for the last attempt of "([^"]*)"$/
     *
     * @param string $username
     * @return void
     */
    public function i_visit_the_cat_attempt_feedback_page_for_the_last_attempt_of(string $username): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $attempt = $DB->get_records(
            'local_catquiz_attempts',
            ['userid' => $userid],
            'id DESC',
            'attemptid, contextid',
            0,
            1
        );

        if (empty($attempt)) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'No CAT attempt found for user ' . $username,
                $this->getSession()
            );
        }

        $attempt = reset($attempt);
        $url = new moodle_url('/local/catquiz/show_attemptfeedback.php', [
            'attemptid' => $attempt->attemptid,
            'contextid' => $attempt->contextid,
        ]);

        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /**
     * Opens the result page of a user's last attempt, as the end of the attempt shows it (issue #121).
     *
     * @Given /^I open the result page of the last attempt of "([^"]*)"$/
     *
     * @param string $username
     * @return void
     */
    public function i_open_the_result_page_of_the_last_attempt_of(string $username): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $attempts = $DB->get_records('adaptivequiz_attempt', ['userid' => $userid], 'id DESC', 'id, instance, uniqueid', 0, 1);
        if (!$attempts) {
            throw new \Behat\Mink\Exception\ExpectationException('No attempt of ' . $username, $this->getSession());
        }
        $attempt = reset($attempts);
        $cm = get_coursemodule_from_instance('adaptivequiz', $attempt->instance, 0, false, MUST_EXIST);
        $url = new moodle_url('/mod/adaptivequiz/attemptfinished.php', [
            'cmid' => $cm->id, 'id' => $attempt->instance, 'uattid' => $attempt->uniqueid,
        ]);
        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /**
     * Opens the result page of a user's last attempt in one activity (mod_adaptivequiz #22).
     *
     * @Given /^I open the result page of the last attempt of "([^"]*)" in "([^"]*)"$/
     *
     * @param string $username
     * @param string $idnumber The activity's ID number.
     * @return void
     */
    public function i_open_the_result_page_of_the_last_attempt_of_in(string $username, string $idnumber): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $attempts = $DB->get_records(
            'adaptivequiz_attempt',
            ['userid' => $userid, 'instance' => $cm->instance],
            'id DESC',
            'id, instance, uniqueid',
            0,
            1
        );
        if (!$attempts) {
            throw new \Behat\Mink\Exception\ExpectationException("No attempt of $username in $idnumber", $this->getSession());
        }
        $attempt = reset($attempts);
        $url = new moodle_url('/mod/adaptivequiz/attemptfinished.php', [
            'cmid' => $cm->id, 'id' => $attempt->instance, 'uattid' => $attempt->uniqueid,
        ]);
        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /**
     * Makes an activity unavailable to participants: a date restriction that ended an hour ago (issue #121).
     *
     * @Given /^the activity "([^"]*)" is no longer available to participants$/
     *
     * @param string $idnumber
     * @return void
     */
    public function the_activity_is_no_longer_available_to_participants(string $idnumber): void {
        global $DB;

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $availability = \core_availability\tree::get_root_json([
            \availability_date\condition::get_json(\availability_date\condition::DIRECTION_UNTIL, time() - HOURSECS),
        ]);
        $DB->set_field('course_modules', 'availability', json_encode($availability), ['id' => $cm->id]);
        rebuild_course_cache($cm->course, true);
    }

    /**
     * Offers the CAT settings of an activity as a template of the given name (issue #123).
     *
     * A template is a test environment without an activity (componentid 0) that is active.
     *
     * @Given /^the CAT settings of "([^"]*)" are offered as the template "([^"]*)"$/
     *
     * @param string $idnumber ID number of the activity.
     * @param string $name Name of the template.
     * @return void
     */
    public function the_cat_settings_are_offered_as_the_template(string $idnumber, string $name): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $test = $DB->get_record(
            'local_catquiz_tests',
            ['component' => 'mod_adaptivequiz', 'componentid' => $cm->instance],
            '*',
            MUST_EXIST
        );
        $json = json_decode($test->json);
        $json->componentid = 0;
        $json->name = $name;
        // A template is saved by the form, and the form sends the limits of every feedback range. The generator
        // of test settings writes the number of ranges but limits only for the scale ids of its fixture: without
        // limits of its own, the form filled the ranges of the activity in and only the added ones from the
        // template - a gap the form refuses. The limits the form computes, where none are stored.
        $options = (int) ($json->numberoffeedbackoptionsselect ?? 0);
        $rootid = (int) ($json->catquiz_catscales ?? 0);
        if ($options > 0 && $rootid > 0) {
            $range = (new \local_catquiz\catscale($rootid))->get_ability_range($rootid);
            foreach (get_object_vars($json) as $key => $value) {
                if (!preg_match('/^catquiz_subscalecheckbox_(\d+)$/', $key, $matches) || empty($value)) {
                    continue;
                }
                for ($i = 1; $i <= $options; $i++) {
                    foreach (['lower' => true, 'upper' => false] as $side => $lower) {
                        $limitkey = "feedback_scaleid_limit_{$side}_{$matches[1]}_{$i}";
                        if (!isset($json->$limitkey)) {
                            $json->$limitkey = \local_catquiz\feedback\feedbackclass::return_limits_for_scale(
                                $options,
                                $i,
                                $lower,
                                (float) $range['minscalevalue'],
                                (float) $range['maxscalevalue']
                            );
                        }
                    }
                }
            }
        }
        unset($test->id);
        $test->componentid = 0;
        $test->name = $name;
        $test->status = LOCAL_CATQUIZ_STATUS_TEST_ACTIVE;
        $test->json = json_encode($json);
        $test->timecreated = time();
        $test->timemodified = time();
        $DB->insert_record('local_catquiz_tests', $test);
        \cache_helper::purge_all();
    }

    /**
     * Hides an activity from participants after the fact (issue #139).
     *
     * @Given /^the activity "([^"]*)" is hidden from participants$/
     *
     * @param string $idnumber
     * @return void
     */
    public function the_activity_is_hidden_from_participants(string $idnumber): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        set_coursemodule_visible($cm->id, 0);
        rebuild_course_cache($cm->course, true);
    }

    /**
     * Deletes the question usage of a user's last attempt, as a restore or a clean-up can (issue #139).
     *
     * A page that was opened before still offers the questions; the modal must then report the
     * missing question instead of loading for ever.
     *
     * @Given /^the questions of the last attempt of "([^"]*)" no longer exist$/
     *
     * @param string $username
     * @return void
     */
    public function the_questions_of_the_last_attempt_no_longer_exist(string $username): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $attempts = $DB->get_records('adaptivequiz_attempt', ['userid' => $userid], 'id DESC', 'id, uniqueid', 0, 1);
        if (!$attempts) {
            throw new \Behat\Mink\Exception\ExpectationException('No attempt of ' . $username, $this->getSession());
        }
        \question_engine::delete_questions_usage_by_activity((int) reset($attempts)->uniqueid);
    }

    /**
     * Stores the quiz history of the last CAT attempt of a person in the older format (issue #152).
     *
     * Before personability_after existed, a row carried the ability as personability, and no pilot flag.
     *
     * @Given /^the quiz history of the last CAT attempt of "([^"]*)" is stored in the older format$/
     *
     * @param string $username
     * @return void
     */
    public function the_quiz_history_is_stored_in_the_older_format(string $username): void {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $records = $DB->get_records('local_catquiz_attempts', ['userid' => $userid], 'id DESC', 'id, json', 0, 1);
        $record = reset($records);
        $data = $record ? json_decode((string) $record->json, true) : null;
        if (empty($data['graphicalsummary_data'])) {
            throw new \Behat\Mink\Exception\ExpectationException('No quiz history of ' . $username, $this->getSession());
        }
        foreach ($data['graphicalsummary_data'] as &$row) {
            $row['personability'] = $row['personability_after'] ?? null;
            unset($row['personability_after'], $row['pilot']);
        }
        unset($row);
        $DB->set_field('local_catquiz_attempts', 'json', json_encode($data), ['id' => $record->id]);
        \cache_helper::purge_all();
    }

    /**
     * Gives the parameters of the questions a model that is not installed (issue #153).
     *
     * As after uninstalling the subplugin of their model, or importing parameters of a model this site does not have.
     *
     * @Given /^the parameters of the questions "([^"]*)" belong to a model that is not installed$/
     *
     * @param string $idnumbers Question id numbers, comma-separated.
     * @return void
     */
    public function the_parameters_belong_to_a_model_not_installed(string $idnumbers): void {
        global $DB;
        foreach (array_map('trim', explode(',', $idnumbers)) as $idnumber) {
            $questionid = $DB->get_field_sql(
                "SELECT qv.questionid
                   FROM {question_bank_entries} qbe
                   JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  WHERE qbe.idnumber = :idnumber",
                ['idnumber' => $idnumber],
                MUST_EXIST
            );
            $DB->set_field('local_catquiz_itemparams', 'model', 'notinstalled', ['componentid' => $questionid]);
        }
        \cache_helper::purge_all();
    }

    /**
     * Connects STACK to Maxima with the test configuration of config.php (issue #139).
     *
     * The scenario is skipped where qtype_stack is not installed or no Maxima is configured - the
     * same rule as STACK's own step "I set up STACK using the PHPUnit configuration", which cannot be
     * used here because it does not exist without STACK.
     *
     * @Given /^STACK is connected to Maxima for this test$/
     *
     * @return void
     */
    public function stack_is_connected_to_maxima(): void {
        $dir = \core_component::get_plugin_directory('qtype', 'stack');
        if (!$dir || !file_exists($dir . '/version.php')) {
            throw new \Moodle\BehatExtension\Exception\SkippedException('qtype_stack is not installed.');
        }
        require_once($dir . '/tests/fixtures/test_maxima_configuration.php');
        if (!\qtype_stack_test_config::is_test_config_available()) {
            throw new \Moodle\BehatExtension\Exception\SkippedException(
                'No Maxima configuration for tests (QTYPE_STACK_TEST_CONFIG_* in config.php).'
            );
        }
        \qtype_stack_test_config::setup_test_maxima_connection();
    }

    /**
     * Checks that an image of the question in the open dialogue was delivered (issue #139).
     *
     * An image the browser could not load - refused or missing file - has no natural width.
     *
     * @Then /^the image "([^"]*)" in the question dialogue has loaded$/
     *
     * @param string $alt Alternative text of the image.
     * @return void
     */
    public function the_image_in_the_question_dialogue_has_loaded(string $alt): void {
        $alt = json_encode($alt);
        $script = "return (function() {"
            . " var img = Array.from(document.querySelectorAll('.modal.show img'))"
            . "   .find(function(i) { return i.getAttribute('alt') === $alt; });"
            . " if (!img) { return 'missing'; }"
            . " if (!img.complete) { return 'loading'; }"
            . " return img.naturalWidth > 0 ? 'loaded' : 'broken ' + img.src;"
            . "})();";
        $this->spin(function () use ($script) {
            $state = $this->getSession()->evaluateScript($script);
            if ($state !== 'loaded') {
                throw new \Behat\Mink\Exception\ExpectationException('Image state: ' . $state, $this->getSession());
            }
            return true;
        }, [], behat_base::get_extended_timeout());
        // The network side of the same image (mod_adaptivequiz #16, #18): the URL the modal really shows, requested
        // again with the session of the browser - HTTP status, type and an image in the answer.
        $check = "return (function() {"
            . " var img = Array.from(document.querySelectorAll('.modal.show img'))"
            . "   .find(function(i) { return i.getAttribute('alt') === $alt; });"
            . " var x = new XMLHttpRequest(); x.open('GET', img.currentSrc || img.src, false);"
            . " x.overrideMimeType('text/plain; charset=x-user-defined'); x.send();"
            . " var b = x.responseText || '';"
            . " var png = b.length > 8 && (b.charCodeAt(1) & 0xff) === 0x50 && (b.charCodeAt(2) & 0xff) === 0x4e;"
            . " return JSON.stringify({src: img.currentSrc || img.src, status: x.status,"
            . "   type: x.getResponseHeader('Content-Type'), bytes: b.length, png: png,"
            . "   width: img.naturalWidth, height: img.naturalHeight});"
            . "})();";
        $r = json_decode((string) $this->getSession()->evaluateScript($check), true);
        $report = sprintf(
            'modal image %s: %s HTTP %d, %s, %d bytes, PNG: %s, decoded %dx%d',
            $alt,
            $r['src'],
            $r['status'],
            $r['type'],
            $r['bytes'],
            $r['png'] ? 'yes' : 'no',
            $r['width'],
            $r['height']
        );
        $this->record_network_evidence($report);
        $served = $r['status'] === 200 && strpos((string) $r['type'], 'image/') === 0;
        if (!$served || strpos($r['src'], '/pluginfile.php/') === false) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'Modal image not served as a file: ' . $report,
                $this->getSession()
            );
        }
    }

    /**
     * Restricts an activity to the members of a group (issue #132).
     *
     * @Given /^the activity "([^"]*)" is restricted to the group "([^"]*)"$/
     *
     * @param string $idnumber
     * @param string $groupname
     * @return void
     */
    public function the_activity_is_restricted_to_the_group(string $idnumber, string $groupname): void {
        global $DB;

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $groupid = $DB->get_field('groups', 'id', ['courseid' => $cm->course, 'name' => $groupname], MUST_EXIST);
        $availability = \core_availability\tree::get_root_json([\availability_group\condition::get_json((int) $groupid)]);
        $DB->set_field('course_modules', 'availability', json_encode($availability), ['id' => $cm->id]);
        rebuild_course_cache($cm->course, true);
    }

    /**
     * Stores finished CAT results of a test, as the finalisation stores them (issues #119, #132).
     *
     * One row per result: user, how many days ago it was finished, the ability on the test's scale and
     * its subscales, and whether it is valid. Builds a history without taking the test day after day.
     *
     * @Given /^the following CAT results of "([^"]*)" exist:$/
     *
     * @param string $idnumber
     * @param \Behat\Gherkin\Node\TableNode $table Columns: user, days ago or end (UTC, Y-m-d H:i - issue #151), ability,
     *     valid.
     * @return void
     */
    public function the_following_cat_results_exist(string $idnumber, \Behat\Gherkin\Node\TableNode $table): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        static $hostattemptid = 900000;

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $test = $DB->get_record(
            'local_catquiz_tests',
            ['componentid' => $cm->instance, 'component' => 'mod_adaptivequiz'],
            '*',
            MUST_EXIST
        );
        $rootid = (int) $test->catscaleid;
        $scales = [$rootid, ...\local_catquiz\catscale::get_subscale_ids($rootid)];
        $contextid = (int) \local_catquiz\catscale::get_context_id($rootid);
        foreach ($table->getHash() as $row) {
            $userid = (int) $DB->get_field('user', 'id', ['username' => $row['user']], MUST_EXIST);
            $end = isset($row['end'])
                ? (new \DateTimeImmutable($row['end'], new \DateTimeZone('UTC')))->getTimestamp()
                : time() - (int) $row['days ago'] * DAYSECS;
            $ability = (float) $row['ability'];
            $id = $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => $userid, 'scaleid' => $rootid, 'contextid' => $contextid, 'courseid' => $cm->course,
                'attemptid' => ++$hostattemptid, 'component' => 'mod_adaptivequiz', 'instanceid' => $cm->instance,
                'teststrategy' => LOCAL_CATQUIZ_STRATEGY_CLASSIC, 'status' => 0, 'starttime' => $end - 600,
                'endtime' => $end, 'personability_after_attempt' => $ability,
                'json' => json_encode(['personabilities' => array_fill_keys($scales, $ability)]),
                'timecreated' => $end, 'timemodified' => $end,
            ]);
            foreach ($scales as $scaleid) {
                $DB->insert_record('local_catquiz_personparams', (object) [
                    'userid' => $userid, 'catscaleid' => $scaleid, 'contextid' => $contextid, 'attemptid' => $id,
                    'ability' => $ability, 'isvalid' => $row['valid'] === 'yes' ? 1 : 0, 'resultsource' => 'current',
                    'isprimary' => $scaleid === $rootid ? 1 : 0, 'status' => 0, 'timecreated' => $end, 'timemodified' => $end,
                ]);
            }
        }
    }

    /**
     * Limits the time of an attempt in the stored CAT settings of a test (issue #141, stop reasons).
     *
     * @Given /^the CAT settings of "([^"]*)" limit an attempt to (\d+) minutes$/
     *
     * @param string $idnumber
     * @param int $minutes
     * @return void
     */
    public function the_cat_settings_limit_an_attempt(string $idnumber, int $minutes): void {
        global $DB;

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $test = $DB->get_record(
            'local_catquiz_tests',
            ['componentid' => $cm->instance, 'component' => 'mod_adaptivequiz'],
            '*',
            MUST_EXIST
        );
        $json = json_decode($test->json);
        $json->catquiz_includetimelimit = 1;
        $json->catquiz_timelimitgroup = (object) [
            'catquiz_maxtimeperattempt' => $minutes,
            'catquiz_timeselect_attempt' => 'min',
            'catquiz_maxtimeperitem' => 0,
            'catquiz_timeselect_item' => 'min',
        ];
        $DB->set_field('local_catquiz_tests', 'json', json_encode($json), ['id' => $test->id]);
        \cache_helper::purge_all();
    }

    /**
     * Limits the time for one question in the stored CAT settings of a test (mod_adaptivequiz #5).
     *
     * @Given /^the CAT settings of "([^"]*)" limit a question to (\d+) minutes$/
     *
     * @param string $idnumber
     * @param int $minutes
     * @return void
     */
    public function the_cat_settings_limit_a_question(string $idnumber, int $minutes): void {
        global $DB;

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $test = $DB->get_record(
            'local_catquiz_tests',
            ['componentid' => $cm->instance, 'component' => 'mod_adaptivequiz'],
            '*',
            MUST_EXIST
        );
        $json = json_decode($test->json);
        $json->catquiz_includetimelimit = 1;
        $json->catquiz_timelimitgroup = (object) [
            'catquiz_maxtimeperattempt' => 0,
            'catquiz_timeselect_attempt' => 'min',
            'catquiz_maxtimeperitem' => $minutes,
            'catquiz_timeselect_item' => 'min',
        ];
        $DB->set_field('local_catquiz_tests', 'json', json_encode($json), ['id' => $test->id]);
        \cache_helper::purge_all();
    }

    /**
     * Moves the start of a user's open attempt of the activity into the past (mod_adaptivequiz #5).
     *
     * The activity judges the time of the attempt by its own start; the CAT progress is moved with it.
     *
     * @Given /^the open attempt of "([^"]*)" started (\d+) minutes ago$/
     *
     * @param string $username
     * @param int $minutes
     * @return void
     */
    public function the_open_attempt_started_minutes_ago(string $username, int $minutes): void {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $open = ['userid' => $userid, 'attemptstate' => 'inprogress'];
        $attempts = $DB->get_records('adaptivequiz_attempt', $open, 'id DESC', '*', 0, 1);
        if (!$attempts) {
            throw new \Behat\Mink\Exception\ExpectationException('No open attempt of ' . $username, $this->getSession());
        }
        $attempt = reset($attempts);
        $started = (int) $attempt->timecreated - $minutes * MINSECS;
        $DB->set_field('adaptivequiz_attempt', 'timecreated', $started, ['id' => $attempt->id]);
        if ($DB->record_exists('local_catquiz_progress', ['userid' => $userid])) {
            $this->the_running_cat_attempt_started_minutes_ago($username, $minutes);
        }
    }

    /**
     * Moves the moment the question on a user's screen was shown into the past (mod_adaptivequiz #5).
     *
     * @Given /^the question on the screen of "([^"]*)" was shown (\d+) minutes ago$/
     *
     * @param string $username
     * @param int $minutes
     * @return void
     */
    public function the_question_on_the_screen_was_shown_minutes_ago(string $username, int $minutes): void {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $open = ['userid' => $userid, 'attemptstate' => 'inprogress'];
        $attempts = $DB->get_records('adaptivequiz_attempt', $open, 'id DESC', '*', 0, 1);
        $attempt = reset($attempts);
        if (!$attempt || empty($attempt->uniqueid)) {
            throw new \Behat\Mink\Exception\ExpectationException('No question on the screen of ' . $username, $this->getSession());
        }
        $slot = (int) $DB->get_field_sql(
            'SELECT MAX(slot) FROM {question_attempts} WHERE questionusageid = ?',
            [$attempt->uniqueid]
        );
        $DB->execute(
            "UPDATE {question_attempt_steps}
                SET timecreated = timecreated - :shift
              WHERE questionattemptid IN (SELECT id FROM {question_attempts} WHERE questionusageid = :usage AND slot = :slot)",
            ['shift' => $minutes * MINSECS, 'usage' => $attempt->uniqueid, 'slot' => $slot]
        );
        \cache_helper::purge_by_event('changesinadaptivequizattempt');
    }

    /**
     * Moves the start of a user's running CAT attempt into the past (issue #141, stop reasons).
     *
     * Saves waiting for a time limit in real time. The progress is read again from the database on the
     * next request.
     *
     * @Given /^the running CAT attempt of "([^"]*)" started (\d+) minutes ago$/
     *
     * @param string $username
     * @param int $minutes
     * @return void
     */
    public function the_running_cat_attempt_started_minutes_ago(string $username, int $minutes): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $rows = $DB->get_records('local_catquiz_progress', ['userid' => $userid], 'id DESC', '*', 0, 1);
        if (!$rows) {
            throw new \Behat\Mink\Exception\ExpectationException('No running CAT attempt of ' . $username, $this->getSession());
        }
        $row = reset($rows);
        $json = json_decode($row->json);
        $json->starttime = (int) ($json->starttime ?? time()) - $minutes * MINSECS;
        $DB->set_field('local_catquiz_progress', 'json', json_encode($json), ['id' => $row->id]);
        // The progress lives in the session cache as well; the invalidation event empties it.
        \cache_helper::purge_by_event('changesinadaptivequizattempt');
    }

    /**
     * Deactivates the CAT items of every question a user's last attempt has not shown yet (issues #141, #60).
     *
     * The pool runs out during the attempt - what settings checked before the start cannot foresee. The
     * runtime pool is invalidated by the event a change of the items fires.
     *
     * @Given /^the CAT items of the questions "([^"]*)" has not seen yet are deactivated$/
     *
     * @param string $username
     * @return void
     */
    public function the_cat_items_not_seen_yet_are_deactivated(string $username): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $attempts = $DB->get_records('adaptivequiz_attempt', ['userid' => $userid], 'id DESC', 'id, uniqueid', 0, 1);
        if (!$attempts) {
            throw new \Behat\Mink\Exception\ExpectationException('No attempt of ' . $username, $this->getSession());
        }
        $seen = $DB->get_fieldset_select('question_attempts', 'questionid', 'questionusageid = ?', [reset($attempts)->uniqueid]);
        [$notin, $params] = $DB->get_in_or_equal($seen ?: [0], SQL_PARAMS_QM, 'param', false);
        $DB->set_field_select(
            'local_catquiz_items',
            'status',
            LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE,
            "componentname = 'question' AND componentid $notin",
            $params
        );
        \cache_helper::purge_by_event('changesintestitems');
    }

    /**
     * Checks the questions of a user's last attempt against the subscales of a scale (issue #135).
     *
     * Each question counts for the direct subscale of the scale its item lies under; the attempt must have asked
     * the number of questions, each once, and drawn them from at least the number of subscales.
     *
     * @Then /^the last CAT attempt of "([^"]*)" asked (\d+) different questions from at least (\d+) subscales of "([^"]*)"$/
     *
     * @param string $username
     * @param int $questions
     * @param int $subscales
     * @param string $scalename
     * @return void
     */
    public function the_last_cat_attempt_asked_questions_from_subscales(
        string $username,
        int $questions,
        int $subscales,
        string $scalename
    ): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $attempts = $DB->get_records('adaptivequiz_attempt', ['userid' => $userid], 'id DESC', 'id, uniqueid', 0, 1);
        if (!$attempts) {
            throw new \Behat\Mink\Exception\ExpectationException('No attempt of ' . $username, $this->getSession());
        }
        $asked = $DB->get_fieldset_select('question_attempts', 'questionid', 'questionusageid = ?', [reset($attempts)->uniqueid]);
        $rootid = (int) $DB->get_field('local_catquiz_catscales', 'id', ['name' => $scalename, 'parentid' => 0], MUST_EXIST);
        $parents = $DB->get_records_menu('local_catquiz_catscales', null, '', 'id, parentid');
        $top = [];
        foreach ($asked as $questionid) {
            $scaleids = $DB->get_fieldset_select(
                'local_catquiz_items',
                'catscaleid',
                "componentname = 'question' AND componentid = ?",
                [$questionid]
            );
            foreach ($scaleids as $scaleid) {
                $scaleid = (int) $scaleid;
                while ($scaleid && (int) ($parents[$scaleid] ?? 0) !== $rootid) {
                    $scaleid = (int) ($parents[$scaleid] ?? 0);
                }
                if ($scaleid) {
                    $top[$scaleid] = true;
                }
            }
        }
        $problems = [];
        if (count($asked) !== $questions) {
            $problems[] = count($asked) . " questions asked, not $questions";
        }
        if (count(array_unique($asked)) !== count($asked)) {
            $problems[] = 'a question was asked twice';
        }
        if (count($top) < $subscales) {
            $problems[] = 'questions of ' . count($top) . " subscales, not at least $subscales";
        }
        if ($problems) {
            throw new \Behat\Mink\Exception\ExpectationException(implode('; ', $problems), $this->getSession());
        }
    }

    /**
     * Switches the reporting of a scale off in the stored CAT settings of a test (issue #117).
     *
     * @Given /^the scale "([^"]*)" is not reported by "([^"]*)"$/
     *
     * @param string $scalename
     * @param string $idnumber
     * @return void
     */
    public function the_scale_is_not_reported_by(string $scalename, string $idnumber): void {
        global $DB;

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $test = $DB->get_record(
            'local_catquiz_tests',
            ['componentid' => $cm->instance, 'component' => 'mod_adaptivequiz'],
            '*',
            MUST_EXIST
        );
        $scaleid = $DB->get_field('local_catquiz_catscales', 'id', ['name' => $scalename], MUST_EXIST);
        $json = json_decode($test->json);
        $json->{'catquiz_scalereportcheckbox_' . $scaleid} = '0';
        $DB->set_field('local_catquiz_tests', 'json', json_encode($json), ['id' => $test->id]);
        \cache_helper::purge_all();
    }

    /**
     * A large scale tree: more subscales below a scale (issue #51).
     *
     * @Given /^the CAT scale "([^"]*)" has (\d+) more subscales$/
     *
     * @param string $scalename
     * @param int $number
     * @return void
     */
    public function the_cat_scale_has_more_subscales(string $scalename, int $number): void {
        global $DB;

        $parent = $DB->get_record('local_catquiz_catscales', ['name' => $scalename], '*', MUST_EXIST);
        for ($i = 1; $i <= $number; $i++) {
            \local_catquiz\data\dataapi::create_catscale(new \local_catquiz\data\catscale_structure([
                'name' => $scalename . ' extra ' . $i, 'parentid' => $parent->id, 'contextid' => $parent->contextid,
                'minscalevalue' => $parent->minscalevalue, 'maxscalevalue' => $parent->maxscalevalue,
                'timecreated' => time(), 'timemodified' => time(),
            ]));
        }
        \cache_helper::purge_all();
    }

    /**
     * The number of feedback editors in the settings form - of the one scale being edited (issue #51).
     *
     * @Then /^the settings form has (\d+) feedback editors$/
     *
     * @param int $number
     * @return void
     */
    public function the_settings_form_has_feedback_editors(int $number): void {
        $editors = $this->getSession()->getPage()->findAll(
            'xpath',
            "//textarea[starts-with(@name, 'feedbackeditor_scaleid_')]"
        );
        if (count($editors) !== $number) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('%d feedback editors in the form, %d expected.', count($editors), $number),
                $this->getSession()
            );
        }
    }

    /**
     * The number of scales whose feedback is carried along without fields (issue #51).
     *
     * @Then /^the settings form carries the feedback of at least (\d+) more scales$/
     *
     * @param int $number
     * @return void
     */
    public function the_settings_form_carries_feedback_of(int $number): void {
        $carriers = $this->getSession()->getPage()->findAll(
            'xpath',
            "//input[@type='hidden' and starts-with(@name, 'catquiz_feedbackcarrier_') and not(@disabled)]"
        );
        if (count($carriers) < $number) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('The feedback of %d scales is carried along, at least %d expected.', count($carriers), $number),
                $this->getSession()
            );
        }
    }

    /**
     * Checks that the keyboard focus is inside the open dialogue (issue #139).
     *
     * Moodle decides which element of a dialogue takes the focus; what matters is that it is in the
     * dialogue and not left on the page behind it.
     *
     * @Then /^the focus is inside the open dialogue$/
     *
     * @return void
     */
    public function the_focus_is_inside_the_open_dialogue(): void {
        $this->spin(function () {
            $inside = $this->getSession()->evaluateScript(
                "return !!(document.activeElement && document.activeElement.closest('.modal.show'));"
            );
            if (!$inside) {
                throw new \Behat\Mink\Exception\ExpectationException(
                    'The focus is not in the open dialogue.',
                    $this->getSession()
                );
            }
            return true;
        });
    }

    /**
     * Puts the first question of a user's last attempt into its usage a second time (issue #125).
     *
     * Reproduces what older versions of the activity did on a reload: the same question in a further
     * slot of the same usage. The host no longer does it, so the state is built here - the reviewer's
     * view of such an attempt is what the scenario checks.
     *
     * @Given /^the last CAT attempt of "([^"]*)" administered its first question a second time$/
     *
     * @param string $username
     * @return void
     */
    public function the_last_cat_attempt_administered_its_first_question_again(string $username): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $attempts = $DB->get_records('adaptivequiz_attempt', ['userid' => $userid], 'id DESC', 'id, uniqueid', 0, 1);
        if (!$attempts) {
            throw new \Behat\Mink\Exception\ExpectationException('No attempt of ' . $username, $this->getSession());
        }
        $quba = \question_engine::load_questions_usage_by_activity((int) reset($attempts)->uniqueid);
        $first = $quba->get_question(1, false);
        $slot = $quba->add_question(\question_bank::load_question($first->id));
        $quba->start_question($slot);
        \question_engine::save_questions_usage_by_activity($quba);
    }

    /**
     * Hands the saved progress of a person's running CAT attempt to someone else (issue #96).
     *
     * The state that #96 refuses: a progress row whose person is not the person of its CAT attempt. The
     * session cache is emptied as well, or the own entry would be used and the row never checked.
     *
     * @Given /^the saved progress of the running CAT attempt of "([^"]*)" belongs to "([^"]*)"$/
     *
     * @param string $owner
     * @param string $other
     * @return void
     */
    public function the_saved_progress_belongs_to(string $owner, string $other): void {
        global $DB;
        $catattempt = $this->last_cat_attempt_of($owner);
        $otherid = $DB->get_field('user', 'id', ['username' => $other], MUST_EXIST);
        $progress = $DB->get_record('local_catquiz_progress', ['attemptid' => $catattempt->id]);
        if (!$progress) {
            throw new \Behat\Mink\Exception\ExpectationException('No saved progress of ' . $owner, $this->getSession());
        }
        $DB->set_field('local_catquiz_progress', 'userid', $otherid, ['id' => $progress->id]);
        \cache_helper::purge_by_event('changesinadaptivequizattempt');
    }

    /**
     * The saved progress of a person's CAT attempt still names the given person, and there is one.
     *
     * @Then /^the saved progress of the CAT attempt of "([^"]*)" still belongs to "([^"]*)"$/
     *
     * @param string $owner
     * @param string $expected
     * @return void
     */
    public function the_saved_progress_still_belongs_to(string $owner, string $expected): void {
        global $DB;
        $catattempt = $this->last_cat_attempt_of($owner);
        $expectedid = (int) $DB->get_field('user', 'id', ['username' => $expected], MUST_EXIST);
        $rows = $DB->get_records('local_catquiz_progress', ['attemptid' => $catattempt->id]);
        if (count($rows) !== 1 || (int) reset($rows)->userid !== $expectedid) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf(
                    'Progress of the CAT attempt %d: %d rows, user %s.',
                    $catattempt->id,
                    count($rows),
                    $rows ? reset($rows)->userid : '-'
                ),
                $this->getSession()
            );
        }
    }

    /**
     * A refused progress was logged with its reason (issue #96).
     *
     * @Then /^a progress integrity failure "([^"]*)" was logged$/
     *
     * @param string $reason
     * @return void
     */
    public function a_progress_integrity_failure_was_logged(string $reason): void {
        global $DB;
        $events = $DB->get_records('logstore_standard_log', ['eventname' => '\\local_catquiz\\event\\progress_integrity_failed']);
        foreach ($events as $event) {
            $other = json_decode((string) $event->other, true);
            if (($other['reason'] ?? null) === $reason) {
                return;
            }
        }
        throw new \Behat\Mink\Exception\ExpectationException(
            sprintf('No progress integrity failure "%s" logged (%d others).', $reason, count($events)),
            $this->getSession()
        );
    }

    /**
     * Sets the numbering of CAT attempts exactly N ahead of the activity's (issues #95, #101).
     *
     * Afterwards the next CAT attempt gets the id the next activity attempt gets, plus N. Rows are inserted
     * and deleted again, which moves the sequence on every database and leaves nothing that statistics
     * could count. Whichever numbering is behind is moved.
     *
     * @Given /^the CAT attempt numbering is (\d+) ahead of the activity attempts$/
     *
     * @param int $n
     * @return void
     */
    public function the_cat_attempt_numbering_is_ahead(int $n): void {
        $catnext = $this->next_id('local_catquiz_attempts');
        $activitynext = $this->next_id('adaptivequiz_attempt');
        if ($catnext - $activitynext < $n) {
            $this->advance_to('local_catquiz_attempts', $activitynext + $n);
        } else if ($catnext - $activitynext > $n) {
            $this->advance_to('adaptivequiz_attempt', $catnext - $n);
        }
    }

    /**
     * The next activity attempt gets the id the CAT attempt of a person already has (issue #101).
     *
     * The case where mixing up the two id spaces does harm: the attempt id of person B is the CAT id of
     * person A.
     *
     * @Given /^the next activity attempt id equals the CAT attempt id of "([^"]*)"$/
     *
     * @param string $username
     * @return void
     */
    public function the_next_activity_attempt_id_equals_the_cat_attempt_id_of(string $username): void {
        $target = (int) $this->last_cat_attempt_of($username)->id;
        $next = $this->next_id('adaptivequiz_attempt');
        if ($next > $target) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('The activity attempts are already at %d, beyond %d; move the CAT numbering ahead first.', $next, $target),
                $this->getSession()
            );
        }
        $this->advance_to('adaptivequiz_attempt', $target);
    }

    /**
     * The last CAT attempt of a person is filed under that person's own activity attempt, and the two ids
     * differ (issues #95, #101).
     *
     * @Then /^the CAT attempt of "([^"]*)" is filed under its own activity attempt with a different id$/
     *
     * @param string $username
     * @return void
     */
    public function the_cat_attempt_is_filed_under_its_own_activity_attempt(string $username): void {
        global $DB;
        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $attempts = $DB->get_records('adaptivequiz_attempt', ['userid' => $userid], 'id DESC', 'id, userid', 0, 1);
        $attempt = reset($attempts);
        $catattempt = $this->last_cat_attempt_of($username);
        $progress = $DB->get_records('local_catquiz_progress', ['attemptid' => $catattempt->id]);
        $problems = [];
        if (!$attempt || (int) $catattempt->attemptid !== (int) $attempt->id) {
            $problems[] = 'the CAT attempt names another activity attempt';
        }
        if ((int) $catattempt->userid !== $userid) {
            $problems[] = 'the CAT attempt belongs to someone else';
        }
        if ($attempt && (int) $catattempt->id === (int) $attempt->id) {
            $problems[] = 'both ids are the same, nothing was tested';
        }
        if (count($progress) > 1 || ($progress && (int) reset($progress)->userid !== $userid)) {
            $problems[] = 'the progress is not the person\'s one';
        }
        if ($problems) {
            throw new \Behat\Mink\Exception\ExpectationException(implode('; ', $problems), $this->getSession());
        }
    }

    /**
     * The last CAT attempt of a person.
     *
     * @param string $username
     * @return \stdClass
     */
    private function last_cat_attempt_of(string $username): \stdClass {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $rows = $DB->get_records('local_catquiz_attempts', ['userid' => $userid], 'id DESC', '*', 0, 1);
        if (!$rows) {
            throw new \Behat\Mink\Exception\ExpectationException('No CAT attempt of ' . $username, $this->getSession());
        }
        return reset($rows);
    }

    /**
     * A row for moving the numbering of a table, deleted right away.
     *
     * @param string $table local_catquiz_attempts or adaptivequiz_attempt
     * @return int Its id.
     */
    private function insert_and_delete(string $table): int {
        global $DB;
        $id = (int) $DB->insert_record($table, (object) $this->numbering_row($table));
        $DB->delete_records($table, ['id' => $id]);
        return $id;
    }

    /**
     * The id the next row of a table gets.
     *
     * @param string $table
     * @return int
     */
    private function next_id(string $table): int {
        return $this->insert_and_delete($table) + 1;
    }

    /**
     * Moves the numbering of a table on: its next row gets the id given.
     *
     * @param string $table
     * @param int $next
     */
    private function advance_to(string $table, int $next): void {
        global $DB;
        // In one go: the sequences of a test site start far apart, and a row inserted per step took
        // hundreds of thousands of them. A row with the id before the target makes the sequence
        // continue after it; it is deleted again.
        $before = $next - 1;
        $row = $this->numbering_row($table);
        $DB->execute(
            'INSERT INTO {' . $table . '} (id, ' . implode(', ', array_keys($row)) . ') VALUES (?'
                . str_repeat(', ?', count($row)) . ')',
            array_merge([$before], array_values($row))
        );
        $DB->get_manager()->reset_sequence(new \xmldb_table($table));
        $DB->delete_records($table, ['id' => $before]);
    }

    /**
     * A row that can be put into a table to move its numbering.
     *
     * @param string $table
     * @return array
     */
    private function numbering_row(string $table): array {
        $row = $table === 'local_catquiz_attempts'
            ? ['userid' => 0, 'attemptid' => -1, 'component' => 'behat_numbering', 'status' => 1, 'json' => '{}']
            : ['instance' => 0, 'userid' => 0, 'uniqueid' => 0, 'attemptstate' => 'complete', 'attemptstopcriteria' => '',
                'questionsattempted' => 0, 'difficultysum' => 0, 'standarderror' => 0, 'measure' => 0];
        return $row + ['timecreated' => 0, 'timemodified' => 0];
    }

    /**
     * Fill specified HTMLQuickForm element by its number under goven xpath with a value.
     * @When /^I fill in the "([^"]*)" element number "([^"]*)" with the dynamic identifier "([^"]*)" with "([^"]*)"$/
     *
     * @param string $fieldtype
     * @param string $numberofitem
     * @param string $dynamicidentifier
     * @param string $value
     *
     * @return void
     *
     */
    public function i_fill_in_the_element_with_dynamic_identifier($fieldtype, $numberofitem, $dynamicidentifier, $value) {
        // Use $dynamicIdentifier to locate and fill in the corresponding form field.
        // Use $value to set the desired value in the form field.

        // First we need to open all collapsibles.
        // We should probably have a single fuction for that.
        $xpathtarget = "//div[contains(@id, 'catquiz_feedback_collapse_')]";
        $fields = $this->getSession()->getPage()->findAll('xpath', $xpathtarget);

        foreach ($fields as $field) {
            $id = $field->getAttribute('id');
            // Use JavaScript to add the expected class to the element.
            $script = "document.getElementById('$id').classList.add('show');";
            $this->getSession()->executeScript($script);
            $this->getSession()->wait(500);
        }
        // Now we get the form element fields by the identifier and its number in DOM.
        switch ($fieldtype) {
            case 'editor':
                $xpathtarget = "(//div[contains(@id, '" . $dynamicidentifier . "')][@contenteditable='true'])
                    [" . $numberofitem . "]";
                break;
            case 'autocomplete':
                $xpathtarget = "(//div[contains(@id, '" . $dynamicidentifier . "')])
                    [" . $numberofitem . "]//input[contains(@id, 'form_autocomplete_input-')]";
                $xpathtarget1 = "(//div[contains(@id, '" . $dynamicidentifier . "')])
                    [" . $numberofitem . "]//li[contains(@id, 'form_autocomplete_suggestions-')]";
                break;
            case 'wb_colourpicker':
                $xpathtarget = "(//div[contains(@id, '" . $dynamicidentifier . "')])
                    [" . $numberofitem . "]//span[@data-colour=" . $value . "]";
                break;
            default:
                $xpathtarget = "(//" . $fieldtype . "[contains(@id, '" . $dynamicidentifier . "')])[" . $numberofitem . "]";
        }

        // Assuming you want to find an editor element related to the competency and fill it with the specified value.
        $field = $this->getSession()->getPage()->find('xpath', $xpathtarget);
        if ($field->isVisible()) {
            switch ($fieldtype) {
                case 'autocomplete':
                    $field->setValue($value);
                    // The suggestion list is populated asynchronously (debounced
                    // AJAX). Wait for the visible suggestion that actually
                    // contains the requested value instead of pressing Enter and
                    // clicking the first list item.
                    $escapedvalue = behat_context_helper::escape($value);
                    $suggestionxpath = $xpathtarget1
                        . "[@role='option']"
                        . "[not(@aria-hidden='true')]"
                        . "[contains(normalize-space(.), $escapedvalue)]";
                    $suggestion = null;
                    for ($attempt = 0; $attempt < 20; $attempt++) {
                        $candidate = $this->getSession()->getPage()->find('xpath', $suggestionxpath);
                        if ($candidate && $candidate->isVisible()) {
                            $suggestion = $candidate;
                            break;
                        }
                        $this->getSession()->wait(300);
                    }
                    if (!$suggestion) {
                        throw new \Behat\Mink\Exception\ExpectationException(
                            "Visible autocomplete suggestion containing '$value' did not appear",
                            $this->getSession()
                        );
                    }
                    $suggestion->click();
                    // Moodle's autocomplete transfers the selection to the hidden
                    // native <select> asynchronously (a promise chain that ends in
                    // option.selected = true plus a native change event). Wait for
                    // that to complete rather than forcing the native state
                    // ourselves: the test must verify Moodle's own behaviour, and
                    // the native <select> is the value actually submitted (Behat
                    // 001 / catquiz_courses).
                    $containerxpath = "(//div[contains(@id, '" . $dynamicidentifier . "')])[" . $numberofitem . "]";
                    $selected = false;
                    for ($attempt = 0; $attempt < 30; $attempt++) {
                        if ($this->native_autocomplete_has_selected_text($containerxpath, $value)) {
                            $selected = true;
                            break;
                        }
                        $this->getSession()->wait(200);
                    }
                    if (!$selected) {
                        throw new \Behat\Mink\Exception\ExpectationException(
                            "Autocomplete '$value' was clicked, but Moodle did not select the "
                                . "corresponding native option. Native options: "
                                . $this->get_native_autocomplete_debug($containerxpath),
                            $this->getSession()
                        );
                    }
                    // Close the suggestion list only after the selection completed,
                    // so it cannot overlap and swallow later clicks.
                    $field->keyPress(27);
                    break;
                case 'wb_colourpicker':
                    $field->click();
                    break;
                default:
                    // Fill in the form field with the specified value.
                    $field->setValue($value);
            }
        }
    }

    /**
     * Whether the hidden native <select> of a Moodle autocomplete has an option
     * with the given text selected.
     *
     * Uses evaluateScript so it reads the real DOM state: Moodle hides the
     * original <select> (display:none, aria-hidden), so Mink's getText() on its
     * options is unreliable, while option.selected and option.textContent are
     * accurate regardless of visibility.
     *
     * @param string $containerxpath Xpath of the form item wrapping the select.
     * @param string $value          The visible option text to look for.
     *
     * @return bool
     */
    protected function native_autocomplete_has_selected_text($containerxpath, $value) {
        $xpath = json_encode($containerxpath . '//select');
        $wanted = json_encode($value);
        $script = <<<JS
(function() {
    const select = document.evaluate(
        $xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null
    ).singleNodeValue;
    if (!select) {
        return false;
    }
    const wanted = $wanted;
    return Array.from(select.options).some(function (option) {
        return option.selected && (option.textContent || '').trim().indexOf(wanted) !== -1;
    });
})()
JS;
        return (bool) $this->getSession()->evaluateScript($script);
    }

    /**
     * Returns a JSON dump of the native <select> options and their state.
     *
     * Included in failure messages so a red run shows the actual DOM state
     * (which option exists and whether it is selected) instead of only
     * "not selected".
     *
     * @param string $containerxpath Xpath of the form item wrapping the select.
     *
     * @return string
     */
    protected function get_native_autocomplete_debug($containerxpath) {
        $xpath = json_encode($containerxpath . '//select');
        $script = <<<JS
(function() {
    const select = document.evaluate(
        $xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null
    ).singleNodeValue;
    if (!select) {
        return 'NO SELECT';
    }
    return JSON.stringify(Array.from(select.options).map(function (option) {
        return {
            value: option.value,
            text: (option.textContent || '').trim(),
            selected: option.selected,
            disabled: option.disabled
        };
    }));
})()
JS;
        return (string) $this->getSession()->evaluateScript($script);
    }

    /**
     * Asserts that the native <select> of an autocomplete has an option with the
     * given text selected.
     *
     * This checks the value that is actually submitted with the form, which is
     * independent of the visible autocomplete chips. Placing this assertion at
     * the hand-over points (right after selection, after a failed validation
     * submit, and after save + reload) localises exactly where a selection is
     * lost (Behat 001 / catquiz_courses).
     *
     * @Then /^the autocomplete number "([^"]*)" for "([^"]*)" has "([^"]*)" natively selected$/
     *
     * @param string $numberofitem
     * @param string $dynamicidentifier
     * @param string $value
     *
     * @return void
     */
    public function autocomplete_should_have_natively_selected($numberofitem, $dynamicidentifier, $value) {
        $containerxpath = "(//div[contains(@id, '" . $dynamicidentifier . "')])[" . $numberofitem . "]";
        if ($this->native_autocomplete_has_selected_text($containerxpath, $value)) {
            return;
        }
        throw new \Behat\Mink\Exception\ExpectationException(
            "Native autocomplete select under '$dynamicidentifier' number '$numberofitem' "
                . "does not have '$value' selected. Native options: "
                . $this->get_native_autocomplete_debug($containerxpath),
            $this->getSession()
        );
    }

    /**
     * The question id of a question bank entry, by its idnumber: the version a CAT item refers to (issue #82).
     *
     * @param string $idnumber
     * @return int
     */
    private function catquiz_item_question_id(string $idnumber): int {
        global $DB;
        $ids = $DB->get_fieldset_sql(
            "SELECT qv.questionid
               FROM {question_bank_entries} qbe
               JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
               JOIN {local_catquiz_items} lci ON lci.componentid = qv.questionid AND lci.componentname = 'question'
              WHERE qbe.idnumber = :idnumber
           ORDER BY qv.version DESC",
            ['idnumber' => $idnumber]
        );
        if (!$ids) {
            throw new \Behat\Mink\Exception\ExpectationException('No CAT item for the question ' . $idnumber, $this->getSession());
        }
        return (int) reset($ids);
    }

    /**
     * The id of a CAT scale, by its name.
     *
     * @param string $scalename
     * @return int
     */
    private function catquiz_scale_id(string $scalename): int {
        global $DB;
        return (int) $DB->get_field('local_catquiz_catscales', 'id', ['name' => $scalename], MUST_EXIST);
    }

    /**
     * Hides a question in the question bank, the way the bank does it for a question in use (issue #82).
     *
     * Deleting a question that is in use only sets the status of its versions to hidden -
     * question_delete_question() - and triggers no event. That is what this step does, for every version of
     * the entry, whether or not something uses it yet.
     *
     * @Given /^the question "([^"]*)" is hidden in the question bank$/
     *
     * @param string $idnumber
     * @return void
     */
    public function the_question_is_hidden_in_the_question_bank(string $idnumber): void {
        global $DB;
        $entryid = $DB->get_field('question_bank_entries', 'id', ['idnumber' => $idnumber], MUST_EXIST);
        $DB->set_field(
            'question_versions',
            'status',
            \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN,
            ['questionbankentryid' => $entryid]
        );
    }

    /**
     * Assigns a question that is already a CAT item to a further scale, active there (issue #82).
     *
     * @Given /^the question "([^"]*)" is also an item of the CAT scale "([^"]*)"$/
     *
     * @param string $idnumber
     * @param string $scalename
     * @return void
     */
    public function the_question_is_also_an_item_of_the_cat_scale(string $idnumber, string $scalename): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        $result = \local_catquiz\catscale::add_or_update_testitem_to_scale(
            $this->catquiz_scale_id($scalename),
            $this->catquiz_item_question_id($idnumber),
            LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE
        );
        if (!$result->isOk()) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'Not assigned to ' . $scalename . ': ' . $result->get_status(),
                $this->getSession()
            );
        }
    }

    /**
     * Deactivates a CAT item in one scale, as the eye icon of the CAT manager does (issue #82).
     *
     * @Given /^the question "([^"]*)" is deactivated in the CAT scale "([^"]*)"$/
     *
     * @param string $idnumber
     * @param string $scalename
     * @return void
     */
    public function the_question_is_deactivated_in_the_cat_scale(string $idnumber, string $scalename): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        \local_catquiz\catscale::add_or_update_testitem_to_scale(
            $this->catquiz_scale_id($scalename),
            $this->catquiz_item_question_id($idnumber),
            LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE
        );
    }

    /**
     * Opens the questions of one CAT scale - without its subscales - in the CAT manager (issue #82).
     *
     * @Given /^I open the questions of the CAT scale "([^"]*)" in the CAT manager$/
     *
     * @param string $scalename
     * @return void
     */
    public function i_open_the_questions_of_the_cat_scale_in_the_cat_manager(string $scalename): void {
        $scaleid = $this->catquiz_scale_id($scalename);
        $url = new moodle_url('/local/catquiz/manage_catscales.php', [
            'tab' => 'questions',
            'scaleid' => $scaleid,
            'contextid' => \local_catquiz\catscale::get_context_id($scaleid),
            'usesubs' => 0,
        ]);
        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /**
     * Opens the detail card of a CAT item in the CAT manager (issue #82).
     *
     * @Given /^I open the CAT item "([^"]*)" of the CAT scale "([^"]*)" in the CAT manager$/
     *
     * @param string $idnumber
     * @param string $scalename
     * @return void
     */
    public function i_open_the_cat_item_of_the_cat_scale_in_the_cat_manager(string $idnumber, string $scalename): void {
        $scaleid = $this->catquiz_scale_id($scalename);
        $url = new moodle_url('/local/catquiz/manage_catscales.php', [
            'tab' => 'questions',
            'scaleid' => $scaleid,
            'contextid' => \local_catquiz\catscale::get_context_id($scaleid),
            'id' => $this->catquiz_item_question_id($idnumber),
            'component' => 'question',
        ]);
        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /**
     * Checks that the last attempt of a user never asked a question (issue #82).
     *
     * @Then /^the last CAT attempt of "([^"]*)" did not ask "([^"]*)"$/
     *
     * @param string $username
     * @param string $idnumber
     * @return void
     */
    public function the_last_cat_attempt_did_not_ask(string $username, string $idnumber): void {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $attempts = $DB->get_records('adaptivequiz_attempt', ['userid' => $userid], 'id DESC', 'id, uniqueid', 0, 1);
        if (!$attempts) {
            throw new \Behat\Mink\Exception\ExpectationException('No attempt of ' . $username, $this->getSession());
        }
        $attempt = reset($attempts);
        $asked = $DB->get_fieldset_sql(
            "SELECT qbe.idnumber
               FROM {question_attempts} qa
               JOIN {question_versions} qv ON qv.questionid = qa.questionid
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              WHERE qa.questionusageid = :usage",
            ['usage' => $attempt->uniqueid]
        );
        if (!$asked) {
            throw new \Behat\Mink\Exception\ExpectationException('The last attempt asked nothing.', $this->getSession());
        }
        if (in_array($idnumber, $asked, true)) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The last attempt asked ' . $idnumber . ': ' . implode(', ', $asked),
                $this->getSession()
            );
        }
    }

    /**
     * Checks the runtime pool of one CAT scale - as a test of that scale reads it - for a question (issue #82).
     *
     * @Then /^the CAT pool of "([^"]*)" (contains|does not contain) the question "([^"]*)"$/
     *
     * @param string $scalename
     * @param string $which
     * @param string $idnumber
     * @return void
     */
    public function the_cat_pool_contains_the_question(string $scalename, string $which, string $idnumber): void {
        $scaleid = $this->catquiz_scale_id($scalename);
        $pool = (new \local_catquiz\catscale($scaleid))->get_testitems(\local_catquiz\catscale::get_context_id($scaleid));
        $contains = array_key_exists($this->catquiz_item_question_id($idnumber), $pool);
        if ($contains !== ($which === 'contains')) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The pool of ' . $scalename . ($contains ? ' contains ' : ' does not contain ') . $idnumber,
                $this->getSession()
            );
        }
    }

    /**
     * Checks that a root scale points to a context of its own that exists (issue #81).
     *
     * @Then /^the CAT scale "([^"]*)" has a context of its own$/
     *
     * @param string $scalename
     * @return void
     */
    public function the_cat_scale_has_a_context_of_its_own(string $scalename): void {
        global $DB;
        $contextid = (int) $DB->get_field('local_catquiz_catscales', 'contextid', ['name' => $scalename], MUST_EXIST);
        if ($contextid <= 0 || !$DB->record_exists('local_catquiz_catcontext', ['id' => $contextid])) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The scale ' . $scalename . ' points to context ' . $contextid,
                $this->getSession()
            );
        }
    }

    /**
     * Checks that a subscale stores no context and resolves to the one of its root (issue #81).
     *
     * @Then /^the CAT scale "([^"]*)" has no context of its own and uses the one of "([^"]*)"$/
     *
     * @param string $scalename
     * @param string $rootname
     * @return void
     */
    public function the_cat_scale_uses_the_context_of(string $scalename, string $rootname): void {
        global $DB;
        $scale = $DB->get_record('local_catquiz_catscales', ['name' => $scalename], '*', MUST_EXIST);
        $rootcontext = (int) $DB->get_field('local_catquiz_catscales', 'contextid', ['name' => $rootname], MUST_EXIST);
        \cache_helper::purge_by_event('changesincatscales');
        if ($scale->contextid !== null || \local_catquiz\catscale::get_context_id((int) $scale->id) !== $rootcontext) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The scale ' . $scalename . ' stores context ' . var_export($scale->contextid, true),
                $this->getSession()
            );
        }
    }

    /**
     * Checks that no id is used twice on the page (issue #30).
     *
     * Tabs, collapses and dialogues find their target by id. Two instances with the same ids - the same
     * shortcode twice, a shortcode next to the result page - make a click in one open the panel of the
     * other, or none at all. The duplicates are named in the failure.
     *
     * @Then /^the page has no duplicate ids$/
     *
     * @return void
     */
    public function the_page_has_no_duplicate_ids(): void {
        $this->spin(function () {
            $duplicates = $this->getSession()->evaluateScript(
                "return (function() {" .
                "  var seen = {}, twice = [];" .
                "  document.querySelectorAll('[id]').forEach(function(el) {" .
                "    if (!el.id) { return; }" .
                "    if (seen[el.id] === 1) { twice.push(el.id); }" .
                "    seen[el.id] = (seen[el.id] || 0) + 1;" .
                "  });" .
                "  return twice;" .
                "})();"
            );
            if (!empty($duplicates)) {
                throw new \Behat\Mink\Exception\ExpectationException(
                    'Ids used more than once on the page: ' . implode(', ', $duplicates),
                    $this->getSession()
                );
            }
            return true;
        });
    }

    /**
     * The templates of CAT settings - test environments of no activity - with a name, or all of them (issue #89).
     *
     * @param string|null $name
     * @return \stdClass[]
     */
    private function cat_templates(?string $name = null): array {
        global $DB;
        $conditions = ['componentid' => 0];
        if ($name !== null) {
            $conditions['name'] = $name;
        }
        return $DB->get_records('local_catquiz_tests', $conditions, 'id');
    }

    /**
     * Checks how many templates of CAT settings exist, with a name or at all (issue #89).
     *
     * @Then /^there (?:is|are) (\d+) CAT templates?(?: named "([^"]*)")?$/
     *
     * @param int $count
     * @param string $name
     * @return void
     */
    public function there_are_cat_templates(int $count, string $name = ''): void {
        $found = count($this->cat_templates($name === '' ? null : $name));
        if ($found !== $count) {
            $named = $name ? " named \"$name\"" : '';
            throw new \Behat\Mink\Exception\ExpectationException(
                "Expected $count CAT templates$named, found $found.",
                $this->getSession()
            );
        }
    }

    /**
     * Checks a stored value of a template of CAT settings; nested keys with a dot (issue #89).
     *
     * @Then /^the CAT template "([^"]*)" stores "([^"]*)" as "([^"]*)"$/
     *
     * @param string $name
     * @param string $key E.g. maxquestionsgroup.catquiz_maxquestions
     * @param string $expected
     * @return void
     */
    public function the_cat_template_stores(string $name, string $key, string $expected): void {
        $templates = $this->cat_templates($name);
        if (count($templates) !== 1) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Expected one CAT template named \"$name\", found " . count($templates) . '.',
                $this->getSession()
            );
        }
        $value = json_decode(reset($templates)->json, true);
        foreach (explode('.', $key) as $part) {
            $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : null;
        }
        if ((string) $value !== $expected) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Template \"$name\": $key is \"" . (string) $value . "\", not \"$expected\".",
                $this->getSession()
            );
        }
    }

    /**
     * Checks that the CAT settings of an activity name the template they are based on (issue #89).
     *
     * @Then /^the CAT settings of "([^"]*)" are based on the template "([^"]*)"$/
     *
     * @param string $idnumber
     * @param string $name
     * @return void
     */
    public function the_cat_settings_are_based_on_the_template(string $idnumber, string $name): void {
        global $DB;
        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $parentid = (int) $DB->get_field('local_catquiz_tests', 'parentid', [
            'component' => 'mod_adaptivequiz', 'componentid' => $cm->instance,
        ], MUST_EXIST);
        $templates = $this->cat_templates($name);
        $template = reset($templates);
        if (!$template || (int) $template->id !== $parentid) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The CAT settings of $idnumber are based on template $parentid, not on \"$name\".",
                $this->getSession()
            );
        }
    }

    /**
     * Checks lines of the visible text of the main region, exactly and in the given order (issue #77).
     *
     * The written feedback is one block with a line per scale; "should appear before" compares elements and
     * finds the same block for every line. Each line has to be found as a whole line, after the one before.
     *
     * @Then /^the following lines appear in this order in the main region:$/
     *
     * @param \Behat\Gherkin\Node\TableNode $table One line per row, first column.
     * @return void
     */
    public function the_following_lines_appear_in_this_order(\Behat\Gherkin\Node\TableNode $table): void {
        $text = (string) $this->evaluate_script(
            "return (document.querySelector('#region-main') || document.body).innerText;"
        );
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text)), 'strlen'));
        $from = 0;
        foreach ($table->getRows() as $row) {
            $wanted = trim($row[0]);
            $found = null;
            for ($i = $from; $i < count($lines); $i++) {
                if ($lines[$i] === $wanted) {
                    $found = $i;
                    break;
                }
            }
            if ($found === null) {
                $where = in_array($wanted, $lines, true) ? 'only before the line before it' : 'not as a line';
                throw new \Behat\Mink\Exception\ExpectationException("\"$wanted\" is $where.", $this->getSession());
            }
            $from = $found + 1;
        }
    }

    /**
     * The items of a CAT scale and its subscales lose their parameters, as after a new calibration context or
     * before the first calibration: the results stay, the test information has nothing to sum (issue #150).
     *
     * @Given /^the items of the CAT scale "([^"]*)" have no parameters$/
     *
     * @param string $scalename
     * @return void
     */
    public function the_items_of_the_cat_scale_have_no_parameters(string $scalename): void {
        global $DB;
        $rootid = $this->catquiz_scale_id($scalename);
        $scaleids = [$rootid, ...\local_catquiz\catscale::get_subscale_ids($rootid)];
        [$insql, $params] = $DB->get_in_or_equal($scaleids);
        $itemids = $DB->get_fieldset_select('local_catquiz_items', 'id', "catscaleid $insql", $params);
        if (!$itemids) {
            throw new \Behat\Mink\Exception\ExpectationException("No items in $scalename.", $this->getSession());
        }
        [$initem, $itemparams] = $DB->get_in_or_equal($itemids);
        $DB->execute("UPDATE {local_catquiz_items} SET activeparamid = NULL WHERE id $initem", $itemparams);
        $DB->delete_records_select('local_catquiz_itemparams', "itemid $initem", $itemparams);
        \cache_helper::purge_all();
    }

    /**
     * A CAT scale moves to a new CAT context, as a new calibration starts it: through the scale update of the CAT
     * manager, which takes the items and parameters along (issue #148). The results stay in the old context.
     *
     * @Given /^the CAT scale "([^"]*)" is switched to a new CAT context$/
     *
     * @param string $scalename
     * @return void
     */
    public function the_cat_scale_is_switched_to_a_new_cat_context(string $scalename): void {
        global $DB;
        $scaleid = $this->catquiz_scale_id($scalename);
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Recalibration of ' . $scalename, 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 60, 'endtimestamp' => time() + YEARSECS, 'timecreated' => time(),
            'timemodified' => time(), 'usermodified' => 0,
        ]);
        \local_catquiz\data\dataapi::update_catscale((object) ['id' => $scaleid, 'contextid' => $contextid]);
        if ((int) \local_catquiz\catscale::get_context_id($scaleid) !== $contextid) {
            throw new \Behat\Mink\Exception\ExpectationException("$scalename did not move.", $this->getSession());
        }
        \cache_helper::purge_all();
    }

    /**
     * CAT results as the strategy "lowest skill gap" stores them (issue #150, re-audit 27.10.2026, test 20): the root
     * scale measured validly but not the reported scale (isvalid 0, reason not_primary), the reported subscale valid.
     * With the optional column "root reasons" (comma-separated) the root scale carries other reasons - se_max for a
     * root scale not measured validly (re-audit 28.10.2026).
     *
     * @Given /^the following CAT results of "([^"]*)" exist under the lowest skill gap:$/
     *
     * @param string $idnumber
     * @param \Behat\Gherkin\Node\TableNode $table Columns: user, days ago, root ability, reported scale, reported ability,
     *     optionally root reasons.
     * @return void
     */
    public function the_following_cat_results_exist_under_the_lowest_skill_gap(
        string $idnumber,
        \Behat\Gherkin\Node\TableNode $table
    ): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        static $hostattemptid = 950000;

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $test = $DB->get_record(
            'local_catquiz_tests',
            ['componentid' => $cm->instance, 'component' => 'mod_adaptivequiz'],
            '*',
            MUST_EXIST
        );
        $rootid = (int) $test->catscaleid;
        $contextid = (int) \local_catquiz\catscale::get_context_id($rootid);
        foreach ($table->getHash() as $row) {
            $userid = (int) $DB->get_field('user', 'id', ['username' => $row['user']], MUST_EXIST);
            $reported = $this->catquiz_scale_id($row['reported scale']);
            $end = time() - (int) $row['days ago'] * DAYSECS;
            $id = $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => $userid, 'scaleid' => $rootid, 'contextid' => $contextid, 'courseid' => $cm->course,
                'attemptid' => ++$hostattemptid, 'component' => 'mod_adaptivequiz', 'instanceid' => $cm->instance,
                'teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, 'status' => 0, 'starttime' => $end - 600,
                'endtime' => $end, 'personability_after_attempt' => (float) $row['root ability'],
                'json' => json_encode([
                    'personabilities' => [
                        $rootid => (float) $row['root ability'], $reported => (float) $row['reported ability'],
                    ],
                    'personabilities_abilities' => [
                        $reported => ['value' => (float) $row['reported ability'], 'primary' => true, 'toreport' => true],
                        $rootid => ['value' => (float) $row['root ability'], 'primary' => false, 'toreport' => false],
                    ],
                ]),
                'timecreated' => $end, 'timemodified' => $end,
            ]);
            $rows = [
                $rootid => [(float) $row['root ability'], 0, 0, $row['root reasons'] ?? 'not_primary'],
                $reported => [(float) $row['reported ability'], 1, 1, ''],
            ];
            foreach ($rows as $scaleid => [$ability, $isvalid, $isprimary, $reasons]) {
                $DB->insert_record('local_catquiz_personparams', (object) [
                    'userid' => $userid, 'catscaleid' => $scaleid, 'contextid' => $contextid, 'attemptid' => $id,
                    'ability' => $ability, 'isvalid' => $isvalid, 'isprimary' => $isprimary, 'resultsource' => 'current',
                    'validationstatus' => $reasons, 'status' => 0, 'timecreated' => $end, 'timemodified' => $end,
                ]);
            }
        }
    }

    /**
     * A recalibration as the CAT manager starts it, with the old items left without parameters - the pattern of
     * issue #157: the scale gets a new CAT context with a copy of the items that have parameters (higher item ids,
     * dataapi::create_new_context_for_scale()); the items of the old context keep no parameters - pilot rows with the
     * lower ids. The tests of the scale move to the new context.
     *
     * @Given /^the CAT scale "([^"]*)" is recalibrated into the CAT context "([^"]*)" and its old items keep no parameters$/
     *
     * @param string $scalename
     * @param string $contextname
     * @return void
     */
    public function the_cat_scale_is_recalibrated_and_its_old_items_keep_no_parameters(
        string $scalename,
        string $contextname
    ): void {
        global $DB;
        $scaleid = $this->catquiz_scale_id($scalename);
        $old = (int) \local_catquiz\catscale::get_context_id($scaleid);
        $context = \local_catquiz\data\dataapi::create_new_context_for_scale($scaleid, $scalename, 'behat');
        $DB->set_field('local_catquiz_catcontext', 'name', $contextname, ['id' => $context->id]);
        $new = (int) \local_catquiz\catscale::get_context_id($scaleid);
        if ($new === $old || $new !== (int) $context->id) {
            throw new \Behat\Mink\Exception\ExpectationException("$scalename did not move.", $this->getSession());
        }
        $scales = [$scaleid, ...\local_catquiz\catscale::get_subscale_ids($scaleid)];
        [$insql, $params] = $DB->get_in_or_equal($scales, SQL_PARAMS_NAMED);
        $DB->set_field_select(
            'local_catquiz_items',
            'activeparamid',
            null,
            "contextid = :old AND catscaleid $insql",
            $params + ['old' => $old]
        );
        foreach ($DB->get_records('local_catquiz_tests', ['catscaleid' => $scaleid]) as $test) {
            $json = json_decode($test->json);
            $json->catquiz_catcontext = $new;
            $DB->update_record(
                'local_catquiz_tests',
                (object) ['id' => $test->id, 'contextid' => $new, 'json' => json_encode($json)]
            );
        }
        $min = (int) $DB->get_field_sql(
            "SELECT MIN(id) FROM {local_catquiz_items} WHERE contextid = :new AND catscaleid $insql",
            $params + ['new' => $new]
        );
        $max = (int) $DB->get_field_sql(
            "SELECT MAX(id) FROM {local_catquiz_items} WHERE contextid = :old AND catscaleid $insql",
            $params + ['old' => $old]
        );
        if (!$min || $min < $max) {
            throw new \Behat\Mink\Exception\ExpectationException('The new items do not have the higher ids.', $this->getSession());
        }
        \cache_helper::purge_all();
    }

    /**
     * Every question of the last attempt of a person is the calibrated item of a CAT context: the item played (from
     * the progress), its context, its active parameters, no pilot; the questions of the question usage are the same
     * (issue #157).
     *
     * @Then /^the questions of the last CAT attempt of "([^"]*)" are calibrated items of the CAT context "([^"]*)"$/
     *
     * @param string $username
     * @param string $contextname
     * @return void
     */
    public function the_questions_are_calibrated_items_of_the_context(string $username, string $contextname): void {
        global $DB;
        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $contextid = (int) $DB->get_field('local_catquiz_catcontext', 'id', ['name' => $contextname], MUST_EXIST);
        $attempt = $DB->get_records('adaptivequiz_attempt', ['userid' => $userid], 'id DESC', 'id, uniqueid', 0, 1);
        $attempt = reset($attempt);
        $catattemptid = \local_catquiz\catquiz::get_cat_attempt_id((int) $attempt->id, 'mod_adaptivequiz');
        $progress = \local_catquiz\teststrategy\progress::load_for_reading((int) $catattemptid);
        $fail = fn(string $m) => throw new \Behat\Mink\Exception\ExpectationException($m, $this->getSession());
        if (!$progress) {
            $fail("No progress of the attempt of $username.");
        }
        if ((int) $progress->get_contextid() !== $contextid) {
            $fail('The attempt played in context ' . $progress->get_contextid() . ", not in $contextname.");
        }
        if ((int) $DB->get_field('local_catquiz_attempts', 'contextid', ['id' => $catattemptid]) !== $contextid) {
            $fail("The stored attempt is not in $contextname.");
        }
        $played = $progress->get_playedquestions();
        $asked = array_map(
            'intval',
            $DB->get_fieldset_select('question_attempts', 'questionid', 'questionusageid = ?', [$attempt->uniqueid])
        );
        sort($asked);
        $ids = array_map('intval', array_keys($played));
        sort($ids);
        if (!$ids || $ids !== $asked) {
            $fail('Played questions and the question usage differ: ' . json_encode([$ids, $asked]));
        }
        foreach ($played as $questionid => $question) {
            $item = $DB->get_record('local_catquiz_items', ['id' => $question->itemid]);
            if (!$item || (int) $item->contextid !== $contextid || (int) $item->componentid !== (int) $questionid) {
                $fail("Question $questionid was played with an item of another context.");
            }
            $param = $item->activeparamid ? $DB->get_record('local_catquiz_itemparams', ['id' => $item->activeparamid]) : null;
            if (!$param || (int) $param->itemid !== (int) $item->id || (int) $param->contextid !== $contextid) {
                $fail("Question $questionid has no active parameters of its item in $contextname.");
            }
            if (!empty($question->is_pilot)) {
                $fail("Question $questionid was played as a pilot.");
            }
        }
    }

    /**
     * The time from which the results of CAT attempts are stored (result_provenance, issue #150, re-audit 28.10.2026).
     *
     * @Given /^results of CAT attempts are stored since (\d+) days ago$/
     *
     * @param int $days
     * @return void
     */
    public function results_of_cat_attempts_are_stored_since(int $days): void {
        set_config(\local_catquiz\local\result\result_provenance::CONFIG, time() - $days * DAYSECS, 'local_catquiz');
    }

    /**
     * Completed CAT attempts the finalisation stored no result for, carrying a value of the root scale - the attempt
     * 180 of test 20 (issue #150, re-audit 28.10.2026).
     *
     * @Given /^the following CAT attempts of "([^"]*)" ended without a stored result:$/
     *
     * @param string $idnumber
     * @param \Behat\Gherkin\Node\TableNode $table Columns: user, days ago, root ability.
     * @return void
     */
    public function the_following_cat_attempts_ended_without_a_stored_result(
        string $idnumber,
        \Behat\Gherkin\Node\TableNode $table
    ): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        static $hostattemptid = 970000;

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $test = $DB->get_record(
            'local_catquiz_tests',
            ['componentid' => $cm->instance, 'component' => 'mod_adaptivequiz'],
            '*',
            MUST_EXIST
        );
        $rootid = (int) $test->catscaleid;
        $contextid = (int) \local_catquiz\catscale::get_context_id($rootid);
        foreach ($table->getHash() as $row) {
            $userid = (int) $DB->get_field('user', 'id', ['username' => $row['user']], MUST_EXIST);
            $end = time() - (int) $row['days ago'] * DAYSECS;
            $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => $userid, 'scaleid' => $rootid, 'contextid' => $contextid, 'courseid' => $cm->course,
                'attemptid' => ++$hostattemptid, 'component' => 'mod_adaptivequiz', 'instanceid' => $cm->instance,
                'teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, 'status' => 0, 'starttime' => $end - 600,
                'endtime' => $end, 'personability_after_attempt' => (float) $row['root ability'],
                'json' => json_encode([
                    'personabilities' => [$rootid => (float) $row['root ability']],
                    'personabilities_abilities' => [],
                ]),
                'timecreated' => $end, 'timemodified' => $end,
            ]);
        }
    }

    /**
     * Attaches a CAT scale to another, empty CAT context - its items and parameters stay where they are, as after
     * a recalibration context was created and not calculated yet (issue #150, re-audit 27.10.2026).
     *
     * @Given /^the CAT scale "([^"]*)" is attached to the empty CAT context "([^"]*)"$/
     *
     * @param string $scalename
     * @param string $contextname
     * @return void
     */
    public function the_cat_scale_is_attached_to_the_empty_cat_context(string $scalename, string $contextname): void {
        global $DB;
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => $contextname, 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 60, 'endtimestamp' => time() + YEARSECS, 'timecreated' => time(),
            'timemodified' => time(), 'usermodified' => 0,
        ]);
        $DB->set_field('local_catquiz_catscales', 'contextid', $contextid, ['id' => $this->catquiz_scale_id($scalename)]);
        \cache_helper::purge_all();
    }

    /**
     * Records every chart the browser draws on the pages from now on (re-audit of issue #150, 26.10.2026).
     *
     * The recorder goes into the head of every page (additionalhtmlhead), before any script of the page: it sees
     * charts drawn when the page loads as well as those of a lazy region loaded later, the core way
     * (core/chart_builder) and local_catquiz/chart alike - both create the chart through
     * core/chart_base.prototype.create. For each chart it keeps the type, the number of labels and per series the
     * label and the number of values - no values. Errors of the page (error, unhandledrejection, console.error) are
     * kept with it.
     *
     * @Given /^the charts drawn in the browser are recorded$/
     *
     * @return void
     */
    public function the_charts_drawn_in_the_browser_are_recorded(): void {
        $script = <<<'JS'
<script>
(function() {
    var rec = window.localCatquizCharts = {charts: [], errors: []};
    var text = function(a) {
        return a && a.message ? a.message : String(a);
    };
    window.addEventListener('error', function(e) {
        rec.errors.push(text(e.error || e.message));
    });
    window.addEventListener('unhandledrejection', function(e) {
        rec.errors.push(text(e.reason));
    });
    var consoleerror = window.console.error;
    window.console.error = function() {
        rec.errors.push(Array.prototype.map.call(arguments, text).join(' '));
        return consoleerror.apply(window.console, arguments);
    };
    var hook = function() {
        if (typeof window.require !== 'function' || typeof window.require.defined !== 'function') {
            window.setTimeout(hook, 10);
            return;
        }
        window.require(['core/chart_base'], function(Base) {
            var create = Base.prototype.create;
            Base.prototype.create = function(Klass, data) {
                var entry = {
                    type: data && data.type,
                    labels: data && Array.isArray(data.labels) ? data.labels.length : null,
                    series: (data && Array.isArray(data.series) ? data.series : []).map(function(s) {
                        return {label: s && s.label, values: s && Array.isArray(s.values) ? s.values.length : null};
                    })
                };
                rec.charts.push(entry);
                try {
                    return create.call(this, Klass, data);
                } catch (e) {
                    entry.error = text(e);
                    throw e;
                }
            };
        });
    };
    hook();
})();
</script>
JS;
        set_config('additionalhtmlhead', (string) get_config('core', 'additionalhtmlhead') . $script);
    }

    /**
     * Checks the charts recorded on the current page: at least one, each complete - every series with as many values
     * as the chart has labels - and drawn without an error of the page.
     *
     * With the environment variable CATQUIZ_CHART_EVIDENCE set to a directory, the recording is written there as
     * JSON before the check (one file per check, with the page), so that a run of an old state shows what reached
     * the browser and what it threw.
     *
     * @Then /^the recorded charts were drawn without an error$/
     *
     * @return void
     */
    public function the_recorded_charts_were_drawn_without_an_error(): void {
        $json = $this->evaluate_script('return JSON.stringify(window.localCatquizCharts || null);');
        $recording = json_decode((string) $json, true);
        $dir = getenv('CATQUIZ_CHART_EVIDENCE');
        if ($dir && is_dir($dir)) {
            static $count = 0;
            $count++;
            $file = sprintf('%s/charts-%d-%02d.json', $dir, getmypid(), $count);
            $page = $this->getSession()->getCurrentUrl();
            file_put_contents($file, json_encode(['page' => $page, 'recording' => $recording], JSON_PRETTY_PRINT));
        }
        if (!is_array($recording)) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'Nothing was recorded on this page; is the step "the charts drawn in the browser are recorded" missing?',
                $this->getSession()
            );
        }
        if (empty($recording['charts'])) {
            throw new \Behat\Mink\Exception\ExpectationException('No chart was drawn on this page.', $this->getSession());
        }
        $problems = [];
        foreach ($recording['charts'] as $chart) {
            foreach ($chart['series'] as $series) {
                if ($series['values'] !== $chart['labels']) {
                    $problems[] = sprintf(
                        'Series "%s": %s values, %s labels',
                        $series['label'],
                        var_export($series['values'], true),
                        var_export($chart['labels'], true)
                    );
                }
            }
            if (!empty($chart['error'])) {
                $problems[] = 'Chart ' . $chart['type'] . ': ' . $chart['error'];
            }
        }
        foreach ($recording['errors'] as $error) {
            $problems[] = 'Page: ' . $error;
        }
        if ($problems) {
            throw new \Behat\Mink\Exception\ExpectationException(implode("\n", array_unique($problems)), $this->getSession());
        }
    }

    /**
     * The question attempt of a question in the last host attempt of a user (mod_adaptivequiz #16, #18).
     *
     * @param string $questionname
     * @param string $username
     * @return array [adaptivequiz_attempt record, question_attempt, slot]
     */
    private function question_attempt_in_last_attempt(string $questionname, string $username): array {
        global $DB;
        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $attempts = $DB->get_records('adaptivequiz_attempt', ['userid' => $userid], 'id DESC', '*', 0, 1);
        if (!$attempts) {
            throw new \Behat\Mink\Exception\ExpectationException("No attempt of $username", $this->getSession());
        }
        $attempt = reset($attempts);
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        foreach ($quba->get_slots() as $slot) {
            $qa = $quba->get_question_attempt($slot);
            if ($qa->get_question(false)->name === $questionname) {
                return [$attempt, $qa, (int) $slot];
            }
        }
        throw new \Behat\Mink\Exception\ExpectationException(
            "The last attempt of $username has no question \"$questionname\"",
            $this->getSession()
        );
    }

    /**
     * Requests the file embedded in a question of an attempt with the session of the browser - a real HTTP request
     * to pluginfile.php, the same URL the question shows - and checks status, type and content (mod_adaptivequiz
     * #16, #18).
     *
     * Delivered: HTTP 200, an image type, an image the browser can decode. Refused: no image - Moodle answers
     * with 404 or a login/error page.
     *
     * @Then /^the file of question "([^"]*)" in the last attempt of "([^"]*)" is (delivered to|refused to) me$/
     *
     * @param string $questionname
     * @param string $username
     * @param string $outcome
     * @return void
     */
    public function the_file_of_question_in_the_last_attempt_is(string $questionname, string $username, string $outcome): void {
        [, $qa] = $this->question_attempt_in_last_attempt($questionname, $username);
        $question = $qa->get_question(false);
        $text = $qa->rewrite_pluginfile_urls($question->questiontext, 'question', 'questiontext', $question->id);
        if (!preg_match('~src="([^"]*/pluginfile\.php/[^"]+)"~', $text, $m)) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Question \"$questionname\" has no embedded file",
                $this->getSession()
            );
        }
        $url = html_entity_decode($m[1], ENT_QUOTES);
        $script = 'return (function() {'
            . ' var x = new XMLHttpRequest(); x.open("GET", ' . json_encode($url) . ', false);'
            . ' x.overrideMimeType("text/plain; charset=x-user-defined"); x.send();'
            . ' var b = x.responseText || "";'
            . ' var png = b.length > 8 && (b.charCodeAt(1) & 0xff) === 0x50 && (b.charCodeAt(2) & 0xff) === 0x4e;'
            . ' var gif = b.substr(0, 3) === "GIF";'
            . ' var jpg = b.length > 2 && (b.charCodeAt(0) & 0xff) === 0xff && (b.charCodeAt(1) & 0xff) === 0xd8;'
            . ' return JSON.stringify({status: x.status, type: x.getResponseHeader("Content-Type"),'
            . '   url: x.responseURL, bytes: b.length, image: png || gif || jpg});'
            . '})();';
        $result = json_decode((string) $this->getSession()->evaluateScript($script), true);
        $delivered = $result['status'] === 200 && strpos((string) $result['type'], 'image/') === 0 && $result['image'];
        $report = sprintf(
            '%s: HTTP %d, %s, %d bytes, image: %s, final URL %s',
            $url,
            $result['status'],
            $result['type'],
            $result['bytes'],
            $result['image'] ? 'yes' : 'no',
            $result['url']
        );
        $this->record_network_evidence($report);
        if ($outcome === 'delivered to' && !$delivered) {
            throw new \Behat\Mink\Exception\ExpectationException("Not delivered - $report", $this->getSession());
        }
        if ($outcome === 'refused to' && $delivered) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Delivered although it must be refused - $report",
                $this->getSession()
            );
        }
    }

    /**
     * Calls the web service behind the question modal (local_catquiz_render_question_with_response) with the session
     * of the browser, for a question of the last attempt of a user, with slot and question attempt id as the result
     * page sends them (mod_adaptivequiz #16, #18).
     *
     * @Then /^the question "([^"]*)" in the last attempt of "([^"]*)" is (rendered for|refused to) me$/
     *
     * @param string $questionname
     * @param string $username
     * @param string $outcome
     * @return void
     */
    public function the_question_in_the_last_attempt_is(string $questionname, string $username, string $outcome): void {
        [$attempt, $qa, $slot] = $this->question_attempt_in_last_attempt($questionname, $username);
        $args = ['slot' => $slot, 'attemptid' => (int) $attempt->id, 'questionattemptid' => (int) $qa->get_database_id()];
        $call = json_encode([['index' => 0, 'methodname' => 'local_catquiz_render_question_with_response', 'args' => $args]]);
        $script = 'return (function() {'
            . ' var x = new XMLHttpRequest();'
            . ' x.open("POST", M.cfg.wwwroot + "/lib/ajax/service.php?sesskey=" + M.cfg.sesskey, false);'
            . ' x.setRequestHeader("Content-Type", "application/json"); x.send(' . json_encode($call) . ');'
            . ' return JSON.stringify({status: x.status, body: x.responseText});'
            . '})();';
        $result = json_decode((string) $this->getSession()->evaluateScript($script), true);
        $body = json_decode((string) $result['body'], true);
        $answer = is_array($body) && isset($body[0]) ? $body[0] : [];
        $rendered = empty($answer['error']) && !empty($answer['data']['questionhtml']);
        $report = sprintf(
            'render_question_with_response %s: HTTP %d, %s',
            json_encode($args),
            $result['status'],
            $rendered
                ? 'question HTML, ' . strlen($answer['data']['questionhtml']) . ' characters'
                : 'error ' . ($answer['exception']['errorcode'] ?? $answer['exception']['message']
                    ?? substr((string) $result['body'], 0, 120))
        );
        $this->record_network_evidence($report);
        if ($outcome === 'rendered for' && !$rendered) {
            throw new \Behat\Mink\Exception\ExpectationException("Not rendered - $report", $this->getSession());
        }
        if ($outcome === 'refused to' && $rendered) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Rendered although it must be refused - $report",
                $this->getSession()
            );
        }
        $leaked = strpos($result['body'], 'Text of resource question') !== false;
        if ($outcome === 'refused to' && !$rendered && $leaked) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The refusal contains the question - $report",
                $this->getSession()
            );
        }
    }

    /**
     * Appends a line of evidence to $CATQUIZ_NETWORK_EVIDENCE, if that file is set (evidence runs only).
     *
     * @param string $line
     * @return void
     */
    private function record_network_evidence(string $line): void {
        $file = getenv('CATQUIZ_NETWORK_EVIDENCE');
        if ($file) {
            $who = $this->getSession()->evaluateScript(
                'return (document.querySelector(".usertext, .logininfo a") || {}).textContent || "";'
            );
            file_put_contents($file, date('H:i:s') . ' [' . trim((string) $who) . '] ' . $line . "\n", FILE_APPEND);
        }
    }

    /**
     * Checks every attempt of a user at an activity, oldest first (mod_adaptivequiz #5).
     *
     * Columns: state (inprogress, complete), stop reason (the stored code, - for none), finished (yes when
     * timefinished is set), questions (slots of the usage), answered (slots with a graded answer stored in the usage),
     * duplicates (yes when a question is in two slots),
     * cat result (ended, open or none: the record of the CAT model and whether it has an end).
     *
     * @Then /^the attempts of "([^"]*)" at "([^"]*)" are:$/
     *
     * @param string $username
     * @param string $idnumber
     * @param \Behat\Gherkin\Node\TableNode $table
     * @return void
     */
    public function the_attempts_of_at_are(string $username, string $idnumber, \Behat\Gherkin\Node\TableNode $table): void {
        global $DB;
        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $attempts = array_values($DB->get_records(
            'adaptivequiz_attempt',
            ['instance' => $cm->instance, 'userid' => $userid],
            'id ASC'
        ));
        $actual = [];
        foreach ($attempts as $attempt) {
            $questions = [];
            $answered = 0;
            if (!empty($attempt->uniqueid) && $DB->record_exists('question_usages', ['id' => $attempt->uniqueid])) {
                $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
                foreach ($quba->get_slots() as $slot) {
                    $questions[] = (int) $quba->get_question($slot, false)->id;
                    if ($quba->get_question_state($slot)->is_graded()) {
                        $answered++;
                    }
                }
            }
            [$insql, $inparams] = $DB->get_in_or_equal(\local_catquiz\catquiz::component_names('mod_adaptivequiz'));
            $cat = $DB->get_records_select(
                'local_catquiz_attempts',
                "attemptid = ? AND component $insql",
                array_merge([$attempt->id], $inparams)
            );
            $catstate = 'none';
            if ($cat) {
                $catstate = 'open';
                foreach ($cat as $record) {
                    if (!empty($record->endtime)) {
                        $catstate = 'ended';
                    }
                }
            }
            $actual[] = [
                'state' => (string) $attempt->attemptstate,
                'stop reason' => $attempt->stopcode === null || $attempt->stopcode === '' ? '-' : (string) $attempt->stopcode,
                'finished' => empty($attempt->timefinished) ? 'no' : 'yes',
                'questions' => (string) count($questions),
                'answered' => (string) $answered,
                'duplicates' => count($questions) !== count(array_unique($questions)) ? 'yes' : 'no',
                'cat result' => $catstate,
            ];
        }
        $expected = $table->getHash();
        $columns = array_keys($expected[0] ?? []);
        $shown = array_map(fn($row) => array_intersect_key($row, array_flip($columns)), $actual);
        if ($shown != $expected) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Attempts of $username:\n" . json_encode($shown, JSON_PRETTY_PRINT) . "\nexpected:\n"
                    . json_encode($expected, JSON_PRETTY_PRINT),
                $this->getSession()
            );
        }
        $this->record_network_evidence("attempts of $username at $idnumber: " . json_encode($actual));
    }

    /**
     * Sends the choice for an open attempt as the form of the activity page does - a POST to attempt.php with
     * resume, attemptid and sesskey - from the browser, with its session, once or several times in a row (a double
     * click), optionally with a wrong sesskey (mod_adaptivequiz #5).
     *
     * @When /^I send the resume action "([^"]*)" for the open attempt of "([^"]*)" at "([^"]*)"( twice)?( with a wrong sesskey)?$/
     *
     * @param string $action
     * @param string $username Whose open attempt is named in the request.
     * @param string $idnumber
     * @param string $twice
     * @param string $wrongsesskey
     * @return void
     */
    public function i_send_the_resume_action(
        string $action,
        string $username,
        string $idnumber,
        string $twice = '',
        string $wrongsesskey = ''
    ): void {
        global $DB;
        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $open = $DB->get_records(
            'adaptivequiz_attempt',
            ['instance' => $cm->instance, 'userid' => $userid, 'attemptstate' => 'inprogress'],
            'id DESC',
            'id',
            0,
            1
        );
        if (!$open) {
            throw new \Behat\Mink\Exception\ExpectationException("No open attempt of $username", $this->getSession());
        }
        $attemptid = (int) reset($open)->id;
        $url = (new \moodle_url('/mod/adaptivequiz/attempt.php', ['cmid' => $cm->id]))->out(false);
        $script = 'return (function() {'
            . ' var key = ' . ($wrongsesskey ? '"wrongsesskey"' : '(window.M && M.cfg ? M.cfg.sesskey : "")') . ';'
            . ' var body = "resume=" + encodeURIComponent(' . json_encode($action) . ') + "&attemptid=' . $attemptid
            . '&sesskey=" + encodeURIComponent(key);'
            . ' var out = [];'
            . ' for (var i = 0; i < ' . ($twice ? 2 : 1) . '; i++) {'
            . '   var x = new XMLHttpRequest(); x.open("POST", ' . json_encode($url) . ', false);'
            . '   x.setRequestHeader("Content-Type", "application/x-www-form-urlencoded"); x.send(body);'
            . '   out.push({status: x.status, url: x.responseURL});'
            . ' }'
            . ' return JSON.stringify(out);'
            . '})();';
        $result = (string) $this->getSession()->evaluateScript($script);
        $this->record_network_evidence("resume $action for attempt $attemptid of $username" . ($twice ? ' twice' : '')
            . ($wrongsesskey ? ' with a wrong sesskey' : '') . ': ' . $result);
    }

    /**
     * Requests the attempt page of an activity several times at once from the browser, as parallel tabs do
     * (mod_adaptivequiz #5).
     *
     * @When /^I request the attempt page of "([^"]*)" (\d+) times at once$/
     *
     * @param string $idnumber
     * @param int $count
     * @return void
     */
    public function i_request_the_attempt_page_times_at_once(string $idnumber, int $count): void {
        global $DB;
        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $url = (new \moodle_url('/mod/adaptivequiz/attempt.php', ['cmid' => $cm->id]))->out(false);
        $this->getSession()->executeScript('(function() {'
            . ' window.localCatquizParallel = [];'
            . ' for (var i = 0; i < ' . $count . '; i++) {'
            . '   var x = new XMLHttpRequest(); x.open("GET", ' . json_encode($url) . ', true);'
            . '   x.onloadend = function() { window.localCatquizParallel.push(this.status); };'
            . '   x.send();'
            . ' }'
            . '})();');
        $this->spin(function () use ($count) {
            $done = (int) $this->getSession()->evaluateScript('return (window.localCatquizParallel || []).length;');
            if ($done < $count) {
                throw new \Behat\Mink\Exception\ExpectationException("$done of $count answered", $this->getSession());
            }
            return true;
        }, [], behat_base::get_extended_timeout() * 2);
        $statuses = (string) $this->getSession()->evaluateScript('return JSON.stringify(window.localCatquizParallel);');
        $this->record_network_evidence("attempt page of $idnumber $count times at once: $statuses");
    }

    /**
     * The last host attempt of a user at an activity and its course module (mod_adaptivequiz #22).
     *
     * @param string $username
     * @param string $idnumber
     * @return array [attempt record, course module record]
     */
    private function last_host_attempt_at(string $username, string $idnumber): array {
        global $DB;
        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $attempts = $DB->get_records(
            'adaptivequiz_attempt',
            ['instance' => $cm->instance, 'userid' => $userid],
            'id DESC',
            '*',
            0,
            1
        );
        if (!$attempts) {
            throw new \Behat\Mink\Exception\ExpectationException("No attempt of $username at $idnumber", $this->getSession());
        }
        return [reset($attempts), $cm];
    }

    /**
     * Requests a page with the session of the browser and returns status, final URL and body.
     *
     * @param string $url
     * @return array
     */
    private function request_in_browser(string $url): array {
        $script = 'return (function() { var x = new XMLHttpRequest(); x.open("GET", ' . json_encode($url) . ', false);'
            . ' x.send(); return JSON.stringify({status: x.status, url: x.responseURL, body: x.responseText}); })();';
        return json_decode((string) $this->getSession()->evaluateScript($script), true);
    }

    /** @var array|null The last page requested with request_in_browser() by a step (mod_adaptivequiz #22). */
    private ?array $requested = null;

    /**
     * Requests the result page of a user's last attempt at an activity with the session of the browser
     * (mod_adaptivequiz #22). Parameters of attemptfinished.php: cmid, id (the instance), uattid (the usage).
     *
     * @When /^I request the result page of the last attempt of "([^"]*)" at "([^"]*)"$/
     *
     * @param string $username
     * @param string $idnumber
     * @return void
     */
    public function i_request_the_result_page_of_the_last_attempt(string $username, string $idnumber): void {
        [$attempt, $cm] = $this->last_host_attempt_at($username, $idnumber);
        $this->request_result_page(['cmid' => $cm->id, 'id' => $cm->instance, 'uattid' => $attempt->uniqueid]);
    }

    /**
     * Requests the result page again with one parameter taken from another attempt - a manipulated link
     * (mod_adaptivequiz #22).
     *
     * @When /^I request it again with the (cmid|id|uattid) of the last attempt of "([^"]*)" at "([^"]*)"$/
     *
     * @param string $param
     * @param string $username
     * @param string $idnumber
     * @return void
     */
    public function i_request_it_again_with_the_param_of(string $param, string $username, string $idnumber): void {
        [$attempt, $cm] = $this->last_host_attempt_at($username, $idnumber);
        $params = $this->requested['params'];
        $params[$param] = ['cmid' => $cm->id, 'id' => $cm->instance, 'uattid' => $attempt->uniqueid][$param];
        $this->request_result_page($params);
    }

    /**
     * Requests the CATquiz feedback page of a user's last attempt at an activity - the page teachers open from
     * the reports (mod_adaptivequiz #22).
     *
     * @When /^I request the CAT feedback page of the last attempt of "([^"]*)" at "([^"]*)"$/
     *
     * @param string $username
     * @param string $idnumber
     * @return void
     */
    public function i_request_the_cat_feedback_page_of_the_last_attempt(string $username, string $idnumber): void {
        global $DB;
        [$attempt] = $this->last_host_attempt_at($username, $idnumber);
        [$insql, $inparams] = $DB->get_in_or_equal(\local_catquiz\catquiz::component_names('mod_adaptivequiz'));
        $contextid = $DB->get_field_select(
            'local_catquiz_attempts',
            'contextid',
            "attemptid = ? AND component $insql",
            array_merge([$attempt->id], $inparams),
            MUST_EXIST
        );
        $params = ['attemptid' => $attempt->id, 'contextid' => $contextid];
        $url = (new \moodle_url('/local/catquiz/show_attemptfeedback.php', $params))->out(false);
        $this->requested = ['params' => $params] + $this->request_in_browser($url);
        $this->record_network_evidence(sprintf('CAT feedback page %s: HTTP %d', $url, $this->requested['status']));
    }

    /**
     * Checks the page requested last: a text it must show ("nothing" for a refused page) and one it must not.
     *
     * @Then /^the requested page shows "([^"]*)" and not "([^"]*)"$/
     *
     * @param string $expected
     * @param string $forbidden
     * @return void
     */
    public function the_requested_page_shows_and_not(string $expected, string $forbidden): void {
        $body = (string) ($this->requested['body'] ?? '');
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<(script|style)\b.*?</\1>#si', '', $body))));
        $report = 'HTTP ' . ($this->requested['status'] ?? '?') . ', ' . ($this->requested['url'] ?? '')
            . ', page: ' . \core_text::substr($text, 0, 600);
        if ($expected !== 'nothing' && strpos($body, $expected) === false) {
            throw new \Behat\Mink\Exception\ExpectationException("\"$expected\" missing - $report", $this->getSession());
        }
        if (strpos($body, $forbidden) !== false) {
            throw new \Behat\Mink\Exception\ExpectationException("\"$forbidden\" shown - $report", $this->getSession());
        }
    }

    /**
     * Requests attemptfinished.php with the given parameters and keeps the answer for the check.
     *
     * @param array $params
     * @return void
     */
    private function request_result_page(array $params): void {
        $url = (new \moodle_url('/mod/adaptivequiz/attemptfinished.php', $params))->out(false);
        $this->requested = ['params' => $params] + $this->request_in_browser($url);
        $this->record_network_evidence(sprintf(
            'result page %s: HTTP %d, final URL %s',
            $url,
            $this->requested['status'],
            $this->requested['url']
        ));
    }

    /**
     * Moves the ids of the next CAT attempts away from those of the host (issues #95, #101): local_catquiz_attempts.id
     * then differs from adaptivequiz_attempt.id, and a mix-up of the two namespaces can no longer go unnoticed.
     *
     * @Given /^the next CAT attempt ids start at (\d+)$/
     *
     * @param int $start
     * @return void
     */
    public function the_next_cat_attempt_ids_start_at(int $start): void {
        global $DB;
        $id = $DB->insert_record_raw('local_catquiz_attempts', [
            'id' => $start - 1, 'userid' => 0, 'timecreated' => 0, 'timemodified' => 0,
        ], true, false, true);
        $DB->get_manager()->reset_sequence(new \xmldb_table('local_catquiz_attempts'));
        $DB->delete_records('local_catquiz_attempts', ['id' => $id]);
    }

    /**
     * The CAT attempt of a user's last attempt at an activity, with the host attempt.
     *
     * @param string $username
     * @param string $idnumber
     * @return array [CAT attempt record, host attempt record]
     */
    private function cat_attempt_of_last_attempt(string $username, string $idnumber): array {
        global $DB;
        [$hostattempt] = $this->last_host_attempt_at($username, $idnumber);
        [$insql, $inparams] = $DB->get_in_or_equal(\local_catquiz\catquiz::component_names('mod_adaptivequiz'));
        $catattempt = $DB->get_record_select(
            'local_catquiz_attempts',
            "attemptid = ? AND component $insql",
            array_merge([$hostattempt->id], $inparams),
            '*',
            MUST_EXIST
        );
        return [$catattempt, $hostattempt];
    }

    /**
     * Checks the id namespaces of a user's last attempt (issues #95, #101): the CAT attempt has its own id, holds the
     * host id in its attemptid, and the progress, the stored results and the history of the questions refer to the
     * CAT attempt - nothing is filed under the id of the host attempt.
     *
     * @Then /^the CAT attempt of the last attempt of "([^"]*)" at "([^"]*)" has its own id and everything refers to it$/
     *
     * @param string $username
     * @param string $idnumber
     * @return void
     */
    public function the_cat_attempt_has_its_own_id_and_everything_refers_to_it(string $username, string $idnumber): void {
        global $DB;
        [$catattempt, $hostattempt] = $this->cat_attempt_of_last_attempt($username, $idnumber);
        $problems = [];
        if ((int) $catattempt->id === (int) $hostattempt->id) {
            $problems[] = "the ids are equal ({$catattempt->id}) - the step 'the next CAT attempt ids start at' is missing";
        }
        if ((int) $catattempt->userid !== (int) $hostattempt->userid) {
            $problems[] = 'the CAT attempt belongs to another user';
        }
        $progress = $DB->get_records('local_catquiz_progress', ['attemptid' => $catattempt->id]);
        if (count($progress) !== 1) {
            $problems[] = count($progress) . ' progress rows under the CAT attempt id';
        }
        foreach ($progress as $row) {
            if ((int) $row->userid !== (int) $hostattempt->userid) {
                $problems[] = 'the progress belongs to another user';
            }
        }
        if ($DB->record_exists('local_catquiz_progress', ['attemptid' => $hostattempt->id])) {
            $problems[] = "a progress row under the host attempt id {$hostattempt->id}";
        }
        if (!empty($hostattempt->timefinished)) {
            if (!$DB->record_exists('local_catquiz_personparams', ['attemptid' => $catattempt->id, 'resultsource' => 'current'])) {
                $problems[] = 'no stored results under the CAT attempt id';
            }
        }
        if ($problems) {
            throw new \Behat\Mink\Exception\ExpectationException(sprintf(
                'CAT attempt %d, host attempt %d: %s',
                $catattempt->id,
                $hostattempt->id,
                implode('; ', $problems)
            ), $this->getSession());
        }
        $this->record_network_evidence(sprintf(
            'ids of %s at %s: CAT attempt %d, host attempt %d, progress and results under the CAT attempt',
            $username,
            $idnumber,
            $catattempt->id,
            $hostattempt->id
        ));
    }

    /**
     * Gives the saved state of a user's open attempt to another user - a progress row whose numeric id meets an
     * attempt of somebody else, the collision of issue #96. The cache is cleared: the row is read from the database.
     *
     * @Given /^the saved state of the last attempt of "([^"]*)" at "([^"]*)" belongs to "([^"]*)"$/
     *
     * @param string $username
     * @param string $idnumber
     * @param string $other
     * @return void
     */
    public function the_saved_state_belongs_to(string $username, string $idnumber, string $other): void {
        global $DB;
        [$catattempt] = $this->cat_attempt_of_last_attempt($username, $idnumber);
        $otherid = (int) $DB->get_field('user', 'id', ['username' => $other], MUST_EXIST);
        $DB->set_field('local_catquiz_progress', 'userid', $otherid, ['attemptid' => $catattempt->id]);
        \cache_helper::purge_all();
    }

    /**
     * Checks that the saved state was neither taken over nor overwritten (issue #96): it still belongs to the given
     * user and is unchanged.
     *
     * @Then /^the saved state of the last attempt of "([^"]*)" at "([^"]*)" still belongs to "([^"]*)" and is unchanged$/
     *
     * @param string $username
     * @param string $idnumber
     * @param string $other
     * @return void
     */
    public function the_saved_state_still_belongs_to(string $username, string $idnumber, string $other): void {
        global $DB;
        [$catattempt] = $this->cat_attempt_of_last_attempt($username, $idnumber);
        $otherid = (int) $DB->get_field('user', 'id', ['username' => $other], MUST_EXIST);
        $rows = $DB->get_records('local_catquiz_progress', ['attemptid' => $catattempt->id]);
        $row = reset($rows);
        if (count($rows) !== 1 || (int) $row->userid !== $otherid) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The saved state was taken over or replaced: ' . count($rows) . ' rows',
                $this->getSession()
            );
        }
        if (isset($this->savedstate) && $this->savedstate !== [(int) $row->id, (string) $row->json]) {
            throw new \Behat\Mink\Exception\ExpectationException('The saved state was overwritten.', $this->getSession());
        }
    }

    /** @var array|null Id and content of a saved state, remembered by a step (issue #96). */
    private ?array $savedstate = null;

    /**
     * Remembers id and content of the saved state of a user's last attempt, to check later that it was not
     * overwritten (issue #96).
     *
     * @Given /^I remember the saved state of the last attempt of "([^"]*)" at "([^"]*)"$/
     *
     * @param string $username
     * @param string $idnumber
     * @return void
     */
    public function i_remember_the_saved_state(string $username, string $idnumber): void {
        global $DB;
        [$catattempt] = $this->cat_attempt_of_last_attempt($username, $idnumber);
        $row = $DB->get_record('local_catquiz_progress', ['attemptid' => $catattempt->id], '*', MUST_EXIST);
        $this->savedstate = [(int) $row->id, (string) $row->json];
    }

    /**
     * Submits an answer in the browser and checks that the next page refuses with the given text (issue #96).
     *
     * A refusal is a Moodle error page, which the hooks would report as a failure of the step; the page is read here
     * and then left for the course page, so that only the text decides.
     *
     * @When /^I answer "([^"]*)" to question (\d+) and the page refuses with "([^"]*)"$/
     *
     * @param string $answer
     * @param int $number
     * @param string $text
     * @return void
     */
    public function i_answer_and_the_page_refuses_with(string $answer, int $number, string $text): void {
        $this->execute('behat_general::i_click_on_in_the', [$answer, 'text', "Question $number", 'question']);
        // Pressed directly: execute() would stop at the error page itself.
        // The old page is "complete" too: a marker in it shows when the next page has replaced it.
        $this->getSession()->executeScript('window.catquizoldpage = true;');
        $this->getSession()->getPage()->pressButton('Submit answer');
        $replaced = $this->getSession()->wait(
            behat_base::get_extended_timeout() * 1000,
            'typeof window.catquizoldpage === "undefined" && document.readyState === "complete"'
        );
        if (!$replaced) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "No new page after the answer to question $number",
                $this->getSession()
            );
        }
        $page = $this->getSession()->getPage()->getText();
        $url = $this->getSession()->getCurrentUrl();
        $this->record_network_evidence(sprintf(
            'answer to question %d at %s: %s',
            $number,
            $url,
            \core_text::substr(trim(preg_replace('/\s+/', ' ', $page)), 0, 300)
        ));
        $this->getSession()->visit($this->locate_path('/my/courses.php'));
        if (strpos($page, $text) === false) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "\"$text\" not shown after the answer: " . \core_text::substr(trim(preg_replace('/\s+/', ' ', $page)), 0, 600),
                $this->getSession()
            );
        }
    }

    /**
     * Files N earlier participants with the same valid result on a test's scale (issue #106): a prior without spread.
     *
     * @Given /^(\d+) earlier participants have the valid CAT result "([^"]*)" at "([^"]*)"$/
     *
     * @param int $count
     * @param string $ability
     * @param string $idnumber
     * @return void
     */
    public function earlier_participants_have_the_same_result(int $count, string $ability, string $idnumber): void {
        $rows = [['user', 'days ago', 'ability', 'valid']];
        $generator = \testing_util::get_data_generator();
        for ($i = 1; $i <= $count; $i++) {
            $user = $generator->create_user(['username' => 'earlier' . $i]);
            $rows[] = [$user->username, (string) (1 + $i % 20), $ability, 'yes'];
        }
        $this->the_following_cat_results_exist($idnumber, new \Behat\Gherkin\Node\TableNode($rows));
    }

    /**
     * Checks that a user's last attempt stored only finite numbers (issue #106): the results of every scale, the
     * abilities and standard errors in the CAT attempt and in its saved state. No NaN, no INF, also not as text.
     *
     * @Then /^the last attempt of "([^"]*)" at "([^"]*)" stored only finite abilities$/
     *
     * @param string $username
     * @param string $idnumber
     * @return void
     */
    public function the_last_attempt_stored_only_finite_abilities(string $username, string $idnumber): void {
        global $DB;
        [$catattempt] = $this->cat_attempt_of_last_attempt($username, $idnumber);
        $problems = [];
        $params = $DB->get_records('local_catquiz_personparams', ['attemptid' => $catattempt->id]);
        if (!$params) {
            $problems[] = "no stored result for CAT attempt {$catattempt->id} (status {$catattempt->status}); results of the "
                . 'person: ' . json_encode($DB->get_records_menu(
                    'local_catquiz_personparams',
                    ['userid' => $catattempt->userid],
                    '',
                    'id, attemptid'
                ));
        }
        foreach ($params as $param) {
            foreach (['ability', 'standarderror'] as $field) {
                if ($param->$field !== null && !is_finite((float) $param->$field)) {
                    $problems[] = "personparams {$param->catscaleid} $field = {$param->$field}";
                }
            }
        }
        if (!is_finite((float) $catattempt->personability_after_attempt)) {
            $problems[] = 'personability_after_attempt = ' . $catattempt->personability_after_attempt;
        }
        $texts = ['attempt' => (string) $catattempt->json,
            'progress' => (string) $DB->get_field('local_catquiz_progress', 'json', ['attemptid' => $catattempt->id])];
        foreach ($texts as $where => $text) {
            if (preg_match('/\b(NAN|-?INF)\b/i', $text, $m)) {
                $problems[] = "$where holds $m[0]";
            }
            $walk = function ($value, string $path) use (&$walk, &$problems, $where): void {
                if (is_array($value) || is_object($value)) {
                    foreach ((array) $value as $key => $inner) {
                        $walk($inner, "$path.$key");
                    }
                } else if (is_float($value) && !is_finite($value)) {
                    $problems[] = "$where$path";
                }
            };
            $walk(json_decode($text), '');
        }
        if ($problems) {
            throw new \Behat\Mink\Exception\ExpectationException(implode('; ', $problems), $this->getSession());
        }
    }

    /**
     * A numeric fallback was reported as an event (issue #106), with its reason.
     *
     * @Then /^a numeric fallback "([^"]*)" was logged$/
     *
     * @param string $reason
     * @return void
     */
    public function a_numeric_fallback_was_logged(string $reason): void {
        global $DB;
        $events = $DB->get_records('logstore_standard_log', ['eventname' => '\\local_catquiz\\event\\numeric_fallback_applied']);
        foreach ($events as $event) {
            $other = json_decode((string) $event->other, true);
            if (($other['reason'] ?? null) === $reason) {
                return;
            }
        }
        throw new \Behat\Mink\Exception\ExpectationException(
            sprintf('No numeric fallback "%s" logged (%d others).', $reason, count($events)),
            $this->getSession()
        );
    }

    /**
     * Answers the questions of the open attempt one after the other until the attempt ends (issue #106) - as many as
     * the CAT model asks, at most a given number. Each answer is chosen by its text and sent with "Submit answer".
     *
     * @When /^I answer "([^"]*)" to every question until the attempt ends, at most (\d+) times$/
     *
     * @param string $answer
     * @param int $max
     * @return void
     */
    public function i_answer_every_question_until_the_attempt_ends(string $answer, int $max): void {
        for ($i = 0; $i < $max; $i++) {
            if (!$this->answer_the_question_on_the_screen($answer, $i + 1)) {
                return;
            }
        }
        if ($this->getSession()->getPage()->find('css', 'form#responseform') !== null) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The attempt did not end after $max answers.",
                $this->getSession()
            );
        }
    }

    /**
     * Checks the scale stored in the CAT settings of an activity (issue #123): a reload of the form stores nothing,
     * "Save and display" stores the choice.
     *
     * @Then /^the stored CAT settings of "([^"]*)" use the CAT scale "([^"]*)"$/
     *
     * @param string $idnumber
     * @param string $scalename
     * @return void
     */
    public function the_stored_cat_settings_use_the_cat_scale(string $idnumber, string $scalename): void {
        global $DB;
        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $test = $DB->get_record(
            'local_catquiz_tests',
            ['componentid' => $cm->instance, 'component' => 'mod_adaptivequiz'],
            '*',
            MUST_EXIST
        );
        $stored = (string) $DB->get_field('local_catquiz_catscales', 'name', ['id' => $test->catscaleid]);
        $json = json_decode((string) $test->json, true) ?: [];
        $injson = (string) $DB->get_field('local_catquiz_catscales', 'name', ['id' => (int) ($json['catquiz_catscales'] ?? 0)]);
        if ($stored !== $scalename || $injson !== $scalename) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The stored settings of \"$idnumber\" use \"$stored\" (settings: \"$injson\"), not \"$scalename\".",
                $this->getSession()
            );
        }
    }

    /**
     * Checks whether a subscale is switched on in the stored CAT settings of an activity (issue #123).
     *
     * @Then /^the stored CAT settings of "([^"]*)" have the subscale "([^"]*)" (on|off)$/
     *
     * @param string $idnumber
     * @param string $scalename
     * @param string $state
     * @return void
     */
    public function the_stored_cat_settings_have_the_subscale(string $idnumber, string $scalename, string $state): void {
        global $DB;
        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $json = json_decode((string) $DB->get_field(
            'local_catquiz_tests',
            'json',
            ['componentid' => $cm->instance, 'component' => 'mod_adaptivequiz'],
            MUST_EXIST
        ), true) ?: [];
        $scaleid = (int) $DB->get_field('local_catquiz_catscales', 'id', ['name' => $scalename], MUST_EXIST);
        $value = $json['catquiz_subscalecheckbox_' . $scaleid] ?? null;
        $on = !empty($value);
        if ($on !== ($state === 'on')) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The subscale \"$scalename\" is " . ($on ? 'on' : 'off') . " in the stored settings of \"$idnumber\" ("
                    . json_encode($value) . ').',
                $this->getSession()
            );
        }
    }

    /**
     * Answers the open attempt with two answers in turn until it ends (issue #131): a mixed result, so that the
     * scales can be measured validly.
     *
     * @When /^I answer "([^"]*)" and "([^"]*)" in turn until the attempt ends, at most (\d+) times$/
     *
     * @param string $first
     * @param string $second
     * @param int $max
     * @return void
     */
    public function i_answer_in_turn_until_the_attempt_ends(string $first, string $second, int $max): void {
        for ($i = 0; $i < $max; $i++) {
            if (!$this->answer_the_question_on_the_screen($i % 2 === 0 ? $first : $second, $i + 1)) {
                return;
            }
        }
        if ($this->getSession()->getPage()->find('css', 'form#responseform') !== null) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The attempt did not end after $max answers.",
                $this->getSession()
            );
        }
    }

    /**
     * Checks the routing of an attempt against its stored results, not against the routing code (issue #131).
     *
     * The groups "<prefix> <scale>" the person is in must be exactly the scales the result routes: an invalid
     * attempt routes nobody; a strategy that selects one scale (CAT, lowest gap, greatest strength) routes its valid
     * primary scale; the others every scale measured validly in this attempt and reported - stored as valid, or with
     * no reason but "not primary". The course is joined if and only if a group is.
     *
     * @Then /^"([^"]*)" is routed by the stored results of the last attempt at "([^"]*)" into "([^"]*)" and "([^"]*)" groups$/
     *
     * @param string $username
     * @param string $idnumber
     * @param string $courseshortname
     * @param string $prefix
     * @return void
     */
    public function the_routing_follows_the_stored_results(
        string $username,
        string $idnumber,
        string $courseshortname,
        string $prefix
    ): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        [$catattempt, $hostattempt] = $this->cat_attempt_of_last_attempt($username, $idnumber);
        $userid = (int) $hostattempt->userid;
        $rows = $DB->get_records('local_catquiz_personparams', ['attemptid' => $catattempt->id, 'resultsource' => 'current']);
        $single = in_array(
            (int) $catattempt->teststrategy,
            [LOCAL_CATQUIZ_STRATEGY_FASTEST, LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB],
            true
        );
        $expected = [];
        $stored = [];
        foreach ($rows as $row) {
            $name = (string) $DB->get_field('local_catquiz_catscales', 'name', ['id' => $row->catscaleid]);
            $stored[] = "$name:" . ((int) $row->isvalid) . ':' . (string) $row->validationstatus;
            $reasons = array_filter(array_map('trim', explode(',', (string) $row->validationstatus)));
            if ((int) $row->isvalid === 1 || (!$single && $reasons === ['not_primary'])) {
                $expected[] = $name;
            }
        }
        if (!(int) $hostattempt->resultvalid) {
            $expected = [];
        }
        // Only a group that exists can be joined; the course is joined for every routed scale all the same.
        $routes = $expected;
        $existing = $DB->get_fieldset_select('groups', 'name', $DB->sql_like('name', '?'), [$DB->sql_like_escape($prefix) . ' %']);
        $expected = array_values(array_intersect(
            $expected,
            array_map(fn($name) => substr($name, strlen($prefix) + 1), $existing)
        ));
        $actual = [];
        $groups = $DB->get_records_sql(
            "SELECT g.id, g.name FROM {groups} g JOIN {groups_members} gm ON gm.groupid = g.id
              WHERE gm.userid = ? AND " . $DB->sql_like('g.name', '?'),
            [$userid, $DB->sql_like_escape($prefix) . ' %']
        );
        foreach ($groups as $group) {
            $actual[] = substr($group->name, strlen($prefix) + 1);
        }
        sort($expected);
        sort($actual);
        $course = $DB->get_record('course', ['shortname' => $courseshortname], 'id', MUST_EXIST);
        $enrolled = is_enrolled(\context_course::instance($course->id), $userid);
        if ($expected !== $actual || $enrolled !== ($routes !== [])) {
            throw new \Behat\Mink\Exception\ExpectationException(sprintf(
                'Routed into %s, enrolled in %s: %s; the stored results route %s (strategy %d, result %s; rows %s).',
                json_encode($actual),
                $courseshortname,
                $enrolled ? 'yes' : 'no',
                json_encode($expected),
                $catattempt->teststrategy,
                $hostattempt->resultstatus,
                implode(' ', $stored)
            ), $this->getSession());
        }
    }

    /**
     * Ends the routing of an attempt a second time, as a repeated finalisation or a retried task would (issue #131).
     *
     * @When /^the last attempt of "([^"]*)" at "([^"]*)" is finalised and routed again$/
     *
     * @param string $username
     * @param string $idnumber
     * @return void
     */
    public function the_last_attempt_is_finalised_and_routed_again(string $username, string $idnumber): void {
        [, $hostattempt] = $this->cat_attempt_of_last_attempt($username, $idnumber);
        \core\session\manager::set_user(get_admin());
        \local_catquiz\local\attempt\attempt_finalizer::finalize((int) $hostattempt->id, time(), 'repeated');
        \local_catquiz\local\attempt\attempt_enrolment::enrol((int) $hostattempt->id);
    }

    /**
     * A person was enrolled into a course by the routing at most once (issue #131): one enrolment, one event.
     *
     * @Then /^"([^"]*)" was routed into "([^"]*)" at most once$/
     *
     * @param string $username
     * @param string $courseshortname
     * @return void
     */
    public function was_routed_at_most_once(string $username, string $courseshortname): void {
        global $DB;
        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $courseshortname], MUST_EXIST);
        $enrolments = $DB->count_records_sql(
            'SELECT COUNT(1) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = ? AND e.courseid = ?',
            [$userid, $courseid]
        );
        $fullname = (string) $DB->get_field('course', 'fullname', ['id' => $courseid]);
        $events = 0;
        foreach (
            $DB->get_records(
                'logstore_standard_log',
                ['eventname' => '\\local_catquiz\\event\\usertocourse_enroled', 'objectid' => $userid]
            ) as $event
        ) {
            $other = json_decode((string) $event->other, true) ?: [];
            $events += (($other['coursename'] ?? null) === $fullname) ? 1 : 0;
        }
        if ($enrolments > 1 || $events > 1) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "$username: $enrolments enrolments and $events enrolment events in $courseshortname.",
                $this->getSession()
            );
        }
    }

    /**
     * Answers the question on the screen with the answer of that text and waits for the next page (issues #106, #131).
     *
     * Waits until the question form shows another slot or is gone, so that the next step never reads the page that was
     * just left (a stale element).
     *
     * @param string $answer
     * @param int $number The number of the answer in the attempt, for the message.
     * @return bool False when no question form is on the screen.
     */
    private function answer_the_question_on_the_screen(string $answer, int $number): bool {
        $page = $this->getSession()->getPage();
        $form = $page->find('css', 'form#responseform');
        $button = $form ? $page->findButton('Submit answer') : null;
        if ($button === null) {
            return false;
        }
        $slotfield = $page->find('css', 'form#responseform input[name="slots"]');
        $slot = $slotfield ? (string) $slotfield->getValue() : '';
        $literal = behat_context_helper::escape($answer);
        $radio = $page->find('xpath', "//div[@data-region='answer-label'][contains(normalize-space(.), $literal)]"
            . "/preceding-sibling::input[@type='radio']");
        if ($radio === null) {
            $text = preg_replace('/\s+/', ' ', $page->find('css', '#region-main')?->getText() ?? $page->getText());
            throw new \Behat\Mink\Exception\ExpectationException("No answer \"$answer\" on question $number: "
                . mb_substr($text, 0, 600), $this->getSession());
        }
        $radio->click();
        $button->press();
        $this->spin(function () use ($slot): bool {
            $page = $this->getSession()->getPage();
            if ($page->find('css', 'form#responseform') === null) {
                return true;
            }
            $field = $page->find('css', 'form#responseform input[name="slots"]');
            return $field !== null && (string) $field->getValue() !== $slot;
        }, [], behat_base::get_extended_timeout(), null, true);
        $this->wait_for_pending_js();
        return true;
    }

    /**
     * Requests the CSV export of a user's last attempt with the session of the browser (issue #95: export).
     *
     * @When /^I request the CSV export of the last attempt of "([^"]*)" at "([^"]*)"$/
     *
     * @param string $username
     * @param string $idnumber
     * @return void
     */
    public function i_request_the_csv_export_of_the_last_attempt(string $username, string $idnumber): void {
        [$attempt] = $this->last_host_attempt_at($username, $idnumber);
        $url = (new \moodle_url('/local/catquiz/export_attempt_csv.php', ['attemptid' => $attempt->id]))->out(false);
        $this->requested = ['params' => []] + $this->request_in_browser($url);
        $this->record_network_evidence(sprintf('csv export %s: HTTP %d', $url, $this->requested['status']));
    }

    /**
     * Checks the requested CSV export (issue #95): every row names the attempt of the host and its person, the number
     * of questions, and no duplicate slot - the export follows the CAT attempt of exactly this attempt.
     *
     * @Then /^the export names the last attempt of "([^"]*)" at "([^"]*)" with (\d+) questions and no duplicate$/
     *
     * @param string $username
     * @param string $idnumber
     * @param int $questions
     * @return void
     */
    public function the_export_names_the_last_attempt(string $username, string $idnumber, int $questions): void {
        [$attempt] = $this->last_host_attempt_at($username, $idnumber);
        $body = (string) ($this->requested['body'] ?? '');
        $lines = array_values(array_filter(preg_split('/\r?\n/', $body), fn($l) => trim($l) !== ''));
        $problems = [];
        if ((int) ($this->requested['status'] ?? 0) !== 200 || count($lines) < 2) {
            $problems[] = 'HTTP ' . ($this->requested['status'] ?? '?') . ', ' . count($lines) . ' lines';
        } else {
            // The writer may put a byte order mark in front and separates with ";" (Moodle's csv_export_writer).
            $sep = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
            $head = array_map(fn($c) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $c)), str_getcsv($lines[0], $sep));
            if (!in_array('attemptid', $head, true)) {
                throw new \Behat\Mink\Exception\ExpectationException('Export without the expected columns: '
                    . mb_substr($body, 0, 300), $this->getSession());
            }
            foreach (array_slice($lines, 1) as $line) {
                $row = array_combine($head, array_pad(str_getcsv($line, $sep), count($head), ''));
                if ((int) $row['attemptid'] !== (int) $attempt->id || (int) $row['userid'] !== (int) $attempt->userid) {
                    $problems[] = "row of attempt {$row['attemptid']} / user {$row['userid']}";
                }
                if ((int) $row['questionsattempted'] !== $questions || (int) $row['duplicateslots'] !== 0) {
                    $problems[] = "questions {$row['questionsattempted']}, duplicate slots {$row['duplicateslots']}";
                }
            }
        }
        if ($problems) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'Export: ' . implode('; ', array_unique($problems)),
                $this->getSession()
            );
        }
    }

    /** @var array Result pages remembered by name (issue #159). */
    private static array $rememberedpages = [];

    /**
     * Opens the result page of the last attempt of a person in the browser, as from the report (issue #159).
     *
     * @When /^I open the CAT feedback page of the last attempt of "([^"]*)" at "([^"]*)"$/
     *
     * @param string $username
     * @param string $idnumber
     * @return void
     */
    public function i_open_the_cat_feedback_page_of_the_last_attempt(string $username, string $idnumber): void {
        global $DB;
        [$attempt] = $this->last_host_attempt_at($username, $idnumber);
        [$insql, $inparams] = $DB->get_in_or_equal(\local_catquiz\catquiz::component_names('mod_adaptivequiz'));
        $contextid = $DB->get_field_select(
            'local_catquiz_attempts',
            'contextid',
            "attemptid = ? AND component $insql",
            array_merge([$attempt->id], $inparams),
            MUST_EXIST
        );
        $url = new \moodle_url(
            '/local/catquiz/show_attemptfeedback.php',
            ['attemptid' => $attempt->id, 'contextid' => $contextid]
        );
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $this->getSession()->wait(behat_base::get_extended_timeout() * 1000, 'document.readyState === "complete"');
    }

    /**
     * Remembers the text of the result page shown now, with its address (issue #159).
     *
     * @Given /^I remember the result page as "([^"]*)"$/
     *
     * @param string $name
     * @return void
     */
    public function i_remember_the_result_page_as(string $name): void {
        self::$rememberedpages[$name] = [
            'url' => $this->getSession()->getCurrentUrl(),
            'text' => $this->result_page_text(),
        ];
        if (self::$rememberedpages[$name]['text'] === '') {
            throw new \Behat\Mink\Exception\ExpectationException('The result page is empty.', $this->getSession());
        }
    }

    /**
     * Runs the retention task the way the site does it: every finished attempt loses its progress (issue #159).
     *
     * @Given /^the retention task removes the progress of finished CAT attempts$/
     *
     * @return void
     */
    public function the_retention_task_removes_the_progress(): void {
        global $DB;
        set_config('progressretention', 'minimal', 'local_catquiz');
        $before = $DB->count_records('local_catquiz_progress');
        ob_start();
        (new \local_catquiz\task\cleanup_attempt_progress())->execute();
        ob_end_clean();
        $finished = $DB->count_records_sql(
            'SELECT COUNT(1) FROM {local_catquiz_progress} p JOIN {local_catquiz_attempts} a ON a.id = p.attemptid
              WHERE a.endtime IS NOT NULL'
        );
        if ($before === 0 || $finished !== 0) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The retention task removed nothing ($before progress rows before, $finished of finished attempts left).",
                $this->getSession()
            );
        }
        \cache_helper::purge_all();
    }

    /**
     * Opens the remembered result page again and compares what it shows (issue #159).
     *
     * @Then /^the result page "([^"]*)" shows the same values and labels again$/
     *
     * @param string $name
     * @return void
     */
    public function the_result_page_shows_the_same_again(string $name): void {
        $remembered = self::$rememberedpages[$name] ?? null;
        if ($remembered === null) {
            throw new \Behat\Mink\Exception\ExpectationException("No result page \"$name\" remembered.", $this->getSession());
        }
        $this->getSession()->visit($remembered['url']);
        $this->getSession()->wait(behat_base::get_extended_timeout() * 1000, 'document.readyState === "complete"');
        $text = $this->result_page_text();
        if ($text !== $remembered['text']) {
            $this->record_network_evidence("result page before: {$remembered['text']}");
            $this->record_network_evidence("result page after: $text");
            throw new \Behat\Mink\Exception\ExpectationException(
                "The result page changed after the cleanup.\nBefore: " . \core_text::substr($remembered['text'], 0, 900)
                    . "\nAfter:  " . \core_text::substr($text, 0, 900),
                $this->getSession()
            );
        }
    }

    /**
     * The visible text of the main region, without the ids generated per rendering.
     *
     * @return string
     */
    private function result_page_text(): string {
        $region = $this->getSession()->getPage()->find('css', '#region-main');
        $text = $region ? $region->getText() : $this->getSession()->getPage()->getText();
        $text = preg_replace('~\b(?:[a-z_]*)?[0-9a-f]{13,}\b~i', '#', $text);
        return trim(preg_replace('~\s+~u', ' ', $text));
    }
}
