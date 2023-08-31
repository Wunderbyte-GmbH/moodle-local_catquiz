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

namespace local_catquiz\output;

use cache;
use local_catquiz\catquiz;
use local_catquiz\teststrategy\feedbackgenerator;
use local_catquiz\teststrategy\info;
use templatable;
use renderable;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attemptfeedback implements renderable, templatable {

    /**
     * @var ?int
     */
    public int $attemptid;

    /**
     * @var ?int
     */
    public int $contextid;

    /**
     * @var ?int
     */
    public int $catscaleid;

    /**
     * @var ?int
     */
    public int $teststrategy;

    /**
     * Constructor of class.
     *
     * @param int $attemptid
     * @param int $contextid
     * @param int $catscaleid
     *
     */
    public function __construct(int $attemptid, int $contextid = 0, int $catscaleid = 0) {
        global $USER;
        if ($attemptid === 0) {
            // This can still return nothing. In that case, we show a message that the user has no attempts yet.
            if (!$attemptid = catquiz::get_last_user_attemptid($USER->id)) {
                return;
            }
        }
        $this->attemptid = $attemptid;

        if (!$testenvironment = catquiz::get_testenvironment_by_attemptid($attemptid)) {
            return;
        }

        $settings = json_decode($testenvironment->json);
        $this->teststrategy = intval($settings->catquiz_selectteststrategy);

        if ($contextid === 0) {
            // Get the contextid from the attempt.
            $contextid = intval($settings->catquiz_catcontext);
        }
        $this->contextid = $contextid;

        if ($catscaleid === 0) {
            $catscaleid = intval($settings->catquiz_catcatscales);
        }
        $this->catscaleid = $catscaleid;
    }

    /**
     * Renders strategy feedback.
     *
     * @return mixed
     *
     */
    private function render_strategy_feedback() {
        if (!$this->teststrategy) {
            return '';
        }

        $availableteststrategies = info::return_available_strategies();
        $filteredstrategies = array_filter(
            $availableteststrategies,
            fn ($strategy) => $strategy->id === $this->teststrategy
        );

        if (!$attemptstrategy = reset($filteredstrategies)) {
            return '';
        }

        $feedbackclasses = $attemptstrategy->get_feedbackgenerators();

        /**
         * @var feedbackgenerator[]
         */
        $generators = [];
        foreach ($feedbackclasses as $classname) {
            $generators[] = new $classname();
        }

        $context = [
            'attemptid' => $this->attemptid,
            'contextid' => $this->contextid,
            'needsimprovementthreshold' => 0, // TODO: Get the quantile threshold from the quiz settings.
            'feedback' => [],
        ];

        $cache = cache::make('local_catquiz', 'adaptivequizattempt');
        if ($personabilities = $cache->get('personabilities')) {
            $context['personabilities'] = $personabilities;
        }
        if ($quizsettings = $cache->get('quizsettings')) {
            $context['quizsettings'] = $quizsettings;
        }
        
        foreach ($generators as $generator) {
            $feedback = $generator->get_feedback($context);
            if (! $feedback) {
                continue;
            }
            $context['feedback'][] = $feedback;
        }
        return $context['feedback'];
    }

    /**
     * Export for template.
     *
     * @param \renderer_base $output
     *
     * @return array
     *
     */
    public function export_for_template(\renderer_base $output): array {
        return [
            'feedback' => $this->render_strategy_feedback(),
        ];
    }
}
