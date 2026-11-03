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

namespace local_catquiz;

use advanced_testcase;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * On a reload of the CAT settings, the submission wins over the stored settings (issue #124).
 *
 * data_preprocessing() tells the first load of the form from a later request by the submission.
 * The host never handed over its form, so the submission always looked empty: every request took
 * the first-load path and wrote the stored scale and subscale choices over the fresh ones. Choosing
 * another root scale or activating a subscale then could not be saved.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catquiz_handler
 */
final class settings_reload_precedence_test extends advanced_testcase {
    /** @var int Id of the activity instance the settings belong to. */
    private const INSTANCE = 64001;

    /**
     * Stores settings for the activity: root scale 11, subscale 12 switched off.
     */
    private function store_settings(): void {
        global $DB;

        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => self::INSTANCE, 'component' => 'mod_adaptivequiz', 'catscaleid' => 11,
            'contextid' => 1, 'courseid' => 1, 'name' => 'stored', 'status' => 1,
            'json' => json_encode(['catquiz_catscales' => 11, 'catquiz_subscalecheckbox_12' => 0]),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Builds a form carrying a submission, as the host's form does on a reload.
     *
     * @param array $submission The submitted values.
     * @return MoodleQuickForm
     */
    private function submitted_form(array $submission): MoodleQuickForm {
        $mform = new MoodleQuickForm('mod_adaptivequiz_mod_form', 'post', '');
        $mform->updateSubmission($submission, []);
        $_POST = $submission;

        return $mform;
    }

    /**
     * On the first load, the stored settings fill the form.
     */
    public function test_first_load_restores_the_stored_settings(): void {
        $this->resetAfterTest();
        $this->store_settings();
        $_POST = [];

        $defaults = ['instance' => self::INSTANCE];
        $mform = new MoodleQuickForm('mod_adaptivequiz_mod_form', 'post', '');
        catquiz_handler::data_preprocessing($defaults, $mform);

        $this->assertEquals(11, $_POST['catquiz_catscales']);
        $this->assertEquals(0, $_POST['catquiz_subscalecheckbox_12']);
    }

    /**
     * An activated subscale is not switched off again by the stored settings.
     */
    public function test_a_submitted_subscale_is_not_overwritten(): void {
        $this->resetAfterTest();
        $this->store_settings();

        $defaults = ['instance' => self::INSTANCE];
        $mform = $this->submitted_form(['catquiz_catscales' => 11, 'catquiz_subscalecheckbox_12' => 1]);
        catquiz_handler::data_preprocessing($defaults, $mform);

        $this->assertEquals(1, $_POST['catquiz_subscalecheckbox_12'], 'The stored 0 overwrote the submitted 1.');
    }

    /**
     * A newly chosen root scale is not replaced by the stored one.
     */
    public function test_a_submitted_root_scale_is_not_overwritten(): void {
        $this->resetAfterTest();
        $this->store_settings();

        $defaults = ['instance' => self::INSTANCE];
        $mform = $this->submitted_form(['catquiz_catscales' => 21]);
        catquiz_handler::data_preprocessing($defaults, $mform);

        $this->assertEquals(21, $_POST['catquiz_catscales'], 'The stored root scale replaced the submitted one.');
    }

    /**
     * Without a form from the host, the raw submission decides the same way.
     */
    public function test_without_a_form_the_submission_still_wins(): void {
        $this->resetAfterTest();
        $this->store_settings();
        $_POST = ['catquiz_catscales' => 21, 'catquiz_subscalecheckbox_12' => 1];

        $defaults = ['instance' => self::INSTANCE];
        catquiz_handler::data_preprocessing($defaults);

        $this->assertEquals(21, $_POST['catquiz_catscales']);
        $this->assertEquals(1, $_POST['catquiz_subscalecheckbox_12']);
    }

    /**
     * Resets the request superglobal the tests write into.
     */
    protected function tearDown(): void {
        $_POST = [];
        parent::tearDown();
    }
}
