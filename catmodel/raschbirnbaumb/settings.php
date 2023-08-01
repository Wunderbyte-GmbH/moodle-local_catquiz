<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     catmodel_raschbirnbaumb
 * @category    admin
 * @copyright   Wunderbyte Gmbh 2023 <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$componentname = 'catmodel_raschbirnbaumb';

if ($hassiteconfig) {
    if ($ADMIN->fulltree) {
        $page->add(
            new admin_setting_configtext(
                $componentname . '/trusted_region_min',
                get_string('trusted_region_min', $componentname),
                get_string('trusted_region_min_desc', $componentname),
                -10.0,
                PARAM_FLOAT,
                2
            )
        );
        $page->add(
            new admin_setting_configtext(
                $componentname . '/trusted_region_max',
                get_string('trusted_region_max', $componentname),
                get_string('trusted_region_max_desc', $componentname),
                10.0,
                PARAM_FLOAT,
                2
            )
        );
    }
}