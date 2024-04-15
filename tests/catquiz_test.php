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

use advanced_testcase;
use local_catquiz\catquiz;
use local_catquiz\data\catscale_structure;
use local_catquiz\data\dataapi;
use local_catquiz\external\manage_catscale;
use PHPUnit\Framework\ExpectationFailedException;
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
class catquiz_test extends advanced_testcase {

    /**
     * Tests the return value supposed to be a human readable information about course & group enrolment.
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
     * Data provider for test_create_strings_for_enrolement_notification()
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
                    'messagebody' => "Based on your results in test Testname in course Kurs 1 you are now...<br><br>"
                    . "subscribed in the following course(s):<br><div>"
                    . " - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>
                    </div><div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a>
                    </div><div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a>
                    </div><br>member of the following group(s):<br>"
                    . "<div> - Gruppe 1 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a></div>"
                    . "<div> - Gruppe 2 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></div>"
                    . "<div> - Gruppe 3 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></div>"
                    . "Good luck with your studies!",
                    'messageforfeedback' => "Based on your results you are now...<br><br>"
                    . "subscribed in the following course(s):<br>"
                    . "<div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>
                    </div><div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a>
                    </div><div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a>
                    </div><br>member of the following group(s):<br>"
                    . "<div> - Gruppe 1 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a></div>"
                    . "<div> - Gruppe 2 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></div>"
                    . "<div> - Gruppe 3 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></div>"
                    . "Good luck with your studies!",
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
                    'messagebody' => 'Because of your test results in "Skala 1", '
                    . 'you are now enrolled in course '
                    . '<a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>.',
                    'messageforfeedback' => 'Because of your test results in "Skala 1", '
                    . 'you are now enrolled in course '
                    . '<a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>.',
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
                    'messagebody' => 'Because of your test results in "Skala 1", you are now enrolled in group "Gruppe 1" in course'
                    . ' <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>.',
                    'messageforfeedback' => 'Because of your test results in "Skala 1", '
                    . 'you are now enrolled in group "Gruppe 1" in course '
                    . '<a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>.',
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
                    'messagebody' => "Based on your results in test Testname in course Kurs 1 you are now...<br>"
                    . "<br>subscribed in the following course(s):<br><div>"
                    . " - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>
                    </div><div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a>
                    </div><div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a>
                    </div><br>Good luck with your studies!",
                    'messageforfeedback' => "Based on your results you are now...<br>"
                    . "<br>subscribed in the following course(s):<br>"
                    . "<div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>
                    </div><div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a>
                    </div><div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a>
                    </div><br>Good luck with your studies!",
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
                    'messagebody' => "Based on your results in test Testname in course Kurs 1 you are now...<br>"
                        . "<br>member of the following group(s):<br>"
                        . "<div> - Gruppe 1 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a></div>"
                        . "<div> - Gruppe 2 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></div>"
                        . "<div> - Gruppe 3 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a></div>"
                        . "Good luck with your studies!",
                    'messageforfeedback' => "Based on your results you are now...<br><br>"
                        . "member of the following group(s):<br>"
                        . "<div> - Gruppe 1 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a></div>"
                        . "<div> - Gruppe 2 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a></div>"
                        . "<div> - Gruppe 3 in course <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a></div>"
                        . "Good luck with your studies!",
                ],
            ],
        ];
    }

    public function test_user_enrolment_works_as_expected() {
        $this->resetAfterTest();
        global $DB, $USER;
        self::setUser(1);
        $course = $this->getDataGenerator()->create_course();
        $quizsettings = $this->get_default_quizsettings();
        $personabilities = [];

        // Create catscale.
        $catscalestructure = new catscale_structure([
            'name' => 'Testscale',
            'description' => 'Testscale',
            'action' => 'create',
            'minscalevalue' => -5.0,
            'maxscalevalue' => 5.0,
            'parentid' => 0,
            'id' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            ]
        );
        $catscaleid = dataapi::create_catscale($catscalestructure);

        $quizsettings[sprintf('catquiz_courses_%s_1', $catscaleid)] = [$course->id];
        $quizsettings[sprintf('enrolment_message_checkbox%s_1', $catscaleid)] = '1';
        $quizsettings[sprintf('feedback_scaleid_limit_lower_%s_1', $catscaleid)] = -5;
        $quizsettings[sprintf('feedback_scaleid_limit_upper_%s_1', $catscaleid)] = -1.666;
        $personabilities[$catscaleid] = -2;

        // Ensure user is not yet enrolled - there are no enrolments yet.
        $enrolments = $DB->get_records(
            'enrol',
            ['courseid' => $course->id, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED]
        );
        $this->assertIsArray($enrolments);
        $this->assertEquals(1, count($enrolments));
        $enrolment = reset($enrolments);
        $this->assertIsObject($enrolment);
        $userenrolments = $DB->get_records('user_enrolments', ['enrolid' => $enrolment->id, 'userid' => $USER->id]);
        $this->assertEmpty($userenrolments);

        catquiz::enrol_user($USER->id, $quizsettings, $personabilities);

        // Get the enrolid for the course.
        $enrolments = $DB->get_records(
            'enrol',
            ['courseid' => $course->id, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED]
        );
        $enrolment = reset($enrolments);
        $this->assertIsObject($enrolment);
        $userenrolments = $DB->get_records('user_enrolments', ['enrolid' => $enrolment->id, 'userid' => $USER->id]);
        $this->assertNotEmpty($userenrolments);
    }

    private function get_default_quizsettings() {
        $json = file_get_contents(__DIR__ . '/fixtures/testenvironment.json');
        return json_decode($json, true);
    }
}
