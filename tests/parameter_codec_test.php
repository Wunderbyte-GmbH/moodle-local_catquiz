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
 * Round-trip tests for the item-parameter codec of all CAT models.
 *
 * @package    local_catquiz
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use PHPUnit\Framework\TestCase;

/**
 * Verifies that convert_ip_to_vector and convert_vector_to_ip are inverse and
 * that the item-parameter dimensionality is derived from the data.
 *
 * @package    local_catquiz
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\local\model\model_raschmodel
 */
final class parameter_codec_test extends TestCase {
    /**
     * The codec must be a lossless round-trip for the estimated item parameters.
     *
     * @dataProvider codec_cases_provider
     *
     * @param string $modelclass fully qualified model class name
     * @param array $ip estimated item parameters
     * @param array $fractions response fractions keying the vector parameters
     * @param int $expecteddim expected length of the flat parameter vector
     *
     * @return void
     */
    public function test_codec_roundtrip(string $modelclass, array $ip, array $fractions, int $expecteddim): void {
        $vector = $modelclass::convert_ip_to_vector($ip);

        // Dimensionality is data-driven: it follows the actual parameter values.
        $this->assertCount($expecteddim, $vector, 'flat vector has the data-driven dimension');
        foreach ($vector as $value) {
            $this->assertIsNumeric($value, 'every vector entry is a scalar number');
        }

        $restored = $modelclass::convert_vector_to_ip($vector, $fractions);
        $this->assertEquals($ip, $restored, 'convert_vector_to_ip inverts convert_ip_to_vector');

        // The data-driven model dimension is the item-parameter count plus the ability.
        $this->assertSame($expecteddim + 1, $modelclass::get_model_dim_from_ip($ip));
    }

    /**
     * One representative estimated item for every model.
     *
     * @return array
     */
    public static function codec_cases_provider(): array {
        // Baseline fraction is fixed at 0 and excluded from the free vector.
        $thresholds = ['0.0' => 0.0, '0.5' => 0.2, '1.0' => 1.3];
        $fractions = array_keys($thresholds);
        return [
            'rasch (1PL)' => [
                'catmodel_rasch\\rasch',
                ['difficulty' => 0.7],
                [],
                1,
            ],
            'raschbirnbaum (2PL)' => [
                'catmodel_raschbirnbaum\\raschbirnbaum',
                ['difficulty' => 0.3, 'discrimination' => 1.2],
                [],
                2,
            ],
            'mixedraschbirnbaum (3PL)' => [
                'catmodel_mixedraschbirnbaum\\mixedraschbirnbaum',
                ['difficulty' => 0.5, 'discrimination' => 1.5, 'guessing' => 0.2],
                [],
                3,
            ],
            'grm' => [
                'catmodel_grm\\grm',
                ['difficulties' => $thresholds],
                $fractions,
                2,
            ],
            'grmgeneralized' => [
                'catmodel_grmgeneralized\\grmgeneralized',
                ['difficulties' => $thresholds, 'discrimination' => 1.3],
                $fractions,
                3,
            ],
            'pcm' => [
                'catmodel_pcm\\pcm',
                ['intercepts' => $thresholds],
                $fractions,
                2,
            ],
            'pcmgeneralized' => [
                'catmodel_pcmgeneralized\\pcmgeneralized',
                ['intercepts' => $thresholds, 'discrimination' => 1.1],
                $fractions,
                3,
            ],
        ];
    }
}
