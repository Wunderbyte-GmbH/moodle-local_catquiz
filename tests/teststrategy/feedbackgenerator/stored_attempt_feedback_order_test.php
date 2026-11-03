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

namespace local_catquiz\teststrategy\feedbackgenerator;

use advanced_testcase;
use local_catquiz\catscale;
use local_catquiz\output\attemptfeedback;
use local_catquiz\teststrategy\classic_hierarchy_trait;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once(__DIR__ . '/../classic_hierarchy_trait.php');

/**
 * The written feedback of a finished attempt, rendered from what the attempt stored (issue #77).
 *
 * A whole attempt through the real selection writes its feedback data to local_catquiz_attempts.json; the
 * result page reads it back with json_decode(..., true), so the scale records are arrays. Rendered from
 * there - the session cache emptied - the feedback shows every scale, main scale first, then by name, and
 * PHP raises nothing on the way.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\feedbackgenerator\customscalefeedback
 * @covers \local_catquiz\output\attemptfeedback
 */
final class stored_attempt_feedback_order_test extends advanced_testcase {
    use classic_hierarchy_trait;

    /** @var array Warnings and notices PHP raised while rendering. */
    private array $phpissues = [];

    /**
     * The pool of issue #64, one feedback range per scale with a text naming the scale.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_up_hierarchy();
        $extra = [];
        foreach ([$this->rootid, ...catscale::get_subscale_ids($this->rootid)] as $scaleid) {
            $name = catscale::return_catscale_object($scaleid)->name;
            $extra['feedbackeditor_scaleid_' . $scaleid . '_1_editor'] = (object) [
                'text' => 'FEEDBACK[' . $name . ']', 'format' => FORMAT_HTML,
            ];
        }
        $this->settings($extra);
    }

    /**
     * Unsets the switches of the testing classes again.
     */
    public function tearDown(): void {
        $this->unset_testing_switches();
        parent::tearDown();
    }

    /**
     * Played, stored, read back as arrays, rendered: every scale, in order, no PHP warning.
     */
    public function test_feedback_from_the_stored_attempt(): void {
        global $DB;
        [$ids, , $attemptid] = $this->play(60, false);
        $this->assertCount(24, $ids);
        $stored = json_decode((string) $DB->get_field('local_catquiz_attempts', 'json', ['attemptid' => $attemptid]), true);
        $this->assertIsArray(
            $stored['catscales'] ?? null,
            'The attempt stored no scales: ' . implode(',', array_keys((array) $stored))
        );
        $this->assertIsArray(reset($stored['catscales']), 'Fixture: read back, the scale records are arrays.');

        // The result page of another request: nothing from this session.
        \cache_helper::purge_by_definition('local_catquiz', 'adaptivequizattempt');
        set_config('lazyfeedbacktabs', 0, 'local_catquiz');
        set_debugging(DEBUG_DEVELOPER);
        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if (str_contains($errfile, '/local/catquiz/classes/teststrategy/feedbackgenerator/customscalefeedback.php')) {
                $this->phpissues[] = "$errstr ($errfile:$errline)";
            }
            return false;
        }, E_WARNING | E_NOTICE | E_DEPRECATED);
        try {
            $feedback = (new attemptfeedback($attemptid))->get_feedback_for_attempt();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $this->phpissues, 'PHP raised: ' . implode('; ', $this->phpissues));
        $primary = array_values(array_filter(
            $feedback['studentfeedback'] ?? [],
            fn($entry) => ($entry['generatorname'] ?? '') === 'customscalefeedback'
        ));
        $this->assertCount(1, $primary, 'No written feedback: ' . json_encode(array_column(
            $feedback['studentfeedback'] ?? [],
            'generatorname'
        )));
        preg_match_all('/FEEDBACK\[([^\]]+)\]/', $primary[0]['content'], $matches);
        $this->assertSame(['Simulation', 'SimA', 'SimA01', 'SimA02'], $matches[1]);
    }
}
