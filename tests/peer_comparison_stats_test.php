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
 * Issue #15: context-true, statistically sound peer comparison.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\local\model\model_person_param;

/**
 * Peer comparison reference group and midrank percentile (issue #15).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\catquiz::get_peer_comparison_stats
 */
final class peer_comparison_stats_test extends advanced_testcase {
    /** @var int Scale id. */
    private $scaleid = 7;
    /** @var int Context id. */
    private $contextid = 90;
    /** @var int A different context (must not leak in). */
    private $othercontext = 91;

    /**
     * Inserts a person parameter row.
     *
     * @param int $userid
     * @param float $ability
     * @param int $contextid
     * @return void
     */
    private function pp(int $userid, float $ability, int $contextid): void {
        global $DB;
        $DB->insert_record('local_catquiz_personparams', (object) [
            'userid' => $userid,
            'catscaleid' => $this->scaleid,
            'contextid' => $contextid,
            'ability' => $ability,
            'status' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Seeds a reference group and returns the compared user's id.
     *
     * Peers in context: u1=0.0, u2=1.0, u3=1.0, u4=2.0. The compared user u10
     * has ability 1.0. u4 has an older, superseded row that must be ignored.
     * Another context and another scale add noise that must not leak in.
     *
     * @return void
     */
    private function seed(): void {
        // Peers.
        $this->pp(1, 0.0, $this->contextid);
        $this->pp(2, 1.0, $this->contextid);
        $this->pp(3, 1.0, $this->contextid);
        // Issue #25: u4 used to carry an older, superseded row (5.0) in addition to
        // the current one (2.0). That state is now structurally impossible - the
        // unique index on (userid, contextid, catscaleid) rejects it, the upgrade
        // removes pre-existing duplicates, and both write paths
        // (model_person_param_list::save_to_db and catquiz::update_personparam)
        // look up by exactly this triple and update instead of inserting.
        // The expected results are unchanged, because only the newer row ever
        // counted; the one-per-person guarantee is now enforced by the schema
        // rather than by the query.
        $this->pp(4, 2.0, $this->contextid);
        // The compared user is also in the table but must be excluded.
        $this->pp(10, 1.0, $this->contextid);
        // An invalid (diverged) result clamped to the saturation bound must not
        // count as a peer (issue #10 / #15).
        $this->pp(7, model_person_param::MODEL_POS_INF, $this->contextid);
        $this->pp(8, -model_person_param::MODEL_POS_INF, $this->contextid);
        // Cross-context noise (same scale, different context).
        $this->pp(5, 1.0, $this->othercontext);
        $this->pp(6, -3.0, $this->othercontext);
    }

    /**
     * The reference group is context-true, one-per-person and excludes the user.
     *
     * @return void
     */
    public function test_reference_group_scoping(): void {
        $this->resetAfterTest();
        $this->seed();
        $stats = catquiz::get_peer_comparison_stats($this->contextid, $this->scaleid, 1.0, 10);
        // Distinct peers: u1, u2, u3, u4 -> 4 (u10 excluded,
        // other-context users ignored).
        $this->assertSame(4, $stats->n);
        // Mean of {0.0, 1.0, 1.0, 2.0} = 1.0.
        $this->assertEqualsWithDelta(1.0, $stats->meanvalue, 0.0001);
    }

    /**
     * The midrank percentile splits ties evenly.
     *
     * @return void
     */
    public function test_midrank_percentile(): void {
        $this->resetAfterTest();
        $this->seed();
        $score = 1.0;
        $stats = catquiz::get_peer_comparison_stats($this->contextid, $this->scaleid, $score, 10);
        // Peers {0.0, 1.0, 1.0, 2.0}: lower = 1 (the 0.0), equal = 2 (both 1.0).
        $this->assertSame(1, $stats->lowercount);
        $this->assertSame(2, $stats->equalcount);
        // Midrank percentile = 100 * (1 + 0.5 * 2) / 4 = 50.
        $percentile = (($stats->lowercount + 0.5 * $stats->equalcount) / $stats->n) * 100;
        $this->assertEqualsWithDelta(50.0, $percentile, 0.0001);
    }

    /**
     * An empty reference group yields zero counts.
     *
     * @return void
     */
    public function test_empty_reference_group(): void {
        $this->resetAfterTest();
        // Only the compared user exists -> no peers.
        $this->pp(10, 1.0, $this->contextid);
        $stats = catquiz::get_peer_comparison_stats($this->contextid, $this->scaleid, 1.0, 10);
        $this->assertSame(0, $stats->n);
        $this->assertSame(0, $stats->lowercount);
        $this->assertSame(0, $stats->equalcount);
    }

    /**
     * Diverged (saturated) results do not count as valid peers.
     *
     * @return void
     */
    public function test_saturated_results_excluded(): void {
        $this->resetAfterTest();
        $this->seed();
        // Peers with |ability| == MODEL_POS_INF (users 7, 8) must be ignored.
        $stats = catquiz::get_peer_comparison_stats($this->contextid, $this->scaleid, 1.0, 10);
        $this->assertSame(4, $stats->n, 'Saturated results must not be counted as peers.');
    }

    /**
     * The minimum-peers threshold is configurable (issue #15).
     *
     * @return void
     */
    public function test_min_peers_configurable(): void {
        $this->resetAfterTest();
        // Default falls back to MIN_USERS (3).
        set_config('minpeersforcomparison', '', 'local_catquiz');
        $this->assertSame(
            \local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage::MIN_USERS,
            \local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage::get_min_peers()
        );
        // A configured value overrides the default.
        set_config('minpeersforcomparison', 7, 'local_catquiz');
        $this->assertSame(
            7,
            \local_catquiz\teststrategy\feedbackgenerator\comparetotestaverage::get_min_peers()
        );
    }
}
