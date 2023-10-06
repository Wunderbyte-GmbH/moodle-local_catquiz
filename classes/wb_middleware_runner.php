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
 * Class wb_middleware_runner.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use cache;
use local_catquiz\local\result;

/**
 * Runs the given middleware instances on the given input
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wb_middleware_runner {
    /**
     * Rum action.
     *
     * @param array $middlewares
     * @param array $context
     * @param mixed|null $action
     *
     * @return mixed
     *
     */
    public static function run(array $middlewares, array &$context, $action = null) {
        global $CFG;
        $cache = null;
        if ($CFG->debug > 0) {
                $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        }

        // Set a default action that just wraps the $context in a result
        if (!$action) {
            // Will be called last
            $action = self::get_last_action();
        }

        foreach (array_reverse($middlewares) as $middleware) {
            if (! $middleware instanceof wb_middleware) {
                throw new \moodle_exception(
                    sprintf('Class %s does not implement the wb_middleware interface', get_class($middleware))
                );
            }
            $action = function (array &$context) use ($action, $middleware, $cache): result {
                $result = $middleware->process($context, $action);
                if ($cache) {
                    $cachedcontexts = $cache->get('context') ?: [];
                    $cachedcontexts[$context['questionsattempted']] = $context;
                    $cache->set('context', $cachedcontexts);
                }
                return $result;
            };
        }

        return $action($context);
    }

    private static function get_last_action() {
        global $CFG;

        if ($CFG->debug > 0) {
            return function (array $context): result {
                $cache = cache::make('local_catquiz', 'adaptivequizattempt');
                $cache->set('context', $context);
                return result::ok($context);
            };
        }

        return fn (array $context): result => result::ok($context);
    }
}
