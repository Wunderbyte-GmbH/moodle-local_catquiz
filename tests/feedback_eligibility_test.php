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
 * Every sound subscale reaches the feedback table, not only the primary one.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\local\result\attempt_result_validator;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\teststrategy\feedbacksettings;

/**
 * Three ideas were being read off one flag.
 *
 * statisticallyvalid says the measurement can be used, primary says the strategy
 * singles this scale out, reportable says it may be shown at all. The feedback table
 * asked for reportable, which is built from the strategy's toreport flag - and
 * inferlowestskillgap sets that for exactly one scale. Everything else measured
 * properly disappeared, and with one row left the comparison chart reported "not
 * enough valid results".
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\teststrategy\feedback_helper::is_feedback_eligible
 */
final class feedback_eligibility_test extends advanced_testcase {
    /**
     * Builds a result from scales described as [ability, toreport, extra flags].
     *
     * @param array $scales
     * @return \local_catquiz\local\result\attempt_result
     */
    private function make_result(array $scales) {
        $personabilities = [];

        foreach ($scales as $scaleid => $spec) {
            $entry = ['value' => $spec['value']];

            if (!empty($spec['toreport'])) {
                $entry['toreport'] = true;
            }
            if (!empty($spec['hidden'])) {
                $entry['hidden'] = true;
            }
            if (!empty($spec['notreported'])) {
                $entry[feedbacksettings::FIELD_NOTREPORTED] = true;
            }
            if (!empty($spec['semax'])) {
                $entry['error'] = ['se' => ['semaxdefined' => 1.5, 'securrent' => 3.0]];
            }

            $personabilities[$scaleid] = $entry;
        }

        return attempt_result_validator::from_personabilities(
            $personabilities,
            [],
            array_fill_keys(array_keys($scales), 5),
            [],
            2001
        );
    }

    /**
     * A sound scale is eligible even though the strategy did not mark it.
     *
     * @return void
     */
    public function test_non_primary_scales_are_eligible(): void {
        $this->resetAfterTest();

        $result = $this->make_result([
            2001 => ['value' => -2.0, 'toreport' => true],
            2002 => ['value' => -1.0],
            2003 => ['value' => 0.2],
        ]);

        $this->assertTrue(
            feedback_helper::is_feedback_eligible($result, 2001),
            'The primary scale stays eligible.'
        );
        $this->assertTrue(
            feedback_helper::is_feedback_eligible($result, 2002),
            'Being singled out by the strategy is a reason to emphasise a scale, '
                . 'never a condition for showing the others.'
        );
        $this->assertTrue(
            feedback_helper::is_feedback_eligible($result, 2003)
        );
    }

    /**
     * A statistically unsound scale stays out.
     *
     * @return void
     */
    public function test_unsound_scales_are_not_eligible(): void {
        $this->resetAfterTest();

        $result = $this->make_result([
            2001 => ['value' => -2.0, 'toreport' => true],
            2004 => ['value' => 0.8, 'semax' => true],
        ]);

        $this->assertFalse(
            feedback_helper::is_feedback_eligible($result, 2004),
            'A standard error above the configured maximum still excludes a scale.'
        );
    }

    /**
     * Explicit configuration decisions stay exclusions.
     *
     * Hidden and "reporting disabled" are choices by whoever set up the test, unlike
     * "not primary", which is an outcome of the strategy.
     *
     * @return void
     */
    public function test_configuration_exclusions_are_respected(): void {
        $this->resetAfterTest();

        $result = $this->make_result([
            2001 => ['value' => -2.0, 'toreport' => true],
            2005 => ['value' => -0.5, 'hidden' => true],
            2006 => ['value' => -0.4, 'notreported' => true],
        ]);

        $this->assertFalse(feedback_helper::is_feedback_eligible($result, 2005));
        $this->assertFalse(feedback_helper::is_feedback_eligible($result, 2006));
    }

    /**
     * The table is fed by the feedback predicate, not the completion one.
     *
     * The other tests call the predicate directly and would keep passing if
     * get_restructured_abilities() still used is_displayable() - which is the defect.
     *
     * @return void
     */
    public function test_generator_uses_the_feedback_predicate(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/teststrategy/feedbackgenerator.php'
        );

        $start = strpos($source, 'get_restructured_abilities');
        $this->assertNotFalse($start);

        $body = substr($source, $start, 3000);

        $this->assertStringContainsString(
            'is_feedback_eligible',
            $body,
            'The abilities table has to use the feedback predicate.'
        );
    }
    /**
     * Every class feedback_helper calls statically can actually be resolved.
     *
     * `attempt_result_validator` was used without a `use` statement. PHP then looks
     * for it in the file's own namespace, finds nothing, and the request dies with
     * "Class not found" - at render time, inside the feedback page. Every attempt
     * feedback on the site came out empty; the list of attempts still rendered, so it
     * looked like missing data rather than a fatal.
     *
     * Static analysis catches this, PHPUnit normally does not: the call sits behind a
     * branch that the other tests do not enter. Hence this test, which reads the
     * source rather than executing it.
     *
     * @return void
     */
    public function test_every_used_class_is_imported(): void {
        global $CFG;

        $this->resetAfterTest();

        $file = $CFG->dirroot . '/local/catquiz/classes/teststrategy/feedback_helper.php';
        $source = file_get_contents($file);

        preg_match_all('/^use\s+([^;]+);/m', $source, $uses);

        $imported = [];
        foreach ($uses[1] as $use) {
            $parts = explode('\\', trim($use));
            $imported[] = end($parts);
        }

        // Static calls to a bare class name: Foo::bar(). Names that are fully
        // qualified (a leading backslash) resolve on their own.
        preg_match_all('/(?<![\\\w$>])([a-z_][a-z0-9_]*)::/i', $source, $calls);

        $ownclass = 'feedback_helper';
        $builtin = ['self', 'static', 'parent'];

        $missing = [];
        foreach (array_unique($calls[1]) as $name) {
            if ($name === $ownclass || in_array(strtolower($name), $builtin, true)) {
                continue;
            }
            if (in_array($name, $imported, true)) {
                continue;
            }
            // A class in the same namespace needs no import.
            if (file_exists(dirname($file) . '/' . $name . '.php')) {
                continue;
            }
            $missing[] = $name;
        }

        $this->assertSame(
            [],
            $missing,
            'These classes are called statically but neither imported nor in the same '
                . 'namespace, so the call fails at run time rather than at parse time.'
        );
    }
}
