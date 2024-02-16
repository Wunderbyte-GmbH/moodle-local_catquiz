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
 * Class catcontext_table.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;

use cache_helper;
use local_catquiz\testenvironment;
use local_wunderbyte_table\wunderbyte_table;
use stdClass;

/**
 * Search results for managers are shown in a table (student search results use the template searchresults_student).
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catcontext_table extends wunderbyte_table {

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
     * Return value for starttimestamp column.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_starttimestamp(stdClass $values) {

        if (!empty($values->starttimestamp)) {
            return userdate($values->starttimestamp);
        }
        return get_string('notimelimit', 'local_catquiz');
    }

    /**
     * Return value for endtimestamp column.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_endtimestamp(stdClass $values) {

        if (!empty($values->endtimestamp)) {
            return userdate($values->endtimestamp);
        }
        return get_string('notimelimit', 'local_catquiz');
    }

    /**
     * Return value for endtimestamp column.
     *
     * @param stdClass $values
     * @return mixed
     */
    public function col_testitems(stdClass $values) {

        // Returns the number of testitems in the realm of this context.

        return $values->testitems;
    }

    /**
     * Return value for attempts column.
     *
     * @param mixed $values
     *
     * @return mixed
     *
     */
    public function col_attempts($values) {
        if (!empty($values->attempts)) {
            return $values->attempts;
        }
        return '0';
    }

    /**
     * This handles the colum checkboxes.
     *
     * @param stdClass $values
     * @return string|null
     */
    public function col_action($values) {

        global $OUTPUT;

        $data['showactionbuttons'][] = [
            'label' => get_string('edit', 'core'), // Name of your action button.
            'class' => 'btn btn-blank',
            'href' => '#', // You can either use the link, or JS, or both.
            'iclass' => 'fa fa-edit', // Add an icon before the label.
            'id' => $values->id,
            'formname' => 'local_catquiz\\form\\edit_catcontext',
            'data' => [
                'id' => $values->id,
                'labelcolumn' => 'name',
                ],
        ];

        $data['showactionbuttons'][] = [
            'label' => get_string('delete', 'core'), // Name of your action button.
            'class' => 'btn btn-blank',
            'href' => '#', // You can either use the link, or JS, or both.
            'iclass' => 'fa fa-edit', // Add an icon before the label.
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

        return $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', $data);

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
