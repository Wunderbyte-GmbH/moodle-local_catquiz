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
use coding_exception;
use dml_exception;
use local_catquiz\catquiz;
use local_catquiz\data\catscale_structure;
use local_catquiz\data\dataapi;
use local_catquiz\external\manage_catscale;
use moodle_exception;
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
final class catquiz_test extends advanced_testcase {

    /**
     * Tests the return value supposed to be a human readable information about course & group enrolment.
     *
     * @dataProvider create_strings_for_enrolement_notification_provider
     *
     * @param array $enrolementarray
     * @param array $expected
     * @return void
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     *
     */
    public function test_create_strings_for_enrolement_notification(array $enrolementarray, array $expected): void {

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
                    ".
                    '</div><br>member of the following group(s):<br>'.
                    '<div> - “Gruppe 1” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 1</a>”</div>'.
                    '<div> - “Gruppe 2” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 2</a>”</div>'.
                    '<div> - “Gruppe 3” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 2</a>”</div>'.
                    'Good luck with your studies!',
                    'messageforfeedback' => "Based on your results you are now...<br><br>"
                    . "subscribed in the following course(s):<br>"
                    . "<div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 1</a>
                    </div><div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 2</a>
                    </div><div> - <a href=http://10.111.0.2:8000/course/view.php?id=17>Kurs 3</a>
                    ".
                    '</div><br>member of the following group(s):<br>'.
                    '<div> - “Gruppe 1” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 1</a>”</div>'.
                    '<div> - “Gruppe 2” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 2</a>”</div>'.
                    '<div> - “Gruppe 3” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 2</a>”</div>'.
                    'Good luck with your studies!',
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
                    'messagebody' => 'Because of your test results in “Skala 1”, '.
                    'you are now enrolled in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 1</a>”.',
                    'messageforfeedback' => 'Because of your test results in “Skala 1”, '.
                    'you are now enrolled in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 1</a>”.',
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
                    'messagebody' => 'Because of your test results in “Skala 1”, '.
                    'you are now enrolled in group “Gruppe 1” '.
                    'in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 1</a>”.',
                    'messageforfeedback' => 'Because of your test results in “Skala 1”, '.
                    'you are now enrolled in group “Gruppe 1” '.
                    'in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 1</a>”.',
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
                    'messagebody' => 'Based on your results in test Testname in course Kurs 1 you are now...<br><br>'.
                    'member of the following group(s):<br>'.
                    '<div> - “Gruppe 1” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 1</a>”</div>'.
                    '<div> - “Gruppe 2” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 2</a>”</div>'.
                    '<div> - “Gruppe 3” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 3</a>”</div>'.
                    'Good luck with your studies!',
                    'messageforfeedback' => 'Based on your results you are now...<br><br>'.
                    'member of the following group(s):<br>'.
                    '<div> - “Gruppe 1” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 1</a>”</div>'.
                    '<div> - “Gruppe 2” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 2</a>”</div>'.
                    '<div> - “Gruppe 3” in course “<a href="http://10.111.0.2:8000/course/view.php?id=17">Kurs 3</a>”</div>'.
                    'Good luck with your studies!',
                ],
            ],
        ];
    }

    /**
     * Checks that a user is enrolled in a course and added to a group according to person ability values
     *
     * @param array $quizsettings
     * @param array $personabilities
     * @param int $catscaleid
     * @param bool $isenrolled
     *
     * @dataProvider user_is_enrolled_according_to_ability_and_scale_setting_provider
     */
    public function test_user_is_enrolled_according_to_ability_and_scale_settings(
        array $quizsettings,
        array $personabilities,
        int $catscaleid,
        bool $isenrolled
    ) {
        global $USER;
        // This is necessary so that phpunit does not throw an error if the database is changed.
        $this->resetAfterTest();

        // Create a course, group and catscale.
        self::setUser($this->getDataGenerator()->create_user());
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        self::create_dummy_catscale($catscaleid);

        // Create settings so that the user is really enrolled.
        $quizsettings[sprintf('catquiz_courses_%s_1', $catscaleid)] = [$course->id];
        $quizsettings[sprintf('catquiz_group_%s_1', $catscaleid)] = $group->name;

        // Check pre-conditions: the user is not enrolled to the course, not a member of the specified group and has no messages.
        $this->assertEmpty(groups_get_members($group->id));
        $this->assertEmpty($this->get_user_enrolment($course));
        $this->assertEmpty(message_get_messages($USER->id, 0, -1, MESSAGE_GET_READ_AND_UNREAD));

        $coursestoenrol = [];
        $groupstoenrol = [];
        if ($isenrolled) {
            $coursestoenrol = [$catscaleid => ['show_message' => true, 'range' => 1, 'course_ids' => [$course->id]]];
            $groupstoenrol = [$catscaleid => [$group->id]];
        }
        // This is the function we want to test. After this call, the user should be added to the given group and enrolled to the
        // given course depending on the person ability of the user.
        catquiz::enrol_user($quizsettings, $coursestoenrol, $groupstoenrol);

        // Check post-conditions.
        $groupmembers = groups_get_members($group->id);
        if ($isenrolled) {
            $this->assertNotEmpty($this->get_user_enrolment($course));
            $this->assertArrayHasKey($USER->id, $groupmembers);
            $this->assertNotEmpty(message_get_messages($USER->id, 0, -1, MESSAGE_GET_READ_AND_UNREAD));
        } else {
            $this->assertEmpty($this->get_user_enrolment($course));
            $this->assertEmpty($groupmembers);
            $this->assertEmpty(message_get_messages($USER->id, 0, -1, MESSAGE_GET_READ_AND_UNREAD));
        }
    }

    /**
     * Dataprovider for the corresponding test function.
     * @return array
     */
    public static function user_is_enrolled_according_to_ability_and_scale_setting_provider(): array {
        $catscaleid = 1;
        // Set quiz settings in a way so that only if the ability is in the lowest third range [-5, -1.66], the user is enroled to
        // the newly created course.
        $quizsettings = self::get_default_quizsettings();
        $quizsettings[sprintf('catquiz_courses_%s_2', $catscaleid)] = [];
        $quizsettings[sprintf('catquiz_courses_%s_3', $catscaleid)] = [];
        $quizsettings[sprintf('enrolment_message_checkbox_%s_1', $catscaleid)] = '1';
        $quizsettings[sprintf('feedback_scaleid_limit_lower_%s_1', $catscaleid)] = -5.0;
        $quizsettings[sprintf('feedback_scaleid_limit_upper_%s_1', $catscaleid)] = -1.666;
        $quizsettings[sprintf('feedback_scaleid_limit_lower_%s_2', $catscaleid)] = -1.666;
        $quizsettings[sprintf('feedback_scaleid_limit_upper_%s_2', $catscaleid)] = 1.666;
        $quizsettings[sprintf('feedback_scaleid_limit_lower_%s_3', $catscaleid)] = 1.666;
        $quizsettings[sprintf('feedback_scaleid_limit_upper_%s_3', $catscaleid)] = 5.0;
        $quizsettings[sprintf('catquiz_group_%s_2', $catscaleid)] = "";
        $quizsettings[sprintf('catquiz_group_%s_3', $catscaleid)] = "";

        return [
            'is enrolled' => [
                'quizsettings' => $quizsettings,
                'personabilities' => [$catscaleid => -2],
                'catscaleid' => $catscaleid,
                'is enrolled' => true,
            ],
            'is not enrolled' => [
                'quizsettings' => $quizsettings,
                'personabilities' => [$catscaleid => 0],
                'catscaleid' => $catscaleid,
                'is enrolled' => false,
            ],
        ];
    }

    /**
     * Parse the testsettings from the test fixtures folder
     *
     * @return mixed
     */
    private static function get_default_quizsettings() {
        $json = file_get_contents(__DIR__ . '/fixtures/testenvironment.json');
        return json_decode($json, true);
    }

    /**
     * Creates a new catscale with the given ID and defaul tsettings
     * @param int $id
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private static function create_dummy_catscale(int $id): int {
        $catscalestructure = new catscale_structure([
            'name' => 'Dummy',
            'description' => 'Just for testing',
            'action' => 'create',
            'minscalevalue' => -5.0,
            'maxscalevalue' => 5.0,
            'parentid' => 0,
            'id' => $id,
            'timecreated' => time(),
            'timemodified' => time(),
            ]
        );
        $catscaleid = dataapi::create_catscale($catscalestructure);
        return $catscaleid;
    }

    /**
     * Return the user enrolments for the given course and the current user
     *
     * @param mixed $course
     * @return array
     */
    private function get_user_enrolment($course): array {
        global $DB, $USER;

        $enrolments = $DB->get_records(
            'enrol',
            ['courseid' => $course->id, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED]
        );
        $enrolment = reset($enrolments);
        return $DB->get_records('user_enrolments', ['enrolid' => $enrolment->id, 'userid' => $USER->id]);
    }
}
