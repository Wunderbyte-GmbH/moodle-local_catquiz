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
use cache;
use local_catquiz\local\status;

/**
 * The result page of an attempt takes what it shows from that attempt and its activity (mod_adaptivequiz #22).
 *
 * The session holds the state of the latest attempt only. An error the selection recorded there belongs to the
 * attempt it was recorded for; the result page of another attempt - of another activity, say - must not show it.
 * The CAT context comes from the attempt's test, not from the request.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz_handler
 */
final class attempt_finished_binding_test extends advanced_testcase {
    /**
     * A finished attempt without an answer in an activity of its own.
     *
     * @return array [adaptivequiz, cm, attempt]
     */
    private function attempt(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $adaptivequiz = $this->getDataGenerator()->create_module('adaptivequiz', ['course' => $course->id]);
        $id = $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => $adaptivequiz->id, 'userid' => 2, 'uniqueid' => 0, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => 0, 'difficultysum' => 0, 'standarderror' => 0,
            'measure' => 0, 'timecreated' => 1, 'timemodified' => 2, 'timefinished' => 2,
        ]);
        return [
            $DB->get_record('adaptivequiz', ['id' => $adaptivequiz->id]),
            get_coursemodule_from_instance('adaptivequiz', $adaptivequiz->id),
            $DB->get_record('adaptivequiz_attempt', ['id' => $id]),
        ];
    }

    /**
     * The error of another attempt is not this attempt's; its own is shown.
     */
    public function test_an_error_is_shown_only_for_its_attempt(): void {
        global $OUTPUT, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$adaptivequiz, $cm, $attempt] = $this->attempt();
        $PAGE->set_url('/mod/adaptivequiz/attemptfinished.php');
        $OUTPUT = $PAGE->get_renderer('core');
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $message = get_string(status::EXCEEDED_MAX_ATTEMPT_TIME, 'local_catquiz');

        $cache->set('catquizerror', status::EXCEEDED_MAX_ATTEMPT_TIME);
        $cache->set('catquizerrorattempt', (int) $attempt->id + 1);
        $this->assertNotSame($message, catquiz_handler::attempt_finished($adaptivequiz, $cm, $attempt));

        $cache->set('catquizerrorattempt', (int) $attempt->id);
        $this->assertSame($message, catquiz_handler::attempt_finished($adaptivequiz, $cm, $attempt));
    }
}
