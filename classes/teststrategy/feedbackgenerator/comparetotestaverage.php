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
 * Class comparetotestaverage.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use cache;
use local_catquiz\catquiz;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\preselect_task\firstquestionselector;

/**
 * Compare the ability of this attempt to the average abilities of other
 * students that took this test.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comparetotestaverage extends feedbackgenerator {
    protected function get_studentfeedback(array $data): array {
        global $OUTPUT;
        $feedback = $OUTPUT->render_from_template('local_catquiz/feedback/comparetotestaverage', $data);

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
            'contextid',
            'personabilities',
            'quizsettings',
            'needsimprovementthreshold',
            'testaverageability',
            'userability',
            // Used for positioning in the progress bar. 0 is left, 50 middle and 100 right.
            // This assumes that all values are in the range [-5, 5].
            'testaverageposition',
            'userabilityposition',
            'text',
        ];
    }

    public function get_heading(): string {
        return get_string('personability', 'local_catquiz');
    }

    public function load_data(int $attemptid, array $initialcontext): ?array {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        if (! $quizsettings = $cache->get('quizsettings')) {
            return null;
        }

        if (! $catscaleid = $quizsettings->catquiz_catcatscales) {
            return null;
        }

        if (! $personabilities = $cache->get('personabilities')) {
            return null;
        }

        if (! $personabilities) {
            return null;
        }

        $ability = $personabilities[$catscaleid];
        if (! $ability) {
            return null;
        }

        $personparams = catquiz::get_person_abilities(
            $initialcontext['contextid'],
            array_keys($personabilities)
        );
        $worseabilities = array_filter(
            $personparams,
            fn ($pp) => $pp->ability < $ability
        );

        if (!$worseabilities) {
            return null;
        }

        $quantile = (count($worseabilities)/count($personparams)) * 100;
        $text = get_string('feedbackcomparetoaverage', 'local_catquiz', sprintf('%.2f', $quantile));
        if ($needsimprovementthreshold = $initialcontext['needsimprovementthreshold']) {
            if ($quantile < $needsimprovementthreshold) {
                $text .= " " . get_string('feedbackneedsimprovement', 'local_catquiz');
            }
        }

        $testaverage = (new firstquestionselector())->get_average_ability_of_test($personparams);

        return [
            'contextid' => $initialcontext['contextid'],
            'personabilities' => $personabilities,
            'quizsettings' => $quizsettings,
            'needsimprovementthreshold' => $needsimprovementthreshold,
            'testaverageability' => sprintf('%.2f', $testaverage),
            'userability' => sprintf('%.2f', $ability),
            'testaverageposition' => ($testaverage + 5) * 10,
            'userabilityposition' => ($ability + 5) * 10,
            'text' => $text
        ];
    }
}