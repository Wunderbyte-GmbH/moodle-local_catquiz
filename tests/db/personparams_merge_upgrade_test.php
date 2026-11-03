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

namespace local_catquiz;

use advanced_testcase;
use xmldb_field;
use xmldb_index;
use xmldb_key;
use xmldb_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/db/upgradelib.php');

/**
 * The upgrade that merges local_catquiz_attemptscale into local_catquiz_personparams works on data.
 *
 * PHPUnit installs the current schema, in which the scale table no longer exists. This test builds
 * it again as it was on the 1.2 line - with the column catattemptid - fills both tables, restores the
 * old unique index on the person table, and runs the upgrade. A first version of the step read a
 * column name that only exists on the migration branch and would have stopped on every 1.2 site.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::local_catquiz_merge_attemptscale_into_personparams
 */
final class personparams_merge_upgrade_test extends advanced_testcase {
    /**
     * Builds local_catquiz_attemptscale as it was, with the given name for the attempt reference.
     *
     * @param string $reference catattemptid on the 1.2 line, attemptid where it had been renamed.
     */
    private function create_old_scale_table(string $reference): void {
        global $DB;

        $table = new xmldb_table('local_catquiz_attemptscale');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field($reference, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('catscaleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('score', XMLDB_TYPE_NUMBER, '10, 4');
        $table->add_field('standarderror', XMLDB_TYPE_NUMBER, '10, 4');
        $table->add_field('n', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('fraction', XMLDB_TYPE_NUMBER, '10, 4');
        $table->add_field('isprimary', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('isvalid', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('resultsource', XMLDB_TYPE_CHAR, '20');
        $table->add_field('validationstatus', XMLDB_TYPE_CHAR, '255');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $DB->get_manager()->create_table($table);
    }

    /**
     * Puts the person table back to one row per user, context and scale.
     */
    private function restore_the_old_unique_index(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_catquiz_personparams');
        $index = new xmldb_index('userid_contextid_catscaleid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'contextid', 'catscaleid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $dbman->add_index($table, new xmldb_index(
            'userid_contextid_catscaleid',
            XMLDB_INDEX_UNIQUE,
            ['userid', 'contextid', 'catscaleid']
        ));
    }

    /**
     * Existing rows of both tables end up in the person table, and the scale table is gone.
     *
     * @dataProvider reference_names
     * @param string $reference Name of the attempt reference in the old scale table.
     */
    public function test_existing_rows_are_merged(string $reference): void {
        global $DB;

        $this->resetAfterTest();
        $this->create_old_scale_table($reference);
        $this->restore_the_old_unique_index();

        // A person parameter written before validity existed.
        $DB->insert_record('local_catquiz_personparams', (object) [
            'userid' => 2, 'catscaleid' => 5, 'contextid' => 9, 'ability' => 0.2,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        // Two scale results for the same person and scale, from two attempts.
        foreach ([[700, 0.5], [701, 0.9]] as [$attempt, $score]) {
            $DB->insert_record('local_catquiz_attemptscale', (object) [
                $reference => $attempt, 'userid' => 2, 'contextid' => 9, 'catscaleid' => 5,
                'score' => $score, 'standarderror' => 0.3, 'n' => 6, 'fraction' => 0.5,
                'isprimary' => 1, 'isvalid' => 1, 'resultsource' => 'current', 'timecreated' => time(),
            ]);
        }

        $result = local_catquiz_merge_attemptscale_into_personparams();

        $this->assertSame(['legacy' => 1, 'carried' => 2], $result);
        $this->assertFalse(
            $DB->get_manager()->table_exists('local_catquiz_attemptscale'),
            'The scale table must be gone after the merge.'
        );
        $this->assertEquals(
            3,
            $DB->count_records('local_catquiz_personparams', ['userid' => 2, 'catscaleid' => 5]),
            'Three estimates for the same person and scale - the old one-row rule must be gone.'
        );
        $this->assertEquals(
            'legacy',
            $DB->get_field('local_catquiz_personparams', 'resultsource', ['ability' => 0.2])
        );
        $this->assertEquals(
            701,
            (int) $DB->get_field('local_catquiz_personparams', 'attemptid', ['ability' => 0.9]),
            'The attempt reference must be carried over from the column it was stored in.'
        );
    }

    /**
     * Both names the attempt reference has carried.
     *
     * @return array
     */
    public static function reference_names(): array {
        return [
            'catattemptid - the 1.2 line' => ['catattemptid'],
            'attemptid - renamed first' => ['attemptid'],
        ];
    }
}
