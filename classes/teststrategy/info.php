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

use cache;
use core_component;
use local_catquiz\feedback\feedbackclass;
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

        $cache = cache::make('local_catquiz', 'teststrategies');
        if ($strategies = $cache->get('all')) {
            return $strategies;
        }

        $strategies = core_component::get_component_classes_in_namespace(
            "local_catquiz",
            'teststrategy\strategy'
        );

        $classnames = array_keys($strategies);

        $strategies = array_map(fn($x) => new $x(), $classnames);

        $cache->set('all', $strategies);
        foreach ($strategies as $strategy) {
            $cache->set($strategy->id, $strategy);
        }
        return $strategies;
    }

    /**
     * Get test strategy.
     *
     * @param int $id
     *
     * @return mixed
     *
     */
    public static function get_teststrategy(int $id) {
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $cache = cache::make('local_catquiz', 'teststrategies');
        if ($strategy = $cache->get($id)) {
            return $strategy;
        }

        $strategy = array_filter(
                self::return_available_strategies(),
                fn ($strategy) => $strategy->id == $id
        );
        return reset($strategy);
    }

    /**
     * Add strategy specific items to mform.
     *
     * @param MoodleQuickForm $mform
     * @param array $elements
     * @return void
     */
    public static function instance_form_definition(MoodleQuickForm &$mform, array &$elements) {

        $data = $mform->getSubmitValues();
        $defaultvalues = $mform->_defaultValues;
        // Add a special header for catquiz.
        $elements[] = $mform->addElement('header', 'catquiz_teststrategy',
                get_string('catquiz_teststrategyheader', 'local_catquiz'));
        $mform->setExpanded('catquiz_teststrategy');

        $teststrategies = self::return_available_strategies();

        $teststrategiesoptions = [];
        $strategieswithoutpilotquestions = [];
        $strategyhasstandarderrorperscale = [];
        foreach ($teststrategies as $ts) {
            $teststrategiesoptions[$ts->id] = $ts->get_description();

            // Only for those strategies in the array, we want to show the standard error setting.
            if (!in_array($ts->id, [
                    LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
                    LOCAL_CATQUIZ_STRATEGY_HIGHESTSUB,
                    LOCAL_CATQUIZ_STRATEGY_ALLSUBS,
                    LOCAL_CATQUIZ_STRATEGY_FASTEST,
                    ])) {
                $strategyhasstandarderrorperscale[] = $ts->id;
            }
        }

        // Choose a test strategy for this instance.
        $elements[] = $mform->addElement('select', 'catquiz_selectteststrategy',
            get_string('catquiz_selectteststrategy', 'local_catquiz'),
            $teststrategiesoptions
        );

        $elements[] = $mform->addElement(
            'advcheckbox',
            'catquiz_includepilotquestions',
            get_string('includepilotquestions', 'local_catquiz'));
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
        // When a classical CAT is performed, we ignore the first-question
        // option and display all questions ordered by their ID.
        $mform->hideIf(
            'catquiz_selectfirstquestion',
            'catquiz_selectteststrategy',
            'eq',
            LOCAL_CATQUIZ_STRATEGY_CLASSIC
        );

        $maxquestionspertest = [
            $mform->createElement(
                'static',
                'catquiz_maxquestion_label_min',
                'catquiz_maxquestion_label_min',
                get_string('min', 'local_catquiz')
            ),
            $mform->createElement(
                'float',
                'catquiz_minquestions',
                'minquestions',
                ['size' => '3']
            ),
            $mform->createElement(
                'static',
                'catquiz_maxquestion_label_max',
                'catquiz_maxquestion_label_max',
                get_string('max', 'local_catquiz')
            ),
            $mform->createElement(
                'float',
                'catquiz_maxquestions',
                'maxquestions',
                ['size' => '3']
            ),
            ];
        $elements[] = $mform->addGroup(
            $maxquestionspertest,
            'maxquestionsgroup',
            get_string('numberofquestionspertest', 'local_catquiz'));
        $mform->addHelpButton('maxquestionsgroup', 'numberofquestionspertest', 'local_catquiz');

        $maxquestionsperscale = [
            $mform->createElement(
                'static',
                'catquiz_questionsperscale_label_min',
                'catquiz_questionsperscale_label_min',
                get_string('min', 'local_catquiz')
            ),
            $mform->createElement(
                'float',
                'catquiz_minquestionspersubscale',
                get_string('minquestionspersubscale', 'local_catquiz'),
                ['size' => '3']
            ),
            $mform->createElement(
                'static',
                'catquiz_questionsperscale_label_max',
                'catquiz_questionsperscale_label_max',
                get_string('max', 'local_catquiz')
            ),
            $mform->createElement(
                'float',
                'catquiz_maxquestionspersubscale',
                get_string('maxquestionspersubscale', 'local_catquiz'),
                ['size' => '3']
            ),
            ];
        $elements[] = $mform->addGroup(
            $maxquestionsperscale,
            'maxquestionsscalegroup',
            get_string('numberofquestionsperscale', 'local_catquiz'));
        $mform->addHelpButton('maxquestionsscalegroup', 'numberofquestionsperscale', 'local_catquiz');

        $standarderrorgroup = [
            $mform->createElement(
                'static',
                'catquiz_standarderror_label_min',
                'catquiz_standarderror_label_min',
                get_string('min', 'local_catquiz')
            ),
            $mform->createElement(
                'float',
                'catquiz_standarderror_min',
                'catquiz_standarderror_min',
                ['size' => '3']
            ),
            $mform->createElement(
                'static',
                'catquiz_standarderror_label_max',
                'catquiz_standarderror_label_max',
                get_string('max', 'local_catquiz')
            ),
            $mform->createElement(
                'float',
                'catquiz_standarderror_max',
                'catquiz_standarderror_max',
                ['size' => '3']
            ),
        ];

        if (!optional_param('catquiz_standarderror_min', 0, PARAM_FLOAT) &&
            !isset($defaultvalues['catquiz_standarderrorgroup']['catquiz_standarderror_min'])) {
                $mform->_defaultValues['catquiz_standarderrorgroup']['catquiz_standarderror_min'] =
                LOCAL_CATQUIZ_STANDARDERROR_DEFAULT_MIN;
        }
        if (!optional_param('catquiz_standarderror_max', 0, PARAM_FLOAT) &&
            !isset($defaultvalues['catquiz_standarderrorgroup']['catquiz_standarderror_max'])) {
                $mform->_defaultValues['catquiz_standarderrorgroup']['catquiz_standarderror_max'] =
                LOCAL_CATQUIZ_STANDARDERROR_DEFAULT_MAX;
        }

        $elements[] = $mform->addGroup(
            $standarderrorgroup,
            'catquiz_standarderrorgroup',
            get_string('acceptedstandarderror', 'local_catquiz'));
        $mform->addHelpButton('catquiz_standarderrorgroup', 'acceptedstandarderror', 'local_catquiz');

        $elements[] = $mform->addElement(
            'advcheckbox',
            'catquiz_includetimelimit',
            get_string('includetimelimit', 'local_catquiz'));
        $mform->addHelpButton('catquiz_includetimelimit', 'includetimelimit', 'local_catquiz');

        $timelimitgroup = [
            $mform->createElement(
                'static',
                'catquiz_timelabel_attempt',
                'catquiz_timelabel_attempt',
                get_string('perattempt', 'local_catquiz')
            ),
            $mform->createElement(
                'float',
                'catquiz_maxtimeperattempt',
                'catquiz_maxtimeperattempt',
                ['size' => '3']
            ),
            $mform->createElement('select',
                'catquiz_timeselect_attempt',
                "string",
                ['h' => "h", 'min' => 'min']),
            $mform->createElement(
                'static',
                'catquiz_timelabel_item',
                'catquiz_timelabel_item',
                get_string('peritem', 'local_catquiz')
            ),
            $mform->createElement(
                'float',
                'catquiz_maxtimeperitem',
                'catquiz_maxtimeperitem',
                ['size' => '3']
            ),
            $mform->createElement('select',
                'catquiz_timeselect_item',
                "string",
                ['min' => "min", 'sec' => 'sec']),
        ];
        $elements[] = $mform->addGroup(
            $timelimitgroup,
            'catquiz_timelimitgroup',
            get_string('maxtimeperquestion', 'local_catquiz'));
        $mform->setType('catquiz_maxtimeperattempt', PARAM_INT);
        $mform->setType('catquiz_maxtimeperitem', PARAM_INT);
        $mform->hideIf('catquiz_timelimitgroup', 'catquiz_includetimelimit', 'neq', 1);

        feedbackclass::instance_form_definition($mform, $elements);
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
        // Exclude classes that end with _testing.
        $classnames = array_filter(
            $classnames,
            fn ($classname) => ! self::is_testing_class($classname)
        );

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

    /**
     * Checks a classname to see if the class is used just for testing.
     * @param mixed $classname
     * @return bool
     */
    private static function is_testing_class($classname) {
        return substr($classname, -strlen('_testing')) === '_testing';
    }
}
