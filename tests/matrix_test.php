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
 * Tests the matrix functionality.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use basic_testcase;

/**
 * Tests the matrix functionality.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\matrix
 */
final class matrix_test extends basic_testcase {
    /**
     * Reads a matrix into a plain nested array for comparison.
     *
     * @param matrix $m
     * @return array
     */
    private function toarray(matrix $m): array {
        $out = [];
        for ($r = 0; $r < $m->getrows(); $r++) {
            for ($c = 0; $c < $m->getcols(); $c++) {
                $out[$r][$c] = $m[$r][$c];
            }
        }
        return $out;
    }

    /**
     * identity() returns a correct n x n identity for several sizes.
     *
     * @dataProvider identity_provider
     *
     * @param int $n
     * @return void
     */
    public function test_identity_returns_correct_matrix(int $n): void {
        $identity = (new matrix($n, $n))->identity();

        $this->assertSame($n, $identity->getrows());
        $this->assertSame($n, $identity->getcols());
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                $this->assertEquals($r === $c ? 1.0 : 0, $identity[$r][$c]);
            }
        }
    }

    /**
     * Data provider for identity sizes.
     *
     * @return array
     */
    public static function identity_provider(): array {
        return [
            '1x1' => [1],
            '2x2' => [2],
            '3x3' => [3],
            '5x5' => [5],
        ];
    }

    /**
     * identity() on a non-square matrix throws.
     *
     * @return void
     */
    public function test_identity_on_non_square_throws(): void {
        $this->expectException(MatrixException::class);
        (new matrix(2, 3))->identity();
    }

    /**
     * determinant() matches known values.
     *
     * @dataProvider determinant_provider
     *
     * @param array $values
     * @param float $expected
     * @return void
     */
    public function test_determinant(array $values, float $expected): void {
        $this->assertEqualsWithDelta($expected, (new matrix($values))->determinant(), 1e-12);
    }

    /**
     * Data provider for determinants.
     *
     * @return array
     */
    public static function determinant_provider(): array {
        return [
            '1x1' => [[[5]], 5.0],
            '2x2' => [[[1, 2], [3, 4]], -2.0],
            '2x2 diagonal' => [[[2, 0], [0, 3]], 6.0],
            '3x3' => [[[6, 1, 1], [4, -2, 5], [2, 8, 7]], -306.0],
            '3x3 singular' => [[[1, 2, 3], [4, 5, 6], [7, 8, 9]], 0.0],
        ];
    }

    /**
     * inverse() multiplied with the original yields the identity.
     *
     * @return void
     */
    public function test_inverse_times_original_is_identity(): void {
        $original = new matrix([[4, 7], [2, 6]]);
        $product = $original->inverse()->multiply($original);

        $this->assertEqualsWithDelta(1.0, $product[0][0], 1e-12);
        $this->assertEqualsWithDelta(0.0, $product[0][1], 1e-12);
        $this->assertEqualsWithDelta(0.0, $product[1][0], 1e-12);
        $this->assertEqualsWithDelta(1.0, $product[1][1], 1e-12);
    }

    /**
     * multiply() computes the standard matrix product.
     *
     * @return void
     */
    public function test_multiply(): void {
        $left = new matrix([[1, 2], [3, 4]]);
        $right = new matrix([[5, 6], [7, 8]]);

        $this->assertEquals([[19, 22], [43, 50]], $this->toarray($left->multiply($right)));
    }

    /**
     * A matrix times a column vector (n x 1 matrix) yields the expected product.
     *
     * @return void
     */
    public function test_matrix_times_column_vector(): void {
        $matrix = new matrix([[1, 2], [3, 4]]);
        $vector = new matrix([[5], [6]]);

        $this->assertEquals([[17], [39]], $this->toarray($matrix->multiply($vector)));
    }

    /**
     * transpose() swaps rows and columns.
     *
     * @return void
     */
    public function test_transpose(): void {
        $matrix = new matrix([[1, 2, 3], [4, 5, 6]]);

        $this->assertEquals([[1, 4], [2, 5], [3, 6]], $this->toarray($matrix->transpose()));
    }
}
