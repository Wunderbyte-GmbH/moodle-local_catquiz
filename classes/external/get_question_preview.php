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
 * Renders the preview of a single question on demand (issue #20).
 *
 * @package   local_catquiz
 * @copyright 2026 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');

/**
 * Returns the rendered text of one question.
 *
 * The question lists used to carry the full, formatted text of every
 * row - including base64 images - inside a hidden modal. That text was held in the
 * database result, in PHP strings, in the AJAX response and in the DOM at the same
 * time. The lists now select only short values, and the preview is fetched through
 * this endpoint when a row is actually clicked.
 *
 * @package   local_catquiz
 * @copyright 2026 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_question_preview extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionid' => new external_value(PARAM_INT, 'Question id'),
        ]);
    }

    /**
     * Returns the formatted question text.
     *
     * @param int $questionid
     * @return array
     */
    public static function execute(int $questionid): array {
        global $DB, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['questionid' => $questionid]);
        $questionid = $params['questionid'];

        // The question bank is managed site wide, so the CAT manager capability is
        // the right gate here - the same one that guards the pages these lists live
        // on. Without it there is no reason to hand out question content.
        $context = context_system::instance();
        self::validate_context($context);

        // Two ways to be allowed here, and they answer different questions.
        //
        // Managers may look at any question - that is what manage_catscales is for.
        //
        // Participants may look at the questions they were actually asked. Whether
        // the link is offered at all is decided by the feedback configuration of the
        // test: if the question list is not part of a learner's feedback, no preview
        // link is rendered. What this endpoint has to add is the object check - the
        // question id arrives from the client, and without it any authenticated user
        // could read the text of any question in the installation by guessing ids.
        if (!has_capability('local/catquiz:manage_catscales', $context)) {
            self::require_own_question($questionid);
        }

        $question = $DB->get_record(
            'question',
            ['id' => $questionid],
            'id, name, questiontext, questiontextformat',
            MUST_EXIST
        );

        // Files attached to a question live in the context of its question category,
        // not in the system context. Rewriting the URLs against the system context
        // produced links that resolve to nothing, so an image in a question text
        // simply did not appear - and a text-only preview never noticed.
        $filecontext = self::get_question_context($questionid) ?? $context;

        // Rewrite pluginfile URLs so images resolve, then format. This is the work
        // that used to happen for every row of every page; now it happens once, for
        // the one question the user asked to see.
        $text = question_rewrite_question_urls(
            $question->questiontext,
            'pluginfile.php',
            $filecontext->id,
            'question',
            'questiontext',
            [],
            $question->id
        );

        return [
            'questionid' => $question->id,
            'name' => format_string($question->name),
            'questiontext' => format_text(
                $text,
                $question->questiontextformat,
                ['context' => $filecontext]
            ),
        ];
    }

    /**
     * Returns the context a question's files belong to.
     *
     * Resolved through question_versions and question_bank_entries to the question
     * category, which carries the context id. Returns null when the chain cannot be
     * followed, so the caller can fall back rather than fail: a preview without
     * images is still useful, an exception is not.
     *
     * @param int $questionid
     * @return \context|null
     */
    private static function get_question_context(int $questionid): ?\context {
        global $DB;

        $contextid = $DB->get_field_sql(
            "SELECT qc.contextid
               FROM {question_versions} qv
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE qv.questionid = :questionid",
            ['questionid' => $questionid],
            IGNORE_MULTIPLE
        );

        if (!$contextid) {
            return null;
        }

        try {
            return \context::instance_by_id($contextid, IGNORE_MISSING) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questionid' => new external_value(PARAM_INT, 'Question id'),
            'name' => new external_value(PARAM_RAW, 'Question name'),
            'questiontext' => new external_value(PARAM_RAW, 'Formatted question text'),
        ]);
    }
    /**
     * Throws unless the current user was asked this question in one of their attempts.
     *
     * The check is against the recorded question attempts of this person, not against
     * the item pool: being in a scale the user may see is not the same as having been
     * asked, and only the latter is the user's own data.
     *
     * @param int $questionid
     * @throws moodle_exception
     * @return void
     */
    private static function require_own_question(int $questionid): void {
        global $DB, $USER;

        // The join runs through adaptivequiz_attempt on purpose:
        // local_catquiz_attempts.attemptid is the activity's attempt id, not the
        // question usage id. Joining it straight to question_attempts.questionusageid
        // matches nothing - verified against the data rather than assumed - and the
        // check would then refuse every participant.
        $sql = "SELECT 1
                  FROM {local_catquiz_attempts} lca
                  JOIN {adaptivequiz_attempt} aqa ON aqa.id = lca.attemptid
                  JOIN {question_attempts} qa ON qa.questionusageid = aqa.uniqueid
                 WHERE lca.userid = :userid
                   AND qa.questionid = :questionid";

        $params = ['userid' => $USER->id, 'questionid' => $questionid];

        if (!$DB->record_exists_sql($sql, $params)) {
            throw new moodle_exception('norighttoaccess', 'local_catquiz');
        }
    }
}
