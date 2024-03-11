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
 * Plugin event observers are registered here.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\output;

use plugin_renderer_base;
use templatable;

/**
 * Renderer class.
 * @package local_catquiz
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the catscaledashboard
     *
     * @param templatable $data
     * @return string|boolean
     */
    public function render_catscaledashboard(templatable $data) {
        $data = $data->export_for_template($this);
        return $this->render_from_template('local_catquiz/catscaledashboard', $data);
    }

    /**
     * Render the catscaledashboard
     *
     * @param templatable $data
     * @return string|boolean
     */
    public function render_testitemdashboard(templatable $data) {
        $data = $data->export_for_template($this);
        return $this->render_from_template('local_catquiz/testitemdashboard', $data);
    }

    /**
     * Render the studentdetails
     *
     * @param templatable $data
     * @return string|boolean
     */
    public function render_studentdetails(templatable $data) {
        $data = $data->export_for_template($this);
        return $this->render_from_template('local_catquiz/studentdetails', $data);
    }
}
