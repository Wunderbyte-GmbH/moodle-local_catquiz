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
 * Issue #26: the runtime pool loads fewer columns than the manager interface.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;

/**
 * The lean column set has to stay lean, and it has to stay correct.
 *
 * One query serves two consumers: the manager interface, which displays and filters
 * by question, category and scale names, and the runtime selection, which works on
 * ids and item parameters. The selection caches its rows, so every column it does not
 * need is carried in the cache for the lifetime of the entry.
 *
 * Two ways this can break, and both are silent. The runtime path could stop passing
 * the lean flag - nothing fails, the cache just grows. Or the lean set could lose a
 * column the selection actually reads - which surfaces much later, as a model without
 * its parameters.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::return_sql_for_catscalequestions
 */
final class runtime_pool_columns_test extends advanced_testcase {
    /**
     * Returns the column names of one variant of the pool query.
     *
     * @param bool $lean
     * @return array
     */
    /**
     * Returns the SELECT clause of one variant of the pool query.
     *
     * The clause itself is examined rather than parsed into column names: a parser
     * that misses an alias reports "no difference" and the test passes while proving
     * nothing. Asking whether a name appears in the statement cannot fail that way.
     *
     * @param bool $lean
     * @return string
     */
    private function select_of(bool $lean): string {
        global $DB, $USER;

        // A scale that exists: the query resolves subscales, and an unknown id ends in
        // get_in_or_equal() with an empty array - the test would then fail on its own
        // fixture rather than on the column sets.
        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Column set context',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => $now - 100,
            'endtimestamp' => $now + 10000,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0,
            'name' => 'Column set scale',
            'label' => 'COL1',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        [$select] = catquiz::return_sql_for_catscalequestions(
            [$scaleid],
            $contextid,
            [],
            (int) $USER->id,
            null,
            null,
            $lean
        );

        return $select;
    }

    /**
     * The lean set omits the three name columns and nothing else.
     *
     * @return void
     */
    public function test_lean_set_omits_only_the_names(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $full = $this->select_of(false);
        $lean = $this->select_of(true);

        // The manager path selects '*': it displays and filters by the names, and
        // listing them explicitly would only invite them to drift from the joins.
        $this->assertStringContainsString(
            '*',
            $full,
            'The manager path takes every column of the joined rows.'
        );

        foreach (['questionname', 'categoryname', 'catscalename'] as $name) {
            $this->assertStringNotContainsString(
                $name,
                $lean,
                "The selection never reads $name, and the runtime pool is cached - "
                    . 'every column it carries stays there for the life of the entry.'
            );
        }

        // The parameters the models read have to survive; dropping one of these
        // surfaces much later, as a model without its parameters.
        //
        // json is the one that is easy to overlook. It looks like payload - it is the
        // largest remaining text column - but the polytomous models take their
        // category thresholds out of it: grm reads
        // json_decode($record->json)['difficulties'], and model_item_param carries it
        // into every item. Dropping it would leave GRM, GGRM, PCM and GPCM without
        // their thresholds, and the failure would appear in the estimate rather than
        // at the query.
        foreach (
            [
            'model',
            'difficulty',
            'discrimination',
            'guessing',
            'json',
            'status',
            'usable',
            ] as $needed
        ) {
            $this->assertStringContainsString(
                $needed,
                $lean,
                "The selection or one of the models reads $needed."
            );
        }
    }

    /**
     * The runtime path asks for the lean set.
     *
     * If this call loses its flag nothing fails - the cache simply grows again, by
     * roughly 16 % per entry.
     *
     * @return void
     */
    public function test_runtime_path_requests_the_lean_set(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents($CFG->dirroot . '/local/catquiz/classes/catscale.php');

        $start = strpos($source, 'return_sql_for_catscalequestions(');
        $this->assertNotFalse($start, 'The runtime pool is loaded here.');

        $call = substr($source, $start, 260);

        $this->assertMatchesRegularExpression(
            '/\$orderby,\s*null,\s*true/',
            $call,
            'The runtime pool has to request the lean column set; without it the '
                . 'cached rows carry the display names for nothing.'
        );
    }
}
