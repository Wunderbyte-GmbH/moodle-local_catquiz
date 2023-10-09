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

namespace local_catquiz\output\catscalemanager\questions\cards;

use context_system;
use qbank_previewquestion\question_preview_options;
use question_bank;
use question_display_options;
use question_engine;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->libdir . '/questionlib.php');


/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer, Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionpreview {

    /**
     * @var object
     */
    private object $record;

    /**
     * Constructor
     *
     * @param object $record
     *
     */
    public function __construct(object $record) {

        $this->record = $record;
    }


    /**
     * Renders preview of testitem (question).
     *
     * @return array
     *
     */
    public function render_questionpreview() {

        $title = get_string('questionpreview', 'local_catquiz');
        $record = $this->record;

        $id = $record->id;
        $question = question_bank::load_question($id);

        $quba = question_engine::make_questions_usage_by_activity(
            'local_catquiz', context_system::instance());

        $options = new question_preview_options((object)$question);
        $options->feedback = question_display_options::HIDDEN;
        $options->generalfeedback = question_display_options::HIDDEN;
        $options->flags = question_display_options::HIDDEN;
        $options->numpartscorrect = question_display_options::HIDDEN;
        $options->generalfeedback = question_display_options::HIDDEN;
        $options->rightanswer = question_display_options::HIDDEN;
        $options->manualcomment = question_display_options::HIDDEN;
        $options->history = question_display_options::HIDDEN;
        $options->marks = question_display_options::HIDDEN;
        $options->readonly = false; // User can choose options. Set false for display only.
        $quba->set_preferred_behaviour($options->behaviour);
        $quba->add_question($question);

        $slot = $quba->add_question($question, $options->maxmark);

        $quba->start_all_questions();

        $previewdata['question'] = $quba->render_question($slot, $options);

        return [
            'title' => $title,
            'body' => $previewdata,
        ];
    }

}
