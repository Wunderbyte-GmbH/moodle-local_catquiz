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
 * Class initial_scales_loader.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\context\loader;

use local_catquiz\teststrategy\context\contextloaderinterface;
use local_catquiz\teststrategy\progress;

/**
 * Loads the active scales.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class initial_scales_loader implements contextloaderinterface {

    /**
     * Returns the new context elements provided by this class.
     *
     * @return array
     */
    public function provides(): array {
        return ['initial_scales'];
    }

    /**
     * Returns the context elements required by this class.
     *
     * @return array
     */
    public function requires(): array {
        return [
            'attemptid',
            'component',
            'contextid',
            'quizsettings',
            'progress',
        ];
    }

    /**
     * Load the progress object.
     *
     * @param array $context
     * @return array
     */
    public function load(array $context): array {
        $progress = $context['progress'];
        if ($progress->is_first_question()) {
            // By default, set the root scale active.
            $activescales = [$context['catscaleid']];
            // For the inferallsubscales strategy, all scales are activated.
            if ($context['teststrategy'] == LOCAL_CATQUIZ_STRATEGY_ALLSUBS) {
                $activescales = $progress->get_selected_subscales();
            }
            $progress->set_active_scales($activescales);
            $context['progress'] = $progress;
        }

        return $context;
    }
}
