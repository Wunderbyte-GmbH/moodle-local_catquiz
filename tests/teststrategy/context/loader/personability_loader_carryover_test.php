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
 * Issue #9: carry-over start values come from the attempt history.
 *
 * local_catquiz_personparams is written DURING an attempt (updatepersonability,
 * filterbystandarderror), so it is a living intermediate state rather than a
 * record of finished attempts - reading it as a prior can carry over a
 * half-finished estimate. The attemptscale rows are written once at finalisation
 * and only for validly measured scales, so they take precedence.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\context\loader\personability_loader
 */

namespace local_catquiz\teststrategy\context\loader;

use advanced_testcase;
use local_catquiz\teststrategy\progress;
use ReflectionMethod;
use stdClass;

/**
 * Guards the carry-over source of person abilities.
 *
 * @package    local_catquiz
 * @covers \local_catquiz\teststrategy\context\loader\personability_loader
 */
final class personability_loader_carryover_test extends advanced_testcase {
    /**
     * Inserts a finalised attemptscale row.
     *
     * @param int $userid
     * @param int $contextid
     * @param int $catscaleid
     * @param float $ability
     * @param int $isvalid
     * @param int $isprimary
     *
     * @return void
     */
    private function add_attemptscale(
        int $userid,
        int $contextid,
        int $catscaleid,
        float $ability,
        int $isvalid = 1,
        int $isprimary = 0
    ): void {
        global $DB;
        $row = new stdClass();
        $row->catattemptid = $catscaleid * 100 + $contextid;
        $row->userid = $userid;
        $row->contextid = $contextid;
        $row->catscaleid = $catscaleid;
        $row->score = $ability;
        $row->standarderror = 0.3;
        $row->isvalid = $isvalid;
        $row->isprimary = $isprimary;
        $row->timecreated = time();
        $DB->insert_record('local_catquiz_attemptscale', $row);
    }

    /**
     * Inserts a living person parameter row.
     *
     * @param int $userid
     * @param int $contextid
     * @param int $catscaleid
     * @param float $ability
     *
     * @return void
     */
    private function add_personparam(int $userid, int $contextid, int $catscaleid, float $ability): void {
        global $DB;
        $row = new stdClass();
        $row->userid = $userid;
        $row->catscaleid = $catscaleid;
        $row->contextid = $contextid;
        $row->ability = $ability;
        $row->standarderror = 0.5;
        $row->timecreated = time();
        $row->timemodified = time();
        $DB->insert_record('local_catquiz_personparams', $row);
    }

    /**
     * Calls the protected loader method.
     *
     * @param array $context
     *
     * @return array
     */
    private function load(array $context): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $loader = new personability_loader();
        $progressstub = $this->createMock(progress::class);
        $progressstub->method('get_selected_subscales')->willReturn([]);
        $context['progress'] = $progressstub;

        $property = new \ReflectionProperty(personability_loader::class, 'progress');
        $property->setAccessible(true);
        $property->setValue($loader, $progressstub);

        $method = new ReflectionMethod(personability_loader::class, 'load_saved_personparams');
        $method->setAccessible(true);
        return $method->invokeArgs($loader, [&$context]);
    }

    /**
     * A finalised attempt result wins over the living person parameter.
     *
     * @return void
     */
    public function test_attempt_history_wins_over_living_personparam(): void {
        $this->resetAfterTest(true);

        $userid = 42;
        $contextid = 7;
        $scaleid = 11;

        // The living intermediate state says 2.5 ...
        $this->add_personparam($userid, $contextid, $scaleid, 2.5);
        // ... while the last finalised attempt measured 0.8.
        $this->add_attemptscale($userid, $contextid, $scaleid, 0.8);

        $abilities = $this->load([
            'contextid' => $contextid,
            'catscaleid' => $scaleid,
            'userid' => $userid,
            'includesubscales' => false,
        ]);

        $this->assertEqualsWithDelta(
            0.8,
            $abilities[$scaleid],
            0.0001,
            'The carry-over must come from the finalised attempt, not from the living personparams.'
        );
    }

    /**
     * Without an attempt history the person parameter remains the fallback.
     *
     * @return void
     */
    public function test_personparam_is_used_when_no_attempt_history_exists(): void {
        $this->resetAfterTest(true);

        $userid = 43;
        $contextid = 7;
        $scaleid = 12;

        $this->add_personparam($userid, $contextid, $scaleid, 1.75);

        $abilities = $this->load([
            'contextid' => $contextid,
            'catscaleid' => $scaleid,
            'userid' => $userid,
            'includesubscales' => false,
        ]);

        $this->assertEqualsWithDelta(1.75, $abilities[$scaleid], 0.0001);
    }

    /**
     * An invalid attempt result must not be carried over.
     *
     * @return void
     */
    public function test_invalid_attempt_result_is_not_carried_over(): void {
        $this->resetAfterTest(true);

        $userid = 44;
        $contextid = 7;
        $scaleid = 13;

        $this->add_personparam($userid, $contextid, $scaleid, 1.25);
        $this->add_attemptscale($userid, $contextid, $scaleid, -2.0, 0);

        $abilities = $this->load([
            'contextid' => $contextid,
            'catscaleid' => $scaleid,
            'userid' => $userid,
            'includesubscales' => false,
        ]);

        $this->assertEqualsWithDelta(
            1.25,
            $abilities[$scaleid],
            0.0001,
            'Only validly measured scales may serve as a carry-over prior.'
        );
    }
}
