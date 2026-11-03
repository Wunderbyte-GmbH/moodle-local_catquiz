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

namespace local_catquiz\privacy;

use context_module;
use context_system;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use stdClass;

/**
 * Privacy provider of local_catquiz (issue #17): metadata, contexts, export and deletion.
 *
 * The data of an attempt lives in the context of its activity - also while it runs and has no course
 * yet; data outside any activity in the user context; who changed something in the system context.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var stdClass */
    private stdClass $course;

    /**
     * A course.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * An activity of the course.
     *
     * @return array [adaptivequiz, context_module]
     */
    private function activity(): array {
        $adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $this->course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $cm = get_coursemodule_from_instance('adaptivequiz', $adaptivequiz->id);
        return [$adaptivequiz, context_module::instance($cm->id)];
    }

    /**
     * An attempt of a user with everything CATquiz stores for it. Like a running attempt: no course.
     *
     * @param stdClass $adaptivequiz
     * @param stdClass $user
     * @return int The CAT attempt id.
     */
    private function attempt(stdClass $adaptivequiz, stdClass $user): int {
        global $DB;
        $now = time();
        $hostid = $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => $adaptivequiz->id, 'userid' => $user->id, 'uniqueid' => 0, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => 2, 'difficultysum' => 0, 'standarderror' => 0,
            'measure' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $catid = $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $user->id, 'attemptid' => $hostid, 'component' => 'mod_adaptivequiz', 'teststrategy' => 4,
            'status' => 2, 'json' => '{"ability":0.5}', 'starttime' => $now, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_catquiz_personparams', (object) [
            'userid' => $user->id, 'attemptid' => $catid, 'catscaleid' => 1, 'contextid' => 1, 'ability' => 0.5,
            'standarderror' => 0.3, 'isprimary' => 1, 'isvalid' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_catquiz_progress', (object) [
            'userid' => $user->id, 'attemptid' => $catid, 'component' => 'mod_adaptivequiz', 'json' => '{"answers":[1]}',
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_catquiz_trace', (object) [
            'adaptiveattemptid' => $hostid, 'userid' => $user->id, 'kind' => 'first_item', 'timecreated' => $now,
        ]);
        return $catid;
    }

    /**
     * Data of a user outside any activity, and records the user changed.
     *
     * @param stdClass $user
     */
    private function user_and_actor_data(stdClass $user): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_catquiz_personparams', (object) [
            'userid' => $user->id, 'attemptid' => null, 'catscaleid' => 1, 'contextid' => 1, 'ability' => 1.5,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_catquiz_subscriptions', (object) [
            'userid' => $user->id, 'itemid' => 1, 'area' => 'catscale', 'status' => 1, 'usermodified' => $user->id,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_catquiz_sync_event', (object) [
            'userid' => $user->id, 'contextid' => 1, 'catscaleid' => 1, 'num_fetched_params' => 3,
        ]);
    }

    /**
     * The rows of an attempt that still exist.
     *
     * @param int $catid
     * @return int
     */
    private function rows_of(int $catid): int {
        global $DB;
        return $DB->count_records('local_catquiz_attempts', ['id' => $catid])
            + $DB->count_records('local_catquiz_personparams', ['attemptid' => $catid])
            + $DB->count_records('local_catquiz_progress', ['attemptid' => $catid]);
    }

    /**
     * Every user field of install.xml is declared, and every declared string exists in English and German.
     */
    public function test_metadata(): void {
        global $CFG;
        $collection = provider::get_metadata(new collection('local_catquiz'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
            $this->assertTrue(get_string_manager()->string_exists($item->get_summary(), 'local_catquiz'), $item->get_summary());
            foreach ($item->get_privacy_fields() as $string) {
                $this->assertTrue(get_string_manager()->string_exists($string, 'local_catquiz'), $string);
            }
        }
        $xml = simplexml_load_file($CFG->dirroot . '/local/catquiz/db/install.xml');
        foreach ($xml->TABLES->TABLE as $table) {
            foreach ($table->FIELDS->FIELD as $field) {
                $name = (string) $field['NAME'];
                if (in_array($name, ['userid', 'usermodified'], true)) {
                    $this->assertContains($name, $declared[(string) $table['NAME']] ?? [], $table['NAME'] . '.' . $name);
                }
            }
        }
        $load = function (string $lang) use ($CFG): array {
            $string = [];
            include($CFG->dirroot . '/local/catquiz/lang/' . $lang . '/local_catquiz.php');
            return $string;
        };
        $de = $load('de');
        $en = $load('en');
        foreach (array_keys($en) as $key) {
            if (str_starts_with($key, 'privacy:')) {
                $this->assertArrayHasKey($key, $de, 'German: ' . $key);
            }
        }
    }

    /**
     * A running attempt without a course is found in its activity; data outside an activity in the user
     * context; changed records in the system context.
     */
    public function test_contexts_for_userid(): void {
        [$adaptivequiz, $context] = $this->activity();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->attempt($adaptivequiz, $user);
        $this->user_and_actor_data($user);

        $contextids = provider::get_contexts_for_userid((int) $user->id)->get_contextids();
        sort($contextids);
        $expected = [(int) $context->id, (int) context_user::instance($user->id)->id, (int) context_system::instance()->id];
        sort($expected);
        $this->assertEquals($expected, array_map('intval', $contextids));
        $this->assertEmpty(provider::get_contexts_for_userid((int) $other->id)->get_contextids());
    }

    /**
     * The users of an activity, of a user context and of the system context.
     */
    public function test_users_in_context(): void {
        [$adaptivequiz, $context] = $this->activity();
        [$otherquiz] = $this->activity();
        $user = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $third = $this->getDataGenerator()->create_user();
        $this->attempt($adaptivequiz, $user);
        $this->attempt($adaptivequiz, $user);
        $this->attempt($adaptivequiz, $second);
        $this->attempt($otherquiz, $third);
        $this->user_and_actor_data($third);

        $userlist = new userlist($context, 'local_catquiz');
        provider::get_users_in_context($userlist);
        $ids = $userlist->get_userids();
        sort($ids);
        $this->assertEquals([(int) $user->id, (int) $second->id], array_map('intval', $ids));

        $userlist = new userlist(context_user::instance($third->id), 'local_catquiz');
        provider::get_users_in_context($userlist);
        $this->assertEquals([(int) $third->id], array_map('intval', $userlist->get_userids()));

        $userlist = new userlist(context_system::instance(), 'local_catquiz');
        provider::get_users_in_context($userlist);
        $this->assertEquals([(int) $third->id], array_map('intval', array_values(array_unique($userlist->get_userids()))));
    }

    /**
     * Two attempts of one person in one activity are both exported, each in a folder of its own below the
     * plugin, so the activity's own export is not overwritten.
     */
    public function test_export(): void {
        [$adaptivequiz, $context] = $this->activity();
        $user = $this->getDataGenerator()->create_user();
        $first = $this->attempt($adaptivequiz, $user);
        $second = $this->attempt($adaptivequiz, $user);
        $this->user_and_actor_data($user);

        $this->export_context_data_for_user((int) $user->id, $context, 'local_catquiz');
        $writer = writer::with_context($context);
        $this->assertEmpty($writer->get_data([]), 'Nothing in the root of the activity.');
        $plugin = get_string('pluginname', 'local_catquiz');
        foreach ([$first, $second] as $catid) {
            $data = $writer->get_data([$plugin, get_string('privacy:attempt', 'local_catquiz', $catid)]);
            $this->assertEquals(0.5, $data->scales[0]->ability);
            $this->assertEquals([1], $data->workingstate->answers);
        }
        $this->assertNotEmpty($writer->get_data([$plugin, get_string('privacy:trace', 'local_catquiz')])->requests);

        $usercontext = context_user::instance($user->id);
        $this->export_context_data_for_user((int) $user->id, $usercontext, 'local_catquiz');
        $this->assertEquals(1.5, writer::with_context($usercontext)
            ->get_data([$plugin, get_string('privacy:abilities', 'local_catquiz')])->abilities[0]->ability);
    }

    /**
     * Deleting an activity's context removes its attempts - of every user, not those of another activity
     * nor the data outside it.
     */
    public function test_delete_context(): void {
        global $DB;
        [$adaptivequiz, $context] = $this->activity();
        [$otherquiz] = $this->activity();
        $user = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $mine = $this->attempt($adaptivequiz, $user);
        $theirs = $this->attempt($adaptivequiz, $second);
        $elsewhere = $this->attempt($otherquiz, $user);
        $this->user_and_actor_data($user);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(0, $this->rows_of($mine));
        $this->assertSame(0, $this->rows_of($theirs));
        $this->assertSame(3, $this->rows_of($elsewhere));
        $this->assertSame(1, $DB->count_records('local_catquiz_trace'), 'Only the trace of the other activity is left.');
        $this->assertTrue($DB->record_exists_select('local_catquiz_personparams', 'attemptid IS NULL'));
    }

    /**
     * Deleting a person in approved contexts touches only their data in those contexts; actor fields are
     * anonymised, the records stay.
     */
    public function test_delete_user(): void {
        global $DB;
        [$adaptivequiz, $context] = $this->activity();
        [$otherquiz] = $this->activity();
        $user = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $mine = $this->attempt($adaptivequiz, $user);
        $theirs = $this->attempt($adaptivequiz, $second);
        $elsewhere = $this->attempt($otherquiz, $user);
        $this->user_and_actor_data($user);

        provider::delete_data_for_user(new approved_contextlist($user, 'local_catquiz', [
            $context->id, context_user::instance($user->id)->id, context_system::instance()->id,
        ]));

        $this->assertSame(0, $this->rows_of($mine));
        $this->assertSame(3, $this->rows_of($theirs));
        $this->assertSame(3, $this->rows_of($elsewhere), 'Not approved: the other activity.');
        $this->assertFalse($DB->record_exists_select('local_catquiz_personparams', 'attemptid IS NULL'));
        $this->assertFalse($DB->record_exists('local_catquiz_subscriptions', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('local_catquiz_sync_event', ['userid' => 0]), 'Anonymised, kept.');
    }

    /**
     * Deleting some people in an activity leaves the others.
     */
    public function test_delete_users(): void {
        [$adaptivequiz, $context] = $this->activity();
        $user = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $mine = $this->attempt($adaptivequiz, $user);
        $theirs = $this->attempt($adaptivequiz, $second);

        provider::delete_data_for_users(new approved_userlist($context, 'local_catquiz', [$user->id]));

        $this->assertSame(0, $this->rows_of($mine));
        $this->assertSame(3, $this->rows_of($theirs));
    }

    /**
     * An attempt whose activity is gone belongs to the person and is deleted with the user context.
     */
    public function test_orphaned_attempt(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $catid = $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $user->id, 'attemptid' => 999999, 'component' => 'mod_adaptivequiz', 'instanceid' => 999999,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $usercontext = context_user::instance($user->id);
        $this->assertContains((int) $usercontext->id, array_map('intval', provider::get_contexts_for_userid((int) $user->id)
            ->get_contextids()));

        provider::delete_data_for_all_users_in_context($usercontext);
        $this->assertFalse($DB->record_exists('local_catquiz_attempts', ['id' => $catid]));
    }
}
