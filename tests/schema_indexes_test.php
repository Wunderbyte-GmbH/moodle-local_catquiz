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
 * Issue #25: database indexes for attempts, progress and scale queries.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use dml_exception;
use xmldb_index;
use xmldb_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/db/upgrade.php');

/**
 * Verifies the schema indexes and the duplicate cleanup of issue #25.
 *
 * These tests run against the live test database, so they check the schema that
 * Moodle actually installed - not the XML that describes it. A mistake in
 * install.xml (such as the index named "timecreated" that indexed instanceid)
 * would otherwise stay invisible.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_catquiz_upgrade_remove_duplicates
 */
final class schema_indexes_test extends advanced_testcase {
    /**
     * Data provider of the indexes issue #25 requires.
     *
     * @return array
     */
    public static function index_provider(): array {
        return [
            'attempts: time range filtering' => [
                'local_catquiz_attempts',
                'timecreated',
                ['timecreated'],
                false,
            ],
            'attempts: statistics access pattern' => [
                'local_catquiz_attempts',
                'contextid_scaleid_userid_attemptid',
                ['contextid', 'scaleid', 'userid', 'attemptid'],
                false,
            ],
            'personparams: one parameter per user, context and scale' => [
                'local_catquiz_personparams',
                'userid_contextid_catscaleid',
                ['userid', 'contextid', 'catscaleid'],
                true,
            ],
            'progress: one row per attempt' => [
                'local_catquiz_progress',
                'attemptid',
                ['attemptid'],
                true,
            ],
            'items: lookup by scale and component' => [
                'local_catquiz_items',
                'catscaleid_componentname_componentid',
                ['catscaleid', 'componentname', 'componentid'],
                false,
            ],
            'items: join to the active parameter' => [
                'local_catquiz_items',
                'catscaleid_activeparamid',
                ['catscaleid', 'activeparamid'],
                false,
            ],
        ];
    }

    /**
     * Each required index exists in the installed database.
     *
     * @dataProvider index_provider
     * @param string $tablename
     * @param string $indexname
     * @param string[] $fields
     * @param bool $unique
     * @return void
     */
    public function test_required_index_exists(string $tablename, string $indexname, array $fields, bool $unique): void {
        global $DB;

        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        $table = new xmldb_table($tablename);
        $index = new xmldb_index(
            $indexname,
            $unique ? XMLDB_INDEX_UNIQUE : XMLDB_INDEX_NOTUNIQUE,
            $fields
        );

        $this->assertTrue(
            $dbman->index_exists($table, $index),
            sprintf('Index %s on %s (%s) is missing.', $indexname, $tablename, implode(', ', $fields))
        );
    }

    /**
     * The index named "timecreated" indexes timecreated, not instanceid.
     *
     * This is the concrete defect issue #25 reports: the index existed, so a plain
     * "does an index called timecreated exist" check passed, while it actually
     * duplicated the instanceid index and left time-range filters unindexed.
     *
     * @return void
     */
    public function test_timecreated_index_covers_the_intended_field(): void {
        global $DB;

        $this->resetAfterTest();

        $columnsets = self::index_column_sets('local_catquiz_attempts');

        // The point of the fix: an index on timecreated must exist at all. Before the
        // fix none did - the index carrying that name indexed instanceid.
        $this->assertContains(
            ['timecreated'],
            $columnsets,
            'No index covers the timecreated field.'
        );

        // And instanceid must be covered exactly once. Previously two indexes
        // ("instanceid" and the misnamed "timecreated") covered the same field.
        $instanceidindexes = array_filter($columnsets, fn($cols) => $cols === ['instanceid']);
        $this->assertCount(
            1,
            $instanceidindexes,
            'instanceid should be covered by exactly one index, not duplicated.'
        );
    }

    /**
     * Returns the column lists of all indexes on a table, as reported by the database.
     *
     * Moodle's index_exists() matches on fields rather than on the index name, so it
     * cannot tell an index that carries the wrong name from a correct one. Reading the
     * actual metadata can.
     *
     * @param string $tablename
     * @return array[] List of column name arrays.
     */
    private static function index_column_sets(string $tablename): array {
        global $DB;

        $sets = [];
        foreach ($DB->get_indexes($tablename) as $index) {
            $sets[] = array_values($index['columns']);
        }

        return $sets;
    }

    /**
     * The database rejects a second person parameter for the same user, context and scale.
     *
     * @return void
     */
    public function test_personparams_uniqueness_is_enforced(): void {
        global $DB;

        $this->resetAfterTest();

        $row = (object) [
            'userid' => 42,
            'contextid' => 7,
            'catscaleid' => 3,
            'ability' => 0.5,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $DB->insert_record('local_catquiz_personparams', $row);

        $this->expectException(dml_exception::class);
        $DB->insert_record('local_catquiz_personparams', $row);
    }

    /**
     * The database rejects a second progress row for the same attempt.
     *
     * @return void
     */
    public function test_progress_uniqueness_is_enforced(): void {
        global $DB;

        $this->resetAfterTest();

        $row = (object) [
            'userid' => 42,
            'attemptid' => 12345,
            'json' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $DB->insert_record('local_catquiz_progress', $row);

        $this->expectException(dml_exception::class);
        $DB->insert_record('local_catquiz_progress', $row);
    }

    /**
     * The cleanup keeps the newest row of each duplicate group and deletes the rest.
     *
     * Uses a table without the unique index so that duplicates can be created at all -
     * this exercises the helper itself, which during a real upgrade runs before the
     * index is added.
     *
     * @return void
     */
    public function test_duplicate_cleanup_keeps_the_newest_row(): void {
        global $DB;

        $this->resetAfterTest();

        $ids = [];
        foreach ([1.0, 2.0, 3.0] as $ability) {
            $ids[] = $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => 99,
                'contextid' => 5,
                'scaleid' => 8,
                'courseid' => 2,
                'component' => 'mod_adaptivequiz',
                'personability_after_attempt' => $ability,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        // An unrelated group that must survive untouched.
        $other = $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 100,
            'contextid' => 5,
            'scaleid' => 8,
            'courseid' => 2,
            'component' => 'mod_adaptivequiz',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // The helper reports its cleanup via mtrace; capture it so the test does not
        // count as risky, and assert that the report actually names what was removed.
        ob_start();
        $deleted = local_catquiz_upgrade_remove_duplicates(
            'local_catquiz_attempts',
            ['userid', 'contextid', 'scaleid']
        );
        $output = ob_get_clean();

        $this->assertEquals(2, $deleted);
        $this->assertStringContainsString('removed 2 duplicate row(s)', $output);
        $this->assertFalse($DB->record_exists('local_catquiz_attempts', ['id' => $ids[0]]));
        $this->assertFalse($DB->record_exists('local_catquiz_attempts', ['id' => $ids[1]]));
        $this->assertTrue($DB->record_exists('local_catquiz_attempts', ['id' => $ids[2]]));
        $this->assertTrue($DB->record_exists('local_catquiz_attempts', ['id' => $other]));
    }

    /**
     * The cleanup leaves a table without duplicates completely alone.
     *
     * @return void
     */
    public function test_duplicate_cleanup_is_a_no_op_without_duplicates(): void {
        global $DB;

        $this->resetAfterTest();

        $id = $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 101,
            'contextid' => 5,
            'scaleid' => 8,
            'courseid' => 2,
            'component' => 'mod_adaptivequiz',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        ob_start();
        $deleted = local_catquiz_upgrade_remove_duplicates(
            'local_catquiz_attempts',
            ['userid', 'contextid', 'scaleid']
        );
        $output = ob_get_clean();

        $this->assertEquals(0, $deleted);
        // Where there is nothing to clean up there is nothing to report: the step
        // stays silent instead of adding a line to the upgrade log.
        $this->assertSame('', trim($output));
        $this->assertTrue($DB->record_exists('local_catquiz_attempts', ['id' => $id]));
    }

    /**
     * Rows carrying NULL in an indexed field are never deleted.
     *
     * A unique index constrains nothing when one of its columns is NULL: both
     * PostgreSQL and MariaDB accept any number of such rows. GROUP BY, in contrast,
     * folds all NULLs into one group. Cleaning up by grouping alone would therefore
     * delete rows the index would have accepted, which is data loss for no gain.
     * contextid in personparams is nullable, so this is not a theoretical case.
     *
     * @return void
     */
    public function test_cleanup_keeps_rows_with_null_in_an_indexed_field(): void {
        global $DB;

        $this->resetAfterTest();

        $row = [
            'userid' => 4242,
            'contextid' => null,
            'catscaleid' => 7,
            'ability' => 0.1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $first = $DB->insert_record('local_catquiz_personparams', (object) $row);
        $row['ability'] = 0.2;
        $second = $DB->insert_record('local_catquiz_personparams', (object) $row);

        ob_start();
        $deleted = local_catquiz_upgrade_remove_duplicates(
            'local_catquiz_personparams',
            ['userid', 'contextid', 'catscaleid']
        );
        ob_end_clean();

        $this->assertEquals(0, $deleted, 'NULL groups must not be treated as duplicates.');
        $this->assertTrue($DB->record_exists('local_catquiz_personparams', ['id' => $first]));
        $this->assertTrue($DB->record_exists('local_catquiz_personparams', ['id' => $second]));
    }

    /**
     * No table carries two indexes over the same column list.
     *
     * XMLDB creates an index for every foreign key, so declaring an <INDEX> on a
     * column that a <KEY TYPE="foreign"> already covers produces two physically
     * identical indexes. Both then have to be maintained on every write - pure cost
     * on tables that are written on every single answer. This guard keeps the
     * declarations from drifting back in.
     *
     * @return void
     */
    public function test_no_redundant_indexes_remain(): void {
        global $DB;

        $this->resetAfterTest();

        $tables = [
            'local_catquiz_attempts',
            'local_catquiz_personparams',
            'local_catquiz_progress',
            'local_catquiz_items',
            'local_catquiz_tests',
            'local_catquiz_catscales',
            'local_catquiz_subscriptions',
            'local_catquiz_itemparams',
        ];

        $redundant = [];
        foreach ($tables as $table) {
            $bycolumns = [];
            foreach ($DB->get_indexes($table) as $name => $info) {
                $bycolumns[implode(',', $info['columns'])][] = $name;
            }
            foreach ($bycolumns as $columns => $names) {
                if (count($names) > 1) {
                    $redundant[] = sprintf('%s(%s): %s', $table, $columns, implode(', ', $names));
                }
            }
        }

        $this->assertSame(
            [],
            $redundant,
            "Redundant indexes found:\n" . implode("\n", $redundant)
        );
    }
}
