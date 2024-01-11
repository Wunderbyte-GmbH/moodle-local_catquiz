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
 * Class mayberemovescale.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use cache;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Checks if subscales should be excluded and removes the respective questions
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mayberemovescale extends preselect_task implements wb_middleware {
    /**
     * Run preselect task.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array &$context, callable $next): result {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $played = $cache->get('playedquestionsperscale') ?: [];
        if (count($played) === 0) {
            return $next($context);
        }

        // Remove catscale questions of scales that are marked as excluded in the cache.
        $excludedscales = $cache->get('excludedscales') ?: [];
        foreach ($excludedscales as $catscaleid) {
            $context['questions'] = array_filter(
                $context['questions'],
                fn ($q) => $q->catscaleid != $catscaleid
            );
        }

        if ($context['max_attempts_per_scale'] == -1) {
            return $next($context);
        }

        foreach ($played as $catscaleid => $questions) {
            if (($context['max_attempts_per_scale'] != -1)
                && (count($questions) >= $context['max_attempts_per_scale'])) {
                $context['questions'] = array_filter(
                    $context['questions'],
                    fn ($q) => $q->catscaleid !== $catscaleid
                );
            }
        }

        return $next($context);
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'questions',
            'max_attempts_per_scale',
        ];
    }
}
