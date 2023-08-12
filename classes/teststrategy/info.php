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
 * Class info.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy;

use core_component;
use local_catquiz\teststrategy\context\contextcreator;
use MoodleQuickForm;

/**
 * Base class for test strategies.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class info {

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = 0; // Administrativ.


    /**
     * Instantioate parameters.
     */
    public function __construct() {

    }

    /**
     * Returns the active test strategy
     *
     * @param int $id
     * @return strategy
     */
    public function return_active_strategy(int $id) {

        global $CFG;

        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $strategies = self::return_available_strategies();

        foreach ($strategies as $strategy) {

            if (isset($strategy->id) && $strategy->id === $id) {
                return $strategy;
            }
        }

        // If we come here, we will just return the first strategy.
        return reset($strategies);
    }

    /**
     * Returns all test strategies
     *
     * @return strategy[]
     */
    public static function return_available_strategies() {

        global $CFG;

        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $strategies = core_component::get_component_classes_in_namespace(
            "local_catquiz",
            'teststrategy\strategy'
        );

        $classnames = array_keys($strategies);

        return array_map(fn($x) => new $x(), $classnames);
    }

    /**
     * Add strategy specific items to mform.
     *
     * @param MoodleQuickForm $mform
     * @param array $elements
     * @return void
     */
    public static function instance_form_definition(MoodleQuickForm &$mform, array &$elements) {
        // Add a special header for catquiz.
        $elements[] = $mform->addElement('header', 'catquiz_teststrategy',
                get_string('catquiz_teststrategyheader', 'local_catquiz'));
        $mform->setExpanded('catquiz_teststrategy');

        $teststrategies = self::return_available_strategies();

        $teststrategiesoptions = [];
        $strategieswithoutpilotquestions = [];
        foreach ($teststrategies as $ts) {
            $teststrategiesoptions[$ts->id] = $ts->get_description();
            if ($ts->get_description() === get_string('teststrategy_fastest', 'local_catquiz')) {
                $strategieswithoutpilotquestions[] = $ts->id;
            }
        }

        // Choose a test strategy for this instance.
        $elements[] = $mform->addElement('select', 'catquiz_selectteststrategy',
            get_string('catquiz_selectteststrategy', 'local_catquiz'),
            $teststrategiesoptions
        );

        $elements[] = $mform->addElement('advcheckbox', 'catquiz_includepilotquestions', get_string('includepilotquestions', 'local_catquiz'));
        $mform->hideIf('catquiz_includepilotquestions', 'catquiz_selectteststrategy', 'eq', $strategieswithoutpilotquestions);
        // Add ratio of pilot questions.
        $elements[] = $mform->addElement('text', 'catquiz_pilotratio', get_string('pilotratio', 'local_catquiz'));
        $mform->hideIf('catquiz_pilotratio', 'catquiz_includepilotquestions', 'neq', 1);
        $mform->hideIf('catquiz_pilotratio', 'catquiz_selectteststrategy', 'eq', $strategieswithoutpilotquestions);
        $mform->setType('catquiz_pilotratio', PARAM_FLOAT);
        $mform->addHelpButton('catquiz_pilotratio', 'pilotratio', 'local_catquiz');

        $elements[] = $mform->addElement('select', 'catquiz_selectfirstquestion',
            get_string('catquiz_selectfirstquestion', 'local_catquiz'),
            [
                'startwitheasiestquestion' => get_string('startwitheasiestquestion', 'local_catquiz'),
                'startwithfirstofsecondquintil' => get_string('startwithfirstofsecondquintil', 'local_catquiz'),
                'startwithfirstofsecondquartil' => get_string('startwithfirstofsecondquartil', 'local_catquiz'),
                'startwithmostdifficultsecondquartil' => get_string('startwithmostdifficultsecondquartil', 'local_catquiz'),
                'startwithaverageabilityoftest' => get_string('startwithaverageabilityoftest', 'local_catquiz'),
                'startwithcurrentability' => get_string('startwithcurrentability', 'local_catquiz'),
            ]
        );
    }

    /**
     * Returns score modifier functions
     *
     * @return contextcreator
     */
    public static function get_contextcreator(): contextcreator {
        $contextloaders = core_component::get_component_classes_in_namespace(
            "local_catquiz",
            'teststrategy\context\loader'
        );
        $classnames = array_keys($contextloaders);

        $contextloaders = array_map(fn($x) => new $x(), $classnames);

        return new contextcreator($contextloaders);
    }

    /**
     * Returns score modifier functions
     *
     * @return array<preselect_task>
     */
    public static function get_score_modifiers(): array {
        $scoremodifiers = core_component::get_component_classes_in_namespace(
            "local_catquiz",
            'teststrategy\preselect_task'
        );
        foreach (array_keys($scoremodifiers) as $classname) {
            $instances[$classname] = new $classname();
        }
        return $instances;
    }
}
