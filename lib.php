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
 * Moodle hooks for local_catquiz
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define constants.
define('STATUS_SUBSCRIPTION_BOOKED', 1);
define('STATUS_SUBSCRIPTION_DELETED', 0);

/**
 * Renders the popup Link.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_catquiz_render_navbar_output(\renderer_base $renderer) {
    global $CFG;

    // Early bail out conditions.
    if (!isloggedin() || isguestuser() || !has_capability('local/catquiz:canmanage', context_system::instance())) {
        return;
    }

    $output = '<div class="popover-region nav-link icon-no-margin dropdown">
        <button class="btn btn-secondary dropdown-toggle" type="button"
        id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        '. get_string('catquiz', 'local_catquiz') .'
        </button>
        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
            <a class="dropdown-item" href="'
                . $CFG->wwwroot . '/local/catquiz/manage_dimensions.php"">'
                . get_string('managedimensions', 'local_catquiz') . '</a>
            <a class="dropdown-item" href="'
                . $CFG->wwwroot . '/local/catquiz/test.php">'
                . get_string('test', 'local_catquiz') . '</a>
        </div>
    </div>';

    return $output;
}