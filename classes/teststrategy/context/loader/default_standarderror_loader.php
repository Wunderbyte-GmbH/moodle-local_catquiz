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
 * Class default_standarderror_loader.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\context\loader;

use local_catquiz\teststrategy\context\contextloaderinterface;

/**
 * Sets the 'se' key to the default standard errors.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class default_standarderror_loader implements contextloaderinterface {

    /**
     * Returns array of items.
     *
     * @return array
     *
     */
    public function provides(): array {
        return ['se'];
    }

    /**
     * Returns requires.
     *
     * @return array
     *
     */
    public function requires(): array {
        return [
            'initial_standarderror',
            'person_ability',
        ];
    }

    /**
     * Set default standard errors.
     *
     * @param array $context
     *
     * @return array
     */
    public function load(array $context): array {
        $default = $context['initial_standarderror'];
        foreach (array_keys($context['person_ability']) as $scaleid) {
            // If this value was already set, do not change it here.
            if (!empty($context['se'][$scaleid])) {
                continue;
            }
            $context['se'][$scaleid] = $default;
        }
        return $context;
    }
}
