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
 * Plugin strings are defined here.
 *
 * @package     local_catquiz
 * @category    string
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'ALiSe CAT Quiz';
$string['catquiz'] = 'Catquiz';

// Catquiz handler.
$string['catquizsettings'] = 'Cat quiz settings';
$string['selectmodel'] = 'Choose a model';
$string['model'] = 'Model';
$string['modeldeactivated'] = 'Deactivate CAT engine';
$string['usecatquiz'] = 'Use the catquiz engine for this test instance.';
$string['catscales'] = 'Define catquiz catscales';
$string['catscales:information'] = 'Define catscales: {$a->link}';
$string['catscalesname_exists'] = 'The name is already being used';
$string['cachedef_catscales'] = 'Caches the catscales of catquiz';
$string['catcatscales'] = 'Catscales to be tested';
$string['nameexists'] = 'The name of the catscale already exists';
$string['createnewcatscale'] = 'Create new catscale';
$string['parent'] = 'Parent catscale - None if top level catscale';
$string['managecatscale'] = 'Manage catscale';
$string['createcatscale'] = 'Create your first catquiz catscale!';
$string['cannotdeletescalewithchildren'] = 'Cannot delete CAT scale with children';
// Buttons.
$string['subscribe'] = 'Subscribe';
$string['subscribed'] = 'Subscribed';

// Events.
$string['userupdatedcatscale'] = 'User with id {$a->userid} updated catscale with id {$a->objectid}';

// Message.
$string['messageprovider:catscaleupdate'] = 'Notification of catscale update';
$string['catscaleupdatedtitle'] = 'A catscale was updated';
$string['catscaleupdatedbody'] = 'A catscale was updated. TODO: more description.';

// access.php.
$string['catquiz:canmanage'] = 'Is allowed to manage Catquiz plugin';
$string['catquiz:subscribecatscales'] = 'Is allowed to subscribe to Catquiz catscales';
$string['catquiz:manage_catscales'] = 'Is allowed to maange Catquiz catscales';

// Role.
$string['catquizroledescription'] = 'Catquiz Manager';

// Navbar.
$string['managecatscales'] = 'Manage CAT Scales';
$string['test'] = 'Test Subscription';
