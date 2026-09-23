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
 * Searching inside question texts without carrying them in the list (issue #20).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\table\catscalequestions_table;

/**
 * Verifies the dedicated question text search.
 *
 * The list queries deliberately no longer select the question text, so the table's
 * own free text search cannot reach it. Searching is restored through a small
 * dedicated query that resolves matching ids; the text itself never travels with
 * the list rows.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\table\catscalequestions_table::resolve_questiontext_matches
 * @covers     \local_catquiz\table\catscalequestions_table::apply_questiontext_search
 */
final class questiontext_search_test extends advanced_testcase {
    /**
     * Inserts a question with the given text.
     *
     * @param string $name
     * @param string $text
     * @return int
     */
    private function make_question(string $name, string $text): int {
        global $DB;

        return (int) $DB->insert_record('question', (object) [
            'name' => $name,
            'questiontext' => $text,
            'questiontextformat' => FORMAT_HTML,
            'qtype' => 'truefalse',
            'generalfeedback' => '',
            'generalfeedbackformat' => FORMAT_HTML,
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
            'modifiedby' => 2,
        ]);
    }

    /**
     * A term inside the question text finds exactly the matching questions.
     *
     * @return void
     */
    public function test_search_finds_questions_by_their_text(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $match = $this->make_question('Q1', 'The mitochondrion is the powerhouse');
        $other = $this->make_question('Q2', 'Completely unrelated content');

        $ids = catscalequestions_table::resolve_questiontext_matches('mitochondrion');

        $this->assertIsArray($ids);
        $this->assertContains($match, $ids);
        $this->assertNotContains($other, $ids);
    }

    /**
     * The search is case insensitive and matches substrings.
     *
     * @return void
     */
    public function test_search_is_case_insensitive_and_matches_substrings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $match = $this->make_question('Q1', 'The Mitochondrion is the powerhouse');

        $this->assertContains($match, catscalequestions_table::resolve_questiontext_matches('MITOCHONDRION'));
        $this->assertContains($match, catscalequestions_table::resolve_questiontext_matches('chondri'));
    }

    /**
     * A term nobody uses returns an empty list, not everything.
     *
     * @return void
     */
    public function test_search_without_matches_returns_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->make_question('Q1', 'Some content');

        $this->assertSame([], catscalequestions_table::resolve_questiontext_matches('zzzznotfoundzzzz'));
    }

    /**
     * Wildcards in the search term are escaped, not interpreted.
     *
     * The underscore is the discriminating case: unescaped it matches any single
     * character, so a search for "Disc_unt" would also return "Discount". A percent
     * sign would not prove this - "%50%%" still only matches rows containing "50".
     *
     * @return void
     */
    public function test_wildcards_in_the_search_term_are_escaped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $literal = $this->make_question('Q1', 'A Disc_unt with a literal underscore');
        $wildcardhit = $this->make_question('Q2', 'A Discount without one');

        $ids = catscalequestions_table::resolve_questiontext_matches('Disc_unt');

        $this->assertContains($literal, $ids);
        $this->assertNotContains(
            $wildcardhit,
            $ids,
            'An underscore must be a literal character, not a single-character wildcard.'
        );
    }

    /**
     * Beyond the cap the search declines to build a huge IN() clause.
     *
     * @return void
     */
    public function test_too_many_matches_are_reported_as_null(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        for ($i = 0; $i <= catscalequestions_table::QUESTIONTEXT_SEARCH_LIMIT; $i++) {
            $this->make_question("Q$i", "common marker text $i");
        }

        $this->assertNull(
            catscalequestions_table::resolve_questiontext_matches('common marker'),
            'Above the cap the search must decline instead of returning every id.'
        );
    }
}
