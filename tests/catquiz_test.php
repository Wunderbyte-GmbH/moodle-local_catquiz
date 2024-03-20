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
 * Tests the catquiz class
 *
 * @package    local_catquiz
 * @author     Magdalena Holczik <magdalena.holczik@wunderbyte.at>
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use local_catquiz\catquiz;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests the catquiz class
 *
 * @package    local_catquiz
 * @author     Magdalena Holczik <magdalena.holczik@wunderbyte.at>
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\catquiz
 */
class catquiz_test extends TestCase {

    /**
     * Tests that the ability is not updated in cases where it should not be updated.
     *
     * @dataProvider create_strings_for_enrolement_notification_provider
     *
     * @param array $enrolementarray
     * @param array $expected
     *
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_create_strings_for_enrolement_notification(array $enrolementarray, array $expected) {

        $result = [];
        $result = catquiz::create_strings_for_enrolement_notification($enrolementarray);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for test_simulation_steps_calculated_ability_is_correct()
     *
     * @return array
     */
    public static function create_strings_for_enrolement_notification_provider(): array {

        return [
            'multiplecourseandgroups' => [
                'enrolementarray' => [
                    'course' => [
                        '0' => [
                            'coursename' => 'Kurs 1',
                            'coursesummary' => 'Ein toller Einführungskurs!',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 1',
                            'testname' => 'Testname',
                        ],
                        '1' => [
                            'coursename' => 'Kurs 2',
                            'coursesummary' => 'Ein toller Einführungskurs!',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 2',
                            'testname' => 'Testname',
                            ],
                        '2' => [
                            'coursename' => 'Kurs 3',
                            'coursesummary' => 'Ein toller Einführungskurs!',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 2',
                            'testname' => 'Testname',
                            ],
                        ],
                    'group' => [
                        '0' => [
                            'groupname' => 'Gruppe 1',
                            'groupdescription' => 'Eine tolle Gruppe!',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 1',
                            'coursename' => 'Kurs 1',
                            'testname' => 'Testname',
                        ],
                        '1' => [
                            'groupname' => 'Gruppe 2',
                            'groupdescription' => 'Eine tolle Gruppe!',
                            'coursename' => 'Kurs 2',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 2',
                            'testname' => 'Testname',
                            ],
                        '2' => [
                            'groupname' => 'Gruppe 3',
                            'groupdescription' => 'Eine tolle Gruppe!',
                            'coursename' => 'Kurs 2',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 2',
                            'testname' => 'Testname',
                            ],
                    ],
                ],
                'expected' => [
                    'messagetitle' => "Notification about new course / group enrolments",
                    'messagebody' => "Based on your results in test Testname in course Kurs 1 you are now...<br><br>subscribed in the following course(s):<br><br><ul><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>
                    </li><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a>
                    </li><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a>
                    </li></ul><br>member of the following group(s):<br><br><ul><li>Gruppe 1 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a></li><li>Gruppe 2 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></li><li>Gruppe 3 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></li></ul>Good luck with your studies!",
                    'messageforfeedback' => "Based on your results you are now...<br><br>subscribed in the following course(s):<br><br><ul><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>
                    </li><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a>
                    </li><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a>
                    </li></ul><br>member of the following group(s):<br><br><ul><li>Gruppe 1 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a></li><li>Gruppe 2 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></li><li>Gruppe 3 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></li></ul>Good luck with your studies!",
                ],
            ],
            'singlecourse' => [
                'enrolementarray' => [
                    'course' => [
                        '0' => [
                            'coursename' => 'Kurs 1',
                            'coursesummary' => 'Ein toller Einführungskurs!',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 1',
                            'testname' => 'Testname',
                        ],
                    ],
                ],
                'expected' => [
                    'messagetitle' => "Notification about new course / group enrolments",
                    'messagebody' => 'Because of your test results in "Skala 1", you are now enrolled in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>.',
                    'messageforfeedback' => 'Because of your test results in "Skala 1", you are now enrolled in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>.',
                ],
            ],
            'singlegroup' => [
                'enrolementarray' => [
                    'group' => [
                        '0' => [
                            'groupname' => 'Gruppe 1',
                            'groupdescription' => 'Eine tolle Gruppe!',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 1',
                            'coursename' => 'Kurs 1',
                            'testname' => 'Testname',
                        ],
                    ],
                ],
                'expected' => [
                    'messagetitle' => "Notification about new course / group enrolments",
                    'messagebody' => 'Because of your test results in "Skala 1", you are now enrolled in group "Gruppe 1" in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>.',
                    'messageforfeedback' => 'Because of your test results in "Skala 1", you are now enrolled in group "Gruppe 1" in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>.',
                ],
            ],
            'onlycourses' => [
                'enrolementarray' => [
                    'course' => [
                        '0' => [
                            'coursename' => 'Kurs 1',
                            'coursesummary' => 'Ein toller Einführungskurs!',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 1',
                            'testname' => 'Testname',
                        ],
                        '1' => [
                            'coursename' => 'Kurs 2',
                            'coursesummary' => 'Ein toller Einführungskurs!',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 2',
                            'testname' => 'Testname',
                            ],
                        '2' => [
                            'coursename' => 'Kurs 3',
                            'coursesummary' => 'Ein toller Einführungskurs!',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 2',
                            'testname' => 'Testname',
                            ],
                        ],
                    ],
                'expected' => [
                    'messagetitle' => "Notification about new course / group enrolments",
                    'messagebody' => "Based on your results in test Testname in course Kurs 1 you are now...<br><br>subscribed in the following course(s):<br><br><ul><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>
                    </li><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a>
                    </li><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a>
                    </li></ul><br>Good luck with your studies!",
                    'messageforfeedback' => "Based on your results you are now...<br><br>subscribed in the following course(s):<br><br><ul><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>
                    </li><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a>
                    </li><li> <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a>
                    </li></ul><br>Good luck with your studies!",
                ],
            ],
            'onlygroups' => [
                'enrolementarray' => [
                    'group' => [
                        '0' => [
                            'groupname' => 'Gruppe 1',
                            'groupdescription' => 'Eine tolle Gruppe!',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 1',
                            'coursename' => 'Kurs 1',
                            'testname' => 'Testname',
                        ],
                        '1' => [
                            'groupname' => 'Gruppe 2',
                            'groupdescription' => 'Eine tolle Gruppe!',
                            'coursename' => 'Kurs 2',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 2',
                            'testname' => 'Testname',
                            ],
                        '2' => [
                            'groupname' => 'Gruppe 3',
                            'groupdescription' => 'Eine tolle Gruppe!',
                            'coursename' => 'Kurs 3',
                            'courseurl' => 'http://10.111.0.2:8000/course/view.php?id=17',
                            'catscalename' => 'Skala 2',
                            'testname' => 'Testname',
                            ],
                    ],
                ],
                'expected' => [
                    'messagetitle' => "Notification about new course / group enrolments",
                    'messagebody' => "Based on your results in test Testname in course Kurs 1 you are now...<br><br>member of the following group(s):<br><br><ul><li>Gruppe 1 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a></li><li>Gruppe 2 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></li><li>Gruppe 3 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a></li></ul>Good luck with your studies!",
                    'messageforfeedback' => "Based on your results you are now...<br><br>member of the following group(s):<br><br><ul><li>Gruppe 1 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a></li><li>Gruppe 2 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></li><li>Gruppe 3 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a></li></ul>Good luck with your studies!",
                ],
            ],
        ];
    }
}
