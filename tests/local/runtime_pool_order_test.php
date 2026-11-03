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
use local_catquiz\catscale;

/**
 * The order of the runtime pool is the order asked for, whoever filled the cache (issue #147).
 *
 * The key of the shared pool cache holds no order. The selection asks for the pool by difficulty, other readers
 * without an order; whichever came first decided the order for everybody after, until the cache was purged.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\catscale::get_testitems
 * @covers \local_catquiz\catscale::order_pool
 */
final class runtime_pool_order_test extends advanced_testcase {
    /** @var int */
    private int $contextid;

    /** @var int */
    private int $scaleid;

    /** @var int[] Question ids by their place: created in an order that is neither id nor difficulty order. */
    private array $questions = [];

    /**
     * Items with difficulties 1.5, -1, 0.5, -1 (a tie), and a pilot without parameters.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Order', 'description' => '', 'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => time() - 1000, 'endtimestamp' => time() + 10000,
            'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 0,
        ]);
        $this->scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'Order', 'contextid' => $this->contextid,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        foreach ([1.5, -1.0, 0.5, -1.0, null] as $difficulty) {
            $question = $generator->create_question('truefalse', null, ['category' => $category->id]);
            $itemid = (int) $DB->insert_record('local_catquiz_items', (object) [
                'componentid' => $question->id, 'componentname' => 'question', 'catscaleid' => $this->scaleid,
                'contextid' => $this->contextid, 'status' => LOCAL_CATQUIZ_TESTITEM_STATUS_ACTIVE,
            ]);
            if ($difficulty !== null) {
                $paramid = $DB->insert_record('local_catquiz_itemparams', (object) [
                    'itemid' => $itemid, 'componentid' => $question->id, 'componentname' => 'question',
                    'contextid' => $this->contextid, 'model' => 'raschbirnbaum', 'difficulty' => $difficulty,
                    'discrimination' => 1.0, 'guessing' => 0, 'json' => '{}', 'status' => LOCAL_CATQUIZ_STATUS_CALCULATED,
                    'usable' => 1, 'timecreated' => time(), 'timemodified' => time(),
                ]);
                $DB->set_field('local_catquiz_items', 'activeparamid', $paramid, ['id' => $itemid]);
            }
            $this->questions[] = (int) $question->id;
        }
    }

    /**
     * The pool as an attempt gets it.
     *
     * @param string|null $orderby
     * @return int[] Question ids in order.
     */
    private function pool(?string $orderby): array {
        return array_map('intval', array_keys((new catscale($this->scaleid))->get_testitems($this->contextid, false, $orderby)));
    }

    /**
     * Both orders, each read cold and warm, the cache filled first by the one and then by the other.
     */
    public function test_the_order_does_not_depend_on_who_filled_the_cache(): void {
        [$q15, $qm1a, $q05, $qm1b, $pilot] = $this->questions;
        // By difficulty; the tie -1/-1 by question id; the pilot without difficulty last.
        $bydifficulty = [$qm1a, $qm1b, $q05, $q15, $pilot];
        $byid = $this->questions;
        sort($byid);
        $poolcache = cache::make('local_catquiz', 'runtimepool');

        foreach ([[null, 'difficulty'], ['difficulty', null]] as [$first, $second]) {
            $poolcache->purge();
            $this->assertSame($first === null ? $byid : $bydifficulty, $this->pool($first), 'Cold, ' . ($first ?? 'no order'));
            $this->assertSame($second === null ? $byid : $bydifficulty, $this->pool($second), 'Warm, ' . ($second ?? 'no order'));
            $this->assertSame($first === null ? $byid : $bydifficulty, $this->pool($first), 'Warm again.');
        }
    }

    /**
     * Two persons share the pool: the second gets the order she asks for, not that of the first.
     */
    public function test_two_sessions_share_the_pool_not_the_order(): void {
        cache::make('local_catquiz', 'runtimepool')->purge();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->pool(null);
        $this->setUser($this->getDataGenerator()->create_user());
        $ordered = $this->pool('difficulty');
        $difficulties = [];
        foreach ((new catscale($this->scaleid))->get_testitems($this->contextid, false, 'difficulty') as $item) {
            $difficulties[] = $item->difficulty === null ? null : (float) $item->difficulty;
        }
        $this->assertSame([-1.0, -1.0, 0.5, 1.5, null], $difficulties);
        $this->assertCount(5, $ordered);
    }
}
