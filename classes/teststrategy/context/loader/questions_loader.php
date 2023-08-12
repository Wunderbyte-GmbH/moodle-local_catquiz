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
 * Class questions_loader.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\context\loader;

use cache;
use local_catquiz\catscale;
use local_catquiz\teststrategy\context\contextloaderinterface;

/**
 * Loads questions for test strategies.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questions_loader implements contextloaderinterface {

    /**
     * Returns array of items.
     *
     * @return array
     *
     */
    public function provides(): array {
        return ['questions'];
    }

    /**
     * Returns requires.
     *
     * @return array
     *
     */
    public function requires(): array {
        return [
            'catscaleid',
            'contextid',
            'includesubscales'
        ];
    }

    /**
     * Load testitems.
     *
     * @param array $context
     *
     * @return array
     *
     */
    public function load(array $context): array {
        $catscale = new catscale($context['catscaleid']);
        $context['questions'] = $catscale->get_testitems(
            $context['contextid'],
            $context['includesubscales'],
            'difficulty'
        );
        $context['questions_ordered_by'] = 'difficulty';

        return $context;
    }

}
