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
 * @package     catmodel_web_rasch
 * @category    admin
 * @copyright   Wunderbyte Gmbh 2023 <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$componentname = 'catmodel_web_rasch';

if ($hassiteconfig) {
    if ($ADMIN->fulltree) {

        $page->add(
            new admin_setting_configtext(
                $componentname . '/hostname',
                get_string('hostname', $componentname),
                get_string('hostname_desc', $componentname),
                'http://localhost',
                PARAM_TEXT));

        $page->add(
            new admin_setting_configtext(
                $componentname . '/port',
                get_string('port', $componentname),
                get_string('port_desc', $componentname),
                '9090',
                PARAM_INT));
    }
}
