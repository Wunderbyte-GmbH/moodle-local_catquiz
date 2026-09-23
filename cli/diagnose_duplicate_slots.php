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
 * Diagnostic (read-only): find adaptive quiz attempts whose question usage
 * holds more than one slot for the same question (Issue #6).
 *
 * Historical duplicate slots (created before the slot-reuse fix) cannot always
 * be reconstructed unambiguously, so this script only reports them; it performs
 * no repair. Use it to size the problem and to decide on a manual clean-up.
 *
 * Usage:
 *   php local/catquiz/cli/diagnose_duplicate_slots.php
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Attempts (by question usage) that hold the same question in more than one slot.
$sql = "SELECT qa.questionusageid AS uniqueid,
               qa.questionid,
               COUNT(*) AS slotcount
          FROM {question_attempts} qa
          JOIN {adaptivequiz_attempt} aa ON aa.uniqueid = qa.questionusageid
      GROUP BY qa.questionusageid, qa.questionid
        HAVING COUNT(*) > 1
      ORDER BY qa.questionusageid, qa.questionid";

$duplicates = $DB->get_records_sql($sql);

if (empty($duplicates)) {
    cli_writeln('No duplicate question slots found.');
    exit(0);
}

cli_writeln('Found duplicate question slots (question usage id / question id / slot count):');
$affectedusages = [];
foreach ($duplicates as $row) {
    $affectedusages[$row->uniqueid] = true;
    cli_writeln(sprintf('  uniqueid=%d  questionid=%d  slots=%d', $row->uniqueid, $row->questionid, $row->slotcount));
}

cli_writeln(sprintf(
    'Total: %d duplicate (usage, question) pairs across %d affected attempts.',
    count($duplicates),
    count($affectedusages)
));
cli_writeln('This is a read-only diagnostic; no changes were made.');

exit(0);
