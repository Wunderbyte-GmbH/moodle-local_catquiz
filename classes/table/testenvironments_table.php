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
 * Class testenvironments_table.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../../lib.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

use cache_helper;
use context_module;
use context_system;
use html_writer;
use local_catquiz\catscale;
use local_catquiz\testenvironment;
use local_wunderbyte_table\wunderbyte_table;
use mod_booking\booking;
use moodle_url;
use question_bank;
use stdClass;

/**
 * Search results for managers are shown in a table (student search results use the template searchresults_student).
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testenvironments_table extends wunderbyte_table {

    /**
     * Return value for type column.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_istest(stdClass $values) {
        if ($values->istest) {
            return get_string('testtype', 'local_catquiz');
        }
        return get_string('templatetype', 'local_catquiz');
    }

    /**
     * Return value for visible column.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_visible(stdClass $values) {

        switch ($values->visible) {
            case '1':
                return get_string('visible', 'core');
            case '0':
                return get_string('invisible', 'local_catquiz');
            default:
                return get_string('invisible', 'local_catquiz');
        }
    }

    /**
     * Return value for status column.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_status(stdClass $values) {

        switch ($values->status) {
            case '2':
                return get_string('force', 'local_catquiz');
            case '1':
                return get_string('active', 'core');
            case '0':
                return get_string('inactive', 'core');
            default:
                return get_string('inactive', 'core');
        }
    }

    /**
     * Return value for visible column.
     *
     * @param stdClass $values
     * @return mixed
     */
    public function col_availability(stdClass $values) {

        if (empty($values->availability)) {
            return '';
        } else {
            return $values->availability;
        }
    }

    /**
     * Return value for timecreated column.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_timecreated(stdClass $values) {

        return userdate($values->timecreated);
    }

    /**
     * Return value for timemodified column.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_timemodified(stdClass $values) {

        return userdate($values->timemodified);
    }

    /**
     * This handles the colum checkboxes.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_action($values) {

        global $OUTPUT;

        $data['showactionbuttons'][] = [
            'label' => get_string('edit', 'core'), // Name of your action button.
            'class' => 'btn btn-blank',
            'href' => '#', // You can either use the link, or JS, or both.
            'iclass' => 'fa fa-edit', // Add an icon before the label.
            'id' => $values->id,
            // The method needs to be added to your child of wunderbyte_table class.
            'formname' => 'local_catquiz\\form\\edit_testenvironment',
            'data' => [
                'id' => $values->id,
                'labelcolumn' => 'name',
                ],
        ];

        $data['showactionbuttons'][] = [
            'label' => get_string('delete', 'core'), // Name of your action button.
            'class' => 'btn btn-blank',
            'href' => '#', // You can either use the link, or JS, or both.
            'iclass' => 'fa fa-trash', // Add an icon before the label.
            'id' => $values->id,
            'methodname' => 'deleteitem', // The method needs to be added to your child of wunderbyte_table class.
            'data' => [
                [ // Will be added eg as data-id = $values->id, so values can be transmitted to the method above.
                    'key' => 'id',
                    'value' => $values->id,
                ],
                [
                    'key' => 'labelcolumn',
                    'value' => 'name',
                ],
                ],
        ];

        // For templates we can not link to a quiz instance, so display the show action just for tests.
        if ($values->istest) {
            $url = new moodle_url('/mod/adaptivequiz/view.php', ['n' => $values->componentid]);
            $data['showactionbuttons'][] = [
                'label' => get_string('show', 'core'), // Name of your action button.
                'class' => 'btn btn-blank',
                'href' => $url->out(),
                'iclass' => 'fa fa-eye', // Add an icon before the label.
                'id' => $values->id,
            ];
        }

        return $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', $data);;
    }

    /**
     * Return the number of questions in the test
     *
     * @param stdClass $values
     * @return mixed
     */
    public function col_numberofitems(stdClass $values) {
        return testenvironment::get_num_items_for_test($values->id);
    }

    /**
     * Delete item.
     *
     * @param int $id
     * @param string $data
     * @return array
     */
    public function action_deleteitem(int $id, string $data): array {

        if (testenvironment::delete_testenvironment($id)) {
            $success = 1;
            $message = get_string('success');
        } else {
            $success = 0;
            $message = get_string('error');
        }

        cache_helper::purge_by_event('changesintestenvironments');

        return [
            'success' => $success,
            'message' => $message,
        ];
    }
}
