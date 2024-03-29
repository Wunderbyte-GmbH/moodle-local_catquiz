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

namespace local_catquiz\output;

use templatable;
use renderable;

/**
 * Renderable class for the breakinfo page
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Szkiba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class breakinfo implements renderable, templatable {

    /**
     * Cmid
     *
     * @var int $cmid
     */
    public int $cmid;

    /**
     *
     * @var int $breakend
     */
    public int $breakend;

    /**
     * Constructor.
     *
     * @param int $cmid
     * @param int $breakend
     *
     */
    public function __construct(int $cmid, int $breakend) {
        $this->cmid = $cmid;
        $this->breakend = $breakend;
    }

    /**
     * Return the breakinfo for the template.
     *
     * @param \renderer_base $output
     *
     * @return array
     *
     */
    public function export_for_template(\renderer_base $output): array {
        return [
            'cmid' => $this->cmid,
            'breakend' => date('H:i:s', $this->breakend),
        ];
    }
}
