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
 * The feedbacktab_clicked event.
 *
 * @package local_catquiz
 * @copyright 2024 Georg Maißer, <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\event;

use html_writer;
use moodle_url;

/**
 * The feedbacktab_clicked event class.
 *
 * @copyright 2024 Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedbacktab_clicked extends \core\event\base {

    /**
     * Init parameters.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = parent::LEVEL_OTHER;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('feedback_tab_clicked', 'local_catquiz');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        $data = $this->data;
        $otherarray = json_decode($data['other']);
        $url = new moodle_url('manage_catscales.php', [
            'attemptid' => $otherarray->attemptid,
        ], 'lcq_quizattempts');
        $attemptlink = html_writer::link(
            $url,
            get_string('feedbacksheader', 'local_catquiz', $otherarray->attemptid),
            // Open the attempt in a new tab, otherwise the link does not work because it just appends an anchor.
            ['target' => '_blank']
        );
        return get_string(
            'feedback_tab_clicked_description',
            'local_catquiz',
            [
                'userid' => $otherarray->userid,
                'feedback_translated' => $otherarray->feedback_translated,
                'attemptlink' => $attemptlink,
            ]
        );
    }

    /**
     * Get url.
     *
     * @return object
     */
    public function get_url() {
        $data = $this->data;
        $otherarray = json_decode($data['other']);
        return new moodle_url('manage_catscales.php', [
            'attemptid' => $otherarray->attemptid,
        ], 'lcq_quizattempts');
    }
}
