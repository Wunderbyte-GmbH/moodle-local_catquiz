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
 * Class model_sorter.
 *
 * Usage example:
 * $models = [
 *     'mixedraschbirnbaum' => $mixedRaschBirnbaumModel,
 *     'rasch' => $raschModel,
 *     'raschbirnbaum' => $raschBirnbaumModel,
 * ];
 *
 * $sortedmodels = model_sorter::sort($models);
 *
 * To add a new model in the future:
 * model_sorter::add_model_order('newmodel', 4);
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\local\model;

use InvalidArgumentException;

/**
 * Model sorter class.
 *
 * Returns models in the defined order.
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_sorter {
    /**
     * Defines the order in which models should appear.
     * Lower numbers appear first in the sorted result.
     * @var array
     */
    private static array $order = [
        'rasch' => 1,
        'raschbirnbaum' => 2,
        'mixedraschbirnbaum' => 3,
        // Add new models here with their order priority.
    ];

    /**
     * Sorts an array of models according to predefined order
     *
     * @param array $models Array of models with class names as keys
     * @return array Sorted array of models
     * @throws InvalidArgumentException If an unknown model is encountered
     */
    public static function sort(array $models): array {
        // Validate all models have a defined order.
        foreach (array_keys($models) as $modelname) {
            if (!isset(self::$order[$modelname])) {
                throw new InvalidArgumentException(
                    "Unknown model: $modelname. Please define its order in model_sorter::\$order"
                );
            }
        }

        // Sort based on the predefined order.
        uksort($models, function ($a, $b) {
            return self::$order[$a] <=> self::$order[$b];
        });

        return $models;
    }

    /**
     * Adds a new model to the ordering system
     *
     * @param string $modelname Name of the model class
     * @param int $order Order priority (lower numbers appear first)
     */
    public static function add_model_order(string $modelname, int $order): void {
        self::$order[$modelname] = $order;
    }
}
