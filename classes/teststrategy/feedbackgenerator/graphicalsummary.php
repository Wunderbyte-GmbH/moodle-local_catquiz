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
 * Class graphicalsummary.
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
 * Compare the ability of this attempt to the average abilities of other students that took this test.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graphicalsummary extends feedbackgenerator {

    /**
     * Get student feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    protected function get_studentfeedback(array $data): array {
        return [];
    }

    /**
     * Get teacher feedback.
     *
     * @param array $data
     *
     * @return array
     *
     */
    protected function get_teacherfeedback(array $data): array {
        global $OUTPUT;
        $feedback = $OUTPUT->render_from_template('local_catquiz/feedback/graphicalsummary', ['data' => $data['graphicalsummary']]);

        return [
            'heading' => $this->get_heading(),
            'content' => $feedback,
        ];
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return get_string('graphicalsummary', 'local_catquiz');
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return ['graphicalsummary'];
    }

    /**
     * Load data.
     *
     * @param int $attemptid
     * @param array $initialcontext
     *
     * @return array|null
     *
     */
    public function load_data(int $attemptid, array $initialcontext): ?array {
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
        $graphicalsummary = [];
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

            $selectedscale = isset($data['selected_catscale'])
                ? $catscales[$data['selected_catscale']]->name
                : "NA";

            $lastresponse = isset($data['lastresponse'])
                ? $data['lastresponse']['fraction']
                : "NA";

            $standarderrorperscale = [];
            if (array_key_exists('standarderrorperscale', $data)) {
                foreach ($data['standarderrorperscale'] as $scaleid => $standarderror) {
                    // Convert INF to string so that the data can be json
                    // encoded and saved to the DB.
                    foreach (['played', 'remaining'] as $region) {
                        if (is_infinite($standarderror[$region])) {
                            $standarderror[$region] = "INF";
                        }
                    }
                    $standarderrorperscale[] = [
                        'name' => $catscales[$scaleid]->name,
                        'se' => $standarderror,
                    ];
                }
            }
            if ($standarderrorperscale) {
                $standarderrorperscale[array_key_last($standarderrorperscale)]['last'] = true;
            }

            $graphicalsummary[] = [
                'userid' => $data['userid'],
                'attemptid' => $data['attemptid'],
                'questionsattempted' => $data['questionsattempted'],
                'timestamp' => $data['timestamp'],
                'contextid' => $data['contextid'],
                'catscale' => !empty($catscales[$data['catscaleid']]->name) ? $catscales[$data['catscaleid']]->name : "no catscale",
                'teststrategy' => $teststrategy,
                'personabilities' => $personabilities,
                'active_scales' => '"' . implode(", ", array_map(fn ($catscale) => $catscale->name, $catscales)) . '"',
                'lastquestion' => (array) $data['lastquestion'],
                'selectedscale' => $selectedscale,
                'updateabilityfallback' => $data['updateabilityfallback'],
                'excludedsubscales' => implode(',', $data['excludedsubscales']),
                'lastresponse' => $lastresponse,
            ];
        }
        return ['graphicalsummary' => ['info' => 'testtesttest']];
    }
}
