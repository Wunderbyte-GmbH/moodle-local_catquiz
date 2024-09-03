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
define('LOCAL_CATQUIZ_STATUS_SUBSCRIPTION_BOOKED', 1);
define('LOCAL_CATQUIZ_STATUS_SUBSCRIPTION_DELETED', 0);

define('LOCAL_CATQUIZ_STATUS_TEST_FORCE', 2);
define('LOCAL_CATQUIZ_STATUS_TEST_ACTIVE', 1);
define('LOCAL_CATQUIZ_STATUS_TEST_INACTIVE', 0);

define('LOCAL_CATQUIZ_STATUS_TEST_VISIBLE', 1);
define('LOCAL_CATQUIZ_STATUS_TEST_INVISIBLE', 0);

define('LOCAL_CATQUIZ_THRESHOLD_DEFAULT', 30);

// Teststrategies.
define('LOCAL_CATQUIZ_STRATEGY_FASTEST', 1);
define('LOCAL_CATQUIZ_STRATEGY_BALANCED', 2);
define('LOCAL_CATQUIZ_STRATEGY_ALLSUBS', 3);
define('LOCAL_CATQUIZ_STRATEGY_LOWESTSUB', 4);
define('LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB', 5);
define('LOCAL_CATQUIZ_STRATEGY_PILOT', 6);
define('LOCAL_CATQUIZ_STRATEGY_CLASSIC', 7);
define('LOCAL_CATQUIZ_STRATEGY_RELSUBS', 8);

// Testiem Status in Scale.
define('LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE', 0);
define('LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE', 1);
define('LOCAL_CATQUIZ_TESTITEM_STATUS_UNDEFINED', 2);

// Testitem model calc status.
define('LOCAL_CATQUIZ_STATUS_EXCLUDED_MANUALLY', -5);
define('LOCAL_CATQUIZ_STATUS_NOT_CALCULATED', 0);
define('LOCAL_CATQUIZ_STATUS_CALCULATED', 1);
define('LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY', 4);
define('LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY', 5);

define('LOCAL_CATQUIZ_STATUS_EXCLUDED_MANUALLY_COLOR_CLASS', 'text-danger');
define('LOCAL_CATQUIZ_STATUS_NOT_CALCULATED_COLOR_CLASS', 'text-secondary');
define('LOCAL_CATQUIZ_STATUS_CALCULATED_COLOR_CLASS', 'text-warning');
define('LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY_COLOR_CLASS', 'text-primary');
define('LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY_COLOR_CLASS', 'text-success');

// Attempt Status.
define('LOCAL_CATQUIZ_ATTEMPT_OK', 0);
define('LOCAL_CATQUIZ_ATTEMPT_ABORTED', 1);

define('LOCAL_CATQUIZ_PERSONABILITY_MAX', 50);

define('LOCAL_CATQUIZ_PERSONABILITY_LOWER_LIMIT', -5);
define('LOCAL_CATQUIZ_PERSONABILITY_UPPER_LIMIT', 5);

define('LOCAL_CATQUIZ_COLORBARGRADIENT', 3);

define('LOCAL_CATQUIZ_DEFAULT_NUMBER_OF_FEEDBACKS_PER_SCALE', 2);

// Sortorder.
define('LOCAL_CATQUIZ_SORTORDER_ASC', 1);
define('LOCAL_CATQUIZ_SORTORDER_DESC', 2);
define('LOCAL_CATQUIZ_SORTORDER_BY_NAME', 3);

// Scaleid.
define('LOCAL_CATQUIZ_PRIMARYCATSCALE_PARENT', 0);
define('LOCAL_CATQUIZ_PRIMARYCATSCALE_LOWEST', -1);
define('LOCAL_CATQUIZ_PRIMARYCATSCALE_STRONGEST', -2);
define('LOCAL_CATQUIZ_PRIMARYCATSCALE_DEFAULT', -3);

// Range for timespan.
define('LOCAL_CATQUIZ_TIMERANGE_DAY', 0);
define('LOCAL_CATQUIZ_TIMERANGE_WEEK', -1);
define('LOCAL_CATQUIZ_TIMERANGE_MONTH', -2);
define('LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR', -3);

// Magic Number.
define('LOCAL_CATQUIZ_RANDOM_DEFAULT', -12345);
define('LOCAL_CATQUIZ_DEFAULT_NONSENSE_TESTSTRATEGY', -1);

// Standarderror defaults.
define('LOCAL_CATQUIZ_STANDARDERROR_DEFAULT_MIN', 0.35);
define('LOCAL_CATQUIZ_STANDARDERROR_DEFAULT_MAX', 1);

define('LOCAL_CATQUIZ_DEFAULT_GREY', "#878787");
define('LOCAL_CATQUIZ_DEFAULT_BLACK', "#000000");
define('LOCAL_CATQUIZ_MAX_SCALERANGE', 8);
/**
 * Renders the popup Link.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_catquiz_render_navbar_output(\renderer_base $renderer) {
    global $CFG;

    // Early bail out conditions.
    if (!isloggedin() || isguestuser()
        || !has_capability('local/catquiz:canmanage', context_system::instance())) {
        return;
    }

    $output = '<div class="popover-region nav-link icon-no-margin dropdown">
        <a class="btn btn-secondary"
        id="dropdownMenuButton" aria-haspopup="true" aria-expanded="false" href="'
            . $CFG->wwwroot . '/local/catquiz/manage_catscales.php"
        role="button">
        '. get_string('catquiz', 'local_catquiz') .'
        </a>
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

/**
 * Get saved files to display images in feedbacks
 *
 * @param mixed $course
 * @param mixed $birecordorcm
 * @param mixed $context
 * @param mixed $filearea
 * @param mixed $args
 * @param bool $forcedownload
 * @param array $options
 */
function local_catquiz_pluginfile($course, $birecordorcm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    $isfeedbackfile = strpos($filearea, 'feedback_files') === 0;
    if (!$isfeedbackfile) {
        send_file_not_found();
    }

    $fs = get_file_storage();
    $filename = array_pop($args);
    $filepath = '/';
    $itemid = intval($args[0]);
    if (!$file = $fs->get_file($context->id, 'local_catquiz', $filearea, $itemid, $filepath, $filename) or $file->is_directory()) {
        send_file_not_found();
    }
    \core\session\manager::write_close();
    send_stored_file($file, null, 0, $forcedownload, $options);
}