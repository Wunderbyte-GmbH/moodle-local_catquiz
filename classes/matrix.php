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
 * @copyright 2023 Wunderbyte GmbH
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
    private $_rows;

    /**
     * Number of columns in the matrix.
     *
     * @var int
     */
    private $_cols;

    /**
     * Create a matrix from another matrix, an array or with its size (rows, cols).
     *
     * @param mixed $value Matrix, array or number of rows
     * @throws MatrixException Wrong parameters
     */
    public function __construct($value, $cols = null) {
        if ($value instanceof self) {
            $matrix = $value;
            $this->_rows = $matrix->_rows;
            $this->_cols = $matrix->_cols;
            for ($r = 0; $r < $this->_rows; $r++) {
                $this[$r] = [];
                for ($c = 0; $c < $this->_cols; $c++) {
                    $this[$r][$c] = $matrix[$r][$c];
                }
            }
        } else if (is_array($value) && $cols == null) {
            parent::__construct($value);
            $this->_rows = count($value);
            $this->_cols = count($value[0]);
        } else if (is_numeric($value) && is_numeric($cols)
                && $value > 0 && $cols > 0
        ) {
            $this->_rows = $value;
            $this->_cols = $cols;
            for ($r = 0; $r < $this->_rows; $r++) {
                $this[$r] = [];
                for ($c = 0; $c < $this->_cols; $c++) {
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
            if ($this->_rows == $matrix->_rows && $this->_cols == $matrix->_cols) {
                $result = new self($this);
                for ($r = 0; $r < $this->_rows; $r++) {
                    for ($c = 0; $c < $this->_cols; $c++) {
                        $result[$r][$c] += $matrix[$r][$c];
                    }
                }
                return $result;
            }
            throw new MatrixException('Cannot add matrices: matrices do not have the same size');
        } else {
            $result = new self($this);
            for ($r = 0; $r < $result->_rows; $r++) {
                for ($c = 0; $c < $result->_cols; $c++) {
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
            if ($this->_rows == $matrix->_rows && $this->_cols == $matrix->_cols) {
                $result = new self($this);
                for ($r = 0; $r < $this->_rows; $r++) {
                    for ($c = 0; $c < $this->_cols; $c++) {
                        $result[$r][$c] -= $matrix[$r][$c];
                    }
                }
                return $result;
            }
            throw new MatrixException('Cannot subtract matrices: matrices do not have the same size');
        } else {
            $result = new self($this);
            for ($r = 0; $r < $result->_rows; $r++) {
                for ($c = 0; $c < $result->_cols; $c++) {
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
            if ($this->_cols != $matrix->_rows) {
                throw new MatrixException('Cannot multiply matrices: incompatible matrices');
            }
            $resultArray = [];
            for ($i = 0; $i < $this->_rows; $i++) {
                for ($j = 0; $j < $matrix->_cols; $j++) {
                    $resultArray[$i][$j] = 0;
                    for ($k = 0; $k < $matrix->_rows; $k++) {
                        $resultArray[$i][$j] += $this[$i][$k] * $matrix[$k][$j];
                    }
                }
            }
            return new self($resultArray);
        } else {
            $result = new self($this->_rows, $this->_cols);
            for ($r = 0; $r < $result->_rows; $r++) {
                for ($c = 0; $c < $result->_cols; $c++) {
                    $result[$r][$c] = $this[$r][$c] * $value;
                }
            }
            return $result;
        }
    }

    /**
     * Return a new sub matrix from this matrix.
     *
     * @param int $rowOffset Row offset to avoid
     * 
     * @param int $colOffset Col offset to avoid
     * 
     * @return Matrix The new sub matrix
     */
    public function subMatrix($rowOffset, $colOffset) {
        $subArray = [];
        for ($r = 0, $sR = 0; $r < $this->_rows; $r++) {
            if ($r != $rowOffset) {
                $subArray[$sR] = [];
                for ($c = 0, $sC = 0; $c < $this->_cols; $c++) {
                    if ($c != $colOffset) {
                        $subArray[$sR][$sC] = $this[$r][$c];
                        $sC++;
                    }
                }
                $sR++;
            }
        }
        return new self($subArray);
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
        if ($this->_rows == 2) {
            return $this[0][0] * $this[1][1] - $this[0][1] * $this[1][0];
        } else {
            $out = 0;
            for ($c = 0; $c < $this->_cols; $c++) {
                if ($this[0][$c])
                    $out += pow(-1, $c + 2) * $this[0][$c] * $this->subMatrix(0, $c)->determinant();
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
        $cofactorArray = [];
        for ($c = 0; $c < $this->_cols; $c++) {
            $cofactorArray[$c] = [];
            for ($r = 0; $r < $this->_rows; $r++) {
                if ($this->_cols == 2) {
                    $cofactorArray[$c][$r] = pow(-1, $c + $r) * $this->subMatrix($c, $r)[0][0];
                } else {
                    $cofactorArray[$c][$r] = pow(-1, $c + $r) * $this->subMatrix($c, $r)->determinant();
                }
            }
        }
        return new self($cofactorArray);
    }

    /**
     * Gets a new transposed matrix from this one, return a new matrix instance.
     *
     * @return Matrix The new transposed matrix
     */
    public function transpose() {
        $resultArray = [];
        for ($i = 0; $i < $this->_cols; $i++) {
            for ($j = 0; $j < $this->_rows; $j++) {
                $resultArray[$i][$j] = $this[$j][$i];
            }
        }
        return new self($resultArray);
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
        for ($r = 0; $r < $this->_rows; $r++) {
            for ($c = 0; $c < $this->_cols; $c++) {
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
    public function getRows() {
        return $this->_rows;
    }

    /**
     * Get the number of columns.
     *
     * @return int The number of columns
     */
    public function getCols() {
        return $this->_cols;
    }

    /**
     * Checks if two matrices are equal in value.
     *
     * @param Matrix $matrix The second matrix
     * @return boolean
     */
    public function equals(Matrix $matrix) {
        if ($this->_rows != $matrix->_rows || $this->_cols != $matrix->_cols) {
            return false;
        }
        for ($r = 0; $r < $this->_rows; $r++) {
            for ($c = 0; $c < $this->_cols; $c++) {
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
    public function isSquare() {
        return $this->_rows == $this->_cols;
    }

}

use RuntimeException;

/**
 * Simple matrix exception.
 *
 * @author Romain Vermot <romain@vermot.eu>
 * @license MIT
 */
class MatrixException extends RuntimeException {
}
