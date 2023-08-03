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

namespace local_catquiz\output;

use local_catquiz\catmodel_info;
use local_catquiz\catquiz;
use templatable;
use renderable;

/**
 * Renderable class for student details
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class studentdetails implements renderable, templatable {

    /**
     * @var int
     */
    private int $testitemid;

    /**
     * @var int
     */
    private int $catcontext;

    /**
     * @var int
     */
    private int $scale;

    /**
     * @var int
     */
    private int $subscale;


    /**
     * @param int $testitemid
     * @param int $catcontext
     * @param int $testitemid
     * @param int $testitemid
     */
    public function __construct(int $testitemid, int $catcontext, int $scale, int $subscale) {

    }

    /**
     * Render the student details
     *
     * @return array
     */
    private function render_testitemdetailview() {

    }

    /**
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        return [
            'testitemdetailview' => $this->render_testitemdetailview(),
        ];
    }
}
