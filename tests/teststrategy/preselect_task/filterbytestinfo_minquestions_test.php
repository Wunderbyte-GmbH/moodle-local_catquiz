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
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task\filterbytestinfo;
use local_catquiz\teststrategy\progress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * Guards that filterbytestinfo respects the globally configured minimum number
 * of questions for the main scale.
 *
 * Regression for the "attempt ends after question 1" abort: at an extreme
 * starting ability the main scale's test information falls below the se_max
 * threshold, so the exclusion branch is reached. The earlier first-question fix
 * only required at least one played question (max(1, min_attempts_per_scale)),
 * so with min_attempts_per_scale = 0 the main scale could be deactivated right
 * after question 1 — ending the whole attempt even though catquiz_minquestions
 * demanded four. The fix additionally requires the global minimum (mirroring
 * filterbystandarderror). This test drives the number of played questions across
 * that boundary; it turns red if the global-minimum guard is removed.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\preselect_task\filterbytestinfo
 */
final class filterbytestinfo_minquestions_test extends advanced_testcase {
    /** @var int Main scale id used throughout the test. */
    private const SCALE = 1;

    /**
     * The main scale must not be excluded before the global minimum is reached.
     *
     * @param int $played Number of questions already played.
     * @param int $minimumquestions Globally configured minimum.
     * @param bool $mayexclude Whether deactivation is expected at this point.
     *
     * @dataProvider minquestions_provider
     *
     * @return void
     */
    public function test_main_scale_respects_global_minimum(int $played, int $minimumquestions, bool $mayexclude): void {
        $this->resetAfterTest();

        // Extreme ability so Fisher information (and thus test potential /
        // information) is effectively zero: the exclusion branch is reached.
        $ability = -50.0;
        $records = $this->item_records($played);

        $progress = $this->createMock(progress::class);
        $progress->method('get_abilities')->willReturn([self::SCALE => $ability]);
        $progress->method('without_pilots')->willReturnSelf();
        $progress->method('is_active_scale')->willReturn(true);
        $progress->method('is_dropped_scale')->willReturn(false);
        $progress->method('get_playedquestions')->willReturnCallback(
            fn (bool $byscale = false, ?int $scaleid = null) => $records
        );
        /* filterbytestinfo now counts ANSWERED productive items instead of
           displayed ones (issue #6), so the stub has to provide that number too -
           in this fixture every played item is answered and productive. */
        $progress->method('get_num_answered_productive_questions')->willReturnCallback(
            fn (?int $scaleid = null) => $played
        );

        // The heart of the assertion: deactivate_scale must (not) be called.
        $progress->expects($mayexclude ? $this->once() : $this->never())
            ->method('deactivate_scale');

        $context = [
            'progress' => $progress,
            'catscaleid' => self::SCALE,
            'minimumquestions' => $minimumquestions,
            'min_attempts_per_scale' => 0,
            'max_attempts_per_scale' => -1,
            'se' => [self::SCALE => 1.0],
            'se_min' => 0.4,
            'se_max' => 0.6,
            'questionsperscale' => [self::SCALE => $this->item_records(3, true)],
            'teststrategy' => \LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
        ];

        $task = new filterbytestinfo();
        $result = $task->run($context);
        $this->assertTrue($result->isok());
    }

    /**
     * Data provider: played count vs. global minimum.
     *
     * @return array
     */
    public static function minquestions_provider(): array {
        return [
            'played 1 of 4 -> keep active' => [1, 4, false],
            'played 2 of 4 -> keep active' => [2, 4, false],
            'played 3 of 4 -> keep active' => [3, 4, false],
            'played 4 of 4 -> may exclude' => [4, 4, true],
            // With no global minimum the old behaviour stands: one played
            // question in the scale already allows exclusion.
            'played 1, minimum 0 -> may exclude' => [1, 0, true],
        ];
    }

    /**
     * Builds $count item records far from the test ability (low Fisher info).
     *
     * @param int $count
     * @param bool $withpilotflag Add is_pilot for questionsperscale entries.
     *
     * @return array
     */
    private function item_records(int $count, bool $withpilotflag = false): array {
        $records = [];
        for ($i = 1; $i <= $count; $i++) {
            $r = (object) [
                'id' => $i,
                'componentid' => (string) $i,
                'model' => 'rasch',
                'status' => \LOCAL_CATQUIZ_STATUS_CALCULATED,
                'difficulty' => 50.0,
            ];
            if ($withpilotflag) {
                $r->is_pilot = false;
            }
            $records[$i] = $r;
        }
        return $records;
    }
}
