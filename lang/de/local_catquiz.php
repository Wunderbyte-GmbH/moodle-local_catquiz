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
$string['catquizsettings'] = 'Cat Quiz Einstellungen';
$string['selectmodel'] = 'Wähle ein Modell';
$string['model'] = 'Modell';
$string['modeldeactivated'] = 'Deaktiviere CAT engine';
$string['usecatquiz'] = 'Verwende die Catquiz Engine für dieses Quiz.';
$string['catscales'] = 'CAT quiz Dimnensionen verwalten';
$string['catscales:information'] = 'Verwalte CAT Test Skalen: {$a->link}';
$string['catscalesname_exists'] = 'Der Name wird bereits verwendet';
$string['cachedef_catscales'] = 'Caches the catscales of catquiz';
$string['catcatscales'] = 'CAT Skalen für den Test';
$string['nameexists'] = 'Der Name der CAT Skala wurde bereits verwendet';
$string['createnewcatscale'] = 'Neue CAT Skala erstellen';
$string['parent'] = 'Übergeordnete CAT Skala - keine Auswahl falls Top-Level CAT Skala';
$string['managecatscale'] = 'CAT Skalen verwalten';
$string['createcatscale'] = 'Erstellen Sie die erste CAT Skala';
$string['cannotdeletescalewithchildren'] = 'CAT Skalen mit Unterskalen können nicht gelöscht werden.';
// Buttons.
$string['subscribe'] = 'Abonniere';
$string['subscribed'] = 'Abonniert';

// Events.
$string['userupdatedcatscale'] = 'Nutzerin mit der Id {$a->userid} hat die CAT Skala mit der Id {$a->objectid} aktualisiert.';

// Message.
$string['messageprovider:catscaleupdate'] = 'Benachrichtung über eine Aktualisierung einer CAT Skala.';
$string['catscaleupdatedtitle'] = 'Eine CAT Skala wurde aktualisiert';
$string['catscaleupdatedbody'] = 'Eine CAT Skala wurde aktualisiert. TODO: Mehr Details.';

// Access.
$string['catquiz:canmanage'] = 'Darf Catquiz Plugin verwalten';
$string['catquiz:subscribecatscales'] = 'Darf CAT Skalen abonnieren';
$string['catquiz:manage_catscales'] = 'Darf CAT Skalen verwalten';

// Role.
$string['catquizroledescription'] = 'Catquiz VerwalterIn';

// Navbar.
$string['managecatscales'] = 'Verwalte Skalen';
$string['test'] = 'Teste Abos';
