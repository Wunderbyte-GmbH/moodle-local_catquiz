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

// Catquiz handler.
$string['catquizsettings'] = 'Cat Quiz Einstellungen';
$string['selectmodel'] = 'Wähle ein Modell';
$string['model'] = 'Modell';
$string['modeldeactivated'] = 'Deaktiviere CAT engine';
$string['usecatquiz'] = 'Verwende die Catquiz Engine für dieses Quiz.';

$string['dimensions'] = 'CAT quiz Dimnensionen verwalten';
$string['dimensions:information'] = 'Verwalte CAT Test Dimensionen: {$a->link}';
$string['cachedef_dimensions'] = 'Caches the dimensions of catquiz';
$string['catdimensions'] = 'Dimensionen für den Test';

// Buttons.
$string['subscribe'] = 'Abonniere';
$string['subscribed'] = 'Abonniert';

// Events.
$string['userupdateddimension'] = 'Nutzerin mit der Id {$a->userid} hat die Dimension mit der Id {$a->objectid} aktualisiert.';

// Message.
$string['messageprovider:dimensionupdate'] = 'Benachrichtung über eine Aktualisierung einer Dimension.';
$string['dimensionupdatedtitle'] = 'Eine Dimension wurde aktualisiert';
$string['dimensionupdatedbody'] = 'Eine Dimension wurde aktualisiert. TODO: Mehr Details.';

// Access.
$string['catquiz:canmanage'] = 'Darf Catquiz Plugin verwalten';
$string['catquiz:subscribedimensions'] = 'Darf Catquiz Dimensionen abonnieren';
$string['catquiz:manage_dimensions'] = 'Darf Catquiz Dimensionen verwalten';

// Role.
$string['catquizroledescription'] = 'Catquiz VerwalterIn';
