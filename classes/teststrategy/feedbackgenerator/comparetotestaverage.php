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
    protected function run(array $context): array {
        $quizsettings = $context['quizsettings'];
        if (! $catscaleid = $quizsettings->catquiz_catcatscales) {
            return [];
        }

        $abilities = $context['personabilities'];
        if (! $abilities) {
            return [];
        }

        $ability = $abilities[$catscaleid];
        if (! $ability) {
            return [];
        }

        $personparams = catquiz::get_person_abilities(
            $context['contextid'],
            array_keys($abilities)
        );
        $worseabilities = array_filter(
            $personparams,
            fn ($pp) => $pp->ability < $ability
        );

        if (!$worseabilities) {
            return [];
        }

        $quantile = (count($worseabilities)/count($personparams)) * 100;
        $text = get_string('feedbackcomparetoaverage', 'local_catquiz', sprintf('%.2f', $quantile));
        if ($needsimprovementthreshold = $context['needsimprovementthreshold']) {
            if ($quantile < $needsimprovementthreshold) {
                $text .= " " . get_string('feedbackneedsimprovement', 'local_catquiz');
            }
        }

        $testaverage = (new firstquestionselector())->get_average_ability_of_test($personparams);
        $data = [
            'testaverageability' => sprintf('%.2f', $testaverage),
            'userability' => sprintf('%.2f', $ability),
            // Used for positioning in the progress bar. 0 is left, 50 middle and 100 right.
            // This assumes that all values are in the range [-5, 5].
            'testaverageposition' => ($testaverage + 5) * 10,
            'userabilityposition' => ($ability + 5) * 10,
            'text' => $text
        ];

        global $OUTPUT;
        $feedback = $OUTPUT->render_from_template('local_catquiz/feedback/comparetotestaverage', $data);

       return [
            'heading' => $this->get_heading(),
            'content' => $feedback,
        ];
    }

    public function get_required_context_keys(): array {
        return [
            'contextid',
            'personabilities',
            'quizsettings',
            'needsimprovementthreshold',
        ];
    }

    public function get_heading(): string {
        return get_string('personability', 'local_catquiz');
    }
}