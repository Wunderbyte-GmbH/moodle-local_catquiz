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
     * @var string
     */
    public $primaryscale;

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
     *
     */
    public function __construct($primaryscalename = 'parent', $scaleresult = '') {

        if ($primaryscalename != 'parent') {
            $catscale = catscale::return_catscale_by_name($primaryscalename);
            $this->primaryscale = isset($catscale) ? $catscale->id : 0;
        } else {
            $this->primaryscale = 0;
        }

        if (!isset($scaleresult)) {
            $this->scaleresult = $this->primaryscale;
        } else {
            $catscale = catscale::return_catscale_by_name($scaleresult);
            $this->scaleresult = isset($catscale) ? $catscale->id : $this->primaryscale;
        }

        $this->sortorder = LOCAL_CATQUIZ_SORTORDER_DESC;
    }


}
