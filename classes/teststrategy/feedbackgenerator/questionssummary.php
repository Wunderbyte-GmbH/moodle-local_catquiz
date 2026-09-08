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

/**
 * Returns rendered attempt statistics.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionssummary extends feedbackgenerator {
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
        if (! $rows = catquiz::get_attempt_statistics($attemptid)) {
            return null;
        }

        // Exclude pilot items from the performance counters. The pilot
        // flag is context-computed, so we get the played pilot question ids from
        // the progress and drop those questions here.
        $pilotids = array_map('intval', array_keys($this->get_progress()->get_played_pilot_questions()));

        $right = 0;
        $wrong = 0;
        $partial = 0;
        $unanswered = 0;
        foreach ($rows as $row) {
            if (in_array((int) $row->questionid, $pilotids, true)) {
                continue;
            }
            // No graded step at all: skipped/unanswered, kept separate from wrong.
            if ($row->fraction === null) {
                $unanswered++;
                continue;
            }
            $fraction = (float) $row->fraction;
            if ($fraction >= 1.0) {
                $right++;
            } else if ($fraction > 0.0) {
                $partial++;
            } else {
                $wrong++;
            }
        }

        return ['questionssummary' => [
                'gradedright' => $right,
                'gradedwrong' => $wrong,
                'gradedpartial' => $partial,
                'gradedunanswered' => $unanswered,
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
