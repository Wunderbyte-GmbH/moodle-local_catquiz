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
 * Tests the personability_loader class.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\context\loader;

use local_catquiz\teststrategy\progress;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests the personability_loader class.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\teststrategy\strategy\personability_loader
 */
final class personability_loader_test extends TestCase {

    /**
     * Tests that the ability is not updated in cases where it should not be updated.
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function test_personability_loader_works_as_expected() {
        // This is almost the same class as the real personability_loader. It
        // just changes the part that retrieves the person abilities from the
        // database.
        $loader = new personability_loader_testing();

        $progressstub = $this->createMock(progress::class);
        $progressstub->method('is_first_question')
            ->willReturn(false);
        $context = [
            'contextid' => 1,
            'catscaleid' => 10,
            'userid' => 2,
            'progress' => $progressstub,
            'fake_personparams' => [
                9 => 1.23,
                10 => 1.1,
            ],
        ];

        $expected = [
            'contextid' => 1,
            'catscaleid' => 10,
            'userid' => 2,
            'person_ability' => [
                9 => 1.23,
                10 => 1.1,
            ],
            'progress' => $progressstub,
        ];
        $result = $loader->load($context);
        $this->assertEquals($expected, $result);
    }
}
