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
 * Class for math functions;
 *
 * @package local_catquiz
 * @author Daniel Pasterk
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use ArrayObject;

/**
 * Matrix basic implementation.
 *
 * Different ways are available to instanciate a Matrix:
 * - by setting with another Matrix
 * - by setting all values using bidimensional array,
 * - by setting its columns and rows
 *
 * Examples:
 * $m = new Matrix(4, 2); // 4 rows, 2 columns
 *
 * $m = new Matrix([
 *      [42, 21],
 *      [84, 0],
 *      [20, -21],
 * ]);
 *
 * $m2 = new Matrix($m3);
 *
 * You can also : add, subtract and multiply your matrix with scalar or Matrix
 * There are methods to compute determinant, to invert the matrix
 *
 * See methods to have more information!
 *
 * @package local_catquiz
 * @author Romain Vermot <romain@vermot.eu>
 * @license MIT
 */
class matrix extends ArrayObject {
    /**
     * Number of rows in the matrix.
     *
     * @var int
     */
    private $rows;

    /**
     * Number of columns in the matrix.
     *
     * @var int
     */
    private $cols;

    /**
     * Create a matrix from another matrix, an array or with its size (rows, cols).
     *
     * @param mixed $value Matrix, array or number of rows
     * @param mixed|null $cols
     *
     * @throws MatrixException Wrong parameters
     *
     */
    public function __construct($value, $cols = null) {
        if ($value instanceof self) {
            $matrix = $value;
            $this->rows = $matrix->rows;
            $this->cols = $matrix->cols;
            for ($r = 0; $r < $this->rows; $r++) {
                $this[$r] = [];
                for ($c = 0; $c < $this->cols; $c++) {
                    $this[$r][$c] = $matrix[$r][$c];
                }
            }
        } else if ($cols == null) {
            // Check, if $value is array.
            if (is_array($value)) {
                // Strip of any associated indices.
                $value = array_values($value);
                // Check if $value is not an array of array.
                if (is_array($value[0])) {
                    // Note: Also strip of any further associated indices.
                    foreach ($value as $key => $val) {
                        $value[$key] = array_values($val);
                    }
                } else {
                    // Note: Vector is given, convert to proper matrix.
                    $value = [$value];
                }
            } else {
                // Note: int|float is given, convert to proper matrix.
                $value = [[floatval($value)]];
            }
            parent::__construct($value);
            $this->rows = count($value);
            $this->cols = count($value[0]);
        } else if (
            is_numeric($value) && is_numeric($cols)
                && $value > 0 && $cols > 0
        ) {
            // Create a void matrix with dimensions $value x $cols.
            $this->rows = $value;
            $this->cols = $cols;
            for ($r = 0; $r < $this->rows; $r++) {
                $this[$r] = [];
                for ($c = 0; $c < $this->cols; $c++) {
                    $this[$r][$c] = 0;
                }
            }
        } else {
            throw new MatrixException('Cannot create matrix');
        }
    }


    /**
     * Add another matrix or a scalar to this matrix, return a new matrix instance.
     *
     * @param mixed $value Matrix or scalar to add to this Matrix
     *
     * @return Matrix New result matrix
     *
     * @throws MatrixException If matrices do not have the same size
     */
    public function add($value) {
        if ($value instanceof self) {
            $matrix = $value;
            if ($this->rows == $matrix->rows && $this->cols == $matrix->cols) {
                $result = new self($this);
                for ($r = 0; $r < $this->rows; $r++) {
                    for ($c = 0; $c < $this->cols; $c++) {
                        $result[$r][$c] += $matrix[$r][$c];
                    }
                }
                return $result;
            }
            throw new MatrixException('Cannot add matrices: matrices do not have the same size');
        } else {
            $result = new self($this);
            for ($r = 0; $r < $result->rows; $r++) {
                for ($c = 0; $c < $result->cols; $c++) {
                    $result[$r][$c] += $value;
                }
            }
            return $result;
        }
    }

    /**
     * Subtract another matrix or a scalar to this matrix, return a new matrix instance.
     *
     * @param mixed $value matrix or scalar to subtract to this matrix
     *
     * @return Matrix New result matrix
     *
     * @throws MatrixException If matrices do not have the same size
     */
    public function subtract($value) {
        if ($value instanceof self) {
            $matrix = $value;
            if ($this->rows == $matrix->rows && $this->cols == $matrix->cols) {
                $result = new self($this);
                for ($r = 0; $r < $this->rows; $r++) {
                    for ($c = 0; $c < $this->cols; $c++) {
                        $result[$r][$c] -= $matrix[$r][$c];
                    }
                }
                return $result;
            }
            throw new MatrixException('Cannot subtract matrices: matrices do not have the same size');
        } else {
            $result = new self($this);
            for ($r = 0; $r < $result->rows; $r++) {
                for ($c = 0; $c < $result->cols; $c++) {
                    $result[$r][$c] -= $value;
                }
            }
            return $result;
        }
    }

    /**
     * Multiply another matrix or a scalar to this matrix, return a new matrix instance.
     *
     * @param mixed $value matrix or scalar to multiply to this matrix
     *
     * @return Matrix New result matrix
     *
     * @throws MatrixException If matrices are incompatible
     */
    public function multiply($value) {
        if ($value instanceof self) {
            $matrix = $value;
            if ($this->cols != $matrix->rows) {
                throw new MatrixException('Cannot multiply matrices: incompatible matrices');
            }
            $resultarray = [];
            for ($i = 0; $i < $this->rows; $i++) {
                for ($j = 0; $j < $matrix->cols; $j++) {
                    $resultarray[$i][$j] = 0;
                    for ($k = 0; $k < $matrix->rows; $k++) {
                        $resultarray[$i][$j] += $this[$i][$k] * $matrix[$k][$j];
                    }
                }
            }
            return new self($resultarray);
        } else {
            $result = new self($this->rows, $this->cols);
            for ($r = 0; $r < $result->rows; $r++) {
                for ($c = 0; $c < $result->cols; $c++) {
                    $result[$r][$c] = $this[$r][$c] * $value;
                }
            }
            return $result;
        }
    }

    /**
     * Return a new sub matrix from this matrix.
     *
     * @param int $rowoffset Row offset to avoid
     *
     * @param int $coloffset Col offset to avoid
     *
     * @return Matrix The new sub matrix
     */
    public function submatrix($rowoffset, $coloffset) {
        $subarray = [];
        for ($r = 0, $sr = 0; $r < $this->rows; $r++) {
            if ($r != $rowoffset) {
                $subarray[$sr] = [];
                for ($c = 0, $sc = 0; $c < $this->cols; $c++) {
                    if ($c != $coloffset) {
                        $subarray[$sr][$sc] = $this[$r][$c];
                        $sc++;
                    }
                }
                $sr++;
            }
        }
        return new self($subarray);
    }

    /**
     * Returns an identity matrix with the same dimensions as this matrix.
     *
     * @return matrix Identity matrix.
     * @throws MatrixException If this matrix is not square.
     */
    public function identity(): matrix {
        if (!$this->isSquare()) {
            throw new MatrixException('Cannot create identity matrix from a non-square matrix.');
        }

        $identity = new self($this->rows, $this->cols);
        for ($index = 0; $index < $this->rows; $index++) {
            $identity[$index][$index] = 1.0;
        }

        return $identity;
    }

    /**
     * Computes the matrix's determinant.
     *
     * @return float The matrix's determinant
     *
     * @throws MatrixException If matrix is not a square
     */
    public function determinant() {
        if (!$this->isSquare()) {
            throw new MatrixException('Cannot compute determinant of non square matrix!');
        }
        if ($this->rows == 1) {
            return $this[0][0];
        } else if ($this->rows == 2) {
            return $this[0][0] * $this[1][1] - $this[0][1] * $this[1][0];
        } else {
            $out = 0;
            for ($c = 0; $c < $this->cols; $c++) {
                if ($this[0][$c]) {
                    $out += pow(-1, $c + 2) * $this[0][$c] * $this->subMatrix(0, $c)->determinant();
                }
            }
            return $out;
        }
    }

    /**
     * Compute cofactor matrix from this one, return a new matrix instance.
     *
     * @return Matrix The new computed matrix
     */
    public function cofactor() {
        $cofactorarray = [];
        for ($c = 0; $c < $this->cols; $c++) {
            $cofactorarray[$c] = [];
            for ($r = 0; $r < $this->rows; $r++) {
                if ($this->cols == 1) {
                    $cofactorarray[$c][$r] = 1;
                } else if ($this->cols == 2) {
                    $cofactorarray[$c][$r] = pow(-1, $c + $r) * $this->subMatrix($c, $r)[0][0];
                } else {
                    $cofactorarray[$c][$r] = pow(-1, $c + $r) * $this->subMatrix($c, $r)->determinant();
                }
            }
        }
        return new self($cofactorarray);
    }

    /**
     * Gets a new transposed matrix from this one, return a new matrix instance.
     *
     * @return Matrix The new transposed matrix
     */
    public function transpose() {
        $resultarray = [];
        for ($i = 0; $i < $this->cols; $i++) {
            $resultarray[$i] = [];
            for ($j = 0; $j < $this->rows; $j++) {
                $resultarray[$i][$j] = $this[$j][$i];
            }
        }
        return new self($resultarray);
    }

    /**
     * Adjugate the matrix, return a new matrix instance.
     *
     * @return Matrix The computed matrix
     */
    public function adjugate() {
        return $this->cofactor()->transpose();
    }

    /**
     * Inverse this matrix if and only if the determinant is not null, return a new matrix instance.
     *
     * @return Matrix The inverted matrix
     * @throws MatrixException If determinant is null
     */
    public function inverse() {
        $det = $this->determinant();
        if ($det == 0) {
            throw new MatrixException('Cannot invert matrix: determinant is nul!');
        }
        return $this->adjugate()->multiply(1 / $det);
    }

    /**
     * Returns human readable matrix string like a pseudo table.
     *
     * @return string The matrix
     */
    public function __toString() {
        $out = '';
        for ($r = 0; $r < $this->rows; $r++) {
            for ($c = 0; $c < $this->cols; $c++) {
                if ($c) {
                    $out .= "\t";
                }
                $out .= $this[$r][$c];
            }
            $out .= "\n";
        }
        return $out;
    }

    /**
     * Get the number of rows.
     *
     * @return int The number of rows
     */
    public function getrows() {
        return $this->rows;
    }

    /**
     * Get the number of columns.
     *
     * @return int The number of columns
     */
    public function getcols() {
        return $this->cols;
    }

    /**
     * Calculates the square root of the summed squared elements
     * of the matrix.
     *
     * return float
     */
    public function rooted_summed_squares(): float {
        $result = 0;
        for ($r = 0; $r < $this->rows; $r++) {
            for ($c = 0; $c < $this->cols; $c++) {
                $result += $this[$r][$c] ** 2;
            }
        }
        return sqrt($result);
    }

    /**
     * Returns the value of the highest absolute elemente
     * of the matrix.
     *
     * return float
     */
    public function max_absolute_element(): float {
        $result = 0;
        for ($r = 0; $r < $this->rows; $r++) {
            for ($c = 0; $c < $this->cols; $c++) {
                $result = (abs($this[$r][$c]) > $result) ? (abs($this[$r][$c])) : $result;
            }
        }
        return $result;
    }

    /**
     * Print the matrix as pretty php code (bracket array).
     *
     */
    public function print_m() {
        echo '(' . $this->rows . " x " . $this->cols . ")-matrix : [";
        for ($r = 0; $r < $this->rows; $r++) {
            echo "[";
            for ($c = 0; $c < $this->cols; $c++) {
                echo ' ' . round(floatval($this[$r][$c]), 7) . (($c < (($this->cols) - 1)) ? ', ' : ' ');
            }
            echo ']' . (($r < ($this->rows) - 1) ? ", " : "");
        }
        echo ']';
    }

    /**
     * Checks if two matrices are equal in value.
     *
     * @param Matrix $matrix The second matrix
     * @return boolean
     */
    public function equals(Matrix $matrix) {
        if ($this->rows != $matrix->rows || $this->cols != $matrix->cols) {
            return false;
        }
        for ($r = 0; $r < $this->rows; $r++) {
            for ($c = 0; $c < $this->cols; $c++) {
                if ($this[$r][$c] != $matrix[$r][$c]) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Return true if the matrix is a square matrix.
     *
     * @return boolean
     */
    public function issquare() {
        return $this->rows == $this->cols;
    }

    /**
     * Returns an identity matrix of the given size as a nested array.
     *
     * Plain-array companion to {@see self::identity()} for numerical routines
     * that operate on dense arrays rather than matrix instances.
     *
     * @param int $size Matrix dimension.
     * @return array
     */
    public static function identity_array(int $size): array {
        $matrix = array_fill(0, $size, array_fill(0, $size, 0.0));
        for ($index = 0; $index < $size; $index++) {
            $matrix[$index][$index] = 1.0;
        }
        return $matrix;
    }

    /**
     * Multiplies a matrix (nested array) with a vector (flat array).
     *
     * @param array $matrix Matrix as a nested array.
     * @param array $vector Vector as a flat array.
     * @return array Resulting vector as a flat array.
     */
    public static function matrix_vector_product(array $matrix, array $vector): array {
        $result = [];
        for ($i = 0; $i < count($matrix); $i++) {
            $result[$i] = 0;
            for ($j = 0; $j < count($matrix[$i]); $j++) {
                $result[$i] += $matrix[$i][$j] * $vector[$j];
            }
        }
        return $result;
    }

    /**
     * Calculates the scalar product of two vectors.
     *
     * @param array $left First vector.
     * @param array $right Second vector.
     * @return float
     */
    public static function dot_product(array $left, array $right): float {
        if (count($left) !== count($right)) {
            throw new \InvalidArgumentException('Vector dimensions do not match.');
        }
        $result = 0.0;
        foreach ($left as $index => $value) {
            $result += $value * $right[$index];
        }
        return $result;
    }

    /**
     * Subtracts the second vector from the first vector.
     *
     * @param array $left First vector.
     * @param array $right Second vector.
     * @return array
     */
    public static function vector_subtract(array $left, array $right): array {
        if (count($left) !== count($right)) {
            throw new \InvalidArgumentException('Vector dimensions do not match.');
        }
        $result = [];
        foreach ($left as $index => $value) {
            $result[$index] = $value - $right[$index];
        }
        return $result;
    }

    /**
     * Returns the largest absolute value in a vector.
     *
     * @param array $vector Vector to inspect.
     * @return float
     */
    public static function max_absolute_value(array $vector): float {
        $result = 0.0;
        foreach ($vector as $value) {
            $result = max($result, abs((float) $value));
        }
        return $result;
    }
}
