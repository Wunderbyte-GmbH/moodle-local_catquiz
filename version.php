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
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_catquiz
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_catquiz';
// Moodle 4.5 only. The upper bound used to say 500, which declares support for a
// release line this plugin has not been verified against - the CI matrix builds
// MOODLE_405_STABLE, and nothing here has been run on 5.x. Declaring support that
// was never tested is a promise to administrators that the code does not keep.
// Moodle 5.x is a work package of its own.
$plugin->supported = [405, 405];
$plugin->release = '1.2.0';
$plugin->version = 2026090540;
$plugin->requires = 2024100700;
$plugin->maturity = MATURITY_STABLE;
$plugin->dependencies = [
    'local_wunderbyte_table' => 2024040200,
    'mod_adaptivequiz' => 2026081900,
    'adaptivequizcatmodel_catquiz' => 2026081900,
];
