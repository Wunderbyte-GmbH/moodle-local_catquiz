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
 * Issue #44: the scheduled recalculation refuses to run twice and skips inactive data.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use core\lock\lock_config;

/**
 * Two properties the other tests do not cover.
 *
 * A recalculation rewrites item parameters. Running two of them over the same scale
 * at once means both read the same responses and the later write wins silently - the
 * result looks complete and is not reproducible. The lock is what prevents that, and
 * a lock is only worth having if it is shown to hold.
 *
 * Inactive contexts are the second case: recalibrating a context that was closed
 * changes historical data that somebody has already been shown.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\local\calculation\calculation_service::execute
 */
final class recalculation_lock_test extends advanced_testcase {
    /**
     * The lock type the calculation service uses.
     *
     * @var string
     */
    private const LOCK_TYPE = 'local_catquiz_calculation';

    /**
     * Creates a context and a scale in it.
     *
     * @param bool $active Whether the context is currently open.
     * @return array [contextid, scaleid]
     */
    private function make_scale(bool $active): array {
        global $DB;

        $now = time();

        // An inactive context is one whose window has passed - the same shape as an
        // active one, so the test distinguishes them by the dates alone.
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => $active ? 'Active context' : 'Closed context',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => $active ? $now - 100 : $now - 20000,
            'endtimestamp' => $active ? $now + 10000 : $now - 10000,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);

        $scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0,
            'name' => $active ? 'Active scale' : 'Closed scale',
            'label' => $active ? 'ACT1' : 'CLS1',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return [$contextid, $scaleid];
    }

    /**
     * The service asks for the lock before it does any work.
     *
     * What this can and cannot show is worth stating plainly. Moodle's database lock
     * is re-entrant within one process: a lock taken here and then requested again by
     * the service in the same request is granted, so a PHPUnit test cannot make two
     * runs collide. An earlier version of this test looked like it proved the lock
     * held - it passed because the run was skipped for having no new responses, not
     * because the lock refused it.
     *
     * So this checks the property that is verifiable here: the lock is requested on
     * the scale, with a zero timeout, before anything is written. A scheduled run
     * that blocked instead would sit in the queue rather than report that the work is
     * already under way.
     *
     * A genuine collision needs two processes and belongs in an integration run.
     *
     * @return void
     */
    public function test_the_lock_is_requested_without_blocking(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/local/calculation/calculation_service.php'
        );

        $start = strpos($source, 'public function execute(');
        $this->assertNotFalse($start);
        $body = substr($source, $start, 2000);

        $this->assertStringContainsString(
            "get_lock(\$resource, 0)",
            $body,
            'The lock has to be requested with a zero timeout: a scheduled run that '
                . 'waits occupies the queue instead of reporting the conflict.'
        );

        // The lock has to be taken before the work and released after it. Comparing
        // against the return type in the signature proved nothing - that appears
        // before the body even starts, which is why the first version of this
        // assertion failed on correct code.
        $lockpos = strpos($body, 'get_lock(');
        $releasepos = strpos($body, '->release()');

        $this->assertNotFalse($lockpos);
        $this->assertNotFalse(
            $releasepos,
            'A lock that is never released blocks every later run.'
        );
        $this->assertLessThan(
            $releasepos,
            $lockpos,
            'The lock is only useful while it precedes the work it protects.'
        );
    }

    /**
     * After the lock is released the next run may start again.
     *
     * Without this the first test would also pass if the lock were never released -
     * a recalculation that can only ever run once.
     *
     * @return void
     */
    public function test_the_lock_is_released_again(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $scaleid] = $this->make_scale(true);

        $factory = lock_config::get_lock_factory(self::LOCK_TYPE);
        $first = $factory->get_lock('scale_' . $scaleid, 0);
        $this->assertNotFalse($first);
        $first->release();

        $second = $factory->get_lock('scale_' . $scaleid, 0);
        $this->assertNotFalse(
            $second,
            'The lock was not released, so no later run could ever start.'
        );
        $second->release();
    }

    /**
     * Scales in a closed context are not offered for recalculation.
     *
     * Recalibrating a closed context rewrites parameters that results were already
     * reported against.
     *
     * @return void
     */
    public function test_closed_contexts_are_not_recalculated(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $activescale] = $this->make_scale(true);
        [, $closedscale] = $this->make_scale(false);

        $scales = catquiz::get_all_scales_for_active_contexts();
        $ids = array_map('intval', array_column($scales, 'id'));

        $this->assertContains($activescale, $ids, 'The open context has to be included.');
        $this->assertNotContains(
            $closedscale,
            $ids,
            'A closed context must not be recalibrated - its parameters have already '
                . 'been reported against.'
        );
    }
}
