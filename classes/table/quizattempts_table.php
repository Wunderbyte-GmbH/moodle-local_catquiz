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
 * Class quizattempts_table.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\table;

use local_catquiz\teststrategy\info;
use local_wunderbyte_table\wunderbyte_table;

/**
 * Lists catquiz attempts.
 *
 * @package local_catquiz
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizattempts_table extends wunderbyte_table {

    /**
     * Contains utl.
     *
     * @var mixed
     */
    private $url = null;

    /**
     * Acts like a cache and stores names of teststrategies.
     * @var array<string>
     */
    private array $teststrategynames = [];

    /**
     * Shows the username.
     *
     * @param mixed $values The row data.
     * @return mixed
     */
    public function col_name($values) {
        $name = $values->username ?? 'anonymous';
        return $name;
    }

    /**
     * Shows when this attempt was created in the database.
     *
     * @param \stdClass $values The row data.
     * @return string
     */
    public function col_timecreated($values) {
        return userdate($values->timecreated);
    }

    /**
     * Shows when this attempt was modified in the database.
     *
     * @param \stdClass $values The row data.
     * @return string
     */
    public function col_timemodified($values) {
        return userdate($values->timemodified);
    }

    /**
     * Shows the name of the teststrategy.
     *
     * @param \stdClass $values The row data.
     * @return string
     */
    public function col_teststrategy($values) {
        if (empty($this->teststrategynames[$values->teststrategy])) {
            $teststrategy = info::get_teststrategy($values->teststrategy);
            // Gets the unqualified classname without namespace.
            // See https://stackoverflow.com/a/27457689.
            $classname = substr(strrchr(get_class($teststrategy), '\\'), 1);
            $this->teststrategynames[$values->teststrategy] = get_string($classname, 'local_catquiz');
        }
        return $this->teststrategynames[$values->teststrategy];
    }

    /**
     * Shows when this attempt was started.
     *
     * @param \stdClass $values The row data.
     * @return string
     */
    public function col_starttime($values) {
        return userdate($values->starttime);
    }

    /**
     * Shows when this attempt ended.
     *
     * @param \stdClass $values The row data.
     * @return string
     */
    public function col_endtime($values) {
        return userdate($values->endtime);
    }

    /**
     * Shows the action column.
     *
     * @param \stdClass $values The row data.
     * @return string
     */
    public function col_action($values) {

        global $PAGE;

        $url = clone $PAGE->url;
        $url->params($_GET);

        $url = clone $this->url;
        $url->params(['attemptid' => $values->attemptid]);

        return sprintf(
            '<a class="btn btn-plain btn-smaller"
                href="%s#lcq_quizattempts"><i class="fa fa-cog" title="%s"></i>
            </a>',
            $url,
            get_string('cogwheeltitle', 'local_catquiz')
        );
    }
}
