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

namespace local_catquiz\local\attempt;

use advanced_testcase;
use context_module;
use local_catquiz\event\attempt_completed;
use local_catquiz\local\monitoring\attempt_snapshot;
use local_catquiz\teststrategy\progress;

/**
 * The CAT attempt announces its completion when it is finalised, once (issue #122).
 *
 * The event used to be triggered when the result page was built - so a test whose result page was
 * never opened was never announced, and every reload of the page announced it again. It is now
 * triggered by attempt_finalizer after the commit, and the endtime guard keeps it to once.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\attempt\attempt_finalizer
 */
final class attempt_completed_event_test extends advanced_testcase {
    /**
     * Files an attempt of a real activity and its CAT attempt, ready to be finalised.
     *
     * @return array{0: int, 1: int, 2: \stdClass} Adaptive attempt id, CAT attempt id, the cm.
     */
    private function attempt_ready_to_finalise(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $module = $this->getDataGenerator()->create_module('adaptivequiz', [
            'course' => $course->id,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $now = time();
        $adaptiveattemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => $module->id, 'userid' => $user->id, 'uniqueid' => 0, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => 0, 'difficultysum' => 0,
            'standarderror' => 1, 'measure' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $catattemptid = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $user->id, 'scaleid' => 5, 'contextid' => 9, 'courseid' => $course->id,
            'instanceid' => $module->id, 'attemptid' => $adaptiveattemptid, 'component' => 'mod_adaptivequiz',
            'status' => 0, 'teststrategy' => 1,
            'json' => json_encode(['personabilities_abilities' => [5 => ['value' => 0.4, 'toreport' => true]], 'se' => [5 => 0.3]]),
            'timecreated' => $now, 'timemodified' => $now,
        ]);

        return [$adaptiveattemptid, $catattemptid, get_coursemodule_from_instance('adaptivequiz', $module->id)];
    }

    /**
     * Finalising announces the attempt once, with the right object, context and validity.
     */
    public function test_finalising_announces_the_attempt_once(): void {
        global $DB;

        $this->resetAfterTest();
        [$adaptiveattemptid, $catattemptid, $cm] = $this->attempt_ready_to_finalise();

        $sink = $this->redirectEvents();
        $this->assertTrue(attempt_finalizer::finalize($adaptiveattemptid, time(), 'reason'));
        // A second finalisation - a reload, a second request, the cleanup task - changes nothing.
        $this->assertFalse(attempt_finalizer::finalize($adaptiveattemptid, time(), 'reason'));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof attempt_completed));
        $sink->close();

        $this->assertCount(1, $events, 'The attempt was not announced exactly once.');
        $event = $events[0];
        $this->assertSame('local_catquiz_attempts', $event->objecttable);
        $this->assertEquals($catattemptid, $event->objectid);
        $this->assertEquals(context_module::instance($cm->id)->id, $event->contextid, 'Not the context of the activity.');
        $this->assertEquals($adaptiveattemptid, $event->other['adaptiveattemptid']);
        $this->assertEquals($catattemptid, $event->other['catattemptid']);

        // The verdict in the event is the one that was persisted - decided before the announcement.
        $persisted = $DB->get_record('adaptivequiz_attempt', ['id' => $adaptiveattemptid], 'resultvalid, resultstatus');
        $this->assertEquals($persisted->resultvalid, $event->other['resultvalid']);
        $this->assertEquals($persisted->resultstatus, $event->other['resultstatus']);

        $this->assertStringContainsString('/local/catquiz/show_attemptfeedback.php', $event->get_url()->out(false));
        $this->assertStringContainsString('attemptid=' . $adaptiveattemptid, $event->get_url()->out(false));
    }

    /**
     * Nothing but the finalisation announces an attempt - in particular not the result page.
     */
    public function test_only_the_finaliser_announces(): void {
        global $CFG;

        $sources = [
            $CFG->dirroot . '/local/catquiz/classes/output/attemptfeedback.php',
            $CFG->dirroot . '/local/catquiz/classes/local/attempt/attempt_finalizer.php',
        ];
        $this->assertStringNotContainsString('attempt_completed::create', file_get_contents($sources[0]));
        $this->assertStringContainsString('attempt_completed::create', file_get_contents($sources[1]));
    }

    /**
     * Saving the progress stamps it; the monitor sees the running attempt and its current item.
     */
    public function test_the_monitor_sees_a_running_attempt(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $progress = progress::load(94001, 'mod_adaptivequiz', 9, (object) []);
        $played = (object) ['id' => 7001, 'catscaleid' => 5, 'is_pilot' => false, 'fisherinformation' => []];
        $before = time();
        // Handing the question out stamps it: that is the start of the current item.
        $progress->add_playedquestion($played);
        $progress->save();

        $catattemptid = \local_catquiz\catquiz::get_cat_attempt_id(94001, 'mod_adaptivequiz');
        $this->assertGreaterThanOrEqual(
            $before,
            (int) $DB->get_field('local_catquiz_progress', 'timemodified', ['attemptid' => $catattemptid]),
            'Saving the progress did not stamp it.'
        );

        $snapshot = attempt_snapshot::for_adaptive_attempt(94001);
        $this->assertNotNull($snapshot);
        $this->assertTrue($snapshot->running);
        $this->assertTrue($snapshot->hasprogress);
        $this->assertSame($catattemptid, $snapshot->catattemptid);
        $this->assertSame(94001, $snapshot->adaptiveattemptid);
        $this->assertSame(1, $snapshot->numplayedquestions);
        $this->assertSame(7001, $snapshot->lastquestion->questionid);
        $this->assertFalse($snapshot->lastquestion->answered);
        $this->assertGreaterThanOrEqual(
            $before,
            $snapshot->lastquestionstartedat,
            'The start of the current item is not available.'
        );
        $this->assertSame([7001], $snapshot->playedquestionsbyscale[5] ?? null);
    }

    /**
     * A finished attempt whose progress was removed still has a snapshot, without progress detail.
     */
    public function test_a_finished_attempt_without_progress(): void {
        global $DB;

        $this->resetAfterTest();
        [$adaptiveattemptid, $catattemptid] = $this->attempt_ready_to_finalise();
        attempt_finalizer::finalize($adaptiveattemptid, time(), 'reason');
        $DB->delete_records('local_catquiz_progress', ['attemptid' => $catattemptid]);

        $snapshot = attempt_snapshot::for_cat_attempt($catattemptid);

        $this->assertFalse($snapshot->running);
        $this->assertFalse($snapshot->hasprogress);
        $this->assertNotNull($snapshot->endtime);
    }
}
