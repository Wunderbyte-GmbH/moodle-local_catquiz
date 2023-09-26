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
 * Class personabilities.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use cache;
use local_catquiz\catquiz;
use local_catquiz\teststrategy\feedbackgenerator;

/**
 * Returns rendered person abilities.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class personabilities extends feedbackgenerator {
    protected function get_studentfeedback(array $data): array {
        global $OUTPUT;
        $feedback = $OUTPUT->render_from_template(
            'local_catquiz/feedback/personabilities',
            ['abilities' => $data['feedback_personabilities']]
        );

       return [
            'heading' => $this->get_heading(),
            'content' => $feedback,
        ];
    }

    protected function get_teacherfeedback(array $data): array {
        return [];
    }

    public function get_required_context_keys(): array {
        return [
            'feedback_personabilities',
        ];
    }

    public function get_heading(): string {
        return get_string('personability', 'local_catquiz');
    }

    public function load_data(int $attemptid, array $initialcontext): ?array
    {
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $personabilities = $initialcontext['personabilities'] ?? $cache->get('personabilities') ?: [];
        if ($personabilities === []) {
            return null;
        }

        $catscales = catquiz::get_catscales(array_keys($personabilities));
        $data = [];
        foreach ($personabilities as $catscaleid => $ability) {
            if (abs(floatval($ability)) === abs(floatval(PERSONABILITY_MAX))) {
                if ($ability < 0) {
                    $ability = get_string('allquestionsincorrect', 'local_catquiz');
                } else {
                    $ability = get_string('allquestionscorrect', 'local_catquiz');
                }
            } else {
                $ability = sprintf("%.2f", $ability);
            }
            $data[] = [
                'ability' => $ability,
                'name' => $catscales[$catscaleid]->name
            ];
        }
        return ['feedback_personabilities' => $data];
    }
}