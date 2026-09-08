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
 * Classifies whether an item's parameters are usable for its model (issue #54).
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local;

use local_catquiz\local\model\model_strategy;
use stdClass;

/**
 * Reports whether the stored parameters of an item can actually be used.
 *
 * An item whose parameters violate its model contract is treated as a
 * pilot item at runtime - necessary, because unusable parameters can silently
 * destroy the estimation (a 2PL item with discrimination 0 is mathematically mute,
 * so the ability estimate freezes). Until now that state was only visible in the
 * import feedback and in the attempt debug output, never where the item pool is
 * actually maintained.
 *
 * This class does not implement any rules of its own. It delegates to
 * model_strategy::validate_item_parameters(), which is the same call the import and
 * the runtime use, so the backend cannot drift away from what actually happens
 * during a test. New models and new contract rules take effect here without any
 * change to this class.
 */
class itemparam_validity {
    /** @var string Parameters exist and satisfy the model contract. */
    const STATE_USABLE = 'usable';

    /** @var string Parameters exist but violate the contract - treated as a pilot item. */
    const STATE_UNUSABLE = 'unusable';

    /** @var string No parameters at all - a classic pilot item. */
    const STATE_NOPARAMS = 'noparams';

    /**
     * Classifies one item record.
     *
     * Expects the fields the question list already selects (model, difficulty,
     * discrimination, guessing, json), so no additional query is needed per row.
     *
     * @param stdClass $record
     * @return array{state: string, reasons: string[], model: string}
     */
    public static function classify(stdClass $record): array {
        $model = (string) ($record->model ?? '');

        // A classic pilot item never had parameters calculated. That is a different
        // situation from parameters that exist but cannot be used, and the two must
        // stay distinguishable: the first is expected, the second needs attention.
        if ($model === '' && !self::has_any_parameter($record)) {
            return [
                'state' => self::STATE_NOPARAMS,
                'reasons' => [],
                'model' => '',
            ];
        }

        $reasons = model_strategy::validate_item_parameters($record);

        return [
            'state' => empty($reasons) ? self::STATE_USABLE : self::STATE_UNUSABLE,
            'reasons' => $reasons,
            'model' => $model,
        ];
    }

    /**
     * Whether the record carries any parameter value at all.
     *
     * @param stdClass $record
     * @return bool
     */
    private static function has_any_parameter(stdClass $record): bool {
        foreach (['difficulty', 'discrimination', 'guessing'] as $field) {
            // Explicit null check: 0.0 is a real, stored value - and precisely the
            // one that makes a 2PL item mute - so it must not be mistaken for
            // "no parameter".
            if (($record->{$field} ?? null) !== null) {
                return true;
            }
        }

        return !empty($record->json);
    }

    /**
     * Sets the persisted usable flag on a record about to be written.
     *
     * The backend has to filter and sort on the state, which needs a
     * database column - the state itself is derived in PHP from the model contract.
     * Persisting a derived value risks it drifting away from the rule, and the item
     * parameters are written from eight different places.
     *
     * This function is the single place where the flag is computed. Every write path
     * calls it, so the rule stays in model_strategy::validate_item_parameters() and
     * nothing else decides what "usable" means. local_catquiz_upgrade_backfill_usable()
     * recomputes the same value and reports mismatches, which turns the drift risk
     * into something measurable rather than something to hope about.
     *
     * @param \stdClass $record An item parameter record.
     * @return \stdClass The same record, with usable set.
     */
    public static function stamp(\stdClass $record): \stdClass {
        $record->usable = empty(model_strategy::validate_item_parameters($record)) ? 1 : 0;

        return $record;
    }

    /**
     * Returns a short, translated label for a state.
     *
     * @param string $state
     * @return string
     */
    public static function get_state_label(string $state): string {
        switch ($state) {
            case self::STATE_UNUSABLE:
                return get_string('itemparams_unusable', 'local_catquiz');
            case self::STATE_NOPARAMS:
                return get_string('itemparams_noparams', 'local_catquiz');
            default:
                return get_string('itemparams_usable', 'local_catquiz');
        }
    }

    /**
     * Returns the reason text including the model, for display.
     *
     * @param array $classification Result of classify().
     * @return string Empty string when there is nothing to explain.
     */
    public static function get_reason_text(array $classification): string {
        if (empty($classification['reasons'])) {
            return '';
        }

        return get_string('itemparams_unusable_reason', 'local_catquiz', (object) [
            'model' => $classification['model'],
            'reason' => implode('; ', $classification['reasons']),
        ]);
    }
}
