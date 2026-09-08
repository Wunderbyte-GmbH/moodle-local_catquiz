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
 * The share of points per scale is computed, not left null.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\teststrategy\progress;

/**
 * The fraction column stayed null on every row although the data was there.
 *
 * validate() passed an empty array for the fraction map, so nothing ever reached
 * local_catquiz_attemptscale.fraction - while the per-item fractions sat in the
 * recorded responses the whole time.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\teststrategy\progress::get_fraction_for_scale
 */
final class scale_fraction_test extends advanced_testcase {
    /**
     * Builds a progress with the given responses.
     *
     * @param array $fractions questionid => fraction
     * @param array $pilots questionids treated as pilot items
     * @return progress
     */
    private function make_progress(array $fractions, array $pilots = []): progress {
        $progress = progress::load(
            random_int(800000, 899999),
            'mod_adaptivequiz',
            1,
            (object) [
                'catquiz_catscales' => 1,
                'maxquestionspertest' => 10,
                'minquestionspertest' => 1,
            ]
        );

        $responses = [];
        $played = [];

        foreach ($fractions as $questionid => $fraction) {
            $responses[$questionid] = ['fraction' => $fraction];
            $played[$questionid] = (object) [
                'id' => $questionid,
                'is_pilot' => in_array($questionid, $pilots, true),
            ];
        }

        $set = function ($name, $value) use ($progress) {
            $property = new \ReflectionProperty(progress::class, $name);
            $property->setAccessible(true);
            $property->setValue($progress, $value);
        };

        $set('responses', $responses);
        $set('playedquestions', $played);

        return $progress;
    }

    /**
     * The mean of the per-item fractions is returned.
     *
     * @return void
     */
    public function test_fraction_is_the_mean_of_the_items(): void {
        $this->resetAfterTest();

        $progress = $this->make_progress([11 => 1.0, 12 => 0.0, 13 => 0.5]);

        $this->assertEqualsWithDelta(
            0.5,
            $progress->get_fraction_for_scale(),
            0.0001,
            'One right, one wrong and one half gives one half.'
        );
    }

    /**
     * Pilot items do not count, exactly as for N.
     *
     * @return void
     */
    public function test_pilot_items_are_excluded(): void {
        $this->resetAfterTest();

        // The pilot answer would drag the mean to 0.5 if it counted.
        $progress = $this->make_progress([11 => 1.0, 12 => 0.0], [12]);

        $this->assertEqualsWithDelta(
            1.0,
            $progress->get_fraction_for_scale(),
            0.0001,
            'The fraction has to use the same population as N.'
        );
    }

    /**
     * Nothing answered gives null, not zero.
     *
     * "No productive answer" and "every answer wrong" are different statements, and a
     * column that cannot tell them apart is worse than an empty one.
     *
     * @return void
     */
    public function test_no_countable_answer_gives_null(): void {
        $this->resetAfterTest();

        $this->assertNull($this->make_progress([])->get_fraction_for_scale());
        $this->assertNull($this->make_progress([11 => 1.0], [11])->get_fraction_for_scale());
    }

    /**
     * The validator hands the map on instead of an empty array.
     *
     * The other tests would keep passing if validate() still passed [], because they
     * call the accessor directly - which is the defect this file exists for.
     *
     * @return void
     */
    public function test_validator_passes_the_fractions_on(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/local/result/attempt_result_validator.php'
        );

        $this->assertStringContainsString(
            'get_fraction_for_scale',
            $source,
            'The value has to be computed where N is computed.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/from_personabilities\(\s*\$personabilities,\s*\$sebyscale,\s*\$nbyscale,\s*\[\]/',
            $source,
            'An empty array here is what left the column null on every row.'
        );
    }
}
