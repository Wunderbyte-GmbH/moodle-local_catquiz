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
 * Class pilotquestions.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use cache;
use local_catquiz\teststrategy\feedbackgenerator;

/**
 * Returns feedback for pilotquestions.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pilotquestions extends feedbackgenerator {
    protected function run(array $context): array {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $numpilotquestions = $cache->get('num_pilot_questions');

        if (! $numpilotquestions) {
            return $this->no_data();
        }

        $feedback = sprintf(
            '%s: %d',
            get_string('pilot_questions', 'local_catquiz'),
            $cache->get('num_pilot_questions')
        );

       return [
            'heading' => $this->get_heading(),
            'content' => $feedback,
        ];
    }

    public function get_required_context_keys(): array {
        return [];
    }

    public function get_heading(): string {
        return get_string('pilot_questions', 'local_catquiz');
    }
}