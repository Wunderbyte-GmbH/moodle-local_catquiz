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

namespace local_catquiz\teststrategy\strategy;

use local_catquiz\catscale;
use local_catquiz\local\result;
use local_catquiz\teststrategy\info;
use local_catquiz\teststrategy\item_score_modifier;
use moodle_exception;

/**
 * Base class for test strategies.
 */
abstract class teststrategy {


    /**
     *
     * @var int $id // strategy id defined in lib.
     */
    public int $id = 0; // Administrativ.


    /**
     *
     * @var int $id scaleid.
     */
    public int $scaleid;

    /**
     *
     * @var int $catcontextid
     */
    public int $catcontextid;

    /**
     * @var array<item_score_modifier>
     */
    public array $score_modifiers;

    public function __construct() {
        $this->score_modifiers = info::get_score_modifiers();
    }

    /**
     * Returns an array of score modifier classes
     * 
     * The classes will be called in the given order to calculate the score of a question
     * 
     * @return array 
     */
    abstract public function requires_score_modifiers(): array;

    /**
     * Returns the translated description of this strategy
     *
     * @return string
     */
    public function get_description(): string {

        $classname = get_class($this);

        $parts = explode('\\', $classname);
        $classname = array_pop($parts);
        return get_string($classname, 'local_catquiz');
    }

    /**
     * Strategy specific way of returning the next testitem.
     *
     * @return object
     */
    public function return_next_testitem(array $context) {
        $now = time();

        foreach ($this->requires_score_modifiers() as $modifier) {
            // if is in array
            if (!array_key_exists($modifier, $this->score_modifiers)) {
                throw new moodle_exception(
                    sprintf(
                        'Strategy requires a score modifier that is not available: %s'
                    )
                );
            }
            if (! $this->score_modifiers[$modifier] instanceof item_score_modifier) {
                throw new moodle_exception(
                    sprintf('Class %s does not implement the item_score_modifier interface', $modifier)
                );
            }
            $result = $this->score_modifiers[$modifier]->update_score($context);

            if ($result->isErr()) {
                return $result;
            }

            $context = $result->unwrap();
        }

        // Select the question with the maximum score
        $selected_question = $context['questions'][array_keys($context['questions'])[0]];
        $selected_question->lastattempttime = $now;
        catscale::update_testitem(
            $this->catcontextid,
            $selected_question,
            $context['includesubscales']
        );
        return result::ok($selected_question);
    }

    /**
     * Retrieves all the available testitems from the current scale.
     *
     * @param int  $catscaleid
     * @param bool $includesubscales
     * @return array
     */
    public function get_all_available_testitems(int $catscaleid, bool $includesubscales = false):array {

        $catscale = new catscale($catscaleid);

        return $catscale->get_testitems($this->catcontextid, $includesubscales);

    }

    /**
     * Set catscale id.
     * @param int $scaleid
     * @return self
     */
    public function set_scale(int $scaleid) {
        $this->scaleid = $scaleid;
        return $this;
    }

    /**
     * Set the CAT context id
     * @param int $catcontextid
     * @return $this
     */
    public function set_catcontextid(int $catcontextid) {
        $this->catcontextid = $catcontextid;
        return $this;
    }
}
