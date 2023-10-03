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

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

$string['pluginname'] = 'ALiSe CAT Quiz';
$string['catquiz'] = 'Catquiz';

// Catquiz handler.
$string['catscale'] = 'CAT Skala';
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
$string['createcatscale'] = 'Erstellen Sie eine CAT Skala';
$string['cannotdeletescalewithchildren'] = 'CAT Skalen mit Unterskalen können nicht gelöscht werden.';
$string['passinglevel'] = 'Bestehensgrenze in %';
$string['passinglevel_help'] = 'Die Bestehensgenze bezieht sich auf die Personenkompetenz und kann für jeden Test individuell gesetzt werden.';
$string['pilotratio'] = 'Verhältnis von Pilotfragen';
$string['pilotratio_help'] = 'Gleitkommazahl für das Verhältnis von Pilotfragen. Bei einem Wert von 0.5 wird im Schnitt bei jedem zweiten Versuch eine Pilotfrage angezeigt.';
$string['pilotattemptsthreshold'] = 'Schwellenwert der Versuche für Pilotfragen';
$string['pilotattemptsthreshold_help'] = 'Fragen mit weniger Versuchen werden als Pilotfragen klassifiziert';
$string['includepilotquestions'] = 'Pilotfragen anzeigen';
$string['standarderrorpersubscale'] = 'Standarderror pro Subskala in Prozent';
$string['standarderrorpersubscale_help'] = 'Sobald der Standardfehler einer Subskala unter diesen Wert fällt, wird sie nicht weiter getestet.';
$string['maxquestionspersubscale'] = 'Maximalanzahl an angezeigten Fragen pro Subskala';
$string['maxquestionspersubscale_help'] = 'Wenn von einer Subskala so viele Fragen angezeigt wurden, werden keine weiteren Fragen dieser Skala mehr ausgespielt. Wenn auf 0 gesetzt, dann gibt es kein Limit.';
$string['minquestionspersubscale'] = 'Minimum an angezeigten Fragen pro Subskala';
$string['minquestionspersubscale_help'] = 'Eine Subskala wird frühestens dann ausgeschlossen, wenn die Minimalanzahl an Fragen aus dieser Skala angezeigt wurden.';

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

// Test Strategy.
$string['catquiz_teststrategyheader'] = 'Teststrategie';
$string['catquiz_selectteststrategy'] = 'Wähle Teststrategie';

$string['teststrategy_base'] = 'Basisklase der Teststrategien';
$string['teststrategy_info'] = 'Info Klasse für Teststrategien';
$string['teststrategy_fastest'] = 'Radikaler CAT';
$string['teststrategy_balanced'] = 'Moderater CAT';
$string['pilot_questions'] = 'Pilotfragen';
$string['inferlowestskillgap'] = 'Unterste Kompetenzlücke diagnostizieren';
$string['infergreateststrength'] = 'Größte Stärke diagnostizieren';
$string['inferallsubscales'] = 'Alle Subskalen bestimmen';

$string['catquiz_selectfirstquestion'] = "Auswahl der ersten Quizfrage";
$string['startwitheasiestquestion'] = "Starte mit der leichtesten Frage an";
$string['startwithfirstofsecondquintil'] = "Starte mit der leichtesten Frage aus dem zweiten Quintil";
$string['startwithfirstofsecondquartil'] = "Starte mit der leichtesten Frage aus dem zweiten Quartil";
$string['startwithmostdifficultsecondquartil'] = "Starte mit der schwierigsten Frage aus dem zweiten Quartil";
$string['startwithaverageabilityoftest'] = "Personenparamter entspricht Mittelwert der bisher im Test gemessenen Population";
$string['startwithcurrentability'] = "Personenparameter aus vorherigem Testlauf nutzen";
$string['maxtimeperquestion'] = "Erlaube Zeit pro Frage in Sekunden";
$string['maxtimeperquestion_help'] = "Falls die Beantwortung einer Frage länger dauert, wird eine Pause erzwungen";
$string['breakduration'] = "Dauer der Pause in Sekunden";

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

// Events and Event Log.
$string['target'] = 'Ziel';
$string['userupdatedcatscale'] = 'NutzerIn mit der Id {$a->userid} hat {$a->catscalelink} aktualisiert.';
$string['catscale_updated'] = 'CAT Skala aktualisert';
$string['testitem'] = 'Frage mit ID {$a}';
$string['add_testitem_to_scale'] = '{$a->testitemlink} wurde {$a->catscalelink} hinzugefügt.';
$string['testiteminscale_added'] = 'Frage zu CAT Skala hinzugefügt';
$string['testiteminscale_updated'] = 'Frage in CAT Skala aktualisert';
$string['testitemactivitystatus_updated'] = 'Aktivitätsstatus der Frage aktualisiert.';
$string['update_testitem_in_scale'] = '{$a->testitemlink} wurde in {$a->catscalelink} aktualisiert.';
$string['update_testitem_activity_status'] = 'Der Aktivitätsstatus der Frage mit der Id {$a->objectid} wurde aktualisiert.';
$string['activitystatussetinactive'] = 'Die Frage ist jetzt deaktiviert.';
$string['activitystatussetactive'] = 'Die Frage ist jetzt aktiviert.';
$string['testitemstatus_updated'] = 'Status der Frage aktualisiert.';
$string['testitem_status_updated_description'] = 'Der neue Status der {$a->testitemlink} ist nun: {$a->statusstring}';
$string['catscale_created'] = 'CAT Skala erzeugt';
$string['create_catscale_description'] = 'CAT Skala "{$a->catscalelink}" mit der ID {$a->objectid} erzeugt.';
$string['context_updated'] = 'CAT Context aktualisiert';
$string['update_catscale_description'] = 'CAT Context {$a} aktualisiert.';
$string['context_created'] = 'CAT Context erzeugt';
$string['created_catscale_description'] = 'CAT Context {$a} erzeugt.';
$string['logsafter'] = 'Einträge vor';
$string['logsbefore'] = 'Einträge nach';
$string['calculation_executed'] = 'Berechnung durchgeführt.';
$string['executed_calculation_description'] =
    'Es wurde eine Berechnung der CAT Skala {$a->catscalename} mit der ID {$a->catscaleid} im Kontext {$a->contextid} durchgeführt von {$a->userid}. Es wurden {$a->numberofitems} Fragen neu berechnet.';
$string['deletedcatscale'] = 'CAT Skala die nicht mehr exisitiert';
$string['attempt_completed'] = 'Versuch abgeschlossen';
$string['complete_attempt_description'] = 'Versuch mit ID {$a->attemptid} in CAT Skala {$a->catscalelink} durchgeführt von User {$a->userid}.';
$string['eventtime'] = 'Zeitpunkt des Ereignisses';
$string['eventname'] = 'Name des Ereignisses';
$string['testitem_imported'] = 'Frage(n) importiert';
$string['imported_testitem_description'] = 'Es wurden {$a} Frage(n) importiert.';

// Message.
$string['messageprovider:catscaleupdate'] = 'Benachrichtung über eine Aktualisierung einer CAT Skala.';
$string['catscaleupdatedtitle'] = 'Eine CAT Skala wurde aktualisiert';
$string['catscaleupdatedbody'] = 'Eine CAT Skala wurde aktualisiert. TODO: Mehr Details.';
$string['messageprovider:updatecatscale'] = 'Hat Berechtigung zum Updaten der CAT Skala';

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
$string['scaledetailviewheading'] = 'Detailansicht von CAT-Skala {$a}';

// Table.
$string['label'] = "Kennzeichen";
$string['name'] = "Name";
$string['questiontext'] = "Fragentext";

// Testitem Dashboard.
$string['testitemdashboard'] = "Testitem Dashboard";
$string['itemdifficulty'] = "Item difficulty";
$string['likelihood'] = "Likelihood";

$string['difficulty'] = "Schwierigkeit";
$string['discrimination'] = "Diskriminierung";
$string['lastattempttime'] = "Letzter Versuch";
$string['guessing'] = "Guessing";

$string['numberofanswers'] = "Antworten";
$string['numberofusagesintests'] = "In verschiedenen Tests";
$string['numberofpersonsanswered'] = "Von Personen";
$string['numberofanswerscorrect'] = "Richtig";
$string['numberofanswersincorrect'] = "Falsch";
$string['numberofanswerspartlycorrect'] = "Teilweise richtig";
$string['averageofallanswers'] = "Durchschnitt";

$string['itemstatus_-5'] = "Manuell ausgeschlossen"; // STATUS_EXCLUDED_MANUALLY.
$string['itemstatus_0'] = "Noch nicht berechnet"; // STATUS_NOT_CALCULATED.
$string['itemstatus_1'] = "Berechnet"; // STATUS_CALCULATED.
$string['itemstatus_4'] = "Manuell gesetzt"; // STATUS_UPDATED_MANUALLY.
$string['itemstatus_5'] = "Manuell bestätigt"; // STATUS_CONFIRMED_MANUALLY.

// Student Details.
$string['studentdetails'] = "Student details";
$string['enroled_courses'] = "Eingeschriebene Kurse";
$string['questionresults'] = "Fragen Auswertung";
$string['daysago'] = 'Vor {$a} Tagen';
$string['hoursago'] = 'Vor {$a} Stunden';
$string['noaccessyet'] = 'Bisher kein Zugriff.';

// Tasks.
$string['task_recalculate_cat_model_params'] = "CAT Parameter neu berechnen";

// CAT Manager.
$string['catmanager'] = "CAT-Manager";
$string['summary'] = "Zusammenfassung";
$string['questions'] = "Fragen";
$string['testsandtemplates'] = "Tests & Templates";
$string['calculations'] = "Berechnungen";
$string['versioning'] = "Versionierung";
$string['catscalesheading'] = "CAT Skalen";
$string['subscribedcatscalesheading'] = "Eingeschriebene CAT Skalen";
$string['summarygeneral'] = "Allgemeines";
$string['summarynumberofassignedcatscales'] = "Anzahl der Ihnen zugeordneten CAT Skalen";
$string['summarynumberoftests'] = "Anzahl der einsetzenden Tests";
$string['summarytotalnumberofquestions'] = "Anzahl der Fragen (insgesamt)";
$string['summarylastcalculation'] = "Letzte (vollständige) Berechnung";
$string['recentevents'] = "Letzte Bearbeitungen";
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
$string['quizattempts'] = 'Quiz Versuche';
$string['calculate'] = 'Berechnen';

// CAT Manager Questions Table.
$string['type'] = 'Typ';
$string['attempts'] = 'Versuche';
$string['addquestion'] = 'Frage aus Fragenkatalog hinzufügen';
$string['addtest'] = 'Bestehenden Test hinzufügen';
$string['checklinking'] = 'Linking prüfen';
$string['confirmdeletion'] = 'Sie sind dabei das folgende Element zu löschen: <br> "{$a->data}"';
$string['deletedatatitle'] = 'Löschen';
$string['genericsubmit'] = 'Bestätigen';
$string['confirmactivitychange'] = 'Sie sind dabei den Aktivitätsstatus des folgenden Elements zu ändern: <br> "{$a->data}"';
$string['toggleactivity'] = 'Aktivitätsstatus';
$string['errorrecordnotfound'] = 'Fehler mit der Datenbankabfrage. Der Datensatz wurde nicht gefunden.';
$string['trashbintitle'] = 'Element löschen';
$string['cogwheeltitle'] = 'Details anzeigen';
$string['eyeicontitle'] = 'Aktivieren/Deaktivieren';

// Testitem Detail View.
$string['questionpreview'] = 'Fragevorschau';
$string['backtotable'] = 'Zurück zur Testitems Tabelle';
$string['local_catquiz_toggle_testitemstatus_message'] = 'Status des Elements wurde aktualisiert';
$string['togglestatus'] = 'Status ändern';

// CAT Quiz handler.
$string['noremainingquestions'] = "Keine weiteren Fragen";
$string['errorfetchnextquestion'] = "Es trat ein Fehler bei der Auswahl der nächsten Frage auf.";
$string['reachedmaximumquestions'] = "Die Maximalanzahl an Testfragen wurde erreicht";
$string['error'] = "Es ist ein Fehler aufgetreten";
$string['id'] = "ID";
$string['abortpersonabilitynotchanged'] = "Personenparameter unverändert";
$string['emptyfirstquestionlist'] = "Kann keine Startfrage wählen da die Liste leer ist";

// Quiz Feedback.
$string['attemptfeedbacknotavailable'] = "Kein Feedback verfügbar";
$string['allquestionsincorrect'] = "Nicht verfügbar - alle Fragen wurden falsch beantwortet";
$string['allquestionscorrect'] = "Nicht verfügbar - alle Fragen wurden richtig beantwortet";
$string['feedbackcomparetoaverage'] = 'Sie sind besser als {$a}% Ihrer Mit-Studierenden im aktuellen Jahrgang.';
$string['feedbackneedsimprovement'] = "Da geht doch sicher noch etwas, oder?";
$string['questionssummary'] = "Zusammenfassung";
$string['currentability'] = "Ihr momentaner Wissensstand";
$string['currentabilityfellowstudents'] = "Momentaner Mittelwert der Wissensstände Ihrer zukünftigen Mit-Studierenden";
$string['feedbackbarlegend'] = "Bedeutung der Farbskala";
$string['feedbackbarlegend_region_1'] = "Ihre zukünftigen Lehrenden schätzen einen solchen Wissensstand als zu gering ein, um im Fachstudium mithalten zu können.";
$string['feedbackbarlegend_region_2'] = "Mit einem Wissensstand in diesem Bereich ist im Fachstudium mit regelmäßigen Verständnisproblemen zu rechnen.";
$string['feedbackbarlegend_region_3'] = "In diesem Bereich der Wissensstände ist erfahrungsgemäß ein Studium in der Regelstudienzeit möglich.";
$string['feedbackbarlegend_region_4'] = "Dieser Bereich legt ein Vorwissen nahe, was über die Anforderungen des Fachstudiums sogar hinausgeht.";
$string['teacherfeedback'] = "Feedback für Lehrende";

$string['catquiz_feedbackheader'] = "Feedback";
$string['noselection'] = "Keine Auswahl";
$string['lowerlimit'] = "Unteres Limit";
$string['setcoursesforscaletext'] = 'Setzen sie für die Scala {$a} die Kurse, in die Nutzerinnen, die unter dem unteren Limit abschneiden, eingeschrieben werden sollen.';

// Quiz attempts
$string['catcontext'] = 'CAT Kontext';
$string['totalnumberoftestitems'] = "Gesamtzahl Fragen";
$string['numberoftestitemsused'] = "Anzahl getesteter Fragen";
$string['personabilitybeforeattempt'] = "Ability vor Versuch";
$string['personabilityafterattempt'] = "Ability nach Versuch";
$string['instance'] = "Test";
$string['teststrategy'] = "Teststrategie";
$string['starttime'] = "Beginn";
$string['endtime'] = "Ende";

// CSV Import Form.
$string['importcsv'] = 'Import CSV';
$string['importsuccess'] = 'Import war erfolgreich. Es wurden {$a} Datensatz/Datensätze bearbeitet.';
$string['importfailed'] = 'Import fehlgeschlagen.';
$string['dateparseformat'] = 'Format des Datums';
$string['dateparseformat_help'] = 'Bitte Datum so wie es im CSV definiert wurde verwenden. Hilfe unter <a href="http://php.net/manual/en/function.date.php">Datumsdokumentation</a> für diese Einstellung.';
$string['defaultdateformat'] = 'j.n.Y H:i:s';
$string['importcolumnsinfos'] = 'Informationen zu Importfeldern:';
$string['mandatory'] = 'verpflichtend';
$string['format'] = 'Format';
$string['downloaddemofile'] = 'Demofile herunterladen';
$string['labelidnotunique'] = 'Wert von Label {$a} muss einzigartig sein.';
$string['labelidnotfound'] = 'Wert von Label {$a} nicht gefunden.';
$string['updatedrecords'] = '{$a} Eintrag/Einträge aktualisiert.';
$string['addedrecords'] = '{$a} Eintrag/Einträge hinzugefügt.';
$string['callbackfunctionnotdefined'] = 'Callback Funktion nicht definiert.';
$string['callbackfunctionnotapplied'] = 'Callback Funktion konnte nicht angewandt werden.';
$string['canbesetto0iflabelgiven'] = 'Kann 0 sein, wenn Abgleich über Label stattfindet.';
$string['ifdefinedusedtomatch'] = 'Wenn angegeben findet der Abgleich über diesen Wert statt.';
$string['fieldnamesdontmatch'] = 'Die importierten Spaltennamen entsprechen nicht der Vorgabe.';
$string['checkdelimiteroremptycontent'] = 'Überprüfen Sie ob Daten vorhanden und durch das angegebene Zeichen getrennt sind.';
$string['wronglabels'] = 'Die importierten Spaltennamen entsprechen nicht der Vorgabe. {$a} kann nicht importiert werden.';
$string['nolabels'] = 'Keine Spaltennamen definiert.';
$string['checkdelimiter'] = 'Überprüfen Sie die Spaltennamen durch das angegebene Zeichen getrennt sind.';
$string['scaleinformation'] = 'Die ID der CAT Skala der die Frage zugeordnet werden soll.';
$string['scalenameinformation'] = 'Der Name der CAT Skala der die Frage zugeordnet werden soll. Falls keine ID angegeben, wird Matching über Name vorgenommen.';
$string['dataincomplete'] = 'Der Datensatz mit "componentid" {$a->id} ist unvollständig und konnte nicht gänzlich eingefügt werden. Überprüfen Sie das Feld "{$a->field}".';
$string['modelinformation'] = 'Dieses Feld ist notwendig, um Fragen vollständig zu erfassen. Ist das Feld leer, kann die Frage lediglich einer Skala zugeordnet werden.';
$string['parentscalenamesinformation'] = 'Alle Eltern Scalen können beim Import angelegt werden. Starten sie mit dem Namen der höchsten Scala und fügen sie alle Kinder mit | getrennt hinzu.';

// Testenvironments table.
$string['notifyallteachers'] = 'Kursleiter der gewählten Kurse benachrichtigen';
$string['notifyteachersofselectedcourses'] = 'Alle Kursleiter benachrichtigen';

$string['close'] = 'Schließen';

// Shortcodes
$string['shortcodeslistofquizattempts'] = 'Gibt eine Tabelle mit Quiz-Versuchen zurück.';

// Validation
$string['valuemustbegreaterzero'] = 'Wert muss höher als 0 sein.';

// Breakinfo.
$string['breakinfo_title'] = 'Test pausiert';
$string['breakinfo_description'] = 'Der Test wurde pausiert.';
$string['breakinfo_continue'] = 'Der Test kann um {$a} fortgesetzt werden';
$string['breakinfo_backtotest'] = 'Zurück zum Test';
