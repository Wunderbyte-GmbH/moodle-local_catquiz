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
 * The stored progress holds every administered question once (issue #143).
 *
 * playedquestionsbyscale kept the full question again for its scale and every ancestor, lastquestion
 * once more: three to five copies of each question in the JSON written on every answer. Both now
 * refer to the played questions by id. Readers see the same as before; older records still load.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\progress
 */
final class progress_compact_json_test extends advanced_testcase {
    /** @var int The attempt of the activity. */
    private int $adaptiveattemptid;

    /** @var int The CAT attempt. */
    private int $catattemptid;

    /**
     * A running attempt with its CAT attempt.
     */
    private function attempt(): void {
        global $DB, $USER;
        $this->setAdminUser();
        $now = time();
        $this->adaptiveattemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => 1, 'userid' => $USER->id, 'uniqueid' => 0, 'attemptstate' => 'inprogress',
            'attemptstopcriteria' => '', 'questionsattempted' => 0, 'difficultysum' => 0,
            'standarderror' => 1, 'measure' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->catattemptid = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $USER->id, 'attemptid' => $this->adaptiveattemptid, 'component' => 'mod_adaptivequiz',
            'contextid' => 9, 'status' => 2, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * A progress of $n questions in a hierarchy of $depth scales below the root; every second
     * question a pilot item, the last one unanswered.
     *
     * @param int $n
     * @param int $depth
     * @return progress
     */
    private function progress(int $n, int $depth): progress {
        $progress = progress::load($this->adaptiveattemptid, 'mod_adaptivequiz', 9, (object) []);
        $scales = range(100, 100 + $depth);
        $played = [];
        $byscale = [];
        $responses = [];
        for ($i = 1; $i <= $n; $i++) {
            $q = (object) [
                'id' => 5000 + $i, 'catscaleid' => end($scales), 'label' => 'Q' . $i, 'model' => 'raschbirnbaum',
                'difficulty' => 0.1 * $i, 'discrimination' => 1.2, 'is_pilot' => $i % 2 === 0,
                'fisherinformation' => array_fill_keys($scales, 0.25), 'userlastattempttime' => 1000 + $i,
                'json' => str_repeat('x', 200),
            ];
            $played[$q->id] = $q;
            foreach ($scales as $scaleid) {
                $byscale[$scaleid][] = $q;
            }
            if ($i < $n) {
                $responses[$q->id] = ['questionid' => $q->id, 'fraction' => 0.5];
            }
        }
        $state = [
            'playedquestions' => $played, 'playedquestionsbyscale' => $byscale, 'responses' => $responses,
            'lastquestion' => end($played),
        ];
        foreach ($state as $name => $value) {
            $property = new \ReflectionProperty($progress, $name);
            $property->setAccessible(true);
            $property->setValue($progress, $value);
        }
        return $progress;
    }

    /**
     * What readers see of a progress.
     *
     * @param progress $progress
     * @return array
     */
    private function view(progress $progress): array {
        $ids = fn($questions) => array_map(fn($q) => $q->id, array_values((array) $questions));
        $byscale = [];
        foreach ($progress->get_playedquestions(true) as $scaleid => $questions) {
            $byscale[$scaleid] = $ids($questions);
        }
        return [
            'played' => json_encode($progress->get_playedquestions()),
            'byscale' => $byscale,
            'last' => json_encode($progress->get_last_question()),
            'productive' => $progress->get_num_answered_productive_questions(),
            'answered' => $progress->get_num_answered_questions(),
            'nopilots' => $ids($progress->without_pilots()->get_playedquestions()),
        ];
    }

    /**
     * Sizes of a hierarchy, from a short test to a long one.
     *
     * @return array
     */
    public static function sizes(): array {
        return [
            '10 items, one level' => [10, 1],
            '50 items, three levels' => [50, 3],
            '150 items, four levels' => [150, 4],
        ];
    }

    /**
     * Saved and loaded, the progress reads as before - and each question is stored once.
     *
     * @dataProvider sizes
     * @param int $n
     * @param int $depth
     */
    public function test_roundtrip_keeps_every_reader_and_stores_each_question_once(int $n, int $depth): void {
        $this->resetAfterTest();
        $this->attempt();
        $progress = $this->progress($n, $depth);
        $before = $this->view($progress);

        $progress->save();
        $json = json_decode($this->stored_json(), true);
        $loaded = progress::load_for_reading($this->catattemptid);

        $this->assertEquals($before, $this->view($loaded));
        $this->assertSame(progress::FORMAT_COMPACT, $json['progressformat']);
        $this->assertSame($n, substr_count($this->stored_json(), '"label"'), 'Every question once.');
        foreach ($json['playedquestionsbyscale'] as $entries) {
            $this->assertContainsOnly('int', $entries);
        }
        $this->assertSame(5000 + $n, $json['lastquestion']);

        // Loaded, the entries are the played questions themselves, not copies.
        $played = $loaded->get_playedquestions();
        foreach ($loaded->get_playedquestions(true) as $questions) {
            foreach ($questions as $question) {
                $this->assertSame($played[$question->id], $question);
            }
        }
        $this->assertSame($played[5000 + $n], $loaded->get_last_question());
    }

    /**
     * The JSON written on every answer shrinks with the depth of the hierarchy.
     */
    public function test_payload_is_much_smaller_than_full_copies(): void {
        $this->resetAfterTest();
        $this->attempt();
        $progress = $this->progress(150, 4);

        $compact = strlen(json_encode($progress));
        $full = strlen(json_encode(array_merge(
            $progress->jsonSerialize(),
            ['playedquestionsbyscale' => $progress->get_playedquestions(true), 'lastquestion' => $progress->get_last_question()]
        )));

        $this->assertLessThan($full / 4, $compact, "compact $compact bytes, full copies $full bytes");
    }

    /**
     * A record written before the change - full copies everywhere - still loads, unchanged.
     */
    public function test_older_records_load_as_before(): void {
        global $DB;
        $this->resetAfterTest();
        $this->attempt();
        $progress = $this->progress(12, 2);
        $before = $this->view($progress);
        $progress->save();

        // As the previous version wrote it.
        $old = array_merge(json_decode($this->stored_json(), true), [
            'playedquestionsbyscale' => $progress->get_playedquestions(true),
            'lastquestion' => $progress->get_last_question(),
        ]);
        unset($old['progressformat']);
        $DB->set_field('local_catquiz_progress', 'json', json_encode($old), ['attemptid' => $this->catattemptid]);

        $this->assertEquals($before, $this->view(progress::load_for_reading($this->catattemptid)));
    }

    /**
     * The JSON stored for the CAT attempt.
     *
     * @return string
     */
    private function stored_json(): string {
        global $DB;
        return (string) $DB->get_field('local_catquiz_progress', 'json', ['attemptid' => $this->catattemptid]);
    }
}
