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
 * Issue #25: no column is covered by two physically identical indexes.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * Verifies that redundant indexes are absent and that the cleanup removes them.
 *
 * A foreign key is implemented by XMLDB as an index on the referencing column. Where
 * install.xml additionally declared an <INDEX> on the same column, two identical
 * indexes had to be maintained on every write - on tables written on every answer.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_catquiz_upgrade_drop_duplicate_indexes
 */
final class redundant_indexes_test extends advanced_testcase {
    /**
     * Tables whose indexes must not contain duplicates.
     *
     * @return array[]
     */
    public static function table_provider(): array {
        return [
            'attempts' => ['local_catquiz_attempts'],
            'personparams' => ['local_catquiz_personparams'],
            'progress' => ['local_catquiz_progress'],
            'items' => ['local_catquiz_items'],
            'catscales' => ['local_catquiz_catscales'],
            'itemparams' => ['local_catquiz_itemparams'],
            'tests' => ['local_catquiz_tests'],
            'subscriptions' => ['local_catquiz_subscriptions'],
        ];
    }

    /**
     * No two indexes on a table cover exactly the same columns.
     *
     * @dataProvider table_provider
     * @param string $tablename
     * @return void
     */
    public function test_no_duplicate_indexes(string $tablename): void {
        global $DB;

        $this->resetAfterTest();

        $seen = [];
        foreach ($DB->get_indexes($tablename) as $name => $info) {
            $key = implode(',', $info['columns']);
            $seen[$key][] = $name;
        }

        foreach ($seen as $columns => $names) {
            $this->assertCount(
                1,
                $names,
                "Redundante Indizes auf $tablename($columns): " . implode(', ', $names)
            );
        }
    }

    /**
     * The uniqueness guarantee on progress.attemptid survives the cleanup.
     *
     * @return void
     */
    public function test_progress_attemptid_stays_unique(): void {
        global $DB;

        $this->resetAfterTest();

        $unique = false;
        foreach ($DB->get_indexes('local_catquiz_progress') as $info) {
            if (array_values($info['columns']) === ['attemptid'] && !empty($info['unique'])) {
                $unique = true;
            }
        }

        $this->assertTrue($unique, 'progress.attemptid must keep a unique index.');
    }

    /**
     * The cleanup drops the extra index and keeps the unique one.
     *
     * Recreates the old situation - a second, plain index next to the unique one -
     * and checks that exactly the redundant index goes away. Without this the test
     * above could pass simply because the duplicate never existed here.
     *
     * @return void
     */
    public function test_cleanup_keeps_the_unique_index(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/catquiz/db/upgrade.php');

        // Create the duplicate directly: $dbman->add_index() is guarded by
        // index_exists(), which matches on columns alone and would consider the
        // existing unique index a match, so nothing would be created and the test
        // would silently verify nothing.
        $prefixed = $DB->get_prefix() . 'local_catquiz_progress';
        $indexname = $DB->get_prefix() . 'locacatqprog_attred_ix';
        $DB->change_database_structure(
            $DB->get_dbfamily() === 'mysql'
                ? "CREATE INDEX $indexname ON $prefixed (attemptid)"
                : "CREATE INDEX $indexname ON $prefixed (attemptid)"
        );

        $before = 0;
        foreach ($DB->get_indexes('local_catquiz_progress') as $info) {
            if (array_values($info['columns']) === ['attemptid']) {
                $before++;
            }
        }
        $this->assertEquals(2, $before, 'Setup must produce two indexes on attemptid.');

        ob_start();
        $dropped = local_catquiz_upgrade_drop_duplicate_indexes(
            'local_catquiz_progress',
            ['attemptid'],
            true
        );
        ob_end_clean();

        $this->assertEquals(1, $dropped);

        $remaining = [];
        foreach ($DB->get_indexes('local_catquiz_progress') as $name => $info) {
            if (array_values($info['columns']) === ['attemptid']) {
                $remaining[$name] = !empty($info['unique']);
            }
        }

        $this->assertCount(1, $remaining, 'Exactly one index must remain.');
        $this->assertTrue(reset($remaining), 'The surviving index must be the unique one.');
    }
}
