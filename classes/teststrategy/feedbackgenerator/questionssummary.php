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
 * Class questionssummary.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use local_catquiz\catquiz;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedbacksettings;

/**
 * Returns rendered attempt statistics.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionssummary extends feedbackgenerator {

    /**
     *
     * @var feedbacksettings $feedbacksettings.
     */
    public feedbacksettings $feedbacksettings;

    /**
     * Creates a new customscale feedback generator.
     *
     * @param feedbacksettings $feedbacksettings
     */
    public function __construct(feedbacksettings $feedbacksettings) {

        $this->feedbacksettings = $feedbacksettings;
    }

    /**
     * Get student feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    public function get_studentfeedback(array $data): array {
        global $OUTPUT;
        $feedback = $OUTPUT->render_from_template('local_catquiz/feedback/questionssummary', $data);

        if (empty($feedback)) {
            return [];
        } else {
            return [
                'heading' => $this->get_heading(),
                'content' => $feedback,
            ];
        }

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
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return get_string('questionssummary', 'local_catquiz');
    }

    /**
     * Get generatorname.
     *
     * @return string
     *
     */
    public function get_generatorname(): string {
        return 'questionssummary';
    }

    /**
     * For specific feedbackdata defined in generators.
     *
     * @param array $feedbackdata
     */
    public function apply_settings_to_feedbackdata(array $feedbackdata) {
        // Get excluded names from settings.
        // Check if whole generator or only certain keys are excluded.
        // Compare with names for fields and write new array with feedbackkeys only.
        // Exclude feedbackkeys from feedbackdata.
        $feedbackdata = $this->feedbacksettings->hide_defined_elements($feedbackdata, $this->get_generatorname());
        return $feedbackdata;
    }

    /**
     * Loads data.
     *
     * @param int $attemptid
     * @param array $existingdata
     * @param array $newdata
     *
     * @return array|null
     *
     */
    public function load_data(int $attemptid, array $existingdata, array $newdata): ?array {
        if (! $attempt = catquiz::get_attempt_statistics($attemptid)) {
            return null;
        }

        return ['questionssummary' => [
                'gradedright' => $attempt['gradedright']->count ?? 0,
                // We want to count skipped questions as wrong. The skipped answers
                // are stored under array key null.
                'gradedwrong' => ($attempt['gradedwrong']->count ?? 0)
                    + ($attempt[null]->count ?? 0),
                'gradedpartial' => $attempt['gradedpartial']->count ?? 0,
            ],
        ];
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'questionssummary',
        ];
    }
}
