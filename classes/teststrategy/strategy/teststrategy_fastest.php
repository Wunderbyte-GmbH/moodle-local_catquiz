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

/**
 * Base class for test strategies.
 */
class teststrategy_fastest extends teststrategy {

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = STRATEGY_FASTEST;

    public function requires_score_modifiers(): array {
        return [
            'add_time_penalty',
            'numberofgeneralattempts',
            'add_fisher_info',
            'add_weighted_score',
        ];
    }
}
