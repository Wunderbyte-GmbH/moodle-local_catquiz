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
 * @package     catmodel_pcmgeneralized
 * @category    admin
 * @copyright   Wunderbyte Gmbh 2023 <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$componentname = 'catmodel_pcmgeneralized';

if ($hassiteconfig) {
    if ($ADMIN->fulltree) {
        $page->add(
            new admin_setting_configtext(
                $componentname . '/trusted_region_min_a',
                get_string('trusted_region_min_a', $componentname),
                get_string('trusted_region_min_a_desc', $componentname),
                -10.0,
                PARAM_FLOAT,
                2
            )
        );
        $page->add(
            new admin_setting_configtext(
                $componentname . '/trusted_region_max_a',
                get_string('trusted_region_max_a', $componentname),
                get_string('trusted_region_max_a_desc', $componentname),
                10.0,
                PARAM_FLOAT,
                2
            )
        );
        $page->add(
            new admin_setting_configtext(
                $componentname . '/trusted_region_factor_sd_a',
                get_string('trusted_region_factor_sd_a', $componentname),
                get_string('trusted_region_factor_sd_a_desc', $componentname),
                3.0,
                PARAM_FLOAT,
                2
            )
        );
        $page->add(
            new admin_setting_configtext(
                $componentname . '/trusted_region_placement_b',
                get_string('trusted_region_placement_b', $componentname),
                get_string('trusted_region_placement_b_desc', $componentname),
                3.0,
                PARAM_FLOAT,
                2
            )
        );
        $page->add(
            new admin_setting_configtext(
                $componentname . '/trusted_region_slope_b',
                get_string('trusted_region_slope_b', $componentname),
                get_string('trusted_region_slope_b_desc', $componentname),
                3.0,
                PARAM_FLOAT,
                2
            )
        );
        $page->add(
            new admin_setting_configtext(
                $componentname . '/trusted_region_factor_max_b',
                get_string('trusted_region_factor_max_b', $componentname),
                get_string('trusted_region_factor_max_b_desc', $componentname),
                3.0,
                PARAM_FLOAT,
                2
            )
        );
        $page->add(
            new admin_setting_configtext(
                $componentname . '/trusted_region_min_b',
                get_string('trusted_region_min_b', $componentname),
                get_string('trusted_region_min_b_desc', $componentname),
                -3.0,
                PARAM_FLOAT,
                2
            )
        );
        $page->add(
            new admin_setting_configtext(
                $componentname . '/trusted_region_max_b',
                get_string('trusted_region_max_b', $componentname),
                get_string('trusted_region_max_b_desc', $componentname),
                3.0,
                PARAM_FLOAT,
                2
            )
        );
    }
}
