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
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy;

use coding_exception;
use context_system;
use UnexpectedValueException;

/**
 * Classes of this type return feedback for a quiz attempt.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class feedbackgenerator {
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
     * Loads the data required to render the feedback.
     *
     * @param int $attemptid
     * @param array $initialcontext
     * @return ?array
     */
    abstract public function load_data(int $attemptid, array $initialcontext): ?array;

    /**
     * Returns the feedback as an array with elements 'heading' and 'feedback'.
     *
     * @param array $context
     * @return array
     * @throws coding_exception
     * @throws UnexpectedValueException
     */
    public function get_feedback(array $context): array {
        // Check if all required data are provided. If not, load them.
        if (!$this->has_required_context_keys($context)) {
            return $this->no_data();
        }

        $studentfeedback = $this->get_studentfeedback($context);
        if (! $this->isvalidfeedback($studentfeedback)) {
            $studentfeedback = $this->no_data();
        }

        $teacherfeedback = [];
        if ($this->has_teacherfeedbackpermission()) {
            $teacherfeedback = $this->get_teacherfeedback($context);
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
    private function has_teacherfeedbackpermission(): bool {
        return has_capability(
            'local/catquiz:view_teacher_feedback', context_system::instance()
        );
    }

 }
