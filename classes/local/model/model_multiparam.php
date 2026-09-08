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
 * Class model_raschmodel.
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use coding_exception;
use local_catquiz\catcalc_ability_estimator;
use local_catquiz\catcalc_item_estimator;
use MoodleQuickForm;
use stdClass;

/**
 * This class implements model raschmodel.
 *
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class model_multiparam extends model_raschmodel {
    /**
     * Get static param array
     *
     * @param model_item_param $param
     * @return array
     * @throws coding_exception
     */
    public function get_static_param_array(\local_catquiz\local\model\model_item_param $param): array {
        $fractions = [];
        $count = 0;
        foreach ($this->get_parameter_fields($param) as $label => $value) {
            if ($label === 'discrimination') {
                continue;
            }
            if (preg_match('/^fraction_(.*)$/', $label, $matches)) {
                $count = $matches[1];
                $fractions['Fraction ' . $count] = $value;
            } else {
                $fractions['Difficulty ' . $count] = $value;
            }
        }

        if (!array_key_exists('discrimination', $param->get_params_array())) {
            return $fractions;
        }

        $disclabel = get_string('discrimination', 'local_catquiz');
        return array_merge(
            [$disclabel => $param->get_params_array()['discrimination']],
            $fractions,
        );
    }

    /**
     * Get parameter fields
     *
     * @param model_item_param $param
     * @return array
     */
    public function get_parameter_fields(model_item_param $param): array {
        if (!$param->get_params_array()) {
            return $this->get_default_params();
        }
        $paramsarray = $param->get_params_array();
        $parameters = [];
        if (array_key_exists('discrimination', $paramsarray)) {
            $parameters = ['discrimination' => $paramsarray['discrimination']];
        }
        $multiparam = $this->get_multi_param_name();
        $counter = 0;
        foreach ($param->get_params_array()[$multiparam] as $frac => $val) {
            $parameters['fraction_' . ++$counter] = $frac;
            $parameters['difficulty_' . $counter] = $val;
        }
        return $parameters;
    }

    /**
     * Converts an form array to a record
     *
     * @param array $formarray
     * @return stdClass
     */
    public function form_array_to_record(array $formarray): stdClass {
        $diffarray = [];
        $multiparam = $this->get_multi_param_name();
        foreach ($formarray as $key => $val) {
            if (preg_match('/^fraction_(.*)/', $key, $matches)) {
                $fraction = $val;
                continue;
            }
            if (preg_match('/^difficulty_(.*)/', $key, $matches)) {
                $diffarray[$fraction] = $val;
            }
        }

        return (object) [
            'discrimination' => $formarray['discrimination'] ?? 0,
            'json' => json_encode([$multiparam => $diffarray]),
        ];
    }

    /**
     * Get multi param name
     *
     * @return string
     */
    abstract protected static function get_multi_param_name(): string;

    /**
     * Validates the item parameters of a polytomous model.
     *
     * Polytomous models do NOT carry their real parameters in the scalar
     * `difficulty` column - that column only holds a derived mean. The actual
     * thresholds/intercepts live in the `json` field under the model's own key
     * (see get_multi_param_name()). Validating the scalar column would therefore
     * wave through an item whose json is missing or malformed, which would only
     * blow up later during estimation. Validate the json payload instead.
     *
     * @param \stdClass $record The raw item parameter record.
     * @return string[] Reasons the parameters are invalid; empty array if valid.
     */
    public static function validate_parameters(\stdClass $record): array {
        $reasons = [];
        $key = static::get_multi_param_name();

        $json = $record->json ?? '';
        if (!is_string($json) || trim($json) === '') {
            return [sprintf('no json payload, so no "%s" are stored', $key)];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !array_key_exists($key, $decoded)) {
            return [sprintf('json payload does not contain "%s"', $key)];
        }

        $values = $decoded[$key];
        if (!is_array($values) || $values === []) {
            $reasons[] = sprintf('"%s" is empty', $key);
            return $reasons;
        }

        foreach ($values as $index => $value) {
            if (!self::is_valid_float($value)) {
                $reasons[] = sprintf('"%s" entry %s is not a valid number', $key, (string) $index);
                break;
            }
        }

        return $reasons;
    }

    /**
     * All multiparam models support editing parameters.
     *
     * @return bool
     */
    public function supports_parameter_edits(): bool {
        return true;
    }
    /**
     * Clamp a discrimination (slope) into the model's configured trusted region.
     *
     * The graded/partial-credit models require a strictly positive slope: a
     * non-positive discrimination would invert the category ordering and produce
     * degenerate (zero/negative) category probabilities. The bounds are read from
     * the model's trusted_region_min_b / trusted_region_max_b settings (defaults
     * 0.1 and 5.0); an unset or empty setting falls back to those defaults, and the
     * lower bound is additionally floored at a small positive value so a
     * misconfigured non-positive minimum can never disable the invariant.
     *
     * @param string $componentname e.g. 'catmodel_grmgeneralized'
     * @param float $discrimination raw discrimination
     *
     * @return float clamped discrimination
     */
    protected static function restrict_discrimination(string $componentname, float $discrimination): float {
        $floor = 0.1;
        $minconfig = get_config($componentname, 'trusted_region_min_b');
        $maxconfig = get_config($componentname, 'trusted_region_max_b');
        $min = ($minconfig === false || $minconfig === '') ? $floor : max($floor, (float) $minconfig);
        $max = ($maxconfig === false || $maxconfig === '') ? 5.0 : (float) $maxconfig;
        if ($max < $min) {
            $max = $min;
        }
        return max($min, min($max, $discrimination));
    }
}
