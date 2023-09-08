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
 * Class debuginfo.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use cache;
use local_catquiz\catquiz;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\info;

/**
 * Returns debug info.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class debuginfo extends feedbackgenerator {
    protected function run(array $context): array {
        if (! $this->has_permissions()) {
            return [
                'heading' => $this->get_heading(),
                'content' => 'No permission to view debug info',
            ];
        }

        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        $cachedcontexts = $cache->get('context');
        if (! $cachedcontexts) {
            return $this->no_data();
        }

        $teststrategies = info::return_available_strategies();
        $teststrategy = array_filter($teststrategies, fn ($t) => $t->id == $cachedcontexts[0]['teststrategy']);
        $reflect = new \ReflectionClass($teststrategy[array_key_first($teststrategy)]);


        // Each cachedcontext corresponds to one question attempt.
        $data = [];
        $catscales = catquiz::get_catscales(array_keys($cachedcontexts[0]['person_ability']));
        $teststrategy = get_string($reflect->getShortName(), 'local_catquiz');

        foreach ($cachedcontexts as $context) {

            $personabilities = [];
            foreach ($context['person_ability'] as $catscaleid => $pp) {
                $personabilities[] = $catscales[$catscaleid]->name . ": " . $pp;
            }
            $personabilities = '"' . implode(", ", $personabilities) . '"';

            $questions = [];
            foreach ($context['questions'] as $qid => $question) {
                $fisherinformation = $question->fisherinformation ?? "NA";
                $score = $question->score ?? "NA";
                $questions[] = [
                    'id' => $qid,
                    'text' => $question->questiontext,
                    'type' => $question->qtype,
                    'fisherinformation' => $fisherinformation,
                    'score' => $score,
                ];
            }
            $questions[array_key_last($questions)]['last'] = true;

            $selectedscale = isset($context['selected_catscale'])
                ? $catscales[$context['selected_catscale']]->name
                : "NA";

            $data[] = [
                'userid' => $context['userid'],
                'attemptid' => $context['testid'],
                'questionsattempted' => $context['questionsattempted'],
                'timestamp' => $context['timestamp'],
                'contextid' => $context['contextid'],
                'catscale' => $catscales[$context['catscaleid']]->name,
                'teststrategy' => $teststrategy,
                'personabilities' => $personabilities,
                'questions' => $questions,
                'active_scales' => '"' . implode(", ", array_map(fn ($catscale) => $catscale->name, $catscales)) . '"',
                'lastquestion' => (array) $context['lastquestion'],
                'selectedscale' => $selectedscale,
            ];
        }
        global $OUTPUT;
        $feedback = $OUTPUT->render_from_template('local_catquiz/feedback/debuginfo', ['data' => $data]);

        return [
            'heading' => $this->get_heading(),
            'content' => $feedback,
        ];
    }

    public function get_heading(): string {
        return 'debug info';
    }

    public function get_required_context_keys(): array {
        return [];
    }

    private function has_permissions() {
        // TODO: implement.
        return true;
    }

    private function get_question_details(array $context) {
        return array_map(fn ($q) => (array) $q, $context['questions']);
    }
}