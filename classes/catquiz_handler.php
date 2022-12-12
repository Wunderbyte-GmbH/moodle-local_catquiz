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

    /**
     * entities constructor.
     */
    public function __construct() {

    }

    /**
     * Create the form fields relevant to this plugin.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public static function instance_form_definition(MoodleQuickForm $mform) {

        $modelarray = [
            1 => get_string('model', 'local_catquiz') . ' 1',
            1 => get_string('model', 'local_catquiz') . ' 2',
            1 => get_string('model', 'local_catquiz') . ' 3',
            1 => get_string('model', 'local_catquiz') . ' 4',
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

    }

    /**
     * Set the data relvant to this plugin.
     *
     * @param stdClass $data
     * @return void
     */
    public static function instance_form_before_set_data(stdClass &$data) {

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
     * @return void
     */
    public static function instance_form_save(stdClass &$data) {

        if (!isset($data->id)) {
            throw new moodle_exception('noidindataobject', 'lcoal_catquiz');
        }

        // Do the saving.
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
