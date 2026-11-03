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

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\event\usertocourse_enroled;
use local_catquiz\event\usertogroup_enroled;
use local_catquiz\output\attemptfeedback;
use local_catquiz\teststrategy\progress;

/**
 * Routing after an attempt: which scales route (#129) and how they are processed (#130).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catquiz
 */
final class enrolment_routing_test extends advanced_testcase {
    /**
     * Files a scale and returns its id.
     *
     * @param string $name
     * @return int
     */
    private function scale(string $name): int {
        global $DB;

        return (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => $name, 'description' => '', 'parentid' => 0, 'contextid' => 1, 'minmaxgroup' => '',
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Every routing scale once, with its own name, and a scale that routes into groups only.
     */
    public function test_each_scale_is_enrolled_once_with_its_own_name(): void {
        global $COURSE;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $home = $this->getDataGenerator()->create_course();
        // The person takes the test in this course and is enrolled in it.
        $this->getDataGenerator()->enrol_user($user->id, $home->id, 'student');
        $COURSE = get_course($home->id);
        $first = $this->getDataGenerator()->create_course(['fullname' => 'First course']);
        $second = $this->getDataGenerator()->create_course(['fullname' => 'Second course']);
        $this->getDataGenerator()->create_group(['courseid' => $home->id, 'name' => 'alpha']);
        $scalea = $this->scale('Scale A');
        $scaleb = $this->scale('Scale B');
        $scalec = $this->scale('Scale C');

        $courses = [
            $scalea => ['range' => 1, 'show_message' => true, 'course_ids' => [$first->id]],
            $scaleb => ['range' => 1, 'show_message' => true, 'course_ids' => [$second->id]],
            $scalec => ['range' => 1, 'show_message' => true, 'course_ids' => []],
        ];
        $groups = [$scalec => ['alpha']];

        $this->redirectMessages();
        $sink = $this->redirectEvents();
        $message = catquiz::enrol_user(['name' => 'Test'], $courses, $groups);
        $events = $sink->get_events();
        $sink->clear();

        $courseevents = array_values(array_filter($events, fn($e) => $e instanceof usertocourse_enroled));
        $groupevents = array_values(array_filter($events, fn($e) => $e instanceof usertogroup_enroled));
        $this->assertCount(2, $courseevents, 'Each new course enrolment exactly once.');
        $this->assertCount(1, $groupevents, 'The groups-only scale was not processed.');
        $byname = [];
        foreach ($courseevents as $event) {
            $byname[$event->other['coursename']] = $event->other['catscalename'];
        }
        $this->assertSame(['First course' => 'Scale A', 'Second course' => 'Scale B'], $byname, 'Wrong scale named.');
        $this->assertSame('Scale C', $groupevents[0]->other['catscalename']);

        foreach (['First course', 'Second course', 'alpha'] as $expected) {
            $this->assertStringContainsString($expected, $message, "The message misses $expected.");
        }

        // Again: everything exists already - no event, no message.
        $again = catquiz::enrol_user(['name' => 'Test'], $courses, $groups);
        $this->assertSame([], array_filter(
            $sink->get_events(),
            fn($e) => $e instanceof usertocourse_enroled || $e instanceof usertogroup_enroled
        ));
        $this->assertSame('', $again);
        $sink->close();
    }

    /**
     * Two scales, each with its own new group: both groups joined once, each named after its scale.
     */
    public function test_two_scales_with_a_group_each(): void {
        global $COURSE;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $home = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $home->id, 'student');
        $COURSE = get_course($home->id);
        $this->getDataGenerator()->create_group(['courseid' => $home->id, 'name' => 'alpha']);
        $this->getDataGenerator()->create_group(['courseid' => $home->id, 'name' => 'beta']);
        $scalea = $this->scale('Scale A');
        $scaleb = $this->scale('Scale B');

        $courses = [
            $scalea => ['range' => 1, 'show_message' => true, 'course_ids' => []],
            $scaleb => ['range' => 1, 'show_message' => true, 'course_ids' => []],
        ];
        $groups = [$scalea => ['alpha'], $scaleb => ['beta']];

        $this->redirectMessages();
        $sink = $this->redirectEvents();
        $message = catquiz::enrol_user(['name' => 'Test'], $courses, $groups);
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof usertogroup_enroled));
        $sink->close();

        $byname = [];
        foreach ($events as $event) {
            $byname[$event->other['groupname']] = $event->other['catscalename'];
        }
        ksort($byname);
        $this->assertSame(['alpha' => 'Scale A', 'beta' => 'Scale B'], $byname, 'Each group once, under its own scale.');
        $this->assertStringContainsString('alpha', $message);
        $this->assertStringContainsString('beta', $message);
    }

    /**
     * A strategy without the personabilities generator still routes its valid root (CAT).
     *
     * Not routed: a scale the strategy excluded, and one it selected but that was not measured in
     * this attempt.
     */
    public function test_routing_comes_from_the_final_result(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $root = 100;
        $excluded = 101;
        $carryover = 102;

        // The attempt of the activity behind the progress, as every real attempt has one.
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => 98001, 'instance' => 1, 'userid' => 2, 'uniqueid' => 598001, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => 3, 'difficultysum' => 0, 'standarderror' => 0.3,
            'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $progress = progress::load(98001, 'mod_adaptivequiz', 9, (object) []);
        $played = [];
        $responses = [];
        foreach ([7001, 7002, 7003] as $qid) {
            $played[$qid] = (object) ['id' => $qid, 'catscaleid' => $root, 'is_pilot' => false, 'fisherinformation' => []];
            $responses[$qid] = ['questionid' => $qid, 'fraction' => 0.5];
        }
        $state = ['responses' => $responses, 'playedquestions' => $played,
            // A progress with played questions always has a last one.
            'lastquestion' => end($played) ?: null, 'playedquestionsbyscale' => [$root => $played]];
        foreach ($state as $name => $value) {
            $property = new \ReflectionProperty($progress, $name);
            $property->setAccessible(true);
            $property->setValue($progress, $value);
        }
        $progress->save();

        // What CAT leaves behind: no personabilities_abilities, only the custom scale feedback.
        $DB->set_field('local_catquiz_attempts', 'contextid', 9, ['attemptid' => 98001]);
        $DB->set_field('local_catquiz_attempts', 'json', json_encode([
            'customscalefeedback_abilities' => [
                $root => ['value' => 0.4, 'toreport' => true, 'primary' => true],
                $excluded => ['value' => 1.2, 'excluded' => true],
                $carryover => ['value' => 2.0, 'toreport' => true],
            ],
            'se' => [$root => 0.3, $excluded => 0.3, $carryover => 0.3],
        ]), ['attemptid' => 98001]);

        $this->assertSame([$root => 0.4], attemptfeedback::routing_scores(98001));
    }

    /**
     * Files a finished attempt: its progress (answers per scale) and what the strategy left behind.
     *
     * @param int $attemptid adaptivequiz_attempt.id
     * @param array $fractions Per scale id the fractions of its productive answers.
     * @param array $abilities Per scale id the strategy's entry (value, toreport, primary ...).
     * @return void
     */
    private function finished_attempt(int $attemptid, array $fractions, array $abilities): void {
        global $DB;
        $DB->import_record('adaptivequiz_attempt', (object) [
            'id' => $attemptid, 'instance' => 1, 'userid' => 2, 'uniqueid' => 500000 + $attemptid,
            'attemptstate' => 'complete', 'attemptstopcriteria' => '', 'questionsattempted' => 6, 'difficultysum' => 0,
            'standarderror' => 0.3, 'measure' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $progress = progress::load($attemptid, 'mod_adaptivequiz', 9, (object) []);
        $played = [];
        $byscale = [];
        $responses = [];
        $qid = $attemptid * 100;
        foreach ($fractions as $scaleid => $list) {
            foreach ($list as $fraction) {
                $q = (object) ['id' => ++$qid, 'catscaleid' => $scaleid, 'is_pilot' => false, 'fisherinformation' => []];
                $played[$q->id] = $q;
                $byscale[$scaleid][] = $q;
                $responses[$q->id] = ['questionid' => $q->id, 'fraction' => $fraction];
            }
        }
        $state = ['responses' => $responses, 'playedquestions' => $played, 'lastquestion' => end($played),
            'playedquestionsbyscale' => $byscale];
        foreach ($state as $name => $value) {
            $property = new \ReflectionProperty($progress, $name);
            $property->setAccessible(true);
            $property->setValue($progress, $value);
        }
        $progress->save();
        $DB->set_field('local_catquiz_attempts', 'contextid', 9, ['attemptid' => $attemptid]);
        $DB->set_field('local_catquiz_attempts', 'json', json_encode([
            'personabilities_abilities' => $abilities,
            'se' => array_fill_keys(array_keys($abilities), 0.3),
        ]), ['attemptid' => $attemptid]);
    }

    /**
     * All answers right - or all wrong - routes nowhere: the result is not valid (issues #138, #140).
     */
    public function test_a_fraction_invalid_result_routes_nowhere(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $abilities = [200 => ['value' => 2.5, 'toreport' => true, 'primary' => true]];

        $this->finished_attempt(98101, [200 => [1.0, 1.0, 1.0]], $abilities);
        $this->finished_attempt(98102, [200 => [0.0, 0.0, 0.0]], $abilities);
        $this->finished_attempt(98103, [200 => [1.0, 0.0, 1.0]], $abilities);

        $this->assertSame([], attemptfeedback::routing_scores(98101), 'All right routed.');
        $this->assertSame([], attemptfeedback::routing_scores(98102), 'All wrong routed.');
        $this->assertSame([200 => 2.5], attemptfeedback::routing_scores(98103), 'A mixed result must route.');
    }

    /**
     * Several routing scales: the one with all answers right is left out, the valid ones still route.
     *
     * One invalid scale does not take the others with it - not even when it is the primary one.
     */
    public function test_an_invalid_scale_does_not_take_the_valid_ones_with_it(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $abilities = [
            301 => ['value' => 1.5, 'toreport' => true, 'primary' => true],
            302 => ['value' => -0.5, 'toreport' => true],
            303 => ['value' => 0.7, 'toreport' => true],
        ];

        $this->finished_attempt(98201, [301 => [1.0, 1.0], 302 => [0.0, 1.0, 0.0], 303 => [1.0, 0.0]], $abilities);

        $this->assertSame([302 => -0.5, 303 => 0.7], attemptfeedback::routing_scores(98201));
    }
}
