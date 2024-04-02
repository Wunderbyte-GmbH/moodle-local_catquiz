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

namespace local_catquiz\output\catscalemanager\testsandtemplates;

use html_writer;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\output\catscalemanager\scaleandcontexselector;
use local_catquiz\output\testenvironmentdashboard;
use local_catquiz\table\catscalequestions_table;
use moodle_url;

/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testsandtemplatesdisplay {


    /**
     * @var int
     */
    private int $scaleid = 0; // The selected scale.

    /**
     * @var int
     */
    private int $usesubs = 1; // If subscales should be integrated in question display, value is 1.

    /**
     * @var string
     */
    private string $componentname = 'question'; // Componentname of the testitem.

    /**
     * Constructor.
     *
     * @param int $catscaleid
     * @param int $usesubs
     * @param string $componentname
     *
     */
    public function __construct(int $catscaleid = 0, int $usesubs = 1, string $componentname = 'question') {
        $this->scaleid = $catscaleid;
        $this->usesubs = $usesubs;
        $this->componentname = $componentname;
    }

    /**
     * Renders table.
     *
     * @return mixed
     *
     */
    private function render_table() {

        $testenvironmentdashboard = new testenvironmentdashboard();
        return $testenvironmentdashboard->testenvironmenttable($this->scaleid);
    }

    /**
     * Return the item tree of all catscales.
     * @return array
     */
    public function export_data_array(): array {

        $selector = scaleandcontexselector::render_rootscaleselector($this->scaleid);
        $data = [
            'scaleselectors' => $selector ?? "",
            'table' => $this->render_table(),

        ];

        return $data;
    }
}
