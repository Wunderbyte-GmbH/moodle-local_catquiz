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

namespace local_catquiz\teststrategy;

use advanced_testcase;

/**
 * In trace mode every change of a scale's state is recorded with the step it happened at (issue #133).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\progress
 */
final class progress_scale_state_trace_test extends advanced_testcase {
    /** @var int The CAT attempt. */
    private int $catattemptid;

    /**
     * A running attempt's progress.
     *
     * @return progress
     */
    private function progress(): progress {
        global $DB, $USER;
        $this->setAdminUser();
        $now = time();
        $adaptiveattemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 1, 'userid' => $USER->id, 'uniqueid' => 0, 'attemptstate' => 'inprogress',
            'attemptstopcriteria' => '', 'questionsattempted' => 0, 'difficultysum' => 0,
            'standarderror' => 1, 'measure' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->catattemptid = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $USER->id, 'attemptid' => $adaptiveattemptid, 'component' => 'mod_adaptivequiz',
            'contextid' => 9, 'status' => 2, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        return progress::load($adaptiveattemptid, 'mod_adaptivequiz', 9, (object) []);
    }

    /**
     * Plays a question, so the next changes belong to the next step.
     *
     * @param progress $progress
     * @param int $id
     */
    private function play(progress $progress, int $id): void {
        $property = new \ReflectionProperty($progress, 'playedquestions');
        $property->setAccessible(true);
        $played = $property->getValue($progress);
        $played[$id] = (object) ['id' => $id, 'catscaleid' => 10, 'is_pilot' => false, 'fisherinformation' => [10 => 0.2]];
        $property->setValue($progress, $played);
    }

    /**
     * The state of the scales as the progress holds it.
     *
     * @param progress $progress
     * @return array active, dropped, locked
     */
    private function state(progress $progress): array {
        $value = fn($name) => (function () use ($name) {
            return $this->$name;
        })->call($progress);
        $active = array_map('intval', array_values($progress->get_active_scales()));
        $dropped = array_map('intval', array_values($value('droppedscales')));
        $locked = array_map('intval', array_keys($value('lockedscales')));
        foreach ([&$active, &$dropped, &$locked] as &$ids) {
            sort($ids);
        }
        unset($ids);
        return ['active' => $active, 'dropped' => $dropped, 'locked' => $locked];
    }

    /**
     * Activation, locking, removal and unlocking are traced; the trace replays to the final state,
     * also after saving and loading.
     */
    public function test_trace_records_every_change_and_replays_to_the_state(): void {
        $this->resetAfterTest();
        set_config('progressretention', 'trace', 'local_catquiz');
        $progress = $this->progress();

        $progress->set_active_scales([10, 11, 12, 13]);
        $this->play($progress, 501);
        $progress->deactivate_scale(11, true);
        $progress->drop_scale(12);
        $this->play($progress, 502);
        // The step is the one of the ability trace: an estimate at this point carries the same number.
        $progress->set_ability(0.3, 10);
        $this->assertSame(2, $progress->get_ability_trace()[10][0]['step']);
        $progress->deactivate_scale(13, true);
        $progress->unlock_scale(13);
        $progress->add_active_scale(11, true);
        $progress->deactivate_scale(10);
        // No change, no event: already active, already inactive, already removed, not locked.
        $progress->add_active_scale(11);
        $progress->deactivate_scale(10);
        $progress->drop_scale(12);
        $progress->unlock_scale(10);

        $events = array_map(fn($e) => [$e['step'], $e['scaleid'], $e['event']], $progress->get_scale_state_trace());
        $this->assertSame([
            [0, 10, progress::SCALE_ACTIVATED],
            [0, 11, progress::SCALE_ACTIVATED],
            [0, 12, progress::SCALE_ACTIVATED],
            [0, 13, progress::SCALE_ACTIVATED],
            [1, 11, progress::SCALE_LOCKED],
            [1, 11, progress::SCALE_DEACTIVATED],
            [1, 12, progress::SCALE_DEACTIVATED],
            [1, 12, progress::SCALE_REMOVED],
            [2, 13, progress::SCALE_LOCKED],
            [2, 13, progress::SCALE_DEACTIVATED],
            [2, 13, progress::SCALE_UNLOCKED],
            [2, 11, progress::SCALE_UNLOCKED],
            [2, 11, progress::SCALE_ACTIVATED],
            [2, 10, progress::SCALE_DEACTIVATED],
        ], $events);
        $this->assertSame($this->state($progress), progress::replay_scale_state_trace($progress->get_scale_state_trace()));
        $this->assertSame(['active' => [11], 'dropped' => [12], 'locked' => []], $this->state($progress));

        $progress->save();
        $loaded = progress::load_for_reading($this->catattemptid);
        $this->assertSame($progress->get_scale_state_trace(), $loaded->get_scale_state_trace());
        $this->assertSame($this->state($loaded), progress::replay_scale_state_trace($loaded->get_scale_state_trace()));
    }

    /**
     * Without trace mode nothing is recorded; progress stored before the trace existed still loads.
     */
    public function test_no_trace_outside_trace_mode_and_old_progress_loads(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('progressretention', 'keep', 'local_catquiz');
        $progress = $this->progress();

        $progress->set_active_scales([10, 11]);
        $progress->drop_scale(11);
        $this->assertSame([], $progress->get_scale_state_trace());

        $progress->save();
        $stored = $DB->get_field('local_catquiz_progress', 'json', ['attemptid' => $this->catattemptid]);
        // Not even an empty key: outside trace mode the stored JSON is the same as before the trace existed.
        $this->assertStringNotContainsString('scalestatetrace', $stored);

        // A progress stored before the trace existed - and one that still carries an empty trace.
        $json = json_decode($stored, true);
        $json['scalestatetrace'] = [];
        $DB->set_field('local_catquiz_progress', 'json', json_encode($json), ['attemptid' => $this->catattemptid]);
        $this->assertSame([], progress::load_for_reading($this->catattemptid)->get_scale_state_trace());
        unset($json['scalestatetrace']);
        $DB->set_field('local_catquiz_progress', 'json', json_encode($json), ['attemptid' => $this->catattemptid]);

        $loaded = progress::load_for_reading($this->catattemptid);
        $this->assertSame([], $loaded->get_scale_state_trace());
        $this->assertSame([10], array_values($loaded->get_active_scales()));
    }
}
