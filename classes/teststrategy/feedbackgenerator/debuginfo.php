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
use context_system;
use local_catquiz\catquiz;
use local_catquiz\teststrategy\feedbackgenerator;
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

        if (!get_config('local_catquiz', 'store_debug_info')) {
            return [];
        }

        $columnames = [
            'questionsattempted',
            'timestamp',
            'personabilities',
            'lastquestion',
            'questions',
            'activescales',
            'selectedscale',
            'lastmiddleware',
            'excluded subscales',
            'lastresponse',
            'standard error',
            'num questions per scale',
        ];
        $csvstring = implode(';', $columnames).PHP_EOL;

        foreach ($data['debuginfo'] as $row) {
            $rowarr = [];
            $rowarr[] = $row['questionsattempted'];
            $rowarr[] = $row['timestamp'];
            $rowarr[] = $row['personabilities'];

            $lastquestion = $row['lastquestion'];
            if (! $lastquestion) {
                $rowarr[] = 'NA';
            } else {
                $score = $lastquestion['score'] ?? 'NA';
                $fisherinformation = $lastquestion['fisherinformation'] ?? 'NA';
                $lasttimeplayedpenalty = $lastquestion['lasttimeplayedpenaltyfactor'] ?? 'NA';
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

            $rowarr[] = $row['activescales'] ?? 'NA';
            $rowarr[] = $row['selectedscale'];
            $rowarr[] = $row['lastmiddleware'];
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
        return 'Export';
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
        if (!get_config('local_catquiz', 'store_debug_info')) {
            return null;
        }
        $teststrategy = $this->get_progress()->get_quiz_settings()->catquiz_selectteststrategy;

        $teststrategies = info::return_available_strategies();
        $teststrategy = array_filter(
            $teststrategies,
            fn ($t) => $t->id == $teststrategy);
        $reflect = new \ReflectionClass($teststrategy[array_key_first($teststrategy)]);

        $debuginfo = $existingdata['debuginfo'] ?? [];
        $catscales = catquiz::get_catscales(array_keys($newdata['person_ability']));
        $teststrategy = get_string($reflect->getShortName(), 'local_catquiz');

            $personabilities = [];
        foreach ($newdata['person_ability'] as $catscaleid => $pp) {
            if (empty($catscales[$catscaleid])) {
                continue;
            }
            $personabilities[] = $catscales[$catscaleid]->name . ": " . $pp;
        }
            $personabilities = '"' . implode(", ", $personabilities) . '"';

            $questions = [];
            $questionsperscale = [];

            $selectedscale = isset($data['selected_catscale'])
                ? $catscales[$data['selected_catscale']]->name
                : "NA";

            $lastresponse = isset($newdata['lastresponse'])
                ? $newdata['lastresponse']['fraction']
                : "NA";

        if ($newdata['lastquestion']) {
            $newdata['lastquestion']
                ->fisherinformation = $newdata['lastquestion']->fisherinformation[$newdata['catscaleid']]
            ?? 'NA';
        }

            $activescales = array_map(
                fn ($scaleid) => $catscales[$scaleid]->name,
                $this->get_progress()->get_active_scales()
            );
            $debuginfo[] = [
                'questionsattempted' => count($this->get_progress()->get_playedquestions()),
                'timestamp' => time(),
                'personabilities' => $personabilities,
                'questions' => $questions,
                'activescales' => '"' . implode(", ", $activescales) . '"',
                'lastquestion' => (array) $newdata['lastquestion'],
                'selectedscale' => $selectedscale,
                'lastmiddleware' => $newdata['lastmiddleware'],
                'lastresponse' => $lastresponse,
                'numquestionsperscale' => '"'
                    . implode(", ", array_map(fn ($entry) => $entry['name'].": ".$entry['num'], $questionsperscale)) . '"',
            ];
            return ['debuginfo' => $debuginfo];
    }

    /**
     * Overwrite the inherited method to allow access only to CAT managers.
     *
     * @return bool
     */
    protected function has_teacherfeedbackpermission(): bool {
        return has_capability(
            'local/catquiz:canmanage', context_system::instance()
        );
    }
}
