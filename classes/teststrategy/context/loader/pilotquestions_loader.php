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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\context\loader;
use local_catquiz\teststrategy\context\contextloaderinterface;
use local_catquiz\local\model\model_strategy;

/**
 * Moves pilot questions to a separate context key `pilot_questions` and removes them from the `questions` key.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pilotquestions_loader implements contextloaderinterface {
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
            'pilot_attempts_threshold',
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
        foreach ($context['questions'] as $question) {
            $question->is_pilot = $this->ispilot(
                $question,
                $context['pilot_attempts_threshold'],
                $context
            );
        }
        return $context;
    }

    /**
     * Shows if a question is a pilot question.
     *
     * @param \stdClass $question
     * @param int $attemptsthreshold
     * @param array|null $context Optional strategy context, used to collect debug warnings.
     *
     * @return bool
     *
     */
    public function ispilot(\stdClass $question, int $attemptsthreshold, ?array &$context = null): bool {
        // Original rule: no item parameter record at all means the item is a pilot.
        // A difficulty of exactly 0.0 is a perfectly regular IRT parameter (an
        // item of average difficulty) - it must never mark an item as a pilot.
        // The previous guard used floatval($question->difficulty) as a truthiness
        // test, so every calibrated b = 0 item was misclassified as a pilot even
        // when its status was UPDATED_MANUALLY. Those items were then skipped by
        // updatepersonability, which froze the ability estimate. Decide on
        // parameter *presence* plus calibration status/attempts instead.
        $hasdifficulty = isset($question->difficulty)
            && $question->difficulty !== ''
            && $question->difficulty !== null
            && is_numeric($question->difficulty);
        if (
            !$hasdifficulty
            || (intval($question->status) < LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY
                && intval($question->attempts) < $attemptsthreshold
            )
        ) {
            return true;
        }

        // Extended rule: parameters that exist but are unusable for the item's model
        // are treated like missing parameters, i.e. the item becomes a pilot. Playing
        // such an item productively is worse than piloting it - a 2PL item with
        // discrimination 0 is mathematically mute and freezes the ability estimate.
        $reasons = model_strategy::validate_item_parameters($question);
        if ($reasons) {
            $this->record_invalid_parameters($question, $reasons, $context);
            return true;
        }

        return false;
    }

    /**
     * Records a debug warning about unusable item parameters.
     *
     * The warning is only collected when CATQUIZ debug output is active; it is
     * surfaced in the attempt debug output (PDF/export) so that a broken item can
     * be traced back to its id, model and concrete reason.
     *
     * @param \stdClass $question
     * @param string[] $reasons
     * @param array|null $context
     * @return void
     */
    private function record_invalid_parameters(\stdClass $question, array $reasons, ?array &$context): void {
        if (!get_config('local_catquiz', 'store_debug_info')) {
            return;
        }

        $warning = [
            'itemid' => $question->id ?? ($question->componentid ?? null),
            'label' => $question->label ?? '',
            'model' => $question->model ?? '',
            'reason' => implode('; ', $reasons),
        ];

        if ($context !== null) {
            $context['invaliditemparams'][] = $warning;
        }

        debugging(
            sprintf(
                'local_catquiz: item %s (model "%s") treated as pilot - %s',
                (string) ($warning['itemid'] ?? '?'),
                (string) $warning['model'],
                $warning['reason']
            ),
            DEBUG_DEVELOPER
        );
    }
}
