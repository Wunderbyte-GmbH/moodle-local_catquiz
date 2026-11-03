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
 * This file contains the version information for the 1plraschbirnbaum model catquiz plugin
 *
 * @package catmodel_raschbirnbaum
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Issue #31: the subplugins of a line ask for the Moodle of that line, like local_catquiz itself. The
// version of the 1.3 line is above the one of the 1.2 line, so that an upgrade from 1.2 to 1.3 runs.
$plugin->version   = 2026101700;
$plugin->requires  = 2024100700;
$plugin->supported = [405, 405];
$plugin->incompatible = 500;
$plugin->release   = '1.2.1';
$plugin->maturity  = MATURITY_STABLE;
$plugin->component = 'catmodel_raschbirnbaum';
