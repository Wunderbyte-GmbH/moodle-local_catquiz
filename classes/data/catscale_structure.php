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

namespace local_catquiz\data;

use moodle_url;

/**
 *
 * Simple data structure reflecting the db table scheme. Should be same as in install.xml.
 */
class catscale_structure {

    /** @var ?int $id null means object is not yet initialised */
    public ?int $id = null;

    /** @var string $name */
    public string $name;

    /** @var string $description */
    public string $description;

    /** @var float $minscalevalue */
    public float $minscalevalue = 0;

    /** @var float $maxscalevalue */
    public float $maxscalevalue = 0;

    /** @var int $timecreated */
    public int $timecreated;

    /** @var int $timemodified */
    public int $timemodified;

    /** @var ?int $parentid */
    public ?int $parentid = null;

    /** @var ?string $viewlink */
    public ?string $viewlink = null;

    /**
     * Constructor for a single catscale data object.
     *
     * @param array $data
     */
    public function __construct(array $data) {
        if (!empty($data)) {
            // ID is only known after object has been saved to db.
            if (!empty($data['id'])) {
                $this->id = $data['id'];
            }
            $this->parentid = $data['parentid'];
            $this->timemodified = $data['timemodified'];
            $this->timecreated = $data['timecreated'];
            $this->name = $data['name'];
            $this->description = $data['description'];
            $this->minscalevalue = empty($data["minmaxgroup"]["catquiz_minscalevalue"]) ? 0 : $data["minmaxgroup"]["catquiz_minscalevalue"];
            $this->maxscalevalue = empty($data["minmaxgroup"]["catquiz_maxscalevalue"]) ? 0 : $data["minmaxgroup"]["catquiz_maxscalevalue"];

            $url = new moodle_url('/local/catquiz/edit_catscale.php', [
                'id' => $data['id']
            ]);

            // Add the link to the view php.
            $this->viewlink = $url->out();
        }
    }
}
