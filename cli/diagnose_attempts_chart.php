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
 * Prints the query behind "Testversuche pro Person" and what it returns.
 *
 * The chart disagreed with a hand-written reference query on a production site, and
 * reading the code could not settle why: the plugin applies *fewer* filters than the
 * reference, so it should return more rows, not fewer. This script removes the
 * guessing by showing the statement that is actually built, the parameters bound to
 * it, and the distribution it produces - next to the same reference query.
 *
 * Usage on the server:
 *   php local/catquiz/cli/diagnose_attempts_chart.php --scaleid=91 --courseid=2299
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params([
    'help' => false,
    'scaleid' => 0,
    'courseid' => 0,
    'contextid' => 0,
], ['h' => 'help']);

if ($options['help'] || empty($options['scaleid'])) {
    cli_writeln("Diagnoses the 'attempts per person' chart.\n");
    cli_writeln('  --scaleid=N    Scale the shortcode uses (required).');
    cli_writeln('  --courseid=N   Course, as the shortcode resolves it.');
    cli_writeln('  --contextid=N  Only for the reference query, to compare like for like.');
    exit(0);
}

$scaleid = (int) $options['scaleid'];
$courseid = (int) $options['courseid'] ?: null;
$contextid = (int) $options['contextid'] ?: null;

\core\session\manager::set_user(get_admin());

// What the page itself would resolve. Printed because a mismatch here is the first
// thing worth ruling out.
cli_writeln('Resolved by the plugin:');
cli_writeln(sprintf(
    '  catscale::get_context_id(%d) = %s',
    $scaleid,
    \local_catquiz\catscale::get_context_id($scaleid)
));
cli_writeln(sprintf('  courseid passed here        = %s', $courseid ?? 'null'));

[$sql, $params] = \local_catquiz\catquiz::get_sql_for_attempts_per_person(
    // The chart passes the scale context; it is not used to filter attempts, but is
    // passed through, so it is reproduced faithfully here.
    (int) \local_catquiz\catscale::get_context_id($scaleid),
    $scaleid,
    $courseid,
    null,
    null,
    null
);

cli_writeln("\n--- Generated statement ---");
cli_writeln($sql);

cli_writeln("\n--- Bound parameters ---");
foreach ($params as $name => $value) {
    cli_writeln(sprintf('  %-24s %s', $name, is_scalar($value) ? $value : gettype($value)));
}

cli_writeln("\n--- Distribution produced by the plugin ---");
$rows = $DB->get_records_sql(
    "SELECT attempts, COUNT(*) persons FROM ($sql) x GROUP BY attempts ORDER BY attempts",
    $params
);
foreach ($rows as $row) {
    cli_writeln(sprintf('  attempts=%-4s persons=%s', $row->attempts, $row->persons));
}
if (!$rows) {
    cli_writeln('  (no rows)');
}

// The same question, written independently. If the two disagree, the difference is
// in the statement above and now visible side by side.
cli_writeln("\n--- Reference query ---");

$refparams = ['scaleid' => $scaleid];
$refwhere = 'a.scaleid = :scaleid';
if ($courseid) {
    $refwhere .= ' AND a.courseid = :courseid';
    $refparams['courseid'] = $courseid;
}
if ($contextid) {
    $refwhere .= ' AND a.contextid = :contextid';
    $refparams['contextid'] = $contextid;
}

$refsql = "SELECT attempts, COUNT(*) persons FROM (
               SELECT a.userid, COUNT(*) attempts
                 FROM {local_catquiz_attempts} a
                WHERE $refwhere
             GROUP BY a.userid
           ) r GROUP BY attempts ORDER BY attempts";

foreach ($DB->get_records_sql($refsql, $refparams) as $row) {
    cli_writeln(sprintf('  attempts=%-4s persons=%s', $row->attempts, $row->persons));
}

cli_writeln("\nIf the two lists differ, the statement above is the place to look.");
