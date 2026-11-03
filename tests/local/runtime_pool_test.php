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

namespace local_catquiz\local;

use advanced_testcase;
use cache;
use cache_helper;
use local_catquiz\catquiz_handler;
use local_catquiz\catscale;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->dirroot . '/local/catquiz/tests/fixtures/concurrent_processes.php');

/**
 * The runtime item pool apart from the state of the attempts (issue #146).
 *
 * The pool was kept, hydrated, in the session cache of the attempts, written back whole for every
 * question, and every attempt start purged that cache - with the progress and the feedback of any other
 * attempt open in the session. Now the pool is in an application cache of its own, the same for
 * everybody; what depends on the person is added from a small table in the session; the state of an
 * attempt has keys of its own.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\attempt_cache
 * @covers \local_catquiz\catscale::get_testitems
 * @covers \local_catquiz\catscale::update_testitem
 * @covers \local_catquiz\catscale::pool_key
 * @covers \local_catquiz\catscale::user_question_stats
 * @covers \local_catquiz\catquiz_handler::prepare_attempt_caches
 * @covers \local_catquiz_observer::question_bank_changed
 */
final class runtime_pool_test extends advanced_testcase {
    use \local_catquiz_concurrent_processes;

    /** @var int A CAT context that covers now. */
    private int $contextid;

    /** @var int The scale of the pool. */
    private int $scaleid;

    /** @var \stdClass[] The questions of the pool. */
    private array $questions = [];

    /**
     * A scale with three questions in a CAT context.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Pool context', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 1000, 'endtimestamp' => time() + 10000,
            'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
        ]);
        $this->scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'Pool scale', 'contextid' => $this->contextid,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        for ($i = 0; $i < 3; $i++) {
            $this->questions[] = $this->item();
        }
    }

    /**
     * A true/false question in the scale.
     *
     * @return \stdClass
     */
    private function item(): \stdClass {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $question = $generator->create_question('truefalse', null, ['category' => $generator->create_question_category()->id]);
        $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => $question->id, 'componentname' => 'question', 'catscaleid' => $this->scaleid,
            'contextid' => $this->contextid, 'status' => LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE,
        ]);
        return $question;
    }

    /**
     * The pool as an attempt gets it.
     *
     * @return array
     */
    private function pool(): array {
        return (new catscale($this->scaleid))->get_testitems($this->contextid, false);
    }

    /**
     * The current user is shown a question - step 0 of a question attempt, as the activity writes it.
     *
     * @param \stdClass $question
     */
    private function show(\stdClass $question): void {
        $quba = question_engine::make_questions_usage_by_activity('local_catquiz', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(\question_bank::load_question($question->id));
        $quba->start_question($slot);
        question_engine::save_questions_usage_by_activity($quba);
    }

    /**
     * The pool is the same for everybody: the statistics of one person are not in it for another, and
     * it is read from the database once.
     */
    public function test_the_pool_is_shared_without_the_statistics_of_a_person(): void {
        global $DB;
        $viewer = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);
        $this->show($this->questions[0]);

        $pool = $this->pool();
        $this->assertCount(3, $pool);
        $this->assertSame(1, $pool[$this->questions[0]->id]->userattempts);
        $this->assertGreaterThan(0, $pool[$this->questions[0]->id]->userlastattempttime);
        $this->assertNull($pool[$this->questions[1]->id]->userlastattempttime);

        $cached = cache::make('local_catquiz', 'runtimepool')->get(catscale::pool_key($this->contextid, false, $this->scaleid, []));
        $this->assertNotFalse($cached, 'The pool is in its own cache.');
        foreach ($cached as $row) {
            $this->assertNull($row->userlastattempttime, 'Nothing of a person in the shared pool.');
        }

        // A warm call of the same person, for comparison: the pool and the statistics are cached.
        $queries = $DB->perf_get_reads();
        $this->pool();
        $warm = $DB->perf_get_reads() - $queries;

        // The reads of the statistics of the other person alone (a recordset: several reads on some databases).
        $queries = $DB->perf_get_reads();
        \local_catquiz\catquiz::get_user_question_stats((int) $other->id);
        $statistics = $DB->perf_get_reads() - $queries;

        $this->setUser($other);
        $queries = $DB->perf_get_reads();
        $pool = $this->pool();
        $this->assertNull($pool[$this->questions[0]->id]->userlastattempttime, 'Not what another person saw.');
        $this->assertSame($warm + $statistics, $DB->perf_get_reads() - $queries, 'Only the statistics of the person are read.');
    }

    /**
     * A question handed out changes the statistics of the person, not the pool; the pool is not written
     * again, and nothing of it is in the session.
     */
    public function test_a_question_handed_out_writes_only_the_statistics(): void {
        $this->pool();
        $poolcache = cache::make('local_catquiz', 'runtimepool');
        $key = catscale::pool_key($this->contextid, false, $this->scaleid, []);
        $before = serialize($poolcache->get($key));

        $question = $this->pool()[$this->questions[1]->id];
        $question->userlastattempttime = 1234567;
        $question->lasttimeplayedpenaltyfactor = 0.5;
        catscale::update_testitem($this->contextid, $question, $this->scaleid);

        $this->assertSame($before, serialize($poolcache->get($key)), 'The pool is not written again.');
        $pool = $this->pool();
        $this->assertSame(1234567, $pool[$this->questions[1]->id]->userlastattempttime);
        $this->assertSame(1, $pool[$this->questions[1]->id]->userattempts);
        $this->assertObjectNotHasProperty('lasttimeplayedpenaltyfactor', $pool[$this->questions[1]->id]);

        $session = serialize(attempt_cache::cache()->get(
            sprintf('userquestionstats_%d_0', (int) $GLOBALS['USER']->id)
        ));
        $this->assertLessThan(200, strlen($session), 'A question is a pair of numbers in the session.');
    }

    /**
     * Starting an attempt leaves every other attempt of the session as it is: its progress, its
     * feedback, its values; and the pool.
     */
    public function test_starting_an_attempt_keeps_the_other_attempts(): void {
        global $USER;
        catquiz_handler::prepare_attempt_caches(100);
        $cache = attempt_cache::cache();
        $cache->set('progress_cat_555', 'progress of attempt 100');
        $cache->set('feedbackdata_100', ['numplayed' => 3, 'data' => []]);
        attempt_cache::set('abilitybeforeattempt', 100, 0.75);
        $this->pool();
        $this->show($this->questions[2]);
        $generation = catscale::user_question_stats((int) $USER->id);

        catquiz_handler::prepare_attempt_caches(200);

        $this->assertSame('progress of attempt 100', $cache->get('progress_cat_555'));
        $this->assertSame(['numplayed' => 3, 'data' => []], $cache->get('feedbackdata_100'));
        $this->assertSame(0.75, attempt_cache::get('abilitybeforeattempt', 100));
        $this->assertNotFalse(attempt_cache::get('starttime', 200));
        $this->assertFalse(attempt_cache::cache()->get(attempt_cache::key('abilitybeforeattempt', 200)));
        $this->assertNotFalse(
            cache::make('local_catquiz', 'runtimepool')->get(catscale::pool_key($this->contextid, false, $this->scaleid, [])),
            'The pool stays.'
        );
        // The statistics of the person are read anew: what was shown since is in them.
        $this->assertArrayNotHasKey((int) $this->questions[2]->id, $generation);
        $this->assertArrayHasKey((int) $this->questions[2]->id, catscale::user_question_stats((int) $USER->id));
    }

    /**
     * A session from before the upgrade is cleared at the first attempt start - its pools are not read
     * any more and would stay to its end; later starts clear nothing.
     */
    public function test_the_old_session_layout_is_cleared_once(): void {
        $cache = attempt_cache::cache();
        $cache->set('testitems_1_1_7_0', array_fill(0, 50, 'an old pool'));

        catquiz_handler::prepare_attempt_caches(300);
        $this->assertFalse($cache->get('testitems_1_1_7_0'));

        $cache->set('progress_cat_9', 'running');
        catquiz_handler::prepare_attempt_caches(301);
        $this->assertSame('running', $cache->get('progress_cat_9'));
    }

    /**
     * The values of an attempt: its own; the session-wide ones of the old layout only when it has none.
     */
    public function test_values_of_an_attempt_are_its_own(): void {
        $cache = attempt_cache::cache();
        $cache->set('totalnumberoftestitems', 99);
        attempt_cache::set('totalnumberoftestitems', 1, 10);
        attempt_cache::set('totalnumberoftestitems', 2, 20);

        $this->assertSame(10, attempt_cache::get('totalnumberoftestitems', 1));
        $this->assertSame(20, attempt_cache::get('totalnumberoftestitems', 2));
        $this->assertSame(99, attempt_cache::get('totalnumberoftestitems', 3), 'Running during the upgrade.');
    }

    /**
     * Items, their parameters and the scales invalidate the pool; the key holds context, scale and the
     * selected subscales, in any order.
     */
    public function test_changes_invalidate_the_pool(): void {
        $key = catscale::pool_key($this->contextid, false, $this->scaleid, []);
        $poolcache = cache::make('local_catquiz', 'runtimepool');
        foreach (['changesintestitems', 'changesinitemparams', 'changesincatscales'] as $event) {
            $this->pool();
            $this->assertNotFalse($poolcache->get($key));
            cache_helper::purge_by_event($event);
            $this->assertFalse($poolcache->get($key), $event);
        }

        $this->assertSame(
            catscale::pool_key(1, true, 2, [5, 3]),
            catscale::pool_key(1, true, 2, [3, 5])
        );
        $this->assertNotSame(catscale::pool_key(1, true, 2, [3]), catscale::pool_key(1, true, 2, [5]));
        $this->assertNotSame(catscale::pool_key(1, true, 2, []), catscale::pool_key(2, true, 2, []));
        $this->assertNotSame(catscale::pool_key(1, true, 2, []), catscale::pool_key(1, false, 2, []));

        // A new item, added the way the manager adds it, is in the next pool.
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question(
            'truefalse',
            null,
            ['category' => $this->getDataGenerator()->get_plugin_generator('core_question')->create_question_category()->id]
        );
        $this->pool();
        catscale::add_or_update_testitem_to_scale($this->scaleid, (int) $question->id);
        $this->assertArrayHasKey((int) $question->id, $this->pool());
    }

    /**
     * The question bank changes the pool too (issue #146): a CAT item hidden there is gone from the next pool at
     * once - not after the lifetime of the cache - and so is a new version's effect; a question of the site that
     * is no CAT item leaves the cached pool alone.
     */
    public function test_question_bank_changes_invalidate_the_pool(): void {
        global $DB;
        $key = catscale::pool_key($this->contextid, false, $this->scaleid, []);
        $poolcache = cache::make('local_catquiz', 'runtimepool');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $this->assertArrayHasKey((int) $this->questions[0]->id, $this->pool());
        $DB->set_field(
            'question_versions',
            'status',
            \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN,
            ['questionid' => $this->questions[0]->id]
        );
        \core\event\question_updated::create_from_question_instance($this->questions[0], \context_system::instance())->trigger();
        $this->assertArrayNotHasKey((int) $this->questions[0]->id, $this->pool(), 'Hidden in the question bank.');

        // A new version of an item's question: the event names the new question, the item the old one.
        $this->pool();
        $this->assertNotFalse($poolcache->get($key));
        $newversion = $generator->update_question($this->questions[1]);
        \core\event\question_created::create_from_question_instance($newversion, \context_system::instance())->trigger();
        $this->assertFalse($poolcache->get($key), 'A new version of a CAT item.');

        // Any other question: the pool stays.
        $this->pool();
        $other = $generator->create_question('truefalse', null, ['category' => $generator->create_question_category()->id]);
        \core\event\question_updated::create_from_question_instance($other, \context_system::instance())->trigger();
        $this->assertNotFalse($poolcache->get($key), 'Not a CAT item.');
    }

    /**
     * Persons whose attempts really run at the same time (issue #146): processes of their own start on a cold
     * pool at the same instant, each person is shown a question of their own, starts an attempt and reads the
     * pool. Everybody gets the same pool, sees only their own statistics, and nothing of a person is in the
     * shared pool.
     */
    public function test_concurrent_attempts_of_several_persons(): void {
        $this->require_concurrent_processes();
        // The processes have connections of their own: they see only what is committed, and what they write
        // is not undone by a rollback. So no test transaction - the slow reset afterwards takes it all away.
        $this->preventResetByRollback();
        $users = [];
        foreach ($this->questions as $question) {
            $users[] = $this->getDataGenerator()->create_user();
        }
        $poolcache = cache::make('local_catquiz', 'runtimepool');
        $poolcache->purge();
        $questions = $this->questions;

        $answers = $this->at_the_same_time(count($users), function (int $i) use ($users, $questions) {
            $this->setUser($users[$i]);
            $this->show($questions[$i]);
            catquiz_handler::prepare_attempt_caches(800 + $i);
            $seen = [];
            foreach ($this->pool() as $id => $row) {
                $seen[$id] = (int) $row->userattempts;
            }
            ksort($seen);
            return $seen;
        });

        $ids = array_map(fn($q) => (int) $q->id, $this->questions);
        sort($ids);
        foreach ($answers as $i => $answer) {
            $this->assertArrayNotHasKey('error', $answer, $answer['error'] ?? '');
            $expected = array_fill_keys($ids, 0);
            $expected[(int) $this->questions[$i]->id] = 1;
            $this->assertSame($expected, $answer['ok'], "Person $i sees the same pool, and only their own question as seen.");
        }
        $cached = $poolcache->get(catscale::pool_key($this->contextid, false, $this->scaleid, []));
        if ($cached !== false) {
            foreach ($cached as $row) {
                $this->assertEmpty($row->userattempts ?? null, 'Nothing of a person in the shared pool.');
                $this->assertEmpty($row->userlastattempttime ?? null, 'Nothing of a person in the shared pool.');
            }
        }
    }

    /**
     * Cold and warm: the same pool.
     */
    public function test_cold_and_warm_pools_are_the_same(): void {
        $this->show($this->questions[1]);
        $warm = $this->pool();
        $this->assertEquals($warm, $this->pool());
        cache::make('local_catquiz', 'runtimepool')->purge();
        attempt_cache::cache()->purge();
        $this->assertEquals($warm, $this->pool());
    }

    /**
     * After every kind of change made the way the plugin makes it, the pool an attempt reads - cached again
     * right after the change - is the pool a cold read gives (issue #146, decision in runtime-pool-cache.md):
     * parameters, the status of an item, the scale, the context of the scale, the question bank.
     */
    public function test_after_each_change_the_cached_pool_is_a_cold_read(): void {
        global $DB;
        foreach ($this->questions as $i => $question) {
            $item = $DB->get_record('local_catquiz_items', ['componentid' => $question->id], '*', MUST_EXIST);
            $paramid = $DB->insert_record('local_catquiz_itemparams', (object) [
                'itemid' => $item->id, 'componentid' => $question->id, 'componentname' => 'question',
                'contextid' => $this->contextid, 'model' => 'raschbirnbaum', 'difficulty' => $i - 1.0,
                'discrimination' => 1.0, 'guessing' => 0, 'json' => '{}', 'status' => LOCAL_CATQUIZ_STATUS_CALCULATED,
                'usable' => 1, 'timecreated' => time(), 'timemodified' => time(),
            ]);
            $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $item->id]);
        }
        cache::make('local_catquiz', 'runtimepool')->purge();
        $cold = function (): array {
            cache::make('local_catquiz', 'runtimepool')->purge();
            attempt_cache::cache()->purge();
            return $this->pool();
        };
        $check = function (string $change, callable $make) use ($cold): void {
            $this->pool();
            $make();
            $cached = $this->pool();
            $this->assertEquals($cold(), $cached, "After: $change.");
            $this->assertEquals($cached, $this->pool(), "Warm again after: $change.");
        };

        $check('a parameter estimated anew', function (): void {
            $list = \local_catquiz\local\model\model_item_param_list::load_from_db(
                $this->contextid,
                'raschbirnbaum',
                [$this->scaleid]
            );
            $param = $list[(int) $this->questions[0]->id];
            $param->set_parameters(['difficulty' => 2.5, 'discrimination' => 1.0]);
            $list->save_to_db($this->contextid, [$this->scaleid]);
        });
        $this->assertEquals(2.5, (float) $this->pool()[(int) $this->questions[0]->id]->difficulty);

        $check('an item switched off', fn() => catscale::add_or_update_testitem_to_scale(
            $this->scaleid,
            (int) $this->questions[1]->id,
            LOCAL_CATQUIZ_TESTITEM_STATUS_INACTIVE
        ));
        $this->assertArrayNotHasKey((int) $this->questions[1]->id, $this->pool());

        $check('the scale changed', fn() => \local_catquiz\data\dataapi::update_catscale((object) [
            'id' => $this->scaleid, 'name' => 'Renamed pool scale', 'parentid' => 0,
        ]));

        $check('the scale moved to a new context', function (): void {
            $new = (int) $GLOBALS['DB']->insert_record('local_catquiz_catcontext', (object) [
                'name' => 'New pool context', 'description' => '', 'descriptionformat' => FORMAT_HTML,
                'starttimestamp' => time() - 1000, 'endtimestamp' => time() + 10000,
                'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
            ]);
            \local_catquiz\data\dataapi::update_catscale((object) ['id' => $this->scaleid, 'parentid' => 0, 'contextid' => $new]);
            $this->contextid = $new;
        });
        $this->assertArrayHasKey((int) $this->questions[0]->id, $this->pool(), 'The items moved with the scale.');

        $check('a question hidden in the question bank', function (): void {
            $GLOBALS['DB']->set_field(
                'question_versions',
                'status',
                \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN,
                ['questionid' => $this->questions[2]->id]
            );
            \core\event\question_updated::create_from_question_instance($this->questions[2], \context_system::instance())
                ->trigger();
        });
        $this->assertArrayNotHasKey((int) $this->questions[2]->id, $this->pool());
    }
}
