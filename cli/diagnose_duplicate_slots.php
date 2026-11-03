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
 * Since issue #125 it reports every administration of the question - slots, question attempts and
 * their states - through local\attempt\administration_history, the same view the review page of an
 * attempt shows. Output is CSV, one line per attempt and question.
 *
 * Usage:
 *   php local/catquiz/cli/diagnose_duplicate_slots.php [--instance=ID]
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\local\attempt\administration_history;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['instance' => null, 'help' => false],
    ['i' => 'instance', 'h' => 'help']
);

if ($options['help'] || $unrecognised) {
    cli_writeln('Lists attempts in which a question was administered more than once. Read-only.');
    cli_writeln('');
    cli_writeln('Options:');
    cli_writeln('  -i, --instance=ID  Only attempts of this adaptive quiz instance.');
    cli_writeln('  -h, --help         This help.');
    exit($unrecognised ? 1 : 0);
}

$instanceid = $options['instance'] === null ? null : (int) $options['instance'];
$found = administration_history::find_duplicate_administrations($instanceid);

cli_writeln('attemptid,userid,instance,usageid,questionid,slots,questionattemptids,states');
foreach ($found as $row) {
    cli_writeln(implode(',', [
        $row->attemptid,
        $row->userid,
        $row->instance,
        $row->usageid,
        $row->questionid,
        '"' . implode(' ', $row->slots) . '"',
        '"' . implode(' ', $row->questionattemptids) . '"',
        '"' . implode(' ', $row->states) . '"',
    ]));
}
cli_writeln('');
cli_writeln(count($found) . ' attempt(s) and question(s) with more than one administration.');
