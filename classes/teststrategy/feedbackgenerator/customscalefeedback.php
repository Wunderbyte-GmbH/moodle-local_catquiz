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

use context_module;
use local_catquiz\teststrategy\feedback_helper;
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
     * Stores the testid
     * @var ?int
     */
    private ?int $testid;

    /**
     * Stores the main scale ID.
     *
     * @var int
     */
    private int $mainscale;

    /**
     * Creates a new customscale feedback generator.
     *
     * @param feedbacksettings $feedbacksettings
     * @param feedback_helper $feedbackhelper
     */
    public function __construct(feedbacksettings $feedbacksettings, feedback_helper $feedbackhelper) {
        parent::__construct($feedbacksettings, $feedbackhelper);

        // Order the feedbacks by their scale ability.
        // If none is given, the feedbacks are displayed in descending order of their ability.
        if ($feedbacksettings->is_sorted_ascending()) {
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
        $this->testid = $data['testid'];
        $this->mainscale = $data['catscaleid'];

        if (!($data['customscalefeedback_abilities'] ?? false)) {
            return [];
        }
        $progress = $this->get_progress();
        $customscalefeedback = $this->get_customscalefeedback_for_abilities_in_range(
            $data['customscalefeedback_abilities'],
            (array) $progress->get_quiz_settings(),
            $data['catscales'],
            ['se' => $data['se'] ?? []]
        );

        if (empty($customscalefeedback)) {
            return [];
        }

        return [
            'heading' => $this->get_heading(),
            'content' => $customscalefeedback,
        ];
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
        $progress = $this->get_progress();
        $personabilities = $progress->get_abilities(true);

        if (!$personabilities) {
            return [];
        }

        $personabilitiesfeedbackeditor = $this->select_scales_for_report(
            $newdata,
            $this->feedbacksettings,
            $existingdata['teststrategy']
        );

        // Attach the per-scale standard error so the feedback range
        // resolver can optionally apply measurement-uncertainty gating.
        if (isset($newdata['se']) && is_array($newdata['se'])) {
            foreach ($personabilitiesfeedbackeditor as $scaleid => &$abilityentry) {
                if (is_array($abilityentry) && isset($newdata['se'][$scaleid])) {
                    $abilityentry['se'] = (float) $newdata['se'][$scaleid];
                }
            }
            unset($abilityentry);
        }

        return [
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
     * @param array $feedbackdata Surrounding feedback data, used for the SE values.
     *
     * @return string
     *
     */
    private function get_customscalefeedback_for_abilities_in_range(
        array $personabilities,
        array $quizsettings,
        array $catscales,
        array $feedbackdata = []
    ): string {
        $scalefeedback = [];
        $relevantscalesfound = false;

        /* Issue #7 DoD 2/3: the gate and the rejection message both come from the
           central result object. Previously this filtered on `toreport` and then
           skipped `excluded`/`hidden` itself, which duplicated the validator's
           rules and left the display out of step whenever those rules changed. */
        $attemptresult = feedback_helper::build_attempt_result($personabilities, $feedbackdata);
        $displayable = array_filter(
            $personabilities,
            fn($scaleid) => feedback_helper::is_displayable($attemptresult, (int) $scaleid),
            ARRAY_FILTER_USE_KEY
        );
        if (empty($displayable)) {
            // No scale can be shown: report the machine readable reason.
            return feedback_helper::get_rejection_reason_string($attemptresult, $personabilities);
        }
        foreach ($displayable as $catscaleid => $personability) {
            $relevantscalesfound = true;
            // A score is assigned to exactly one range (half-open
            // intervals), instead of matching every range whose inclusive bounds
            // contain the value and letting the last match overwrite the earlier.
            // When measurement-uncertainty gating is enabled (factor > 0), the
            // whole confidence interval ability +/- k*SE must fall into one range;
            // otherwise the classification is uncertain and a neutral transition
            // message is shown instead of a definite range feedback.
            $uncertaintyfactor = (float) get_config('local_catquiz', 'feedback_uncertainty_factor');
            if ($uncertaintyfactor > 0.0) {
                $se = isset($personability['se']) ? (float) $personability['se'] : null;
                $rangeindex = feedback_helper::get_feedback_range_index_with_uncertainty(
                    $quizsettings,
                    $catscaleid,
                    (float) $personability['value'],
                    $se,
                    $uncertaintyfactor
                );
                if ($rangeindex === null) {
                    // Only emit the neutral notice when the point value itself is
                    // inside the configured ranges (i.e. the interval merely
                    // straddles a boundary), not when the value is out of range.
                    $pointindex = feedback_helper::get_feedback_range_index(
                        $quizsettings,
                        $catscaleid,
                        (float) $personability['value']
                    );
                    if ($pointindex !== null) {
                        $scalefeedback[$catscaleid] = get_string('feedbackrangeuncertain', 'local_catquiz');
                    }
                    continue;
                }
            } else {
                $rangeindex = feedback_helper::get_feedback_range_index(
                    $quizsettings,
                    $catscaleid,
                    (float) $personability['value']
                );
            }
            if ($rangeindex === null) {
                continue;
            }
            $feedback = $this->getfeedbackforrange($catscaleid, $rangeindex, $quizsettings);
            // Do not display empty feedback messages.
            if (!$feedback) {
                continue;
            }
            $scalefeedback[$catscaleid] = $feedback;
        }

        if (!$scalefeedback) {
            if (!$relevantscalesfound) {
                return $this->get_exclusion_reason_string($personabilitiestoreport);
            }
            return get_string('nofeedback', 'local_catquiz');
        }

        // Sort in the following way:
        // 1. Main scale always comes first.
        // 2. Other scales are sorted by name.
        $mainscale = $scalefeedback[$this->mainscale] ?? null;
        unset($scalefeedback[$this->mainscale]);
        uksort($scalefeedback, function ($a, $b) use ($catscales) {
            $a = (object) $catscales[$a];
            $b = (object) $catscales[$b];
            return $catscales[$a->id]->name <=> $catscales[$b->id]->name;
        });
        $sorted = $scalefeedback;
        if ($mainscale) {
            $sorted = [$mainscale, ...$scalefeedback];
        }

        $text = "";
        foreach ($sorted as $value) {
            $text .= $value . '<br/>';
        }
        return $text;
    }

    /**
     * Check in personabilities array for the reason feedback was excluded and return reason as readable string.
     *
     * @param array $personabilities
     *
     * @return string
     *
     */
    private function get_exclusion_reason_string(array $personabilities): string {
        return \local_catquiz\teststrategy\feedback_helper::get_exclusion_reason_string($personabilities);
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
        if ($cm = get_coursemodule_from_instance('adaptivequiz', $this->testid)) {
            $context = context_module::instance($cm->id);
        }
        $quizsettingskey = 'feedbackeditor_scaleid_' . $catscaleid . '_' . $groupnumber;
        $filearea = sprintf('feedback_files_%d_%d', $catscaleid, $groupnumber);

        // To be compatible with the old format, check if content is an object and if so, extract the
        // text from there.
        if (!array_key_exists($quizsettingskey, $quizsettings)) {
             $quizsettingskey .= '_editor';
        }
        $content = $quizsettings[$quizsettingskey];
        if (is_object($content) && property_exists($content, 'text')) {
            $content = $content->text;
        }

        if ($cm) {
            return file_rewrite_pluginfile_urls(
                $content,
                'pluginfile.php',
                $context->id,
                'local_catquiz',
                $filearea,
                $this->testid
            );
        }

        return $content;
    }
}
