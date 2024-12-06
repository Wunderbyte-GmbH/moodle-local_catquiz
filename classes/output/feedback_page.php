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

namespace local_catquiz\output;

use context_course;
use renderable;
use renderer_base;
use templatable;
use local_catquiz\teststrategy\feedback_helper;

/**
 * Class containing data for feedback page
 *
 * @package     local_catquiz
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_page implements renderable, templatable {
    /** @var array */
    private $args;

    /**
     * Constructor.
     *
     * @param array $args The arguments for filtering feedback
     */
    public function __construct($args = []) {
        $this->args = $args;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $COURSE, $USER, $DB, $CFG;

        $context = context_course::instance($COURSE->id);
        return feedback_helper::get_feedback_data($this->args, $context, $USER, $COURSE, $DB, $CFG);
    }
}
