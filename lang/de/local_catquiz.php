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
 * @copyright   2022 onwards Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Adaptive Quiz - Advanced CAT Module';
$string['catquiz'] = 'Catquiz';
$string['subplugintype_catmodel'] = 'CAT Modell';
$string['subplugintype_catmodel_plural'] = 'CAT Modelle';

// Catquiz handler.
$string['catscale'] = 'Skala';
$string['catquizsettings'] = 'Test-Inhalt und Einsatz-Kontext';
$string['selectmodel'] = 'Wähle Modell';
$string['model'] = 'Modell';
$string['modeldeactivated'] = 'Deaktiviere CAT engine';
$string['usecatquiz'] = 'Verwende die Catquiz Engine für dieses Quiz.';
$string['catscales'] = 'CAT quiz Dimensionen verwalten';
$string['catscales:information'] = 'Verwalte CAT Test Skalen: {$a->link}';
$string['cattags'] = 'Kurs Tags verwalten';
$string['cattags:information'] = 'Diese Tags kennzeichnen Kurse, zu denen Nutzende einschreiben können, unabhängig davon, ob sie Teil des Kurses sind.';
$string['choosetags'] = 'Tag(s) auswählen';
$string['choosetags:disclaimer'] = 'Mehrfachauswahl mit „⌘ command“ (Apple) oder „Ctrl“ (Windows, Linux)';
$string['catscalesname_exists'] = 'Der Name wird bereits verwendet';
$string['cachedef_catscales'] = 'Speichert (Cache) die Skalen von catquiz';
$string['catcatscales'] = 'Auswahl untergeordnete Skalen';
$string['selectparentscale'] = 'Auswahl Skala';
$string['catcatscales_help'] = 'Wählen Sie die für Sie die für Sie relevanten untergeordneten Skalen an und ab. Eine untergeordnete Skala umfasst Fragen aus einen Teil des gewählten Inhaltsbereichs. In einem Test-Versuch werden nur Fragen aus den angewählten Skalen verwendet.';
$string['nameexists'] = 'Der Name der Skala wurde bereits verwendet';
$string['createnewcatscale'] = 'Neue Skala erstellen';
$string['parent'] = 'Übergeordnete Skala - keine Auswahl falls Top-Level Skala';
$string['managecatscale'] = 'Skalen verwalten';
$string['managetestenvironments'] = 'Testumgebungen verwalten';
$string['showlistofcatscalemanagers'] = 'Catscale Managers';
$string['addcategory'] = 'Kategorie hinzufügen';
$string['documentation'] = 'Dokumentation';
$string['createcatscale'] = 'Erstellen Sie eine Skala';
$string['cannotdeletescalewithchildren'] = 'Skalen mit Unterskalen können nicht gelöscht werden.';
$string['passinglevel'] = 'Bestehensgrenze in %';
$string['passinglevel_help'] = 'Die Bestehensgenze bezieht sich auf die Personenkompetenz und kann für jeden Test individuell gesetzt werden.';
$string['pilotratio'] = 'Anteil zu pilotierender Fragen in %';
$string['pilotratio_help'] = 'Anteil von noch zu pilotierender Fragen an der Gesamtfragezahl in einem Test-Versuch. Die Angabe 20% führt beispielsweise dazu, dass eine von fünf Fragen  eines Test-Versuches eine zu pilotierende Frage sein wird.';
$string['firstquestion_startnewtest'] = 'Beginne neuen Test';
$string['firstquestionreuseexistingdata'] = 'mit Ergebnisdaten aus vorherigen Testversuchen';
$string['firstquestionselectotherwise'] = '...ansonsten: ';
$string['includepilotquestions'] = 'Pilotierungsmodus aktivieren';
$string['standarderror'] = 'Standardfehler';
$string['tr_sd_ratio_name'] = 'Multiplikator für Vertrauensbereich';
$string['tr_sd_ratio_desc'] = 'Der Multiplikator für den Vertrauensbereich gibt
    das Vielfache der Standardabweichung um den Mittelwert an, um den eine
    Parameterschätzung einer Person oder Item-Schwierigkeit erwartet wird. Wird
    der Multiplikator für den Vertrauensbereich zu hoch gewählt, besteht die
    Gefahr, dass der numerische Algorithmus instabil wird und bei schwieriger
    Datenlage unzuverlässige Werte liefert. Default-Wert ist ein Multiplikator
    von 3.0, was statistisch 99,9 Prozent aller zu erwartenden Fälle mit einschließt.';
$string['minquestions_default_name'] = 'Standardwert für die Mindestanzahl an Fragen pro Versuch';
$string['minquestions_default_desc'] = 'Dieser Wert wird standardmässig gesetzt, kann jedoch in den Quizsettings überschrieben werden';
$string['acceptedstandarderror'] = 'akzeptierter Standardfehler';
$string['acceptedstandarderror_help'] = 'Sobald der Standardfehler einer Skala außerhalb dieser Werte fällt, wird sie nicht weiter getestet.';
$string['maxquestionspersubscale'] = 'max. Frageanzahl pro Skala';
$string['maxquestionspersubscale_help'] = 'Wenn von einer Skala so viele Fragen angezeigt wurden, werden keine weiteren Fragen dieser Skala mehr ausgespielt. Wenn auf 0 gesetzt, dann gibt es kein Limit.';

$string['numberofquestionspertest'] = 'Anzahl der Fragen pro Test';
$string['numberofquestionspertest_help'] = 'Setzen Sie den Maximalwert auf 0 um unbegrenzt Fragen auszuspielen.';
$string['numberofquestionsperscale'] = 'Anzahl der Fragen pro Skala';
$string['numberofquestionsperscale_help'] = 'Setzen Sie den Maximalwert auf 0 um unbegrenzt Fragen pro Skala auszuspielen.';
$string['minquestionspersubscale'] = 'min. Frageanzahl pro Skala';
$string['minquestionspersubscale_help'] = 'Eine Skala wird frühestens dann ausgeschlossen, wenn die Minimalanzahl an Fragen aus dieser Skala angezeigt wurden.';
$string['contextidselect'] = 'Einsatz-Kontext - ohne Auswahl wird ein neuer Einsatz-Kontext erstellt';
$string['choosecontextid'] = 'Einsatz-Kontext auswählen';
$string['defaultcontext'] = 'Neuer Standard Einsatz-Kontext für Skala';
$string['moveitemtootherscale'] = 'Testitem(s) {$a} sind bereits einer anderen Skala des selben Baumes zugeordnet. Zuordnung ändern?';
$string['pleasecheckorcancel'] = 'Bitte bestätigen oder abbrechen';
$string['progress'] = 'Entwicklung Fähigkeits-Wert in {$a}';
$string['learningprogress_title'] = 'Ihr Lernfortschritt';
$string['learningprogress_description'] = 'Wie hat sich Ihr Fähigkeits-Wert über
    die letzten Versuche hin entwickelt? Haben Sie sich verbessert?<br/> Die
    folgende Grafik zeigt Ihnen die Entwicklung Ihres (allgemeinen)
    Fähigkeits-Wertes in {$a} im Vergleich zum Durchschnittswert aller
    Testversuche:';
$string['graphicalsummary_description'] = 'Während des Verlaufs des Testversuchs wird Ihrer Fähigkeits-Wert mit jeder Antwort neu
berechnet und aktualisiert. Die folgende Grafik zeigt Ihnen, wie sich die Einschätzung
Ihres Fähigkeits-Wertes in {$a} über den Verlauf des Testversuchs hinweg
verändert hat.';
$string['graphicalsummary_description_lowest'] = 'Zusätzlich ist auch die
    Entwicklung Ihres Fähigkeits-Wertes bezüglich der als Defizit
    identifizierten Skala {$a} dargestellt:';
$string['debuginfo_desc_title'] = 'Export des Testversuchs Nr. {$a}';
$string['debuginfo_desc'] = 'Hier können Sie als Nutzer mit Berechtigung zum Download eines Exports den Versuchsverlauf als CSV-Datei exportieren.';
$string['timepacedtest'] = 'Zeitbeschränkungen für den Test aktivieren';
$string['includetimelimit'] = 'Bearbeitung eines Testversuchs zeitlich begrenzen';
$string['includetimelimit_help'] = 'Maximaldauer festlegen, die für die Durchführung des Tests gelten soll.';
$string['maxtime'] = 'Maximale Dauer des Tests';
$string['maxtimeperitem'] = 'Höchstzeit pro Frage in Sekunden';
$string['mintimeperitem'] = 'Mindestzeit pro Frage in Sekunden';
$string['perattempt'] = 'pro Versuch ';
$string['peritem'] = 'pro Item ';
$string['applychanges'] = 'Änderungen übernehmen';
$string['automatic_reload_on_scale_selection'] = 'Bei (Sub-)Skalenauswahl Formular neu laden';
$string['automatic_reload_on_scale_selection_description'] = 'Bei (Sub-)Skalenauswahl automatisch das Quizsettings-Formular neu laden';
$string['enrol_only_to_reported_scales'] = 'Benutzer nur in Kurse von detektierter Skala einschreiben';
$string['enrol_only_to_reported_scales_help'] = 'Standardmäßig werden die Benutzer nach den Ergebnissen in den Bereichen eingeschrieben, die entsprechend dem Zweck des Tests ermittelt wurden.
Wenn Sie diese Option deaktivieren, werden die Benutzer auch entsprechend aller anderen gültigen Ergebnissen eingeschrieben.'; // TODO: get translation.
$string['time_penalty_threshold_name'] = 'Wiederholungsverzögerung in Tagen';
$string['time_penalty_threshold_desc'] = 'Eine Frage, die durch einen User in
    einem früheren Testversuch bereits beantwortet wurde, wird nur mit
    verringerter Wahrscheinlichkeit erneut gestellt. Die Wahrscheinlichkeit ist
    abhängig vom der Dauer zwischen dem früheren und dem aktuellen Versuch. Je
    höher die eingestellte Dauer, desto länger ist dieser Schutz vor wiederholt
    gestellten Fragen wirksam.';
$string['timeoutabortnoresult'] = 'Test wird sofort beendet und nicht abschließend bewertet';
$string['timeoutabortresult'] = 'Test wird sofort beendet und abschließend bewertet';
$string['timeoutfinishwithresult'] = 'Nachfrist: angezeigte Items können beendet werden';
$string['store_debug_info_name'] = 'Stelle debug Informationen zur Verfügung';
$string['store_debug_info_desc'] = 'Wenn diese Option aktiviert ist, werden
    zusätzliche Daten gespeichert und als CSV Datei zur Verfügung gestellt.
    Dadurch steigt der benötigte Speicherplatz.';

// Validation.
$string['minabilityscalevalue'] = 'Minimale Personenfähigkeit:';
$string['minabilityscalevalue_help'] = 'Geben Sie die kleinstmögliche Personenfähigkeit dieser Skala als negativen Dezimalwert an. Der Mittelwert ist null.';
$string['maxabilityscalevalue'] = 'Maximale Personenfähigkeit:';
$string['maxabilityscalevalue_help'] = 'Geben Sie die größtmögliche Personenfähigkeit dieser Skala als Dezimalwert an. Der Mittelwert ist null.';
$string['minscalevalue'] = 'Minimalwert';
$string['maxscalevalue'] = 'Maximalwert';
$string['chooseparent'] = 'Wähle übergeordnete Scala';
$string['errorminscalevalue'] = 'Der Minimalwert muss kleiner sein als der Maximalwert der Skala';
$string['errorhastobefloat'] = 'Muss ein Dezimalwert sein';
$string['errorhastobeint'] = 'Muss eine Ganzzahl sein';
$string['formelementnegative'] = 'Wert muss positiv (über 0) sein';
$string['formelementwrongpercent'] = 'Prozentzahl zwischen 0 und 100 eingeben';
$string['formminquestgreaterthan'] = 'Minimum muss kleiner als Maximum sein';
$string['formelementbetweenzeroandone'] = 'Bitte Werte zwischen 0 und 1 eingeben.';
$string['formmscalegreaterthantest'] = 'Minimum pro Skala muss kleiner sein als Maximum des Tests';
$string['formetimelimitnotprovided'] = 'Geben Sie zumindest einen Wert ein';
$string['nogapallowed'] = "Keine Lücken in Personenfähigkeitsspanne erlaubt. Bitte beginnen setzen Sie als Mindestwert den Maximalwert des vorangegangenen Bereichs.";
$string['errorupperlimitvalue'] = "Oberes Limit muss kleiner als unteres Limit sein.";
$string['formelementnegativefloat'] = 'Negative Dezimalzahl eingeben.';
$string['formelementpositivefloat'] = 'Positive Dezimalzahl eingeben.';
$string['formelementnegativefloatwithdefault'] = 'Negative Dezimalzahl eingeben. Standard wäre {$a}.';
$string['formelementpositivefloatwithdefault'] = 'Positive Dezimalzahl eingeben. Standard wäre {$a}.';
$string['setsevalue'] = 'Bitte Werte angeben. Standard: Min={$a->min} Max={$a->max}';


$string['addoredittemplate'] = "Einstellungs-Vorlage bearbeiten";


// Test Strategy.
$string['catquiz_teststrategyheader'] = 'CAT-Einstellungen';
$string['catquiz_selectteststrategy'] = 'Testzweck';

$string['teststrategy_base'] = 'Basisklase der Teststrategien';
$string['teststrategy_info'] = 'Info Klasse für Teststrategien';
$string['teststrategy_fastest'] = 'CAT';
$string['teststrategy_balanced'] = 'Moderater CAT';
$string['pilot_questions'] = 'Pilotfragen';
$string['inferlowestskillgap'] = 'Unterste Kompetenzlücke diagnostizieren';
$string['infergreateststrength'] = 'Größte Stärke diagnostizieren';
$string['inferallsubscales'] = 'Alle untergeordneten Skalen bestimmen';
$string['classicalcat'] = 'Klassischer Test';

$string['catquiz_selectfirstquestion'] = "Ansonsten beginne ...";
$string['startwithveryeasyquestion'] = "mit einer sehr leichten Frage";
$string['startwitheasyquestion'] = "mit einer leichten Frage";
$string['startwithmediumquestion'] = "mit einer mittelschweren Frage";
$string['startwithdifficultquestion'] = "mit einer schweren Frage";
$string['startwithverydifficultquestion'] = "mit einer sehr schweren Frage";
$string['maxtimeperquestion'] = "Erlaubte Zeit";
$string['maxtimeperquestion_help'] = "Falls die Beantwortung einer Frage länger dauert, wird eine Pause erzwungen";
$string['min'] = "min:";
$string['max'] = "max:";

// Tests environment.
$string['newcustomtest'] = 'Benutzerdefinierter Test';
$string['lang'] = 'Sprache';
$string['component'] = 'Plugin';
$string['timemodified'] = 'Modifiziert';
$string['invisible'] = 'Unsichtbar';
$string['edittestenvironment'] = 'Bearbeite Testumgebung';
$string['choosetemplate'] = 'Einstellungs-Vorlage wählen';
$string['parentid'] = 'Übergeordnete ID';
$string['force'] = 'Erzwinge Werte';
$string['catscaleid'] = 'Skala ID';
$string['numberofquestions'] = '# Fragen';
$string['numberofusers'] = '# Nutzende';
$string['type'] = 'Typ';
$string['testtype'] = 'Test';
$string['templatetype'] = 'Template';

// Cat contexts.
$string['addcontext'] = 'Einsatz-Kontext hinzufügen';
$string['managecatcontexts'] = 'Einsatz-Kontexte verwalten';
$string['manage_catcontexts'] = 'Einsatz-Kontexte verwalten';
$string['starttimestamp'] = 'Kontext Startzeit';
$string['endtimestamp'] = 'Kontext Endzeit';
$string['timemodified'] = 'Zuletzt geändert';
$string['notimelimit'] = 'Keine zeitliche Begrenzung';
$string['attempts'] = 'Testversuche';
$string['action'] = 'Action';
$string['searchcatcontext'] = 'Einsatz-Kontexte durchsuchen';
$string['selectcatcontext'] = 'Einsatz-Kontext auswählen';
$string['starttimestamp'] = 'Zeitraum Anfang';
$string['endtimestamp'] = 'Zeitraum Ende';
$string['defaultcontextname'] = 'Standard Kontext';
$string['defaultcontextdescription'] = 'Beinhaltet alle Testitems';
$string['autocontextdescription'] = 'Automatisch durch einen Import generiert für Skala {$a}.';
$string['uploadcontext'] = 'autocontext_{$a->scalename}_{$a->usertime}';
$string['noint'] = 'Bitte geben Sie eine Zahl ein';
$string['notpositive'] = 'Bitte geben Sie eine positive Zahl ein';
$string['strategy'] = 'Strategie';
$string['max_iterations'] = 'Maximale Anzahl an Iterationen';
$string['model_override'] = 'Nur dieses Modell verwenden';
$string['importcontextinfo'] = 'Die Kontextid sollte gesetzt werden, wenn bestehende Items bearbeitet werden, damit die eindeutige Zuordnung gelingt. Für den Import von neuen Items, empfiehlt es sich, den Kontext leer zu lassen. Es wird dann ein neuer Kontext automatisch generiert, welcher die Items aus dem Standardkontext plus die neu importierten enthält. Falls beim Import neuer Items ein Kontext angegeben wird, muss der Kontext der entsprechenden obersten Skala umgestellt werden (im CAT-Manager Dashboard, Skalen-Bereich), damit diese Items zum Einsatz kommen.';

// Buttons.
$string['subscribe'] = 'Abonniere';
$string['subscribed'] = 'Abonniert';

// Events and Event Log.
$string['target'] = 'Ziel';
$string['userupdatedcatscale'] = 'NutzerIn mit der Id {$a->userid} hat {$a->catscalelink} aktualisiert.';
$string['catscale_updated'] = 'Skala aktualisert';
$string['testitem'] = 'Frage mit ID {$a}';
$string['add_testitem_to_scale'] = '{$a->testitemlink} wurde {$a->catscalelink} hinzugefügt.';
$string['testiteminscale_added'] = 'Frage zu Skala hinzugefügt';
$string['testiteminscale_updated'] = 'Frage in Skala aktualisert';
$string['testitemactivitystatus_updated'] = 'Aktivitätsstatus der Frage aktualisiert.';
$string['update_testitem_in_scale'] = '{$a->testitemlink} wurde in {$a->catscalelink} aktualisiert.';
$string['update_testitem_activity_status'] = 'Der Aktivitätsstatus der Frage mit der Id {$a->objectid} wurde aktualisiert.';
$string['activitystatussetinactive'] = 'Die Frage ist jetzt deaktiviert.';
$string['activitystatussetactive'] = 'Die Frage ist jetzt aktiviert.';
$string['testitemstatus_updated'] = 'Status der Frage aktualisiert.';
$string['testitem_status_updated_description'] = 'Der neue Status der {$a->testitemlink} ist nun: {$a->statusstring}';
$string['catscale_created'] = 'Skala erzeugt';
$string['create_catscale_description'] = 'Skala „{$a->catscalelink}“ mit der ID {$a->objectid} erzeugt.';
$string['context_updated'] = 'Einsatz-Kontext aktualisiert';
$string['update_context_description'] = 'Einsatz-Kontext {$a} aktualisiert.';
$string['context_created'] = 'Einsatz-Kontext erzeugt';
$string['create_context_description'] = 'Einsatz-Kontext {$a} erzeugt.';
$string['logsafter'] = 'Einträge vor';
$string['logsbefore'] = 'Einträge nach';
$string['calculation_executed'] = 'Berechnung durchgeführt.';
$string['executed_calculation_description'] =
    'Es wurde eine Berechnung der Skala „{$a->catscalename}“ mit der ID {$a->catscaleid} im Kontext {$a->contextid} durchgeführt von {$a->user}. In folgenden Modellen wurden Fragen neu berechnet: {$a->updatedmodels}';
$string['automaticallygeneratedbycron'] = 'Cron Job (automatisch durchgeführt)';
$string['deletedcatscale'] = 'Skala die nicht mehr exisitiert';
$string['attempt_completed'] = 'Testversuch abgeschlossen';
$string['usertocourse_enroled'] = 'NutzerIn in Kurs eingeschrieben';
$string['usertogroup_enroled'] = 'NutzerIn in Gruppe eingeschrieben';
$string['usertocourse_enroled_description'] = 'NutzerIn mit ID {$a->userid} wurde in folgenden Kurs eingeschrieben: „<a href="{$a->courseurl}">{$a->coursename}</a>“';
$string['usertogroup_enroled_description'] = 'NutzerIn mit ID {$a->userid} wurde in die Gruppe {$a->groupname} in diesem Kurs eingeschrieben: „<a href="{$a->courseurl}">{$a->coursename}</a>“';
$string['complete_attempt_description'] = 'Testversuch mit ID {$a->attemptid} in Skala {$a->catscalelink} durchgeführt von User {$a->userid}.';
$string['eventtime'] = 'Zeitpunkt des Ereignisses';
$string['eventname'] = 'Name des Ereignisses';
$string['testitem_imported'] = 'Frage(n) importiert';
$string['imported_testitem_description'] = 'Es wurden {$a} Frage(n) importiert.';
$string['testitem_deleted'] = 'Frage gelöscht';
$string['testitem_deleted_description'] = 'Es wurde die Frage mit ID {$a->testitemid} gelöscht.';
$string['feedback_tab_clicked'] = 'Klick auf Feedback Tab';
$string['feedback_tab_clicked_description'] = 'Nutzer {$a->userid} hat auf Feedback {$a->feedback_translated} in {$a->attemptlink} geklickt';

// Message.
$string['messageprovider:catscaleupdate'] = 'Benachrichtung über eine Aktualisierung einer Skala.';
$string['messageprovider:updatecatscale'] = 'Benachrichtung über eine Aktualisierung einer Skala.';
$string['catscaleupdatedtitle'] = 'Eine Skala wurde aktualisiert';
$string['messageprovider:updatecatscale'] = 'Erhält Benachrichtungung über Einschreibung in Skala';
$string['onegroupenroled'] = 'Sie sind auf Grundlage Ihres Ergebnisses in „{$a->catscalename}“ nun Mitglied der Gruppe „{$a->groupname}“ im Kurs „<a href="{$a->courseurl}">{$a->coursename}</a>“.';
$string['onecourseenroled'] = 'Sie wurden auf Grundlage Ihres Ergebnisses in „{$a->catscalename}“ in den Kurs „<a href="{$a->courseurl}">{$a->coursename}</a>“ eingeschrieben.';
$string['messageprovider:enrolmentfeedback'] = 'Automatische Einschreibung zu Kursen und Gruppen.';
$string['enrolmentmessagetitle'] = 'Benachrichtigung über neue Kurseinschreibung(en) / Gruppenmitgliedschaft(en)';
$string['groupenrolementstring'] = '„{$a->groupname}“ in Kurs <a href={$a->courseurl}>{$a->coursename}</a>“';
$string['enrolementstringstart'] = 'Auf Grundlage Ihres Ergebnisses im Test „{$a->testname}“ im Kurs „{$a->coursename}“ sind Sie fortan...';
$string['followingcourses'] = 'eingeschrieben in folgenden Kurs bzw. folgende Kurse:<br>';
$string['followinggroups'] = 'Mitglied in folgender Gruppe bzw. folgenden Gruppen:<br>';
$string['enrolementstringstartforfeedback'] = 'Auf Grundlage Ihres Ergebnisses sind Sie fortan...<br>';
$string['enrolementstringend'] = 'Wir wünschen Ihnen viel Erfolg beim weiteren Lernen!';

// Access.
$string['catquiz:canmanage'] = 'Darf Catquiz Plugin verwalten';
$string['catquiz:subscribecatscales'] = 'Darf Skalen abonnieren';
$string['catquiz:manage_catscales'] = 'Darf Skalen verwalten';

// Role.
$string['catquizroledescription'] = 'Catquiz VerwalterIn';

// Capabilities.
$string['catquiz:manage_catcontexts'] = 'Verwalte Einsatz-Kontexte';
$string['catquiz:manage_testenvironments'] = 'Verwalte Testumgebungen';
$string['catquiz:view_teacher_feedback'] = 'Zugriff auf LehrerInnen Feedback';
$string['catquiz:view_users_feedback'] = 'Zugriff auf Feedback von allen UserInnen, nicht nur dem eigenen.';

// Navbar.
$string['managecatscales'] = 'Verwalte Skalen';
$string['test'] = 'Teste Abos';

// Assign testitems to catscale page.
$string['assigntestitemstocatscales'] = "Weise den Skalen Fragen zu";
$string['assign'] = "Ordne zu";
$string['questioncategories'] = 'Fragekategorien';
$string['questiontype'] = 'Fragentyp';
$string['addtestitemtitle'] = 'Testitems zu Skalen hinzufügen';
$string['addtestitembody'] = 'Wollen Sie folgende Testitems der aktuellen Skala zuorden?';
$string['addtestitemsubmit'] = 'Hinzufügen';
$string['addtestitem'] = 'Testitems hinzufügen';
$string['usage'] = 'Übersicht';
$string['failedtoaddmultipleitems'] = '{$a->numadded} Fragen wurden erfolgreich hinzugefügt, bei folgenden {$a->numfailed} Fragen traten Probleme auf: {$a->failedids}';
$string['testiteminrelatedscale'] = 'Testitem ist bereits einer Kind- oder Eltern-Skala zugeordnet';

$string['removetestitemtitle'] = 'Testitems von Skalen entfernen';
$string['removetestitembody'] = 'Wollen Sie folgende Testitems aus aktuellen Skale entfernen? <br> {$a->data}';
$string['removetestitemsubmit'] = 'Entfernen';
$string['removetestitem'] = 'Testitems entfernen';

$string['testitems'] = 'Testitems';
$string['questioncontextattempts'] = '# Testversuche im ausgewählten Einsatz-Kontext';

$string['studentstats'] = 'Nutzende';
$string['notyetcalculated'] = 'Noch nicht berechnet';
$string['notyetattempted'] = 'Ohne Versuch';

// Email Templates.
$string['notificationcatscalechange'] = 'Hallo {$a->firstname} {$a->lastname},
Skalen wurden verändert auf der Moolde Plattform {$a->instancename}.
Dieses e-Mail informiert Sie als CAT Manager*in, verantwortlich für dieses Skala. {$a->editorname} hat die folgenden Änderungen an der Skala „{$a->catscalename}“ vorgenommen.:
    {$a->changedescription}
Sie können den aktuellen Stand hier überprüfen. {$a->linkonscale}';

// Catscale Dashboard.
$string['statistics'] = "Statistik";
$string['models'] = "Modelle";
$string['previewquestion'] = "Fragen Vorschau";
$string['personability'] = "Fähigkeits-Wert";
$string['personabilities'] = "Fähigkeits-Werte";
$string['personabilitytitletab'] = "Ergebnis-Details";
$string['feedback_details_heading'] = "Details zu Ihrem Ergebnis";
$string['feedback_details_description'] = 'Die folgende Tabelle listet alle
    Aspekte (Skalen) von „{$a}“ auf, für die der Test ein valides Ergebnis
    ermitteln konnte';
$string['feedback_details_lowestskill'] = 'Die Skala „<b>{$a->name}</b>“ wurde mit einem
    persönlichen Fähigkeits-Wert {$a->value} (± {$a->se}) als Ihr größtes
    Defizit ermittelt.';
$string['learningprogresstitle'] = "Lernfortschritt";
$string['personabilitiesnodata'] = "Es konnte kein Fähigkeits-Wert errechnet werden";
$string['itemdifficulties'] = "Frage-Schwierigkeiten";
$string['itemdifficultiesnodata'] = "Es konnte keine Frage-Schwierigkeit berechnet werden.";
$string['somethingwentwrong'] = 'Etwas ist schiefgelaufen. Melden Sie den Fehler ihrem Admin';
$string['recalculationscheduled'] = 'Neuberechnung der Kontext-Paremeter wurde veranlasst';
$string['scaledetailviewheading'] = 'Detailansicht der CAT-Skala „{$a}“';

// Table.
$string['label'] = "Kennzeichen";
$string['name'] = "Name";
$string['questiontext'] = "Fragentext";
$string['selectitem'] = "Keine Daten ausgewählt";

// Testitem Dashboard.
$string['testitemdashboard'] = "Fragen Ansicht";
$string['itemdifficulty'] = "Schwierigkeit des Elements";
$string['likelihood'] = "Wahrscheinlichkeit";

$string['difficulty'] = "Schwierigkeit";
$string['discrimination'] = "Trennschärfe";
$string['lastattempttime'] = "Letzter Testversuch";
$string['guessing'] = "Rate-Parameter";

$string['numberofanswers'] = "Antworten";
$string['numberofusagesintests'] = "In verschiedenen Tests";
$string['numberofpersonsanswered'] = "Von Personen";
$string['numberofanswerscorrect'] = "Richtig";
$string['numberofanswersincorrect'] = "Falsch";
$string['numberofanswerspartlycorrect'] = "Teilweise richtig";
$string['averageofallanswers'] = "Durchschnitt";

$string['itemstatus_-5'] = "Manuell ausgeschlossen"; // LOCAL_CATQUIZ_STATUS_EXCLUDED_MANUALLY.
$string['itemstatus_0'] = "Noch nicht berechnet"; // LOCAL_CATQUIZ_STATUS_NOT_CALCULATED.
$string['itemstatus_1'] = "Berechnet"; // LOCAL_CATQUIZ_STATUS_CALCULATED.
$string['itemstatus_4'] = "Manuell gesetzt"; // LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY.
$string['itemstatus_5'] = "Manuell bestätigt"; // LOCAL_CATQUIZ_STATUS_CONFIRMED_MANUALLY.

// Form Validation.
$string['validateform:changevaluesorstatus'] = "Bitte geben Sie Werte ein oder ändern Sie den Status.";
$string['validateform:onlyoneconfirmedstatusallowed'] = "Dieser Status ist nur für jeweils eine Strategie erlaubt.";

// Student Details.
$string['studentdetails'] = "Student details";
$string['enrolled_courses'] = "Eingeschriebene Kurse";
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
$string['catscalesheading'] = "Skalen";
$string['subscribedcatscalesheading'] = "Eingeschriebene Skalen";
$string['summarygeneral'] = "Allgemeines";
$string['summarynumberofassignedcatscales'] = "Anzahl der Ihnen zugeordneten Skalen";
$string['summarynumberoftests'] = "Anzahl der einsetzenden Tests";
$string['summarytotalnumberofquestions'] = "Anzahl der Fragen (insgesamt)";
$string['summarylastcalculation'] = "Letzte (vollständige) Berechnung";
$string['recentevents'] = "Letzte Bearbeitungen";
$string['aria:catscaleimage'] = "Hintergrundmuster für die Skala";
$string['healthstatus'] = "Health-Status";
$string['catmanagernumberofsubscales'] = "Anzahl untergeordneter Skalen";
$string['catmanagernumberofquestions'] = "Anzahl Fragen";
$string['integratequestions'] = "Fragen aus untergeordneten Skalen einbeziehen";
$string['noscaleselected'] = "Keine CAT-Skala gewählt.";
$string['norecordsfound'] = "Keine Fragen in dieser Skala gefunden.";
$string['selectsubscale'] = "Untergeordnete Skala auswählen";
$string['selectcatscale'] = "Skala:";
$string['versionchosen'] = 'ausgewählte Versionierung:';
$string['pleasechoose'] = 'bitte auswählen';
$string['quizattempts'] = 'Testversuche';
$string['calculate'] = 'Berechnen';
$string['noedit'] = 'Editieren beenden';
$string['undefined'] = 'nicht definiert';

// CAT Manager Questions Table.
$string['type'] = 'Typ';
$string['attempts'] = 'Testversuche';
$string['addquestion'] = 'Frage aus Fragenkatalog hinzufügen';
$string['addtest'] = 'Bestehenden Test hinzufügen';
$string['checklinking'] = 'Linking prüfen';
$string['confirmdeletion'] = 'Sie sind dabei das folgende Element zu löschen: <br> „{$a->data}“';
$string['deletedatatitle'] = 'Löschen';
$string['genericsubmit'] = 'Bestätigen';
$string['confirmactivitychange'] = 'Sie sind dabei den Aktivitätsstatus des folgenden Elements zu ändern: <br> „{$a->data}“';
$string['toggleactivity'] = 'Aktivitätsstatus';
$string['errorrecordnotfound'] = 'Fehler mit der Datenbankabfrage. Der Datensatz wurde nicht gefunden.';
$string['trashbintitle'] = 'Element löschen';
$string['cogwheeltitle'] = 'Details anzeigen';
$string['eyeicontitle'] = 'Aktivieren/Deaktivieren';
$string['edititemparams'] = 'Daten ändern';

// Testitem Detail View.
$string['questionpreview'] = 'Fragevorschau';
$string['backtotable'] = 'Zurück zur Übersichts Tabelle';
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
$string['errornoitems'] = "Für die angegebenen Settings kann das Quiz nicht ausgeführt werden. Bitte kontaktieren sie Ihren CAT Manager.";
$string['exceededmaxattempttime'] = "Die erlaubte Zeit für den Versuch wurde überschritten";

// Quiz Feedback.
$string['attemptfeedbacknotavailable'] = "Kein Feedback verfügbar";
$string['attemptfeedbacknotyetavailable'] = "Das Feedback wird angezeigt, sobald der laufende Versuch beendet ist.";
$string['allquestionsincorrect'] = "Ihr Fähigkeits-Wert kann nicht ermittelt werden, da alle Fragen falsch beantwortet wurden.";
$string['allquestionscorrect'] = "Ihr Fähigkeits-Wert kann nicht ermittelt werden, da alle Fragen richtig beantwortet wurden.";
$string['feedbackcomparetoaverage'] = '<p>Der Test misst Ihr Wissen und Können in „{$a->quotedscale}“ in Form eines Fähigkeits-Wertes zwischen
{$a->scale_min} und {$a->scale_max}. Je höher Ihr Fähigkeits-Wert ausfällt, desto besser ist Ihr Wissen und Ihr
Können in der Skala.</p>
<p>Ihr erreichter Fähigkeits-Wert ist <b>{$a->ability_global}</b> (mit einem Standardfehler von ±{$a->se_global}). Der aktuelle
durchschnittliche Fähigkeits-Wert aller Teilnehmenden an dem Test beträgt {$a->average_ability}. {$a->betterthan}</p>
<p>Die folgende Graﬁk stellt Ihren Fähigkeitswert (obere Markierung) und den aktuellen
Durchschnitt (untere Markierung) dar:</p>';
$string['feedbackcomparison_betterthan'] = 'Mit Ihrem Ergebnis sind Sie momentan <b>besser als {$a->quantile}% aller anderen Test-Teilnehmenden</b>.';
$string['questionssummary'] = "Zusammenfassung";
$string['currentability'] = 'Ihr Fähigkeits-Wert';
$string['currentabilityfellowstudents'] = 'Durchschnitt';
$string['feedbackbarlegend'] = "Bedeutung der Farben";
$string['teacherfeedback'] = "Feedback für Lehrende";
$string['catquiz_feedbackheader'] = "Feedback";
$string['catquizfeedbackheader'] = 'Feedback für Skala  	„{$a}“';
$string['noselection'] = "Keine Auswahl";
$string['lowerlimit'] = "Unteres Limit";
$string['upperlimit'] = "Obergrenze";
$string['setcoursesforscaletext'] = 'Legen Sie das Feedback (schriftlichen Rückmeldungen, Kurseinschreibung und/oder Gruppenzuordnung) je Fähigkeits-Stufe für die Skala „{$a}“ fest.';
$string['catcatscaleprime'] = 'Inhaltsbereich (Globalskala)';
$string['catcatscaleprime_help'] = 'Wählen Sie den für Sie relevanten Inhaltsbereich aus. Inhaltsbereche werden als Skala durch eine*n CAT-Manager*in angelegt und verwaltet. Falls Sie eigene Inhalts- und Unterbereiche wünschen, wenden Sie sich bitte an den oder die CAT-Manager*in oder den bzw. die Adminstrator*in Ihrer Moodle-Instanz.';
$string['catcatscales_selectall'] = 'Alle untergeordneten Skalen auswählen';
$string['selectcatcontext_help'] = 'Einsatz-Kontexte differenzieren die Daten hinsichtlich Zielgruppe, Einsatzzweck oder Zeit/Kohorte. Der Einsatz-Kontext wird durch den bzw. die CAT-Manager*in verwaltet. Falls Sie für Ihren Einsatzzweck einen eigenen Einsatz-Kontext wünschen, wenden Sie sich bitte an den oder die CAT-Manager*in oder den bzw. die Adminstrator*in Ihrer Moodle-Instanz.';
$string['includepilotquestions_help'] = 'Im Pilotierungsmodus werden jedem Testversuch eine festzulegende Anzahl an Fragen beigemischt, deren Fragen-Parameter (z.B. Schwierigkeit, Trennschärfe) noch nicht bestimmt sind. Diese tragen nicht zum Test-Ergebnis bei, die durch die Bearbeitungen angefallenen Daten können jedoch durch eine*n CAT-Manager*in zu einem späteren Zeitpunkt zur Bestimmung der Fragen-Parameter statistisch ausgewertet und so der aktuelle Fragen-Pool fortlaufend erweitert werden. (empfohlen)';
$string['catquiz_selectfirstquestion_help'] = 'Dieser Einstellung legt fest, mit welcher Frage ein Testversuch gestartet wird.';
$string['numberoffeedbackoptionpersubscale'] = 'Anzahl der Fähigkeits-Stufen';
$string['feedbacknumber'] = 'Feedback für Fähigkeits-Stufe {$a}';
$string['feedbackrange'] = 'Fähigkeits-Stufe {$a}';
$string['hasability'] = 'Fähigkeit wurde berechnet';
$string['responsesbyusercharttitle'] = 'Gesamtanzahl der gegebenen Antworten pro Person';
$string['noresult'] = 'kein Fähigkeits-Wert ermittelt';
$string['selected_scales_all_ranges_label'] = 'Anzahl der Teilnehmenden';
$string['numberoffeedbackoptionpersubscale_help'] = 'Wählen Sie aus, in wievielen Fähigkeits-Stufen Sie Ihr Feedback differenzieren möchten. Mithilfe der Fähigkeits-Stufen können Sie in Abhängigkeit der ermittelten Fähigkeit für jede Skala Ihren Teilnehmenden unterschiedliche schriftliche Rückmeldungen erteilen, diese in unterschiedliche Kurse einschreiben oder diese unterschiedlichen Gruppen zuordnen.';
$string['choosesubscaleforfeedback'] = 'Skala wählen';
$string['feedbackcompletedpartially'] = '{$a} Feedbacks für diese Skala eingestellt.';
$string['feedbackcompletedentirely'] = 'Alle Feedbacks für diese Skala eingestellt.';
$string['feedbacklegend'] = 'Beschreibung der Fähigkeits-Stufe';
$string['disclaimer:numberoffeedbackchange'] = 'Änderungen erfordern möglicherweise eine Anpassung der Feedbacks.';
$string['feedback_table_questionnumber'] = 'Nr.';
$string['feedback_table_answercorrect'] = "Richtig";
$string['feedback_table_answerincorrect'] = "Falsch";
$string['feedback_table_answerpartlycorrect'] = "Teilweise richtig";
$string['parentscale'] = "Inhaltsbereich (Globalskala)";
$string['seeitemsplayed'] = "Beantwortete Fragen anzeigen";
$string['subfeedbackrange'] = '({$a->lowerlimit} bis {$a->upperlimit})';
$string['greateststrenght:tooltiptitle'] = 'Ihre stärkste Skala „{$a}„';
$string['lowestskill:tooltiptitle'] = 'Ihre schwächste Skala „{$a}';
$string['rootscale:tooltiptitle'] = 'Globalskala „{$a}“';
$string['scaleselected'] = 'Skala „{$a}“';
$string['abilityprofile'] = 'Fähigkeits-Wert in „{$a}“';
$string['feedback_customscale_nofeedback'] = 'Es wurde kein Feedback für ihre Ergebnisse angegeben.';
$string['noscalesfound'] = 'Es konnte zu keiner Skala ein valides Ergebnis ermittelt werden.';
$string['nofeedback'] = 'Kein Feedback angegeben.';
$string['moreinformation'] = 'Weitere Informationen';
$string['comparetotestaverage'] = 'Ihr Ergebnis';
$string['personabilityfeedbacktitle'] = "Fähigkeitsprofil";
$string['estimatedbecause:allanswerscorrect'] = "Sie haben alle Fragen richtig beantwortet! Toll! Leider konnten deshalb Ihre Ergebnisse nicht zuverlässig errechnet werden und wurden geschätzt.";
$string['estimatedbecause:allanswersincorrect'] = "Leider haben Sie alle Fragen falsch beantwortet. Ihre Ergebnisse konnten deshalb nicht zuverlässig errechnet werden und wurden geschätzt.";
$string['estimatedbecause:default'] = "Ihre Ergebnisse konnten nicht zuverlässig errechnet werden und wurden geschätzt.";
$string['error:nminscale'] = "Es konnte leider kein valides Ergebnis ermittelt werden, weil nicht genügend Fragen gespielt wurden.";
$string['error:fraction1'] = "Sie haben alle Fragen richtig beantwortet! Toll! Leider konnte deshalb kein valides Ergebnis ermittelt werden.";
$string['error:fraction0'] = "Leider haben Sie alle Fragen falsch beantwortet. Deshalb kann leider kein valides Ergebnis ermittelt werden.";
$string['error:rootonly'] = ""; // Maybe too complicated to explain / no relevant reason for students.
$string['error:semin'] = ""; // Maybe too complicated to explain / no relevant reason for students.
$string['error:semax'] = ""; // Maybe too complicated to explain / no relevant reason for students.
$string['error:noscalestoreport'] = "Es konnte kein Feedback ermittelt werden, weil kein getesteter Teilbereich für die Berechnung angegewählt wurde."; // Maybe too complicated to explain / no relevant reason for students.


// Chart in Feedback.
$string['global_scale'] = 'Globalskala';
$string['chartlegendabilityrelative'] = '{$a->difference} Unterschied zur Vergleichsskala (Fähigkeits-Wert in dieser Skala: {$a->ability})';
$string['detected_scales_reference'] = 'Vergleichsbasis';
$string['detected_scales_scalename'] = 'Name der Skala';
$string['detected_scales_ability'] = 'Fähigkeits-Wert';
$string['detected_scales_number_questions'] = 'Anzahl Fragen';
$string['personabilitycharttitle'] = 'Differenz Ihrer Fähigkeits-Werte im Vergleich zu {$a}';
$string['itemsplayed'] = 'ausgewertete Fragen:';
$string['detected_scales_chart_description'] = 'Die folgende Grafik stellt die
    Werte im Vergleich zu Ihrem allgemeinen Fähigkeits-Wert in {$a} dar. Durch
    Anklicken des entsprechenden Balkens können Sie die Werte und Skalen-Namen
    einsehen.';
$string['personabilityinscale'] = 'Fähigkeits-Wert für Skala „{$a}“';
$string['yourscorein'] = 'Ihre durchschnittlichen „{$a}“-Ergebnisse';
$string['scoreofpeers'] = 'Durchschnitt aller Ergebnisse';
$string['abilityprofile'] = 'Aktuelle Ergebnisse in {$a}';
$string['abilityprofile_title'] = 'Aktuelle Ergebnisse im Test';
$string['numberofattempts'] = 'Anzahl der Testversuche';
$string['attemptchartstitle'] = 'Anzahl und Ergebnisse der Testversuche für Skala „{$a}“';
$string['labelforrelativepersonabilitychart'] = 'Differenz';
$string['nothingtocompare'] = 'Es sind nicht ausreichend valide Ergebnisse für einen Vergleich vorhanden.';
$string['personabilityrangestring'] = '{$a->rangestart} - {$a->rangeend}';
$string['testinfolabel'] = 'Testinformation';
$string['scalescorechartlabel'] = 'Fähigkeits-Wert in „{$a}“';
$string['chart_detectedscales_title'] = 'Aktuelle Detail-Ergebnisse (Top {$a})';

// Check display line breaks etc.
$string['choosesubscaleforfeedback_help'] = 'Für die angezeigten Skalen können Sie nun {$a} Feedback-Angaben hinterlegen. Wählen Sie die jeweilige (Sub-)Skala an, um Ihr Feedback einzugeben. Die farbigen Symbole zeigen Ihnen den aktuellen Stand der Bearbeitung an, gemessen an den vor Ihnen hinterlegten Anzahl an Feedback-Optionen:
    grau - noch kein Feedback in der Sub-Skala hinterlegt
    gelb - noch einige Feedback-Optionen unausgefüllt
    grün - Feedback vollumfänglich hinterlegt';
$string['choosesubscaleforfeedback_text'] = '';
$string['setfeedbackforscale'] = 'schriftliches Feedback';
$string['setfeedbackforscale_help'] = 'Dieser Text wird den Testteilnehmenden nach Beendigung des Tests angezeigt, sofern das Ergebnis in die eingestellte Fähigkeits-Stufe fällt.';
$string['setgrouprenrolmentforscale'] = 'Einschreibung in eine Gruppe';
$string['groupenrolmenthelptext_help'] = 'Bitte geben Sie den/die genauen Namen existierender Gruppe/n ein (z.B.: „Gruppe1,Gruppe2“ oder „Gruppe3“).';
$string['groupenrolmenthelptext'] = 'Bitte geben Sie den/die genauen Namen existierender Gruppe/n ein (z.B.: „Gruppe1,Gruppe2“ oder „Gruppe3“).';
$string['courseselection'] = 'Kursauswahl';
$string['setgrouprenrolmentforscale_help'] = 'In diese Gruppe des Kurses werden Testteilnehmende nach Beendigung des Tests eingeschrieben, sofern das Ergebnis in die eingestellte Fähigkeits-Stufe fällt. Falls Sie keine Einschreibung in eine Gruppe wünschen, lassen Sie dieses Feld bitte leer.';
$string['setcourseenrolmentforscale'] = 'Einschreibung in einen Kurs';
$string['setcourseenrolmentforscale_help'] = 'In diesen (externen) Kurs werden Testteilnehmende nach Beendigung des Tests eingeschrieben, sofern das Ergebnis in die eingestellte Fähigkeits-Stufe fällt. Sie können nur Kurse auswählen, zu denen Sie die Berechtung zur Einschreibung haben oder die zur Einschreibung durch einen CAT-Manager*in freigegeben wurden. Falls Sie keine Einschreibung in einen externen Kurs wünschen, lassen Sie dieses Feld bitte leer.';
$string['setautonitificationonenrolmentforscale'] = 'Teilnehmende über eine Gruppen- oder Kurseinschreibung mittels Standardtext informieren.';
$string['setautonitificationonenrolmentforscale_help'] = 'Teilnehmende erhalten zusätzlich zu deren schriftlichen Feedback folgenden Hinweis: „Sie wurden automatisch in die Gruppe <Gruppenname> / den Kurs <Kursname als Link> eingeschrieben."';
$string['copysettingsforallsubscales'] = 'Gewählte Einstellungen für untergeordnete Skalen übernehmen';
$string['quizgraphicalsummary'] = 'Quizverlauf';
$string['score'] = 'Gewichteter Fähigkeits-Wert';
$string['response'] = 'Antwort';
$string['abilityintestedscale'] = 'Fähigkeits-Wert in der obersten Skala';
$string['abilityintestedscale_before'] = 'Fähigkeits-Wert in der obersten Skala - davor';
$string['abilityinglobalscale'] = 'Fähigkeits-Wert in der Global-Skala';
$string['fisherinformation'] = 'Fisherinformation';
$string['difficulty_next_easier'] = 'Nächstschwierigere Frage';
$string['difficulty_next_more_difficult'] = 'Nächstleichtere Frage';
$string['scaleiddisplay'] = ' (ID: {$a})';
$string['reportscale'] = 'Skala für den Report der Ergebnisse berücksichtigen';

// Quiz attempts.
$string['catcontext'] = 'Einsatz-Kontext';
$string['totalnumberoftestitems'] = "Gesamtzahl Fragen";
$string['numberoftestitemsused'] = "Anzahl getesteter Fragen";
$string['personabilitybeforeattempt'] = "Fähigkeits-Wert vor Testversuch";
$string['personabilityafterattempt'] = "Fähigkeits-Wert nach Testversuch";
$string['instance'] = "Test";
$string['teststrategy'] = 'Teststrategie';
$string['starttime'] = "Beginn";
$string['endtime'] = "Ende";
$string['feedbacksheader'] = 'Testversuch {$a}';
$string['ownfeedbacksheader'] = 'Mein Testversuch von {$a}';
$string['userfeedbacksheader'] = 'Testversuch {$a->attemptid} von {$a->time}, vorgenommen durch {$a->firstname} {$a->lastname} (Userid: {$a->userid})';
$string['attemptscollapsableheading'] = 'Feedback für Ihre Testversuche:';

// CSV Import Form.
$string['importcsv'] = 'Import CSV';
$string['importsuccess'] = 'Import war erfolgreich. Es wurden {$a} Datensatz/Datensätze bearbeitet.';
$string['importfailed'] = 'Import fehlgeschlagen.';
$string['dateparseformat'] = 'Format des Datums';
$string['dateparseformat_help'] = 'Bitte Datum so wie es im CSV definiert wurde verwenden. Hilfe unter <a href="http://php.net/manual/en/function.date.php">Datumsdokumentation</a> für diese Einstellung.';
$string['defaultdateformat'] = 'j.n.Y H:i:s';
$string['importcolumnsinfos'] = 'Informationen zu Importfeldern:';
$string['mandatory'] = 'verpflichtend';
$string['optional'] = 'optional';
$string['format'] = 'Format';
$string['openformat'] = 'offenes Format';
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
$string['itemassignedtosecondscale'] = 'Datensatz mit componentid {$a->componentid} ist bereits in Skala {$a->scalelink} eingeschrieben und nun zusätzlich in {$a->newscalename}.';
$string['itemassignedtoparentorsubscale'] = 'Datensatz mit componentid {$a->componentid} ist bereits in einer über- oder untergeordneten Skala von {$a->newscalename} eingeschrieben und wird nicht importiert.';
$string['noparentsgiven'] = 'Die Skala {$a->catscalename} ist nicht eindeutig lokalisierbar, weil keine übergeordneten Skalen angegeben wurden. Entsprechender Datensatz nicht importiert/aktualisiert.';
$string['catscaleidnotmatching'] = 'Skalen-ID {$a->catscaleid} wurde nicht in Datenbank gefunden. Entsprechender Datensatz wurde nicht importiert/aktualisiert.';
$string['checkdelimiteroremptycontent'] = 'Überprüfen Sie ob Daten vorhanden und durch das angegebene Zeichen getrennt sind.';
$string['wronglabels'] = 'Die importierten Spaltennamen entsprechen nicht der Vorgabe. {$a} kann nicht importiert werden.';
$string['missinglabel'] = 'Im importierten File fehlt die verpflichtede Spalte {$a}. Daten können nicht importiert werden.';
$string['nolabels'] = 'Keine Spaltennamen definiert.';
$string['checkdelimiter'] = 'Überprüfen Sie die Spaltennamen durch das angegebene Zeichen getrennt sind.';
$string['scaleinformation'] = 'Die ID der Skala der die Frage zugeordnet werden soll.';
$string['scalenameinformation'] = 'Der Name der Skala der die Frage zugeordnet werden soll. Falls keine ID angegeben, wird Matching über Name vorgenommen.';
$string['dataincomplete'] = 'Der Datensatz mit „componentid“ {$a->id} ist unvollständig und konnte nicht gänzlich eingefügt werden. Überprüfen Sie das Feld „{$a->field}“.';
$string['modelinformation'] = 'Dieses Feld ist notwendig, um Fragen vollständig zu erfassen. Ist das Feld leer, kann die Frage lediglich einer Skala zugeordnet werden.';
$string['parentscalenamesinformation'] = 'Präzisieren Sie hier die übergeordneten Skalen der Skala um eine eindeutige Zuordnung zu ermöglichen. Übergeordnete Skalen können beim Import angelegt werden. Starten sie mit dem Namen der höchsten Skala und fügen sie alle Kinder mit „|“ (Vertikaler String Unicode U+007C - nicht zu verwechseln mit „/“ Slash) getrennt hinzu. Vergessen Sie dabei nicht die Globalskala. Für den Import von Items in die Globalskala, geben Sie bitte „0“ in diesem Feld an.';
$string['statusactiveorinactive'] = 'Der Aktivitätsstatus. Geben Sie „1“ an um sicher zu stellen, um den Datensatz von der Verwendung auszuschließen. Lassen Sie das Feld leer oder setzen „0“, gilt der Datensatz als aktiv.';
$string['minscalevalueinformation'] = 'Geben Sie die kleinstmögliche Personenfähigkeit der Skalen als negativen Dezimalwert an. Der Mittelwert ist null. Wert wird nur bei Erzeugung einer neuen Globalskala gesetzt und gilt für alle Sub-Skalen. Hierfür (mind.) im ersten Datensatz angeben. Werte in bestehenden Skalen können nicht via Import verändert werden. Möchten Sie die Werte einer bereits bestehenden Skala ändern, bitte auf das „Skalen“-Tab wechseln.';
$string['maxscalevalueinformation'] = 'Geben Sie die größtmögliche Personenfähigkeit der Skalen als positiven Dezimalwert an. Der Mittelwert ist null. Wert wird nur bei Erzeugung einer neuen Globalskala gesetzt und gilt für alle Sub-Skalen. Hierfür (mind.) im ersten Datensatz angeben. Werte in bestehenden Skalen können nicht via Import verändert werden. Möchten Sie die Werte einer bereits bestehenden Skala ändern, bitte auf das „Skalen“-Tab wechseln.';

// Testenvironments table.
$string['notifyallteachers'] = 'Kursleiter der gewählten Kurse benachrichtigen';
$string['notifyteachersofselectedcourses'] = 'Alle Kursleiter benachrichtigen';

$string['close'] = 'Schließen';

// Shortcodes.
$string['shortcodeslistofquizattempts'] = 'Gibt eine Tabelle mit Testversuchen zurück.';
$string['catquizfeedback'] = 'Zeigt eine Übersicht zu den letzten Testversuchen.';
$string['shortcodescatquizfeedback'] = 'Zeige Feedback zu Versuchen an.';
$string['shortcodescatscalesoverview'] = 'Zeige Übersicht zu CAT-Skalen an.';
$string['shortcodescatquizstatistics'] = 'Zeige Statistiken zu einem CAT Test an';
$string['catquizstatisticsnodata'] = 'Für die angegebenen Paramter können keine Daten gefunden werden';
$string['catquizstatistics_h1_single'] = 'Statistik zu Test {$a}';
$string['catquizstatistics_h2_single'] = 'Die folgenden Daten beziehen sich auf den Test {$a->link}, in dem die Skala {$a->scale} verwendet wird.';
$string['catquizstatistics_h1_scale'] = 'Statistik zu Skala {$a->scalename} in Kurs {$a->coursename}';
$string['catquizstatistics_h2_scale'] = 'Die folgenden Daten beziehen sich auf die Tests {$a->linkedcourses} in Kurs {$a->coursename}, in denen die Skala {$a->scale} verwendet wird.';
$string['catquiz_left_quote'] = '„';
$string['catquiz_right_quote'] = '“';
$string['catquizstatistics_h1_global'] = 'Statistik zu Skala {$a} auf der gesamten moodle Instanz';
$string['catquizstatistics_h2_global'] = 'Die folgenden Daten beziehen sich auf alle Nutzer, die auf dieser
    Moodle-Plattform an Tests teilgenommen haben, in denen die Skala {$a} verwendet wird.';
$string['catquizstatistics_timerange_both'] = 'Nur Daten von {$a->starttime} bis {$a->endtime} werden berücksichtigt.';
$string['catquizstatistics_timerange_start'] = 'Nur Daten ab {$a->starttime} werden berücksichtigt.';
$string['catquizstatistics_timerange_end'] = 'Nur Daten bis {$a->endtime} werden berücksichtigt.';
$string['catquizstatistics_numattempts_title'] = 'Anzahl an Testversuchen';
$string['catquizstatistics_numattemptsperperson_title'] = 'Testversuche pro Person';
$string['catquizstatistics_overview'] = 'Überblick';
$string['catquizstatistics_testusage'] = 'Testnutzung';
$string['catquizstatistics_progress_peers_title'] = 'Durchschnitt';
$string['catquizstatistics_progress_personal_title'] = 'Ihr Fähigkeits-Wert';
$string['catquizstatistics_numberofresponses'] = 'Anzahl der gegebenen Antworten';
$string['catquizstatistics_exportcsv_heading'] = 'Export der Testversuche';
$string['catquizstatistics_exportcsv_description'] = 'Hier können Sie als
    Nutzer mit Berechtigung zum Download eines Exports die Ergebnisse aller Versuche als CSV-Datei
    exportieren.';
$string['catquizstatistics_nodataforcourse'] = 'Für den angegebenen Kurs können keine CAT Tests gefunden werden.';
$string['catquizstatistics_askforparams'] = 'Bitte geben Sie einen „globalscale“ oder „courseid“ Parameter an';
$string['catquizstatistics_scale_testid_conflict'] = 'Der Test zur angegebenen testid verwendet nicht die angegebene Skala';
$string['catquizstatistics_scale_course_conflict'] = 'Die angegebene testid ist nicht im angegebenen Kurs enthalten.';

// Validation.
$string['valuemustbegreaterzero'] = 'Wert muss höher als 0 sein.';

// Breakinfo.
$string['breakinfo_title'] = 'Test pausiert';
$string['breakinfo_description'] = 'Der Test wurde pausiert.';
$string['breakinfo_continue'] = 'Der Test kann um {$a} fortgesetzt werden';
$string['breakinfo_backtotest'] = 'Zurück zum Test';

// CAT Colors.
$string['feedback_colorrange'] = 'Farbbereich auf einer Feedback-Skala';

$string['color_1_name'] = 'Schwarz';
$string['color_2_name'] = 'Dunkelrot';
$string['color_3_name'] = 'Rot';
$string['color_4_name'] = 'Orange';
$string['color_5_name'] = 'Gelb';
$string['color_6_name'] = 'Hellgrün';
$string['color_7_name'] = 'Dunkelgrün';
$string['color_8_name'] = 'Weiß';

$string['color_1_code'] = '#000000';
$string['color_2_code'] = '#8b0000';
$string['color_3_code'] = '#ff0000';
$string['color_4_code'] = '#ffa500';
$string['color_5_code'] = '#ffff00';
$string['color_6_code'] = '#90ee90';
$string['color_7_code'] = '#006400';
$string['color_8_code'] = '#e8e9eb';

// Stringdates.
$string['stringdate:day'] = '{$a}';
$string['stringdate:week'] = 'KW {$a}';
$string['stringdate:month:01'] = 'Januar';
$string['stringdate:month:02'] = 'Februar';
$string['stringdate:month:03'] = 'März';
$string['stringdate:month:04'] = 'April';
$string['stringdate:month:05'] = 'Mai';
$string['stringdate:month:06'] = 'Juni';
$string['stringdate:month:07'] = 'Juli';
$string['stringdate:month:08'] = 'August';
$string['stringdate:month:09'] = 'September';
$string['stringdate:month:10'] = 'Oktober';
$string['stringdate:month:11'] = 'November';
$string['stringdate:month:12'] = 'Dezember';
$string['stringdate:quarter'] = 'Q{$a->q} {$a->y}';

// Cache Definitions.
$string['cachedef_adaptivequizattempt'] = 'Ausführung eines Adaptive Quiz.';
$string['cachedef_catcontexts'] = 'Contexte von catquiz';
$string['cachedef_eventlogtable'] = 'Logs von Events';
$string['cachedef_quizattempts'] = 'Ausführung eines Quiz';
$string['cachedef_studentstatstable'] = 'Daten von Nutzenden';
$string['cachedef_testenvironments'] = 'Testumgebung';
$string['cachedef_testitemstable'] = 'Daten zu Testitems in Tabelle';
$string['cachedef_teststrategies'] = 'Teststrategien';
