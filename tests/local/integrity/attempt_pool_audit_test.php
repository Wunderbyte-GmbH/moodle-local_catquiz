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

namespace local_catquiz\local\integrity;

use advanced_testcase;

/**
 * The audit of completed attempts against issue #157: candidates are named, nothing is changed.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\integrity\attempt_pool_audit
 */
final class attempt_pool_audit_test extends advanced_testcase {
    /**
     * Three attempts: one with a calibrated question played as a pilot, one with a difficulty of another calibration,
     * one as it should be; a real pilot is no candidate.
     */
    public function test_the_audit_names_candidates_and_changes_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $ctx = 4711;
        $items = [];
        foreach ([1001 => 0.5, 1002 => -1.25, 1003 => null] as $questionid => $difficulty) {
            $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
                'componentid' => $questionid, 'componentname' => 'question', 'catscaleid' => 7, 'contextid' => $ctx,
                'status' => 0,
            ]);
            if ($difficulty !== null) {
                $paramid = $DB->insert_record('local_catquiz_itemparams', (object) [
                    'itemid' => $itemid, 'componentid' => $questionid, 'componentname' => 'question', 'contextid' => $ctx,
                    'model' => 'raschbirnbaum', 'difficulty' => $difficulty, 'discrimination' => 1, 'guessing' => 0,
                    'json' => '{}', 'status' => 4, 'usable' => 1, 'timecreated' => time(), 'timemodified' => time(),
                ]);
                $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);
            }
        }
        $attempt = function (array $questions) use ($DB, $ctx) {
            static $host = 99000;
            $DB->insert_record('local_catquiz_attempts', (object) [
                'userid' => 2, 'scaleid' => 7, 'contextid' => $ctx, 'attemptid' => ++$host, 'component' => 'mod_adaptivequiz',
                'endtime' => time(), 'starttime' => time() - 60, 'status' => 0,
                'json' => json_encode(['graphicalsummary_data' => $questions]), 'timecreated' => time(), 'timemodified' => time(),
            ]);
        };
        // Calibrated in the context, played as a pilot (the pool of #157).
        $attempt([['id' => 1001, 'pilot' => true, 'difficulty' => null], ['id' => 1003, 'pilot' => true]]);
        // Productive with a difficulty the context does not have.
        $attempt([['id' => 1002, 'pilot' => false, 'difficulty' => '2.1000']]);
        // As it should be: the parameters of the context, the real pilot as a pilot.
        $attempt([['id' => 1001, 'pilot' => false, 'difficulty' => '0.5000'], ['id' => 1003, 'pilot' => true]]);
        $before = $DB->get_records('local_catquiz_attempts');

        $report = attempt_pool_audit::report($ctx);
        $this->assertSame([
            'completed attempts of the context' => 3,
            '... with the questions recorded (graphicalsummary_data)' => 3,
            'questions played' => 5,
            '... as pilot' => 3,
            '... as pilot, with active parameters in the context now' => 1,
            '... productive, difficulty differs from the active parameters now' => 1,
            'attempts with at least one such question (candidates)' => 2,
        ], $report);
        $this->assertEquals($before, $DB->get_records('local_catquiz_attempts'), 'Nothing changed.');
    }
}
