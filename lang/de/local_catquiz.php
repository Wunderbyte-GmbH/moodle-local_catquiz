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
$string['catcatscales_help'] = 'Jede CAT Skala hat Testitems (Fragen) die im Test verwendet werden.';
$string['nameexists'] = 'Der Name der CAT Skala wurde bereits verwendet';
$string['createnewcatscale'] = 'Neue CAT Skala erstellen';
$string['parent'] = 'Übergeordnete CAT Skala - keine Auswahl falls Top-Level CAT Skala';
$string['managecatscale'] = 'CAT Skalen verwalten';
$string['managetestenvironments'] = 'Testumgebungen verwalten';
$string['showlistofcatscalemanagers'] = "Catscale Managers";
$string['addcategory'] = "Kategorie hinzufügen";
$string['documentation'] = "Dokumentation";
$string['createcatscale'] = 'Erstellen Sie die erste CAT Skala';
$string['cannotdeletescalewithchildren'] = 'CAT Skalen mit Unterskalen können nicht gelöscht werden.';
$string['passinglevel'] = 'Bestehensgrenze in %';
$string['passinglevel_help'] = 'Die Bestehensgenze bezieht sich auf die Personenkompetenz und kann für jeden Test individuell gesetzt werden.';
$string['pilotratio'] = 'Verhältnis von Pilotfragen';
$string['pilotratio_help'] = 'Gleitkommazahl für das Verhältnis von Pilotfragen. Bei einem Wert von 0.5 wird im Schnitt bei jedem zweiten Versuch eine Pilotfrage angezeigt.';

$string['timepacedtest'] = 'Zeitbeschränkungen für den Test aktivieren';
$string['maxtime'] = 'Maximale Dauer des Tests';
$string['maxtimeperitem'] = 'Höchstzeit pro Frage in Sekunden';
$string['mintimeperitem'] = 'Mindestzeit pro Frage in Sekunden';
$string['actontimeout'] = 'Aktion nach Ablauf der Zeit';

$string['timeoutabortnoresult'] = 'Test wird sofort beendet und nicht abschließend bewertet';
$string['timeoutabortresult'] = 'Test wird sofort beendet und abschließend bewertet';
$string['timeoutfinishwithresult'] = 'Nachfrist: angezeigte Items können beendet werden';

$string['minmaxgroup'] = 'Geben Sie Minimal und Maximal als Dezimalwert ein';
$string['minscalevalue'] = 'Minimalwert';
$string['maxscalevalue'] = 'Maximalwert';
$string['chooseparent'] = 'Wähle übergeordnete Scala';
$string['errorminscalevalue'] = 'Der Minimalwert muss kleiner sein als der Maximalwert der Skala';
$string['errorhastobefloat'] = 'Muss ein Dezimalwert sein';

$string['addoredittemplate'] = "Bearbeite Vorlage";

// Test Strategy
$string['catquiz_teststrategyheader'] = 'Teststrategie';
$string['catquiz_selectteststrategy'] = 'Wähle Teststrategie';

$string['teststrategy_base'] = 'Basisklase der Teststrategien';
$string['teststrategy_info'] = 'Info Klasse für Teststrategien';
$string['teststrategy_fastest'] = 'Strategie schnellstes Ergebnis';
$string['teststrategy_balanced'] = 'Strategie ausgeglichene Fragenauswahl';
$string['teststrategy_pilot'] = 'Strategie für Pilotfragen';

// Tests environment.
$string['newcustomtest'] = 'Benutzerdefinierter Test';
$string['lang'] = 'Sprache';
$string['component'] = 'Plugin';
$string['timemodified'] = 'Modifiziert';
$string['invisible'] = 'Unsichtbar';
$string['edittestenvironment'] = 'Bearbeite Testumgebung';
$string['choosetest'] = 'Wähle Testumgebung';
$string['parentid'] = 'Eltern id';
$string['force'] = 'Erzwinge Werte';
$string['catscaleid'] = 'CAT Skala ID';
$string['numberofquestions'] = '# Fragen';
$string['numberofusers'] = '# Studierende';

// Cat contexts.
$string['addcontext'] = 'Cat Kontext hinzufügen';
$string['managecatcontexts'] = 'Cat Kontexte verwalten';
$string['manage_catcontexts'] = 'Cat Kontexte verwalten';
$string['starttimestamp'] = 'Kontext Startzeit';
$string['endtimestamp'] = 'Kontext Endzeit';
$string['timemodified'] = 'Zuletzt geändert';
$string['notimelimit'] = 'No time limit';
$string['attempts'] = 'Versuche';
$string['action'] = 'Action';
$string['searchcatcontext'] = 'Cat Kontexte durchsuchen';
$string['selectcatcontext'] = 'Cat Kontext auswählen';
$string['starttimestamp'] = 'Zeitraum Anfang';
$string['endtimestamp'] = 'Zeitraum Ende';
$string['defaultcontextname'] = 'Standard Cat Kontext';
$string['defaultcontextdescription'] = 'Beinhaltet alle Testitems';
$string['noint'] = 'Bitte geben Sie eine Zahl ein';
$string['notpositive'] = 'Bitte geben Sie eine positive Zahl ein';
$string['strategy'] = 'Strategie';
$string['max_iterations'] = 'Maximale Anzahl an Iterationen';
$string['model_override'] = 'Nur dieses Modell verwenden';
// Buttons.
$string['subscribe'] = 'Abonniere';
$string['subscribed'] = 'Abonniert';

// Events.
$string['userupdatedcatscale'] = 'Nutzerin mit der Id {$a->userid} hat die CAT Skala mit der Id {$a->objectid} aktualisiert.';

// Message.
$string['messageprovider:catscaleupdate'] = 'Benachrichtung über eine Aktualisierung einer CAT Skala.';
$string['catscaleupdatedtitle'] = 'Eine CAT Skala wurde aktualisiert';
$string['catscaleupdatedbody'] = 'Eine CAT Skala wurde aktualisiert. TODO: Mehr Details.';
$string['messageprovider:updatecatscale'] = 'Hat Berechtigung zum updaten der CAT Skala';

// Access.
$string['catquiz:canmanage'] = 'Darf Catquiz Plugin verwalten';
$string['catquiz:subscribecatscales'] = 'Darf CAT Skalen abonnieren';
$string['catquiz:manage_catscales'] = 'Darf CAT Skalen verwalten';

// Role.
$string['catquizroledescription'] = 'Catquiz VerwalterIn';

// Navbar.
$string['managecatscales'] = 'Verwalte Skalen';
$string['test'] = 'Teste Abos';

// Assign testitems to catscale page.
$string['assigntestitemstocatscales'] = "Weise den CAT Skalen Fragen zu";
$string['assign'] = "Ordne zu";
$string['questioncategories'] = 'Fragekategorien';
$string['questiontype'] = 'Fragentyp';
$string['addtestitemtitle'] = 'Testitems zu CAT Skalen hinzufügen';
$string['addtestitembody'] = 'Wollen Sie folgende Testitems der aktuellen Skale zuorden? <br> {$a->data}';
$string['addtestitemsubmit'] = 'Hinzufügen';
$string['addtestitem'] = 'Testitems hinzufügen';
$string['usage'] = 'Übersicht';
$string['failedtoaddmultipleitems'] = '{$a->numadded} Fragen wurden erfolgreich hinzugefügt, bei folgenden {$a->numfailed} Fragen traten Probleme auf: {$a->failedids}';
$string['testiteminrelatedscale'] = 'Testitem ist bereits einer Kind- oder Eltern-Skala zugeordnet';

$string['removetestitemtitle'] = 'Testitems von CAT Skalen entfernen';
$string['removetestitembody'] = 'Wollen Sie folgende Testitems aus aktuellen Skale entfernen? <br> {$a->data}';
$string['removetestitemsubmit'] = 'Entfernen';
$string['removetestitem'] = 'Testitems entfernen';

$string['testitems'] = 'Testitems';
$string['questioncontextattempts'] = '# Versuche im ausgewählten Kontext';

$string['studentstats'] = 'Studierende';
$string['notyetcalculated'] = 'Noch nicht berechnet';
$string['notyetattempted'] = 'Noch keine Versuche';

// Email Templates.
$string['notificationcatscalechange'] = 'Hallo {$a->firstname} {$a->lastname},
CAT Skalen wurden verändert auf der Moolde Plattform {$a->instancename}.
Dieses e-Mail informiert Sie als CAT Manager* verantwortlich für dieses Skala. {$a->editorname} hat die folgenden Änderungen an der Skala "{$a->catscalename}" vorgenommen.":
    {$a->changedescription}
Sie können den aktuellen Stand hier überprüfen. {$a->linkonscale}';

// Catscale Dashboard.
$string['statistics'] = "Statistik";
$string['models'] = "Modelle";
$string['previewquestion'] = "Fragen Vorschau";
$string['personability'] = "Person ability";
$string['personabilities'] = "Person abilities";
$string['itemdifficulties'] = "Item difficulties";
$string['itemdifficultiesnodata'] = "No item difficulties were calculated";
$string['somethingwentwrong'] = 'Etwas ist schiefgelaufen. Melden Sie den Fehler ihrem Admin';
$string['recalculationscheduled'] = 'Neuberechnung der Kontext-Paremeter wurde veranlasst';

// Table.
$string['label'] = "Kennzeichen";
$string['name'] = "Name";
$string['questiontext'] = "Fragentext";

// Testitem Dashboard.
$string['testitemdashboard'] = "Testitem Dashboard";
$string['itemdifficulty'] = "Item difficulty";
$string['likelihood'] = "Likelihood";
$string['statusnotset'] = "ungesetzt";
$string['statussetautomatically'] = "automatisch gesetzt";
$string['statussetmanually'] = "manuell bestätigt";
$string['statusnotcalculated'] = "unberechnet (Pilotierung)";

$string['difficulty'] = "Schwierigkeit";
$string['discrimination'] = "Diskriminierung";
$string['lastattempttime'] = "Letzter Versuch";

$string['numberofanswers'] = "Antworten";
$string['numberofusagesintests'] = "In verschiedenen Tests";
$string['numberofpersonsanswered'] = "Von Personen";
$string['numberofanswerscorrect'] = "Richtig";
$string['numberofanswersincorrect'] = "Falsch";
$string['numberofanswerspartlycorrect'] = "Teilweise richtig";
$string['averageofallanswers'] = "Durchschnitt";

$string['problematic'] = 'Problematisch';
$string['notselected'] = 'Ungesetzt';
$string['selected'] = 'Automatisch gesetzt';
$string['manuallyselected'] = 'Manuell gesetzt';
$string['manuallyexcluded'] = 'Manuell ausgeschlossen';

// Student Details
$string['studentdetails'] = "Student details";

// Tasks
$string['task_recalculate_cat_model_params'] = "CAT Parameter neu berechnen";

// CAT Manager
$string['catmanager'] = "CAT-Manager";
$string['summary'] = "Zusammenfassung";
$string['questions'] = "Fragen";
$string['testsandtemplates'] = "Tests & Templates";
$string['calculations'] = "Berechnungen";
$string['versioning'] = "Versionierung";
$string['catscalesheading'] = "CAT Skalen";
$string['summarygeneral'] = "Allgemeines";
$string['summarynumberofassignedcatscales'] = "Anzahl der Ihnen zugeordneten CAT Skalen";
$string['summarynumberoftests'] = "Anzahl der einsetzenden Tests";
$string['summarytotalnumberofquestions'] = "Anzahl der Fragen (insgesamt)";
$string['summarylastcalculation'] = "Letzte (vollständige) Berechnung";
$string['lasteditincatscales'] = "Letzte Bearbeitung in den CAT Skalen";
$string['aria:catscaleimage'] = "Hintergrundmuster für die CAT Skala";
$string['healthstatus'] = "Health-Status";
$string['catmanagernumberofsubscales'] = "Anzahl Subskalen";
$string['catmanagernumberofquestions'] = "Anzahl Fragen";
$string['integratequestions'] = "Fragen aus untergeordneten Skalen einbeziehen";
$string['noscaleselected'] = "Keine CAT-Skala gewählt.";
$string['norecordsfound'] = "Keine Fragen in dieser Skala gefunden.";
$string['selectsubscale'] = "Subskala auswählen";
$string['selectcatscale'] = "Skala:";
$string['versionchosen'] = 'ausgewählte Versionierung:';
$string['pleasechoose'] = 'bitte auswählen';

// CAT Manager Questions Table
$string['type'] = 'Typ';
$string['attempts'] = 'Versuche';
$string['addquestion'] = 'Frage aus Fragenkatalog hinzufügen';
$string['addtest'] = 'Bestehenden Test hinzufügen';
$string['checklinking'] = 'Linking prüfen';

// CAT Quiz handler
$string['noremainingquestions'] = "Keine weiteren Fragen";
$string['errorfetchnextquestion'] = "Es trat ein Fehler bei der Auswahl der nächsten Frage auf.";
$string['reachedmaximumquestions'] = "Die Maximalanzahl an Testfragen wurde erreicht";
$string['error'] = "Es ist ein Fehler aufgetreten";