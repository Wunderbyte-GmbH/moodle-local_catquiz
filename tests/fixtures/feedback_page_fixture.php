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

namespace local_catquiz\tests;

use local_catquiz\teststrategy\progress;

/**
 * Completed CAT attempts as the result page and the shortcodes read them (issue #85).
 *
 * A scale, a test in a course and, per attempt, the activity's attempt, the stored CAT attempt with its
 * feedback data and the persisted progress - what an attempt leaves behind once it is finished.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait feedback_page_fixture {
    /** @var int */
    protected int $fixturecontextid;

    /** @var int */
    protected int $fixturescaleid;

    /** @var \stdClass */
    protected \stdClass $fixturecourse;

    /** @var \stdClass adaptivequiz instance */
    protected \stdClass $fixturequiz;

    /** @var array CAT settings of the test. */
    protected array $fixturesettings;

    /**
     * Scale, course, test.
     *
     * @param int $groupmode Group mode of course and test, forced.
     * @return void
     */
    protected function create_feedback_scope(int $groupmode = SEPARATEGROUPS): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $now = time();
        $this->fixturecontextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Feedback', 'description' => '', 'descriptionformat' => FORMAT_HTML, 'starttimestamp' => 0,
            'endtimestamp' => $now + 100000, 'timecreated' => $now, 'timemodified' => $now, 'usermodified' => 0,
        ]);
        $this->fixturescaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0, 'name' => 'Global', 'contextid' => $this->fixturecontextid,
            'minscalevalue' => -5, 'maxscalevalue' => 5, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        \cache_helper::purge_all();
        $this->fixturecourse = $this->getDataGenerator()->create_course(['groupmode' => $groupmode, 'groupmodeforce' => 1]);
        $this->fixturequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $this->fixturecourse->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE], 'groupmode' => $groupmode,
        ]);
        $scale = $this->fixturescaleid;
        $this->fixturesettings = [
            'catquiz_catscales' => $scale, 'catquiz_selectteststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'numberoffeedbackoptionsselect' => 2, 'name' => 'Test', 'modulename' => 'mod_adaptivequiz',
            "feedback_scaleid_limit_lower_{$scale}_1" => -5, "feedback_scaleid_limit_upper_{$scale}_1" => 0,
            "feedback_scaleid_limit_lower_{$scale}_2" => 0, "feedback_scaleid_limit_upper_{$scale}_2" => 5,
            "wb_colourpicker_{$scale}_1" => '3', "wb_colourpicker_{$scale}_2" => '6',
        ];
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => $this->fixturequiz->id, 'component' => 'mod_adaptivequiz', 'catscaleid' => $scale,
            'courseid' => $this->fixturecourse->id, 'name' => 'Test', 'status' => 1,
            'json' => json_encode($this->fixturesettings), 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * A completed attempt of a user, with its stored feedback data and progress.
     *
     * @param \stdClass $user
     * @param int $endtime
     * @param float $ability
     * @return int adaptivequiz_attempt.id
     */
    protected function create_completed_attempt(\stdClass $user, int $endtime, float $ability = 0.5): int {
        global $DB, $USER;

        $scale = $this->fixturescaleid;
        $adaptiveattemptid = (int) $DB->insert_record('adaptivequiz_attempt', (object) [
            'instance' => $this->fixturequiz->id, 'userid' => $user->id, 'uniqueid' => 0, 'attemptstate' => 'complete',
            'attemptstopcriteria' => '', 'questionsattempted' => 3, 'difficultysum' => 0, 'standarderror' => 0,
            'measure' => 0, 'timecreated' => $endtime - 300, 'timemodified' => $endtime,
        ]);
        $json = [
            'attemptid' => $adaptiveattemptid, 'contextid' => $this->fixturecontextid,
            'courseid' => (int) $this->fixturecourse->id, 'userid' => (int) $user->id,
            'teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, 'catscaleid' => $scale, 'component' => 'mod_adaptivequiz',
            'testid' => (int) $this->fixturequiz->id, 'starttime' => $endtime - 300, 'endtime' => $endtime,
            'personabilities' => [$scale => $ability], 'se' => [$scale => 0.3], 'nbyscale' => [$scale => 3],
            'customscalefeedback_abilities' => [
                $scale => ['value' => $ability, 'toreport' => true, 'primary' => true, 'name' => 'Global'],
            ],
            'catscales' => [
                $scale => ['id' => $scale, 'name' => 'Global', 'parentid' => 0, 'contextid' => $this->fixturecontextid],
            ],
            'studentfeedback' => [], 'teacherfeedback' => [],
        ];
        $DB->insert_record('local_catquiz_attempts', (object) [
            'userid' => $user->id, 'scaleid' => $scale, 'contextid' => $this->fixturecontextid,
            'courseid' => $this->fixturecourse->id, 'attemptid' => $adaptiveattemptid, 'component' => 'mod_adaptivequiz',
            'instanceid' => $this->fixturequiz->id, 'teststrategy' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB, 'status' => 0,
            'starttime' => $endtime - 300, 'endtime' => $endtime, 'personability_after_attempt' => $ability,
            'json' => json_encode($json), 'debug_info' => '', 'timecreated' => $endtime - 300, 'timemodified' => $endtime,
        ]);
        // The progress is persisted for its owner, as the attempt left it.
        $previous = $USER;
        $this->setUser($user);
        progress::load($adaptiveattemptid, 'mod_adaptivequiz', $this->fixturecontextid, (object) $this->fixturesettings)->save();
        $this->setUser($previous);
        return $adaptiveattemptid;
    }

    /**
     * Forgets everything the fixture left in caches, so a measurement starts cold.
     *
     * @return void
     */
    protected function forget_feedback_caches(): void {
        \cache_helper::purge_all();
        \local_catquiz\local\access\context_resolver::reset_cache();
        \local_catquiz\local\statistics\eligible_population::reset();
    }
}
