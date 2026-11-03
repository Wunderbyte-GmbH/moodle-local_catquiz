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
use core_collator;
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
            // With the progress, so the per-scale N is known and a scale not measured in this attempt
            // is not taken as measured (issue #128).
            ['se' => $data['se'] ?? [], 'progress' => $progress]
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
     * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id - not local_catquiz_attempts.id.
     * @param array $existingdata
     * @param array $newdata
     *
     * @return array
     *
     */
    public function load_data(int $adaptiveattemptid, array $existingdata, array $newdata): ?array {
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
            // Shown scales exist (the empty case returned above), but none has a range text.
            return get_string('nofeedback', 'local_catquiz');
        }

        $sorted = self::sort_scalefeedback($scalefeedback, $catscales, $this->mainscale);

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
        // A range without a text has nothing to say - and does not take the result page down.
        $content = $quizsettings[$quizsettingskey] ?? '';
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

    /**
     * Orders the feedback: main scale first, the rest by the name of their scale.
     *
     * Extracted so the ordering can be tested on its own - it used to sit inside a private method
     * that is only reachable through feedback ranges, an uncertainty factor and the result gate.
     *
     * Three assumptions used to be made here without a check: that an entry for the key exists,
     * that the array key equals catscale.id, and that the entry is an object. A missing scale
     * threw, and a list-keyed array sorted by whatever happened to sit at that offset (issue
     * #102). The name is looked up directly now, and a scale that is not there sorts to the end
     * instead of taking the page down.
     *
     * @param array $scalefeedback Feedback texts keyed by catscale id.
     * @param array $catscales Scale records, keyed by catscale id; entries may be arrays or objects.
     * @param int|null $mainscaleid The scale to put first, if it has feedback.
     * @return array The feedback texts in display order.
     */
    public static function sort_scalefeedback(array $scalefeedback, array $catscales, ?int $mainscaleid): array {
        $mainscale = $mainscaleid !== null ? ($scalefeedback[$mainscaleid] ?? null) : null;

        if ($mainscaleid !== null) {
            unset($scalefeedback[$mainscaleid]);
        }

        /* The scale records are keyed by catscale id here, whatever they arrived as. Only refusing
           to crash on a list-keyed array would not be enough: no scale would find its name, every
           entry would sort equal, and the order would silently be the insertion order. */
        $byid = [];
        foreach ($catscales as $key => $scale) {
            $id = is_object($scale) ? ($scale->id ?? null) : ($scale['id'] ?? null);
            $byid[$id === null ? $key : (int) $id] = $scale;
        }

        // Issue #77: by name as people read it - locale aware, umlauts with their letter, case does not
        // decide, "Scale 2" before "Scale 10". A scale without a name, or one we know nothing about, comes
        // last, in the order of its id.
        $named = [];
        $unnamed = [];
        foreach (array_keys($scalefeedback) as $scaleid) {
            $scale = $byid[$scaleid] ?? null;
            $name = $scale === null ? null : (is_object($scale) ? ($scale->name ?? null) : ($scale['name'] ?? null));
            if ($name === null || trim((string) $name) === '') {
                $unnamed[] = $scaleid;
            } else {
                $named[$scaleid] = (string) $name;
            }
        }
        core_collator::asort($named, core_collator::SORT_NATURAL);
        sort($unnamed);
        $ordered = [];
        foreach ([...array_keys($named), ...$unnamed] as $scaleid) {
            $ordered[$scaleid] = $scalefeedback[$scaleid];
        }
        $scalefeedback = $ordered;

        if ($mainscale === null) {
            return $scalefeedback;
        }

        return [$mainscale, ...$scalefeedback];
    }
}
