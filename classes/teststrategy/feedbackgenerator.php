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
 * Class feedbackgenerator.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy;

use coding_exception;
use context_system;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use UnexpectedValueException;

/**
 * Classes of this type return feedback for a quiz attempt.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class feedbackgenerator {
    /**
     * Attempt ID
     *
     * @var int
     */
    private int $attemptid;

    /**
     * Component ID
     *
     * @var string
     */
    private string $component = 'mod_adaptivequiz';

    /**
     * Context ID
     *
     * @var int
     */
    private int $contextid;

    /**
     * @var ?progress
     */
    private ?progress $progress = null;

    /**
     * Returns the progress for the current attempt, component and contextid.
     *
     * @return progress
     */
    protected function get_progress(): progress {
        if ($this->progress) {
            return $this->progress;
        }

        $this->progress = progress::load($this->attemptid, $this->component, $this->contextid);
        return $this->progress;
    }

    /**
     * Returns an array with two keys 'heading' and 'context'.
     *
     * @param array $data
     * @return array
     */
    abstract protected function get_studentfeedback(array $data): array;

    /**
     * Returns an array with two keys 'heading' and 'context'.
     *
     * @param array $data
     * @return array
     */
    abstract protected function get_teacherfeedback(array $data): array;

    /**
     * Returns an array of the required elements in the $context array that will
     * be passed to the feedbackgenerator.
     *
     * @return array
     */
    abstract public function get_required_context_keys(): array;

    /**
     * The translated heading of this feedback.
     *
     * @return string
     */
    abstract public function get_heading(): string;

    /**
     * The translated heading of this feedback.
     *
     * @return string
     */
    abstract public function get_generatorname(): string;

    /**
     * Update the feedback data that is stored in the DB to render the feedback
     *
     * @param int $attemptid
     * @param array $existingdata
     * @param array $newdata
     * @return null|array
     */
    public function update_data(int $attemptid, array $existingdata, array $newdata): ?array {
        $this->attemptid = $attemptid;
        $this->contextid = $newdata['contextid'];
        return $this->load_data($attemptid, $existingdata, $newdata);
    }

    /**
     * Loads the data required to render the feedback.
     *
     * @param int $attemptid
     * @param array $existingdata
     * @param array $newdata Data from the last attempt
     * @return ?array
     */
    abstract public function load_data(int $attemptid, array $existingdata, array $newdata): ?array;

    /**
     * To update feedbackdata (that will be rendered later)...
     * ...according to specific settings defined by strategy and results.
     *
     * @param array $feedbackdata
     */
    public function apply_settings_to_feedbackdata(array $feedbackdata) {
        return $feedbackdata;
    }

    /**
     * Returns the feedback as an array with elements 'heading' and 'feedback'.
     *
     * @param array $feedbackdata
     * @return array
     * @throws coding_exception
     * @throws UnexpectedValueException
     */
    public function get_feedback(array $feedbackdata): array {
        $this->attemptid = $feedbackdata['attemptid'];
        $this->contextid = $feedbackdata['contextid'];
        // Check if all required data are provided. If not, load them.
        if (!$this->has_required_context_keys($feedbackdata)) {
            return $this->no_data();
        }

        $feedbackdata = $this->apply_settings_to_feedbackdata($feedbackdata);
        if (!$feedbackdata) {
            return $this->no_data();
        }

        $studentfeedback = $this->get_studentfeedback($feedbackdata);
        if (! $this->isvalidfeedback($studentfeedback)) {
            $studentfeedback = $this->no_data();
        }

        $teacherfeedback = [];
        if ($this->has_teacherfeedbackpermission()) {
            $teacherfeedback = $this->get_teacherfeedback($feedbackdata);
        }
        if (! $this->isvalidfeedback($teacherfeedback)) {
            $teacherfeedback = $this->no_data();
        }

        return [
            'studentfeedback' => $studentfeedback,
            'teacherfeedback' => $teacherfeedback,
        ];
    }

    /**
     * Set new keys in personabilities array to define scales selected and excluded for report.
     *
     * @param array $newdata
     * @param feedbacksettings $feedbacksettings
     * @param int $strategyid
     * @param int $forcedscaleid
     * @param bool $feedbackonlyfordefinedscaleid
     *
     * @return array
     *
     */
    public function select_scales_for_report(
        array $newdata,
        feedbacksettings $feedbacksettings,
        int $strategyid,
        int $forcedscaleid = 0,
        bool $feedbackonlyfordefinedscaleid = false
        ): array {
            $quizsettings = $newdata['progress']->get_quiz_settings();

        $transformedpersonabilities = $newdata['updated_personabilities'];

        $transformedpersonabilities = $feedbacksettings->filter_excluded_scales($transformedpersonabilities, $quizsettings);

        $feedbacksettings->set_params_from_attempt($newdata, $quizsettings);

        return info::get_teststrategy($strategyid)
        ->select_scales_for_report(
            $feedbacksettings,
            $transformedpersonabilities,
            $newdata
        );
    }

    /**
     * Write information about colorgradient for colorbar.
     *
     * @param array $quizsettings
     * @param float $personability
     * @param int $catscaleid
     * @return string
     *
     */
    public function get_color_for_personability(array $quizsettings, float $personability, int $catscaleid): string {
        $default = LOCAL_CATQUIZ_DEFAULT_GREY;
        $abilityrange = $this->get_ability_range($catscaleid);
        if (!$quizsettings ||
            $personability < (float) $abilityrange['minscalevalue'] ||
            $personability > (float) $abilityrange['maxscalevalue']) {
            return $default;
        }
        $numberoffeedbackoptions = intval($quizsettings['numberoffeedbackoptionsselect'])
            ?? LOCAL_CATQUIZ_MAX_SCALERANGE;
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);

        for ($i = 1; $i <= $numberoffeedbackoptions; $i++) {
            $rangestartkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_" . $i;
            $rangeendkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $i;
            $rangestart = floatval($quizsettings[$rangestartkey]);
            $rangeend = floatval($quizsettings[$rangeendkey]);

            if ($personability >= $rangestart && $personability <= $rangeend) {
                $colorkey = 'wb_colourpicker_' . $catscaleid . '_' . $i;
                $colorname = $quizsettings[$colorkey];
                return $colorarray[$colorname];
            }

        }
        return $default;
    }

    /**
     * For testing this is called in seperate function.
     *
     * @param mixed $catscaleid
     *
     * @return array
     *
     */
    public function get_ability_range($catscaleid): array {
        $cs = new catscale($catscaleid);
        // Ability range is the same for all scales with same root scale.
        return $cs->get_ability_range();
    }

    /**
     * Returns a fallback if no feedback can be generated.
     *
     * @return array
     * @throws coding_exception
     */
    protected function no_data(): array {
        return [
            'heading' => $this->get_heading(),
            'content' => get_string('attemptfeedbacknotavailable', 'local_catquiz'),
        ];
    }

    /**
     * Has required context keys.
     *
     * @param mixed $context
     *
     * @return mixed
     *
     */
    private function has_required_context_keys($context) {
        foreach ($this->get_required_context_keys() as $key) {
            if (!array_key_exists($key, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Make sure that the feedbackgenerator returned the expected data.
     *
     * @param array $feedback
     * @return bool
     */
    private function isvalidfeedback(array $feedback): bool {
        // Allow empty array.
        if ($feedback === []) {
            return true;
        }
        $expectedelements = ['heading', 'content'];
        foreach ($expectedelements as $elem) {
            if (!array_key_exists($elem, $feedback)) {
                global $CFG;
                if ($CFG->debug > 0) {
                    throw new UnexpectedValueException('Data returned by feedbackgenerator is missing the value for ' . $elem);
                }
                return false;
            }
        }
        return true;
    }
    /**
     * Has teacherfeedbackpermission.
     *
     * @return bool
     *
     */
    private function has_teacherfeedbackpermission(): bool {
        return has_capability(
            'local/catquiz:view_teacher_feedback', context_system::instance()
        );
    }

}
