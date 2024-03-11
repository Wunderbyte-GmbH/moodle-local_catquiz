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
 * Class filterforsubscaletesting.
 *
 * Overwrites just a small part to facilitate testing.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\catscale;
use local_catquiz\local\result;
use local_catquiz\teststrategy\context\loader\personability_loader;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Overwrites some protected methods to facilitate testing.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filterforsubscaletesting extends filterforsubscale {

    /**
     * Get subscale ids.
     *
     * @param mixed $catscaleid
     * @param mixed $context
     *
     * @return mixed
     *
     */
    protected function getsubscaleids($catscaleid, $context) {
        return $context['fake_subscaleids'][$catscaleid];
    }
}
