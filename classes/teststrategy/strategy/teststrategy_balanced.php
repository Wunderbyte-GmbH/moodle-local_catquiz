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

namespace local_catquiz\teststrategy\strategy;

use cache;
use local_catquiz\teststrategy\preselect_task\lasttimeplayedpenalty;
use local_catquiz\teststrategy\preselect_task\maximumquestionscheck;
use local_catquiz\teststrategy\preselect_task\maybe_return_pilot;
use local_catquiz\teststrategy\preselect_task\noremainingquestions;
use local_catquiz\teststrategy\preselect_task\numberofgeneralattempts;
use local_catquiz\teststrategy\preselect_task\strategybalancedscore;
use local_catquiz\teststrategy\preselect_task\updatepersonability;
use local_catquiz\teststrategy\strategy;

/**
 * Will select questions in a way that balances the general number of attempts
 */
class teststrategy_balanced extends strategy {

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = STRATEGY_BALANCED;

    public function requires_score_modifiers(): array {
        return [
            maximumquestionscheck::class,
            updatepersonability::class,
            noremainingquestions::class,
            lasttimeplayedpenalty::class,
            numberofgeneralattempts::class,
            maybe_return_pilot::class,
            strategybalancedscore::class,
        ];
    }

    public static function attempt_feedback(): array {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $feedback = sprintf(
            '%s: %d',
            get_string('pilot_questions', 'local_catquiz'),
            $cache->get('num_pilot_questions')
        );
        if (!$parentfeedback = parent::attempt_feedback()) {
            return [$feedback];
        }
        return [...$parentfeedback, $feedback];
    }
}
