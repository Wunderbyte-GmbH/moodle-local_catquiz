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

namespace local_catquiz\teststrategy\feedbackgenerator;

use advanced_testcase;

/**
 * The feedback order: main scale first, the rest by the name of their scale.
 *
 * The ordering used to assume that the array key equals catscale.id, that an entry for the key
 * exists, and that the entry is an object (issue #102). A missing scale threw; a list-keyed array
 * sorted by whatever happened to sit at that offset.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\feedbackgenerator\customscalefeedback
 */
final class customscalefeedback_sorting_test extends advanced_testcase {
    /** @var array Feedback texts keyed by catscale id. */
    private array $feedback = [
        21 => 'feedback zebra',
        22 => 'feedback alpha',
        23 => 'feedback mango',
    ];

    /**
     * Scale records keyed by catscale id, as arrays.
     *
     * @return array
     */
    private function scales_as_arrays(): array {
        return [
            21 => ['id' => 21, 'name' => 'Zebra'],
            22 => ['id' => 22, 'name' => 'Alpha'],
            23 => ['id' => 23, 'name' => 'Mango'],
        ];
    }

    /**
     * An id-keyed array of arrays is the documented input.
     */
    public function test_id_keyed_array_of_arrays(): void {
        $sorted = customscalefeedback::sort_scalefeedback($this->feedback, $this->scales_as_arrays(), null);

        $this->assertSame(
            ['feedback alpha', 'feedback mango', 'feedback zebra'],
            array_values($sorted)
        );
    }

    /**
     * A numerically reindexed scale array must not change the order.
     *
     * This is the case the issue is about: with array_values() the offsets 0, 1, 2 no longer match
     * the ids 21, 22, 23, so the old code sorted by whichever scale happened to sit there.
     */
    public function test_reindexed_scales_do_not_reorder_the_feedback(): void {
        $sorted = customscalefeedback::sort_scalefeedback(
            $this->feedback,
            array_values($this->scales_as_arrays()),
            null
        );

        $this->assertSame(
            array_values(customscalefeedback::sort_scalefeedback($this->feedback, $this->scales_as_arrays(), null)),
            array_values($sorted),
            'A reindexed scale array changed the order of the feedback.'
        );
    }

    /**
     * Scale records may be objects as well as arrays.
     */
    public function test_stdclass_records(): void {
        $scales = array_map(fn($scale) => (object) $scale, $this->scales_as_arrays());

        $sorted = customscalefeedback::sort_scalefeedback($this->feedback, $scales, null);

        $this->assertSame(
            ['feedback alpha', 'feedback mango', 'feedback zebra'],
            array_values($sorted)
        );
    }

    /**
     * A scale without metadata sorts to the end instead of taking the page down.
     */
    public function test_missing_scale_metadata_falls_back(): void {
        $scales = $this->scales_as_arrays();
        unset($scales[21]);

        $sorted = customscalefeedback::sort_scalefeedback($this->feedback, $scales, null);

        $this->assertSame(
            ['feedback alpha', 'feedback mango', 'feedback zebra'],
            array_values($sorted),
            'The scale without metadata must sort to the end, not disappear or throw.'
        );
    }

    /**
     * A scale record without a name is treated like one that is missing entirely.
     */
    public function test_scale_without_a_name(): void {
        $scales = $this->scales_as_arrays();
        $scales[22] = ['id' => 22];

        $sorted = customscalefeedback::sort_scalefeedback($this->feedback, $scales, null);

        $this->assertSame('feedback alpha', array_values($sorted)[2]);
    }

    /**
     * The main scale comes first, whatever its name would suggest.
     */
    public function test_main_scale_comes_first(): void {
        $sorted = customscalefeedback::sort_scalefeedback($this->feedback, $this->scales_as_arrays(), 21);

        $this->assertSame(
            ['feedback zebra', 'feedback alpha', 'feedback mango'],
            array_values($sorted)
        );
    }

    /**
     * A main scale without feedback does not produce an empty entry.
     */
    public function test_main_scale_without_feedback(): void {
        $sorted = customscalefeedback::sort_scalefeedback($this->feedback, $this->scales_as_arrays(), 99);

        $this->assertSame(
            ['feedback alpha', 'feedback mango', 'feedback zebra'],
            array_values($sorted)
        );
    }

    /**
     * By name as people read it (issue #77): umlauts with their letter, case does not decide, numbers in
     * their natural order. Compared byte by byte, "Beta" and "Zulu" came before "alpha" and "Ärger" last.
     */
    public function test_names_sort_as_people_read_them(): void {
        $names = [31 => 'zulu', 32 => 'Ärger', 33 => 'alpha', 34 => 'Beta', 35 => 'Skala 10', 36 => 'Skala 2'];
        $scales = [];
        $feedback = [];
        foreach ($names as $id => $name) {
            $scales[$id] = ['id' => $id, 'name' => $name];
            $feedback[$id] = 'feedback ' . $name;
        }

        $sorted = customscalefeedback::sort_scalefeedback($feedback, $scales, null);

        $this->assertSame(
            ['feedback alpha', 'feedback Ärger', 'feedback Beta', 'feedback Skala 2', 'feedback Skala 10', 'feedback zulu'],
            array_values($sorted)
        );
    }

    /**
     * Several scales without a name come last, in the order of their id; an empty name counts as none.
     */
    public function test_scales_without_names_come_last_by_id(): void {
        $scales = [
            41 => ['id' => 41, 'name' => 'Zeta'],
            42 => ['id' => 42],
            43 => ['id' => 43, 'name' => ''],
            44 => ['id' => 44, 'name' => 'Alpha'],
        ];
        $feedback = [43 => 'third', 42 => 'second', 41 => 'zeta', 44 => 'alpha'];

        $sorted = customscalefeedback::sort_scalefeedback($feedback, $scales, null);

        $this->assertSame(['alpha', 'zeta', 'second', 'third'], array_values($sorted));
    }
}
