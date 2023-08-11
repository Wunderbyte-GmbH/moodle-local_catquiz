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
    public function inverseMatrix(array $matrix) {
        //TODO $matrix validation

        $matrixCount = count($matrix);

        $identityMatrix = $this->identityMatrix($matrixCount);
        $augmentedMatrix = $this->appendIdentityMatrixToMatrix($matrix, $identityMatrix);
        $inverseMatrixWithIdentity = $this->createInverseMatrix($augmentedMatrix);
        $inverseMatrix = $this->removeIdentityMatrix($inverseMatrixWithIdentity);

        return $inverseMatrix;
    }

    /**
     * Creates inverse matrix.
     *
     * @param array $matrix
     * 
     * @return array
     * 
     */
    private function createInverseMatrix(array $matrix) {
        $numberOfRows = count($matrix);

        for($i=0; $i<$numberOfRows; $i++) {
            $matrix = @$this->oneOperation($matrix, $i, $i);

            for($j=0; $j<$numberOfRows; $j++) {
                if($i !== $j) {
                    $matrix = $this->zeroOperation($matrix, $j, $i, $i);
                }
            }
        }
        $inverseMatrixWithIdentity = $matrix;

        return $inverseMatrixWithIdentity;
    }

    /**
     * Execute operation one on matrix element.
     *
     * @param array $matrix
     * @param mixed $rowPosition
     * @param mixed $zeroPosition
     * 
     * @return array
     * 
     */
    private function oneOperation(array $matrix, $rowPosition, $zeroPosition) {
        if($matrix[$rowPosition][$zeroPosition] !== 1)
        {
            $numberOfCols = count($matrix[$rowPosition]);

            if($matrix[$rowPosition][$zeroPosition] === 0) {
                $divisor = 0.0000000001;
                $matrix[$rowPosition][$zeroPosition] = 0.0000000001;
            } else {
                $divisor = $matrix[$rowPosition][$zeroPosition];
            }

            for($i=0; $i<$numberOfCols; $i++) {
                $matrix[$rowPosition][$i] = $matrix[$rowPosition][$i] / $divisor;
            }
        }

        return $matrix;
    }

    /**
     * Execute operation zero on matrix element.
     *
     * @param array $matrix
     * @param mixed $rowPosition
     * @param mixed $zeroPosition
     * @param mixed $subjectRow
     * 
     * @return array
     * 
     */
    private function zeroOperation(array $matrix, $rowPosition, $zeroPosition, $subjectRow) {
        $numberOfCols = count($matrix[$rowPosition]);

        if($matrix[$rowPosition][$zeroPosition] !== 0) {
            $numberToSubtract = $matrix[$rowPosition][$zeroPosition];

            for($i=0; $i<$numberOfCols; $i++) {
                $matrix[$rowPosition][$i] = $matrix[$rowPosition][$i] - $numberToSubtract * $matrix[$subjectRow][$i];
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
    private function removeIdentityMatrix(array $matrix) {
        $inverseMatrix = array();
        $matrixCount = count($matrix);

        for($i=0; $i<$matrixCount; $i++) {
            $inverseMatrix[$i] = array_slice($matrix[$i], $matrixCount);
        }

        return $inverseMatrix;
    }

    /**
     * Append identity matrix to matrix.
     *
     * @param array $matrix
     * @param array $identityMatrix
     * 
     * @return array
     * 
     */
    private function appendIdentityMatrixToMatrix(array $matrix, array $identityMatrix) {
        //TODO $matrix & $identityMatrix compliance validation (same number of rows/columns, etc)

        $augmentedMatrix = array();

        for($i=0; $i<count($matrix); $i++) {
            $augmentedMatrix[$i] = array_merge($matrix[$i], $identityMatrix[$i]);
        }

        return $augmentedMatrix;
    }

    /**
     * Returns Identity Matrix of given size.
     *
     * @param int $size
     * 
     * @return array
     * 
     */
    public function identityMatrix(int $size) {
        //TODO validate $size

        $identityMatrix = array();

        for($i=0; $i<$size; $i++) {
            for($j=0; $j<$size; $j++) {
                if($i == $j) {
                    $identityMatrix[$i][$j] = 1;
                } else {
                    $identityMatrix[$i][$j] = 0;
                }
            }
        }

        return $identityMatrix;
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
    public function multiplyMatrices($matrix1, $matrix2) {
        $rows1 = count($matrix1);
        $cols1 = count($matrix1[0]);
        $rows2 = count($matrix2);
        $cols2 = count($matrix2[0]);

        if ($cols1 !== $rows2) {
            // Matrices are not compatible for multiplication
            return null;
        }

        $result = array();

        for ($i = 0; $i < $rows1; $i++) {
            $row = array();
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
    public function flattenArray($array)
    {
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
    public function subtractVectors($vector1, $vector2) {
        if (count($vector1) != count($vector2)) {
            throw new \InvalidArgumentException("Vectors should have the same length for subtraction");
        }

        $result = array();
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
}
