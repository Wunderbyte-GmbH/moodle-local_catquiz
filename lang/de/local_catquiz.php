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

$string['abilityinglobalscale'] = 'Fähigkeits-Wert in der Global-Skala';
$string['abilityintestedscale'] = 'Fähigkeits-Wert in der obersten Skala';
$string['abilityintestedscale_before'] = 'Fähigkeits-Wert in der obersten Skala - davor';
$string['abilityprofile'] = 'Aktuelle Ergebnisse in „{$a}“';
$string['abilityprofile_title'] = 'Aktuelle Ergebnisse im Test';
$string['abortpersonabilitynotchanged'] = 'Personenparameter unverändert';
$string['acceptedstandarderror'] = 'akzeptierter Standardfehler';
$string['acceptedstandarderror_help'] = 'Sobald der Standardfehler einer CAT-Skala außerhalb dieser Werte fällt, wird sie nicht weiter getestet.';
$string['action'] = 'Action';
$string['activemodel'] = 'Ausgewähltes Modell';
$string['activitystatussetactive'] = 'Die Frage ist jetzt aktiviert.';
$string['activitystatussetinactive'] = 'Die Frage ist jetzt deaktiviert.';
$string['add_testitem_to_scale'] = '{$a->testitemlink} wurde {$a->catscalelink} hinzugefügt.';
$string['addcategory'] = 'Kategorie hinzufügen';
$string['addcontext'] = 'Einsatz-Kontext hinzufügen';
$string['addedrecords'] = '{$a} Eintrag/Einträge hinzugefügt.';
$string['addoredittemplate'] = 'Einstellungs-Vorlage bearbeiten';
$string['addquestion'] = 'Frage aus Fragenkatalog hinzufügen';
$string['addtest'] = 'Bestehenden Test hinzufügen';
$string['addtestitem'] = 'Testitems hinzufügen';
$string['addtestitembody'] = 'Wollen Sie folgende Testitems der aktuellen CAT-Skala zuorden?';
$string['addtestitemsubmit'] = 'Hinzufügen';
$string['addtestitemtitle'] = 'Testitems zu Skalen hinzufügen';
$string['allquestionscorrect'] = 'Ihr Fähigkeits-Wert kann nicht ermittelt werden, da alle Fragen richtig beantwortet wurden.';
$string['allquestionsincorrect'] = 'Ihr Fähigkeits-Wert kann nicht ermittelt werden, da alle Fragen falsch beantwortet wurden.';
$string['applychanges'] = 'Änderungen übernehmen';
$string['aria:catscaleimage'] = 'Hintergrundmuster für die CAT-Skala';
$string['assign'] = 'Ordne zu';
$string['assigntestitemstocatscales'] = 'Weise den Skalen Fragen zu';
$string['attempt_completed'] = 'Testversuch abgeschlossen';
$string['attemptchartstitle'] = 'Anzahl und Ergebnisse der Testversuche für Skala „{$a}“';
$string['attemptclosedbytimelimit'] = 'Versuch wurde wegen Zeitüberschreitung automatisch beendet';
$string['attemptfeedbacknotavailable'] = 'Kein Feedback verfügbar';
$string['attemptfeedbacknotyetavailable'] = 'Das Feedback wird angezeigt, sobald der laufende Versuch beendet ist.';
$string['attempts'] = 'Testversuche';
$string['attemptscollapsableheading'] = 'Feedback für Ihre Testversuche:';
$string['attemptstatus_-1'] = 'Versuchsabschluss ausstehend';
$string['attemptstatus_0'] = 'OK';
$string['attemptstatus_1'] = 'Item-Pool erschöpft';
$string['attemptstatus_2'] = 'ERROR_TESTITEM_ALREADY_IN_RELATED_SCALE';
$string['attemptstatus_3'] = 'ERROR_FETCH_NEXT_QUESTION';
$string['attemptstatus_4'] = 'Max. Fragezahl erreicht';
$string['attemptstatus_5'] = 'ABORT_PERSONABILITY_NOT_CHANGED';
$string['attemptstatus_6'] = 'ERROR_EMPTY_FIRST_QUESTION_LIST';
$string['attemptstatus_7'] = 'ERROR_NO_ITEMS';
$string['attemptstatus_8'] = 'Bearbeitungszeit abgelaufen';
$string['autocontextdescription'] = 'Automatisch durch einen Import generiert für CAT-Skala „{$a}“.';
$string['automatic_reload_on_scale_selection'] = 'Bei (Sub-)Skalenauswahl Formular neu laden';
$string['automatic_reload_on_scale_selection_description'] = 'Bei (Sub-)Skalenauswahl automatisch das Quizsettings-Formular neu laden';
$string['automaticallygeneratedbycron'] = 'Cron Job (automatisch durchgeführt)';
$string['averageofallanswers'] = 'Durchschnitt';
$string['backtotable'] = 'Zurück zur Übersichts Tabelle';
$string['breakinfo_backtotest'] = 'Zurück zum Test';
$string['breakinfo_continue'] = 'Der Test kann um {$a} fortgesetzt werden';
$string['breakinfo_description'] = 'Der Test wurde pausiert.';
$string['breakinfo_title'] = 'Test pausiert';
$string['cachedef_adaptivequizattempt'] = 'Ausführung eines Adaptive Quiz.';
$string['cachedef_catcontexts'] = 'Contexte von catquiz';
$string['cachedef_catscales'] = 'Speichert (Cache) die Skalen von catquiz';
$string['cachedef_eventlogtable'] = 'Logs von Events';
$string['cachedef_quizattempts'] = 'Ausführung eines Quiz';
$string['cachedef_studentstatstable'] = 'Daten von Nutzenden';
$string['cachedef_testenvironments'] = 'Testumgebung';
$string['cachedef_testitemstable'] = 'Daten zu Testitems in Tabelle';
$string['cachedef_teststrategies'] = 'Teststrategien';
$string['calculate'] = 'Berechnen';
$string['calculation_executed'] = 'Berechnung durchgeführt.';
$string['calculation_skipped'] = 'Berechnung wurde nicht durchgeführt.';
$string['calculations'] = 'Berechnungen';
$string['callbackfunctionnotapplied'] = 'Callback Funktion konnte nicht angewandt werden.';
$string['callbackfunctionnotdefined'] = 'Callback Funktion nicht definiert.';
$string['canbesetto0iflabelgiven'] = 'Kann 0 sein, wenn Abgleich über Label stattfindet.';
$string['cancelexpiredattempts'] = 'Abgelaufene Versuche schließen';
$string['cannotdeletedefaultcontext'] = 'Der Default CAT Kontext kann nicht gelöscht werden';
$string['cannotdeletescalewithchildren'] = 'Skalen mit Unterskalen können nicht gelöscht werden.';
$string['catcatscaleprime'] = 'Inhaltsbereich (Globalskala)';
$string['catcatscaleprime_help'] = 'Wählen Sie den für Sie relevanten Inhaltsbereich aus. Inhaltsbereche werden als CAT-Skala durch eine*n CAT-Manager*in angelegt und verwaltet. Falls Sie eigene Inhalts- und Unterbereiche wünschen, wenden Sie sich bitte an den oder die CAT-Manager*in oder den bzw. die Adminstrator*in Ihrer Moodle-Instanz.';
$string['catcatscales'] = 'Auswahl untergeordnete Skalen';
$string['catcatscales_help'] = 'Wählen Sie die für Sie die für Sie relevanten untergeordneten CAT-Skalen an und ab. Eine untergeordnete CAT-Skala umfasst Fragen aus einen Teil des gewählten Inhaltsbereichs. In einem Test-Versuch werden nur Fragen aus den angewählten Skalen verwendet.';
$string['catcatscales_selectall'] = 'Alle untergeordneten Skalen auswählen';
$string['catcontext'] = 'Einsatz-Kontext';
$string['catmanager'] = 'CAT-Manager';
$string['catmanagernumberofquestions'] = 'Anzahl Fragen';
$string['catmanagernumberofsubscales'] = 'Anzahl untergeordneter Skalen';
$string['catquiz'] = 'CAT-Manager';
$string['catquiz:canmanage'] = 'Darf Catquiz Plugin verwalten';
$string['catquiz:manage_catcontexts'] = 'Verwalte Einsatz-Kontexte';
$string['catquiz:manage_catscales'] = 'Darf Skalen verwalten';
$string['catquiz:manage_testenvironments'] = 'Verwalte Testumgebungen';
$string['catquiz:subscribecatscales'] = 'Darf Skalen abonnieren';
$string['catquiz:view_teacher_feedback'] = 'Zugriff auf LehrerInnen Feedback';
$string['catquiz:view_users_feedback'] = 'Zugriff auf Feedback von allen UserInnen, nicht nur dem eigenen.';
$string['catquiz_feedbackheader'] = 'Feedback';
$string['catquiz_left_quote'] = '„';
$string['catquiz_right_quote'] = '“';
$string['catquiz_selectfirstquestion'] = 'ansonsten beginne ...';
$string['catquiz_selectfirstquestion_help'] = 'Diese Einstellung legt fest, mit welcher Frage ein Testversuch gestartet wird.';
$string['catquiz_selectteststrategy'] = 'Testzweck';
$string['catquiz_teststrategyheader'] = 'CAT-Einstellungen';
$string['catquizfeedback'] = 'Zeigt eine Übersicht zu den letzten Testversuchen.';
$string['catquizfeedbackheader'] = 'Feedback für Skala „{$a}“';
$string['catquizroledescription'] = 'CAT-Manager/innen haben Zugriff auf das Backend zum Verwalten von CAT-Skalen und haben Einblick in instanzweite Statistiken.';
$string['catquizsettings'] = 'Test-Inhalt und Einsatz-Kontext';
$string['catquizstatistics_askforparams'] = 'Bitte geben Sie die Parameter „globalscale“ oder „courseid“ an';
$string['catquizstatistics_exportcsv_description'] = 'Hier können Sie als
    Nutzer mit Berechtigung zum Download eines Exports die Ergebnisse aller Versuche als CSV-Datei
    exportieren.';
$string['catquizstatistics_exportcsv_heading'] = 'Export der Testversuche';
$string['catquizstatistics_h1_global'] = 'Statistik zu Skala „{$a}“ auf der gesamten moodle Instanz';
$string['catquizstatistics_h1_scale'] = 'Statistik zu Skala „{$a->scalename}“ in Kurs „{$a->coursename}“';
$string['catquizstatistics_h1_single'] = 'Statistik zu Test „{$a}“';
$string['catquizstatistics_h2_global'] = 'Die folgenden Daten beziehen sich auf alle Nutzer, die auf dieser
    Moodle-Plattform an Tests teilgenommen haben, in denen die Skala „{$a}“ verwendet wird.';
$string['catquizstatistics_h2_scale'] = 'Die folgenden Daten beziehen sich auf die Tests „{$a->linkedcourses}“ im Kurs „{$a->coursename}“,
    in denen die Skala „{$a->scale}“ verwendet wird.';
$string['catquizstatistics_h2_single'] = 'Die folgenden Daten beziehen sich auf den Test „{$a->link}“, in dem die Skala „{$a->scale}“
    verwendet wird.';
$string['catquizstatistics_nodataforcourse'] = 'Für den angegebenen Kurs können keine CAT Tests gefunden werden.';
$string['catquizstatistics_numattempts_title'] = 'Anzahl an Testversuchen';
$string['catquizstatistics_numattemptsperperson_title'] = 'Personen im Kurs und deren Anzahl an Testversuchen';
$string['catquizstatistics_numberofresponses'] = 'Anzahl der gegebenen Antworten';
$string['catquizstatistics_overview'] = 'Überblick';
$string['catquizstatistics_progress_peers_title'] = 'Durchschnitt';
$string['catquizstatistics_progress_personal_title'] = 'Ihr Fähigkeits-Wert';
$string['catquizstatistics_scale_course_conflict'] = 'Die angegebene testid ist nicht im angegebenen Kurs enthalten.';
$string['catquizstatistics_scale_testid_conflict'] = 'Der Test zur angegebenen Test-ID verwendet nicht die angegebene Skala';
$string['catquizstatistics_testusage'] = 'Testnutzung';
$string['catquizstatistics_timerange_both'] = 'Nur Daten von {$a->starttime} bis {$a->endtime} werden berücksichtigt.';
$string['catquizstatistics_timerange_end'] = 'Nur Daten bis {$a->endtime} werden berücksichtigt.';
$string['catquizstatistics_timerange_start'] = 'Nur Daten ab {$a->starttime} werden berücksichtigt.';
$string['catquizstatisticsnodata'] = 'Aktuell liegen hierfür (noch) keine Daten vor.';
$string['catscale'] = 'Skala';
$string['catscale_created'] = 'Skala erzeugt';
$string['catscale_updated'] = 'Skala aktualisert';
$string['catscaleid'] = 'Skala ID';
$string['catscaleidnotmatching'] = 'Skalen-ID {$a->catscaleid} wurde nicht in Datenbank gefunden. Entsprechender Datensatz wurde nicht importiert/aktualisiert.';
$string['catscales'] = 'CAT-Skalen verwalten';
$string['catscales:information'] = 'Verwalte CAT-Skalen: {$a->link}';
$string['catscalesheading'] = 'Skalen';
$string['catscalesname_exists'] = 'Der Name wird bereits verwendet';
$string['catscaleupdatedtitle'] = 'Eine CAT-Skala wurde aktualisiert';
$string['cattags'] = 'Kurs Tags verwalten';
$string['cattags:information'] = 'Diese Tags kennzeichnen Kurse, zu denen Nutzende einschreiben können, unabhängig davon, ob sie Teil des Kurses sind.';
$string['central_host'] = 'Host der zentralen Berechnungsinstanz';
$string['central_host_desc'] = 'Z.B. https://www.example.com';
$string['central_scale'] = 'Synchronisierte Skala';
$string['central_scale_desc'] = 'TODO: autocomplate - now integer ID. Parameter dieser Skala werden mit der zentralen Berechnungsinstanz synchronisiert.';
$string['central_token'] = 'Token zum Zugriff auf die zentrale Berechnungsinstanz';
$string['central_token_desc'] = 'Das webtoken, das für sie auf der zentralen Berechnungsinstanz eingerichtet worden ist';
$string['chart_detectedscales_title'] = 'Die {$a} aktuell am häufigsten zurückgemeldeten Teilbereiche';
$string['chartlegendabilityrelative'] = '{$a->difference} Unterschied zur Vergleichsskala (Fähigkeits-Wert in dieser Skala: {$a->ability})';
$string['checkdelimiter'] = 'Überprüfen Sie die Spaltennamen durch das angegebene Zeichen getrennt sind.';
$string['checkdelimiteroremptycontent'] = 'Überprüfen Sie ob Daten vorhanden und durch das angegebene Zeichen getrennt sind.';
$string['checklinking'] = 'Linking prüfen';
$string['choosecontextid'] = 'Einsatz-Kontext auswählen';
$string['chooseparent'] = 'Wähle übergeordnete Scala';
$string['choosesubscaleforfeedback'] = 'Skala wählen';
$string['choosesubscaleforfeedback_help'] = 'Für die angezeigten CAT-Skalen können Sie nun {$a} Feedback-Angaben hinterlegen. Wählen Sie die jeweilige (Sub-)Skala an, um Ihr Feedback einzugeben. Die farbigen Symbole zeigen Ihnen den aktuellen Stand der Bearbeitung an, gemessen an den vor Ihnen hinterlegten Anzahl an Feedback-Optionen:
    grau - noch kein Feedback in der Sub-Skala hinterlegt
    gelb - noch einige Feedback-Optionen unausgefüllt
    grün - Feedback vollumfänglich hinterlegt';
$string['choosesubscaleforfeedback_text'] = '';
$string['choosetags'] = 'Tag(s) auswählen';
$string['choosetags:disclaimer'] = 'Mehrfachauswahl mit „⌘ command“ (Apple) oder „Ctrl“ (Windows, Linux)';
$string['choosetemplate'] = 'Einstellungs-Vorlage wählen';
$string['classicalcat'] = 'Klassischer Test';
$string['close'] = 'Schließen';
$string['cogwheeltitle'] = 'Details anzeigen';
$string['color_1_code'] = '#000000';
$string['color_1_name'] = 'Schwarz';
$string['color_2_code'] = '#8b0000';
$string['color_2_name'] = 'Dunkelrot';
$string['color_3_code'] = '#ff0000';
$string['color_3_name'] = 'Rot';
$string['color_4_code'] = '#ffa500';
$string['color_4_name'] = 'Orange';
$string['color_5_code'] = '#ffff00';
$string['color_5_name'] = 'Gelb';
$string['color_6_code'] = '#90ee90';
$string['color_6_name'] = 'Hellgrün';
$string['color_7_code'] = '#006400';
$string['color_7_name'] = 'Dunkelgrün';
$string['color_8_code'] = '#e8e9eb';
$string['color_8_name'] = 'Weiß';
$string['comparetotestaverage'] = 'Ihr Ergebnis';
$string['complete_attempt_description'] = 'Testversuch mit ID {$a->attemptid} in CAT-Skala {$a->catscalelink} durchgeführt von User {$a->userid}.';
$string['component'] = 'Plugin';
$string['confirmactivitychange'] = 'Sie sind dabei den Aktivitätsstatus des folgenden Elements zu ändern: <br> „{$a->data}“';
$string['confirmdeletion'] = 'Sie sind dabei das folgende Element zu löschen: <br> „{$a->data}“';
$string['context_created'] = 'Einsatz-Kontext erzeugt';
$string['context_updated'] = 'Einsatz-Kontext aktualisiert';
$string['contextidselect'] = 'Einsatz-Kontext - ohne Auswahl wird ein neuer Einsatz-Kontext erstellt';
$string['copysettingsforallsubscales'] = 'Gewählte Einstellungen für untergeordnete Skalen übernehmen';
$string['courseselection'] = 'Kursauswahl';
$string['create_catscale_description'] = 'Skala „{$a->catscalelink}“ mit der ID {$a->objectid} erzeugt.';
$string['create_context_description'] = 'Einsatz-Kontext {$a} erzeugt.';
$string['createcatscale'] = 'Erstellen Sie eine CAT-Skala';
$string['createnewcatscale'] = 'Neue CAT-Skala erstellen';
$string['csvexportheader:attemptduration'] = 'Dauer';
$string['csvexportheader:attemptend'] = 'Endzeit';
$string['csvexportheader:attemptid'] = 'Versuch-ID';
$string['csvexportheader:attemptquestionno'] = 'Anz. Fragen gesamt';
$string['csvexportheader:attemptstart'] = 'Startzeit';
$string['csvexportheader:attemptstatus'] = 'Status';
$string['csvexportheader:resultppdetail'] = 'PP Ergebnisskala';
$string['csvexportheader:resultppglobal'] = 'PP global';
$string['csvexportheader:resultscaledetail'] = 'Ergebnis-Skala [je Strategie]';
$string['csvexportheader:resultscaleglobal'] = 'Globalskala';
$string['csvexportheader:resultsedetail'] = 'SE Ergebnisskala';
$string['csvexportheader:resultseglobal'] = 'SE global';
$string['csvexportheader:testid'] = 'Test-ID';
$string['csvexportheader:teststrategy'] = 'Strategie';
$string['csvexportheader:useremail'] = 'User-E-Mail';
$string['csvexportheader:userid'] = 'User-ID';
$string['csvexportheader:username'] = 'User-Name';
$string['currentability'] = 'Ihr Fähigkeits-Wert';
$string['currentabilityfellowstudents'] = 'Durchschnitt';
$string['dataincomplete'] = 'Der Datensatz mit „componentid“ {$a->id} ist unvollständig und konnte nicht gänzlich eingefügt werden. Überprüfen Sie das Feld „{$a->field}“.';
$string['dateparseformat'] = 'Format des Datums';
$string['dateparseformat_help'] = 'Bitte Datum so wie es im CSV definiert wurde verwenden. Hilfe unter <a href="http://php.net/manual/en/function.date.php">Datumsdokumentation</a> für diese Einstellung.';
$string['daysago'] = 'Vor {$a} Tagen';
$string['debuginfo_desc'] = 'Hier können Sie als Nutzer mit Berechtigung zum Download eines Exports den Versuchsverlauf als CSV-Datei exportieren.';
$string['debuginfo_desc_title'] = 'Export des Testversuchs Nr. {$a}';
$string['defaultcontext'] = 'Neuer Standard Einsatz-Kontext für Skala';
$string['defaultcontextdescription'] = 'Beinhaltet alle Testitems';
$string['defaultcontextmissing'] = 'Es konnte kein Standard Kontext in der Datenbank gefunden werden. Bitte gehen Sie sicher, dass '
    . 'der Installationsprozess erfolgreich abgeschlossen wurde.';
$string['defaultcontextname'] = 'Standard Kontext';
$string['defaultdateformat'] = 'j.n.Y H:i:s';
$string['deletedatatitle'] = 'Löschen';
$string['deletedcatscale'] = 'Skala die nicht mehr exisitiert';
$string['detected_scales_ability'] = 'Fähigkeits-Wert';
$string['detected_scales_chart_description'] = 'Die folgende Grafik stellt die
    Werte im Vergleich zu Ihrem allgemeinen Fähigkeits-Wert in {$a} dar. Durch
    Anklicken des entsprechenden Balkens können Sie die Werte und Skalen-Namen
    einsehen.';
$string['detected_scales_number_questions'] = 'Anzahl Fragen';
$string['detected_scales_reference'] = 'Vergleichsbasis';
$string['detected_scales_scalename'] = 'Name der Skala';
$string['difficulties'] = 'Schwierigkeiten';
$string['difficulty'] = 'Schwierigkeit';
$string['difficulty_next_easier'] = 'Nächstschwierigere Frage';
$string['difficulty_next_more_difficult'] = 'Nächstleichtere Frage';
$string['disclaimer:numberoffeedbackchange'] = 'Änderungen erfordern möglicherweise eine Anpassung der Feedbacks.';
$string['discrimination'] = 'Trennschärfe';
$string['documentation'] = 'Dokumentation';
$string['downloaddemofile'] = 'Demofile herunterladen';
$string['duration'] = 'Dauer';
$string['edititemparams'] = 'Daten ändern';
$string['edittestenvironment'] = 'Bearbeite Testumgebung';
$string['emptyfirstquestionlist'] = 'Kann keine Startfrage wählen da die Liste leer ist';
$string['endtime'] = 'Ende';
$string['endtimestamp'] = 'Zeitraum Ende';
$string['enrol_only_to_reported_scales'] = 'Benutzer nur in Kurse von detektierter Skala einschreiben';
$string['enrol_only_to_reported_scales_help'] = 'Standardmäßig werden die Benutzer nach den Ergebnissen in den Bereichen eingeschrieben, die entsprechend dem Zweck des Tests ermittelt wurden.
Wenn Sie diese Option deaktivieren, werden die Benutzer auch entsprechend aller anderen gültigen Ergebnissen eingeschrieben.';
$string['enrolementstringend'] = 'Wir wünschen Ihnen viel Erfolg beim weiteren Lernen!';
$string['enrolementstringstart'] = 'Auf Grundlage Ihres Ergebnisses im Test „{$a->testname}“ im Kurs „{$a->coursename}“ sind Sie fortan...';
$string['enrolementstringstartforfeedback'] = 'Auf Grundlage Ihres Ergebnisses sind Sie fortan...<br>';
$string['enrolled_courses'] = 'Eingeschriebene Kurse';
$string['enrolmentmessagetitle'] = 'Benachrichtigung über neue Kurseinschreibung(en) / Gruppenmitgliedschaft(en)';
$string['error'] = 'Es ist ein Fehler aufgetreten';
$string['errors'] = 'Fehler';
$string['error:fraction0'] = 'Leider konnten wir aufgrund Ihrer Antworten kein zuverlässiges Ergebnis ermitteln.
    Wir würden uns freuen, wenn Sie es erneut versuchen.';
$string['error:fraction1'] = 'Herzlichen Glückwunsch, Sie haben alle Fragen richtig beantwortet! Das ist wirklich großartig!
    Aufgrund dieser exzellenten Leistung konnte jedoch kein eindeutiges Ergebnis ermittelt werden.';
$string['error:minmaxrangeequal'] = 'Es liegt ein Fehler in den Einstellungen zu den genutzten CAT-Skalen vor: Die minimalen und maximale Skalenbegrenzungen
    sind identisch. Bitte melden Sie das Problem unter Nennung des von Ihnen genutzten Tests dem bzw. der CAT-Manager*in mit der Bitte,
    die Angaben zu den Skalen-Begrenzungen zu korrigieren.';
$string['error:nminscale'] = 'Leider konnten wir kein Ergebnis ermitteln, da im Testversuch nicht genügend Fragen beantwortet wurden.
    Bitte stellen Sie bei Ihrem nächsten Versuch sicher, alle Fragen zu beantworten, um ein vollständiges Ergebnis zu erhalten.';
$string['error:noscalestoreport'] = 'Leider konnten wir mit der aktuellen Anzahl an gestellten Fragen in den getesteten Bereichen
kein verlässliches Ergebnis ermitteln. Wir empfehlen Ihnen, sich an die Verantwortlichen für den Test zu wenden und zu bitten,
die Anzahl der zu beantwortenden Fragen zu erhöhen.';
$string['error:permissionforcsvdownload'] = 'Ihnen fehlt die notwendige Berechtigung ({$a}), die gewünschte Informationen herunterzuladen.';
$string['error:rootonly'] = '';
$string['error:semax'] = 'Leider konnten wir in den getesteten Bereichen kein Ergebnis mit der vorgegebenen Mindestgenauigkeit ermitteln.
    Wir empfehlen Ihnen, sich an die Verantwortlichen für den Test zu wenden und zu bitten, die Anzahl der zu beantwortenden Fragen zu erhöhen.';
$string['error:semin'] = '';
$string['errorfetchnextquestion'] = 'Es trat ein Fehler bei der Auswahl der nächsten Frage auf.';
$string['errorhastobefloat'] = 'Muss ein Dezimalwert sein';
$string['errorhastobeint'] = 'Muss eine Ganzzahl sein';
$string['errorminscalevalue'] = 'Der Minimalwert muss kleiner sein als der Maximalwert der CAT-Skala';
$string['errornoitems'] = 'Für die angegebenen Settings kann das Quiz nicht ausgeführt werden. Bitte kontaktieren sie Ihren CAT-Manager.';
$string['errorrecordnotfound'] = 'Fehler mit der Datenbankabfrage. Der Datensatz wurde nicht gefunden.';
$string['errorupperlimitvalue'] = 'Oberes Limit muss kleiner als unteres Limit sein.';
$string['estimatedbecause:allanswerscorrect'] = 'Sie haben alle Fragen richtig beantwortet! Toll! Leider konnten deshalb Ihre Ergebnisse nicht
    zuverlässig errechnet werden und wurden geschätzt.';
$string['estimatedbecause:allanswersincorrect'] = 'Leider haben Sie alle Fragen falsch beantwortet. Ihre Ergebnisse konnten deshalb nicht zuverlässig
    errechnet werden und wurden geschätzt.';
$string['estimatedbecause:default'] = 'Ihre Ergebnisse konnten nicht zuverlässig errechnet werden und wurden geschätzt.';
$string['eventname'] = 'Name des Ereignisses';
$string['eventtime'] = 'Zeitpunkt des Ereignisses';
$string['exceededmaxattempttime'] = 'Die erlaubte Zeit für den Versuch wurde überschritten';
$string['executed_calculation_description'] = 'Es wurde eine Berechnung der CAT-Skala „{$a->catscalename}“ mit der ID {$a->catscaleid} im Kontext
    {$a->contextid} durchgeführt von {$a->user}. In folgenden Modellen wurden Fragen neu berechnet: {$a->updatedmodels}';
$string['eyeicontitle'] = 'Aktivieren/Deaktivieren';
$string['failedtoaddmultipleitems'] = '{$a->numadded} Fragen wurden erfolgreich hinzugefügt, bei folgenden {$a->numfailed} Fragen traten Probleme auf: {$a->failedids}';
$string['feedback_colorrange'] = 'Farbbereich auf einer Feedback-Skala';
$string['feedback_customscale_nofeedback'] = 'Es wurde kein Feedback für ihre Ergebnisse angegeben.';
$string['feedback_details_description'] = 'Die folgende Tabelle listet alle
    Teilbereiche (Skalen) von „{$a}“ auf, für die der Test ein zuverlässiges Ergebnis
    ermitteln konnte';
$string['feedback_details_heading'] = 'Details zu Ihrem Ergebnis';
$string['feedback_details_highestskill'] = 'Teilbereich (Skala) „<b>{$a->name}</b>“ wurde mit einem
    persönlichen Fähigkeits-Wert von {$a->value} (± {$a->se}) als Ihre größte
    Stärke ermittelt.';
$string['feedback_details_lowestskill'] = 'Teilbereich (Skala) „<b>{$a->name}</b>“ wurde mit einem
    persönlichen Fähigkeits-Wert von {$a->value} (± {$a->se}) als Ihr größtes
    Defizit ermittelt.';
$string['feedback_tab_clicked'] = 'Klick auf Feedback Tab';
$string['feedback_tab_clicked_description'] = 'Nutzer {$a->userid} hat auf Feedback {$a->feedback_translated} in {$a->attemptlink} geklickt';
$string['feedback_table_answercorrect'] = 'Richtig';
$string['feedback_table_answerincorrect'] = 'Falsch';
$string['feedback_table_answerpartlycorrect'] = 'Teilweise richtig';
$string['feedback_table_questionnumber'] = 'Nr.';
$string['feedbackbarlegend'] = 'Bedeutung der Farben';
$string['feedbackcomparetoaverage'] = '<p>Der Test misst Ihr Wissen und Können in „{$a->quotedscale}“ in Form eines Fähigkeits-Wertes zwischen
{$a->scale_min} und {$a->scale_max}. Je höher Ihr Fähigkeits-Wert ausfällt, desto besser ist Ihr Wissen und Ihr
Können in der Skala.</p>
<p>Ihr erreichter Fähigkeits-Wert ist <b>{$a->ability_global}</b> (mit einem Standardfehler von ±{$a->se_global}). Der aktuelle
durchschnittliche Fähigkeits-Wert aller Teilnehmenden an dem Test beträgt {$a->average_ability}. {$a->betterthan}</p>
<p>Die folgende Graﬁk stellt Ihren Fähigkeitswert (obere Markierung) und den aktuellen
Durchschnitt (untere Markierung) dar:</p>';
$string['feedbackcomparison_betterthan'] = 'Mit Ihrem Ergebnis sind Sie momentan <b>besser als {$a->quantile}% aller anderen Test-Teilnehmenden</b>.';
$string['feedbackcompletedentirely'] = 'Alle Feedbacks für diese CAT-Skala hinterlegt.';
$string['feedbackcompletedpartially'] = '{$a} Feedbacks für diese Skala hinterlegt.';
$string['feedbacklegend'] = 'Beschreibung der Fähigkeits-Stufe';
$string['feedbacknumber'] = 'Feedback für Fähigkeits-Stufe {$a}';
$string['feedbackrange'] = 'Fähigkeits-Stufe {$a}';
$string['feedbacksheader'] = 'Testversuch {$a}';
$string['fetchempty'] = 'Parameter sind am aktuellsten Stand';
$string['fetchingparameters'] = 'Parameter werden von der zentralen Instanz abgerufen...';
$string['fetchparamheading'] = 'Parameter werden von {$a} abgerufen';
$string['fetchsuccess'] = 'Es wurden {$a->num} Parameter in neuem Kontext {$a->contextname} gespeichert';
$string['fieldnamesdontmatch'] = 'Die importierten Spaltennamen entsprechen nicht der Vorgabe.';
$string['firstquestion_startnewtest'] = 'Beginne neuen Test';
$string['firstquestionreuseexistingdata'] = 'mit Ergebnisdaten aus vorherigen Testversuchen';
$string['firstquestionselectotherwise'] = '... ansonsten: ';
$string['fisherinformation'] = 'Item-Information';
$string['followingcourses'] = 'eingeschrieben in folgenden Kurs bzw. folgende Kurse:<br>';
$string['followinggroups'] = 'Mitglied in folgender Gruppe bzw. folgenden Gruppen:<br>';
$string['force'] = 'Erzwinge Werte';
$string['format'] = 'Format';
$string['formelementbetweenzeroandone'] = 'Bitte Werte zwischen 0 und 1 eingeben.';
$string['formelementnegative'] = 'Wert muss positiv (über 0) sein';
$string['formelementnegativefloat'] = 'Negative Dezimalzahl eingeben.';
$string['formelementnegativefloatwithdefault'] = 'Negative Dezimalzahl eingeben. Standard wäre {$a}.';
$string['formelementpositivefloat'] = 'Positive Dezimalzahl eingeben.';
$string['formelementpositivefloatwithdefault'] = 'Positive Dezimalzahl eingeben. Standard wäre {$a}.';
$string['formelementwrongpercent'] = 'Prozentzahl zwischen 0 und 100 eingeben';
$string['formetimelimitnotprovided'] = 'Geben Sie zumindest einen Wert ein';
$string['formminquestgreaterthan'] = 'Minimum muss kleiner als Maximum sein';
$string['formmscalegreaterthantest'] = 'Minimum pro CAT-Skala muss kleiner sein als Maximum des Tests';
$string['fraction'] = 'Fraction';
$string['genericsubmit'] = 'Bestätigen';
$string['global_scale'] = 'Globalskala';
$string['graphicalsummary_description'] = 'Während des Verlaufs des Testversuchs wird Ihrer Fähigkeits-Wert mit jeder Antwort neu
berechnet und aktualisiert. Die folgende Grafik zeigt Ihnen, wie sich die Einschätzung
Ihres Fähigkeits-Wertes in „{$a}“ über den Verlauf des Testversuchs hinweg
verändert hat.';
$string['graphicalsummary_description_lowest'] = 'Zusätzlich ist auch die
    Entwicklung Ihres Fähigkeits-Wertes bezüglich der als Defizit
    identifizierten Skala {$a} dargestellt:';
$string['greateststrenght:tooltiptitle'] = 'Ihre stärkste Skala „{$a}„';
$string['groupenrolementstring'] = '„{$a->groupname}“ in Kurs <a href={$a->courseurl}>{$a->coursename}</a>“';
$string['groupenrolmenthelptext'] = 'Bitte geben Sie den/die genauen Namen existierender Gruppe/n ein (z.B.: „Gruppe1,Gruppe2“ oder „Gruppe3“).';
$string['groupenrolmenthelptext_help'] = 'Bitte geben Sie den/die genauen Namen existierender Gruppe/n ein (z.B.: „Gruppe1,Gruppe2“ oder „Gruppe3“).';
$string['guessing'] = 'Rate-Parameter';
$string['hasability'] = 'Fähigkeit wurde berechnet';
$string['healthstatus'] = 'Health-Status';
$string['hoursago'] = 'Vor {$a} Stunden';
$string['id'] = 'ID';
$string['ifdefinedusedtomatch'] = 'Wenn angegeben findet der Abgleich über diesen Wert statt.';
$string['importcolumnsinfos'] = 'Informationen zu Importfeldern:';
$string['importcontextinfo'] = 'Die Kontextid sollte gesetzt werden, wenn bestehende Items bearbeitet werden, damit die eindeutige Zuordnung gelingt. Für den Import von neuen Items, empfiehlt es sich, den Kontext leer zu lassen. Es wird dann ein neuer Kontext automatisch generiert, welcher die Items aus dem Standardkontext plus die neu importierten enthält. Falls beim Import neuer Items ein Kontext angegeben wird, muss der Kontext der entsprechenden obersten Skala umgestellt werden (im CAT-Manager Dashboard, Skalen-Bereich), damit diese Items zum Einsatz kommen.';
$string['importcsv'] = 'Import CSV';
$string['imported_testitem_description'] = 'Es wurden {$a} Frage(n) importiert.';
$string['importfailed'] = 'Import fehlgeschlagen.';
$string['importsuccess'] = 'Import war erfolgreich. Es wurden {$a} Datensatz/Datensätze bearbeitet.';
$string['includepilotquestions'] = 'Pilotierungsmodus aktivieren';
$string['includepilotquestions_help'] = 'Im Pilotierungsmodus werden jedem Testversuch eine festzulegende Anzahl an Fragen beigemischt, deren Fragen-Parameter (z.B. Schwierigkeit, Trennschärfe) noch nicht bestimmt sind. Diese tragen nicht zum Test-Ergebnis bei, die durch die Bearbeitungen angefallenen Daten können jedoch durch eine*n CAT-Manager*in zu einem späteren Zeitpunkt zur Bestimmung der Fragen-Parameter statistisch ausgewertet und so der aktuelle Fragen-Pool fortlaufend erweitert werden. (empfohlen)';
$string['includetimelimit'] = 'Bearbeitung eines Testversuchs zeitlich begrenzen';
$string['includetimelimit_help'] = 'Maximaldauer festlegen, die für die Durchführung des Tests gelten soll.';
$string['inferallsubscales'] = 'Alle angegebenen Skalen abprüfen';
$string['infergreateststrength'] = 'Größte Stärke diagnostizieren';
$string['inferlowestskillgap'] = 'Unterste Kompetenzlücke diagnostizieren';
$string['instance'] = 'Test';
$string['integratequestions'] = 'Fragen aus untergeordneten Skalen einbeziehen';
$string['intercepts'] = 'Intercepts';
$string['invisible'] = 'Unsichtbar';
$string['itemassignedtoparentorsubscale'] = 'Datensatz mit componentid {$a->componentid} ist bereits in einer über- oder untergeordneten Skala von {$a->newscalename} eingeschrieben und wird nicht importiert.';
$string['itemassignedtosecondscale'] = 'Datensatz mit componentid {$a->componentid} ist bereits in Skala {$a->scalelink} eingeschrieben und nun zusätzlich in {$a->newscalename}.';
$string['itemdifficulties'] = 'Frage-Schwierigkeiten';
$string['itemdifficultiesnodata'] = 'Es konnte keine Frage-Schwierigkeit berechnet werden.';
$string['itemdifficulty'] = 'Schwierigkeit des Elements';
$string['itemsplayed'] = 'ausgewertete Fragen:';
$string['itemstatus_-5'] = 'Manuell ausgeschlossen';
$string['itemstatus_0'] = 'Noch nicht berechnet';
$string['itemstatus_1'] = 'Berechnet';
$string['itemstatus_4'] = 'Manuell gesetzt';
$string['itemstatus_5'] = 'Manuell bestätigt';
$string['jsoninformation'] = 'Zusätzliche, modell-spezifische Informationen';
$string['label'] = 'Kennzeichen';
$string['labelforrelativepersonabilitychart'] = 'Differenz';
$string['labelidnotfound'] = 'Wert von Label „{$a}“ nicht gefunden.';
$string['labelidnotunique'] = 'Wert von Label „{$a}“ muss einzigartig sein.';
$string['lang'] = 'Sprache';
$string['lastattempttime'] = 'Letzter Testversuch';
$string['learningprogress_description'] = 'Wie hat sich Ihr Fähigkeits-Wert über
    die letzten Versuche hin entwickelt? Haben Sie sich verbessert?<br/> Die
    folgende Grafik zeigt Ihnen die Entwicklung Ihres (allgemeinen)
    Fähigkeits-Wertes in „{$a}“ im Vergleich zum Durchschnittswert aller
    Testversuche:';
$string['learningprogresstitle'] = 'Ihr Lernfortschritt';
$string['likelihood'] = 'Wahrscheinlichkeit';
$string['local_catquiz_toggle_testitemstatus_message'] = 'Status des Elements wurde aktualisiert';
$string['logsafter'] = 'Einträge vor';
$string['logsbefore'] = 'Einträge nach';
$string['lowerlimit'] = 'Unteres Limit';
$string['lowestskill:tooltiptitle'] = 'Ihre schwächste Skala „{$a}“';
$string['manage_catcontexts'] = 'Einsatz-Kontexte verwalten';
$string['managecatcontexts'] = 'Einsatz-Kontexte verwalten';
$string['managecatscale'] = 'Skalen verwalten';
$string['managecatscales'] = 'Verwalte Skalen';
$string['managetestenvironments'] = 'Testumgebungen verwalten';
$string['mandatory'] = 'verpflichtend';
$string['max'] = 'max:';
$string['max_iterations'] = 'Maximale Anzahl an Iterationen';
$string['maxabilityscalevalue'] = 'Maximale Personenfähigkeit:';
$string['maxabilityscalevalue_help'] = 'Geben Sie die größtmögliche Personenfähigkeit dieser Skala als Dezimalwert an. Der Mittelwert ist null.';
$string['maxattemptduration'] = 'Maximale Laufzeit für Versuche';
$string['maxattemptduration_desc'] = 'Versuche die älter sind werden automatisch geschlossen. Ein Wert von 0 bedeutet, dass die Laufzeit unbeschränkt ist. Dieser Wert kann in den Quiz-Einstellungen überschrieben werden.';
$string['maxquestionspersubscale'] = 'max. Frageanzahl pro Skala';
$string['maxquestionspersubscale_help'] = 'Wenn von einer Skala so viele Fragen angezeigt wurden, werden keine weiteren Fragen dieser Skala mehr ausgespielt. Wenn auf 0 gesetzt, dann gibt es kein Limit.';
$string['maxscalevalue'] = 'Maximalwert';
$string['maxscalevalueinformation'] = 'Geben Sie die größtmögliche Personenfähigkeit der Skalen als positiven Dezimalwert an. Der Mittelwert ist null. Wert wird nur bei Erzeugung einer neuen Globalskala gesetzt und gilt für alle Sub-Skalen. Hierfür (mind.) im ersten Datensatz angeben. Werte in bestehenden Skalen können nicht via Import verändert werden. Möchten Sie die Werte einer bereits bestehenden Skala ändern, bitte auf das „Skalen“-Tab wechseln.';
$string['maxtime'] = 'Maximale Dauer des Tests';
$string['maxtimeperitem'] = 'Höchstzeit pro Frage in Sekunden';
$string['maxtimeperquestion'] = 'Erlaubte Zeit';
$string['maxtimeperquestion_help'] = 'Falls die Beantwortung einer Frage länger dauert, wird eine Pause erzwungen';
$string['messageprovider:catscaleupdate'] = 'Benachrichtung über eine Aktualisierung einer Skala.';
$string['messageprovider:enrolmentfeedback'] = 'Automatische Einschreibung zu Kursen und Gruppen.';
$string['messageprovider:updatecatscale'] = 'Erhält Benachrichtungung über Einschreibung in Skala';
$string['min'] = 'min:';
$string['minabilityscalevalue'] = 'Minimale Personenfähigkeit:';
$string['minabilityscalevalue_help'] = 'Geben Sie die kleinstmögliche Personenfähigkeit dieser Skala als negativen Dezimalwert an. Der Mittelwert ist null.';
$string['minquestions_default_desc'] = 'Dieser Wert wird standardmässig gesetzt, kann jedoch in den Quizsettings überschrieben werden';
$string['minquestions_default_name'] = 'Standardwert für die Mindestanzahl an Fragen pro Versuch';
$string['minquestionsnotreached'] = 'Es kann kein Ergebnis berechnet werden, da die Mindestanzahl an Fragen nicht erreicht worden ist';
$string['minquestionspersubscale'] = 'min. Frageanzahl pro Skala';
$string['minquestionspersubscale_help'] = 'Eine Skala wird frühestens dann ausgeschlossen, wenn die Minimalanzahl an Fragen aus dieser Skala angezeigt wurden.';
$string['minscalevalue'] = 'Minimalwert';
$string['minscalevalueinformation'] = 'Geben Sie die kleinstmögliche Personenfähigkeit der Skalen als negativen Dezimalwert an. Der Mittelwert ist null. Wert wird nur bei Erzeugung einer neuen Globalskala gesetzt und gilt für alle Sub-Skalen. Hierfür (mind.) im ersten Datensatz angeben. Werte in bestehenden Skalen können nicht via Import verändert werden. Möchten Sie die Werte einer bereits bestehenden Skala ändern, bitte auf das „Skalen“-Tab wechseln.';
$string['mintimeperitem'] = 'Mindestzeit pro Frage in Sekunden';
$string['missinglabel'] = 'Im importierten File fehlt die verpflichtede Spalte {$a}. Daten können nicht importiert werden.';
$string['model'] = 'Modell';
$string['model_override'] = 'Nur dieses Modell verwenden';
$string['modeldeactivated'] = 'Deaktiviere CAT engine';
$string['modelinformation'] = 'Dieses Feld ist notwendig, um Fragen vollständig zu erfassen. Ist das Feld leer, kann die Frage lediglich einer Skala zugeordnet werden.';
$string['models'] = 'Modelle';
$string['moreinformation'] = 'Weitere Informationen';
$string['moveitemtootherscale'] = 'Testitem(s) {$a} sind bereits einer anderen Skala des selben Baumes zugeordnet. Zuordnung ändern?';
$string['name'] = 'Name';
$string['nameexists'] = 'Der Name der Skala wurde bereits verwendet';
$string['newcustomtest'] = 'Benutzerdefinierter Test';
$string['noaccessyet'] = 'Bisher kein Zugriff.';
$string['nocentralconfig'] = 'Konfiguration der zentralen Berechnungsinstanz fehlt. Bitte konfigurieren sie Host und Token in den Einstellungen des Plugins.';
$string['noedit'] = 'Editieren beenden';
$string['nofeedback'] = 'Kein Feedback angegeben.';
$string['nogapallowed'] = 'Keine Lücken in Personenfähigkeitsspanne erlaubt. Bitte beginnen setzen Sie als Mindestwert den Maximalwert des vorangegangenen Bereichs.';
$string['noint'] = 'Bitte geben Sie eine Zahl ein';
$string['nolabels'] = 'Keine Spaltennamen definiert.';
$string['nolocalmappingforscale'] = 'Keine Skala mit Label "{$a->remotelabel}" auf lokaler Instanz gefunden.';
$string['noparentsgiven'] = 'Die Skala {$a->catscalename} ist nicht eindeutig zu identifizieren, weil keine übergeordneten Skalen angegeben wurden.
    Die betroffenen Datensätze wurden nicht importiert/aktualisiert.';
$string['noquestionhashmatch'] = 'Keine lokale Frage für angegebenen Hash gefunden';
$string['norecordsfound'] = 'Keine Fragen in dieser Skala gefunden.';
$string['noremainingquestions'] = 'Keine weiteren Fragen';
$string['noresponsestoestimate'] = 'Es sind nicht genügend Daten zur Neuberechnung verfügbar.';
$string['noresponsestoestimatedesc'] = '{$a->reason} Skala: {$a->scalename}, Kontext: {$a->contextname}.';
$string['noresult'] = 'kein Fähigkeits-Wert ermittelt';
$string['noscaleselected'] = 'Keine CAT-Skala gewählt.';
$string['noscalesfound'] = 'Für keine Skala konnte ein zuverlässiges Ergebnis ermittelt werden.';
$string['noselection'] = 'Keine Auswahl';
$string['nothingtocompare'] = 'Für einen Vergleich liegen noch nicht genügend Ergebnisse vor.';
$string['notificationcatscalechange'] = 'Hallo {$a->firstname} {$a->lastname},
Skalen wurden verändert auf der Moolde Plattform {$a->instancename}.
Diese e-Mail informiert Sie als CAT-Manager*in, verantwortlich für dieses Skala. {$a->editorname} hat die folgenden Änderungen an der Skala „{$a->catscalename}“ vorgenommen.:
    {$a->changedescription}
Sie können den aktuellen Stand hier überprüfen. {$a->linkonscale}';
$string['notifyallteachers'] = 'Kursleiter der gewählten Kurse benachrichtigen';
$string['notifyteachersofselectedcourses'] = 'Alle Kursleiter benachrichtigen';
$string['notimelimit'] = 'Keine zeitliche Begrenzung';
$string['notpositive'] = 'Bitte geben Sie eine positive Zahl ein';
$string['notyetattempted'] = 'Ohne Versuch';
$string['notyetcalculated'] = 'Noch nicht berechnet';
$string['numberofanswers'] = 'Antworten';
$string['numberofanswerscorrect'] = 'Richtig';
$string['numberofanswersincorrect'] = 'Falsch';
$string['numberofanswerspartlycorrect'] = 'Teilweise richtig';
$string['numberofattempts'] = 'Anzahl der Testversuche';
$string['numberoffeedbackoptionpersubscale'] = 'Anzahl der Fähigkeits-Stufen';
$string['numberoffeedbackoptionpersubscale_help'] = 'Wählen Sie aus, in wievielen Fähigkeits-Stufen Sie Ihr Feedback differenzieren möchten. Mithilfe der Fähigkeits-Stufen können Sie in Abhängigkeit der ermittelten Fähigkeit für jede Skala Ihren Teilnehmenden unterschiedliche schriftliche Rückmeldungen erteilen, diese in unterschiedliche Kurse einschreiben oder diese unterschiedlichen Gruppen zuordnen.';
$string['numberofpersonsanswered'] = 'Von Personen';
$string['numberofquestions'] = '# Fragen';
$string['numberofquestionsperscale'] = 'Anzahl der Fragen pro Skala';
$string['numberofquestionsperscale_help'] = 'Setzen Sie den Maximalwert auf 0 um unbegrenzt Fragen pro Skala auszuspielen.';
$string['numberofquestionspertest'] = 'Anzahl der Fragen pro Test';
$string['numberofquestionspertest_help'] = 'Setzen Sie den Maximalwert auf 0 um unbegrenzt Fragen auszuspielen.';
$string['numberoftestitemsused'] = 'Anzahl getesteter Fragen';
$string['numberofusagesintests'] = 'In verschiedenen Tests';
$string['numberofusers'] = '# Nutzende';
$string['occurrences'] = 'mal aufgetreten';
$string['onecourseenroled'] = 'Sie wurden auf Grundlage Ihres Ergebnisses in „{$a->catscalename}“ in den Kurs „<a href="{$a->courseurl}">{$a->coursename}</a>“ eingeschrieben.';
$string['onegroupenroled'] = 'Sie sind auf Grundlage Ihres Ergebnisses in „{$a->catscalename}“ nun Mitglied der Gruppe „{$a->groupname}“ im Kurs „<a href="{$a->courseurl}">{$a->coursename}</a>“.';
$string['openformat'] = 'offenes Format';
$string['optional'] = 'optional';
$string['ownfeedbacksheader'] = 'Mein Testversuch von {$a}';
$string['parameterssynced'] = 'Synchronisierte Parameter';
$string['parent'] = 'Übergeordnete Skala - keine Auswahl falls Top-Level Skala';
$string['parentid'] = 'Übergeordnete ID';
$string['parentscale'] = 'Inhaltsbereich (Globalskala)';
$string['parentscalenamesinformation'] = 'Präzisieren Sie hier die übergeordneten Skalen der Skala um eine eindeutige Zuordnung zu ermöglichen. Übergeordnete Skalen können beim Import angelegt werden. Starten sie mit dem Namen der höchsten Skala und fügen sie alle Kinder mit „|“ (Vertikaler String Unicode U+007C - nicht zu verwechseln mit „/“ Slash) getrennt hinzu. Vergessen Sie dabei nicht die Globalskala. Für den Import von Items in die Globalskala, geben Sie bitte „0“ in diesem Feld an.';
$string['perattempt'] = 'pro Versuch ';
$string['peritem'] = 'pro Item ';
$string['personabilities'] = 'Fähigkeits-Werte';
$string['personabilitiesnodata'] = 'Es konnte kein Fähigkeits-Wert errechnet werden';
$string['personability'] = 'Fähigkeits-Wert';
$string['personabilityafterattempt'] = 'Fähigkeits-Wert nach Testversuch';
$string['personabilitybeforeattempt'] = 'Fähigkeits-Wert vor Testversuch';
$string['personabilitycharttitle'] = 'Differenz Ihrer Fähigkeits-Werte im Vergleich zu „{$a}“';
$string['personabilityfeedbacktitle'] = 'Fähigkeitsprofil';
$string['personabilityinscale'] = 'Fähigkeits-Wert für Skala „{$a}“';
$string['personabilityrangestring'] = '{$a->rangestart} - {$a->rangeend}';
$string['personabilitytitletab'] = 'Ergebnis-Details';
$string['picturesavewarning'] = 'Im Moment werden Bilder in Feedbacks nur gespeichert, wenn ein bestehendes Quiz upgedated wird';
$string['pilot_questions'] = 'Pilotfragen';
$string['pilotratio'] = 'Anteil zu pilotierender Fragen in %';
$string['pilotratio_help'] = 'Anteil von noch zu pilotierender Fragen an der Gesamtfragezahl in einem Test-Versuch. Die Angabe 20% führt beispielsweise dazu, dass eine von fünf Fragen  eines Test-Versuches eine zu pilotierende Frage sein wird.';
$string['pleasecheckorcancel'] = 'Bitte bestätigen oder abbrechen';
$string['pleasechoose'] = 'bitte auswählen';
$string['pluginname'] = 'Adaptive Quiz - Advanced CAT Module';
$string['previewquestion'] = 'Fragen Vorschau';
$string['privacy:metadata:local_catquiz_attempts'] = 'Informationen über Benutzerversuche in adaptiven Quizzen.';
$string['privacy:metadata:local_catquiz_personparams'] = 'Benutzerdatenparameter in verschiedenen Kontexten.';
$string['privacy:metadata:local_catquiz_subscriptions'] = 'Informationen über Benutzerabonnements im Catquiz-Plugin.';
$string['privacy:metadata:local_catquiz_subscriptions:area'] = 'Der Bereich des abonnierten Elements.';
$string['privacy:metadata:local_catquiz_subscriptions:itemid'] = 'Die ID des abonnierten Elements.';
$string['privacy:metadata:local_catquiz_subscriptions:status'] = 'Der Status des Abonnements.';
$string['privacy:metadata:local_catquiz_subscriptions:timecreated'] = 'Der Zeitpunkt, zu dem das Abonnement erstellt wurde.';
$string['privacy:metadata:local_catquiz_subscriptions:timemodified'] = 'Der Zeitpunkt, zu dem das Abonnement geändert wurde.';
$string['privacy:metadata:local_catquiz_subscriptions:userid'] = 'Die ID des Benutzers.';
$string['progress'] = 'Entwicklung Fähigkeits-Wert in „{$a}“';
$string['questioncategories'] = 'Fragekategorien';
$string['questioncontextattempts'] = '# Testversuche im ausgewählten Einsatz-Kontext';
$string['questionfeedbackdisabled'] = 'Rückmeldung zu gegebener Antwort ist deaktiviert';
$string['questionfeedbacksettings'] = 'Einstellungen zu Frage-Rückmeldungen';
$string['questionfeedbackshow'] = 'Rückmeldung über gegebene Antworten anzeigen';
$string['questionfeedbackshowcorrectresponse'] = 'Korrekte Antwort anzeigen';
$string['questionfeedbackshowfeedback'] = 'Fragefeedback anzeigen';
$string['questionfeedbackshowresponse'] = 'Indikator zur Korrektheit der gegebenen Antwort anzeigen';
$string['questionpreview'] = 'Fragevorschau';
$string['questionresults'] = 'Fragen Auswertung';
$string['questions'] = 'Fragen';
$string['questionssummary'] = 'Zusammenfassung';
$string['questiontext'] = 'Fragentext';
$string['questiontype'] = 'Fragentyp';
$string['quizattempts'] = 'Testversuche';
$string['quizgraphicalsummary'] = 'Quizverlauf';
$string['reachedmaximumquestions'] = 'Die Maximalanzahl an Testfragen wurde erreicht';
$string['recalculationscheduled'] = 'Neuberechnung der Kontext-Paremeter wurde veranlasst.<br>Die berechneten Parameter werden in einem neuen Kontext gespeichert.';
$string['recentevents'] = 'Letzte Bearbeitungen';
$string['relevantscales'] = 'Skalen im Kompetenzbereich abprüfen';
$string['removetestitem'] = 'Testitems entfernen';
$string['removetestitembody'] = 'Wollen Sie folgende Testitems aus aktuellen Skale entfernen? <br> {$a->data}';
$string['removetestitemsubmit'] = 'Entfernen';
$string['removetestitemtitle'] = 'Testitems von Skalen entfernen';
$string['reportscale'] = 'Skala für den Report der Ergebnisse berücksichtigen';
$string['requesttimeout'] = 'Zeitüberschreitung beim Verbindungsaufbau';
$string['response'] = 'Antwort';
$string['responsesbyusercharttitle'] = 'Gesamtanzahl der gegebenen Antworten pro Person';
$string['rootscale:tooltiptitle'] = 'Globalskala „{$a}“';
$string['scaledetailviewheading'] = 'Detailansicht der CAT-Skala „{$a}“';
$string['scalehasnolabel'] = 'Skala hat kein Label';
$string['scaleiddisplay'] = ' (ID: {$a})';
$string['scaleinformation'] = 'Die ID der Skala der die Frage zugeordnet werden soll.';
$string['scalenameinformation'] = 'Der Name der Skala der die Frage zugeordnet werden soll. Falls keine ID angegeben, wird Matching über Name vorgenommen.';
$string['scalenotfound'] = 'Skala konnte nicht gefunden werden';
$string['scalescorechartlabel'] = 'Fähigkeits-Wert in „{$a}“';
$string['scaleselected'] = 'Skala „{$a}“';
$string['score'] = 'Gewichteter Fähigkeits-Wert';
$string['scoreofpeers'] = 'Durchschnitt aller Ergebnisse';
$string['searchcatcontext'] = 'Einsatz-Kontexte durchsuchen';
$string['seeitemsplayed'] = 'Beantwortete Fragen anzeigen';
$string['selectcatcontext'] = 'Einsatz-Kontext auswählen';
$string['selectcatcontext_help'] = 'Einsatz-Kontexte differenzieren die Daten hinsichtlich Zielgruppe, Einsatzzweck oder Zeit/Kohorte. Der Einsatz-Kontext wird durch den bzw. die CAT-Manager*in verwaltet. Falls Sie für Ihren Einsatzzweck einen eigenen Einsatz-Kontext wünschen, wenden Sie sich bitte an den oder die CAT-Manager*in oder den bzw. die Adminstrator*in Ihrer Moodle-Instanz.';
$string['selectcatscale'] = 'Skala:';
$string['selected_scales_all_ranges_label'] = 'Anzahl der Teilnehmenden';
$string['selectitem'] = 'Keine Daten ausgewählt';
$string['selectmodel'] = 'Wähle Modell';
$string['selectparentscale'] = 'Auswahl Skala';
$string['selectsubscale'] = 'Untergeordnete Skala auswählen';
$string['setautonitificationonenrolmentforscale'] = 'Teilnehmende über eine Gruppen- oder Kurseinschreibung mittels Standardtext informieren.';
$string['setautonitificationonenrolmentforscale_help'] = 'Teilnehmende erhalten zusätzlich zu deren schriftlichen Feedback folgenden Hinweis: „Sie wurden automatisch in die Gruppe <Gruppenname> / den Kurs <Kursname als Link> eingeschrieben."';
$string['setcourseenrolmentforscale'] = 'Einschreibung in einen Kurs';
$string['setcourseenrolmentforscale_help'] = 'In diesen (externen) Kurs werden Testteilnehmende nach Beendigung des Tests eingeschrieben, sofern das Ergebnis in die eingestellte Fähigkeits-Stufe fällt. Sie können nur Kurse auswählen, zu denen Sie die Berechtung zur Einschreibung haben oder die zur Einschreibung durch einen CAT-Manager*in freigegeben wurden. Falls Sie keine Einschreibung in einen externen Kurs wünschen, lassen Sie dieses Feld bitte leer.';
$string['setcoursesforscaletext'] = 'Legen Sie das Feedback (schriftlichen Rückmeldungen, Kurseinschreibung und/oder Gruppenzuordnung) je Fähigkeits-Stufe für die Skala „{$a}“ fest.';
$string['setfeedbackforscale'] = 'schriftliches Feedback';
$string['setfeedbackforscale_help'] = 'Dieser Text wird den Testteilnehmenden nach Beendigung des Tests angezeigt, sofern das Ergebnis in die eingestellte Fähigkeits-Stufe fällt.';
$string['setgrouprenrolmentforscale'] = 'Einschreibung in eine Gruppe';
$string['setgrouprenrolmentforscale_help'] = 'In diese Gruppe des Kurses werden Testteilnehmende nach Beendigung des Tests eingeschrieben, sofern das Ergebnis in die eingestellte Fähigkeits-Stufe fällt. Falls Sie keine Einschreibung in eine Gruppe wünschen, lassen Sie dieses Feld bitte leer.';
$string['setsevalue'] = 'Bitte Werte angeben. Standard: Min={$a->min} Max={$a->max}';
$string['shortcodescatquizfeedback'] = 'Zeige Feedback zu Versuchen an.';
$string['shortcodescatquizstatistics'] = 'Zeige Statistiken zu einem CAT Test an';
$string['shortcodescatscalesoverview'] = 'Zeige Übersicht zu CAT-Skalen an.';
$string['shortcodeslistofquizattempts'] = 'Gibt eine Tabelle mit Testversuchen zurück.';
$string['showlistofcatscalemanagers'] = 'CAT-Manager*innen';
$string['showquestion'] = 'Frage anzeigen';
$string['somethingwentwrong'] = 'Etwas ist schiefgelaufen. Melden Sie den Fehler ihrem Admin';
$string['standarderror'] = 'Standardfehler';
$string['starttime'] = 'Beginn';
$string['starttimestamp'] = 'Zeitraum Anfang';
$string['startwithdifficultquestion'] = 'mit einer schweren Frage';
$string['startwitheasyquestion'] = 'mit einer leichten Frage';
$string['startwithmediumquestion'] = 'mit einer mittelschweren Frage';
$string['startwithverydifficultquestion'] = 'mit einer sehr schweren Frage';
$string['startwithveryeasyquestion'] = 'mit einer sehr leichten Frage';
$string['statistics'] = 'Statistik';
$string['statistics_month_01'] = 'Januar {$a->y}';
$string['statistics_month_02'] = 'Februar {$a->y}';
$string['statistics_month_03'] = 'März {$a->y}';
$string['statistics_month_04'] = 'April {$a->y}';
$string['statistics_month_05'] = 'Mai {$a->y}';
$string['statistics_month_06'] = 'Juni {$a->y}';
$string['statistics_month_07'] = 'Juli {$a->y}';
$string['statistics_month_08'] = 'August {$a->y}';
$string['statistics_month_09'] = 'September {$a->y}';
$string['statistics_month_10'] = 'Oktober {$a->y}';
$string['statistics_month_11'] = 'November {$a->y}';
$string['statistics_month_12'] = 'Dezember {$a->y}';
$string['statusactiveorinactive'] = 'Der Aktivitätsstatus. Geben Sie „1“ an um sicher zu stellen, um den Datensatz von der Verwendung auszuschließen. Lassen Sie das Feld leer oder setzen „0“, gilt der Datensatz als aktiv.';
$string['statusok'] = 'Ok';
$string['statusundefined'] = 'Undefinierter Status';
$string['store_debug_info_desc'] = 'Wenn diese Option aktiviert ist, werden
    zusätzliche Daten gespeichert und als CSV Datei zur Verfügung gestellt.
    Dadurch steigt der benötigte Speicherplatz.';
$string['store_debug_info_name'] = 'Stelle debug Informationen zur Verfügung';
$string['strategy'] = 'Strategie';
$string['stringdate:day'] = '{$a}';
$string['stringdate:quarter'] = 'Q{$a->q} {$a->y}';
$string['stringdate:week'] = 'KW {$a}';
$string['studentdetails'] = 'Student details';
$string['studentstats'] = 'Nutzende';
$string['subfeedbackrange'] = '({$a->lowerlimit} bis {$a->upperlimit})';
$string['submission_error'] = 'Fehler beim versenden von Antwort-Daten: {$a}';
$string['submission_success'] = '{$a} Antworten wurden erfolgreich übermittelt';
$string['submit_responses'] = 'Antwort-Daten mit zentraler Berechnungsinstanz teilen';
$string['subplugintype_catmodel'] = 'CAT Modell';
$string['subplugintype_catmodel_plural'] = 'CAT Modelle';
$string['subscribe'] = 'Abonniere';
$string['subscribed'] = 'Abonniert';
$string['subscribedcatscalesheading'] = 'Eingeschriebene Skalen';
$string['summary'] = 'Zusammenfassung';
$string['summarygeneral'] = 'Allgemeines';
$string['summarylastcalculation'] = 'Letzte (vollständige) Berechnung';
$string['summarynumberofassignedcatscales'] = 'Anzahl der Ihnen zugeordneten Skalen';
$string['summarynumberoftests'] = 'Anzahl der einsetzenden Tests';
$string['summarytotalnumberofquestions'] = 'Anzahl der Fragen (insgesamt)';
$string['target'] = 'Ziel';
$string['task_recalculate_cat_model_params'] = 'CAT Parameter neu berechnen';
$string['teacherfeedback'] = 'Feedback für Lehrende';
$string['templatetype'] = 'Template';
$string['test'] = 'Teste Abos';
$string['testinfolabel'] = 'Testinformation';
$string['testitem'] = 'Frage mit ID {$a}';
$string['testitem_deleted'] = 'Frage gelöscht';
$string['testitem_deleted_description'] = 'Es wurde die Frage mit ID {$a->testitemid} gelöscht.';
$string['testitem_imported'] = 'Frage(n) importiert';
$string['testitem_status_updated_description'] = 'Der neue Status der {$a->testitemlink} ist nun: {$a->statusstring}';
$string['testitemactivitystatus_updated'] = 'Aktivitätsstatus der Frage aktualisiert.';
$string['testitemdashboard'] = 'Fragen Ansicht';
$string['testiteminrelatedscale'] = 'Testitem ist bereits einer Kind- oder Eltern-Skala zugeordnet';
$string['testiteminscale_added'] = 'Frage zu Skala hinzugefügt';
$string['testiteminscale_updated'] = 'Frage in Skala aktualisert';
$string['testitems'] = 'Testitems';
$string['testitemstatus_updated'] = 'Status der Frage aktualisiert.';
$string['testsandtemplates'] = 'Tests & Templates';
$string['teststrategy'] = 'Teststrategie';
$string['teststrategy_balanced'] = 'Moderater CAT';
$string['teststrategy_base'] = 'Basisklase der Teststrategien';
$string['teststrategy_fastest'] = 'CAT';
$string['teststrategy_info'] = 'Info Klasse für Teststrategien';
$string['testtype'] = 'Test';
$string['time_penalty_threshold_desc'] = 'Eine Frage, die durch einen User in
    einem früheren Testversuch bereits beantwortet wurde, wird nur mit
    verringerter Wahrscheinlichkeit erneut gestellt. Die Wahrscheinlichkeit ist
    abhängig vom der Dauer zwischen dem früheren und dem aktuellen Versuch. Je
    höher die eingestellte Dauer, desto länger ist dieser Schutz vor wiederholt
    gestellten Fragen wirksam.';
$string['time_penalty_threshold_name'] = 'Wiederholungsverzögerung in Tagen';
$string['timemodified'] = 'Zuletzt geändert';
$string['timeoutabortnoresult'] = 'Test wird sofort beendet und nicht abschließend bewertet';
$string['timeoutabortresult'] = 'Test wird sofort beendet und abschließend bewertet';
$string['timeoutfinishwithresult'] = 'Nachfrist: angezeigte Items können beendet werden';
$string['timepacedtest'] = 'Zeitbeschränkungen für den Test aktivieren';
$string['toggleactivity'] = 'Aktivitätsstatus';
$string['togglestatus'] = 'Status ändern';
$string['totalnumberoftestitems'] = 'Gesamtzahl Fragen';
$string['tr_sd_ratio_desc'] = 'Der Multiplikator für den Vertrauensbereich gibt
    das Vielfache der Standardabweichung um den Mittelwert an, um den eine
    Parameterschätzung einer Person oder Item-Schwierigkeit erwartet wird. Wird
    der Multiplikator für den Vertrauensbereich zu hoch gewählt, besteht die
    Gefahr, dass der numerische Algorithmus instabil wird und bei schwieriger
    Datenlage unzuverlässige Werte liefert. Default-Wert ist ein Multiplikator
    von 3.0, was statistisch 99,9 Prozent aller zu erwartenden Fälle mit einschließt.';
$string['tr_sd_ratio_name'] = 'Multiplikator für Vertrauensbereich';
$string['trashbintitle'] = 'Element löschen';
$string['type'] = 'Typ';
$string['undefined'] = 'nicht definiert';
$string['update_context_description'] = 'Einsatz-Kontext {$a} aktualisiert.';
$string['update_testitem_activity_status'] = 'Der Aktivitätsstatus der Frage mit der Id {$a->objectid} wurde aktualisiert.';
$string['update_testitem_in_scale'] = '{$a->testitemlink} wurde in {$a->catscalelink} aktualisiert.';
$string['updatedparamscontext'] = 'updatedparams_{$a->scalename}_{$a->usertime}';
$string['updatedparamscontextdesc'] = 'Automatisch durch Parameter-Update generiert für Skala {$a}.';
$string['updatedrecords'] = '{$a} Eintrag/Einträge aktualisiert.';
$string['uploadcontext'] = 'autocontext_{$a->scalename}_{$a->usertime}';
$string['upperlimit'] = 'Obergrenze';
$string['usage'] = 'Übersicht';
$string['usecatquiz'] = 'Verwende die Catquiz Engine für dieses Quiz.';
$string['userfeedbacksheader'] = 'Testversuch {$a->attemptid} von {$a->time}, vorgenommen durch {$a->firstname} {$a->lastname} (Userid: {$a->userid})';
$string['usertocourse_enroled'] = 'NutzerIn in Kurs eingeschrieben';
$string['usertocourse_enroled_description'] = 'NutzerIn mit ID {$a->userid} wurde in folgenden Kurs eingeschrieben: „<a href="{$a->courseurl}">{$a->coursename}</a>“';
$string['usertogroup_enroled'] = 'NutzerIn in Gruppe eingeschrieben';
$string['usertogroup_enroled_description'] = 'NutzerIn mit ID {$a->userid} wurde in die Gruppe {$a->groupname} in diesem Kurs eingeschrieben: „<a href="{$a->courseurl}">{$a->coursename}</a>“';
$string['userupdatedcatscale'] = 'NutzerIn mit der Id {$a->userid} hat {$a->catscalelink} aktualisiert.';
$string['validateform:changevaluesorstatus'] = 'Bitte geben Sie Werte ein oder ändern Sie den Status.';
$string['validateform:onlyoneconfirmedstatusallowed'] = 'Dieser Status ist nur für jeweils eine Strategie erlaubt.';
$string['valuemustbegreaterzero'] = 'Wert muss höher als 0 sein.';
$string['versionchosen'] = 'ausgewählte Versionierung:';
$string['versioning'] = 'Versionierung';
$string['warnings'] = 'Warnungen';
$string['wronglabels'] = 'Die importierten Spaltennamen entsprechen nicht der Vorgabe. {$a} kann nicht importiert werden.';
$string['yourscorein'] = 'Ihre durchschnittlichen „{$a}“-Ergebnisse';
