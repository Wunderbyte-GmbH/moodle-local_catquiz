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

define('STATUS_TEST_FORCE', 2);
define('STATUS_TEST_ACTIVE', 1);
define('STATUS_TEST_INACTIVE', 0);

define('STATUS_TEST_VISIBLE', 1);
define('STATUS_TEST_INVISIBLE', 0);

// Teststrategies.

define('STRATEGY_FASTEST', 1);
define('STRATEGY_BALANCED', 2);
define('STRATEGY_ALLSUBS', 3);
define('STRATEGY_LOWESTSUB', 4);
define('STRATEGY_HIGHESTSUB', 5);
define('STRATEGY_PILOT', 6);

// Testiem Status in Scale.
define('TESTITEM_STATUS_ACTIVE', 0);
define('TESTITEM_STATUS_INACTIVE', 1);

// Testitem model calc status.
define('STATUS_EXCLUDED_MANUALLY', -5);
define('STATUS_NOT_CALCULATED', 0);
define('STATUS_CALCULATED', 1);
define('STATUS_UPDATED_MANUALLY', 4);
define('STATUS_CONFIRMED_MANUALLY', 5);

define('STATUS_EXCLUDED_MANUALLY_COLOR_CLASS', 'text-danger');
define('STATUS_NOT_CALCULATED_COLOR_CLASS', 'text-secondary');
define('STATUS_CALCULATED_COLOR_CLASS', 'text-warning');
define('STATUS_UPDATED_MANUALLY_COLOR_CLASS', 'text-primary');
define('STATUS_CONFIRMED_MANUALLY_COLOR_CLASS', 'text-success');

// Attempt Status.
define('ATTEMPT_OK', 0);
define('ATTEMPT_ABORTED', 1);

define('PERSONABILITY_MAX', 50);

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
                . $CFG->wwwroot . '/local/catquiz/manage_catscales.php"">'
                . get_string('managecatscales', 'local_catquiz') . '</a>
            <a class="dropdown-item" href="'
                . $CFG->wwwroot . '/local/catquiz/manage_catcontexts.php"">'
                . get_string('managecatcontexts', 'local_catquiz') . '</a>
            <a class="dropdown-item" href="'
                . $CFG->wwwroot . '/local/catquiz/manage_testenvironments.php"">'
                . get_string('managetestenvironments', 'local_catquiz') . '</a>
        </div>
    </div>';

    return $output;
}

/**
 * Validate the data in the new field when the form is submitted
 *
 * @param moodleform_mod $fromform
 * @param array $fields
 * @return void
 */
function local_catquiz_coursemodule_standard_elements($fromform, $fields) {

}
