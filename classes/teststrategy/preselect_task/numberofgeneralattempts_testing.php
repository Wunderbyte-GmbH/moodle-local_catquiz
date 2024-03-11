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
 * Class numberofgeneralattempts_testing.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

/**
 * Overwrites the method to get the attempts to prevent DB access.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class numberofgeneralattempts_testing extends numberofgeneralattempts {
    /**
     * Returns array of required context keys.
     *
     * @return array
     */
    public function get_required_context_keys(): array {
        return parent::get_required_context_keys()[] = ['fake_questionattemptcounts'];
    }
    /**
     * Returns array of questions with attempt scount.
     *
     * @param mixed $context
     *
     * @return array
     */
    protected function getquestionswithattemptscount($context): array {
        return $context['fake_questionattemptcounts'];
    }
}
