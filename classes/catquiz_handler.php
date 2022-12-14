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
 * Entities Class to display list of entity records.
 *
 * @package local_catquiz
 * @author Georg Maißer
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use moodle_exception;
use MoodleQuickForm;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catquiz_handler {
    /** @var int Rasch model to select next question to be displayed to user */
    public const RASCH_MODEL = 1;
    /** @var int Artificial intelligence model to select next question to be displayed to user */
    public const ARTIFICIAL_INTELLIGENCE = 2;

    /**
     * entities constructor.
     */
    public function __construct() {

    }

    /**
     * Create the form fields relevant to this plugin.
     *
     * @param MoodleQuickForm $mform
     * @param \context $context
     * @return void
     */
    public static function instance_form_definition(MoodleQuickForm $mform, \context $context) {

        $modelarray = [
            self::RASCH_MODEL => get_string('model', 'local_catquiz') . ' Rasch',
            self::ARTIFICIAL_INTELLIGENCE => get_string('model', 'local_catquiz') . ' AI',
            3 => get_string('model', 'local_catquiz') . ' not used yet',
            4 => get_string('model', 'local_catquiz') . ' not used yet',
        ];

        // Add a special header for catquiz.
        $mform->addElement('header', 'catquiz_header',
                get_string('catquizsettings', 'local_catquiz'));

        // Turn the catquiz engine on and off for this particular instance.
        $mform->addElement('advcheckbox', 'catquiz_usecatquiz',
                get_string('usecatquiz', 'local_catquiz'), null, null, [0, 1]);

        // Choose a model for this instance.
        $mform->addElement('select', 'catquiz_model_select',
                get_string('selectmodel', 'local_catquiz'), $modelarray);
        $mform->disabledIf('catquiz_model_select', 'catquiz_usecatquiz', 'neq', 1);

        // Question categories or tags to use for this quiz.

        $areas = adaptivequiz_get_question_categories($context);
        $options = array(
            'multiple' => true,
            'noselectionstring' => get_string('allareas', 'search'),
        );
        $mform->addElement('autocomplete', 'catquestionpool', get_string('questionpool', 'adaptivequiz'), $areas, $options);
        $mform->addHelpButton('catquestionpool', 'questionpool', 'adaptivequiz');
        $mform->addRule('catquestionpool', null, 'required', null, 'client');
    }

    /**
     * Set the data relvant to this plugin.
     *
     * @param stdClass $data
     * @return void
     */
    public static function instance_form_before_set_data(stdClass &$data) {
        global $DB;
        if (!$settings = $DB->get_record('local_catquiz', ['componentname' => 'mod_' . $data->modulename, 'componentid' => $data->id])) {
            return;
        }
        $data->catquiz_usecatquiz = 1;
        $data->catquiz_model_select = $settings->adaptivemodel;
        $data->catquestionpool = json_decode($settings->questioncategoryids);
    }

    /**
     * Undocumented function
     *
     * @param stdClass $data
     * @return void
     */
    public static function instance_form_definition_after_data(stdClass &$data) {

    }

    /**
     * Validate the submitted fields relevant to this plugin.
     *
     * @param stdClass $data
     * @return void
     */
    public static function instance_form_validation(stdClass &$data, array $errors) {

    }

    /**
     * Save submitted data relevant to this plugin.
     *
     * @param stdClass $data
     * @param int $instanceid
     * @param string $componentname
     * @return void
     */
    public static function instance_form_save(stdClass &$data, int $instanceid, string $componentname) {
        global $DB;
        $catdata = new stdClass;
        $catdata->componentname = $componentname;
        $catdata->componentid = $instanceid;
        $catdata->adaptivemodel = $data->catquiz_model_select;
        $catdata->questioncategoryids = json_encode($data->catquestionpool);
        if ($DB->record_exists('local_catquiz', ['componentname' => $componentname, 'componentid' => $instanceid])) {
            $catdata->id = $DB->get_field('local_catquiz', 'id', ['componentname' => $componentname,
                'componentid' => $instanceid]);
            $DB->update_record('local_catquiz', $catdata);
        } else {
            $DB->insert_record('local_catquiz', $catdata);
        }
    }

    /**
     * Delete settings related to
     *
     * @param int $id
     * @param string $componentname
     * @return void
     */
    public static function delete_settings(string $componentname, int $id) {
        global $DB;
        $DB->delete_records('local_catquiz', ['componentname' => $componentname, 'componentid' => $id]);
    }

    /**
     * Check if this instance is setup to actually use catquiz.
     * When called by an external plugin, this must specify its signature.
     * Like "mod_adaptivequiz" and use its own id (not cmid, but instance id).
     *
     * @param integer $quizid
     * @param string $component
     * @return bool
     */
    public static function use_catquiz(int $quizid, string $component) {

        // TODO: Implement fuctionality.

        return true;
    }
}
