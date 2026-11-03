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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/tests/fixtures/measured_progress.php');

/**
 * The finalizer files scale results under the CAT attempt, not under the attempt of the component.
 *
 * Issue #98: the path from finalisation to the stored results was correct, but nothing pinned it.
 * The existing tests take both ids from sequences that usually differ, not by construction - a mix-up
 * of the two namespaces could pass unnoticed whenever they happened to coincide. Here they are made
 * to differ on purpose, far apart.
 *
 * Since local_catquiz_attemptscale was merged into local_catquiz_personparams, the scale results
 * live there; attemptid carries local_catquiz_attempts.id.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\attempt\attempt_finalizer
 */
final class finalizer_attempt_reference_test extends advanced_testcase {
    /** @var int The id of the adaptive quiz attempt - deliberately far from the CAT attempt id. */
    private const COMPONENTATTEMPT = 910000;

    /**
     * Files an adaptive quiz attempt and its CAT attempt with ids that cannot coincide.
     *
     * @return array{0: int, 1: int} The id of the component's attempt and the internal CAT attempt id.
     */
    private function make_attempts(): array {
        global $DB;

        $now = time();
        $json = json_encode([
            'personabilities_abilities' => [
                5 => ['value' => 0.4, 'toreport' => true],
            ],
            'se' => [5 => 0.3],
        ]);

        // The attempt of the component with a fixed, large id.
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => self::COMPONENTATTEMPT,
            'instance' => 1, 'userid' => 2, 'uniqueid' => 7778, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => 6, 'difficultysum' => 0,
            'standarderror' => 0.3, 'measure' => 0, 'timefinished' => null,
            'timecreated' => $now, 'timemodified' => $now,
        ]);

        $catid = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2, 'scaleid' => 5, 'contextid' => 9, 'attemptid' => self::COMPONENTATTEMPT,
            'component' => 'mod_adaptivequiz', 'status' => 0, 'number_of_testitems_used' => 6,
            'endtime' => null, 'json' => $json, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        // Issue #128: the attempt's progress - without it no scale counts as measured.
        $this->setAdminUser();
        local_catquiz_measured_progress(self::COMPONENTATTEMPT, [5 => 3]);
        return [self::COMPONENTATTEMPT, $catid];
    }

    /**
     * The scale result carries the internal CAT attempt id, and only that one.
     */
    public function test_scale_result_is_filed_under_the_cat_attempt(): void {
        global $DB;

        $this->resetAfterTest();
        [$componentattemptid, $catid] = $this->make_attempts();

        $this->assertNotEquals($componentattemptid, $catid, 'The fixture must keep the two ids apart.');

        $this->assertTrue(attempt_finalizer::finalize($componentattemptid, time() + 5, 'reason'));

        $this->assertTrue(
            $DB->record_exists('local_catquiz_personparams', ['attemptid' => $catid, 'catscaleid' => 5]),
            'The scale result is not filed under the CAT attempt.'
        );
        $this->assertFalse(
            $DB->record_exists('local_catquiz_personparams', ['attemptid' => $componentattemptid]),
            'A scale result is filed under the id of the component instead of the CAT attempt.'
        );
    }

    /**
     * What was persisted is read back through the repository with its values intact.
     */
    public function test_repository_persistence(): void {
        $this->resetAfterTest();
        [$componentattemptid, $catid] = $this->make_attempts();

        attempt_finalizer::finalize($componentattemptid, time() + 5, 'reason');

        $rows = \local_catquiz\local\result\personparam_repository::get_for_attempt($catid);
        $this->assertNotEmpty($rows, 'Nothing was persisted for the CAT attempt.');

        $row = reset($rows);
        $this->assertEquals(5, (int) $row->catscaleid);
        $this->assertEquals(0.4, (float) $row->ability);
        $this->assertEquals(0.3, (float) $row->standarderror);
        $this->assertEquals($catid, (int) $row->attemptid);
    }

    /**
     * The schema declares the reference to the CAT attempt as a foreign key.
     */
    public function test_schema_declares_the_foreign_key(): void {
        global $CFG;

        $xmldb = new \xmldb_file($CFG->dirroot . '/local/catquiz/db/install.xml');
        $xmldb->loadXMLStructure();
        $table = $xmldb->getStructure()->getTable('local_catquiz_personparams');
        $key = $table->getKey('attemptid');

        $this->assertNotNull($key, 'local_catquiz_personparams.attemptid has no key.');
        $this->assertEquals('local_catquiz_attempts', $key->getRefTable());
        $this->assertEquals(['id'], $key->getRefFields());
    }

    /**
     * A full finalisation leaves a history: a later attempt appends rather than overwrites.
     */
    public function test_finalisation_builds_a_history(): void {
        global $DB;

        $this->resetAfterTest();
        [$componentattemptid, $catid] = $this->make_attempts();
        attempt_finalizer::finalize($componentattemptid, time() + 5, 'reason');

        // A second attempt of the same person on the same scale.
        $now = time();
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => self::COMPONENTATTEMPT + 1,
            'instance' => 1, 'userid' => 2, 'uniqueid' => 7779, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => 6, 'difficultysum' => 0,
            'standarderror' => 0.3, 'measure' => 0, 'timefinished' => null,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $secondcatid = (int) $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => 2, 'scaleid' => 5, 'contextid' => 9, 'attemptid' => self::COMPONENTATTEMPT + 1,
            'component' => 'mod_adaptivequiz', 'status' => 0, 'number_of_testitems_used' => 6,
            'endtime' => null,
            'json' => json_encode(['personabilities_abilities' => [5 => ['value' => 1.1, 'toreport' => true]],
                'se' => [5 => 0.25]]),
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        local_catquiz_measured_progress(self::COMPONENTATTEMPT + 1, [5 => 3]);
        attempt_finalizer::finalize(self::COMPONENTATTEMPT + 1, $now + 5, 'reason');

        $this->assertTrue($DB->record_exists('local_catquiz_personparams', ['attemptid' => $catid]));
        $this->assertTrue($DB->record_exists('local_catquiz_personparams', ['attemptid' => $secondcatid]));
        $this->assertEquals(
            1.1,
            (float) catquiz::get_current_person_param(2, 9, 5)->ability,
            'The newest valid result must be the current one.'
        );
    }
}
