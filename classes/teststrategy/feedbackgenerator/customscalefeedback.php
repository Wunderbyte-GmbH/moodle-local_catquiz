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
 * Returns a custom feedback for each scale.
 *
 * If the person ability for this attempt is below the threshold as set in the
 * quiz settings, the user will see the message that was defined there.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customscalefeedback extends feedbackgenerator {

    /**
     * Get student feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    protected function get_studentfeedback(array $data): array {

        $text = $data['customscalefeedback'];
        return [
            'heading' => $this->get_heading(),
            'content' => $text,
        ];
    }

    /**
     * Get teacher feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    protected function get_teacherfeedback(array $data): array {
        return [];
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'quizsettings',
            'personabilities',
            'customscalefeedback',
        ];
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return get_string('catquiz_feedbackheader', 'local_catquiz');
    }

    /**
     * Load data.
     *
     * @param int $attemptid
     * @param array $initialcontext
     *
     * @return array|null
     *
     */
    public function load_data(int $attemptid, array $initialcontext): ?array {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $quizsettings = ($initialcontext['quizsettings'] ?? $cache->get('quizsettings')) ?: null;
        if ($quizsettings === null) {
            return null;
        }

        $personabilities = $initialcontext['personabilities'] ?? $cache->get('personabilities') ?: null;
        if ($personabilities === null) {
            return null;
        }

        // Todo: make the sorting dependent on the strategy!
        asort($personabilities);

        $scalefeedback = [];
        foreach ($personabilities as $catscaleid => $personability) {
            for ($j = 1; $j <= $quizsettings->numberoffeedbackoptionsselect; $j++) {
                $lowerlimitprop = sprintf('feedback_scaleid_limit_lower_%d_%d', $catscaleid, $j);
                $lowerlimit = floatval($quizsettings->$lowerlimitprop);
                $upperlimitprop = sprintf('feedback_scaleid_limit_uppser_%d_%d', $catscaleid, $j);
                $upperlimit = floatval($quizsettings->$lowerlimitprop);
                if ($personability < $lowerlimit || $personability > $upperlimit) {
                    continue;
                }

                $feedback = $this->getfeedbackforrange($catscaleid, $j);
                // Do not display empty feedback messages.
                if (!$feedback) {
                    continue;
                }

                $scalefeedback[$catscaleid] = $feedback;
            }
        }

        if (! $scalefeedback) {
            return null;
        }

        $catscales = catquiz::get_catscales(array_keys($scalefeedback));
        $text = "";

        foreach ($scalefeedback as $scaleid => $value) {
            $text .= $catscales[$scaleid]->name . ': ' . $feedback . '<br/>';
        }

        return [
            'customscalefeedback' => $text,
            'quizsettings' => $quizsettings,
            'personabilities' => $personabilities,
        ];
    }

    /**
     * Gets the feedback for the given scale and range.
     *
     * @param int $catscaleid The CAT scale.
     * @param int $groupnumber Identifies the feedback within the scale.
     * @return ?string
     */
    private function getfeedbackforrange(int $catscaleid, int $groupnumber): ?string
    {
        // TODO: Implement getting the feedback.
        return null;

    }
}
