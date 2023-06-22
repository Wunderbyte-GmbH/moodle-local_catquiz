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

namespace local_catquiz\teststrategy;

use core_component;
use local_catquiz\catscale;
use local_catquiz\teststrategy\context\contextcreator;
use MoodleQuickForm;

/**
 * Base class for test strategies.
 */
class info {

    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = 0; // Administrativ.


    public function __construct() {

    }

    /**
     * Returns the active test strategy
     *
     * @param int $id
     * @return teststrategy
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
     * Undocumented function
     *
     * @param $includingadminstrativ
     * @return teststrategy[]
     */
    public static function return_available_strategies(bool $includingadminstrativ = false) {

        global $CFG;

        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $strategies = core_component::get_component_classes_in_namespace(
            "local_catquiz",
            'teststrategy\strategy'
        );

        $classnames = array_keys($strategies);

        $strategies = array_map(fn($x) => new $x(), $classnames);

        if ($includingadminstrativ) {
            return $strategies;
        } else {
            return array_filter($strategies, fn($x) => !empty($x->id));
        }
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

        // TODO: get them dynamically.
        $teststrategies = self::return_available_strategies();

        $teststrategiesoptions = [];
        foreach($teststrategies as $ts) {
            $teststrategiesoptions[$ts->id] = $ts->get_description();
        }

        // Choose a test strategy for this instance.
        $elements[] =  $mform->addElement('select', 'catquiz_selectteststrategy',
        get_string('catquiz_selectteststrategy', 'local_catquiz'), $teststrategiesoptions);

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

    public static function get_score_modifiers(): array {
        $score_modifiers = core_component::get_component_classes_in_namespace(
            "local_catquiz",
            'teststrategy\item_score_modifier'
        );
        foreach ($score_modifiers as $classname => $namespace) {
            $instances[str_replace($namespace[0], "", $classname)] = new $classname();
        }
        return $instances;
    }
}