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
 * Class strategydeficitscore.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\preselect_task;

use local_catquiz\catscale;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\result;
use local_catquiz\teststrategy\preselect_task;
use local_catquiz\teststrategy\progress;
use stdClass;

/**
 * Add a score to each question and sort questions descending by score
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class strategyscore extends preselect_task {
    /**
     * Returns the scale term
     *
     * @param float $testinfo
     * @param float $abilitydifference
     * @return mixed
     */
    abstract protected function get_question_scaleterm(float $testinfo, float $abilitydifference);

    /**
     * Returns the item term
     *
     * @param float $testinfo
     * @param float $fraction
     * @param mixed $difficulty
     * @param mixed $scaleability
     * @param mixed $scalecount
     * @param int $minattemptsperscale
     * @return mixed
     */
    abstract protected function get_question_itemterm(
        float $testinfo,
        float $fraction,
        $difficulty,
        $scaleability,
        $scalecount,
        int $minattemptsperscale
    );

    /**
     * Returns the score for the given question and scaleid
     *
     * @param stdClass $question
     * @param int $scaleid
     * @return mixed
     */
    abstract protected function get_score(stdClass $question, int $scaleid);

    /**
     * Returns the active scales
     *
     * @return array
     */
    protected function get_scales(): array {
        return $this->progress->get_active_scales();
    }

    /**
     * Checks if a scale should be ignored
     *
     * @param int $scaleid
     * @return bool
     */
    protected function ignore_scale(int $scaleid): bool {
        return !$this->progress->is_active_scale($scaleid);
    }

    /**
     * Return the ability for the given scale.
     *
     * @param int $scaleid
     * @return ?float
     */
    protected function get_scale_ability(int $scaleid): ?float {
        if (!array_key_exists($scaleid, $this->progress->get_abilities())) {
            return null;
        }
        return $this->progress->get_abilities()[$scaleid];
    }

    /**
     * @var progress
     */
    protected progress $progress;

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
        $this->context = $context;
        $this->progress = $context['progress'];
        $userresponses = $this->progress->get_user_responses();
        $scalefractions = [];
        $scalecount = [];

        foreach ($this->get_scales() as $scaleid) {
            $played = $this->progress->get_playedquestions(true, $scaleid);
            $scalecount[$scaleid] = count($played);
            if ($scalecount[$scaleid] === 0) {
                $scalefractions[$scaleid] = 0.5;
            } else {
                $scalefractions[$scaleid] = array_sum(
                    array_map(
                        fn ($q) => $userresponses[$q->id]['fraction'],
                        $played
                    )
                ) / $scalecount[$scaleid];
            }
        }

        foreach ($context['questions'] as $question) {
            $affectedscales = [$question->catscaleid, ...catscale::get_ancestors($question->catscaleid)];
            $affectedscales = array_reverse($affectedscales); // Traverse from root to leave.

            foreach ($affectedscales as $scaleid) {
                if (
                    $this->ignore_scale($scaleid)
                    || is_null($scaleability = $this->get_scale_ability($scaleid))
                ) {
                    continue;
                }

                $scaleitems = model_item_param_list::get(
                    $context['contextid'],
                    null,
                    [$scaleid, ...catscale::get_subscale_ids($scaleid)]
                )
                    ->filter_by_componentids(array_keys($this->progress->get_playedquestions()));
                $standarderrorplayed = catscale::get_standarderror($scaleability, $scaleitems, INF);
                $testinfo = $standarderrorplayed === INF ? 0 : 1 / $standarderrorplayed ** 2;
                $question->processterm = max(0.1, $testinfo) / max(1, $scalecount[$scaleid]);
                $abilitydifference = ($scaleability - $this->progress->get_abilities()[$context['catscaleid']]);
                $question->scaleterm = $this->get_question_scaleterm($testinfo, $abilitydifference);
                $question->itemterm = $this->get_question_itemterm(
                    $testinfo,
                    $scalefractions[$scaleid],
                    $question->difficulty,
                    $scaleability,
                    $scalecount[$scaleid],
                    $context['min_attempts_per_scale']
                );

                $score = $this->get_score($question, $scaleid);
                if (! property_exists($question, 'score') || $score > $question->score) {
                    $question->score = $score;
                }
            }
        }

        // In order to have predictable results, in case the values of two
        // elements are exactly the same, sort by question ID.
        $remainingquestions = array_filter($context['questions'], fn ($q) => property_exists($q, 'score'));
        uasort($remainingquestions, function ($q1, $q2) {
            if (! ($q2->score === $q1->score)) {
                return $q2->score <=> $q1->score;
            }
            return $q1->id <=> $q2->id;
        });

        $selectedquestion = reset($remainingquestions);

        return result::ok($selectedquestion);
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
            'progress',
        ];
    }

    /**
     * Helper function for debugging
     *
     * @param int $scaleid
     * @param string $lastquestionlabel Only print if the last question has that label
     * @param stdClass $question The current question
     * @param array $debuglabels Only print output for questions with that label
     * @param float $testinfo Testinfo for the selected question's scale
     * @param float $scaleability Ability for the selected question's scale
     * @param float $standarderror
     * @param float $fraction
     * @param int $minattemptsperscale
     */
    private function print_debug_info(
        int $scaleid,
        string $lastquestionlabel,
        stdClass $question,
        array $debuglabels,
        float $testinfo,
        float $scaleability,
        float $standarderror,
        float $fraction,
        int $minattemptsperscale
    ) {
        $lastq = $this->progress->get_last_question();
        if ($lastq && $lastq->label === $lastquestionlabel) {
            if (in_array($question->label, $debuglabels)) {
                printf(
                    "%d %s: score: %f, testinfo: %f, ability: %f, processterm: %f - scaleterm: %f
                     - itemterm: %f - standarderror: %f - fraction: %f - minattempts: %f\n",
                    $scaleid,
                    $question->label,
                    $question->score,
                    $testinfo,
                    $scaleability,
                    $question->processterm,
                    $question->scaleterm,
                    $question->itemterm,
                    $standarderror,
                    $fraction,
                    $minattemptsperscale
                );
            }
        }
    }
}
