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
$string['dimensions'] = 'Define catquiz dimensions';
$string['dimensions:information'] = 'Define dimensions: {$a->link}';
$string['dimensionsname_exists'] = 'The name is already being used';
$string['cachedef_dimensions'] = 'Caches the dimensions of catquiz';
$string['catdimensions'] = 'Dimensions to be tested';
$string['nameexists'] = 'The name of the dimension already exists';
$string['createnewdimension'] = 'Create new dimension';
$string['parent'] = 'Parent dimension - None if top level dimension';
$string['managedimension'] = 'Manage dimension';
$string['createdimension'] = 'Create your first catquiz dimension!';
// Buttons.
$string['subscribe'] = 'Subscribe';
$string['subscribed'] = 'Subscribed';

// Events.
$string['userupdateddimension'] = 'User with id {$a->userid} updated dimension with id {$a->objectid}';

// Message.
$string['messageprovider:dimensionupdate'] = 'Notification of dimension update';
$string['dimensionupdatedtitle'] = 'A dimension was updated';
$string['dimensionupdatedbody'] = 'A dimension was updated. TODO: more description.';

// access.php.
$string['catquiz:canmanage'] = 'Is allowed to manage Catquiz plugin';
$string['catquiz:subscribedimensions'] = 'Is allowed to subscribe to Catquiz dimensions';
$string['catquiz:manage_dimensions'] = 'Is allowed to maange Catquiz dimensions';

// Role.
$string['catquizroledescription'] = 'Catquiz Manager';

// Navbar.
$string['managedimensions'] = 'Manage Dimensions';
$string['test'] = 'Test Subscription';
