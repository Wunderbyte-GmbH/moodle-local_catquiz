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
 * Regression test: pilot classification and pilot exclusion.
 *
 * Two defects froze the ability estimate in production:
 *  1. an item with difficulty exactly 0.0 was classified as a pilot, because the
 *     guard used floatval($difficulty) as a truthiness test - b = 0 is falsy in
 *     PHP but a perfectly regular IRT parameter;
 *  2. with pilots switched off the preselect task returned early WITHOUT
 *     removing pilot-flagged items, so they stayed in the candidate pool, got
 *     played, and were then skipped by the ability update.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\teststrategy\context\loader\pilotquestions_loader::ispilot
 */

namespace local_catquiz\teststrategy;

use advanced_testcase;
use local_catquiz\teststrategy\context\loader\pilotquestions_loader;
use stdClass;

/**
 * Guards pilot classification against the b = 0 falsiness trap.
 *
 * @package    local_catquiz
 * @covers \local_catquiz\teststrategy\context\loader\pilotquestions_loader::ispilot
 */
final class pilot_classification_test extends advanced_testcase {
    /**
     * Build a question stub.
     *
     * @param mixed $difficulty The difficulty value.
     * @param int $status The calibration status.
     * @param int $attempts The number of attempts.
     * @param string $model The model name the parameters belong to.
     * @return stdClass
     */
    private function question($difficulty, int $status, int $attempts = 0, string $model = 'rasch'): stdClass {
        $q = new stdClass();
        $q->difficulty = $difficulty;
        $q->status = $status;
        $q->attempts = $attempts;
        // The 1PL model only needs a difficulty, so these stubs isolate the
        // difficulty/status logic from the per-model parameter contract.
        $q->model = $model;
        $q->discrimination = 1.0;
        $q->guessing = 0.0;
        return $q;
    }

    /**
     * A calibrated item must not be a pilot, whatever its difficulty is - most
     * importantly for the legal value 0.0.
     *
     * @return void
     */
    public function test_calibrated_item_is_never_pilot_regardless_of_difficulty(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $loader = new pilotquestions_loader();
        $status = \LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY;

        // The regression: b = 0.0 is a regular parameter, not a pilot marker.
        $this->assertFalse($loader->ispilot($this->question(0.0, $status), 30));
        $this->assertFalse($loader->ispilot($this->question('0.0000', $status), 30));
        // Negative and positive difficulties behave the same way.
        $this->assertFalse($loader->ispilot($this->question(-3.13, $status), 30));
        $this->assertFalse($loader->ispilot($this->question(1.52, $status), 30));
    }

    /**
     * An item without any difficulty parameter is still a pilot.
     *
     * @return void
     */
    public function test_uncalibrated_item_remains_pilot(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $loader = new pilotquestions_loader();
        // No parameter at all, and too few attempts to be trusted.
        $this->assertTrue($loader->ispilot($this->question(null, 0, 0), 30));
        $this->assertTrue($loader->ispilot($this->question('', 0, 0), 30));
        // Parameter present but neither manually updated nor enough attempts.
        $this->assertTrue($loader->ispilot($this->question(0.5, 0, 3), 30));
        // Enough attempts makes it productive again.
        $this->assertFalse($loader->ispilot($this->question(0.0, 0, 30), 30));
    }
}
