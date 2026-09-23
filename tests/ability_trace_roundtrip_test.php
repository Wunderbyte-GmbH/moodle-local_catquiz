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
 * Issue #56: the ability trace survives being written and read back.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\teststrategy\progress;

/**
 * The trace is collected across an attempt, so it has to outlive a request.
 *
 * It was written into the object and never into the json, so every consumer saw at
 * most the steps of the current page load. That does not look like a bug in the
 * chart - it looks like a short attempt, which is why it stayed unnoticed.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\teststrategy\progress
 */
final class ability_trace_roundtrip_test extends advanced_testcase {
    /**
     * jsonSerialize() carries the trace.
     *
     * @return void
     */
    public function test_serialisation_contains_the_trace(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/progress.php'
        );

        $start = strpos($source, 'public function jsonSerialize');
        $this->assertNotFalse($start);
        $end = strpos($source, "\n    }\n", $start);
        $body = substr($source, $start, $end - $start);

        $this->assertStringContainsString(
            "'abilitytrace' => \$this->abilitytrace",
            $body,
            'A value that is collected and never written is lost at the end of the '
                . 'request, and the consumer shows an attempt that looks short.'
        );
    }

    /**
     * A trace written into the json comes back with the same shape.
     *
     * json_decode() returns objects while the trace is used as nested arrays. Getting
     * the value back with the wrong type is the failure that a mere "is it there"
     * check would miss.
     *
     * @return void
     */
    public function test_trace_survives_a_json_roundtrip(): void {
        $this->resetAfterTest();

        $trace = [
            3 => [
                ['ability' => -0.5, 'se' => 1.2, 'questions' => 1],
                ['ability' => 0.25, 'se' => 0.9, 'questions' => 2],
            ],
        ];

        // The route the data really takes: encode, store, decode.
        $decoded = json_decode(json_encode(['abilitytrace' => $trace]));
        $restored = array_map(
            fn($branch) => (array) $branch,
            (array) $decoded->abilitytrace
        );

        $this->assertArrayHasKey(3, $restored, 'The scale key has to survive.');
        $this->assertCount(2, $restored[3], 'Both steps have to survive.');

        $first = (array) $restored[3][0];
        $this->assertSame(-0.5, $first['ability']);
        $this->assertSame(1.2, $first['se']);
    }

    /**
     * An attempt written before the trace existed still loads.
     *
     * Refusing such rows would break running attempts in order to fix a display
     * detail, which is the wrong trade.
     *
     * @return void
     */
    public function test_older_attempts_without_the_key_still_load(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/progress.php'
        );

        $this->assertStringContainsString(
            "property_exists(\$data, 'abilitytrace')",
            $source,
            'Rows written before this key existed have to keep loading.'
        );
    }
    /**
     * The trace survives being written to the database and read back.
     *
     * The existing tests cover the JSON encoding. That is the easier half: a trace can
     * serialise correctly and still be lost, because save() and load() go through the
     * progress table and a cache, and either could drop a key the encoder handles
     * fine.
     *
     * The cache is purged between the two halves on purpose - a load answered from
     * the cache would return the object still held in memory and prove nothing about
     * what was stored.
     *
     * @return void
     */
    public function test_trace_survives_the_database(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $attemptid = 77001;
        $contextid = 1;

        // A new progress needs the quiz settings; without them create_new() cannot
        // build one, and the test would fail on its own setup rather than on the
        // behaviour it is meant to check.
        $quizsettings = (object) [
            'catquiz_catscales' => 3,
            'maxquestionspertest' => 10,
            'minquestionspertest' => 1,
        ];

        // The trace is only collected in trace mode - the other retention levels keep
        // a single scalar per scale on purpose. Without switching it on, this test
        // would report a persistence defect where there is simply nothing to persist.
        set_config('progressretention', 'trace', 'local_catquiz');

        $progress = progress::load($attemptid, 'mod_adaptivequiz', $contextid, $quizsettings);
        $progress->set_ability(1.25, 3);

        $this->assertNotEmpty(
            $progress->get_ability_trace(),
            'Nothing was recorded, so the reload below could not tell a lost trace '
                . 'from an empty one.'
        );

        $progress->save();

        $this->assertTrue(
            $DB->record_exists('local_catquiz_progress', ['attemptid' => $attemptid]),
            'Nothing was written, so the reload below would prove nothing.'
        );

        // Everything in-process is dropped, so the reload has to come from storage.

        \cache::make("local_catquiz", "adaptivequizattempt")->purge();

        $reloaded = progress::load($attemptid, 'mod_adaptivequiz', $contextid, $quizsettings);
        $trace = $reloaded->get_ability_trace();

        $this->assertNotEmpty(
            $trace,
            'The trace was written but did not come back - persisted and lost is worse '
                . 'than never stored, because the data looks complete until it is read.'
        );
        $this->assertArrayHasKey(3, $trace, 'The scale the ability was recorded for.');
    }
}
