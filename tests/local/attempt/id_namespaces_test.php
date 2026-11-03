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
use local_catquiz\catquiz;
use local_catquiz\local\result\personparam_repository;

/**
 * The two attempt ids are never mistaken for each other, even when their numbers collide (#107).
 *
 * adaptivequiz_attempt.id and local_catquiz_attempts.id are separate sequences. In a small test
 * database they often run in step, so code that confuses them passes by accident. These tests set
 * the ids on purpose: equal, different, and colliding with a foreign CAT attempt.
 *
 * Exception to the convention, kept on purpose: local_catquiz_attempts.attemptid holds the attempt
 * of the component. Every other attemptid in local_catquiz_* holds local_catquiz_attempts.id.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catquiz
 */
final class id_namespaces_test extends advanced_testcase {
    /**
     * Files a CAT attempt with a given id for a given attempt of the component.
     *
     * @param int $catattemptid The id to give it in local_catquiz_attempts.
     * @param int $adaptiveattemptid The attempt of the component it belongs to.
     */
    private function cat_attempt(int $catattemptid, int $adaptiveattemptid): void {
        global $DB;

        $DB->import_record('local_catquiz_attempts', (object) [
            'id' => $catattemptid, 'userid' => 2, 'scaleid' => 5, 'contextid' => 9, 'courseid' => 1,
            'instanceid' => 1, 'attemptid' => $adaptiveattemptid, 'component' => 'mod_adaptivequiz',
            'status' => 0, 'teststrategy' => 1, 'json' => '{}', 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * The three cases: ids equal, ids different, and a foreign CAT attempt whose id is the number
     * of the component's attempt.
     *
     * @return array
     */
    public static function cases(): array {
        return [
            'equal numbers' => [7001, 7001, null],
            'different numbers' => [7001, 8001, null],
            'colliding foreign CAT attempt' => [7001, 8001, 7001],
        ];
    }

    /**
     * The component's attempt resolves to its own CAT attempt, never to a CAT attempt with that id.
     *
     * @dataProvider cases
     * @param int $adaptiveattemptid
     * @param int $catattemptid
     * @param int|null $decoy Id of a foreign CAT attempt, belonging to another attempt of the component.
     */
    public function test_the_component_attempt_resolves_to_its_own(int $adaptiveattemptid, int $catattemptid, ?int $decoy): void {
        $this->resetAfterTest();
        if ($decoy !== null && $decoy !== $catattemptid) {
            // The foreign CAT attempt carries the number of our component attempt as its own id.
            $this->cat_attempt($decoy, 9999);
        }
        $this->cat_attempt($catattemptid, $adaptiveattemptid);

        $this->assertSame($catattemptid, catquiz::get_cat_attempt_id($adaptiveattemptid, 'mod_adaptivequiz'));
        $this->assertNull(
            catquiz::get_cat_attempt_id($catattemptid === $adaptiveattemptid ? 424242 : $catattemptid, 'mod_adaptivequiz'),
            'A CAT attempt id was taken for the attempt of the component.'
        );
    }

    /**
     * Results are read by CAT attempt id, never by the number of the component's attempt.
     *
     * @dataProvider cases
     * @param int $adaptiveattemptid
     * @param int $catattemptid
     * @param int|null $decoy
     */
    public function test_results_belong_to_the_cat_attempt(int $adaptiveattemptid, int $catattemptid, ?int $decoy): void {
        global $DB;

        $this->resetAfterTest();
        $this->cat_attempt($catattemptid, $adaptiveattemptid);
        $row = ['userid' => 2, 'catscaleid' => 5, 'ability' => 0.5, 'standarderror' => 0.3, 'isvalid' => 1,
            'resultsource' => 'attempt', 'timecreated' => time(), 'timemodified' => time()];
        $own = $DB->insert_record('local_catquiz_personparams', (object) ($row + ['attemptid' => $catattemptid]));
        if ($decoy !== null) {
            // A result of the foreign CAT attempt whose id equals our component attempt's number.
            $this->cat_attempt($decoy === $catattemptid ? 9998 : $decoy, 9999);
            $DB->insert_record('local_catquiz_personparams', (object) ($row + ['attemptid' => $decoy]));
        }

        $found = personparam_repository::get_for_attempt($catattemptid);

        $this->assertSame([(int) $own], array_map('intval', array_keys($found)));
    }
}
