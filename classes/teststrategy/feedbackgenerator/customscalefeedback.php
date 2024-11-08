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

        if (!$data['customscalefeedback_abilities'] ?? false) {
            return [];
        }
        $progress = $this->get_progress();
        $customscalefeedback = $this->get_customscalefeedback_for_abilities_in_range(
            $data['customscalefeedback_abilities'],
            (array) $progress->get_quiz_settings(),
            $data['catscales']
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
        $personabilitiestoreport = array_filter($personabilities, fn($a) => isset($a['toreport']));
        if (empty($personabilitiestoreport)) {
            // If no scale is to be reported, return reason.
            return $this->get_exclusion_reason_string($personabilities);
        }
        foreach ($personabilitiestoreport as $catscaleid => $personability) {
            if (!empty($personability['excluded']) || !empty($personability['hidden'])) {
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
            return $catscales[$a]->name <=> $catscales[$b]->name;
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

        foreach ($personabilities as $personability) {
            if (!isset($personability['excluded'])) {
                continue;
            }
            $errorcode = array_keys($personability['error'])[0];
            $errorarray = $personability['error'][$errorcode];

            switch ($errorcode) {
                case "rootonly": // Uses default string because information might be to complicated for users.
                    return get_string('error:rootonly', 'local_catquiz', $errorarray);
                case "se": // Uses default string because information might be to complicated for users.
                    if (isset($errorarray['semindefined'])) {
                        return get_string('error:semin', 'local_catquiz', $errorarray);
                    } else if (isset($errorarray['semaxdefined'])) {
                        return get_string('error:semax', 'local_catquiz', $errorarray);
                    }
                case "nminscale":
                    return get_string('error:nminscale', 'local_catquiz', $errorarray);
                case "fraction":
                    if ($errorarray['fraction'] == 1) {
                        return get_string('error:fraction1', 'local_catquiz');
                    } else if ($errorarray['fraction'] == 0) {
                        return get_string('error:fraction0', 'local_catquiz');
                    }
                default:
                    return get_string('noscalesfound', 'local_catquiz');
            }
        }
        return get_string('noscalesfound', 'local_catquiz');
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
