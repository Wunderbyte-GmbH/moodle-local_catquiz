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
 * Class questions_loader.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\context\loader;

use cache;
use local_catquiz\catscale;
use local_catquiz\teststrategy\context\contextloaderinterface;
use local_catquiz\teststrategy\progress;

/**
 * Loads questions for test strategies.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questions_loader implements contextloaderinterface {

    /**
     * @var progress $progress
     */
    private progress $progress;

    /**
     * Returns array of items.
     *
     * @return array
     *
     */
    public function provides(): array {
        return ['questions'];
    }

    /**
     * Returns requires.
     *
     * @return array
     *
     */
    public function requires(): array {
        return [
            'catscaleid',
            'contextid',
            'includesubscales',
            'progress',
        ];
    }

    /**
     * Load testitems.
     *
     * @param array $context
     *
     * @return array
     *
     */
    public function load(array $context): array {
        $this->progress = $context['progress'];
        $catscale = new catscale($context['catscaleid']);
        $context['questions'] = $catscale->get_testitems(
            $context['contextid'],
            $context['includesubscales'],
            'difficulty',
            $context['selectedsubscales'],
        );
        $context['questions_ordered_by'] = 'difficulty';
        $context['original_questions'] = $context['questions'];

        // Some questions can be manually excluded, e.g. when the time limit is exceeded.
        foreach ($this->progress->get_excluded_questions() as $excludedqid) {
            unset($context['questions'][$excludedqid]);
        }

        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        if ($this->progress->is_first_question()) {
            $cache->set('totalnumberoftestitems', count($context['questions']));
        }

        // Store for each scaleid the questions associated with that scale or
        // any of its subscales.
        $ancestorscales = [];
        $questionsperscale = [];
        foreach ($context['questions'] as $q) {
            $scale = $q->catscaleid;
            if (!isset($ancestorscales[$scale])) {
                $ancestorscales[$scale] = catscale::get_ancestors($scale);
            }
            $questionscales = [$scale, ...$ancestorscales[$scale]];
            foreach ($questionscales as $s) {
                $questionsperscale[$s][$q->id] = $q;
            }
        }
        $context['questionsperscale'] = $questionsperscale;

        return $context;
    }

}
