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
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy\feedbackgenerator;

use cache;
use local_catquiz\catquiz;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\teststrategy\info;

/**
 * Returns debug info.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class debuginfo extends feedbackgenerator {

    /**
     *
     * @var stdClass $feedbacksettings.
     */
    public feedbacksettings $feedbacksettings;

    /**
     * Creates a new customscale feedback generator.
     *
     * @param feedbacksettings $feedbacksettings
     */
    public function __construct(feedbacksettings $feedbacksettings) {

        $this->feedbacksettings = $feedbacksettings;
    }

    /**
     * Get student feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    public function get_studentfeedback(array $data): array {
        return [];
    }

    /**
     * Get teacher feedback.
     *
     * This renders a link with the href attribute set to data:text/csv and the
     * data contained within the link.
     *
     * @param array $data
     *
     * @return array
     *
     */
    protected function get_teacherfeedback(array $data): array {
        global $OUTPUT;

        $columnames = [
            'userid',
            'attemptid',
            'questionsattempted',
            'timestamp',
            'contextid',
            'catscale',
            'teststrategy',
            'personabilities',
            'lastquestion',
            'questions',
            'active_scales',
            'selectedscale',
            'lastmiddleware',
            'updateabilityfallback',
            'excluded subscales',
            'lastresponse',
            'standard error',
            'num questions per scale',
        ];
        $csvstring = implode(';', $columnames).PHP_EOL;

        foreach ($data['debuginfo'] as $row) {
            $rowarr = [];
            $rowarr[] = $row['userid'];
            $rowarr[] = $row['attemptid'];
            $rowarr[] = $row['questionsattempted'];
            $rowarr[] = $row['timestamp'];
            $rowarr[] = $row['contextid'];
            $rowarr[] = $row['catscale'];
            $rowarr[] = $row['teststrategy'];
            $rowarr[] = $row['personabilities'];

            $lastquestion = $row['lastquestion'];
            if (! $lastquestion) {
                $rowarr[] = 'NA';
            } else {
                $score = $lastquestion['score'] ?? 'NA';
                $fisherinformation = $lastquestion['fisherinformation'] ?? 'NA';
                $lasttimeplayedpenalty = $lastquestion['lasttimeplayedpenalty'] ?? 'NA';
                $difficulty = $lastquestion['difficulty'] ?? 'NA';
                $fraction = $lastquestion['fraction'] ?? 'NA';
                $rowarr[] =
                "id: " . $lastquestion['id']
                .", score: " . $score
                .", fisherinformation in root scale: " . $fisherinformation
                .", lasttimeplayedpenalty: " . $lasttimeplayedpenalty
                .", difficulty: " . $difficulty
                .", fraction: " . $fraction;
            }

            $questions = $row['questions'] ?? [];
            if (! $questions) {
                $rowarr[] = 'NA';
            } else {
                $questionsstr = "";
                foreach ($questions as $question) {
                    $questionsstr .= sprintf(
                       "id: %s, type: %s, fisherinformation: %s, score: %s%s",
                       $question['id'],
                       $question['type'],
                       $question['fisherinformation'],
                       $question['score'],
                       isset($question['last']) ? "" : ","
                    );
                }
                $rowarr[] = $questionsstr;
            }

            $rowarr[] = $row['active_scales'];
            $rowarr[] = $row['selectedscale'];
            $rowarr[] = $row['lastmiddleware'];
            $rowarr[] = $row['updateabilityfallback'];
            $rowarr[] = $row['excludedsubscales'];
            $rowarr[] = $row['lastresponse'];
            $rowarr[] = 'NA';

            $rowarr[] = $row['numquestionsperscale'] ?? 'NA';
            $csvstring .= implode(';', $rowarr).PHP_EOL;
        }
        $feedback = $OUTPUT->render_from_template(
            'local_catquiz/feedback/debuginfo',
            [
                'data' => rawurlencode($csvstring),
                'attemptid' => $data['debuginfo'][0]['attemptid'] ?? 'nan',
            ]
        );

        if (empty($feedback)) {
            return [];
        } else {
            return [
                'heading' => $this->get_heading(),
                'content' => $feedback,
            ];
        }
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return 'debug info';
    }

    /**
     * Get generatorname.
     *
     * @return string
     *
     */
    public function get_generatorname(): string {
        return 'debuginfo';
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return ['debuginfo'];
    }

    /**
     * Get question details.
     *
     * @param array $context
     *
     * @return mixed
     *
     */
    private function get_question_details(array $context) {
        return array_map(fn ($q) => (array) $q, $context['questions']);
    }

    /**
     * Load data.
     *
     * @param int $attemptid
     * @param array $existingdata
     * @param array $newdata
     *
     * @return array|null
     *
     */
    public function load_data(int $attemptid, array $existingdata, array $newdata): ?array {
        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        if (! $cachedcontexts = $cache->get('context')) {
            return null;
        }

        $teststrategies = info::return_available_strategies();
        $teststrategy = array_filter(
            $teststrategies,
            fn ($t) => $t->id == $cachedcontexts[array_key_first($cachedcontexts)]['teststrategy']);
        $reflect = new \ReflectionClass($teststrategy[array_key_first($teststrategy)]);

        // Each cachedcontext corresponds to one question attempt.
        $debuginfo = [];
        $catscales = catquiz::get_catscales(array_keys($cachedcontexts[array_key_first($cachedcontexts)]['person_ability']));
        $teststrategy = get_string($reflect->getShortName(), 'local_catquiz');

        foreach ($cachedcontexts as $data) {

            $personabilities = [];
            foreach ($data['person_ability'] as $catscaleid => $pp) {
                if (empty($catscales[$catscaleid])) {
                    continue;
                }
                $personabilities[] = $catscales[$catscaleid]->name . ": " . $pp;
            }
            $personabilities = '"' . implode(", ", $personabilities) . '"';

            $questions = [];
            $questionsperscale = [];
            if (array_key_exists('questions', $data)) {
                foreach ($data['questions'] as $qid => $question) {
                    $fisherinformation = $question->fisherinformation[$data['catscaleid']] ?? "NA";
                    $score = $question->score ?? "NA";
                    $questions[] = [
                        'id' => $qid,
                        'text' => $question->questiontext,
                        'type' => $question->qtype,
                        'fisherinformation' => $fisherinformation,
                        'score' => $score,
                    ];
                    if (! array_key_exists($question->catscaleid, $questionsperscale)) {
                        $questionsperscale[$question->catscaleid] = [
                            'num' => 0,
                            'name' => $catscales[$question->catscaleid]->name,
                        ];
                    }
                    $questionsperscale[$question->catscaleid]['num'] = $questionsperscale[$question->catscaleid]['num'] + 1;
                }
            }
            if ($questions) {
                $questions[array_key_last($questions)]['last'] = true;
            }

            $selectedscale = isset($data['selected_catscale'])
                ? $catscales[$data['selected_catscale']]->name
                : "NA";

            $lastresponse = isset($data['lastresponse'])
                ? $data['lastresponse']->get_response()
                : "NA";

            if ($data['lastquestion']) {
                $data['lastquestion']->fisherinformation = $data['lastquestion']->fisherinformation[$data['catscaleid']] ?? 'NA';
            }

            $debuginfo[] = [
                'userid' => $data['userid'],
                'attemptid' => $data['attemptid'],
                'questionsattempted' => $data['questionsattempted'],
                'timestamp' => $data['timestamp'],
                'contextid' => $data['contextid'],
                'catscale' => !empty($catscales[$data['catscaleid']]->name) ? $catscales[$data['catscaleid']]->name : "no catscale",
                'teststrategy' => $teststrategy,
                'personabilities' => $personabilities,
                'questions' => $questions,
                'active_scales' => '"' . implode(", ", array_map(fn ($catscale) => $catscale->name, $catscales)) . '"',
                'lastquestion' => (array) $data['lastquestion'],
                'selectedscale' => $selectedscale,
                'lastmiddleware' => $data['lastmiddleware'],
                'updateabilityfallback' => $data['updateabilityfallback'],
                'excludedsubscales' => implode(',', $data['excludedsubscales']),
                'lastresponse' => $lastresponse,
                'numquestionsperscale' => '"'
                    . implode(", ", array_map(fn ($entry) => $entry['name'].": ".$entry['num'], $questionsperscale)) . '"',
            ];
        }
        return ['debuginfo' => $debuginfo];
    }
}
