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
 * Class addscalestandarderror_testing.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

/**
 * Overwrites some functions to facilitate testing.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class addscalestandarderror_testing extends addscalestandarderror {

    /**
     * Returns array of required context keys.
     *
     * @return array
     */
    public function get_required_context_keys(): array {
        return [
            ...parent::get_required_context_keys(),
            'fake_ancestor_scales',
            'fake_child_scales',
        ];
    }

    /**
     * Returns array with ancestor scales.
     *
     * @param mixed $scaleid
     * @return array
     */
    protected function get_with_ancestor_scales($scaleid): array {
        return [
            $scaleid,
            ...$this->context['fake_ancestor_scales'][$scaleid],
        ];
    }
    /**
     * Returns array with child scales.
     * @param mixed $scaleid
     * @return array
     */
    protected function get_with_child_scales($scaleid): array {
        return [
            $scaleid,
           ...$this->context['fake_child_scales'][$scaleid],
        ];
    }
}
