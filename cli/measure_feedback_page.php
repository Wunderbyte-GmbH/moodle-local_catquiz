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
 * Issue #85: what the feedback pages cost with and without lazy loading, on existing data.
 *
 * Measures, as the user given sees them,
 * - the result page of an attempt (export_for_template + template, as show_attemptfeedback.php),
 * - the shortcode catquizfeedback with numberofattempts=K,
 * - the shortcode catquizstatistics,
 * - every region that loads later, through its endpoint (render_feedback_tab, render_statistics_chart),
 * each with queries, reads, HTML bytes, size of the serialised session and time (median, 95th percentile).
 *
 * lazyfeedbacktabs=0 and =1 run in separate processes (this script calls itself with --child), so neither
 * warms the caches of the other. The configuration is forced in memory; nothing is written to the site.
 * Equality of results: every tab rendered with the page (lazy off) is compared with the HTML its endpoint
 * returns (lazy on), normalised (feedback_page_meter::fingerprint).
 *
 *   php local/catquiz/cli/measure_feedback_page.php --userid=U --attemptid=A [--courseid=C] [--scaleid=S]
 *       [--attempts=K] [--repeat=5] [--json]
 *
 * Reads only. Run it on a copy or a test site with real attempts, not on the development sites with the
 * load seed. The same measurement runs as a PHPUnit test (tests/local/statistics/feedback_page_meter_test.php)
 * on a fixture with K = 0, 20 and 100 earlier attempts; doc/statistics-population.md holds the numbers.
 *
 * Development tool, not shipped (.gitattributes).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\local\statistics\feedback_page_meter;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params([
    'userid' => 0,
    'attemptid' => 0,
    'courseid' => 0,
    'scaleid' => 0,
    'attempts' => 20,
    'repeat' => 5,
    'lazy' => null,
    'child' => false,
    'json' => false,
    'help' => false,
], ['h' => 'help']);

if ($options['help'] || !$options['userid'] || !$options['attemptid']) {
    echo "php local/catquiz/cli/measure_feedback_page.php --userid=U --attemptid=A [--courseid=C] [--scaleid=S]"
        . " [--attempts=K] [--repeat=5] [--json]\n";
    exit($options['help'] ? 0 : 1);
}

if (!$options['child']) {
    // The parent: one process per setting, then the comparison.
    $results = [];
    foreach ([0, 1] as $lazy) {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child --lazy=' . $lazy;
        foreach (['userid', 'attemptid', 'courseid', 'scaleid', 'attempts', 'repeat'] as $name) {
            $command .= ' --' . $name . '=' . escapeshellarg((string) $options[$name]);
        }
        $output = shell_exec($command);
        $results[$lazy] = json_decode((string) $output, true);
        if (!is_array($results[$lazy])) {
            cli_error("The measurement with lazyfeedbacktabs=$lazy failed:\n" . $output);
        }
    }
    $equal = [];
    foreach ($results[0]['panes'] as $key => $fingerprint) {
        $equal[$key] = isset($results[1]['tabs'][$key]) ? $results[1]['tabs'][$key] === $fingerprint : null;
    }
    if ($options['json']) {
        echo json_encode(['off' => $results[0], 'on' => $results[1], 'equal' => $equal], JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }
    $columns = ['measurement (lazy off / on)', 'queries', 'reads', 'bytes', 'session', 'median', 'p95'];
    printf("%-34s %8s %8s %10s %10s %9s %9s\n", ...$columns);
    foreach (array_keys($results[0]['measurements'] + $results[1]['measurements']) as $name) {
        foreach ([0, 1] as $lazy) {
            $m = $results[$lazy]['measurements'][$name] ?? null;
            if ($m === null) {
                continue;
            }
            printf(
                "%-34s %8d %8d %10d %10d %9.1f %9.1f\n",
                $name . ($lazy ? ' [on]' : ' [off]'),
                $m['queries'],
                $m['reads'],
                $m['bytes'],
                $m['session'],
                $m['median'],
                $m['p95']
            );
        }
    }
    echo "\nSame content (tab with the page vs. endpoint):\n";
    foreach ($equal as $key => $same) {
        printf("  %-40s %s\n", $key, $same === null ? 'not offered lazily' : ($same ? 'equal' : 'DIFFERENT'));
    }
    exit(0);
}

// The child: one setting, forced in memory only.
$CFG->forced_plugin_settings['local_catquiz']['lazyfeedbacktabs'] = (int) $options['lazy'];
$user = core_user::get_user((int) $options['userid'], '*', MUST_EXIST);
\core\session\manager::set_user($user);
$attemptid = (int) $options['attemptid'];
$courseid = (int) $options['courseid'];
if ($courseid) {
    $COURSE = get_course($courseid);
}
$PAGE->set_context($courseid ? context_course::instance($courseid) : context_system::instance());
$PAGE->set_url('/local/catquiz/cli/measure_feedback_page.php');
$repeat = (int) $options['repeat'];

$measurements = [];
$page = feedback_page_meter::measure(fn() => feedback_page_meter::render_result_page($attemptid, $courseid), $repeat);
$measurements['result page'] = $page;
$shortcode = feedback_page_meter::measure(fn() => feedback_page_meter::render_feedback_shortcode([
    'numberofattempts' => (int) $options['attempts'],
    'courseid' => $courseid,
]), $repeat);
$measurements['catquizfeedback numberofattempts=' . (int) $options['attempts']] = $shortcode;
$statistics = null;
if ($options['scaleid']) {
    $statistics = feedback_page_meter::measure(fn() => feedback_page_meter::render_statistics_shortcode([
        'globalscale' => (int) $options['scaleid'],
        'courseid' => $courseid,
    ]), $repeat);
    $measurements['catquizstatistics'] = $statistics;
}

$tabs = [];
$regions = feedback_page_meter::lazy_regions($page['html']);
foreach ($regions['feedback'] as [$id, $generator, $audience]) {
    $m = feedback_page_meter::measure(fn() => feedback_page_meter::render_tab($id, $generator, $audience), $repeat);
    $measurements["tab $generator/$audience"] = $m;
    $tabs["$generator/$audience"] = feedback_page_meter::fingerprint($m['html']);
}
$collapsed = feedback_page_meter::lazy_regions($shortcode['html'])['feedback'];
$collapsed = array_filter($collapsed, fn($region) => $region[1] === 'attempt');
if ($collapsed) {
    [$id, $generator, $audience] = reset($collapsed);
    $measurements['collapsed attempt (each)'] = feedback_page_meter::measure(
        fn() => feedback_page_meter::render_tab($id, $generator, $audience),
        $repeat
    );
}
if ($statistics) {
    foreach (feedback_page_meter::lazy_regions($statistics['html'])['statistics'] as [$scope, $chart]) {
        $measurements["chart $chart"] = feedback_page_meter::measure(
            fn() => feedback_page_meter::render_chart($scope, $chart),
            $repeat
        );
    }
}

$panes = array_map([feedback_page_meter::class, 'fingerprint'], feedback_page_meter::eager_panes($page['html']));
foreach ($measurements as &$measurement) {
    unset($measurement['html']);
}
echo json_encode(['measurements' => $measurements, 'tabs' => $tabs, 'panes' => $panes]);
