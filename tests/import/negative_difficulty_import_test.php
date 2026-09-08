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
 * Regression test: negative IRT difficulties must survive the import unchanged.
 *
 * A negative difficulty b is a perfectly regular IRT parameter for 1PL/2PL/3PL -
 * it denotes an easy item. Reports from production showed every negative CSV
 * difficulty arriving as 0.0000 in the database while the discrimination stayed
 * correct, which silently turns an easy item (b = -3.13) into an average one
 * (b = 0) and corrupts both the ability estimate and item selection.
 *
 * The pre-existing importer tests only asserted that the import reported
 * success; they never asserted the persisted value. This closes that gap.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\import\fileparser
 * @covers \local_catquiz\local\model\model_item_param
 */

namespace local_catquiz\import;

use advanced_testcase;
use local_catquiz\local\model\model_item_param;
use stdClass;

/**
 * Guards the sign of imported IRT item parameters.
 *
 * @package    local_catquiz
 * @covers \local_catquiz\import\fileparser
 * @covers \local_catquiz\local\model\model_item_param
 */
final class negative_difficulty_import_test extends advanced_testcase {
    /**
     * The parameter object must keep a negative difficulty through a record
     * roundtrip - no clamping to zero at any stage.
     *
     * @dataProvider negative_difficulty_provider
     * @param float $difficulty The difficulty to roundtrip.
     * @return void
     */
    public function test_negative_difficulty_survives_record_roundtrip(float $difficulty): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $record = new stdClass();
        $record->componentid = 1;
        $record->componentname = 'question';
        $record->contextid = 1;
        $record->model = 'raschbirnbaum';
        $record->difficulty = $difficulty;
        $record->discrimination = 2.19;
        $record->guessing = 0.0;
        $record->status = \LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY;

        $param = model_item_param::from_record($record);
        $out = $param->to_record();

        $this->assertEqualsWithDelta(
            $difficulty,
            (float) $out->difficulty,
            0.00001,
            "Difficulty {$difficulty} must not be clamped or zeroed."
        );
        // A negative difficulty must stay strictly negative, never collapse to 0.
        if ($difficulty < 0) {
            $this->assertLessThan(0.0, (float) $out->difficulty);
        }
        // The discrimination must never be zeroed either.
        $this->assertEqualsWithDelta(2.19, (float) $out->discrimination, 0.00001);
        $this->assertGreaterThan(0.0, (float) $out->discrimination);
    }

    /**
     * Difficulties observed in production plus boundary cases.
     *
     * @return array
     */
    public static function negative_difficulty_provider(): array {
        return [
            'strongly negative' => [-3.13],
            'negative 1.88' => [-1.88],
            'small negative 0.47' => [-0.47],
            'small negative 0.25' => [-0.25],
            'negative 2.4' => [-2.4],
            'zero is legal' => [0.0],
            'positive 0.14' => [0.14],
            'positive 1.52' => [1.52],
        ];
    }

    /**
     * The CSV parser must keep the sign for both dot and comma decimals.
     *
     * @return void
     */
    public function test_parser_keeps_sign_for_dot_and_comma_decimals(): void {
        $this->resetAfterTest(true);

        $parser = new class ([]) extends fileparser {
            /**
             * Constructor that skips the settings validation of the parent.
             *
             * @param array $settings Unused.
             */
            public function __construct(array $settings) {
            }

            /**
             * Exposes the protected cast for testing.
             *
             * @param string $value The raw CSV value.
             * @return mixed
             */
            public function cast(string $value) {
                return $this->cast_string_to_float($value);
            }
        };

        $this->assertEqualsWithDelta(-0.25, $parser->cast('-0.25'), 0.00001);
        $this->assertEqualsWithDelta(-0.25, $parser->cast('-0,25'), 0.00001);
        $this->assertEqualsWithDelta(-2.4, $parser->cast('-2.4'), 0.00001);
        $this->assertEqualsWithDelta(-2.4, $parser->cast('-2,4'), 0.00001);
        $this->assertEqualsWithDelta(-3.13, $parser->cast('-3.13'), 0.00001);
        $this->assertEqualsWithDelta(0.14, $parser->cast('0.14'), 0.00001);
    }

    /**
     * The spreadsheet-formula guard apostrophe must not survive into the number.
     *
     * Moodle's csv_import_reader stores parsed rows via csv_export_writer, which
     * applies \core\dataformat::escape_spreadsheet_formula(). That prefixes any
     * value starting with '=', '+', '-' or '@' with an apostrophe. A negative
     * difficulty therefore reaches the importer as "'-5.81"; floatval() of that is
     * 0.0, which silently zeroed every negative difficulty while positive ones
     * survived - exactly the production fingerprint.
     *
     * @return void
     */
    public function test_formula_escape_guard_is_stripped(): void {
        $this->resetAfterTest(true);

        // Escaped negatives must come back as real negative numbers.
        $this->assertSame('-5.81', fileparser::strip_formula_escape("'-5.81"));
        $this->assertSame('-0.25', fileparser::strip_formula_escape("'-0.25"));
        $this->assertSame('+1.5', fileparser::strip_formula_escape("'+1.5"));
        $this->assertEqualsWithDelta(-5.81, (float) fileparser::strip_formula_escape("'-5.81"), 0.00001);

        // Unescaped values are untouched.
        $this->assertSame('-5.81', fileparser::strip_formula_escape('-5.81'));
        $this->assertSame('0.40', fileparser::strip_formula_escape('0.40'));

        // A genuine apostrophe that is not a formula guard stays put.
        $this->assertSame("'text", fileparser::strip_formula_escape("'text"));
    }
}
