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
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use local_catquiz\catquiz;
use local_catquiz\teststrategy\feedbackgenerator;

/**
 * Returns rendered attempt statistics.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionssummary extends feedbackgenerator {
    protected function run(array $context): array {
        $attemptid = $context['attemptid'];
        // 2. If an attemptid is given and belongs to the current user (or the user has permissions to see it), return that one.
        $attempt = catquiz::get_attempt_statistics($attemptid);
        $data = [];
        if (! $attempt) {
            return $this->no_data();
        }

        $data = [
            'gradedright' => $attempt['gradedright']->count ?? 0,
            'gradedwrong' => $attempt['gradedwrong']->count ?? 0,
            'gradedpartial' => $attempt['gradedpartial']->count ?? 0,
        ];
        global $OUTPUT;
        $feedback = $OUTPUT->render_from_template('local_catquiz/feedback/questionssummary', $data);

        return [
            'heading' => $this->get_heading(),
            'content' => $feedback,
        ];
    }

    public function get_heading(): string {
        return get_string('questionssummary', 'local_catquiz');
    }

    public function get_required_context_keys(): array {
        return ['attemptid'];
    }
}