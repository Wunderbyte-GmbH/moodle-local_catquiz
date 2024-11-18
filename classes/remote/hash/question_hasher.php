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

namespace local_catquiz\remote\hash;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles question hash generation and verification.
 * 
 * Idea: Include model name and scale in calculation of hash.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_hasher {

    /**
     * Generate a hash for a question.
     *
     * @param int $questionid The question ID
     * @return string The generated hash
     * @throws \moodle_exception
     */
    public static function generate_hash($questionid) {
        global $DB;

        // Get question data.
        $question = $DB->get_record('question', ['id' => $questionid]);
        if (!$question) {
            throw new \moodle_exception('questionnotfound', 'local_catquiz');
        }

        // Get question answers.
        $answers = $DB->get_records('question_answers', ['question' => $questionid], 'id ASC');

        // Build hash data array.
        $hashdata = [
            'questiontext' => $question->questiontext,
            'questiontextformat' => $question->questiontextformat,
            'generalfeedback' => $question->generalfeedback,
            'defaultmark' => $question->defaultmark,
            'penalty' => $question->penalty,
            'answers' => [],
        ];

        foreach ($answers as $answer) {
            $hashdata['answers'][] = [
                'answertext' => $answer->answer,
                'fraction' => $answer->fraction,
                'feedback' => $answer->feedback,
            ];
        }

        // Store hash data for verification.
        $record = new \stdClass();
        $record->questionid = $questionid;
        $record->hashdata = json_encode($hashdata);
        $record->questionhash = hash('sha256', $record->hashdata);
        $record->timecreated = time();
        $record->timemodified = $record->timecreated;

        // Store or update hash mapping.
        if ($existing = $DB->get_record('local_catquiz_question_hashmap', ['questionid' => $questionid])) {
            $record->id = $existing->id;
            $DB->update_record('local_catquiz_question_hashmap', $record);
        } else {
            $DB->insert_record('local_catquiz_question_hashmap', $record);
        }

        return $record->questionhash;
    }

    /**
     * Get local question ID from hash.
     *
     * @param string $hash The question hash
     * @return int|null The local question ID or null if not found
     */
    public static function get_questionid_from_hash($hash) {
        global $DB;

        if ($record = $DB->get_record('local_catquiz_question_hashmap', ['questionhash' => $hash])) {
            return $record->questionid;
        }
        return null;
    }

    /**
     * Verifies if a question still matches its stored hash.
     *
     * @param int $questionid The question ID to verify
     * @return bool True if hash matches or question has no hash yet
     */
    public static function verify_hash($questionid) {
        global $DB;

        $currenthash = $DB->get_record('local_catquiz_question_hashmap', ['questionid' => $questionid]);
        if (!$currenthash) {
            return true; // No hash exists yet.
        }

        $newhash = self::generate_hash($questionid);
        return $currenthash->questionhash === $newhash;
    }
}
