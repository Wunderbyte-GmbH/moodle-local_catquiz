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
 * Class filterbystandarderror.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use cache;
use dml_exception;
use local_catquiz\catscale;
use local_catquiz\local\result;
use local_catquiz\local\status;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\wb_middleware;

/**
 * Calculates the standarderror for each available catscale.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filterbystandarderror extends preselect_task implements wb_middleware {

    /**
     * Playes questions per scale.
     *
     * @var array|null
     */
    protected ?array $playedquestionsperscale = null;

    /**
     * Run method.
     *
     * @param array $context
     * @param callable $next
     *
     * @return result
     *
     */
    public function run(array &$context, callable $next): result {
        $threshold = $context['standarderrorpersubscale'];

        // If we already have enough information about that scale or if
        // we can never get below the standarderror threshold for that
        // scale, exclude it.
        foreach ($context['standarderrorperscale'] as $catscaleid => $standarderror) {
            $playedquestionsperscale = $context['playedquestionsperscale'][$catscaleid] ?? [];
            $attemptsinscale = empty($playedquestionsperscale)
                ? 0
                : count($playedquestionsperscale);
            if (
                $attemptsinscale >= $context['min_attempts_per_scale']
                && (
                    $standarderror['played'] < $threshold
                    || $standarderror['remaining'] > $threshold
                )
            ) {
                // Questions of this scale will not be selected in this attempt.
                $context['questions'] = array_filter($context['questions'], fn ($q) => $q->catscaleid != $catscaleid);
            }
        }

        // Handle case where no questions are left.
        if ($context['questions'] === []) {
            return result::err(status::ERROR_NO_REMAINING_QUESTIONS);
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
            'standarderrorpersubscale',
            'standarderrorperscale',
            'playedquestionsperscale',
        ];
    }
}
