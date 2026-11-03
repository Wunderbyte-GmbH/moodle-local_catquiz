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

namespace local_catquiz\local\statistics;

use advanced_testcase;
use local_catquiz\local\model\model_strategy;
use local_catquiz\teststrategy\feedback_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * What goes into the ability histograms is checked, not assumed (issue #150, re-audit 23.10.2026).
 *
 * The bars: a value that is no finite number - null, text, INF, NAN - landed in a bar after a cast to float
 * (null and text as 0). The test information line: an item whose model is not installed made
 * model_model::get_instance() return null against its return type - a TypeError that took the whole feedback
 * or statistics page down; an item whose information is not finite made the sum of every step not finite,
 * and the whole line was left out for one item.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\statistics\chart_output::counts_per_step
 * @covers \local_catquiz\teststrategy\feedback_helper::get_fisherinfos_of_items
 */
final class chart_input_test extends advanced_testcase {
    /** @var float[] Two bars, (−0.5, 0] and (0, 0.5], the first holding its lower edge. */
    private const STEPS = [-0.25, 0.25];

    /**
     * Only finite numbers reach a bar: null, text, INF and NAN are left out, numeric strings count.
     */
    public function test_a_value_that_is_no_number_reaches_no_bar(): void {
        $this->assertSame([1, 2], chart_output::counts_per_step(self::STEPS, [-0.3, 0.1, '0.2'], 0.25));
        $this->assertSame(
            [0, 0],
            chart_output::counts_per_step(self::STEPS, [null, '', 'abc', INF, -INF, NAN, true, false, []], 0.25),
            'Nothing that is no finite number - null and text would have counted as 0, in the bar of 0.'
        );
        // The lower edge of the first bar counts, values outside the bars do not.
        $this->assertSame([1, 0], chart_output::counts_per_step(self::STEPS, [-0.5, -0.51, 0.51], 0.25));
    }

    /**
     * An item as the runtime pool lists it.
     *
     * @param int $questionid
     * @param string|null $model
     * @param float $difficulty
     * @param float $discrimination
     * @return \stdClass
     */
    private function item(int $questionid, ?string $model, float $difficulty, float $discrimination): \stdClass {
        return (object) [
            'id' => $questionid, 'componentid' => $questionid, 'componentname' => 'question', 'contextid' => 1,
            'itemid' => $questionid, 'model' => $model, 'status' => LOCAL_CATQUIZ_STATUS_CALCULATED,
            'difficulty' => $difficulty, 'discrimination' => $discrimination, 'guessing' => 0.0, 'json' => '{}',
            'timecreated' => time(), 'timemodified' => time(),
        ];
    }

    /**
     * The test information of the items it can be computed for: an item whose model is not installed, or whose
     * information is not finite, is left out - not the page, not the line.
     */
    public function test_the_test_information_leaves_out_what_it_cannot_compute(): void {
        $this->resetAfterTest();
        $helper = new feedback_helper();
        $models = model_strategy::get_installed_models();
        $this->assertArrayNotHasKey('notinstalled', $models);
        $steps = [-1.0, 0.0, 1.0];
        $valid = $this->item(1, 'raschbirnbaum', 0.0, 1.0);
        $expected = $helper->get_fisherinfos_of_items([$valid], $models, $steps);
        $this->assertCount(3, $expected);

        $items = [
            $valid,
            $this->item(2, 'notinstalled', 0.0, 1.0),
            // The square of the discrimination is INF; at a saturated probability INF * 0 is NAN.
            $this->item(3, 'raschbirnbaum', 0.0, 1e200),
            $this->item(4, null, 0.0, 1.0),
        ];
        $infos = $helper->get_fisherinfos_of_items($items, $models, $steps);
        $this->assertSame(array_keys($expected), array_keys($infos), 'One value per step.');
        foreach ($expected as $key => $value) {
            $this->assertEqualsWithDelta($value, $infos[$key], 1e-12, "Step $key: only the valid item.");
            $this->assertTrue(is_finite($infos[$key]));
        }
        $messages = array_map(fn($debug) => $debug->message, $this->getDebuggingMessages());
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('model notinstalled is not installed', $messages[0], 'Left out before it is read:'
            . ' reading it raised a PHP warning, then an error.');
        $this->assertStringContainsString('information', $messages[1]);
        $this->resetDebugging();
    }
}
