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

use local_catquiz\catscale;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/lib.php');

/**
 * Class feedbacksettings teststrategy and feedbackgenerator.
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedbacksettings {
    /** The scale for which detailed feedback will be displayed. Can be a single scaleid or an array of scales.
     * @var int
     */
    public $primaryscaleid;

    /** Selects the scale to which the feedback values of the other scales refer to.
     * @var string
     */
    public $scaleresult;

    /**
     * @var boolean
     */
    public $displayscaleswithoutitemsplayed = false;

    /**
     * @var boolean
     */
    public $overridesettings = true;

    /**
     * @var int
     */
    public $sortorder;


    /**
     * Constructor for feedbackclass.
     *
     * @param int $primaryscaleid
     * @param int $scaleresult
     */
    public function __construct($primaryscaleid = LOCAL_CATQUIZ_PRIMARYCATSCALE_PARENT, $scaleresult = 0) {
        $this->primaryscaleid = $primaryscaleid;

        if (!isset($scaleresult)) {
            $this->scaleresult = $this->primaryscaleid;
        } else {
            $this->scaleresult = $scaleresult;
        }

        // Default sortorder is descendent.
        $this->sortorder = LOCAL_CATQUIZ_SORTORDER_DESC;
    }
}
