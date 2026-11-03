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
 * Fixture for Behat: what the browser does with a chart it cannot draw (issue #150).
 *
 * The chart of the live case - the test information without values, the bars with one per step - goes past the
 * check of local\statistics\chart_output on purpose, straight into the template local_catquiz/chart. Next to it a
 * chart that can be drawn.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
defined('BEHAT_SITE_RUNNING') || die('Only available on Behat test server');

require_login();
$PAGE->set_url('/local/catquiz/tests/behat/fixtures/chart_refusal.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Chart refusal');
$PAGE->set_heading('Chart refusal');

$labels = array_map('strval', range(-4.75, 4.75, 0.5));
$live = new \core\chart_bar();
$live->add_series(new \core\chart_series('Test information', []));
$live->add_series(new \core\chart_series('Score', array_fill(0, count($labels), 1)));
$live->set_labels([]);
$fine = new \core\chart_bar();
$fine->add_series(new \core\chart_series('Score', array_fill(0, count($labels), 1)));
$fine->set_labels($labels);

echo $OUTPUT->header();
foreach (['live' => $live, 'fine' => $fine] as $name => $chart) {
    echo html_writer::div(
        $OUTPUT->render_from_template('local_catquiz/chart', (object) ['chartdata' => json_encode($chart), 'withtable' => true]),
        '',
        ['data-region' => "local_catquiz-fixture-$name"]
    );
}
echo $OUTPUT->footer();
