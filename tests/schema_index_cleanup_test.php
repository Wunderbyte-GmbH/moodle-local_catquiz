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
 * Issue #25: the upgrade removes redundant indexes without losing guarantees.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use xmldb_field;
use xmldb_key;
use xmldb_table;

/**
 * Verifies the duplicate index cleanup used by the issue #25 upgrade step.
 *
 * These tests run against a scratch table rather than the real ones, for two
 * reasons. PHPUnit rolls back data but not schema changes, so creating indexes on
 * a shared table would leak into every later test in the run. And the surviving
 * index must be provable regardless of the order the database reports indexes in -
 * an order no engine guarantees - which is only controllable when the test creates
 * the table itself.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_catquiz_upgrade_drop_duplicate_indexes
 */
final class schema_index_cleanup_test extends advanced_testcase {
    /** @var string Scratch table name without prefix. */
    private const TABLE = 'local_catquiz_idxprobe';

    /**
     * Creates the scratch table.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        global $DB;
        $dbman = $DB->get_manager();

        $table = new xmldb_table(self::TABLE);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $table->addField(new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE));
        $table->addField(new xmldb_field('refid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'));
        $table->addKey(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']));
        $dbman->create_table($table);
    }

    /**
     * Drops the scratch table again.
     *
     * @return void
     */
    protected function tearDown(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table(self::TABLE);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        parent::tearDown();
    }

    /**
     * Creates a raw index, bypassing XMLDB so the creation order is ours.
     *
     * @param string $indexname
     * @param bool $unique
     * @return void
     */
    private function add_index(string $indexname, bool $unique = false): void {
        global $DB;

        $prefixed = $DB->get_prefix() . self::TABLE;
        $u = $unique ? 'UNIQUE ' : '';
        $DB->change_database_structure("CREATE {$u}INDEX $indexname ON $prefixed (refid)");
    }

    /**
     * Returns [name => isunique] for every index on refid, in reported order.
     *
     * @return array<string, bool>
     */
    private function indexes_on_refid(): array {
        global $DB;

        $found = [];
        foreach ($DB->get_indexes(self::TABLE) as $name => $info) {
            if (array_values($info['columns']) === ['refid']) {
                $found[$name] = !empty($info['unique']);
            }
        }

        return $found;
    }

    /**
     * Two identical indexes collapse into one.
     *
     * @return void
     */
    public function test_duplicate_index_is_dropped(): void {
        global $CFG;

        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/catquiz/db/upgrade.php');

        $this->add_index('catquiz_probe_a');
        $this->add_index('catquiz_probe_b');
        $this->assertCount(2, $this->indexes_on_refid());

        ob_start();
        $dropped = local_catquiz_upgrade_drop_duplicate_indexes(self::TABLE, ['refid']);
        ob_end_clean();

        $this->assertEquals(1, $dropped);
        $this->assertCount(1, $this->indexes_on_refid());
    }

    /**
     * The unique index survives, whatever order the engine reports indexes in.
     *
     * This is the dangerous case of the upgrade: on progress.attemptid a uniqueness
     * guarantee rides on the column. Dropping by column list alone could remove the
     * unique index and leave the plain duplicate behind, silently losing the
     * one-row-per-attempt guarantee.
     *
     * The plain index is created first so that a "keep whichever comes first"
     * implementation gets it wrong. Whether that actually produces the adverse
     * order is engine specific: MariaDB reports unique indexes first regardless of
     * creation order, so there the assertion holds trivially, while PostgreSQL
     * reports them in creation order and the protection is genuinely exercised.
     * The reported order is logged so it is visible which case was tested.
     *
     * @return void
     */
    public function test_unique_index_survives_the_cleanup(): void {
        global $CFG;

        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/catquiz/db/upgrade.php');

        $this->add_index('catquiz_probe_plain');
        $this->add_index('catquiz_probe_unique', true);

        $before = $this->indexes_on_refid();
        $this->assertCount(2, $before);
        $adverse = reset($before) === false;

        ob_start();
        $dropped = local_catquiz_upgrade_drop_duplicate_indexes(self::TABLE, ['refid'], true);
        ob_end_clean();

        $after = $this->indexes_on_refid();
        $this->assertEquals(1, $dropped);
        $this->assertCount(1, $after);
        $this->assertTrue(
            reset($after),
            sprintf(
                'The surviving index must be the unique one (plain index reported first: %s).',
                $adverse ? 'yes' : 'no'
            )
        );
    }

    /**
     * Without a unique index present, nothing is touched and it is reported.
     *
     * @return void
     */
    public function test_missing_unique_index_leaves_everything_alone(): void {
        global $CFG;

        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/catquiz/db/upgrade.php');

        $this->add_index('catquiz_probe_a');
        $this->add_index('catquiz_probe_b');

        ob_start();
        $dropped = local_catquiz_upgrade_drop_duplicate_indexes(self::TABLE, ['refid'], true);
        $output = ob_get_clean();

        $this->assertEquals(0, $dropped);
        $this->assertCount(2, $this->indexes_on_refid());
        $this->assertStringContainsString('no unique index', $output);
    }

    /**
     * A single index is left alone.
     *
     * @return void
     */
    public function test_cleanup_is_a_no_op_without_duplicates(): void {
        global $CFG;

        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/catquiz/db/upgrade.php');

        $this->add_index('catquiz_probe_only');

        ob_start();
        $dropped = local_catquiz_upgrade_drop_duplicate_indexes(self::TABLE, ['refid']);
        ob_end_clean();

        $this->assertEquals(0, $dropped);
        $this->assertCount(1, $this->indexes_on_refid());
    }
}
