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

namespace local_catquiz\event;

/**
 * A numeric fallback was applied during an ability estimation (issue #106).
 *
 * Diagnosis for operations, instead of a developer notice: which fallback, and the figures that led to
 * it - a scale, a number of values, mean and spread, the models. No person and no ability of a person.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class numeric_fallback_applied extends \core\event\base {
    /** @var string The spread of the trusted-region prior was raised to its minimum. */
    const SPREAD_RAISED = 'spreadraised';

    /** @var string The past abilities of a scale have (almost) no spread. */
    const NO_SPREAD = 'nospread';

    /** @var string An estimated ability was no finite number; the previous one, or 0, was kept. */
    const ABILITY_NOT_FINITE = 'abilitynotfinite';

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventnumericfallbackapplied', 'local_catquiz');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        $o = $this->other;
        $parts = [];
        foreach (
            ['catscaleid' => 'scale', 'n' => 'N', 'mean' => 'mean', 'sd' => 'sd', 'effectivesd' => 'used sd',
                'value' => 'value', 'kept' => 'kept', 'models' => 'models'] as $key => $label
        ) {
            if (array_key_exists($key, $o ?? []) && $o[$key] !== null && $o[$key] !== '') {
                $parts[] = $label . ' ' . (is_float($o[$key]) ? var_export($o[$key], true) : (string) $o[$key]);
            }
        }
        return sprintf('Numeric fallback "%s" applied (%s).', (string) ($o['reason'] ?? ''), implode(', ', $parts));
    }

    /**
     * Validates the data.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['reason'])) {
            throw new \coding_exception('The \'reason\' value must be set in other.');
        }
    }
}
