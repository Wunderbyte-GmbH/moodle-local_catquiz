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
        // TODO $matrix validation

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

        for($i = 0; $i < $numberofrows; $i++) {
            $matrix = @$this->oneOperation($matrix, $i, $i);

            for($j = 0; $j < $numberofrows; $j++) {
                if($i !== $j) {
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
        if($matrix[$rowposition][$zeroposition] !== 1)
        {
            $numberofcols = count($matrix[$rowposition]);

            if($matrix[$rowposition][$zeroposition] === 0) {
                $divisor = 0.0000000001;
                $matrix[$rowposition][$zeroposition] = 0.0000000001;
            } else {
                $divisor = $matrix[$rowposition][$zeroposition];
            }

            for($i = 0; $i < $numberofcols; $i++) {
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

        if($matrix[$rowposition][$zeroposition] !== 0) {
            $numbertosubtract = $matrix[$rowposition][$zeroposition];

            for($i = 0; $i < $numberofcols; $i++) {
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
        $inversematrix = array();
        $matrixcount = count($matrix);

        for($i = 0; $i < $matrixcount; $i++) {
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
        // TODO $matrix & $identityMatrix compliance validation (same number of rows/columns, etc)

        $augmentedmatrix = array();

        for($i = 0; $i < count($matrix); $i++) {
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
        // TODO validate $size

        $identitymatrix = array();

        for($i = 0; $i < $size; $i++) {
            for($j = 0; $j < $size; $j++) {
                if($i == $j) {
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

    /**
     * Adds everything correctly together regardless of given data structur (float, array, callables)
     *
     * @param float|array|callable $summands
     * @return float|array|callable
     */
    public function multi_sum (...$summands) {
    	// Test, if argument is given as packed array of arguments and unpack.
    	if (is_array($summands[0])) {
    		if (count($summands) == 1) {
    		    // Unpack arguments.
    			$summands = $summands[0];
    		}
    	}
    	
    	if (is_array($summands[0])) {
    		// Check whether all sumanands are of same dimension.
    		$summand_count = count($summands[0]);
    		foreach ($summands as $summand)
    		{
    			if (count($summand) <> $summand_count) {
    			    // Throw exception error - there should be no calculation if summand-arrays are of different length.
    			    // console("Summanden haben unterschiedliche Dimension!");
                    return false;
    			}
    		}
    	
    		for($i = 0; $i < $summand_count; $i++) {
    			// Call recursivly for each dimension.
    			$new_args = array();
    			foreach ($summands as $summand)
    			{
    				$new_args[] = $summand[$i];
    			}
    			$sum[$i] = multi_sum($new_args);
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

/*
// @DAVID: Die folgenden Zeilen sind Testfälle für die Methode multi_sum, mit floats, arrays und callables.
// Bitte in einem Unit-Test implementieren und dann hier aus dem Quelltext wieder löschen. Danke
$a = 2;
$b = 5;
$c = 3;

print_r (multi_sum($a, $b, $c));
// Expected: 10

$a = [1, 4, 7];
print_r (multi_sum($a));
// Expected: 12

$a = [1, 2, 3];
$b = [4, 5, 6];
$c = [7, 8, 9];

print_r (multi_sum($a, $b, $c));
// Expected [12, 15, 18]

$a = [[1 ,2],[3, 4]];
$b = [[5, 6], [7,8]];
print_r (multi_sum($a, $b));
// Expected [6, 8], [10, 12]

$fn_a = fn($x) => [[1 + $x,2],[3, 4 * $x]];
$fn_b = fn($x) => [[5, 6 - $x], [7 * $x,8]];

$fn_sum = fn($x) => multi_sum($fn_a($x), $fn_b($x));
print_r ($fn_sum(3));
// Expected  [[9, 5], [24, 20]]
    */

    /**
     * Re-Builds an Array of Callables into a Callable that delivers an Array
     *
     * @param array<callable> $fn_function
     * @return callable<array>
     */
    public function build_callable_array ($fn_function) {
        return function($x) use($fn_function) {
            foreach ($fn_function as $key => $f) {
            	$fn_function[$key] = $f($x);
            }
        	return $fn_function;
        };
    }
/*
// @DAVID: Die folgenden Zeilen sind Testfälle für die Methode multi_sum, mit floats, arrays und callables.
// Bitte in einem Unit-Test implementieren und dann hier aus dem Quelltext wieder löschen. Danke
$fn_array = [fn($x) => 1 * $x, fn($x) => 2 * $x, fn($x) => 3 * $x];

$fn_function = build_callable_array($fn_array);
print_r ($fn_function(5));
// Expected: [5, 10, 15]
*/
}
