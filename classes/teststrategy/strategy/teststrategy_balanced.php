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
 * Class teststrategy_balanced.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\strategy;

use cache;
use local_catquiz\teststrategy\feedbackgenerator\debuginfo;
use local_catquiz\teststrategy\feedbackgenerator\graphicalsummary;
use local_catquiz\teststrategy\feedbackgenerator\personabilities;
use local_catquiz\teststrategy\feedbackgenerator\pilotquestions;
use local_catquiz\teststrategy\feedbackgenerator\questionssummary;
use local_catquiz\teststrategy\preselect_task\lasttimeplayedpenalty;
use local_catquiz\teststrategy\preselect_task\maximumquestionscheck;
use local_catquiz\teststrategy\preselect_task\maybe_return_pilot;
use local_catquiz\teststrategy\preselect_task\mayberemovescale;
use local_catquiz\teststrategy\preselect_task\noremainingquestions;
use local_catquiz\teststrategy\preselect_task\numberofgeneralattempts;
use local_catquiz\teststrategy\preselect_task\strategybalancedscore;
use local_catquiz\teststrategy\preselect_task\updatepersonability;
use local_catquiz\teststrategy\strategy;

/**
 * Will select questions in a way that balances the general number of attempts
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teststrategy_balanced extends strategy {

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = LOCAL_CATQUIZ_STRATEGY_BALANCED;

    /**
     * Return required score modifiers.
     *
     * @return array
     *
     */
    public function get_preselecttasks(): array {
        return [
            maximumquestionscheck::class,
            updatepersonability::class,
            mayberemovescale::class,
            noremainingquestions::class,
            lasttimeplayedpenalty::class,
            numberofgeneralattempts::class,
            maybe_return_pilot::class,
            strategybalancedscore::class,
        ];
    }

    /**
     * Get feedbac kgenerators.
     *
     * @return array
     *
     */
    public function get_feedbackgenerators(): array {
        return [
          new   questionssummary(),
          new   personabilities(),
          new   pilotquestions(),
          new   debuginfo(),
          new   graphicalsummary(),
        ];
    }
}
