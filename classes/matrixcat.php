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
 * Class for math functions.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

/**
 * Implements matrix functions.
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matrixcat {
    /**
     * Gauss-Jordan elimination method for matrix inverse
     *
     * @param array $matrix
     *
     * @return array
     *
     */
    public function inversematrix(array $matrix) {
        // TODO $matrix validation.

        $matrixcount = count($matrix);

        $identitymatrix = $this->identityMatrix($matrixcount);
        $augmentedmatrix = $this->appendIdentityMatrixToMatrix($matrix, $identitymatrix);
        $inversematrixwithidentity = $this->createInverseMatrix($augmentedmatrix);
        $inversematrix = $this->removeIdentityMatrix($inversematrixwithidentity);

        return $inversematrix;
    }

    /**
     * Creates inverse matrix.
     *
     * @param array $matrix
     *
     * @return array
     *
     */
    private function createinversematrix(array $matrix) {
        $numberofrows = count($matrix);

        for ($i = 0; $i < $numberofrows; $i++) {
            $matrix = @$this->oneOperation($matrix, $i, $i);

            for ($j = 0; $j < $numberofrows; $j++) {
                if ($i !== $j) {
                    $matrix = $this->zeroOperation($matrix, $j, $i, $i);
                }
            }
        }
        $inversematrixwithidentity = $matrix;

        return $inversematrixwithidentity;
    }

    /**
     * Execute operation one on matrix element.
     *
     * @param array $matrix
     * @param mixed $rowposition
     * @param mixed $zeroposition
     *
     * @return array
     *
     */
    private function oneoperation(array $matrix, $rowposition, $zeroposition) {
        if ($matrix[$rowposition][$zeroposition] !== 1) {
            $numberofcols = count($matrix[$rowposition]);

            if ($matrix[$rowposition][$zeroposition] === 0) {
                $divisor = 0.0000000001;
                $matrix[$rowposition][$zeroposition] = 0.0000000001;
            } else {
                $divisor = $matrix[$rowposition][$zeroposition];
            }

            for ($i = 0; $i < $numberofcols; $i++) {
                $matrix[$rowposition][$i] = $matrix[$rowposition][$i] / $divisor;
            }
        }

        return $matrix;
    }

    /**
     * Execute operation zero on matrix element.
     *
     * @param array $matrix
     * @param mixed $rowposition
     * @param mixed $zeroposition
     * @param mixed $subjectrow
     *
     * @return array
     *
     */
    private function zerooperation(array $matrix, $rowposition, $zeroposition, $subjectrow) {
        $numberofcols = count($matrix[$rowposition]);

        if ($matrix[$rowposition][$zeroposition] !== 0) {
            $numbertosubtract = $matrix[$rowposition][$zeroposition];

            for ($i = 0; $i < $numberofcols; $i++) {
                $matrix[$rowposition][$i] = $matrix[$rowposition][$i] - $numbertosubtract * $matrix[$subjectrow][$i];
            }
        }

        return $matrix;
    }

    /**
     * Remove identity matrix.
     *
     * @param array $matrix
     *
     * @return array
     *
     */
    private function removeidentitymatrix(array $matrix) {
        $inversematrix = [];
        $matrixcount = count($matrix);

        for ($i = 0; $i < $matrixcount; $i++) {
            $inversematrix[$i] = array_slice($matrix[$i], $matrixcount);
        }

        return $inversematrix;
    }

    /**
     * Append identity matrix to matrix.
     *
     * @param array $matrix
     * @param array $identitymatrix
     *
     * @return array
     *
     */
    private function appendidentitymatrixtomatrix(array $matrix, array $identitymatrix) {
        // TODO $matrix & $identityMatrix compliance validation (same number of rows/columns, etc).

        $augmentedmatrix = [];

        for ($i = 0; $i < count($matrix); $i++) {
            $augmentedmatrix[$i] = array_merge($matrix[$i], $identitymatrix[$i]);
        }

        return $augmentedmatrix;
    }

    /**
     * Returns Identity Matrix of given size.
     *
     * @param int $size
     *
     * @return array
     *
     */
    public function identitymatrix(int $size) {
        // TODO validate $size.

        $identitymatrix = [];

        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                if ($i == $j) {
                    $identitymatrix[$i][$j] = 1;
                } else {
                    $identitymatrix[$i][$j] = 0;
                }
            }
        }

        return $identitymatrix;
    }

    /**
     * Multiply matrices.
     *
     * @param mixed $matrix1
     * @param mixed $matrix2
     *
     * @return mixed
     *
     */
    public function multiplymatrices($matrix1, $matrix2) {
        $rows1 = count($matrix1);
        $cols1 = count($matrix1[0]);
        $rows2 = count($matrix2);
        $cols2 = count($matrix2[0]);

        if ($cols1 !== $rows2) {
            // Matrices are not compatible for multiplication.
            return null;
        }

        $result = [];

        for ($i = 0; $i < $rows1; $i++) {
            $row = [];
            for ($j = 0; $j < $cols2; $j++) {
                $sum = 0;
                for ($k = 0; $k < $cols1; $k++) {
                    $sum += $matrix1[$i][$k] * $matrix2[$k][$j];
                }
                $row[] = $sum;
            }
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Returns flatten array.
     *
     * @param mixed $array
     *
     * @return array
     *
     */
    public function flattenarray($array) {
        $result = [];

        foreach ($array as $element) {
            if (is_array($element)) {
                $result = array_merge($result, $this->flattenArray($element));
            } else {
                $result[] = $element;
            }
        }

        return $result;
    }

    /**
     * Substracts two vectors
     * @param mixed $vector1
     * @param mixed $vector2
     * @throws \InvalidArgumentException
     * @return array
     */
    public function subtractvectors($vector1, $vector2) {
        if (count($vector1) != count($vector2)) {
            throw new \InvalidArgumentException("Vectors should have the same length for subtraction");
        }

        $result = [];
        $length = count($vector1);

        for ($i = 0; $i < $length; $i++) {
            $result[] = $vector1[$i] - $vector2[$i];
        }

        return $result;
    }


    /**
     * Returns vestor's distance.
     *
     * @param mixed $vector1
     * @param mixed $vector2
     *
     * @return mixed
     *
     */
    public function dist($vector1, $vector2) {
        if (count($vector1) !== count($vector2)) {
            throw new \InvalidArgumentException('Vectors must have the same number of elements');
        }

        $distance = 0;
        $length = count($vector1);

        for ($i = 0; $i < $length; $i++) {
            $distance += abs($vector1[$i] - $vector2[$i]);
        }

        return $distance;
    }

    /**
     * Adds everything correctly together regardless of given data structur (float, array, callables)
     * param float|array|callable $summands
     * return float|array|callable
     *
     * @param mixed ...$summands
     * @return mixed
     */
    public static function multi_sum(...$summands) {
        // Test, if argument is given as packed array of arguments and unpack.
        if (is_array($summands[0])) {
            if (count($summands) == 1) {
                // Unpack arguments.
                $summands = $summands[0];
            }
        }

        if (is_array($summands[0])) {
            // Check whether all sumanands are of same dimension.
            $summandcount = count($summands[0]);
            foreach ($summands as $summand) {
                if (count($summand) <> $summandcount) {
                    // Throw exception error - there should be no calculation if summand-arrays are of different length.
                    // phpcs:ignore
                    // console("Summanden haben unterschiedliche Dimension!");
                    return false;
                }
            }

            for ($i = 0; $i < $summandcount; $i++) {
                // Call recursivly for each dimension.
                $newargs = [];
                foreach ($summands as $summand) {
                    $newargs[] = $summand[$i];
                }
                $sum[$i] = self::multi_sum($newargs);
            }
        } else {
            // If entrys are just floats, add them together.
            $sum = 0;
            foreach ($summands as $summand) {
                $sum += $summand;
            }
        }
        return $sum;
    }

    /**
     * Re-Builds an Array of Callables into a Callable that delivers an Array
     *
     * @param array<callable> $fnfunction
     * @return callable<array>
     */
    public static function build_callable_array($fnfunction) {
        return function($x) use($fnfunction) {
            foreach ($fnfunction as $key => $f) {
                $fnfunction[$key] = $f($x);
            }
            return $fnfunction;
        };
    }
}
