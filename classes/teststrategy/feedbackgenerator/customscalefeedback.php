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
 * Class personabilities.
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

/**
 * Returns a custom feedback for each scale.
 *
 * If the person ability for this attempt is below the threshold as set in the
 * quiz settings, the user will see the message that was defined there.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customscalefeedback extends feedbackgenerator {

    /**
     * @var callable $sortfun
     */
    private $sortfun;

    /**
     * @var feedbacksettings $feedbacksettings
     */
    private $feedbacksettings;

    /**
     * Creates a new customscale feedback generator.
     *
     * @param feedbacksettings $feedbacksettings
     */
    public function __construct(feedbacksettings $feedbacksettings) {

        if (!isset($feedbacksettings)) {
            return;
        }

        // Order the feedbacks by their scale ability.
        // If none is given, the feedbacks are displayed in descending order of their ability.
        if ($feedbacksettings->sortorder == LOCAL_CATQUIZ_SORTORDER_ASC) {
            $this->sortfun = fn(&$x) => asort($x);
        } else {
            $this->sortfun = fn(&$x) => arsort($x);
        }
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

        if (!$data['customscalefeedback_abilities'] ?? false) {
            return [];
        }
        $customscalefeedback = $this->get_customscalefeedback_for_abilities_in_range(
            $data['customscalefeedback_abilities'],
            $data['quizsettings'],
            $data['catscales']
        );
        $firstelement = $data['customscalefeedback_abilities'][array_key_first($data['customscalefeedback_abilities'])];
        if (!empty($firstelement['estimated'])) {
            if (!isset($firstelement['fraction'])) {
                $comment = get_string('estimatedbecause:default', 'local_catquiz');
            } else {
                switch ((int) $firstelement['fraction']) {
                    case 1 :
                        $comment = get_string('estimatedbecause:allanswerscorrect', 'local_catquiz');
                        break;
                    case 0 :
                        $comment = get_string('estimatedbecause:allanswerinscorrect', 'local_catquiz');
                        break;
                    default :
                        $comment = get_string('estimatedbecause:default', 'local_catquiz');
                        break;

                }
            }
        }

        if (empty($customscalefeedback)) {
            return [];
        } else {
            return [
                'heading' => $this->get_heading(),
                'comment' => $comment ?? "",
                'content' => $customscalefeedback,
            ];
        }
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
        return [];
    }

    /**
     * Get required context keys.
     *
     * @return array
     *
     */
    public function get_required_context_keys(): array {
        return [
            'quizsettings',
            'customscalefeedback_abilities',
        ];
    }

    /**
     * Get heading.
     *
     * @return string
     *
     */
    public function get_heading(): string {
        return get_string('catquiz_feedbackheader', 'local_catquiz');
    }

    /**
     * Get generatorname.
     *
     * @return string
     *
     */
    public function get_generatorname(): string {
        return 'customscalefeedback';
    }

    /**
     * Load data.
     *
     * @param int $attemptid
     * @param array $existingdata
     * @param array $newdata
     *
     * @return array
     *
     */
    public function load_data(int $attemptid, array $existingdata, array $newdata): ?array {
        $quizsettings = $existingdata['quizsettings'];
        $progress = $newdata['progress'];
        if (is_array($progress)) {
            $personabilities = $progress['abilities'];
        } else {
            $personabilities = $progress->get_abilities();
        }

        if (!$personabilities) {
            return [];
        }

        $personabilitiesfeedbackeditor = $this->select_scales_for_report(
            $newdata,
            $this->feedbacksettings,
            $quizsettings,
            $existingdata['teststrategy']
        );

        return [
            'quizsettings' => $quizsettings,
            'personabilities' => $personabilities,
            'customscalefeedback_abilities' => $personabilitiesfeedbackeditor,
        ];
    }

    /**
     * Customscalefeedback defined in quizsettings will be returned if ability is within defined range.
     *
     * @param array $personabilities
     * @param array $quizsettings
     * @param array $catscales
     *
     * @return string
     *
     */
    private function get_customscalefeedback_for_abilities_in_range(
        array $personabilities,
        array $quizsettings,
        array $catscales
        ): string {
        $scalefeedback = [];
        $relevantscalesfound = false;

        // Filter for scales to be reported.
        $personabilities = array_filter($personabilities, fn($a) => isset($a['toreport']));
        foreach ($personabilities as $catscaleid => $personability) {
            if (isset($personability['excluded']) && $personability['excluded']) {
                continue;
            }
            $relevantscalesfound = true;
            for ($j = 1; $j <= $quizsettings['numberoffeedbackoptionsselect']; $j++) {
                $lowerlimitprop = sprintf('feedback_scaleid_limit_lower_%d_%d', $catscaleid, $j);
                $lowerlimit = floatval($quizsettings[$lowerlimitprop]);
                $upperlimitprop = sprintf('feedback_scaleid_limit_upper_%d_%d', $catscaleid, $j);
                $upperlimit = floatval($quizsettings[$upperlimitprop]);
                if ($personability['value'] < $lowerlimit || $personability['value'] > $upperlimit) {
                    continue;
                }

                $feedback = $this->getfeedbackforrange($catscaleid, $j, $quizsettings);
                // Do not display empty feedback messages.
                if (!$feedback) {
                    continue;
                }

                $scalefeedback[$catscaleid] = $feedback;
            }
        }

        if (! $scalefeedback) {
            if (!$relevantscalesfound) {
                return get_string('noscalesfound', 'local_catquiz');
            }
            return get_string('nofeedback', 'local_catquiz');
        }

        $text = "";

        foreach ($scalefeedback as $scaleid => $value) {
            $scale = (array) $catscales[$scaleid];
            $text .= $scale['name'] . ': ' . $value . '<br/>';
        }
        return $text;
    }

    /**
     * Gets the feedback for the given scale and range.
     *
     * @param int $catscaleid The CAT scale.
     * @param int $groupnumber Identifies the feedback within the scale.
     * @param array $quizsettings Data from form.
     * @return ?string
     */
    private function getfeedbackforrange(int $catscaleid, int $groupnumber, array $quizsettings): ?string {

        $quizsettingskey = 'feedbackeditor_scaleid_' . $catscaleid . '_' . $groupnumber;
        return ((array) $quizsettings[$quizsettingskey])['text'];

    }
}
