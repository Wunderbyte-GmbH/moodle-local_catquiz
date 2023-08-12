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
 * Class pilotquestions_loader.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\context\loader;
use local_catquiz\teststrategy\context\contextloaderinterface;

/**
 * Moves pilot questions to a separate context key `pilot_questions` and removes them from the `questions` key.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pilotquestions_loader implements contextloaderinterface {

    /**
     * Question with less attempts will be considered pilot questions.
     *
     * @var int
     */
    const ATTEMPTS_THRESHOLD = 30;

    /**
     * Returns array ['pilot_questions'].
     *
     * @return array
     *
     */
    public function provides(): array {
        return ['pilot_questions'];
    }

    /**
     * Returns array of requires.
     *
     * @return array
     *
     */
    public function requires(): array {
        return [
            'questions',
            'pilot_ratio',
        ];
    }

    /**
     * Load test items.
     *
     * @param array $context
     *
     * @return array
     *
     */
    public function load(array $context): array {
        foreach ($context['questions'] as $id => $question) {
            $question->is_pilot = intval($question->attempts) < self::ATTEMPTS_THRESHOLD;
            $context['questions'][$id] = $question;
        }
        return $context;
    }
}
