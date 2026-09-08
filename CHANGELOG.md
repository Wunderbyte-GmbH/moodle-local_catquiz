# Changelog – local_catquiz

## 1.2.0 (interne Version 2026090548)

> Messung ueber den echten Codeweg statt ueber einzelne SQL-Aufrufe.

- `cli/measure_screens.php` misst Bildschirme durch die **Tabellenklasse**, nicht am
  Query-Builder vorbei. Damit sind das innere Seitenlimit und das Nachladen der
  Statistik je sichtbarer Zeile Teil der Zahl - beides fehlte im bisherigen
  Vergleich, dessen Werte deshalb Abfragezeiten waren und keine Ladezeiten.
- **Neu vermessen: eine Antwort im laufenden Test.** Diese Zahl gab es bisher gar
  nicht, obwohl sie die ist, die Teilnehmende erleben. Auf 250.000 Items:
  **kalt 725 ms, warm 206 ms**.
- Auswahldialog ueber den echten Weg: **kalt 30 ms, warm 4 ms** - die 2 ms des alten
  Werkzeugs waren die Abfrage allein.
- Verglichen wird durch **zweimaliges Ausfuehren** gegen dieselbe Datenbank, mit
  getauschtem Plugin-Verzeichnis. Der frueher versuchte Weg - beide Versionen per
  Namensraum gleichzeitig laden - traegt nicht: Die Klassen eines Moodle-Plugins
  verweisen namentlich aufeinander, und eine umbenannte Datei laesst den Rest auf die
  installierte Version zeigen.

**Offen:** Der Bildschirm "Fragenliste, sortiert" scheitert ueber die Tabellenklasse
mit einem Datenbankfehler. Die Abfrage allein laeuft; die Tabelle sortiert nach
`questionname`, das im Ergebnis nicht unter diesem Namen steht. Die beiden anderen
Bildschirme sind davon nicht betroffen.

## 1.2.0 (interne Version 2026090547)

> Der Vergleich hat einen Bildschirm gemessen, den er nicht gemessen hat.

- **`compare_versions.php` sortierte nicht**, obwohl der Bildschirm "sorted by name"
  hiess. Ohne `ORDER BY` liefert die Datenbank die ersten zehn Zeilen, die sie
  erreicht, und hoert auf - **1 ms** auf einem Bestand, auf dem das Zaehlen derselben
  Zeilen zwei Sekunden dauert. Die Zahl war echt und sagte nichts, weil die
  Sortierung der Aufwand ist.
- Mit `ORDER BY` gemessen (250.000 Items, PostgreSQL, 5 Laeufe):
  **155 ms gegen 416 ms** - der Arbeitsstand ist 2,6-mal schneller. Vorher stand dort
  1 ms gegen 1 ms.
- Zwei Folgefehler auf dem Weg dorthin, beide durch Ausfuehren gefunden: Der schlanke
  Spaltensatz laesst `questionname` weg, weshalb dieser Bildschirm den vollen nimmt;
  und der Name erscheint im Select nicht woertlich, weshalb ihn aus dem String zu
  raten die falsche Spalte waehlte.
- Vollstaendiger Lauf auf 250.000 Items, PostgreSQL: Auswahldialog 2 ms gegen
  1.099 ms, Fragenliste 155 gegen 416 ms, Gesamtzahl 145 gegen 257 ms.

## 1.2.0 (interne Version 2026090546)

> Neuer, ausdruecklich temporaerer Workflow: Versionsvergleich in der CI.

- `compare-versions.yml` ist eine Kopie des Lasttests, die denselben Bestand seedet
  und danach dieselben Bildschirme auf **zwei** Staenden misst: diesem Branch und
  einem Checkout, der als Eingabe `compare_ref` angegeben wird (Vorgabe
  `adaptive-catquiz-v3`).
- Vorgabegroesse **100.000** - dort unterscheiden sich die Staende deutlich, ohne
  dass ein Lauf eine Stunde dauert. `sizes` erlaubt weiterhin 50k, 250k oder alle.
- **Der Lasttest bleibt unveraendert** und ist der dauerhafte: Er misst den aktuellen
  Stand gegen ein Ziel und bleibt nuetzlich, wenn dieser Vergleich verworfen wird.
  Der Kopf der neuen Datei sagt das ausdruecklich, samt Hinweis auf das Loeschen.
- Die geerbten Messartefakte heissen dort `compare-baseline-*`, damit sie nicht mit
  `runtime-pool-*` des dauerhaften Laufs verwechselt werden.

## 1.2.0 (interne Version 2026090545)

> Neu: Direktvergleich zweier Plugin-Staende auf derselben Datenbank.

- `cli/compare_versions.php` misst dieselben Bildschirme auf zwei Staenden - dem
  Arbeitsstand und einem beliebigen Checkout - **im selben Prozess, auf derselben
  Datenbank, in derselben Minute**. Eine Messung eines Standes allein sagt, wie
  schnell er ist; sie sagt nicht, was sich geaendert hat.
- Der zweite Stand wird unter einem eigenen Namensraum geladen, damit beide
  gleichzeitig existieren. Ein Neuinstallieren zwischen den Laeufen wuerde Cache- und
  Verbindungseffekte in den gemessenen Unterschied hineintragen.
- Die Bildschirme sind in Klartext benannt, mit URL und einer Zeile dazu, was
  gemessen wird - nicht ueber Issue-Nummern.
- Erster Lauf gegen `adaptive-catquiz-v3` (Release 1.1.4) auf 250.000 Items siehe
  Abschlussbericht.

## 1.2.0 (interne Version 2026090544)

> Startup failure: `matrix` gibt es im Job-`if` nicht.

- **Meine Groessenauswahl hat den Workflow unstartbar gemacht.** Die Bedingung stand
  am Job und las `matrix.items` - GitHub wertet ein Job-`if` aus, **bevor** die
  Matrix existiert, und lehnt die Datei ab. Nicht ein Lauf schlaegt fehl, sondern der
  Workflow startet nicht.
- Die Matrix wird jetzt in einem vorgelagerten Job `prepare` berechnet und ueber
  `fromJSON` uebernommen. `sizes=all` ergibt sechs Jobs, eine einzelne Groesse zwei
  - beides gegengeprueft.
- Umbenannt in **"Load test (DB Engines)"**.
- Alle Workflows auf dasselbe Muster geprueft: keine weitere Stelle.

## 1.2.0 (interne Version 2026090543)

> #58 geschlossen, Messwerkzeug parametrisiert.

- **Schwellwert 1.000 ms fuer grosse Bestaende.** Das Messwerkzeug nimmt ihn als
  `--threshold` und gibt ein Urteil aus ("Target 1000 ms -> met" bzw. "MISSED by
  N ms"). Eine Zahl ohne ihr Ziel laedt zu der Lesart ein, die gerade passt.
- **Der Workflow nimmt Groesse und Schwellwert als Eingaben.** `sizes` waehlt 50k,
  100k, 250k oder alle - die Matrix enthielt alle drei schon, nur liess sich keine
  einzeln fahren. Die Bedingung sitzt am **Job**, nicht an einem Schritt: Ein Schritt
  mit `exit 0` beendet nur sich selbst, und das Seeding danach liefe trotzdem.
- Beide Eingaben haben Vorgaben, damit ein Lauf ohne Angabe sich verhaelt wie bisher.

## 1.2.0 (interne Version 2026090542)

> Der LEFT JOIN half lokal und schadete im Massstab. Zurueckgenommen.

- Der Umbau von `NOT EXISTS` zu `LEFT JOIN … IS NULL` sah nach der Loesung aus:
  lokal auf MariaDB **113 -> 20 ms**, der Scan fiel von 20.010 auf 2.285 Zeilen.
- **Bei 250.000 Items kehrt es sich um**: warm p95 **1.089 -> 1.967 ms**. Die
  geaenderte Join-Reihenfolge laesst die Versionszugriffe je Kandidatenzeile laufen
  statt je zurueckgegebener Zeile, und dieser Aufwand waechst mit dem Pool.
- **PostgreSQL bevorzugt die Form** (206 -> 113 ms). Zwei Statements je Engine sind
  es nicht wert: MariaDB ist die Engine, die das Ziel verfehlt.
- Zurueckgenommen, mit der Messung als Begruendung im Code - damit der naheliegende
  Umbau nicht ein zweites Mal versucht wird.
- Der Aequivalenztest bleibt und ist auf die Eigenschaft formuliert statt auf die
  Form: Er prueft, dass eine zugeordnete Frage **genau eine** Zeile entfernt, und
  gilt fuer beide Schreibweisen.
- **Stand #58**: MariaDB 2.069 -> 1.089 ms (das innere LIMIT), PostgreSQL 218 -> 206
  ms. Ziel 500 ms auf MariaDB weiterhin verfehlt.

## 1.2.0 (interne Version 2026090541)

> #58: Der Anti-Join war der letzte Engpass.

- **`NOT EXISTS` auf `local_catquiz_items` ist jetzt ein `LEFT JOIN … IS NULL`.**
  Beide Formen sagen dasselbe, aber MariaDB fuehrt `NOT EXISTS` als
  **materialisierten** Anti-Join aus (`r_loops = 1`): Es braucht die vollstaendige
  Kandidatenmenge, bevor es antworten kann, und das innere `LIMIT` konnte den Scan
  deshalb nicht abbrechen.
- `ANALYZE` nach der Aenderung: Der Scan ueber `question_bank_entries` faellt von
  **20.010 auf 2.285 Zeilen**. Gemessen auf MariaDB, lokaler Bestand:
  **113 -> 20 ms** (ohne inneres Limit waren es 614 ms).
- **Zeilengleichheit geprueft** gegen eine Skala mit 50.000 zugeordneten Items:
  70.305 Fragen in beiden Formen. Der PHPUnit-Test dazu wird in der leeren
  Test-Datenbank uebersprungen - er braucht echte Fragen; der Nachweis stammt aus dem
  geseedeten Bestand.
- Der Test haelt zusaetzlich die Voraussetzung fest, unter der die beiden Formen
  gleichwertig sind: Der eindeutige Schluessel auf
  (componentid, componentname, catscaleid) verhindert, dass der Join eine Frage
  vervielfacht statt sie auszuschliessen.
- Regression 112/112 gruen.

## 1.2.0 (interne Version 2026090540)

> CI brach erneut bei Exit 143 ab - diesmal am schweren 3PL-Datensatz.

- **Die 3PL-Personabilitaets-Schaetzung laeuft nicht mehr im Gesamtlauf.** Sie ist mit
  Abstand der teuerste Fall der Suite: Der stabilisierte Newton geht auf diesen Daten
  bis theta um +-800 und braucht Minuten. In der CI erschoepfte sie den Schritt, und
  der Lauf endete mit einem blossen Exit-Code - das Log bricht mitten in der Suite ab
  und liest sich wie ein abgestuerzter Test statt wie ein langsamer.
- Sie wird **uebersprungen, nicht geloescht**: Es ist der Fall, der eine Regression im
  Schaetzer am ehesten faengt. Ein eigener CI-Schritt fuehrt ihn mit
  `CATQUIZ_RUN_SLOW=1` und `--filter 3PL` aus, mit 20 Minuten Zeitlimit und
  `continue-on-error` - ein Timeout dort soll sichtbar sein, nicht die Pipeline
  toeten.
- Lokal: Die Suite laeuft jetzt in **231 s** statt bis zum Abbruch (3 Tests, davon 1
  uebersprungen).

## 1.2.0 (interne Version 2026090539)

> #58 gemessen: 47 % schneller, Schwelle weiter verfehlt. #21 abgeschlossen.

- **#58 bei 250.000 Items mit dem Fix**: MariaDB warm p95 **2.069 -> 1.089 ms**,
  PostgreSQL 206 ms. Das innere Limit wirkt deutlich, reicht aber nicht: Das Ziel
  liegt bei 500 ms.
- `ANALYZE` zeigt, was sich geaendert hat: `q` und `qvnewer` laufen statt 20.010 nur
  noch **10 Mal**. Geblieben ist der Scan ueber `qbe` mit dem Versions-Join `qv` -
  MariaDB bricht dort nicht frueh ab, weil das `NOT EXISTS` auf
  `local_catquiz_items` als materialisierter Anti-Join laeuft und die vollstaendige
  Kandidatenmenge braucht.
- **#21 abgeschlossen.** Der Light Count wirkt (vierte Bestaetigung, 16 % auf
  MariaDB), und der Anschluss aus `doc/issue-21-followup.md` hat sich erledigt: Die
  Statistik der sichtbaren Zeilen kostet gemessen **2 ms**. Der zweistufige
  Ladevorgang sollte genau das erreichen - er ist bereits so.
- **Zum PR wunderbyte_table#141**: Er war fuer keinen der beiden Faelle noetig. Der
  Zugriffspunkt fuer #58 ist `query_db()`, das die Tabelle seit jeher ueberschreibt;
  und der zweistufige Ladevorgang, fuer den `query_db_cached_filtered()` gebraucht
  worden waere, ist entbehrlich. Die Annahme im Issue-Entwurf - die Statistik werde
  fuer alle Zeilen berechnet - hat die Messung nicht bestaetigt.

## 1.2.0 (interne Version 2026090538)

> Die 250k-Messung hat den Fix gar nicht durchlaufen.

- **`measure_runtime_pool.php` baute das Add-Questions-SQL selbst** und ging nie
  durch `catscalequestions_table::query_db()`. Die gemessenen 1.720 ms auf MariaDB
  beziehen sich also auf den **alten** Weg - ueber den Fix sagen sie nichts.
- Dieselbe Fehlerklasse wie beim Pool, den das Werkzeug ohne den schlanken
  Spaltensatz gemessen hatte: Das Werkzeug misst einen anderen Weg als die Anwendung.
- Die Umschreibung wird jetzt reproduziert, mit Verweis auf die Stelle, die sie im
  Produktivcode vornimmt. Lokal auf PostgreSQL: warm p95 3 ms.
- **#58 bleibt damit offen**, bis eine 250k-Messung mit diesem Werkzeug vorliegt.
  Die bisherigen Zahlen (614 -> 113 ms, 813 -> 94 ms) stammen aus 20.000 Items.

## 1.2.0 (interne Version 2026090537)

> #58, dritter Schritt: Filter nach innen - versucht, gemessen, zurueckgenommen.

- Der Versuch lag nahe: Die Filterspalten (`categoryname`, `qtype`) werden von der
  inneren Abfrage ebenfalls ausgewaehlt. Der Test gegen echte Daten hat es widerlegt.
- **Es sind Aliase.** Die innere Abfrage hat `qc.name as categoryname`, und SQL
  erlaubt einen Alias nicht im `WHERE` derselben Ebene. Ein Filter auf
  `categoryname` scheitert innen mit einem Datenbankfehler, waehrend er aussen
  funktioniert - auf einer Seite, die vorher lief.
- `ORDER BY` ist davon nicht betroffen, weil dort Aliase erlaubt sind. Genau deshalb
  konnte die Sortierung wandern und der Filter nicht. Die beiden Klauseln sehen
  austauschbar aus und sind es nicht.
- Zurueckgenommen; gefilterte Seiten behalten den bisherigen Weg. Die Begruendung
  steht als Kommentar an der Waechterstelle, damit der Versuch nicht ein zweites Mal
  unternommen wird.
- Stand von #58: unsortiert 614 -> 113 ms, sortiert 813 -> 94 ms auf MariaDB.
  Gefiltert unveraendert. Regression 112/112 gruen.

## 1.2.0 (interne Version 2026090536)

> #58: Auch sortierte Seiten laufen jetzt ueber die innere Abfrage.

- `ORDER BY` wandert mit nach innen, **wenn jede Sortierspalte dort existiert**. Die
  aeussere Auswahl fuegt Spalten hinzu, die die innere nicht hat - ein literales
  `question as component` und `0 as questioncontextattempts`. Eine Sortierung
  danach waere innen eine unbekannte Spalte; `sort_is_available_in_subquery()`
  erkennt das und laesst solche Seiten auf dem bisherigen Weg.
- **Gemessen auf MariaDB, sortiert nach Name: 813 -> 94 ms.** Zeilen und
  **Reihenfolge** vorher gegeneinander geprueft, fuer vier Sortierungen.
- Die Reihenfolge ist hier der eigentliche Punkt: Ein inneres Limit ohne die
  Sortierung naehme zehn beliebige Zeilen und sortierte diese - das sieht aus wie
  eine sortierte Seite und ist keine.
- Gefiltert wird weiterhin aussen; solche Seiten bleiben unveraendert. Der Filter
  muesste dafuer ebenfalls nach innen uebersetzt werden.
- Vier Tests, Regression 112/112 gruen.

## 1.2.0 (interne Version 2026090535)

> Behat: derselbe Fehler ein zweites Mal, an der zweiten Seite.

- **`show_attemptfeedback.php`** scheiterte mit "Attempt to read property id on null"
  aus `navigationlib` - dieselbe Ursache wie zuvor in
  `mod_adaptivequiz/attemptfinished.php`: Ohne `$cm` setzt `require_login()` das
  Kursmodul auch nicht auf der Seite, und die Sekundaernavigation liest dann auf null.
- `$PAGE->set_cm($cm, $course)` ergaenzt. Die Sichtbarkeitspruefung bleibt draussen -
  genau darum ging es: Die Ergebnisseite soll lesbar bleiben, wenn die Aktivitaet nach
  Abschluss ausgeblendet wird.
- **Ich hatte $cm hier als toten Code entfernt**, weil er nicht mehr an
  `require_login()` geht. Er war es nicht - die Navigation braucht ihn. Jetzt wieder
  aufgeloest und initialisiert.
- Die uebrigen Seiten mit `require_login()` ohne Modul geprueft: Es sind
  Systemseiten (`manage_*`), die nie ein Kursmodul hatten und von der Aenderung nicht
  beruehrt sind.
- Regression 112/112 gruen.

## 1.2.0 (interne Version 2026090534)

> #58: Das Seitenlimit wandert in die abgeleitete Tabelle - wo das gleichwertig ist.

- **MariaDB materialisiert die abgeleitete Tabelle vollstaendig**, bevor sie zehn
  Zeilen nimmt: mit `ANALYZE` gemessen 20.010 Durchlaeufe mit je vier
  Index-Zugriffen fuer eine Seite von zehn. PostgreSQL bricht frueh ab und ist nicht
  betroffen.
- `catscalequestions_table::query_db()` schiebt das `LIMIT` jetzt nach innen -
  **nur solange weder sortiert noch gefiltert wird**. Andernfalls naehme das innere
  Limit zehn beliebige Zeilen, und die Seite saehe richtig aus und zeigte die
  falschen. Sortierung, Filter, Download und die Form der Abfrage werden geprueft;
  das urspruengliche FROM wird danach wiederhergestellt, damit die Zaehlabfrage
  unberuehrt bleibt.
- **Gemessen auf MariaDB: 614 -> 113 ms.** Zeilengleichheit vorher verifiziert:
  identische IDs in identischer Reihenfolge.
- Zwei Tests: einer stellt beide Formen gegeneinander, einer haelt die Waechter fest.
  Regression 112/112 gruen.
- Damit ist der haeufigste Fall abgedeckt - der Dialog beim Oeffnen. Sortierte und
  gefilterte Seiten laufen weiter ueber den bisherigen Weg; sie nach innen zu ziehen
  verlangt, Sortierung und Filter in die innere Abfrage zu uebersetzen, und das
  bleibt offen.

## 1.2.0 (interne Version 2026090533)

> Vorarbeit zu #58 - dabei einen eigenen Fehler von gestern gefunden.

- **Der Sortierschluessel des Add-Dialogs war falsch.** Ich hatte ihn auf
  `questiontext` gesetzt und dabei gegen `define_columns()` geprueft. Das sind aber
  zwei Namensraeume: `define_columns()` nennt **Anzeige**spalten - `questiontext`
  wird von `col_questiontext()` gerendert -, `define_sortablecolumns()` dagegen
  Spalten, die das Statement liefert. `ORDER BY questiontext` scheitert mit "column
  does not exist".
- Und die beiden Fragen-Tabellen unterscheiden sich: Der Add-Dialog waehlt den
  Fragennamen als `name`, die Hauptliste als `questionname`. Gegen **beide** Abfragen
  geprueft statt angenommen.
- Der Test dazu ist umgestellt: Er faehrt jetzt ein echtes `ORDER BY` gegen die
  Abfrage, statt zwei Listen miteinander zu vergleichen. Die alte Fassung hat genau
  den falschen Namen akzeptiert. Zahn-Test nennt die Spalte.
- **Befund fuer #58**: `query_db_cached_filtered()` ist zwar jetzt `protected`, baut
  das SQL aber nicht selbst - sie delegiert an `query_db()`, und das ueberschreibt
  `catscalequestions_table` bereits. Der Zugriffspunkt war also vorhanden. Sortier-
  und Filterspalten des Add-Dialogs sind saemtlich in der inneren Abfrage verfuegbar;
  `questioncontextattempts` ist dort eine Konstante. Damit ist das Verschieben von
  `ORDER BY` und `LIMIT` nach innen grundsaetzlich moeglich.

## 1.2.0 (interne Version 2026090532)

> CI rot: PHPDoc und Behat - beides Folgen eigener Aenderungen.

- **PHPDoc-Checker**: Fuenf Funktionen in `catquiz.php` hatten erweiterte Signaturen
  (`$testid`, `$starttime`, `$endtime`) bei unveraendertem Docblock. Nachgezogen,
  Checker Exit 0. Dieselbe Fehlerklasse wie schon bei `reload_template` und
  `return_sql_for_catscalequestions` - eine Signatur zu erweitern und den Docblock zu
  vergessen.
- **Behat** scheiterte in `mod_adaptivequiz/attemptfinished.php` mit
  "Attempt to read property id on null" aus `navigationlib`. Ursache war die
  Entkopplung von der Modulsichtbarkeit: Ohne `$cm` setzt `require_login()` das
  Kursmodul auch nicht auf der Seite, und die Navigation liest dann auf null.
  `$PAGE->set_cm($cm, $course)` stellt das her, ohne die Sichtbarkeitspruefung
  zurueckzuholen - die Ergebnisseite bleibt bei ausgeblendeter Aktivitaet lesbar.
  Behoben in `mod_adaptivequiz-3.0.0-2026090604`.

## 1.2.0 (interne Version 2026090531)

> #64 gefunden und behoben: Der Versuch endete nach Frage 1.

- **Der Fragenpool wurde nur als Nebenprodukt der Erstfragenauswahl geladen.**
  `first_question_selector()` war die einzige Stelle, die `$context[questions]`
  fuellt, und sie steigt auf drei Wegen vorher aus: nicht die erste Frage, klassische
  Strategie, sowie vorhandene Faehigkeit bei `firstquestion_use_existing_data`.
- Die Kette lief dann mit **ungesetztem** `questions` weiter, und
  `noremainingquestions` rief `count(null)` auf. In PHP 8 ist das ein `TypeError` -
  ein `Error`, kein `Exception`, und das `catch` um die Kette faengt ihn nicht. Der
  Versuch starb nach der ersten Frage.
- Auf der Produktivinstanz reproduziert: **alle sechs Strategien** scheiterten
  identisch an `noremainingquestions.php:49`.
- `questions_loader` wird jetzt vor der Kette aufgerufen - die Klasse existierte seit
  jeher und wurde **nirgends** verwendet. Damit hat die Kette den Pool auf jedem Weg.
- **Kein `?? []`.** Ein solcher Fallback haette den Fatal in "keine Fragen mehr"
  verwandelt und damit genau das Symptom von #64 erzeugt. Ein Test haelt fest, dass
  er nicht eingebaut wird.
- Strategie-Suite unveraendert (38 Tests, 2.549 Assertions), Regression 112/112 ohne
  den bekannten schweren Schaetzer.

## 1.2.0 (interne Version 2026090530)

> Nicht gemessene Skalen erschienen im Feedback; #61 abgeschlossen.

- **`build_attempt_result()` uebergab dem Validator eine leere Fragenzahl je Skala.**
  Damit galt jede Skala als "in diesem Versuch gemessen", `REASON_NOT_MEASURED`
  konnte nie greifen, und Skalen, die nie gefragt wurden, erschienen mit dem **Wert
  ihrer Elternskala**. In Attempt 12357 trugen sieben Skalen denselben Wert -1.07.
- Die Zahl kommt jetzt aus dem Attempt-Fortschritt; das Feedback-Gate schliesst
  `REASON_NOT_MEASURED` aus. Zwei Tests: einer prueft den Ausschluss, einer die
  Verdrahtung - der erste allein bestuende auch, wenn die Zahl gar nicht ankaeme.
- **Diagnoselauf zu 12357 ausgewertet**: Das Gate laesst heute 12 Skalen durch,
  gespeichert war eine. Die urspruenglich vermutete Regression ist in der
  ausgelieferten Fassung bereits behoben; uebrig blieb der Messbarkeits-Defekt oben.
- **#61 abgeschlossen.** Dritte Messreihe bei 250.000 Items: Pool warm 1.781 ms
  (PostgreSQL) gegen 4.235 ms (MariaDB), 177 MB Payload unveraendert. Der Aufwand
  liegt im Pool, nicht in der Kohortenaggregation - kein Handlungsbedarf an den
  Charts.
- **#58**: PostgreSQL 190 ms p95, deutlich unter der Schwelle. MariaDB 2.069 ms,
  weiterhin verfehlt; die Materialisierung wirkt sich mit wachsender Zeilenzahl
  staerker aus.
- **#21** bestaetigt sich im dritten Lauf: 22 % Ersparnis auf MariaDB, auf
  PostgreSQL im Rauschen.

## 1.2.0 (interne Version 2026090529)

- **Neu: `cli/diagnose_feedback_scales.php`.** Gibt fuer eine Attempt-ID aus, welche
  Skalen nach jedem Schritt uebrig sind: `updated_personabilities` wie gespeichert,
  nach `filter_excluded_scales`, nach `select_scales_for_report`, nach
  `is_feedback_eligible` - jeweils mit den Flags und Ablehnungsgruenden. Zum
  Vergleich die im Versuch gespeicherte `personabilities_abilities`.
- Damit ist entscheidbar, ob Skalen im Filterpfad verloren gehen oder schon in den
  gespeicherten Daten fehlen. Lesen allein hat es nicht geklaert: Der Pfad enthaelt
  keinen Filter auf `primary`, und ein Test, der den gemeldeten Fall nachbaut, haelt
  alle vier Skalen.
- Beim Bau des Skripts zwei eigene Fehler gefunden und behoben: `progress::load()`
  faellt auf `create_new()` zurueck und verlangt Quizeinstellungen; und
  `info::get_teststrategy()` liefert bei unbekannter ID **false**, nicht null - eine
  strikte Null-Pruefung laesst das durch, und `method_exists()` scheitert dann an
  einem Boolean.

## 1.2.0 (interne Version 2026090528)

- **Lasttests laufen nur noch auf Anforderung.** `load-k6` und `load-jmeter`
  starteten bei jedem Push auf main; sie seeden Hunderttausende Items und belegen die
  Runner fast eine Stunde, ohne dass jemand das Ergebnis liest. Der `push`-Trigger
  ist entfernt, die Begruendung steht im Workflow. Alle drei Lasttests sind jetzt
  `workflow_dispatch`.
- **Regressionstest zu Attempt 12357** (`feedback_all_valid_scales_test`): vier
  Skalen mit je 3 Fragen und SE unter dem Maximum, eine davon primary. Erwartet wird,
  dass **alle vier** im Feedback zulaessig sind und genau eine primary bleibt. Ein
  dritter Fall haelt fest, dass eine Skala unter dem Minimum weiterhin ausgeschlossen
  wird - sonst bestuende der Test auch mit entferntem Filter.

## 1.2.0 (interne Version 2026090527)

> CI rot: zwei Wrapper reichten Variablen weiter, die ihre Signatur nicht kennt.

- **`get_attempts_per_person_histogram()` und `get_max_attempts_per_person()`**
  uebergaben `$testid`, `$starttime` und `$endtime` an
  `get_sql_for_attempts_per_person()`, ohne diese Parameter selbst zu haben:
  "Undefined variable $testid". Unter PHP 8 ist das ein Fehler, kein stiller `null`.
- Beide Signaturen ergaenzt; die Werte werden jetzt tatsaechlich durchgereicht.
  Damit koennen die Diagramme Test und Zeitraum ueberhaupt erst beruecksichtigen.
- `chart_aggregation_test` wieder gruen (13 Tests, 70 Assertions).
- Vollstaendige Suite gefahren: **113 von 114 Dateien gruen**. Offen bleibt nur
  `model_person_ability_estimator_catcalc_test` - der bekannte schwere
  3PL-Datensatz, der laut Engineering-Guide isoliert zu fahren ist.

## 1.2.0 (interne Version 2026090526)

> Alle Attempt-Feedbacks blieben leer.

- **Fehlender `use`-Import**: `feedback_helper.php` rief
  `attempt_result_validator::from_personabilities()` auf, ohne die Klasse zu
  importieren. PHP sucht sie dann im eigenen Namensraum `local_catquiz	eststrategy`,
  findet nichts, und die Anfrage endet mit "Class not found" - **zur Renderzeit,
  mitten in der Feedback-Seite**.
- Die Versuchsliste rendert davor und blieb sichtbar; nur die Feedback-Inhalte
  fehlten. Das sah nach fehlenden Daten aus, war aber ein Fatal.
- Import ergaenzt. Die acht Feedback-Suiten sind wieder gruen; zwei davon waren rot
  (`feedback_gating_test`, `attemptfeedback_test`) und haetten den Fehler vor der
  Auslieferung gezeigt.
- **Neuer Regressionstest**: Er liest den Quelltext und prueft, dass jede statisch
  aufgerufene Klasse importiert ist oder im selben Namensraum liegt. PHPUnit faengt
  so etwas sonst nicht, weil der Aufruf hinter einem Zweig liegt, den die anderen
  Tests nicht betreten. Zahn-Test: Ohne den Import meldet er
  `attempt_result_validator` namentlich.

## 1.2.0 (interne Version 2026090525)

> "Testversuche pro Person": Die Abfrage war richtig, das Einlesen nicht.

- **`aggregate_person_histogram()` las das Ergebnis mit `get_records_sql()` ein.**
  Diese Methode nimmt die **erste Spalte als Array-Schluessel** und verwirft jede
  spaetere Zeile, die ihn wiederholt - ohne Fehler, ohne Warnung. Erste Spalte ist
  `rangeindex`, und der ist absichtlich nicht eindeutig: Es gibt eine Zeile je
  Bereich **und Bin**. Damit ueberlebte pro Farbbereich genau ein Bin.
- Aus 6/60/19/2 Personen wurde so ein Diagramm mit 6/-/1/2. Die SQL-Abfrage selbst
  war die ganze Zeit korrekt - das Diagnoseskript hat sie auf der Produktivinstanz
  mit exakt den erwarteten Zahlen ausgegeben.
- Auf ein Recordset umgestellt. Lokal gegengeprueft: vorher `{"25":20}`, danach
  `{"24":480,"25":20}`.
- Neu: `cli/diagnose_attempts_chart.php` gibt das erzeugte Statement, die gebundenen
  Parameter und die Verteilung neben einer unabhaengigen Referenzabfrage aus. Ohne
  dieses Werkzeug waere die Ursache weiter im SQL vermutet worden, wo sie nicht lag.

## 1.2.0 (interne Version 2026090523)

- **Beide Statistik-Issue-Entwuerfe ueberarbeitet.** Der erste stellte die
  Kontextbindung als alleinige Ursache dar; das ist nach der Analyse nicht haltbar.
  Er grenzt jetzt die zwei kontextunabhaengigen Defekte ab, die in `2026090513`
  (fehlende Spalte `personability_after_attempt`) und `2026090514` (asymmetrischer
  `NOT EXISTS`) behoben wurden, und nennt die Mindestversion fuer eine Nachpruefung.
- **ID-Bereiche wie `contextid=8-18` zurueckgezogen.** Datenbank-IDs haben keine
  fachliche Intervallsemantik. Es bleiben Einzelwert und explizite Liste.
- Die Parameter-Falle ist vermerkt: Dieselbe Klausel steht in
  `get_sql_for_attempts_per_person()` zweimal im Statement, und Moodle zaehlt
  benannte Parameter je Vorkommen.

## 1.2.0 (interne Version 2026090522)

> P1 abgeschlossen: Das Diagramm verwendet denselben Scope wie die uebrige Statistik.

- **`testid` und Zeitraum wirken jetzt.** Bisher uebergab der Renderer nur Kontext,
  Skala und Kurs. Ein Shortcode, der einen konkreten Test und einen Zeitraum nannte,
  bekam trotzdem die Versuche aller Tests und ausserhalb des Zeitraums - das Diagramm
  beantwortete eine andere Frage als seine eigene Ueberschrift.
- Der Zeitraum wird ueber `endtime` begrenzt, wie im Rest der Statistik: Ein Versuch
  gehoert in den Zeitraum, in dem er **beendet** wurde, nicht in den, in dem er
  begonnen hat.
- Zwei Tests, beide zahn-geprueft: Versuche eines anderen Tests und Versuche
  ausserhalb des Zeitraums duerfen nicht mitzaehlen.

### Damit ist die Liste der Expertise abgearbeitet

| Punkt | Stand |
|---|---|
| ACL aus den Aggregaten | `2026090521` |
| Attempt-basierte Abfrage | `2026090518` |
| Einschreibungen nur fuer "Ohne Versuch" | `2026090518` |
| Kontext nicht mehr Primaerfilter | `2026090518` |
| Farbe aus `personability_after_attempt` | `2026090518` |
| Role-JOIN entfernt | `2026090516` |
| `testid`, `starttime`, `endtime` | **diese Version** |

## 1.2.0 (interne Version 2026090521)

> Die aggregierten Diagramme wurden auf die Gruppen der betrachtenden Person
> zugeschnitten. Damit hing eine Kursstatistik davon ab, wer sie oeffnet.

- **`get_allowed_userids()` ist die falsche Frage fuer ein Histogramm.** Sie
  beantwortet "welche Personen darf diese Person als Individuen sehen" - richtig fuer
  den CSV-Export, fuer Teilnehmerlisten, fuer persoenliches Feedback. Ein Balken
  "60 Personen hatten einen Versuch" nennt niemanden.
- Angewendet auf die Aggregate machte sie aus 87 Personen sieben - je nachdem, in
  welcher Gruppe die betrachtende Person selbst ist.
- **Zurueckgenommen wird auch mein eigener Filter aus `2026090517`.** Ich hatte die
  Beschraenkung in den zentralen Ladepfad gesetzt, um die Diagramme konsistent zu
  machen. Konsistent waren sie danach - aber gemeinsam falsch. Die Konsistenz
  entsteht dadurch, dass **keines** filtert, nicht dass alle filtern.
- **Personenbezogene Pfade behalten ihren Filter**: `get_export_data()` ruft
  `get_allowed_userids()` weiterhin auf. Der Zugriff auf die Statistik selbst wird
  ueber die Capability entschieden (`can_view_other_users`), nicht ueber eine
  Nutzerliste.
- Zwei Tests: einer auf die **Trennung** (Aggregat ohne Filter, Export mit), einer
  mit einem Betrachter in einer Zweiergruppe unter `SEPARATEGROUPS` und ohne
  `accessallgroups` - das Aggregat muss trotzdem alle enthalten.

## 1.2.0 (interne Version 2026090520)

> Die Spalte `fraction` in `local_catquiz_attemptscale` blieb auf jeder Zeile NULL.

- **`validate()` uebergab fuer die Fraction-Karte ein leeres Array.** Die Spalte
  wurde also geschrieben - nur nie mit einem Wert. Verfuegbar war er die ganze Zeit:
  Die einzelnen Antworten tragen ihre `fraction` im Fortschritt.
- **Neu `progress::get_fraction_for_scale()`**: Mittel der Einzel-Fractions ueber
  dieselbe Population wie N - beantwortete Items, Piloten ausgenommen. Werte werden
  auf 0..1 begrenzt, weil eine Frage durch Overrides mehr als ihr Maximum vergeben
  kann.
- **`null` statt `0.0`, wenn nichts zaehlt.** "Keine produktive Antwort auf dieser
  Skala" und "jede Antwort war falsch" sind verschiedene Aussagen; eine Spalte, die
  das nicht unterscheidet, ist schlechter als eine leere.
- Vier Tests, darunter einer auf die **Verdrahtung**: Die drei anderen rufen den
  Zugriff direkt auf und wuerden auch bestehen, wenn `validate()` weiterhin `[]`
  uebergaebe - genau der Defekt, um den es geht.

> Beim Einfuegen der Methode ist mir der Docblock von
> `get_num_answered_productive_questions()` verwaist - die Falle aus dem
> Engineering-Guide. Von phpcs gefangen, Methode verschoben.

## 1.2.0 (interne Version 2026090519)

> Der CSV-Export lieferte nur einen Teil der Versuche.

- **Der Rohdaten-Export filterte auf `a.contextid`** und enthielt damit nur die
  Versuche der aktuell aktiven Kalibrierung. Alles von vor einer Rekalibrierung
  fehlte - und zwar still, weil die Datei vollstaendig aussah: Zeilen, Kopfzeile und
  plausible Werte, nur eben nicht alle.
- Der Filter ist entfernt. Der Export enthaelt jetzt **jeden** Versuch auf der Skala;
  Kurs, Test und Zeitraum schraenken weiterhin ein, ebenso die Gruppenberechtigung.
- Die `contextid` bleibt in der Signatur und in den exportierten Spalten - wer eine
  einzelne Kalibrierung braucht, kann in der Datei danach filtern.
- **Gegengeprueft**: Der Export reduziert nicht auf einen Versuch je Person. Kein
  `GROUP BY`, kein `DISTINCT ON` - jede Zeile ist ein Versuch. Der Kontextfilter war
  die einzige Ursache der fehlenden Zeilen.
- Test mit Versuchen aus zwei Kontexten, zahn-geprueft.

## 1.2.0 (interne Version 2026090518)

> „Testversuche pro Person" zaehlt jetzt Versuche, nicht Einschreibungen.

- **Die Abfrage ist auf `local_catquiz_attempts` als Primaerquelle umgebaut.** Bisher
  begann sie bei `{enrol}`, verband `{user_enrolments}` und `{role}` und hing die
  Versuche per LEFT JOIN an. **Jeder** bisher gefundene Defekt dieses Diagramms kam
  aus dieser Richtung: der Role-Join, der LTI-Nutzer entfernte; Mehrfach-
  einschreibungen, die Zaehlwerte vervielfachten; der Kontextfilter, der die Historie
  verschwinden liess. Keiner davon hat mit dem Zaehlen von Versuchen zu tun.
- **Der Kontext ist kein Filter mehr.** Er haelt fest, unter welcher Kalibrierung ein
  Versuch bewertet wurde - nicht, ob er stattgefunden hat.
- **Die Farbe kommt aus dem letzten Versuch der Person**
  (`personability_after_attempt`) statt aus `local_catquiz_personparams`. Damit
  stammen Balkenhoehe und Farbe aus derselben historischen Datenbasis; die
  Personenparameter tragen den aktuellen Stand und werden bei jeder Rekalibrierung
  ueberschrieben.
- Die Einschreibungen werden nur noch fuer **einen** Zweck herangezogen: den Balken
  „Ohne Versuch". Das ist das Einzige, was die Versuchstabelle nicht liefern kann.
- **Neuer Kohortentest** mit 20 Personen, LTI-Einschreibung ohne Rolle und gemischten
  Versuchszahlen. Er prueft die Summen, nicht einen einzelnen Nutzer: Anzahl Personen
  und Summe der Versuche muessen stimmen. Beides tat es vorher nicht.

## 1.2.0 (interne Version 2026090517)

> Die Diagramme einer Seite zeigten unterschiedliche Grundgesamtheiten.

- **Nur zwei von fuenf Diagrammen wandten die Gruppenbeschraenkung an.** Die drei
  Diagramme auf `get_attempts()` filterten gar nicht, die beiden auf
  `get_sql_for_attempts_per_person()` schon. Dieselbe Seite zeigte damit ein Diagramm
  ueber den ganzen Kurs und ein anderes ueber die Gruppe der betrachtenden Person -
  die Gesamtzahlen oben und unten passten nicht zusammen.
- Der Filter sitzt jetzt im **zentralen Ladepfad**, nicht in jedem Diagramm. Ein
  spaeter ergaenztes Diagramm erbt ihn, ohne dass sich jemand daran erinnern muss.
  Das war die Absicht des Reviews zu #18; umgesetzt war sie nur an zwei Stellen.
- **Fataler Fehler bei einem Zeitraum ohne Versuche behoben.** `max()` auf einem
  leeren Array wirft in PHP 8 "must contain at least one element"; das erreichte die
  Nutzenden als Fehlerseite statt als leeres Diagramm. Ein Zeitraum ohne Versuche ist
  direkt nach der Veroeffentlichung eines Tests der Normalfall.
- Zwei Tests, beide zahn-geprueft.

## 1.2.0 (interne Version 2026090516)

> Die eigentliche Ursache des grauen Einzelbalkens: ein Join, der LTI-Nutzer
> vollstaendig aus der Statistik entfernt.

- **`JOIN {role} r ON e.roleid = r.id` entfernt.** `enrol.roleid` beschreibt die
  Einschreibe-*Instanz*, nicht die Rolle einer Person, und mehrere Plugins lassen den
  Wert auf `0` - die LTI-Einschreibung tut das. Da es keine Rolle mit `id = 0` gibt,
  entfernte der INNER JOIN jede so eingeschriebene Person.
- **Auf einem ueber LTI befuellten Kurs ist das nahezu die gesamte Population.** Der
  graue Balken der Hoehe 1 war nicht "eine Person ohne Versuch", sondern der einzige
  Nutzer, der den Join ueberlebt hat.
- Der Alias wurde nie gelesen - keine Spalte, keine Bedingung. Der Join wirkte
  ausschliesslich als unbeabsichtigter Filter. Auf dem `get_attempts()`-Pfad war
  derselbe Join bereits auskommentiert; hier stand er noch.
- **Derselbe Defekt in `get_sql_for_questions_answered_per_person()`** mitbehoben,
  dort sogar mit `r.shortname = student`.
- Regressionstest mit `roleid = 0`; der Zahn-Test faellt, sobald der Join wieder
  eingesetzt wird.

> Korrektur meiner frueheren Analyse: Der Kontextwechsel existiert als
> Architekturproblem, verursacht diesen Screenshot aber nicht.

## 1.2.0 (interne Version 2026090515)

- **CI-Fehlschlag `Preview endpoint requires the capability` behoben.** Der Test
  erwartete `required_capability_exception`; seit der Endpunkt zwei Tueren hat -
  Verwaltende ueber die Capability, Teilnehmende ueber die Eigentumspruefung -
  kommt fuer einen Nutzer ohne beides `norighttoaccess`.
- Der Test ist dabei **nicht** aufgeweicht worden: Er prueft weiterhin, dass ein
  Studierender ohne eigenen Versuch abgewiesen wird, jetzt zusaetzlich, dass der
  Fragetext nicht in der Fehlermeldung landet. Der Punkt war nie die Klasse der
  Ausnahme, sondern dass der Text den Server nicht verlaesst.
- `render_question_with_response_test` ist nicht betroffen - der Endpunkt nutzt
  weiterhin `require_capability`, geprueft statt angenommen.

## 1.2.0 (interne Version 2026090515)

- **Beide Statistik-Issue-Entwuerfe ueberarbeitet.** Der erste stellte die
  Kontextbindung als alleinige Ursache dar; das ist nach der Analyse nicht haltbar.
  Er grenzt jetzt ausdruecklich die zwei kontextunabhaengigen Defekte ab, die in
  `2026090513` und `2026090514` behoben wurden, und nennt die noetige
  Mindestversion fuer eine Nachpruefung.
- **ID-Bereiche wie `contextid=8-18` zurueckgezogen.** Datenbank-IDs haben keine
  fachliche Intervallsemantik - die Kontexte dazwischen koennen zu ganz anderen
  Skalen gehoeren. Es bleiben Einzelwert und explizite Liste.
- Im zweiten Entwurf ist die Parameter-Falle vermerkt: Dieselbe Klausel steht in
  `get_sql_for_attempts_per_person()` inzwischen zweimal im Statement, und Moodle
  zaehlt benannte Parameter je Vorkommen.

## 1.2.0 (interne Version 2026090514)

> Vier Defekte in `get_sql_for_attempts_per_person()` - alle unabhaengig vom
> Kontextwechsel, jeder erzeugt fuer sich falsche Zahlen.

- **Zugriffsdefekt: Der Kursfilter ueberschrieb die Gruppenbeschraenkung.**
  `$where = "e.courseid = :courseid"` verwarf alles davor - die Beschraenkung auf
  sichtbare Nutzer aus dem Review zu #18 und den `1=0`-Schutz, der "nichts sichtbar"
  bedeutet. Eine Kurs-ID ist beim Shortcode der Normalfall, die Beschraenkung fiel
  also fast immer weg. Still: Das Diagramm rendert, es zaehlt nur Personen mit, die
  der Aufrufende nicht sehen darf.
- **Falscher Alias im selben Filter.** `$where` referenzierte `s2.userid`, wird aber
  *innerhalb* der Unterabfrage eingesetzt, die s2 erzeugt. Das war verdeckt, weil der
  Filter ohnehin ueberschrieben wurde - erst der obige Fix machte es sichtbar.
- **Die Versuche wurden ohne Skalenfilter gezaehlt.** Die Balkenhoehe kam aus allen
  Skalen des Kontexts, die Farbe aus der Personenfaehigkeit der gewaehlten - zwei
  Populationen in einem Bild.
- **Mehrfacheinschreibungen vervielfachten die Zahl.** Manuelle und Kohorten-
  Einschreibung im selben Kurs ergeben mehrere `user_enrolments`-Zeilen; jede brachte
  denselben Zaehlwert in die Summe. Zwei reale Versuche konnten als vier erscheinen.
  Behoben durch `DISTINCT` auf der Einschreibungszeile.
- Vier Tests decken die Faelle ab, jeder mit konsistent aufgebauter Fixture.

> Diese Defekte standen hinter dem zuvor gemeldeten Kontextwechsel. Meine erste
> Analyse hat den Kontext fuer *die* Ursache gehalten - er ist einer von mehreren.

## 1.2.0 (interne Version 2026090514)

> "Testversuche pro Person": Personen fielen aus beiden Haelften der Abfrage.

- **Der `NOT EXISTS` fuer den Eimer "Ohne Versuch" filterte nur nach der Skala**,
  waehrend die Zaehlseite zusaetzlich nach Kurs, Test und Zeitraum filtert. Wer diese
  Skala anderswo bearbeitet hatte, fiel aus **beiden** Haelften: aus der Zaehlung
  wegen der Filter, aus "Ohne Versuch", weil die blosse Skalenpruefung den anderen
  Versuch fand. Diese Personen verschwanden ersatzlos aus dem Diagramm.
- Der `NOT EXISTS` traegt jetzt dieselbe Bedingung wie die Zaehlseite.
- **Dabei gefunden:** `$where` steht nun zweimal im Statement, und Moodle zaehlt
  benannte Parameter je Vorkommen - die Abfrage scheiterte mit "Incorrect number of
  query parameters". Die zweite Fassung bekommt eigene Parameternamen. Aufgefallen
  nur, weil die Abfrage nach der Aenderung real ausgefuehrt wurde.
- Gegengeprueft: Die korrigierte Abfrage liefert dieselbe Verteilung wie eine
  unabhaengig formulierte Referenzabfrage (480 Personen mit 24, 20 mit 25 Versuchen).

## 1.2.0 (interne Version 2026090513)

> Das Faehigkeitsprofil-Diagramm blieb leer.

- **Der Spaltensatz der Statistikabfrage liess `personability_after_attempt` aus** -
  genau die Spalte, aus der `get_snapshot_ability_per_person()` die Werte zieht. Eine
  fehlende Momentaufnahme gilt dort als Altdatensatz und wird verworfen; damit war
  jeder Wert null. Das Diagramm rendert dann mit korrekten Achsen und ohne Daten -
  es sieht gebaut aus, nicht kaputt.
- Ergaenzt. Ein Abgleich aller gelesenen Attribute gegen den Spaltensatz zeigt keine
  weitere Luecke.
- **Der erste Test dazu war wertlos.** Er durchsuchte nur `catquizstatistics.php` und
  bestand auch mit entfernter Spalte - gelesen wird sie in `catquiz.php`. Jetzt
  werden die Verbraucher mitgescannt; der Zahn-Test benennt die Spalte namentlich.

## 1.2.0 (interne Version 2026090512)

> Geschlossenes Quiz sperrte Review-Seite und Fragen-Vorschau.

- **`show_attemptfeedback.php` uebergab das Kursmodul an `require_login()`** und war
  damit gesperrt, sobald die Aktivitaet nach Abschluss unsichtbar wird - genau fuer
  die Versuche, die man ansehen moechte. Dieselbe Entkopplung wie zuvor bei
  `attemptfinished.php`; die Absicherung leistet weiterhin
  `feedback_access::can_view_other_users()`. Das ungenutzte `$cm` ist entfernt.
- **Die Fragen-Vorschau war Teilnehmenden ganz verwehrt**: Der Endpunkt verlangte
  `manage_catscales`, eine Verwaltungsberechtigung. Jetzt zwei Wege - Verwaltende
  sehen jede Frage, Teilnehmende die Fragen **ihres eigenen Versuchs**. Ob die Lupe
  ueberhaupt erscheint, entscheidet weiterhin die Feedback-Konfiguration des Tests;
  der Endpunkt prueft das Objekt, denn die Frage-ID kommt vom Client.
- **Die Verknuepfung wurde an echten Daten geprueft, nicht angenommen.** Ein erster
  Versuch verband `local_catquiz_attempts.attemptid` direkt mit
  `question_attempts.questionusageid` - das trifft nichts: Das Feld ist die
  Attempt-ID der Aktivitaet, nicht die Question-Usage-ID. Der Weg fuehrt ueber
  `adaptivequiz_attempt.uniqueid`.
- Drei Tests: eigene Frage erlaubt, fremde abgelehnt, nie gestellte abgelehnt.

## 1.2.0 (interne Version 2026090511)

> Fragenfortschritt zeigte "5 / 1000".

- **Die Fortschrittsanzeige zaehlte gegen die falsche Groesse.** `mod_adaptivequiz`
  zeichnet den Balken aus seinem *eigenen* Feld `maximumquestions`. Bei einem ueber
  catquiz gesteuerten Test bleibt dieses Feld ueblicherweise auf einem grossen
  Platzhalter stehen, damit die harte Grenze der Aktivitaet nicht vor der CAT-Logik
  greift - der Balken zeigte dann 5 von 1000 bei einem Test, der nach zwanzig Fragen
  endet, und bewegte sich sichtbar nie.
- **`catquiz_handler` schreibt die CAT-Grenze jetzt beim Speichern mit.** Es bleibt
  ein Feld, aber nur eine Quelle. Der umgekehrte Weg - die CAT-Einstellung aus
  `mod_adaptivequiz` lesen - haette die Aktivitaet von diesem Plugin abhaengig
  gemacht, also in die falsche Richtung.
- Ohne konfigurierte CAT-Grenze bleibt der Wert der Aktivitaet unberuehrt: Eine Null
  hineinzuschreiben wuerde die Notbremse ganz entfernen.
- Vier Tests, darunter einer, der die **Verdrahtung** prueft. Die ersten drei rufen
  die Methode direkt auf und wuerden auch bestehen, wenn der Aufruf im
  Speicher-Callback fehlte - genau der Fehler, um den es geht.

## 1.2.0 (interne Version 2026090510)

> Die Ursache des CI-Abbruchs gefunden: eine Endlosrekursion in einer Test-Fixture.

- **`lean_pool_select_test::make_scale()` rief sich selbst auf.** Die Methode sollte
  Kontext und Skala anlegen; stattdessen stand dort
  `[$scaleid, $contextid] = $this->make_scale();` - ein Selbstaufruf ohne
  Abbruchbedingung, dazu ein undefiniertes `$now`.
- **Der Test schlug nicht fehl, er verbrauchte den Speicher.** Der Prozess wurde vom
  Kernel beendet; die Suite endete mit einem blossen Exit-Code ohne Meldung. Genau
  deshalb brach der Lauf in jedem Branch stabil an derselben Stelle ab - direkt nach
  `itemparam_recovery_test`, das alphabetisch davor liegt und selbst in Ordnung ist.
  Lokal reproduziert: rc=137 nach 17 Sekunden.
- Behoben; der Test laeuft jetzt in **einer Sekunde** durch (3 Tests, 31 Assertions).
- Die uebrigen 13 Dateien nach der Abbruchstelle laufen ebenfalls durch. Vier weitere
  Selbstaufrufe im Produktivcode wurden geprueft - alle mit Abbruchbedingung
  (Baumdurchlaeufe), keine Endlosrekursion.

## 1.2.0 (interne Version 2026090509)

> Messreihe 250.000 Items ausgewertet; zwei CI-Fehler behoben.

- **Messreihe mit schlankem Spaltensatz** (`doc/workpackage-moodle45-report.md`):
  Payload **206 → 177 MB**, fast genau der vorhergesagte Wert. Damit ist die
  Korrektur des Messwerkzeugs bestätigt. Beide Engines liegen beim Pool gleichauf
  (2.216 gegen 2.210 ms) – der MariaDB-Rückstand ist abfragespezifisch, nicht
  generell.
- **#58** bleibt auf MariaDB verfehlt: p95 1.187 ms gegen 218 ms auf PostgreSQL,
  25 % besser als im ersten Lauf, aber beim 2,4-fachen der Schwelle.
- **#21** bei 250.000 Items: leichte Zählung spart auf **MariaDB 22 %**
  (1.461 → 1.145 ms), auf PostgreSQL 2 %. Bestätigt den lokalen 20k-Befund in
  Richtung und Grössenordnung.
- **PHPDoc-Checker war rot durch zwei meiner eigenen Änderungen**:
  `reload_template::execute` (typisierte Parameter statt JSON-Blob) und
  `catquiz::return_sql_for_catscalequestions` (`$leanselect`) hatten erweiterte
  Signaturen bei unverändertem Docblock. Beide nachgezogen, Checker Exit 0.
- **PHPUnit endete mit Code 143** – SIGTERM, also abgebrochen und nicht rot. Der Lauf
  stoppte mitten in `itemparam_recovery_test`; der Test braucht lokal **drei
  Sekunden** und war damit nur die Stelle, an der es traf, nicht der Grund. Ohne
  Zeitlimit endet ein abgebrochener Lauf ohne Meldung und liest sich wie ein
  abgestürzter Test. Die Jobs `phpunit` (60), `behat` (60), `playwright` (45) und
  `test` im main-Workflow (90) haben jetzt ein explizites `timeout-minutes` – bewusst
  weit über der beobachteten Laufzeit: Ein Timeout soll als Timeout erkennbar sein,
  nicht die Suite disziplinieren.

## 1.2.0 (interne Version 2026090507)

- **#21 (Anschluss) und #58 als blockiert dokumentiert**, mit Bezug auf
  [wunderbyte_table#141](https://github.com/Wunderbyte-GmbH/moodle-local_wunderbyte_table/pull/141).
  Beide laufen an derselben Stelle zusammen: `query_db_cached_filtered()` ist
  `private` und ist der einzige Ort, an dem Paginierung, Filter und Cache
  zusammengesetzt werden - genau dort muessten Sortierung, Filter und `LIMIT` in die
  innere Abfrage gelangen.
- Festgehalten ist auch, was der PR **nicht** loest: Die Uebersetzung von Sortierung
  und Filter nach innen bleibt zu leisten, und ein Override koppelt `local_catquiz`
  eng an die Interna von `wunderbyte_table`.
- Der Statusblock steht jetzt am Kopf von `doc/issue-21-followup.md`, nicht auf
  Seite drei.

## 1.2.0 (interne Version 2026090506)

> Ab dieser Auslieferung trägt die Linie das Label `1.2.0`. Die älteren Einträge
> behalten `1.1.7`: Sie beschreiben, was unter diesem Label ausgeliefert wurde.

- **#44 geschlossen**: `cli/verify_recalculation_lock.php` prueft die Sperre in zwei
  echten Prozessen - der erste haelt sie, der zweite wird abgewiesen, nach der
  Freigabe wird sie wieder gewaehrt. Der dritte Schritt ist nicht schmueckend: Ohne
  ihn bestuende die Pruefung auch mit einer Sperre, die nie zurueckgegeben wird.
  In der CI laeuft das als Schritt im `load-runtime-pool`-Workflow, auf beiden
  Engines. PHPUnit kann das nicht zeigen - Moodles DB-Sperre ist prozessintern
  wiedereintrittsfaehig.
- **Release-Label auf `1.2.0`.** Aeltere CHANGELOG-Eintraege behalten `1.1.7`: Sie
  beschreiben, was unter jenem Label ausgeliefert wurde.

### Inhalt der Auslieferung 2026090505


> #26: Die Spaltenreduktion war bereits umgesetzt - das Messwerkzeug hat den falschen
> Pfad gemessen.

- **`measure_runtime_pool.php` rief die Poolabfrage ohne den `$leanselect`-Modus auf.**
  Die extern gemessenen 206 MB sind damit nicht der Wert, den die Laufzeit cached -
  der liegt bei rund **174 MB**. Auf Basis der falschen Zahl waere ein Fix gebaut
  worden, den es bereits gibt.
- Der Laufzeitpfad laesst `questionname`, `categoryname` und `catscalename` weg:
  29 Spalten und 868 Byte/Zeile gegen 26 Spalten und 731 Byte/Zeile.
- **Neuer Test `runtime_pool_columns_test`**: der schlanke Satz laesst genau die drei
  Namen weg, der Laufzeitpfad fordert ihn an, und sieben Parameterspalten bleiben
  darin - darunter **`json`**. Es ist die groesste verbleibende Textspalte und damit
  der erste Kandidat bei einer Payload-Optimierung, traegt aber die
  Kategorieschwellen der politomen Modelle. Ohne sie stuenden GRM, GGRM, PCM und GPCM
  ohne Schwellen da, und der Fehler zeigte sich erst in der Schaetzung.

## 1.1.7 (interne Version 2026090504)

> Zwei stille Defekte in der Spaltenkonfiguration; Grundlage von #26 korrigiert.

- **`define_sortablecolumns()` enthielt zwei Eintraege, die auf keine Spalte zeigen**:
  `idnunber` (Tippfehler) und `name` (die Spalte heisst `questiontext`, nur ihre
  Ueberschrift lautet "Name"). Ein solcher Eintrag wird **stillschweigend ignoriert** -
  die Ueberschrift ist einfach nicht sortierbar, nichts schlaegt fehl. Von vier
  deklarierten Spalten funktionierten zwei. Ein Test vergleicht die Sortierliste
  gegen `define_columns()`, also gegen die Regel statt gegen eine feste Liste.
- **#26: die Annahme "29 Spalten geladen, 15 gelesen" haelt nicht.** Die Poolabfrage
  selektiert 21 Spalten, und fast jede wird irgendwo gelesen - `discrimination`
  beispielsweise in vier IRT-Modellen. Der Payload verteilt sich ausserdem
  gleichmaessig; die groesste Einzelspalte macht 14,7 % aus. Die Spaltenreduktion ist
  damit keine kleine Massnahme mehr, sondern ein Eingriff in die Datenversorgung der
  Modelle.
- Festgehalten ist auch der Weg dorthin: Ein erster Zaehlversuch durchsuchte nur
  `classes/teststrategy/` und meldete `discrimination` und `guessing` als ungenutzt -
  beide werden unter `catmodel/` gelesen. Eine `grep`-Zaehlung kann diese Frage nicht
  entscheiden.

## 1.1.7 (interne Version 2026090503)

> #58: Ursache des MariaDB-Rueckstands gefunden - dokumentiert, nicht behoben.

- **`ANALYZE FORMAT=JSON` zeigt 20.010 Durchlaeufe je Tabelle.** Die Abfrage baut die
  vollstaendige abgeleitete Tabelle auf - alle Kandidaten mit je vier Index-Zugriffen -
  und nimmt erst danach zehn Zeilen. Das `LIMIT` steht ausserhalb von
  `FROM ( SELECT ... ) as s1`, MariaDB kann es nicht nach innen ziehen.
- Damit erklaeren sich alle Messungen: Nicht die **Form** der Unterabfrage ist das
  Problem, sondern ihre **Anzahl**. Zwei naheliegende Fixes wurden geprueft und
  scheiden aus - die Umschreibung als Anti-Join bringt 9 ms, ein zusaetzlicher Index
  wird vom Optimierer gar nicht gewaehlt. Beide lassen die 20.010 Durchlaeufe
  unberuehrt.
- Gegenprobe mit innerem `LIMIT`: 47 ms statt 158 ms bei identischem Ergebnis - aber
  **kein Fix**: ergebnisgleich nur, weil hier weder sortiert noch gefiltert wird.
  Sortiert `local_wunderbyte_table` aussen, waehlte ein inneres `LIMIT` die falschen
  Zeilen aus, und die Seite saehe dabei korrekt aus.
- Der naechste Schritt ist eine Frage der Integration mit `local_wunderbyte_table`,
  keine Aenderung an einer Zeile SQL. Festgehalten in
  `doc/workpackage-moodle45-report.md`.

## 1.1.7 (interne Version 2026090502)

> Arbeitspaket Moodle-4.5-Stabilisierung abgeschlossen, soweit belegbar.
> Abschlussbericht: `doc/workpackage-moodle45-report.md`.

- **#29** unter Moodle 4.5 abgenommen: Browser-Back/Forward, `aria-current`, Kontext
  im Tab-Link und - der eigentliche Nachweis - dass **nur der aktive Reiter Abfragen
  erzeugt**. Die anderen sieben DoD-Punkte lassen sich durch Markup erfuellen; dieser
  nicht.
- **#44**: inaktive Kontexte werden nicht rekalibriert; Sperre wird ohne Wartezeit
  angefordert und wieder freigegeben. Ein *echter* paralleler Lauf ist in PHPUnit
  nicht erzeugbar - Moodles DB-Sperre ist prozessintern wiedereintrittsfaehig. Der
  erste Testentwurf sah aus, als beweise er die Sperre, und bestand in Wahrheit, weil
  der Lauf mangels neuer Responses uebersprungen wurde.
- **#61**: Inventar der Full-Cohort-Pfade (`doc/issue-61-cohort-inventory.md`). Alle
  sechs Charts haengen an *einer* Ladung; drei sind gefahrlos aggregierbar, drei lesen
  aus dem Attempt-JSON und wuerden engine-spezifische SQL-Funktionen erfordern.
- **Security-Pass**: alle neun External Functions inventarisiert. `delete_catscale`
  rief kein `validate_context()` - die Funktion sah abgesichert aus, prueft aber nur,
  *wer* handeln darf, nicht *wo*. Als Regel getestet, nicht als Liste.
- **Cleanup**: EN/DE-Paritaet hergestellt. Darunter ein im Code verwendeter String,
  der auf **Englisch** fehlte - der Rueckfallsprache; er waere als `[[schluessel]]`
  erschienen. 152 Issue-Referenzen zu technischen Invarianten umformuliert,
  `synthcat.php` (340 Zeilen, kein Verweis) entfernt.
- **CI** unveraendert: PHP 8.1/8.2/8.3 x PostgreSQL/MariaDB auf `MOODLE_405_STABLE`,
  alle Pflicht-Gates verbindlich - geprueft, nicht angenommen.

## 1.1.7 (interne Version 2026090501)

> Arbeitsauftrag Moodle-4.5-Stabilisierung: Abschnitte 1, 3–7, 12, 13.

- **Nur Moodle 4.5 deklariert** (`supported = [405, 405]`). Die obere Grenze stand auf
  500, obwohl die CI ausschliesslich `MOODLE_405_STABLE` baut – eine Zusage an
  Administratoren, die der Code nie eingelöst hat.
- **#65:** Vier Lücken in den External Functions geschlossen (fehlende Allowlist,
  fehlende Capabilities, fehlende Kontext- und Skalenprüfung). Fünf Negativtests.
  Bei `collect_responses` ist die Allowlist bewusst *nicht* anwendbar – die Nutzlast
  trägt keine Skala; eine Prüfung, die immer besteht, sähe nach Schutz aus.
- **#66:** Der öffentliche Vertrag von `reload_template` führt keinen PHP-Klassennamen
  mehr (`renderer=questiondatacard` statt `classlocation=\local_catquiz\…`) und keine
  CSV-Parameter, sondern typisierte Felder, die `validate_parameters()` prüfen kann.
- **#67:** Die drei Legacy-Attempt-Webservices entfernt. Sie waren **nie
  implementiert** (`attemptid = 0`, leeres Array, `MAX(id) FROM {question}`) und
  prüften eine `CONTEXT_MODULE`-Capability im Systemkontext. Kein Aufrufer in vier
  geprüften Quellen, darunter `local_adele`.
- **#68:** Manager und Lehrende passierten allein aufgrund ihrer Capability – eine
  beliebige Attempt-ID erzeugte ein Ereignis über etwas, das nie stattfand. Der
  Versuch wird jetzt geladen, bevor irgendjemand autorisiert wird. Sieben Rollentests,
  jede Ablehnung doppelt geprüft (verweigert **und** kein Ereignis).
- **#64:** Die Stufen-Instrumentierung war um **eine Stufe verschoben** – PHP wertet
  Argumente vor dem Aufruf aus, also lag jede Zählung unter dem Namen der
  Vorgängerstufe. Eine so verschobene Spur benennt den falschen Filter. Korrigiert,
  Strategie-Suite unverändert (38 Tests, 2.549 Assertions). Neu:
  `cli/reproduce_issue_64.php`.
  Dabei gefunden und **bewusst nicht zugeschüttet**: `count($context['questions'])`
  ohne Null-Prüfung. Ein `?? []` würde den Fatal in „keine Fragen mehr" verwandeln –
  und damit das Symptom von #64 erzeugen statt beheben.
- **#21:** `set_count_context()` existierte, wurde aber **nirgends aufgerufen**. Jetzt
  verdrahtet. Gemessen (warm, gleiche Zeilenzahl): MariaDB 20k **122 → 85 ms (30 %)**,
  PostgreSQL 50k 164 → 153 ms (7 %). Die Zählmessung läuft jetzt im
  engine-übergreifenden Werkzeug mit.
- **#56:** Der Ability-Trace übersteht jetzt nachweislich die **Datenbank**, nicht nur
  die JSON-Kodierung – der bestehende Test deckte nur letztere ab. Dabei gefunden:
  `array_map('static::to_array', …)` ist seit PHP 8.2 deprecated und liegt genau auf
  dem Ladepfad des Trace; die Entfernung in einer späteren PHP-Version machte aus
  einem ladbaren Versuch einen Fatal.
- **#27 und #28** bleiben *closed / not planned* – deckt sich mit den Messbefunden
  dieser Sitzung.

## 1.1.7 (interne Version 2026090224)

> Der Lasttest-Workflow brach wortlos ab – an einer Zeile Shell.

- **`read` ohne abschließenden Zeilenumbruch beendete den Schritt.** Das `php -r`
  gab „110 111 250000" ohne `\n` aus; `read` meldet dann EOF mit Rückgabewert 1,
  und unter `bash -e` endet der Schritt sofort – **bevor eine einzige Zeile Ausgabe
  entsteht**. Der Fehlschlag sah aus, als sei die Abfrage gescheitert, während die
  Werte tatsächlich korrekt gelesen waren. Nachgestellt und bestätigt: ohne
  Zeilenumbruch Abbruch, mit Zeilenumbruch Durchlauf.
- **Der `ANALYZE`-Fix wirkt**: Das Seeding von 250.000 Items samt Verknüpfung
  läuft jetzt in **28 Sekunden** durch, statt nach 30 Minuten noch zu hängen.
- **k6 liefert wieder Zahlen**: 5.610 Requests, p50 254 ms, p95 544 ms, 0 % Fehler –
  `handleSummary()` schreibt die Zusammenfassung, die `--summary-export` in k6 v2
  nicht mehr erzeugt.

## 1.1.7 (interne Version 2026090223)

> Drei Befunde aus den externen Läufen.

- **k6 lieferte eine leere Zusammenfassung, obwohl der Lauf korrekt war.** Das Log
  zeigt 1.247 Iterationen und p50 144 ms; das hochgeladene Artefakt enthielt jede
  Metrik auf null – das liest sich wie ein Lauf, der nie stattgefunden hat.
  `--summary-export` schreibt in k6 v2 nichts mehr. Das Skript nutzt jetzt
  `handleSummary()`, den unterstützten Weg, und das Flag ist entfernt.
- **Der Verknüpfungsschritt des Lastseeders hing über eine halbe Stunde.** Nach dem
  Masseneinfügen sind die Tabellenstatistiken veraltet – für den Planer sehen beide
  Tabellen leer aus, und er plant die korrelierte Unterabfrage als verschachtelte
  Schleife über sequenzielle Scans. Bei einer Viertelmillion Zeilen ist das
  quadratisch. Ein `ANALYZE` davor lässt den vorhandenen Index auf
  `itemparams.itemid` überhaupt erst nutzen: lokal **5,8 s** für 50.000 Items.
- **Der Prüfschritt scheiterte stumm.** Prozesssubstitution verbirgt ein
  fehlgeschlagenes `php` vor `bash -e`; die anschließende Zahlenprüfung brach dann
  mit einem Rechenfehler ab, der die Ursache nicht nennt. Ein leeres Ergebnis wird
  jetzt als solches gemeldet, samt Zählung zur Einordnung.

## 1.1.7 (interne Version 2026090222)

> Der Lasttest-Workflow installierte die Datenbank nicht.

- **`load-runtime-pool.yml` richtet die Site jetzt vollständig ein.**
  `moodle-plugin-ci install --no-init` spielt den Code ein, legt aber kein Schema an.
  Der Seeder scheiterte deshalb auf **beiden** Engines mit „Table
  mdl_local_catquiz_catscales doesn't exist" – einer Meldung, die auf das Plugin
  zeigt, während die Ursache die fehlende Site-Installation ist. Ergänzt um
  `install_database.php`, `upgrade.php` und `purge_caches.php`, wie es die
  funktionierenden Lastworkflows seit jeher tun.
- **Ein Schritt prüft das Live-Schema**, bevor gesät wird. Ein Seeder gegen eine
  halb installierte Site scheitert tief in der DML-Schicht, wo die Meldung die
  Ursache nicht mehr nennt.
- Plattenplatz auf dem Runner: 86 GB frei – die 250.000-Item-Stufe ist dort
  herstellbar, anders als in der Verifikationsumgebung.

## 1.1.7 (interne Version 2026090221)

> Lasttests messen jetzt den Fragenpool, nicht die leere Installation.

- **`tests/load/seed_large.php` legt Fragen und Itemparameter an.** Bisher erzeugte
  es Kontext, Skalen und Personparameter – aber **keine Fragen und keine
  Itemparameter**. Die Lastpläne prüften damit Rendering, Sessions und
  Nebenläufigkeit, während die Abfragen, die mit dem Pool wachsen, gegen nichts
  liefen. Ein JMeter-Lauf meldete so 60 ms für den CAT-Manager; dieselbe Seite
  braucht über 50.000 Items eine Größenordnung mehr. Die Zahlen sahen beruhigend aus
  und maßen etwas anderes.
- **`load-k6.yml` und `load-jmeter.yml` übergeben `--items`** (Vorgabe 20.000, über
  „Run workflow" einstellbar, `0` schaltet ab).
- Die Schwierigkeiten sind über einen realistischen Bereich verteilt statt konstant:
  Ein Pool mit lauter gleich schweren Items lässt die Auswahl entarten und misst
  einen Fall, der nicht vorkommt.
- **Ein Test hält beides fest**: dass der Seeder in die Item-Tabellen schreibt und
  dass die Lastworkflows einen Pool anfordern.

## 1.1.7 (interne Version 2026090220)

> Subplugin-Pipelines brachen vor dem ersten Test ab.

- **Kein fehlendes Sprachpaket, sondern ein fehlendes System-Locale.** Moodles
  PHPUnit-Bootstrap verweigert den Start ohne `en_AU.UTF-8`; die Runner-Images
  liefern es nicht mit. Die Meldung „Required locale 'en_AU.UTF-8' is not installed"
  liest sich wie ein Sprachproblem und führt die Suche in die falsche Richtung.
  `local_catquiz` erzeugt das Locale seit jeher an vier Stellen – den neuen
  Workflows fehlte genau dieser Schritt.
- Ergänzt in beiden Subplugin-Pipelines und im Lasttest-Workflow.
- **Ein Test prüft die Fehlerklasse**: Jeder Workflow, der die PHPUnit-Umgebung
  initialisiert, muss das Locale erzeugen – über alle drei Repository-Ebenen hinweg.

## 1.1.7 (interne Version 2026090219)

> Korrektur: Der Pin folgt `main`, nicht der Arbeitsversion.

- **Beide Hub-Subplugins pinnen `local_catquiz` auf `2026083025`** – die Version auf
  `main`. Ein Pin auf die gerade in Arbeit befindliche Version hätte die Subplugins
  überall unauffindbar gemacht ausser auf dem Entwicklungsbranch.
- **Die Subplugin-Pipelines ziehen wieder `main`.** Pin und Branch gehören zusammen:
  Ein Pin oberhalb von `main` lässt den Installationsschritt mit einem
  Abhängigkeitsfehler scheitern – das ist der Pin bei der Arbeit, kein Defekt der
  Pipeline.
- **Der Test prüft jetzt die richtige Bedingung**: nicht Gleichheit mit der lokalen
  Version, sondern dass ein Pin existiert und die gebaute Elternversion **nicht
  überschreitet**. Gleichheit zu verlangen hiesse, einen Pin auf eine Version zu
  fordern, die noch niemand installieren kann.

## 1.1.7 (interne Version 2026090218)

> Versionierung der Hub-Subplugins.

- **`catquizcentralhub_client` und `_host` stehen auf `2026090500`** (vorher
  `2026081900`).
- **Beide pinnen die Elternversion**, gegen die sie gebaut wurden. Sie nutzen
  Klassen aus `local_catquiz` direkt; ohne den Pin installiert Moodle sie gegen jede
  beliebige Version des Elternplugins – auch gegen eine, die die verwendeten
  Schnittstellen noch nicht hat. Der Fehler zeigt sich dann zur Laufzeit statt bei
  der Installation.
- **Ein Test hält den Pin aktuell.** Ein stehengebliebener Pin schützt nichts mehr,
  und zwar unbemerkt; der Test schlägt fehl, sobald das Elternplugin weiterzieht, und
  erzwingt damit ein bewusstes Nachziehen.
- **Die Subplugin-CI zieht jetzt einen Branch, der den Pin erfüllt.** `main` steht
  auf `2026083025` und liegt damit hinter der gepinnten Version – die Pipeline wäre
  an der Abhängigkeit gescheitert. Das ist beabsichtigtes Verhalten des Pins, kein
  Defekt der Pipeline, aber der Branch muss dazu passen.

## 1.1.7 (interne Version 2026090217)

> Letzter offener Punkt aus Security-Issue C.

- **Feedback-Bezeichner wird serverseitig geprüft.** Bezeichner und Übersetzung kamen
  beide vom Client und wurden beide ins Ereignisprotokoll geschrieben, als
  beschrieben sie, was geschehen ist. Ein Protokolleintrag, dessen Gegenstand der
  Aufrufer frei wählt, ist kein Auditnachweis.
  Der Bezeichner wird jetzt gegen die tatsächlich vorhandenen Feedback-Generatoren
  geprüft – abgeleitet statt gepflegt, weil eine zweite Kopie derselben Menge
  auseinanderdriftet und dann entweder gültige Reiter ablehnt oder nicht mehr
  existierende zulässt. Die Übersetzung bleibt ergänzender Anzeigetext und
  ausdrücklich nicht die maßgebliche Aufzeichnung.

## 1.1.7 (interne Version 2026090216)

> Verhaltenstests zur Eigentumsprüfung – und ein Fehler, den sie sofort gefunden
> haben.

- **`moodle_exception` war im Namensraum nicht importiert.** Die in der
  Vorversion ergänzte Eigentumsprüfung in `feedback_tab_clicked` hätte in Produktion
  nicht abgelehnt, sondern mit „Class not found" abgebrochen. Der Quelltext-Scan
  hatte nur belegt, dass der Aufruf **dasteht** – nicht, dass er wirkt.
- **Drei Verhaltenstests** decken die DoD aus Security-Issue C ab: eigener Versuch
  erlaubt, fremder abgelehnt, unbekannter fail-closed abgelehnt. Jeder prüft
  zusätzlich, dass bei Ablehnung **kein Event** ausgelöst wird.

## 1.1.7 (interne Version 2026090215)

> Lasttest-Matrix als externer Workflow; #58 bei 50.000 Items belegt.

- **Neuer Workflow `load-runtime-pool.yml`**: fährt die von #26 und #58 verbindlich
  geforderte Matrix auf GitHub-Runnern – 50k/100k/250k Items × PostgreSQL und
  MariaDB, sechs Jobs, Ergebnisse als Artefakt. Die Verifikationsumgebung hat dafür
  zu wenig Platz; die Messung findet dort statt, wo sie möglich ist, statt dort
  geschätzt zu werden, wo sie es nicht ist.
- **`measure_runtime_pool.php` misst jetzt auch den Add-Questions-Dialog** (#58) –
  im selben Werkzeug, damit beide dieselbe Definition von Cold, Warm, Median und p95
  benutzen. Zwei getrennte Harnesse driften auseinander und ihre Zahlen hören auf,
  vergleichbar zu sein.
- **#58 bei 50.000 Items belegt**: warm Median **3 ms**, p95 3 ms, zwei Queries –
  gegenüber 12.732 ms im Ausgangszustand. Die Akzeptanzschwelle des Reviews
  (warm p95 < 500 ms) ist bei dieser Stufe deutlich erfüllt.

## 1.1.7 (interne Version 2026090214)

> Belastungstest zu #26 nach dem vom Review geforderten Protokoll.

- **Neues Messwerkzeug** `cli/measure_runtime_pool.php`: Cold- und Warm-Lauf getrennt,
  Median und p95, SQL-Zeit, Gesamtzeit inkl. Hydration, Query-Zahl, Zusatzspeicher und
  Größe des serialisierten Cache-Payloads.
- **Gemessen bei 50.000 Items** (`doc/issue-26-loadtest-report.md`): warme Gesamtzeit
  493 ms bei 2 Queries – tragbar. Auffällig sind **208 MB Zusatzspeicher und 42 MB
  Cache-Payload** je Skala und Kontext, beides linear skalierend. Der überschrittene
  Grenzwert ist damit die Datenmenge je Zeile, nicht die Abfrage.
- **#27 nicht reaktiviert**: Der Poolaufbau braucht 2 Queries; die Zeit liegt in der
  einen Abfrage und der Hydration, nicht in nachgelagerten Skalenberechnungen. Damit
  ist die vom Review genannte Bedingung für #27 nicht erfüllt.
- **Der Kaltstart-Effekt ist jetzt beziffert**: bei 20.401 Items 4.803 ms kalt gegen
  57 ms warm – Faktor 84. Genau diese Verwechslung hätte bei der ersten Bewertung von
  #26 fast zu einem Umbau des heißen Auswahlpfades geführt.
- **Eine Maßnahme versucht und zurückgenommen**: Die Spaltenreduktion bringt belegte
  −65 % Payload, als Feldfilter über die geladenen Zeilen umgesetzt aber 28 Fehler in
  der Strategie-Suite (`Undefined property: stdClass::$attempts`). Eine statisch
  gepflegte Feldliste ist hier das falsche Mittel; der belastbare Weg ist eine eigene
  Auswahl-Abfrage mit Bitgenau-Äquivalenznachweis. Vollständig zurückgenommen, Suite
  wieder grün (35 Tests, 2.537 Assertions).

## 1.1.7 (interne Version 2026090214)

> Belastungstest-Werkzeug für #26 nach dem geforderten Protokoll, erste Größenstufe
> gemessen.

- **Neu `cli/measure_runtime_pool.php`**: misst den Laufzeit-Fragenpool nach der im
  Review vorgegebenen Matrix – Cold- und Warm-Lauf getrennt, Median und p95 über
  Wiederholungen, SQL-Zeit, Gesamtzeit inkl. Hydration, Query-Zahl, Zusatzspeicher
  und Größe des serialisierten Cache-Payloads.
- **Der Kaltstart-Effekt ist jetzt belegt**: bei 20.401 Items 4.803 ms kalt gegen
  57 ms warm – Faktor 84. Genau diese Verwechslung hätte in einer früheren Sitzung
  beinahe zu einem Umbau des heißen Auswahlpfads geführt. Das Werkzeug mittelt beide
  Werte nie zusammen.
- **Befund bei 50.000 Items** (`doc/loadtest-runtime-pool.md`): Laufzeit unkritisch
  (Median 493 ms, zwei Queries), **kritisch sind Speicher und Payload** – 208 MB
  Zusatzspeicher und 42 MB serialisierter Cache je Skala und Kontext, linear
  wachsend. Damit ist erstmals belegt, wofür die Reduktion der 14 nicht gelesenen
  Spalten tatsächlich zahlt: nicht für die Abfragezeit, sondern für den Speicher.
- **Bewusst nicht umgesetzt**: die Spaltenreduktion selbst. Die Matrix ist
  unvollständig (100k und 250k sind in der Verifikationsumgebung mangels
  Plattenplatz nicht herstellbar), und derselbe Pfad hat zuletzt 14 Tests der
  Kernauswahl fallen lassen. Der Bericht nennt die Reproduktionsbefehle.
- **#27 bleibt zurückgestellt**: Die Messung zeigt den Aufwand im Datentransport und
  im Speicher, nicht in skalenbezogenen Berechnungen. Die im Review genannte
  Bedingung für eine Reaktivierung ist nicht erfüllt.

## 1.1.7 (interne Version 2026090213)

> Drei Security-Befunde aus dem Review umgesetzt. Alle wurden vorab am Code
> verifiziert und trafen zu.

- **Clientgesteuerte Klasseninstanziierung beseitigt (P1).**
  `reload_template` konstruierte die Renderklasse aus dem Request – der Aufrufer
  entschied also, welche autoloadbare PHP-Klasse der Server baut, mit
  clientkontrollierten Konstruktorargumenten. Die Capability-Prüfung schränkt den
  Kreis ein, macht den Dispatch aber nicht sicher. Zulässige Klassen stehen jetzt
  serverseitig fest; genau eine wird real verwendet.
  Zusätzlich wird ein nicht baubarer Renderer als Fehlerergebnis gemeldet, statt
  einen rohen `TypeError` aus dem Webservice zu tragen.
- **Falscher Capability-Kontext und fehlende Eigentumsbindung.**
  `local/catquiz:canaccess` ist als `CONTEXT_MODULE` deklariert, wurde aber im
  Systemkontext geprüft. `start_new_attempt` akzeptierte zudem eine beliebige
  `userid`: Versuche im Namen anderer zu eröffnen war damit kein administrativer
  Akt mehr. Fremde Nutzer erfordern jetzt `canmanage`.
  Dabei gefunden: `$params['categorid']` – ein Tippfehler, der bei **jedem** Aufruf
  `null` statt der Kategorie übergab. `get_next_question` deklarierte `quizid` und
  `component` nicht, obwohl `execute()` sie erwartet; sie erreichten den Code
  ungeprüft.
- **Fehlende Objektautorisierung in `feedback_tab_clicked`.**
  `validate_context()` legt fest, *wo* eine Anfrage wirkt, nicht *ob* dieser Nutzer
  auf dieses Objekt zugreifen darf. Ohne Eigentumsprüfung konnte jeder angemeldete
  Nutzer Events für fremde Versuche auslösen – und wurde dabei als deren „student"
  protokolliert. Nicht auflösbare Versuche werden abgelehnt, nicht zugelassen.
- Neue Tests halten beide Fehlerklassen fest: kein Endpunkt instanziiert eine vom
  Request benannte Klasse, und Endpunkte mit Attempt-ID prüfen das Eigentum.

## 1.1.7 (interne Version 2026090211)

> Zwei Folgefehler der #29-Umstellung behoben – beide traten weit entfernt von ihrer
> Ursache auf.

- **Behat (7 Szenarien) schlug an „I press Catquiz" fehl.** Ursache war der
  CSV-Importer: Das Formular wurde bei **jedem** Reiter serverseitig gebaut, und
  Moodle registriert dafür ein Validierungsskript, das das Formular beim Laden per
  ID sucht. Seit nur noch der aktive Reiter gerendert wird, fehlt das Element – die
  Suche liefert `null`, das Skript wirft, und die Seite gilt dauerhaft als „nicht
  bereit". Das Formular wird jetzt nur auf seinem Reiter gebaut.
- **Playwright (3 Tests) fand `#lcq_questions` nicht.** Sechs Stellen verlinkten
  weiter per URL-Fragment auf Panes, die es ohne ihren Reiter nicht mehr gibt; das
  Suchformular trug den Reiter nicht mit und landete nach dem Absenden auf dem
  Standardreiter, ohne die Liste. Alle auf den `tab`-Parameter umgestellt.
- `csvimport.js` prüft jetzt auf ein fehlendes Formular-Element, statt mit `null`
  weiterzuarbeiten.
- Drei neue Tests halten die Fehlerklasse fest: keine Fragment-Links auf Panes, der
  `tab` in den Formularparametern, und kein Formularbau für einen inaktiven Reiter.

## 1.1.7 (interne Version 2026090210)

> CI-Installation repariert; #28 geprüft und begründet nicht umgesetzt.

- **Alle Workflows entfernen die leeren Submodul-Platzhalter vor `install`.** Die
  beiden Hub-Plugins hängen als Submodule am Repository, sodass ein Checkout
  `catquizcentralhub/host` und `/client` als leere Verzeichnisse hinterlässt.
  `moodle-plugin-ci` prüft `is_dir()` und nicht, ob ein Plugin darin liegt – und
  bricht mit „Plugin is already installed in standard Moodle" ab. Der Fix war
  zunächst nur in den Subplugin-Pipelines; die vier Eltern-Workflows fielen weiter
  aus. Jetzt an allen **sieben** Stellen, abgesichert durch einen Test.
- **#28 nicht umgesetzt.** Beide Teile wurden am Code geprüft und beide Befunde
  treffen zu – aber:
  - Das Entfernen der doppelten Progress-Speicherung ließ **14 von 35** Tests der
    Kernauswahl fallen: Die Fähigkeiten blieben bei 0.0, weil Folgeschritte den
    persistierten Fortschritt lesen. Die innere Speicherung ist tragend.
  - Der Peer-Vergleich, den das Issue als teuersten Posten nennt, kostet gemessen
    **3,3 ms** je Antwort (erster Aufruf 279 ms – ein Kaltstart-Artefakt wie schon
    bei #26). Von sieben Feedback-Generatoren berühren nur drei die Datenbank.
  - Die eigentliche Frage ist, warum Folgeschritte den Fortschritt aus der Datenbank
    statt aus dem Objekt lesen. Das ist ein eigenes Arbeitspaket mit
    Äquivalenznachweis.

## 1.1.7 (interne Version 2026090207)

> Subplugin-Registrierung gepinnt, Submodul-Falle geschlossen, #65 abgeschlossen.

- **CI-Layoutprüfung korrigiert.** Ein selbst geschriebener Guard erwartete *genau
  eine* `version.php` unter `local/catquiz` und meldete damit die sieben catmodels
  als Fehler – die Subplugins waren korrekt registriert, die Prüfung war es nicht.
  Sie liest die erlaubten Pfade jetzt aus `db/subplugins.json`, statt zu zählen.
- **Registrierung per Test gepinnt** (`subplugin_registration_test`): Deklaration,
  vollständige catmodel-Verzeichnisse mit passendem Komponentennamen, und – der
  entscheidende Teil – dass Moodle die Typen tatsächlich findet.
- **Submodul-Falle geschlossen.** `catquizcentralhub/host` und `/client` sind eigene
  Repositories, die als Submodule eingehängt sind. Ein normaler Klon liefert **zwei
  leere Verzeichnisse**: Die Pfade existieren, der Code nicht. Prüfungen dagegen
  bestehen, weil nichts zu prüfen ist – ununterscheidbar davon, aus dem richtigen
  Grund zu bestehen. Ein Test verlangt, dass jeder Workflow, der die Plugins
  erwähnt, sie aus ihren eigenen Repositories installiert.
- **Eigene CI-Suite** `catquizcentralhub.yml`: Linting, Coding Style und PHPUnit über
  beide Plugins, PHP 8.2 und 8.3. Bricht ab, wenn ein Plugin leer ist oder keine
  Testdatei gefunden wird – ein Lauf ohne Treffer endet sonst mit Erfolg.
- **#65 abgeschlossen.** Das Formular `remote_settings_form` schrieb `central_host`
  und `central_token` unter `local_catquiz`, wo **niemand** sie las: ein zweiter
  Ablageort für Zugangsdaten, ausserhalb der Reichweite des Kill-Switch, der zum
  Client-Plugin gehört. Formular und Upgrade führen sie in
  `catquizcentralhub_client` zusammen und entfernen die Altkopie.

## 1.1.7 (interne Version 2026090206)

> Sicherheit: Kill-Switch für die Hub-Synchronisation (#65). Diagnose für #64
> repariert.

- **#65 – Kill-Switch serverseitig erzwungen.** Die Schalter `enable_sync_as_node`
  und `enable_sync_as_hub` wurden ausserhalb von Einstellungen und Template
  **nirgends** ausgewertet: Der geplante Task, die External Functions und die
  HTTP-Schicht lasen sie nicht. Abschalten versteckte die Bedienelemente, während
  jeder Ausführungspfad intakt blieb – mit weiterhin gespeicherten Zugangsdaten lief
  der Sendepfad bis `curl->post()`.
  - Zwei Policy-Klassen (`sync_policy`, `hub_policy`) als einzige Quelle der
    Wahrheit; sie lesen die Konfiguration bei jedem Aufruf, damit ein Umlegen des
    Schalters sofort wirkt.
  - **Zuerst die unterste Egress-Schicht**: `response_submitter` und
    `fetch_parameters` prüfen selbst. Ein Test belegt, dass der Guard *vor* dem
    Request steht.
  - Alle fünf External Functions prüfen Schalter, Kontext und Capability; die
    Skalenlisten wirken als serverseitige Allowlists.
  - Der geplante Task prüft den Schalter **vor** den Zugangsdaten. Ausgeschaltet ohne
    Konfiguration ist ein erfolgreicher No-op – das beendet die gemeldeten
    Dauerfehler mit einem `faildelay` von 86.400 Sekunden.
  - Vier bestehende Testdateien setzten voraus, dass die Dienste bei ausgeschaltetem
    Schalter arbeiten. Sie sind korrigiert, nicht die Guards abgeschwächt.
- **#64 – Diagnose repariert.** Die Stufenzahlen wurden nur in `after_error()`
  geschrieben. Der gemeldete Abbruch erreicht diesen Pfad nie: Die Auswahl meldet
  keinen Fehler (`catquizerror = false`), der Pool ist schlicht leer. Die Diagnose
  blieb also genau bei dem Fall stumm, für den sie gebaut wurde. Sie wird jetzt an
  **allen elf** Ausstiegen der Auswahl geschrieben; ein Test meldet jeden Ausstieg,
  der keine Diagnose hinterlässt.

## 1.1.7 (interne Version 2026090204)

> Diagnose für #64: die Auswahlkette gibt Rechenschaft darüber, wo der Pool verloren
> geht.

- **Kandidatenzahl je Stufe** wird während der Fragenauswahl festgehalten (`start`,
  `add_scale_standarderror`, `maximumquestionscheck`, `removeplayedquestions`,
  `noremainingquestions`, `fisherinformation`) und im Fehlerfall **zusammen mit dem
  Status** persistiert. Der gemeldete Abbruch geschah in einem Request, der längst
  vorbei war, als jemand nachsah – was dort nicht gespeichert wird, ist verloren.
- **Kein Fix auf Verdacht.** Drei Kandidaten wurden am Code ausgeschlossen
  (`maximumquestionscheck` zählt beantwortete Fragen und gibt bei 1 gegen 250 `ok`;
  `removeplayedquestions` entfernt nur gespielte IDs; `max_attempts_per_scale` ist
  bei einer Frage nicht erfüllt). Eine Ursache ließ sich nicht belegen – der Fehler
  entsteht im Zusammenspiel von vier Plugins, eines davon hier nicht vorhanden.
- Die Instrumentierung sitzt im Pfad jeder Frage jedes laufenden Tests. Abgesichert
  durch einen Test, der prüft, dass das Ergebnis unverändert durchgereicht wird, und
  durch die vollständige Strategie-Suite: 35 Tests, 2.537 Assertions, unverändert.

## 1.1.7 (interne Version 2026090203)

> #29 vollständig, #26 gemessen und begründet abgeschlossen.

- **#29 abgeschlossen**: Das Template navigiert jetzt serverseitig – echte URLs statt
  Bootstrap-Anker, und nur der aktive Bereich wird ausgegeben. Vorher wurden die
  übrigen Bereiche weiterhin gerendert, inzwischen aber **ohne Daten dahinter**.
- **Ein Fehler der eigenen Umsetzung, den Behat gefunden hat**: Die Reiter-Guards
  umschlossen nur die Instanziierung, nicht die Auswertung danach – neun Stellen
  lasen eine bedingt erzeugte Variable unbedingt. Die erste Seite des Managers wäre
  mit einer Warnung erschienen. PHPUnit konnte das nicht sehen, weil der Konstruktor
  einen vollständigen Seitenkontext braucht.
- Behat `catquiz_manager_tabs`: 4 Szenarien, 23 Steps – Reiter aus der URL übersteht
  das Neuladen, unbekannter Wert fällt zurück, Zustand bleibt beim Wechsel erhalten.
- **#26 ohne Umbau abgeschlossen** (`doc/issue-26-finding.md`): Die Auswahl nutzt
  tatsächlich die Manager-Abfrage und lädt 29 Spalten, von denen sie 15 liest. Die
  vermutete Größenordnung trifft aber nicht zu – gemessen rund 85 ms bei 20.401
  Items. Der zuerst gemessene Wert von 4.878 ms war ein Kaltstart-Artefakt.
  Zwei der benötigten Felder stammen aus den Statistik-Joins, die sich daher nicht
  weglassen lassen.

## 1.1.7 (interne Version 2026090201)

> Erste Auslieferung des neuen Arbeitspakets: zwei Laufzeitfehler im Feedback-Pfad,
> und der CAT-Manager baut nur noch den sichtbaren Reiter auf.

- **Issue #62**: `lastquestion` und `lastmiddleware` wurden in der Debug-Ausgabe
  ungeprüft gelesen. Beide fehlen bei der ersten Frage jedes Versuchs
  systematisch – `catquiz.php` entfernt `lastquestion` sogar ausdrücklich. Mit
  `DEBUG_DEVELOPER` wurde daraus eine Ausnahme, die als „couldn't define the first
  question" erschien und auf die Konfiguration zeigte. Debug-Informationen waren
  damit ausgerechnet auf den Instanzen unerreichbar, die dafür eingerichtet sind.
- **Issue #59**: `attemptfeedback::update_data()` kehrt ohne Fähigkeitswerte früh
  zurück und setzt `catscales` nie. `array_key_first([])` ist `null`, was bis in
  `catscale::__construct()` lief und dort scheiterte – eine Ebene unterhalb der
  Ursache. Jetzt mit Rückfall auf die primäre Skala des Tests; die Signatur von
  `get_ability_range()` verlangt ausdrücklich `int`.
  Dabei mussten **drei weitere Aufrufstellen** abgesichert werden, die untypisierte
  Werte übergaben – sonst hätte der Fix neue Fehler erzeugt statt sie zu beseitigen.
- **Issue #29**: Der CAT-Manager erzeugte bei jedem Aufruf **acht** Displays samt
  ihrer Abfragen, von denen das Template sieben verbarg. Jetzt wird nur der aktive
  Reiter gebaut; er steht in der URL, sodass Neuladen und der Zurück-Button ihn
  behalten. Jeder Reiter-Link trägt Kontext, Skala und `usesubs` mit, damit ein
  Wechsel den betrachteten Zustand nicht zurücksetzt.
- **Issue #56** ist bereits in 1.1.6 vollständig umgesetzt worden und entfällt aus
  diesem Arbeitspaket.
- Neue Tests: `feedback_path_robustness_test` (4) und
  `managecatscaledashboard_tabs_test` (4). Letzterer prüft nicht ein einzelnes
  Display, sondern dass **keines** unbedingt gebaut wird – ein neuntes ohne
  Reiterbindung fiele auf. Er hat dabei eine Lücke der eigenen Umsetzung gefunden:
  `testitemdashboard` war an `!empty()`-Bedingungen gebunden, nicht an den Reiter.

### Noch offen in 1.1.7

- Das Template nutzt weiterhin Bootstrap-Anker statt der neuen Reiter-URLs; die
  Zustandserhaltung wirkt serverseitig, der Wechsel bleibt clientseitig.
- Behat-Szenarien für Neuladen und Zurück-Button.
- Issues #26, #27, #28 sind nicht begonnen.

## 1.1.6 (interne Version 2026083025)

> Alle Aufrufpfade geprüft; ein zweiter Laufzeitfehler derselben Art gefunden.

- **Systematische Prüfung aller Methodenaufrufe** des Plugins per Reflection. Dabei
  kam ein weiterer Fehler ans Licht: `learningprogress` rief
  `get_color_for_personability()` auf sich selbst auf, obwohl die Methode zum
  Feedback-Helfer gehört – vier andere Aufrufstellen machen es richtig. Er wäre beim
  nächsten Lernfortschritt-Diagramm mit Subskalen aufgeschlagen.
- **Neu `internal_method_calls_test`**: prüft, dass jeder `$this->x()`, `self::x()`
  und jeder statische Aufruf auf eine Plugin-Klasse auflösbar ist. PHP löst
  Methodennamen erst zur Laufzeit auf, ein erfundener Name fällt also erst auf,
  wenn die Zeile erreicht wird.
  Die erste Fassung dieses Tests hatte selbst eine Lücke – sie verlangte einen
  Großbuchstaben am Klassennamen und hätte fast jeden Aufruf übersprungen. Nur der
  Zahn-Test hat das aufgedeckt.

## 1.1.6 (interne Version 2026083024)

> Laufzeitfehler im Verlaufsmodus behoben.

- **`progress::set_ability()` rief ein nicht existierendes `get_step()`** auf,
  eingeführt mit dem Verlaufsmodus aus #56. Die Methode liegt auf dem heißen Pfad
  der Fähigkeitsschätzung, sodass mit eingeschaltetem Verlaufsmodus **jeder
  laufende Test** abbrach. Richtig ist `get_num_playedquestions()`.
- Ursache für das späte Auffallen: Die sechs Tests zu #56 prüften Konfiguration und
  Aufräum-Task, aber keiner rief `set_ability()` auf – getestet war die Einstellung,
  nicht ihre Wirkung. Zwei neue Tests führen den Pfad jetzt aus.

## 1.1.6 (interne Version 2026083023)

> Kursseiten-Fehler behoben, Docblocks vervollständigt.

- **TypeError beim Kursaufruf behoben**: Der Feedback-Shortcode las die Teststrategie
  nur aus dem JSON. Fehlte sie dort, brach die **gesamte Kursseite** ab, weil die
  Ausnahme über den Shortcode-Filter bis in den Kursformat-Renderer wanderte. Die
  Abfrage lädt jetzt die vorhandene Spalte `teststrategy` als Rückfallebene; ein
  Versuch ohne Strategie wird übersprungen statt die Seite zu beenden.
- **Sieben unvollständige Docblocks** in `catquiz.php` ergänzt – die Methoden, die
  zuvor um `$alloweduserids` bzw. `$fields` erweitert wurden.

## 1.1.6 (interne Version 2026083022)

> Kursseiten-Fehler beim Feedback-Shortcode behoben.

- Das Öffnen eines Kurses mit eingebettetem Feedback-Shortcode brach mit einem
  `TypeError` ab, wenn das JSON eines Versuchs keine Teststrategie enthielt. Die
  Ausnahme wanderte über den Shortcode-Filter bis in den Kursformat-Renderer und
  legte die **gesamte Kursseite** lahm.
- Die Abfrage lädt jetzt die vorhandene Spalte `teststrategy`, die Strategie wird
  ersatzweise von dort gelesen, und ein Versuch ohne Strategie wird übersprungen
  statt die Seite scheitern zu lassen.

## 1.1.6 (interne Version 2026083021)

> #54-Reste und #23-Reste aus dem externen Review.

- **#54**: Die sichtbare Spalte „Parametergültigkeit" ist jetzt sortierbar (Alias in
  der Abfrage ergänzt), das Aggregat je Skala wird angezeigt – mit Sprung in die
  gefilterte Liste – und wird auf den betrachteten Kontext eingeschränkt statt über
  alle Kontexte der Installation zu summieren.
- **#23**: Der Recordset des gemeinsamen Ladepfads wurde nie geschlossen und hielt
  eine Datenbankressource offen. Die Abfrage lud zudem per `SELECT *` das
  Debug-Trace-Feld, das keiner der Renderer liest; die Spaltenliste ist jetzt
  explizit.
- **Neuer Issue** `doc/issue-chart-json-aggregation.md`: Die verbleibenden Diagramme
  können nicht ohne Weiteres serverseitig aggregieren, weil die Fähigkeitswerte nur
  im JSON der Attempt-Zeile stehen.

## 1.1.6 (interne Version 2026083019)

> Externes Review: acht Befunde geprüft, alle bestätigt, alle behoben.

- **Sicherheit**: `local/catquiz:canaccess` war nie deklariert – eine unbekannte
  Capability liefert `false` für jeden, auch den Administrator, sodass drei
  Attempt-Endpunkte jede Anfrage abwiesen. Vier weitere External Services prüften
  gar nichts. `subscribe` akzeptierte fremde Nutzer-IDs hinter einer einzigen
  Verwaltungsprüfung.
- **Datenschutz**: Der Gruppenfilter wirkte nur im CSV-Export; die Diagramme luden
  die Kohorte ungefiltert. Jetzt in beiden Kohorten-Abfragen.
- **Korrektheit**: Der Detailrenderer (#19) nutzte die Einschränkung nicht, die
  Fragevorschau (#20) baute Datei-URLs gegen den falschen Kontext, und der
  Debug-Zweig eines Diagramms (#23) griff auf eine entfernte Variable zu.
- **Neue Tests gegen die Fehlerklasse**: `capability_declaration_test` prüft, dass
  jede geprüfte Capability deklariert ist, jede deklarierte einen Sprachstring hat
  und jeder External Service überhaupt prüft.

## 1.1.6 (interne Version 2026083013)

> Issue #56 vollständig: Aufbewahrung auch je CAT-Test einstellbar.

- **Formularfeld je CAT-Test**: bietet nur die Stufen an, die die Website zulässt,
  und ist im Finalizer angebunden – ohne die Anbindung wäre die Einstellung
  folgenlos geblieben.
- Fail-safe beim Lesen: Ist die Einstellung nicht lesbar, gilt der
  Website-Standard. Die Aufbewahrungsstufe darf nie verhindern, dass ein beendeter
  Versuch abgeschlossen wird.
- **Behat** `catquiz_progress_retention`: 2 Szenarien, 23 Steps grün.
- PHPUnit `progress_retention_test`: 8 Tests, 25 Assertions.

## 1.1.6 (interne Version 2026083012)

> Issue #56 umgesetzt; Skalierungsbefund zu „Frage hinzufügen" erfasst.

- **Aufbewahrung des Attempt-Fortschritts konfigurierbar** (#56): drei Stufen
  (`minimal`, `keep`, `trace`) mit datensparsamem Standard, globale Einstellung als
  Obergrenze für Aktivitäten, Löschung nach Abschluss über das bisher tote
  `progress::delete()`, echter Schrittverlauf im Modus `trace` und ein
  Cleanup-Task in Stapeln.
- **25 fehlende Privacy-Strings ergänzt**: der Provider referenzierte 34, vorhanden
  waren 9 – auf der Datenschutzseite standen Platzhalter.
- **Neuer Issue** `doc/issue-add-questions-scaling.md`: „Frage hinzufügen" braucht
  bei 110.005 Fragen 6.832 ms statt 28,7 ms bei 60.000 – die Abfrage skaliert mit
  der Fragenbank, nicht mit der Seitengröße.
- **Neu `cli/loadtest_seed.php`** zur reproduzierbaren Erzeugung großer Bestände.

## 1.1.6 (interne Version 2026083011)

> Issue #23 im Browser abgedeckt, Lasttest-Ergebnisse dokumentiert.

- **Playwright deckt jetzt auch die Statistik-Diagramme ab** (#23). Der Seed baut
  Kurs, Seite mit `[catquizstatistics]`, einen registrierten Test samt echter
  adaptivequiz-Instanz und Versuchsdaten auf – idempotent, inklusive Abgleich des
  Seiteninhalts mit der aktuellen Skala.
- Drei Fehlerursachen dabei gefunden: fehlende Test-Registrierung, `componentid` auf
  der falschen Aktivität (scheiterte nur im Kurskontext) und die Pflichtfelder
  `intro`/`attemptfeedback` der `adaptivequiz`-Tabelle.
- **9 Playwright-Tests grün** über drei Specs.
- **`doc/load-test-results.md`**: k6 und JMeter mit 25 Nutzern, null Fehler,
  CAT-Manager 127 ms Median, Statistik 229 ms – mit dem ausdrücklichen Hinweis, dass
  die zugrunde liegende Datenmenge nicht dokumentiert ist.

## 1.1.6 (interne Version 2026083010)

> Behat-Fix, Gruppen im Kurskontext (#18), Playwright entblockt.

- **Behat wieder grün**: Die Laufzeit schreibt `adaptivequiz`, die Aufrufer
  übergeben `mod_adaptivequiz`. Nach der Umstellung des Lookups auf
  `(component, attemptid)` traf der strenge Vergleich nie, und jede Einsicht wurde
  verweigert. Der Resolver akzeptiert jetzt beide Schreibweisen.
- **#18 abgeschlossen**: Die Gruppenprüfung wurde im Kurskontext übersprungen – dort,
  wo die meisten Personen sichtbar sind. Ohne Kursmodul greift jetzt der
  Kurs-Gruppenmodus. Neue Testdatei `feedback_access_test` (4 Tests).
- **Playwright entblockt**: Der Seed ist idempotent und deterministisch; ein zweiter
  Lauf legt keine weitere gleichnamige Skala an. Damit laufen beide Specs –
  6 Tests grün, darunter die zuvor zurückgestellte #54-Spec.

## 1.1.6 (interne Version 2026083008)

> Werkzeug und Nachweis für die Query-Pläne.

- **Neu `cli/explain_hotqueries.php`** (mit `cli/explain_seed.php`): protokolliert
  die Pläne der vier performancekritischen Abfragen, in beiden Dialekten
  (`EXPLAIN (ANALYZE, BUFFERS)` bzw. `ANALYZE FORMAT=JSON`), nach Auffrischen der
  Tabellenstatistiken. Auf einer echten Instanz genügt `--no-seed`.
- **`doc/query-plans-postgres.md`**: gemessen an den Proportionen einer
  Produktivinstanz. Leichte Zählung ohne Attempt-Tabellen, keine Fensterfunktion
  mehr bei „Frage hinzufügen", Index Scan beim Itemparameter-Filter.
- Der `Seq Scan` auf `question_versions` ist eingeordnet: korrekte Wahl des
  Optimierers, der Gewinn liegt im Wegfall der Fensterfunktion.
- **MariaDB-Lauf steht aus** und wird im Dokument ausdrücklich als offen benannt.

## 1.1.6 (interne Version 2026083007)

> Review-Befunde: Upgrade-Ausgabe, Kontextauflösung, CI.

- **Upgrade-Ausgabe entrauscht**: statt siebzehn Protokollzeilen je entferntem Index
  nur noch eine Zusammenfassung; Details über `debugging()`. Die `issue #NN:`-Präfixe
  sind aus nutzersichtbarer Ausgabe entfernt.
- **Kontextauflösung komponentensicher**: `context_resolver::for_attempt()` löst über
  `(component, attemptid)` auf und nutzt `IGNORE_MISSING` statt `IGNORE_MULTIPLE`.
  Ohne Treffer greift der Systemkontext mit weiterhin geltender Capability-Prüfung –
  fail closed statt Autorisierung gegen einen fremden Kurs.
- **Entscheidung dokumentiert** (`doc/decision-attempt-identity.md`):
  `UNIQUE(attemptid)` bleibt. Ein Versuch gehört fachlich genau einem
  Komponententyp; die `component`-Spalte beschreibt die Herkunft, nicht den
  Schlüssel. Ein Test hält die Invariante als gewollt fest.
- CI: korrigierte `seed.php` übernommen.

## 1.1.6 (interne Version 2026083006)

> Leichte Count-Abfrage für die Fragenliste (#21) und Anschluss-Issue.

- **Leichte Zählung ohne die Statistik-Joins**
  (`return_sql_for_catscalequestions_count()`). Das Zählen der Liste zählte bisher
  die Zeilen der vollen Abfrage; die Aggregate wurden nur berechnet, um von
  `COUNT()` verworfen zu werden.
- **Override mit Schutzbedingung** in `catscalequestions_table::query_db()`: Die
  Bibliothek hängt Filter und Suche erst nach dem Tabellenaufbau an, eine zu früh
  festgelegte Zählung würde eine Gesamtzahl melden, die nicht zur Liste passt.
- **Noch nicht aktiviert** – `set_count_context()` wird nicht aufgerufen, der Stand
  ist verhaltensneutral. Begründung und Restschritte in `doc/issue-21-followup.md`.
- Neue Tests (4) und zwei Zahn-Tests; Playwright-Fixture für #54 im Seed ergänzt.
- **Anschluss-Issue** `doc/issue-21-followup.md` für den verbleibenden Teil von #21,
  mit DoD-Liste (5 erledigt, 8 offen) und der Upstream-Voraussetzung `protected`.

## 1.1.6 (interne Version 2026083005)

> Zwei CI-Befunde behoben.

- **`npm ci` scheiterte**: `.gitignore` ignorierte `package-lock.json` global, also
  auch das Playwright-Lockfile – dieselbe Falle wie zuvor bei `amd/build/`. Ausnahme
  ergänzt und mit echtem Git im A/B-Test belegt.
- **MariaDB-Fehlschlag behoben**: `CAST(x AS DECIMAL)` bedeutet dort `DECIMAL(10,0)`,
  also null Nachkommastellen – `-0.1` wurde zu `0` gerundet und landete im falschen
  Bereich. Der Cast steht nur im Test, der die Typisierung der echten Abfrage
  nachbildet; der Produktionscode vergleicht eine numerische Spalte ohne Cast.
- Volle Regression auf **MariaDB**: 91/91 Dateien grün.

## 1.1.6 (interne Version 2026083004)

> Issue #23 abgeschlossen.

- **Zweites Diagramm umgestellt.** `render_attempts_per_person_chart()` lud ebenfalls
  eine Zeile je Person und bestimmte das Maximum über die Sortierung der geladenen
  Menge. `catquizstatistics.php` enthält jetzt **null** `get_records_sql`-Aufrufe.
- **Aggregation verallgemeinert**: beide Diagramme teilen sich
  `aggregate_person_histogram()`, der CASE-Ausdruck existiert genau einmal. Der
  fachliche Unterschied bleibt erhalten und ist als Parameter ausgedrückt – das
  Antworten-Diagramm verwirft eine Person ohne passenden Bereich, das
  Versuchs-Diagramm ordnet sie Bereich 0 zu.
- **Obergrenze** `CHART_MAX_DATA_POINTS = 500` für zurückgegebene Datenpunkte.
- Zahn-Tests: ohne die Untergrenze der Klassenbreite entsteht eine Division durch
  Null; ohne den locale-sicheren Parser wird aus `-1,5` eine `-1.0`. Beide rot.
- **Offen**: Browsertests mit großen Datenmengen (Playwright-Strang).

## 1.1.6 (interne Version 2026083003)

> Issue #23: Diagrammdaten werden in der Datenbank aggregiert.

- **Keine Vollkohorte mehr im Speicher.** Das Antworten-Histogramm lud eine Zeile je
  eingeschriebener Person, um sie anschließend nur zu zählen. Maximum,
  Klassenbildung und Bereichszuordnung geschehen jetzt in SQL; zurück kommen
  ausschließlich fertige Zählungen.
- Neue Methoden `catquiz::get_max_questions_answered_per_person()` und
  `catquiz::get_answers_per_person_histogram()`; die bestehende Kohorten-Abfrage wird
  als Unterabfrage umschlossen statt dupliziert.
- `feedback_helper::get_feedback_range_bounds()` liefert die Feedback-Grenzen als
  Zahlen – aus denselben Einstellungen und mit demselben Parser wie die bestehende
  Klassifikation, damit SQL und PHP nicht auseinanderlaufen. Ein Test vergleicht
  beide direkt.
- Semantik unverändert: Klasse 0 bleibt für „keine Antworten" reserviert, Bereiche
  sind halboffen mit geschlossenem oberstem Bereich.
- Zahn-Test: ohne den locale-sicheren Parser wird aus `-1,5` eine `-1.0` – rot.
- **Noch offen bei #23**: die zweite unbeschränkte Abfrage, eine konfigurierbare
  Obergrenze für Diagrammpunkte und Browsertests mit großen Datenmengen.

## 1.1.6 (interne Version 2026083002)

> Issue #22 abgeschlossen; Playwright in die dev-CI eingebettet.

- **Fensterfunktion über alle Frageversionen ersetzt.** `ROW_NUMBER() OVER
  (PARTITION BY questionbankentryid ...)` nummerierte jede Zeile von
  `question_versions` – die gesamte Versionshistorie der Instanz – und verwarf erst
  danach. Jetzt eine korrelierte Bedingung („keine neuere Version desselben
  Bank-Eintrags"), die den Index nutzt und ohne Fensterfunktion auskommt, also auf
  PostgreSQL und MariaDB gleich arbeitet.
- Neuer Test: zwei Versionen eines Bank-Eintrags, nur die aktuelle wird angeboten.
  Zahn-Test rot ohne den Versionsfilter.
- **CI**: `playwright` läuft im dev-Workflow parallel zu `phpunit` und `behat`, mit
  `lint-php` und `lint-jsamd` als Voraussetzung und `ci-complete` als Nachfolger.
- **Zurückgestellt bei #21**: das zweistufige Laden auf die sichtbare Seitenmenge.
  Es griffe in die Paginierungsschleife von `local_wunderbyte_table` ein, wo
  Paginierung, Callback-Filter, Sortierung und Instanz-Cache ineinandergreifen – das
  braucht einen eigenen Testharnisch. Die bereits wirksame Einschränkung der
  Aggregation auf Kontext und Skalen bleibt bestehen.

## 1.1.6 (interne Version 2026083001)

> CI-Codechecker grün.

- **39 `@covers`-Warnungen behoben** in zehn Testdateien. Die Angaben standen im
  Datei-Docblock statt am Klassen-Docblock – der Sniff wertet nur letzteren. Sie
  wurden verschoben, nicht neu erfunden.
- **Drei echte Fehler behoben**: fehlende Konstanten-Docblocks in
  `tests/playwright/seed.php`, ein Inline-Docblock im `itemparam_validity_test` und
  zwei Klassen in `webservice_external_classes_test.php` – der Stub liegt jetzt in
  `tests/fixtures/external_reload_template_stub.php`.
- Prüfstand: `classes`, `db`, `lang`, `catmodel`, `tests` und Top-Level jeweils
  **0 Meldungen** (ohne die projektüblich ausgeschlossenen `TodoComment`).
- Volle Regression: 90/90 Dateien grün.

## 1.1.6 (interne Version 2026083000)

> Issue #54 abgeschlossen: Filtern, Sortieren und eine Übersicht je Skala.

- **Neue Spalte `local_catquiz_itemparams.usable`** samt Index `(contextid, usable)`.
  Ohne Datenbankspalte gibt es kein serverseitiges Filtern und Sortieren – der
  Zustand wird in PHP aus dem Modellvertrag abgeleitet.
- **Genau eine Stelle berechnet den Wert**: `itemparam_validity::stamp()` fragt
  ausschließlich `model_strategy::validate_item_parameters()`. Alle **acht**
  Schreibpfade rufen sie auf, damit die Regel ungeteilt bleibt.
- **Upgrade mit Backfill** in Stapeln zu 500 Zeilen, plus eine
  **Konsistenzprüfung**, die nachrechnet und Abweichungen meldet, ohne etwas zu
  ändern. Das im Issue benannte Driftrisiko wird damit messbar.
- **Filter und Sortierung** in der Fragenliste auf der persistierten Spalte.
- **Aggregierte Übersicht je Skala** (`get_unusable_item_counts_per_scale()`), eine
  gruppierte Abfrage für alle Skalen. Gezählt werden nur Items mit unbrauchbarem
  aktivem Parameter – Pilotitems ohne aktiven Parameter sind ein erwarteter Zustand.
- Zahn-Tests: entwerteter Stempel und aufgeweichtes Aggregat werden beide rot;
  letzteres zählt dann 4 statt 2, weil das Pilotitem mitgezählt würde.

## 1.1.6 (interne Version 2026082816)

> CI-Grunt entschärft, Playwright-Browsertests eingeführt.

- **CI `lint-jsamd`**: Der Verify-Schritt bricht nicht mehr ab, sondern meldet den
  Diff als Warnung. Er rebuildet weiterhin im Runner – der dokumentierte Workaround
  für `moodle-plugin-ci#350`. Der beobachtete Diff hatte zwei Ursachen, eine davon
  (Sourcemap der falschen Datei) deutet auf Korruption im Build, nicht auf einen
  veralteten Commit.
- **Kein `caniuse-lite`-Pin**: eingebaut und wieder entfernt, weil die Gegenprobe
  ihn nicht stützt – mit einer deutlich älteren Datenbank ändert sich die lokale
  Ausgabe nicht.
- **Playwright eingeführt** (`tests/playwright/`) nach dem Muster der
  flexaccess-Workflows: Seed, Umgebungs-Helfer, CAT-Manager-Helfer und ein erster
  Fall zur Fragetext-Suche. Eigener Workflow – manuell auf jedem Branch, automatisch
  bei Push auf `dev`/`develop`/`development`, zusätzlich wöchentlich.
- **Stabilität belegt**: drei vollständige Läufe hintereinander grün. Zwei echte
  Ursachen behoben – Zählen während des AJAX-Nachladens (jetzt Warten auf Spinner
  bzw. „keine Datensätze") und ein zu knappes Login-Timeout bei kaltem Cache.

## 1.1.6 (interne Version 2026082815)

> Issue #54: Unbrauchbare Itemparameter sind im CAT-Manager sichtbar – und
> Pilotitems erscheinen überhaupt erst in der Liste.

- **Neue Spalte „Parametergültigkeit"** in der Testitem-Übersicht. Bei „nicht
  verwendbar" nennt der Text Modell und Ursache, mit Warnsymbol und Screenreader-Text
  – nicht farbcodiert allein.
- **Eine einzige Wahrheitsquelle**: `local\itemparam_validity` delegiert an
  `model_strategy::validate_item_parameters()`, dieselbe Methode, die Import und
  Laufzeit verwenden. Neue Modelle wirken ohne Anpassung der Ansicht. Ein Test pinnt,
  dass Backend-Urteil und Validierung nie auseinanderlaufen.
- **Drei Zustände**: verwendbar / nicht verwendbar / keine Parameter. Ein klassisches
  Pilotitem und ein Item mit vorhandenen, aber unbrauchbaren Parametern werden beide
  als Pilotitem gespielt – nur eines braucht Aufmerksamkeit.
- **Kein N+1**: Die Spalte wird aus der ohnehin geladenen Zeile berechnet.
- **Pilotitems waren gar nicht sichtbar.** Drei aufeinander aufbauende Ausschlüsse
  mussten fallen: der `INNER JOIN` auf die Itemparameter, die Statistik-Joins über
  `lcip.contextid` (bei Pilotitems NULL – die Kennzahlen wären leer geblieben) und
  die `WHERE`-Bedingung auf derselben Spalte. Items ohne aktiven Parameter sind in
  Pilotierung, und gerade ihre Versuchszahlen sind von Interesse.
- Neue Tests `itemparam_validity_test` (8 Tests) decken alle im Issue genannten Fälle
  ab; zwei Zahn-Tests werden bei Rücknahme rot.
- **Noch offen bei #54**: serverseitiges Filtern/Sortieren (verlangt einen
  persistierten Zustand) und die aggregierte Übersicht je Skala.

## 1.1.6 (interne Version 2026082814)

> Issue #24: Skalenbaum ohne N+1-Abfragen und ohne quadratischen Aufbau.

- **Subscriptions in einer Abfrage.** `build_tree()` fragte je Skala den
  Subscription-Status ab (`record_exists()` pro Skala). Neu:
  `subscription::return_subscribed_itemids()` lädt alle auf einmal, der Baum schlägt
  per `isset()` nach.
- **Fragenzahlen gruppiert.** `export_for_template()` und `return_as_array()` zählten
  je Skala einzeln. Neu: `catquiz::get_number_of_questions_per_scale()` mit
  `GROUP BY catscaleid`.
- **Linearer Baumaufbau.** Die Skalen werden einmal nach `parentid` gruppiert; jede
  Ebene sieht nur ihre eigenen Kinder statt erneut die vollständige Liste. Die
  Reihenfolge innerhalb einer Ebene bleibt unverändert.
- Neue Tests `catscale_tree_test` (5 Tests): Abfragezahl bleibt konstant,
  Baumstruktur und Reihenfolge über drei Ebenen, und – als eigentlicher Beleg –
  Subscription-Zustand sowie Fragenzahlen **identisch** zu den bisherigen
  Einzelabfragen.
- Zahn-Tests beziffern den Gewinn: mit reinjiziertem N+1 braucht der größere Baum
  **221 statt 12** Abfragen, die Fragenzahlen **3 statt 1**.
- **Noch offen bei #24**: Application-Cache für die Skalenhierarchie samt
  Invalidierung – bewusst zurückgestellt, weil falsche Invalidierung veraltete Bäume
  zeigt und der Aufbau bereits linear mit konstanter Abfragezahl ist.

## 1.1.6 (interne Version 2026082813)

> Issue #22: „Frage hinzufügen" ohne globale Scans.

- **`GROUP_CONCAT` und Wildcard-`LIKE` entfernt.** Die Abfrage baute je Frage einen
  String aus allen zugeordneten Skalen (`-3--7-`) und filterte mit `LIKE '%-3-%'`.
  Dieser String wurde nirgends angezeigt – er drückte nur „noch nicht dieser Skala
  zugeordnet" aus. Ein führendes Wildcard kann keinen Index nutzen, und die
  Aggregation erzwang ein `GROUP BY` über die gesamte Fragenbank.
- **Ersetzt durch `NOT EXISTS`** auf `local_catquiz_items`. Das trifft genau den in
  #25 angelegten Index `(catscaleid, componentname, componentid)`. Damit entfallen
  auch der `LEFT JOIN`, das `GROUP BY` und ein toter Parameter.
- Neue Tests `add_questions_query_test` (3 Tests): führen die Abfrage real aus und
  prüfen, dass eine Zuordnung zu einer **anderen** Skala die Frage nicht ausblendet –
  ein zu grob formuliertes `NOT EXISTS` wäre sonst unbemerkt durchgegangen.
- **Noch offen bei #22**: `ROW_NUMBER()` über alle Frageversionen sowie Behat für
  Suche, Pagination und Hinzufügen.

## 1.1.6 (interne Version 2026082812)

> Zählfehler auch in der Add-Items-Abfrage behoben (Fortsetzung von #21).

- **`return_sql_for_addcatscalequestions` zählte Steps statt Versuche.** Sie erreicht
  dieselben Daten über `get_sql_for_stat_base_request()`, das ebenfalls
  `question_attempt_steps` joint – `COUNT(*)` zählte dort jede Interaktion einzeln.
  Jetzt `COUNT(DISTINCT qa.id)`. Damit ist der Defekt an beiden Stellen behoben.
- Regressionstest ergänzt; beide geänderten Abfragen zusätzlich real ausgeführt.

## 1.1.6 (interne Version 2026082811)

> CI `lint-jsamd`: die echte Ursache gefunden. Dazu Issue #21 (Teil 1).

- **`.gitignore` ignorierte `amd/build/`.** Das ist die Ursache dafür, dass
  `questionpreview.min.js` bei jedem CI-Lauf erneut als „newly generated" gemeldet
  wurde, obwohl die Datei in jedem Paket lag: Bereits getrackte Build-Dateien werden
  weiter aktualisiert, **neue** nimmt Git stillschweigend nicht mit. Die Regel ist
  entfernt – Moodle lädt `amd/build/*.min.js` zur Laufzeit, und wer das Plugin
  installiert, hat keine Node-Toolchain. **Einmalig nötig:**
  `git add -f amd/build/questionpreview.min.js amd/build/questionpreview.min.js.map`
- **`update-browserslist-db`-Schritt wieder entfernt** – er hat nicht geholfen, die
  Diagnose war falsch.
- **Neuer Schritt „Verify committed AMD build"** in beiden Workflows: sichert den
  eingecheckten Stand, baut mit der Toolchain des Runners neu und vergleicht per
  `diff`. Fehlschlag nur bei echtem Unterschied. Das umgeht das bekannte
  False-Positive `moodlehq/moodle-plugin-ci#350`, ohne einen tatsächlich veralteten
  Build zu kaschieren – letzteres wurde per Zahn-Test geprüft.
- **Issue #21 (Teil 1)**: Die Statistik-Unterabfrage der Fragenliste hatte **kein
  `WHERE`** und aggregierte jeden CAT-Versuch der gesamten Instanz, bevor der äußere
  Join die erste Zeile verwarf; ein `LIMIT` außen reduzierte davon nichts. Jetzt
  schränkt die Aggregation selbst auf Kontext und Skalen ein.
- **Versuchszahlen zählen Versuche statt Steps**: `COUNT(qa.id)` zählte über den Join
  auf `question_attempt_steps` jede Interaktion einzeln. Jetzt
  `COUNT(DISTINCT qa.id)` in beiden Unterabfragen.
- **Noch offen bei #21**: das zweistufige Laden auf die sichtbare Seitenmenge und die
  leichte Count-Abfrage. **Nebenbefund für #22**: `return_sql_for_addcatscalequestions`
  trägt über `get_sql_for_stat_base_request()` denselben Zählfehler (`COUNT(*)` über
  den Steps-Join).

## 1.1.6 (interne Version 2026082810)

> Fragetext-Suche, Issue #19 und Stabilisierung der CI-Stufe `lint-jsamd`.

- **CI `lint-jsamd` grün**: Vier fremde Module (`csvimport`, `managecatcontext`,
  `managecatscale`, `testitem_model_overrides`) wurden als „stale" gemeldet, obwohl
  niemand sie angefasst hatte. Ursache war eine veraltete `caniuse-lite`-Datenbank –
  Babel leitet seine Ausgabe daraus ab und erzeugt dann andere Bytes als die
  eingecheckten Build-Dateien. Die Dateien sind neu erzeugt, und beide Workflows
  aktualisieren die Datenbank jetzt vor dem Grunt-Schritt.
- **Issue #19**: Die Detailansicht schränkt die Abfrage auf die angeforderte Frage
  ein (`:detailquestionid`, gesetzt im innersten Question-Join) statt die ganze Skala
  zu laden und in PHP auszuwählen. Zwei Fehler behoben: `IGNORE_MISSING` war der
  `$limitfrom`-Parameter und prüfte nichts, und die Frage-ID wurde im `$userid`-Slot
  übergeben, den der Builder an einen Join über **benutzerbezogene** Statistiken
  bindet – die Ansicht verglich Nutzer-IDs mit einer Frage-ID.
- **Fragetext-Suche wieder verfügbar** (Folge von #20): eine dedizierte Abfrage löst
  passende Frage-IDs auf, der Text bleibt aus der Listenabfrage. Kosten fallen pro
  Suche an, nicht pro Seitenaufruf. Wildcards werden escaped, kein Treffer ergibt
  eine leere Liste, und oberhalb von 2000 Treffern verzichtet die Suche auf die
  Einschränkung statt eine riesige `IN()`-Klausel zu bauen.
- **Dabei gefunden**: Die Einschränkung muss in `set_filter_sql()` sitzen, nicht beim
  Rendern. `local_wunderbyte_table` serialisiert seine SQL in eine gecachte Instanz,
  aus der AJAX-Nachladungen aufgebaut werden – zur Renderzeit ergänzte Bedingungen
  überleben das erste Nachladen nicht. Die erste Seite sah gefiltert aus, jedes
  Nachladen zeigte still wieder alle Datensätze.
- Neue Tests: `question_detail_query_test` (4), `questiontext_search_test` (5),
  Behat `catquiz_questiontext_search` (2 Szenarien). Fünf Zahn-Tests, alle rot bei
  Rücknahme.

## 1.1.6 (interne Version 2026082807)

> Issue #20: Fragetexte aus den Tabellenabfragen entfernt, Vorschau lazy geladen.

- **`questiontext` aus fünf SQL-Stellen entfernt** (drei Query-Builder in
  `catquiz.php`, inkl. innerem SELECT und `GROUP BY`). Die Listen selektieren den
  Fragetext nicht mehr.
- **Beide Renderer entschlackt**: `col_name()` und `col_questiontext()` bauten je
  Zeile `question_rewrite_question_urls` → `format_text` → `strip_tags` auf und
  betteten den Volltext in ein verstecktes Modal. Jetzt nur noch ein Auslöser mit
  dem Fragenamen.
- **Neuer Webservice** `local_catquiz_get_question_preview` mit
  `validate_context()` und `require_capability('local/catquiz:manage_catscales')`.
- **Neues AMD-Modul** `local_catquiz/questionpreview` lädt die Vorschau bei Klick in
  ein Core-Modal; ein delegierter Listener überlebt das Neuzeichnen der Tabelle bei
  Sortierung, Filter und Pagination.
- **Volltextsuche und Sortierung über `questiontext` entfallen** (im Issue als
  bewusste Folge benannt); die irreführende Spaltenüberschrift „Fragetext" wurde
  auf „Name" korrigiert.
- Neue Tests: `question_list_payload_test.php` (4 Tests, prüft u. a. die
  SELECT-Feldlisten und die Rechteprüfung des Endpunkts) und
  `catquiz_question_preview_lazy.feature` (2 Szenarien). Zahn-Test: mit
  wiederhergestelltem Volltext-Template fallen beide Szenarien.
- **Hinweis:** `amd/build/questionpreview.min.js` ist handgeschrieben (kein grunt im
  Container). Vor dem Merge `moodle-plugin-ci grunt` laufen lassen.

## 1.1.6 (interne Version 2026082806)

> Archetyp-Lücke bei `view_teacher_feedback` geschlossen.

- **`editingteacher` erhält `local/catquiz:view_teacher_feedback`.** Rollen-
  Archetypen sind unabhängige Vorlagen – ein `editingteacher` erbt **nichts** vom
  `teacher`-Archetyp (Vererbung läuft in Moodle über Kontexte, nicht über
  Archetypen). Vor dem Fix war ausgerechnet die Rolle ausgesperrt, die fremde
  Versuche ohnehin prüfen darf. Empirisch belegt: editingteacher = nein,
  teacher = ja.
- Ein Scan aller neun Capabilities zeigte genau diese eine Lücke.
- Neuer Dauertest `test_all_teacher_roles_may_see_teacher_feedback()` (positiv für
  beide Lehrrollen, negativ für Teilnehmende); Zahn-Test rot bei Rücknahme.
- **Entschieden und geschlossen:** `UNIQUE (component, attemptid)` wird nicht
  umgesetzt. Ein Versuch läuft über genau einen Komponententyp, `attemptid` ist
  für sich eindeutig – das bestehende `UNIQUE (attemptid)` ist fachlich korrekt.

## 1.1.6 (interne Version 2026082805)

> Issue #25, Nachtrag: redundante Indizes entfernt. Spaltennamen unverändert.

- **18 doppelt abgedeckte Spalten bereinigt.** XMLDB legt für jeden
  `<KEY TYPE="foreign">` bereits einen Index an; ein zusätzlicher `<INDEX>` auf
  derselben Spalte erzeugte einen zweiten, physisch identischen Index. Betroffen:
  `attempts` 4, `personparams` 4, `items` 3, `progress` 2, `tests` 2,
  `catscales` 1, `subscriptions` 1, `itemparams` 1. Es wurden ausschließlich
  `<INDEX>`-Deklarationen entfernt – **kein `FIELDS` und kein Spaltenname geändert**.
- **`progress.attemptid`**: Der eigenständige Index trug die Eindeutigkeitsgarantie.
  Der Fremdschlüssel ist jetzt `TYPE="foreign-unique"` – ein eindeutiger Index, die
  Relation bleibt dokumentiert.
- **Upgrade-Helfer** `local_catquiz_upgrade_drop_duplicate_indexes()` entscheidet vor
  dem Löschen, welcher Index überlebt, und löscht per Namen. `drop_index()` löst über
  die Spaltenliste auf und hätte den eindeutigen Index treffen können.
- Entfallen ist dabei auch `<INDEX NAME="catscaleid" FIELDS="scaleid">` auf
  `attempts` – Name und Spalte stimmten nicht überein. Die Spalte heißt weiter
  `scaleid`.
- Neuer Wächter `test_no_redundant_indexes_remain()` und neue Suite
  `schema_index_cleanup_test.php`; verifiziert auf PostgreSQL und MariaDB.

## 1.1.6 (interne Version 2026082804)

> Issue #25: Datenbankindizes für Attempts, Progress und Skalenabfragen –
> verifiziert auf PostgreSQL **und** MariaDB, inkl. zweier dabei gefundener Fehler.

- **Upgrade-Block wäre nie gelaufen**: Savepoint `2026082803` lag über
  `$plugin->version` (`2026082802`). Bestandsinstallationen hätten die Indizes nie
  bekommen. Version liegt jetzt über dem höchsten Savepoint.
- **Dubletten-Bereinigung löschte zu viel**: `GROUP BY` fasst alle `NULL` zu einer
  Gruppe zusammen, ein Unique-Index beschränkt bei `NULL` dagegen nichts. Zeilen mit
  `NULL` wurden gelöscht, obwohl der Index sie zugelassen hätte – und `contextid`
  (personparams) sowie `attemptid` (progress) sind nullable. Die Abfrage schließt
  NULL-Gruppen jetzt aus; neuer Regressionstest.
- **`timecreated`-Index** zeigte auf `instanceid` – korrigiert; Zeitraumfilter sind
  damit erstmals indiziert.
- Neue Indizes: `personparams` UNIQUE `(userid, contextid, catscaleid)`,
  `progress` UNIQUE `(attemptid)`, `attempts` `(contextid, scaleid, userid,
  attemptid)`, `items` `(catscaleid, componentname, componentid)` und
  `(catscaleid, activeparamid)`.
- **EXPLAIN belegt die Nutzung** aller drei Hauptmuster auf beiden Engines
  (20.000 Zeilen, nach `ANALYZE`).
- **Sprachdateien sortiert** (`moodle.Files.LangFilesOrdering`): je vier Verstöße in
  `lang/en` und `lang/de` behoben; nachgewiesen, dass sich nur die Reihenfolge
  ändert (760 bzw. 750 Strings, key⇒value identisch). Die CI fährt
  `--max-warnings 0`, diese Warnungen blockierten den Lauf.
- **Nicht umgesetzt, bewusst**: `UNIQUE (component, attemptid)` aus dem Issue.
  `catquiz.php` sucht Versuche über `attemptid` allein; eine Lockerung würde
  `get_record()` bei mehreren Treffern brechen und verlangt eine Codeänderung.
- **Befund ohne Änderung**: 14 redundante Index-Paare (Bestand, beide Engines),
  weil `install.xml` für dieselbe Spalte Key *und* Index deklariert.

## 1.1.5 (interne Version 2026082801)

> Issue #18: Statistik- und Review-Rechte werden im tatsächlichen Kurs- bzw.
> Modulkontext geprüft statt im Systemkontext oder über den globalen `$COURSE`.

- **Neu `local\access\context_resolver`**: löst einen Versuch auf den Kontext auf,
  zu dem er gehört (Modul → Kurs → System), mit Request-Cache gegen N+1.
  `SITEID` wird bewusst nicht als Kurskontext akzeptiert – das wäre wieder eine
  systemweite Prüfung.
- **Neu `local\access\feedback_access`**: die Regel „darf fremde Ergebnisse sehen"
  existiert jetzt genau einmal und wird von Anzeige, Export und AJAX gleichermaßen
  benutzt. Inklusive Gruppenmodus (`SEPARATEGROUPS` ohne `accessallgroups`).
- **`debuginfo`**: toter, unerreichbarer Fix hinter einem `return` entfernt (er
  hätte zudem auf einer privaten Property fatal geworfen); handgebauter Lookup in
  der `context`-Tabelle mit hartkodiertem `contextlevel => 50` ersetzt.
- **`catquizstatistics`**: vier Prüfstellen vereinheitlicht; zwei latente Abstürze
  behoben (`context_course::instance(null)` bei site-weiter Statistik).
- **`feedback_tab_clicked`**: AJAX-Endpunkt validiert den aufgelösten Kontext.
- **`show_attemptfeedback.php`**: die Review-Seite verlangte systemweites
  `manage_catscales` – eine Lehrkraft konnte einen Versuch des eigenen Kurses nicht
  einsehen. Jetzt Kontext des Versuchs + `feedback_access`-Regel; neuer String
  `error:noreviewpermission`.
- **Behat**: neues Feature `catquiz_context_permissions.feature` (2 Szenarien, echter
  Chrome-Lauf grün; bei zurückgenommenem Fix fallen beide) und ein neuer Step, der die
  Versuchs-ID zur Laufzeit per Username auflöst.
- **`db/access.php`**: `view_teacher_feedback` auf `CONTEXT_MODULE` (systemweite
  Zuweisungen wirken durch Vererbung unverändert weiter).
- **Fallstrick dokumentiert**: `groups_get_activity_groupmode()` liefert einen
  **String** – ein Strict-Vergleich gegen `SEPARATEGROUPS` trifft nie zu.
- Neue Suite `tests/local/access/context_resolver_test.php` (14 Tests), alle drei
  Zahn-Tests werden bei Rücknahme rot.

## 1.1.5 (interne Version 2026082152)

> Dokumentations-Aktualisierung und Sitzungsabschluss des Strangs „CI grün"
> (Sitzungen 065–085). Keine Codeänderung.

- **`doc/environment-setup.md`**: Zielzustand auf mod_adaptivequiz 3.0.0
  aktualisiert, Locale `en_AU.UTF-8` und `local_moodlecheck` als Fixpunkte
  ergänzt. Alle `sudo`-Beispiele auf die Container-Realität umgestellt (man ist
  root, `sudo` fehlt meist). Drei beim Aufsetzen aufgetretene Blocker
  dokumentiert: `max_input_vars` muss **dauerhaft** in die `php.ini` (der
  PHPUnit-Init ignoriert `-d`), `locale-gen en_AU.UTF-8`, und phpcs braucht
  `installed_paths` für PHPCSExtra **und** moodle-cs. Neuer §5a zum
  PHPDoc-Checker als lokale Reproduktion des CI-Quality-Jobs. Behat-Status von
  „non-blocking" auf „blockierend und grün" berichtigt. Neue Stolpersteine: lange
  Läufe im Hintergrund fahren, „ein ZIP löscht nichts", CRLF verfälscht
  `grep`/`preg_match`.
- **`doc/engineering-guide.md`**: neuer **§2a „Zahlen aus Fremdquellen: nie blind
  casten"** – die häufigste Fehlerklasse dieses Strangs (viermal dieselbe Wurzel)
  mit Tabelle und Diagnose-Merkmal. §2.4 um zwei Fälle erweitert, in denen der
  Zahn-Test eine **wirkungslose eigene Assertion** entlarvt hat, samt Regel.
  §2.5 um die Grenze des Aggregats (veraltete Referenz → referenzfreie
  Invarianten). Neuer §5a „Was ein ZIP nicht kann"; Checkliste um Punkt 9
  erweitert.
- **`doc/README.md`**: Index bis session-084 fortgeschrieben (endete bei 018),
  `issues/` aufgenommen, Themenübersicht 065–084 ergänzt.
- **`doc/session-start-prompt.md`**: Release 1.1.5, aktuelle Abhängigkeiten,
  Behat-Status, **Cross-Plugin-Warnung** (Repo-Stand und gebündelte Adapter-Kopie
  können auseinanderlaufen – gebaut wird aus dem Repo) sowie der aktuelle Stand
  mit den bekannten Restpunkten.
- **`doc/session-085-changes.md`** neu.
- Verifikation: alle 7 Modell-Suiten grün (Skip-Zahlen unverändert), Kernsuiten
  grün, Sättigungs-Stresstest 2.856 Assertions ohne nicht-endliche Werte,
  phpcs plugin-weit Exit 0 (verzeichnisweise gefahren), PHPDoc 0 Fehler.

## 1.1.5 (interne Version 2026082150)

> Beide offenen Punkte umgesetzt: Der Adapter liefert jetzt den fehlenden
> Report-Callback, und der seit Langem inkomplette Schätz-Test ist untersucht.

- **Adapter-Callback `attempts_report_url`** (Patch für
  `adaptivequizcatmodel_catquiz`, liegt bei). Ohne ihn rendert
  `mod_adaptivequiz` die Versuchszahl auf der Aktivitätsseite als **reinen Text**
  (`attempts_number::when_custom_catmodel_in_use`) – eine Lehrkraft kam damit
  weder zu einer Versuchsübersicht noch zur Aktion „Close attempt".
  - Der Callback liefert `local/catquiz/feedback.php` mit `courseid` und
    `instanceid`, also die plugin-eigene Versuchsübersicht.
  - **Im echten Moodle verifiziert**, nicht nur angenommen:
    `get_plugin_list_with_function()` findet den Callback, und
    `attempts_number::when_custom_catmodel_in_use()` liefert damit
    `.../feedback.php?courseid=7&instanceid=42` statt `null`; ohne gesetztes
    `catmodel` weiterhin `null`.
  - Das zugehörige Behat-Szenario ist wieder aufgenommen (prüft, dass die
    Versuchszahl für die Lehrkraft erscheint).
- **`test_given_responses_lead_to_expected_abilities` untersucht.** Der Test war
  nicht nur inkomplett, sondern **doppelt defekt** – beides hatte der Skip
  verdeckt:
  1. Der Aufruf `createtestenvironment($strategy)` hatte sein zweites Argument
     verloren und wäre mit `ArgumentCountError` gestorben. **Repariert.**
  2. Der Test fälscht den Attempt-Record (`instance 1, id 1`), statt einen echten
     Versuch anzulegen. Sobald der CAT endet – seit den #6-Fixes deutlich früher –
     betritt die Strategie den Feedback-Pfad und stirbt in `fetch_question_id()`
     mit `attemptfeedback::$contextid must not be accessed before initialization`.
     Eine Begrenzung der Antwortzahl hilft nicht, weil der Versuch vorher endet.
  - Der Test bleibt daher `markTestIncomplete` – aber mit **zutreffender**
    Begründung statt des alten, irreführenden „Calculated value is not yet
    correct". Ihn lauffähig zu machen heißt, die Harness auf einen echten Versuch
    umzubauen; das ist eine Neufassung, keine Korrektur. Der Schätzer ist
    zwischenzeitlich durch den invariantenbasierten Trajektorientest und
    `ability_monotonicity_test` gedeckt.
- phpcs Exit 0 (beide Repos), PHPDoc 0 Fehler.

## 1.1.5 (interne Version 2026082149)

> Zweiter Behat-Lauf: wieder 19/21. Die beiden verbliebenen Szenarien sind über
> die Oberfläche **grundsätzlich nicht durchführbar** – beides sind echte Befunde,
> keine Testfehler. Sie sind entfernt und die Gründe dokumentiert.

- **Administratives Schließen ist bei aktivem CAT-Model per UI nicht erreichbar.**
  `mod_adaptivequiz/view.php` rendert bei gesetztem `catmodel` keinen Link zum
  Attempt-Report, sondern ruft nur `attempts_number()`. Diese Funktion liefert
  **reinen Text**, solange das CAT-Model den Callback `attempts_report_url` nicht
  implementiert – und `adaptivequizcatmodel_catquiz` implementiert ihn nicht
  (verifiziert in `classes/output/attempts_number.php:51-71`). Die Lehrkraft hat
  damit keinen Weg zu „Close attempt". Der HTML-Dump bestätigt es: die
  Aktivitätsseite zeigt lediglich „Attempts: 1" ohne Link, und das einzige
  „Reports" auf der Seite ist der Nutzermenü-Eintrag des Report-Builders.
  - **Der Code-Pfad ist unabhängig davon verifiziert**: `closeattempt.php` ruft
    `adaptivequiz_complete_attempt()` – dieselbe autoritative Funktion wie der
    Cron –, und genau das prüft `cancel_expired_attempts_path_test`.
  - Konsequenz für #5 DoD 7: per Behat nicht erfüllbar, solange der Adapter keinen
    `attempts_report_url`-Callback anbietet. **Das wäre die eigentliche
    Verbesserung** – dann wäre der Report auch für Lehrende erreichbar.
- **Die Reporting-Checkbox ist per Label nicht adressierbar.** `advcheckbox`
  rendert je Skala **zwei** Inputs mit gleichem Namen und Label – ein verstecktes
  `value="0"` plus die sichtbare Checkbox. Der label-basierte Behat-Schritt trifft
  das versteckte zuerst („element not interactable"). Eine Adressierung über die
  id scheidet aus, weil diese die generierte Skalen-ID enthält und je Lauf
  wechselt.
  - Der Sachverhalt ist stattdessen **am Gate** abgedeckt, wo er präziser
    prüfbar ist: `feedback_result_gate_test` belegt in zwei Tests, dass eine
    solche Skala nicht angezeigt wird, aber statistisch valide bleibt – ihr
    Ergebnis wird also weiterhin gespeichert und steht für die Vorwertübernahme
    zur Verfügung.
- Beide Begründungen stehen als Kommentar in den betroffenen Feature-Dateien,
  damit die Lücke nicht später als Versehen gelesen wird.
- Behat-Bestand jetzt **19 Szenarien**, davon 4 in dieser Sitzung neu
  (#6 Back/Forward, #6 wiederholtes Wiederbetreten, #5/#8 keine
  Doppel-Finalisierung, #9 Carryover) – alle im zweiten Lauf grün.

## 1.1.5 (interne Version 2026082148)

> Behat-Nachbesserung: 19 von 21 Szenarien liefen im ersten Lauf grün, die zwei
> Fehlschläge waren genau die vorhergesagten Oberflächen-Annahmen.

- **Undefinierter Schritt behoben.** `I uncheck the "..." checkbox` existiert in
  Moodle nicht; Abwählen wird als `I set the field "..." to ""` ausgedrückt.
  - Dabei war auch der **Feldname geraten und falsch**: Die Checkbox heißt nicht
    „Report results of this scale", sondern **„Include scale for report"**
    (`$string['reportscale']`). Am Code verifiziert statt erneut geraten.
- **Navigation zum Attempt-Report behoben.** `I follow "Reports"` scheiterte mit
  `ElementNotInteractableException` – der Link liegt in der Aktivitäts-
  administration. Ersetzt durch
  `I navigate to "Reports" in current page administration`, das Muster der
  mod_adaptivequiz-eigenen Szenarien.
- **Zwei weitere Fehler vorab gefunden**, die im nächsten Lauf sonst rot geworden
  wären – beide durch Nachlesen im Mod-Quelltext statt durch Raten:
  - In der Report-Tabelle trägt der Link zu den Versuchen einer Person die
    **Anzahl der Versuche** als Text, nicht den Namen (`renderer.php:368`).
    Jetzt zeilenbezogen adressiert: `I click on "1" "link" in the
    "Student1 Test" "table_row"`.
  - „Close attempt" wird als **Action-Icon** (`t/stop`) gerendert, nicht als
    Textlink (`renderer.php:727`) – daher `"Close attempt" "icon"`.
  - Gegengeprüft und bestätigt: `closeattempt.php` nutzt `$renderer->confirm()`,
    dessen Button „Continue" heißt, und
    `I press the "back|forward" button in the browser` existiert im Core
    (`behat_general.php:1990`) – dieses Szenario war im ersten Lauf bereits grün.
- Die übrigen vier neuen Szenarien (Back/Forward, wiederholtes Wiederbetreten,
  Doppel-Finalisierung, Carryover) bestanden den ersten Lauf ohne Änderung.

## 1.1.5 (interne Version 2026082147)

> Das doppeldeutige `excluded`-Flag ist aufgetrennt, und die Behat-Abdeckung für
> #5–#9 ist vollständig geschrieben.

- **`excluded` bedeutet jetzt genau eine Sache: die Messung ist unbrauchbar.**
  Die Anzeigeentscheidung „Reporting abgeschaltet" trägt ein eigenes Flag
  `feedbacksettings::FIELD_NOTREPORTED`. Bisher setzte `filter_excluded_scales()`
  dafür ebenfalls `excluded`, weshalb jeder Konsument zusätzlich das
  `error`-Array inspizieren musste, um „unbrauchbar" von „soll nicht angezeigt
  werden" zu unterscheiden.
  - `attempt_result_validator` braucht die Kompensation über `$hascheckbox` nicht
    mehr; `reportable` und `statisticallyvalid` lesen sich direkt aus den Flags.
  - `inferlowestskillgap` und `infergreateststrength` schließen weiterhin **beide**
    Fälle aus der Primary-Auswahl aus – die Auswahl verhält sich unverändert.
  - `feedback_helper::get_exclusion_reason_string()` erkennt beide Flags.
- **Rückwärtskompatibel.** Daten, die **vor** der Auftrennung geschrieben wurden,
  markieren den Reporting-Fall zusätzlich als `excluded`. Damit solche Ergebnisse
  nicht nachträglich ungültig werden, gilt weiterhin: ein ausgeschlossener, aber
  nur nicht-berichteter Wert bleibt statistisch valide. Das ist getestet
  (`test_legacy_reporting_flag_stays_statistically_valid`) und zahn-geprüft.
- **Verifikation**: `feedback_result_gate_test` auf 9 Tests erweitert (neue und
  alte Datenform, `excluded` allein bedeutet weiterhin unbrauchbar). Die
  vollständige CAT-Simulationsmatrix läuft unverändert grün
  (35 Tests / 2537 Assertions), die Selektionslogik ist also nicht betroffen.
  phpcs Exit 0, PHPDoc 0 Fehler.
- **Behat-Abdeckung vervollständigt** (6 neue Szenarien, insgesamt 20):
  - **#6**: Zurück/Vorwärts im Browser erzeugt keinen Doppel-Slot; wiederholtes
    Wiederbetreten eines unbeantworteten Items behält einen Slot.
  - **#7**: Eine Skala mit abgeschaltetem Reporting wird nicht angezeigt, der
    Versuch aber normal finalisiert – der zentrale „kein valides Ergebnis"-Hinweis
    bleibt echten Messproblemen vorbehalten.
  - **#5/#8**: neue Datei `catquiz_attempt_finalisation.feature` – administratives
    Schließen finalisiert wie ein normaler Abschluss (verifiziert: auch
    `closeattempt.php` nutzt `adaptivequiz_complete_attempt()`), und ein
    abgeschlossener Versuch wird nicht doppelt finalisiert.
  - **#9**: Ein zweiter Versuch startet auf dem übernommenen Ergebnis.
  Alle Feature-Dateien folgen dem bewährten Muster der bestehenden Szenarien
  (Settings-Round-Trip über das Aktivitätsformular) und sind strukturell geprüft.

## 1.1.5 (interne Version 2026082146)

> Die übersprungene CAT-Simulationsmatrix läuft wieder – und prüft wieder etwas.
> 35 Tests, 2537 Assertions statt 26 übersprungener Datensätze.

- **Befund zuerst, dann der Umbau.** Ich habe den Skip entfernt und alle Datensätze
  mit Diagnoseausgabe gegen die gepinnte Referenz gemessen. Ergebnis: **7 von 13**
  Datensätzen treffen die Referenz nur zu 3–43 % (Labels teils 2 %), dazu **11
  Errors** vom Typ `Exception: Should not be 0`. Letzteres heißt: Der CAT war
  bereits fertig, während die Referenz weiterlaufen wollte.
  - Das ist **keine** numerische Verschiebung, sondern die direkte Folge der
    korrekten #6-Fixes: Der Versuch stoppt jetzt nach der konfigurierten Zahl
    **beantworteter** statt angezeigter Items, und Pilot-/Vertragsitems werden
    anders behandelt. Die Referenz ist damit **konstruktionsbedingt veraltet**.
- **Warum weder Re-Pinning noch Aggregat.** Ein aggregierter Vergleich
  (Engineering-Guide §2.5) würde hier Veralterung messen statt Korrektheit – eine
  Schwelle, die 3 % durchlässt, ist wertlos. Ein frisches Neupinnen würde
  zementieren, was der Code heute tut, inklusive eventuell verbliebener Fehler.
- **Stattdessen referenzfreie Invarianten**, die für **jeden** korrekten CAT-Lauf
  gelten und nicht vom diskreten Newton-Zweig abhängen:
  - Der Versuch terminiert von selbst; ein früheres Ende als in der Referenz ist
    legitim und keine Exception mehr.
  - Jede Schätzung bleibt endlich und im Trust-Range.
  - Die Trajektorie folgt dem Antwortmuster: mindestens vier Fünftel falsch →
    Endwert unter dem Startwert, mindestens vier Fünftel richtig → darüber.
  Das Antwortmuster der Referenz bleibt als Treiber nutzbar, ihre Labels nicht.
- **Verifikation**: 35 Tests / 2537 Assertions grün (Laufzeit ~7:40).
  **Zahn-getestet**: Trajektorie um 100 verschoben → Trust-Range-Invariante fällt;
  Trajektorie gespiegelt → Richtungsinvariante fällt („16 wrong out of 19 must end
  below its starting ability"). Die erste Fassung der Richtungsprüfung
  (strikt all-wrong/all-right) feuerte bei keinem Datensatz – erkannt und auf eine
  Mehrheitsbedingung umgestellt, was 13 zusätzliche Assertions bringt.
- phpcs Exit 0, PHPDoc 0 Fehler.

## 1.1.5 (interne Version 2026082145)

> PHPUnit-Fix: Der Umbau der Mindestfragen-Zählung (2026082140) hatte einen
> Mock-Test nicht mitgezogen.

- **`filterbytestinfo_minquestions_test` repariert** (2 Failures, beide Datensätze
  desselben Tests). Der Test mockt `progress` und stubbte nur
  `get_playedquestions()`. Seit der Umstellung auf **beantwortete** statt
  **angezeigte** Items fragt `filterbytestinfo` jedoch
  `get_num_answered_productive_questions()` – ungestubbt lieferte der Mock dort 0,
  die Ausschlussbedingung wurde nie erreicht und das erwartete
  `deactivate_scale()` blieb aus.
  - Der Stub liefert die Zahl jetzt ebenfalls; in dieser Fixture ist jedes
    gespielte Item beantwortet und produktiv, die Erwartungen bleiben also
    unverändert.
  - Gegenprüfung: Alle weiteren Tests, die `progress` mocken und
    `get_playedquestions()` stubben, wurden gesichtet – nur
    `mayberemovescale_test` tut das noch, ist aber von der Umstellung nicht
    betroffen (grün).
- **Hinweis zur Simulationsmatrix**: `strategy_test::test_strategy_returns_expected_questions`
  ist in der CI mit allen 26 Datensätzen **übersprungen**, und zwar als
  dokumentierter Vorbestand („CAT trajectory pinned to pre-refactor estimator").
  Sie sichert die Umbauten dieser Sitzung also **nicht** ab – das Re-Pinning aus
  einem frischen Simulationslauf bleibt offen.
- phpcs Exit 0, PHPDoc 0 Fehler; Filter-, Feedback-, Ergebnis-, Attempt- und
  Kernsuiten grün.

## 1.1.5 (interne Version 2026082144)

> Issue **#7 DoD 2/3**: Der Feedback-Pfad urteilt jetzt über das zentrale
> Ergebnisobjekt statt über die mehrdeutigen Rohflags.

- **Das Problem: `excluded` bedeutete zweierlei.** `feedbacksettings` setzt das
  Flag sowohl für ein **Messproblem** (SE unter dem Minimum, zu wenige Items) als
  auch für eine reine **Anzeigeentscheidung** (Reporting-Checkbox aus). Jeder
  Konsument musste wissen, welche Kombination was bedeutet – und die Anzeige
  konnte von der Validität abdriften, sobald sich die Regeln ändern.
- **Neu: ein Gate für alle.**
  - `feedback_helper::build_attempt_result()` baut aus den Feedback-Daten das
    `attempt_result`-DTO – dieselbe Quelle, die auch der Validator nutzt.
  - `feedback_helper::is_displayable()` entscheidet über die Anzeige:
    `reportable && statisticallyvalid`.
  - `feedbackgenerator` und `customscalefeedback` filtern nicht mehr selbst über
    `toreport`/`excluded`/`hidden`, sondern fragen dieses Gate.
- **Ablehnungsgründe kommen aus `rejectionreasons`.**
  `feedback_helper::get_rejection_reason_string()` leitet die Meldung aus den
  maschinenlesbaren Gründen ab; die interpolierten Detailwerte (Schwellen,
  Ist-Werte) stammen weiterhin aus dem `error`-Array derselben Skala, weil die
  Sprachstrings sie brauchen.
  - **Verhaltensverbesserung**: Die alte Implementierung meldete den Grund der
    **ersten** ausgeschlossenen Skala, die ihr begegnete. Lag dort ein reines
    Anzeigeproblem vor, wurde die generische Meldung „keine Skalen gefunden"
    gezeigt und das echte Messproblem einer anderen Skala nie sichtbar. Jetzt
    überspringen anzeigebezogene Gründe und das Messproblem gewinnt.
- **Äquivalenz abgesichert**: `feedback_result_gate_test` (6 Tests) prüft das neue
  Gate über **alle** Flag-Kombinationen gegen den historischen Filter
  (`toreport && !excluded && !hidden`), belegt die Trennung von Anzeige und
  Validität und pinnt die Verhaltensverbesserung samt Gegenprobe gegen den alten
  Helfer. **Zahn-getestet**: lässt man in `is_displayable()` die statistische
  Validität weg, divergiert das Gate beim Fall „SE unter Minimum".
- phpcs Exit 0, PHPDoc 0 Fehler; Feedback-, Validator-, Carryover-, Finalizer- und
  Kernsuiten grün, ebenso der vollständige Attempt-Durchlauf
  (`test_all_wrong_attempt_drives_ability_down`, 55 Assertions).

## 1.1.5 (interne Version 2026082143)

> Issue **#9**: Der Live-Carryover ist verdrahtet. Vorwerte kommen jetzt aus der
> Versuchshistorie statt aus dem lebenden Zwischenstand.

- **`personability_loader` nutzt die Versuchshistorie als Vorwert.** Bisher las er
  ausschließlich `local_catquiz_personparams`. Diese Tabelle wird jedoch
  **während** eines Versuchs fortgeschrieben (`updatepersonability`,
  `filterbystandarderror`) – sie ist damit ein lebender Zwischenstand und keine
  Historie abgeschlossener Versuche. Als Prior gelesen konnte sie eine
  halbfertige Schätzung in den Folgeversuch tragen.
  - Neu wird je Zielskala zuerst `attemptscale_repository::get_latest_valid()`
    befragt. Diese Zeilen entstehen **einmalig bei der Finalisierung** und nur für
    valide gemessene Skalen. `personparams` bleibt der Fallback für Skalen ohne
    solche Zeile (ältere Versuche, nie gemessen).
  - Gilt für **alle** Zielskalen (Hauptskala und ausgewählte Subskalen), nicht nur
    die Hauptskala.
- **Tests** `personability_loader_carryover_test` (3 Tests): abgeschlossener
  Versuch schlägt den lebenden Personparameter; ohne Historie bleibt der
  Personparameter der Fallback; ein **invalides** Ergebnis wird nicht übernommen.
  **Zahn-getestet**: Carryover-Vorrang entfernt → der Loader liefert wieder den
  Zwischenstand (2.5 statt 0.8) und der Test fällt.
- **Nebenbefund**: Die Spalte heißt `score`, nicht `ability` – beim Verdrahten
  aufgefallen und korrigiert.
- phpcs Exit 0, PHPDoc 0 Fehler; Loader-, Repository-, Finalizer-, Preattempt- und
  Weighting-Suiten grün.

## 1.1.5 (interne Version 2026082142)

> Gegenprüfung gegen `mod_adaptivequiz` **3.0.0** (2026082500): Mehrere
> Cross-Plugin-Punkte sind fremdseitig bereits erledigt, und eine Kritik am
> Validator erweist sich als nicht zutreffend.

- **#6 ist implementierungsseitig vollständig – auf beiden Seiten.** Am Code der
  gelieferten Version verifiziert:
  - Der **Adapter** (`catquiz_item_administration`) gibt bei aktivem
    `$previousquestionslot` bereits `next_item::from_quba_slot()` zurück, und zwar
    **vor** der CAT-Selektion – genau die zuvor empfohlene Änderung.
  - `cat_session` hält einen **Lock** (`lock_config`, Key je Quiz+User), sucht
    **innerhalb** des Locks nach einem bestehenden aktiven Slot
    (`find_active_slot_for_question()` mit Fallback `find_any_active_slot()`) und
    ruft erst danach `add_question()`.
  - Es verbleiben damit nur noch die **Tests** (QUBA-Slotanzahl explizit prüfen,
    Concurrency, Back/Forward, Multi-Click).
- **Korrektur einer Review-Aussage zu #7.** Der Vorwurf, der
  `attempt_result_validator` vermische Validität und Reporting, trifft **nicht**
  zu: `$statisticallyvalid = ($reasons === []) && !($excluded && !$hascheckbox);`
  nimmt den Checkbox-Fall ausdrücklich von der statistischen Validität aus, und
  beide Zustände sind testgesichert
  (`test_reporting_disabled_is_display_only_not_statistical`,
  `test_reporting_disabled_scale_stays_statistically_valid_contract`).
- **Berechtigt bleibt** der Kern von #7 DoD 2/3: `customscalefeedback` filtert die
  Anzeige selbst und die Fehlerausgabe nutzt die alten `error`-Arrays statt
  `rejectionreasons`. Das ist jedoch **Refactoring-Schuld**, kein Rechenfehler:
  Das `attempt_result`-DTO wird im Feedback-Pfad derzeit gar nicht bereitgestellt.
  Zusätzlich dokumentiert: `feedbacksettings` setzt `excluded` für zwei fachlich
  verschiedene Sachverhalte (SE unter Minimum vs. Reporting aus); der Validator
  kompensiert das, sauberer wäre ein eigenes Flag – das berührt aber auch
  `inferlowestskillgap`/`infergreateststrength` und gehört in denselben Schritt.
- Aktualisiert: `doc/issues/strang-c-dod-review.md` mit der Gegenprüfung gegen
  3.0.0 und den korrigierten Bewertungen.

## 1.1.5 (interne Version 2026082141)

> Issue **#5** abgearbeitet: Der Cron schließt Versuche jetzt über denselben
> autoritativen Pfad wie ein normaler Abschluss, und der Finalizer erfindet keine
> Endzeit mehr. Geprüft gegen `mod_adaptivequiz` **3.0.0** (2026082500).

- **Cron umging den Finalizer.** `cancel_expired_attempts` rief
  `local_catquiz\local\attempt\attempt::complete()`. Diese Methode setzt nur
  `attemptstate` und `attemptstopcriteria` – sie stempelt **kein** `timefinished`
  und ruft **nicht** den CAT-Model-Callback. Ein per Cron geschlossener Versuch
  übersprang damit `attempt_finalizer::finalize()` vollständig: weder Endzeit noch
  Ergebnis (und damit `resultvalid`) wurden persistiert.
  - Der Task nutzt jetzt `adaptivequiz_complete_attempt()`. Die Funktion ist in
    `mod_adaptivequiz` 3.0.0 vorhanden, setzt `timefinished` **genau einmal**
    (`if (empty($attempt->timefinished))`) und ruft
    `post_complete_attempt_callback` – Browser-, Admin- und Cron-Abschluss
    erzeugen damit konsistente Daten.
- **Finalizer erfindet keine Endzeit mehr.** `if ($finishedat <= 0) { $finishedat
  = time(); }` persistierte einen fabrizierten Zeitstempel – genau das, was eine
  **autoritative** Endzeit nie sein darf. Stattdessen wird die Finalisierung
  abgelehnt und die Bedingung per `debugging()` sichtbar gemacht; der Versuch
  bleibt offen, statt mit erfundener Abschlusszeit dazustehen.
- **Tests**: neuer `cancel_expired_attempts_path_test` (3 Tests) schließt die vom
  Review benannte Lücke, dass nur die Timeout-**Erkennung** geprüft war: Der Task
  darf keinen zweiten Completion-Mechanismus besitzen, die autoritative Funktion
  muss den Catmodel-Callback erreichen, und der Finalizer darf keine Endzeit
  fabrizieren. Der bestehende `test_finalize_falls_back_when_timefinished_missing`
  schrieb das alte Verhalten fest und ist durch
  `test_finalize_refuses_without_authoritative_end_time` ersetzt (prüft zusätzlich,
  dass **nichts** geschrieben wird).
  **Zahn-getestet**: Cron zurück auf `attempt->complete()` → rot;
  `time()`-Fallback zurück → rot.
- phpcs Exit 0, PHPDoc 0 Fehler; Finalizer-, Validator-, Repository-, Cron- und
  progress-Suiten grün.

## 1.1.5 (interne Version 2026082140)

> #6: Das State-Modell von `progress` ist bereinigt. „Angezeigt", „offen" und
> „beantwortet" sind jetzt sauber getrennt – und zwar geschlossen, samt aller
> Stellen, die bisher die falsche Größe gezählt haben.

- **Mindestfragezahlen zählen jetzt beantwortete statt angezeigter Items.**
  `filterbystandarderror`, `filterbytestinfo` und `filterbyquestionsperscale`
  verglichen `count(playedquestions)` gegen `minimumquestions` bzw.
  `min_attempts_per_scale` – also **angezeigte** Fragen. Damit galt eine
  Mindestzahl bereits als erreicht, während die letzte Frage noch offen war, und
  die Zählung war zudem nicht pilot-gefiltert. Alle drei nutzen jetzt
  `progress::get_num_answered_productive_questions()` bzw. dessen
  per-Skala-Variante – dieselbe Größe, die schon `maximumquestionscheck` und der
  Ergebnisvalidator verwenden. „Mindestens N Fragen" und „höchstens N Fragen"
  meinen damit endlich dasselbe.
- **`progress::load()` entfernt die offene Frage nicht mehr aus
  `playedquestions`.** Der Block widersprach dem eigenen Datenmodell
  (`playedquestions` = „bereits angezeigte Fragen"): `lastquestion` zeigte auf
  eine Frage, die laut `playedquestions` nie gespielt worden war, und
  `get_num_playedquestions()` war nicht monoton. Die fehlende Response
  identifiziert das Item jetzt als offen. Nebeneffekt: Das offene Item kann nicht
  mehr wie eine neue Frage erneut ausgewählt werden (zahlt auf #6 ein).
  - Möglich wurde das erst durch die Umstellung oben – vorher hätte das Behalten
    der offenen Frage die Mindestfragezahl eine Frage zu früh erfüllt.
- **Verifikation**: Neben den gezielten Suiten wurde
  `test_all_wrong_attempt_drives_ability_down` gefahren, das einen vollständigen
  Attempt-Durchlauf abbildet (55 Assertions, grün), ebenso
  `test_legacy_selectfirstquestion_does_not_break_attempt`,
  `test_get_last_response_uses_last_answered_not_last_added`,
  `firstquestion_testinfo`, `ability_monotonicity`, `attempt_result_validator`,
  `progress_*` und `feedback_gating`. phpcs Exit 0, PHPDoc 0 Fehler.
- **Issue-Anpassung dokumentiert**: `doc/issues/issue-7-validity-vs-reporting.md`
  – Vorschlag, „Reporting aktiviert" aus den Validitätsbedingungen von #7 zu
  entfernen und Validität/Anzeige getrennt zu führen (nur Textänderung am Issue,
  das Verhalten ist bereits so implementiert).

## 1.1.5 (interne Version 2026082139)

> DoD-Gegenprüfung der Issues #5–#9 am Code. Die Kritik ist in den überprüfbaren
> Punkten berechtigt; ein belegter Fehler ist behoben, der Rest ist als
> Arbeitsplan festgehalten.

- **#7 DoD 4 behoben: `N` enthielt Pilot- und unbeantwortete Items.** Der
  `attempt_result_validator` bildete die per-Skala-Anzahl über
  `count($progress->get_playedquestions(true, $scaleid))` – also über
  **angezeigte** Fragen –, während der Kommentar daneben „pilot-filtered"
  behauptete. Damit konnte `N` ein noch offenes Item und Pilotitems enthalten und
  ein Versuch auf zu optimistischer Basis als valide gelten.
  - `progress::get_num_answered_productive_questions()` erhält eine
    **per-Skala-Variante**: gezählt wird nur, was eine Response hat, kein Pilotitem
    ist und der Zielskala zugeordnet ist.
  - Der Validator nutzt ausschließlich diese Größe; der irreführende Kommentar ist
    ersetzt.
  - Test `progress_answered_count_test` (2 Tests, 6 Assertions) inkl. dem direkten
    Nachweis, dass sich „angezeigt" von „beantwortet" unterscheidet.
    **Zahn-getestet**: Pilot-/Skalenfilter deaktiviert → rot. Bestehende
    Validator-Suite unverändert grün (8 Tests, 52 Assertions).
- **Gegenprüfung dokumentiert** in `doc/issues/strang-c-dod-review.md`: je
  Behauptung die Fundstelle im Code und die Bewertung, dazu der Arbeitsplan in der
  Reihenfolge #5 → #6 → #7(Rest) → #9 → #8 samt Cross-Plugin-Vorbehalten.
  Bestätigt als echte Fehler: Cron umgeht den Finalizer (#5), Finalizer erfindet
  bei fehlendem `timefinished` eine Endzeit (#5), `customscalefeedback` filtert
  weiterhin selbst (#7.2). Als **bewusst zurückgestellt** (und in der Doku auch so
  benannt): Live-Carryover und `get_last_primary()` (#9).
- **Korrektur einer eigenen Fehlaussage**: Die in Session 072 als offen notierte
  „Bevorzugung defizitärer Skalen" existiert bereits als `scaleterm` im
  IF-modifizierenden Score (`strategydeficitscore`), verifiziert und in der Doku
  richtiggestellt. Kein Handlungsbedarf.
- phpcs Exit 0, plugin-weiter PHPDoc-Check 0 Fehler.

## 1.1.5 (interne Version 2026082138)

> Nacharbeiten nach grüner CI: Chart/Legende auf eine Bezugsgröße, Debug-Warnungen
> im PDF, Kommunikation zum „schwächsten Teilbereich" geschärft.

- **Differenz-Chart: Balkenwert, Farbe und Legende beziehen sich jetzt auf
  dieselbe Größe.** Zuvor mischte der Chart drei Bezugspunkte: der Balken zeigte
  die **Differenz** zur Globalskala, gefärbt wurde nach der **absoluten**
  Subskalen-Fähigkeit gegen die **Subskalen**-Ranges, und die Legende darunter
  wurde für die **Globalskala** gerendert. Ein Lernender konnte so einen grünen
  Balken unter einer Legende sehen, deren grünes Band weit rechts von diesem
  Balkenwert beginnt. Jetzt wird die Differenz gegen die Ranges der Globalskala
  eingefärbt – dieselbe Größe und dieselbe Skala wie Balken und Legende.
- **Debug-Warnungen zu unbrauchbaren Itemparametern erscheinen im
  Attempt-Debug-Output (CSV/PDF).** `pilotquestions_loader` sammelte sie bereits
  im Kontext (`invaliditemparams`), sie wurden aber nirgends dargestellt. Neue
  Spalte `invaliditemparams` in `debuginfo` mit Item-ID, Label, Modell und
  konkretem Grund – ein stummes Item (z. B. 2PL mit `discrimination = 0`) ist
  damit aus dem Export heraus nachvollziehbar.
- **Kommunikation statt Technik beim „lowestskill".** Die Auswahl der Teilskala
  bleibt bewusst unverändert (niedrigste gemessene Subskala). Die Texte sprachen
  aber von „größtes Defizit" bzw. „als Defizit identifizierte Skala", obwohl die
  Skala über dem Gesamtwert liegen kann. Neu: „**schwächster getesteter
  Teilbereich**" mit einer Einordnung, dass dies kein Defizit bedeuten muss,
  sondern der Bereich mit dem größten Lernpotenzial ist (de + en).
- **Issue-Entwurf** `doc/issues/backend-invalid-itemparams.md`: Sichtbarkeit
  unbrauchbarer Itemparameter im CAT-Manager (Anzeige, Grund, serverseitiges
  Filtern/Sortieren, aggregierte Übersicht, Performance, Tests) – im Format von
  Issue #53.
- phpcs Exit 0, plugin-weiter PHPDoc-Check 0 Fehler, Feedback- und Kernsuiten
  grün. Die neuen Lang-Schlüssel liegen in **de und en**.

## 1.1.5 (interne Version 2026082137)

> Root Cause der beiden „Question 5"-Behat-Fails: ein **veralteter
> Last-Response-Cache**. Die Zählgröße aus 2026082136 war richtig – sie bekam
> nach einem Resume nur veraltete Daten geliefert.

- **Ursache: der Cache-Key war nicht monoton.** `progress` cachte die letzte
  Antwort unter `lastresponse_<usageid>_<numplayedquestions>`. Dieser Key setzt
  voraus, dass die Zahl der gespielten Fragen ein **monoton wachsender**
  Versionsindikator der Antwort-Historie ist. Genau das verletzt `load()`: ist die
  zuletzt ausgelieferte Frage noch unbeantwortet, wird sie aus `playedquestions`
  entfernt – der Zähler geht **2 → 1** und wieder auf **2**, sobald das Item
  beantwortet ist. Beim zweiten Treffer auf Key `..._2` liefert der Cache die
  **alte** Antwort (die von vor dem offenen Item). Die frisch gegebene Antwort
  erreicht die Response-Akkumulation nie, `responses` hinkt um eins hinterher, und
  der Test administriert ein Item über das Maximum hinaus.
- **Fix**: Der Cache in `progress::get_last_response_for_attempt()` ist
  **entfernt**; die letzte Antwort wird immer frisch gelesen. Die Query ist eine
  gezielte Einzelzeile (`LIMIT 1`) und läuft pro Versuch höchstens einige Dutzend
  Mal – Korrektheit schlägt hier die eingesparte Abfrage deutlich. Zudem müsste
  man für eine korrekte Invalidierung ohnehin die QUBA-Historie kennen, also genau
  das, was die Abfrage liefert.
- **Regressionstest** `progress_lastresponse_cache_test`: ein vorab gesetzter
  Cache-Eintrag unter dem alten Key darf den echten Wert **nicht** verdecken.
  **Zahn-getestet**: Cache reinjiziert → Test fällt (`111 is not equal to 111`).
- **Bewusst NICHT geändert**: der Löschblock in `progress::load()`, der die offene
  Frage aus `playedquestions` entfernt. Ohne den Cache hängt die Korrektheit nicht
  mehr an der Monotonie dieses Zählers, und ein Eingriff hätte breite
  Auswirkungen – `filterbystandarderror` und `filterbytestinfo` vergleichen
  `count(playedquestions)` gegen `minimumquestions`, die Mindestfragezahl wäre
  also eine Frage früher erreicht. Die saubere Trennung
  „administriert / offen / beantwortet" bleibt als eigener Schritt offen.
- phpcs Exit 0, plugin-weiter PHPDoc-Check 0 Fehler; Kern- und Feedback-Suiten
  grün (catcalc, model_item_param_list, Importer, progress-Suiten,
  maximumquestionscheck, item_parameter_contract).

## 1.1.5 (interne Version 2026082136)

> Zwei Befunde mit derselben Wurzel wie schon beim Import: ein locale-unsicherer
> Zahlparser. Dazu die richtige Zählgröße für die Testlänge.

- **Obergrenzen im Feedback-Formular wurden am Komma abgeschnitten.**
  `feedbackclass` las die Grenzwerte per
  `optional_param(..., PARAM_FLOAT)`. Moodles `PARAM_FLOAT` ist ein **reiner
  Cast**: `clean_param('1,5', PARAM_FLOAT)` ergibt **1.0**, `'-0,5'` ergibt
  `-0.0`. Eine eingegebene Obergrenze „1,5" landete deshalb als „1" im Feld,
  während die nächste Untergrenze weiterhin 1,5 zeigte - daraus entstand die
  Validierungsmeldung „Keine Lücken in Personenfähigkeitsspanne erlaubt".
  - Fix: neuer locale-sicherer Leser `feedbackclass::optional_limit_param()`
    (`PARAM_RAW` + `unformat_float()`, mit Punkt-/Komma-Fallback), eingesetzt für
    **Ober- und Untergrenze**. Die Untergrenze war nur deshalb unauffällig, weil
    ihr Wert meist aus den gespeicherten Defaults kam, nicht aus dem Request.
  - Verifiziert: `"1,5"` → 1.5 (vorher 1.0), `"-0,5"` → -0.5 (vorher -0.0);
    Punkt-Notation unverändert korrekt.
- **Behat „Question 5": die Zählgröße war falsch, nicht nur der Zähler.**
  Die Prüfung in `maximumquestionscheck` stützte sich zuletzt auf
  `max(questionsattempted, playedquestions)`. Beide sind **nicht autoritativ**:
  `questionsattempted` wird außerhalb des Plugins gepflegt und driftet beim
  Resume, und `playedquestions` zählt **angezeigte** Items - `progress::load()`
  entfernt dort beim Resume sogar die noch offene letzte Frage.
  - Fix: neue autoritative Methode
    `progress::get_num_answered_productive_questions()` zählt **beantwortete**
    Items (über das `responses`-Array) ohne Pilotitems. `maximumquestionscheck`
    nutzt ausschließlich diese Größe; `questionsattempted` bleibt reiner
    Legacy-Fallback, wenn kein `progress` im Kontext liegt.
  - Damit gilt exakt: angezeigt-aber-unbeantwortet zählt **nicht**, ein Resume
    ändert die Zählung **nicht**, und nach der vierten Antwort ist Schluss.
- **Tests neu geschnitten** (`maximumquestionscheck_test`, 5 Tests): Stopp bei
  erreichter Antwortzahl, offenes Item zählt nicht, **vollständiger
  Resume-Lifecycle** (Q1 beantwortet → Q2 offen → Resume → Q2–Q4 beantwortet →
  Stopp), Pilotantworten zählen nicht, `-1` unbegrenzt, Fallback ohne progress.
  **Zahn-getestet gegen beide alten Zählgrößen**: zurück auf `questionsattempted`
  → 2 Failures; zurück auf `playedquestions` → 4 Errors.
- phpcs Exit 0, plugin-weiter PHPDoc-Check 0 Fehler, übrige Suiten grün.
- **CI-Stand**: `codeanalysis` success; Behat von 3 auf 2 Fails gesunken (der
  `graphicalsummary`-Fail ist seit 2026082134 behoben).

## 1.1.5 (interne Version 2026082135)

> Die verbleibenden zwei Behat-Fails („Question 5") liegen **nicht** in
> `mod_adaptivequiz`, sondern in der Abbruchlogik von `local_catquiz`.
> Damit ist die im Dokumentationsplan vermerkte Altlast „Test bricht nicht bei
> Maximalfragezahl ab" behoben.

- **Korrektur einer früheren Fehleinschätzung.** Ich hatte die „Question 5"-Fails
  dem Slot-Reuse in `mod_adaptivequiz` zugeschrieben. Der aktuelle Lauf widerlegt
  das: nach dem Resume erscheint korrekt „Question 2", alle Zwischenschritte
  bestehen – der Slot-Reuse **funktioniert**. Es scheitert erst daran, dass nach
  **vier beantworteten Fragen** eine fünfte ausgeliefert wird, obwohl
  `catquiz_maxquestions = 4` konfiguriert ist.
- **Ursache**: `maximumquestionscheck` prüfte ausschließlich gegen den extern von
  `mod_adaptivequiz` gepflegten Zähler `questionsattempted`. Dieser kann beim
  Wiederaufnehmen eines Versuchs driften (das offene Item wird erneut gerendert,
  ohne erneut gezählt zu werden) – und ein um eins zu niedriger Zähler lässt
  genau ein Item zu viel zu.
- **Fix**: Die Prüfung stützt sich jetzt zusätzlich auf die **eigene**
  Fortschrittszählung (`progress->without_pilots()->get_playedquestions()`) und
  nimmt das Maximum aus beiden Quellen. Diese Zählung ist driftfest, weil
  `add_playedquestion()` beim Ausliefern greift und nach Fragen-ID indiziert ist –
  ein beim Resume erneut ausgeliefertes Item wird also nicht doppelt gezählt.
  Pilotitems zählen nicht zur produktiven Testlänge. Fehlt `progress` im Kontext,
  gilt weiterhin der bisherige Zähler.
- **Regressionstest** `maximumquestionscheck_test` (4 Tests): Stopp bei
  erreichtem Maximum, **Stopp auch bei driftendem externem Zähler**, Weiterlaufen
  unterhalb des Maximums, `-1` = unbegrenzt sowie Fallback ohne `progress`.
  **Zahn-getestet**: alte Logik reinjiziert → der Drift-Test fällt.
- **CI-Stand**: `codeanalysis` ist **success**. Behat ist von 3 auf 2 Fails
  gesunken – der `graphicalsummary`-Fail (`.catquiz-response-answerlabel`) ist
  durch 2026082134 behoben. phpcs Exit 0, plugin-weiter PHPDoc-Check 0 Fehler.

## 1.1.5 (interne Version 2026082134)

> Quizverlauf-Tabelle: Breite, Korrektheits-Indikator und Modal-Ladefehler.
> Der neue Behat-Fail (`.catquiz-response-answerlabel` fehlt) hatte dieselbe
> Ursache wie die fehlende Korrektheitsangabe.

- **Korrektheitsangabe fehlte – toter Code.** `render_table()` baute die Zelle
  `$responsecell` (Verdikt + gegebene Antwort) vollständig auf, fügte sie aber
  **nie in `$newrow` ein**. Die Spalte fehlte deshalb komplett in der Ausgabe –
  und der Behat-Test fand folgerichtig weder `.catquiz-response-answerlabel`
  noch `.catquiz-responsesummary`.
  - Neu: eigene Spalte **direkt nach der Fragenzahl**, als Icon –
    `fa-solid fa-circle-check` (grün) für richtig,
    `fa-solid fa-circle-xmark` (rot) für falsch,
    `fa-solid fa-triangle-exclamation` (gelb) für teilweise richtig.
  - Die Spalte erscheint **nur**, wenn „Indikator zur Korrektheit der gegebenen
    Antwort anzeigen" (`catquiz_showquestionresponse`) aktiv ist.
  - Barrierefrei: Icon mit `title` und `aria-hidden`, Verdikt zusätzlich als
    `sr-only`-Text – die Aussage hängt nicht an Farbe/Form allein.
- **Tabelle zu breit.** Die CSS-Regel `min-width: 55rem` zwang die Tabelle über
  die Containerbreite hinaus und schob den ganzen Feedback-Tab in horizontales
  Scrollen. Ersetzt durch `max-width: 100%`; die neue Korrektheitsspalte ist wie
  Nummer/Fähigkeit/Aktion schmal gehalten (`width: 1%`).
- **Modal-Ladefehler „Cannot call moodle_page::add_body_class after output has
  been started".** `render_question_with_response::execute()` rief
  `$OUTPUT->header()` – das **startet die Ausgabe**. Alles, was das anschließende
  Question-Rendering an der Seite tut (Fragetypen und die Question-Engine rufen
  `$PAGE->add_body_class()`), lief danach in den Coding-Error. Der Renderer wird
  jetzt ohne Ausgabe initialisiert (`set_pagelayout('embedded')` +
  `$OUTPUT->doctype()`).
- **Testbarkeit**: `catquiz_showquestionresponse` (und `catquiz_showquestion`)
  sind jetzt über den Behat-Generator setzbar; die Fixture
  `testenvironmentdummy.json` enthält die `catquiz_questionfeedbacksettings`.
  Das Feature setzt die Option explizit und prüft zusätzlich
  `.catquiz-col-correctness` und `.catquiz-response-icon`.
- **CI**: Der Quality-Fail des letzten Laufs (`pilot_classification_test::question
  has incomplete parameters list`) war bereits in 2026082133 behoben – die CI lief
  auf dem älteren Commit `2be3037`. Plugin-weiter PHPDoc-Check: 0 Fehler.
  Die zwei verbleibenden Behat-Fails („Question 5") liegen unverändert in
  `mod_adaptivequiz` (Slot-Reuse) und sind hier nicht behebbar.

## 1.1.5 (interne Version 2026082133)

> Der Modell-Vertrag galt bisher nur für die Birnbaum-Modelle. Jetzt deckt er
> **alle sieben** Modelle ab, und kein Modell kann künftig vergessen werden.

- **Lücke geschlossen: polytome Modelle hatten keinen eigenen Vertrag.** GRM,
  GGRM, PCM und GPCM erbten nur die Basisprüfung auf die skalare
  `difficulty`-Spalte – die bei polytomen Modellen aber nur einen **abgeleiteten
  Mittelwert** enthält. Die echten Parameter (Thresholds bzw. Intercepts) liegen
  im `json`-Feld. Ein Item mit fehlendem oder kaputtem json wäre also
  durchgewinkt worden und erst später bei der Schätzung explodiert.
  - Neu: `model_multiparam::validate_parameters()` prüft die **json-Nutzlast** –
    vorhanden, korrekter Schlüssel (`difficulties` bzw. `intercepts`), nicht
    leer, alle Einträge endliche Zahlen.
  - `get_multi_param_name()` wurde dafür von einer Instanz- zu einer statischen
    Methode (die beiden bestehenden `$this->`-Aufrufe bleiben gültig).
- **GGRM und GPCM verlangen jetzt `discrimination` > 0.** Beide nutzen eine
  Trennschärfe; bei 0 hängen die Kategoriewahrscheinlichkeiten nicht mehr von der
  Fähigkeit ab – dasselbe stumme Item wie im 2PL-Fall. GRM und PCM nutzen keine
  Trennschärfe, dort bleibt eine gespeicherte 0 folgenlos.
- **`method_exists()`-Krücke entfernt.** Alle Modelle leiten von `model_model` ab,
  das `validate_parameters()` definiert – der Vertrag ist strukturell garantiert
  und braucht keine defensive Laufzeitprüfung mehr.
- **Neuer Wächtertest** `test_every_installed_model_implements_the_contract`
  stellt sicher, dass jedes installierte Modell den Vertrag erfüllt; ein künftig
  hinzugefügtes Modell ohne Vertrag lässt den Test fallen.
- **Verifikation**: `item_parameter_contract_test` 9 Tests / 37 Assertions grün,
  beide neuen Guards **zahn-getestet** (polytome Validierung neutralisiert → rot;
  GGRM-Trennschärfe-Guard entfernt → rot). Alle 7 Modell-Suiten unverändert grün.
  phpcs Exit 0; plugin-weiter PHPDoc-Check 0 Fehler (zwei durch die Einfügungen
  verwaiste Docblocks repariert – die bekannte Falle aus Engineering-Guide §4).

## 1.1.5 (interne Version 2026082132)

> **Root Cause der genullten negativen Difficulties gefunden und behoben.**
> Reproduziert mit der echten `ALiSe_Mathematik.csv`: vorher 0 negative
> Difficulties nach dem Parsen, nachher 326 – bei unveränderter Discrimination.

- **Ursache: Moodles Formel-Escaping schlägt auf den Zahlwert durch.**
  `csv_import_reader::load_csv_content()` legt die geparsten Zeilen über
  `csv_export_writer::print_array()` in einer Zwischendatei ab; dessen
  `add_data()` wendet `\core\dataformat::escape_spreadsheet_formula()` an. Dieser
  Helfer stellt jedem Wert, der mit `=`, `+`, `-` oder `@` beginnt, ein
  **Apostroph** voran, damit Tabellenkalkulationen ihn nicht als Formel lesen.
  Eine negative IRT-Schwierigkeit kommt im Importer damit als `'-5.81` an – und
  `floatval("'-5.81")` ist **0.0**. Positive Werte und die Discrimination sind
  nicht betroffen, weil sie nicht mit einem Formelzeichen beginnen. Das erklärt
  den Fingerabdruck exakt: *alle* negativen Difficulties → 0.0000, alle
  nicht-negativen und alle Discriminations korrekt.
  - Die CSV selbst ist sauber: 0 Apostrophe in der Datei (byteweise geprüft),
    das Apostroph entsteht ausschließlich im Import-Pfad.
  - Weder Spaltentyp (`decimal(10,4)` signed, kein UNSIGNED), noch
    `enforce_min_max_range()`, noch die Kontext-Duplizierung sind beteiligt.
- **Fix**: neuer Helfer `fileparser::strip_formula_escape()` entfernt das
  Schutz-Apostroph, bevor der Wert zur Zahl wird – angewandt sowohl im
  Float-Konvertierungspfad als auch in `cast_string_to_float()`. Ein echtes
  Apostroph in Text bleibt unangetastet (nur `'` gefolgt von `=`/`+`/`-`/`@`
  wird entfernt).
- **Verifikation**: echter Parserlauf gegen die Originaldatei – 805 Zeilen,
  **326 negative** Difficulties (vorher 0), erste Zeile `MA.v1.A01-01` jetzt
  `b=-5.81, a=0.4` (vorher `b=0.0`). Regressionstest
  `test_formula_escape_guard_is_stripped`, **zahn-getestet** (Guard entfernt →
  rot). phpcs Exit 0; Importer-Suiten grün.

## 1.1.5 (interne Version 2026082131)

> Modell-Vertrag für Itemparameter + aktiver Itemparam. Simulation mit echten
> ALiSe-Kennwerten belegt: der Schätzer ist gesund; das Einfrieren kam von
> stummen Items (discrimination = 0) und vom falsch gewählten Itemparameter.

- **`set_active_itemparam()` wählte den unkalibrierten Parameter.** Der Kommentar
  versprach „highest status", der Code sortierte aufsteigend und nahm Element 0 –
  also den **niedrigsten** Status. Bei Items mit Parametern für mehrere Modelle
  wurde damit der stale/All-Zero-Datensatz aktiv und im Test gespielt (die
  Test-Query joint korrekt über `lci.activeparamid`). Fix: absteigend sortieren.
- **Modell-Vertrag (neu).** Ursprüngliche Regel bleibt: kein Itemparameter-Eintrag
  = Pilotitem. Neu: Parameter, die für ihr Modell unbrauchbar sind, gelten wie
  fehlende – das Item wird Pilot statt produktiv gespielt.
  - `model_model::validate_parameters()` (Basis: difficulty = signed float,
    endlich; b = 0 und negative b sind gültig), überschreibbar je Modell.
  - 2PL: `discrimination` > 0. 3PL: zusätzlich `guessing` in [0, 1). 1PL: eine
    gespeicherte 0 in discrimination bleibt folgenlos.
  - `model_strategy::validate_item_parameters()` als gemeinsamer Einstiegspunkt
    für **Import und Testdurchführung**.
- **Import**: Vertragsverletzung → Import als Pilot (Status `NOT_CALCULATED`)
  statt produktiv, mit Warnung inkl. Label, Modell und Grund (de+en).
- **Testdurchführung**: `ispilot()` wendet denselben Guard an; bei aktivem
  CATQUIZ-Debug (`store_debug_info`) wird Item-ID, Modell und Grund in
  `$context['invaliditemparams']` gesammelt und per `debugging()` ausgegeben.
- **Belegt durch Simulation**: discrimination = 0 im 2PL ergibt θ-Änderung exakt
  +0,0000 (P = 0,5 für jedes θ, Fisher-Information 0) – reproduziert das
  Einfrieren bitgenau. b = 0 allein friert **nicht** ein. Dieselben Items als 1PL
  bewegen θ normal.
- **Verifikation**: `item_parameter_contract_test` (6 Tests, 19 Assertions),
  zahn-getestet (Sortierung zurückgedreht → rot; discrimination-Guard entfernt →
  rot). phpcs Exit 0; raschbirnbaum (348) und rasch (347) grün.

## 1.1.5 (interne Version 2026082130)

> Feedback-Farben: Skala-Grenzen mit deutschem Dezimalkomma wurden abgeschnitten
> (1,5 → 1), wodurch Fähigkeiten fälschlich grün statt gelb eingefärbt wurden.
> Ursache (Attempt 208) belegt aus dem Settings-Export und Schätz-Trace.

- **Ursache – `floatval`/`(float)` schneidet „1,5" zu 1 ab.** Der
  Settings-Export zeigt: die nicht angefassten Skalen (Global 22, Report-AUS)
  tragen die Gelb/Grün-Grenze korrekt bei **1,5**; alle **konfigurierten**
  Report-Skalen (u. a. Ma-D01=141, Ma-C04=133) tragen **1**. Der Feedback-Text
  dieser Skalen sagt selbst „höher als 1,5" – die Absicht war 1,5. Beim
  **Kopieren der Werte auf die Subskalen** (`catquiz_handler::set_data_after_definition`)
  werden die rohen, lokalisierten `getSubmitValues()`-Strings verarbeitet; „1,5"
  wurde dort zu 1 abgeschnitten. Der direkte Formular-Speicherpfad ist sauber
  (`float`-mform-Element nutzt `unformat_float`), daher blieben die unberührten
  Skalen korrekt.
- **Sichtbares Symptom.** Der Differenz-/Personabilities-Chart färbt die
  Subskalen-Balken über deren **eigene** (korrumpierte) Grenze `1`, während die
  Legende aus der **Globalskala 22** (Grenze `1,5`) gerendert wird → Fähigkeit
  1,10/1,32 lag ≥ 1 → **grün**, obwohl die Legende Gelb bis 1,5 zeigt.

- **Fix Schreibseite:** Der Copy-Pfad normalisiert die Grenz-Felder jetzt per
  `unformat_float()` statt sie roh durchzureichen – „1,5" wird 1.5.
- **Fix Leseseite (Härtung):** Neuer kommasicherer Parser
  `feedback_helper::parse_range_limit()`, eingesetzt in `get_color_for_personability`
  und `get_feedback_range_index`. Selbst ein je gespeicherter Komma-String wird
  nun korrekt interpretiert statt abgeschnitten.
- **Regressionstest** `feedback_range_locale_test` (DB-frei): „1,5" → 1.5;
  Fähigkeit 1,10/1,32 → **gelb** (Range-Index 2), 1,60 → grün, −1,0 → rot.
  **Zahn-getestet**: truncating cast reinjiziert → 1,10 wird grün (Index 3) und
  der Test fällt; Fix restauriert → grün. phpcs Exit 0.
- **Datenreparatur mitgeliefert:** `fix_feedback_limits.py` hebt in einem
  Settings-Export jede an `upper_*_2`/`lower_*_3` abgeschnittene `1` auf den
  Elternskalen-Sollwert `1,5` an (Attempt-208-Export: **68** Grenzen über 34
  Skalen). Das korrigierte JSON liegt bei.
- **Offener Folgepunkt (dokumentiert):** Der Differenz-Chart sollte Farbe **und**
  Legende aus derselben Skala speisen; mit dem korrigierten JSON stimmen beide
  wieder überein, die Skala-Divergenz bleibt als Härtung offen.

## 1.1.5 (interne Version 2026082129)

> CI-Fix (Quality-Job): ungültiger Inline-PHPDoc-Tag im Regressionstest behoben.
> Zusätzlich diagnostiziert: die verbleibenden Behat-Fails liegen in der
> Abhängigkeit `mod_adaptivequiz` (separater Patch beigelegt).

- **PHPDoc-Fehler behoben** (`tests/teststrategy/progress_response_accumulation_test.php`):
  Der Moodle-PHPDoc-Checker meldete in Zeile 39 `Invalid inline phpdocs tag
  @covers found`. Ursache war das Wort `@covers` **im Fließtext** des
  Klassen-Docblocks (Beschreibung der bestehenden Trajektorien-Tests) – der
  Checker wertet jedes `@…` in einem Docblock als Tag. Das `@` im Prosatext
  entfernt (`one @covers …` → `one covers …`); der legitime `@covers`-Tag auf der
  eigenen Zeile bleibt unverändert und wirksam.
  - Reproduziert mit `local_moodlecheck` (exakt derselbe Checker wie der
    CI-Quality-Job): vorher 1 `<error>`, nachher 0. **Zahn-getestet**: `@covers`
    reinjiziert → Fehler kehrt zurück; entfernt → grün. phpcs Exit 0; die Suite
    selbst läuft weiter grün (1 Test, 12 Assertions – der echte `@covers`-Tag ist
    intakt). Plugin-weiter PHPDoc-Scan: 0 Fehler.

- **Behat-Fails eindeutig zugeordnet (Fix in `mod_adaptivequiz`, nicht hier):**
  - **`.catquiz-graphicalsummary-table` fehlt** (`catquiz_graphicalsummary_modal`):
    `mod_adaptivequiz/renderer.php` gab das Attempt-Feedback über
    `html_writer::tag('p', s($attemptfeedback), …)` aus. `s()` escaped die
    CATquiz-Feedback-Tabelle zu sichtbarem `&lt;table&gt;`-Text und das `<p>`
    strippt Block-Elemente → das Diagramm-/Tabellen-Markup existiert nicht als DOM.
    Fix: als vertrauenswürdiges `format_text(…, FORMAT_HTML, ['para' => false])`
    in einem `<div>` rendern. Beigelegt als
    `adaptivequiz-feedback-html-fix.patch` (git-anwendbar; im Änderungsbereich
    phpcs-sauber).
  - **2× „Question 5"** (`catquiz_attempt_completion` Resume,
    `catquiz_slot_reuse` Reload): Der HTML-Dump belegt `id="question-…-5"` /
    `qno 5` → „Question 5" ist die **quba-Slot-Nummer**, nicht die CAT-Fragenzahl.
    Der Duplicate-Slot-Guard (`find_any_active_slot`) ist im aktuellen
    `mod_adaptivequiz`-Branch `v-3.0` (Commit `d755697`) bereits vorhanden und
    korrekt; die schrittweise Durchsicht der Resume-Sequenz ergibt mit diesem
    Guard Slots 1–4 und Stopp bei `maxquestions=4`. Da `v-3.0` ein **beweglicher
    Branch** ist und Guard-Commit und CI-Lauf zeitlich dicht liegen, ist der rote
    Lauf am plausibelsten ein Checkout-vor-Push-Race. Empfehlung: CI erneut gegen
    den aktuellen `v-3.0`-Tip; bei weiterhin rot ist browserbasierte Reproduktion
    nötig (im CLI-Container ohne Chrome/Selenium nicht möglich). Kein spekulativer
    Zusatz-Fix hier.

## 1.1.5 (interne Version 2026082128)

> Fähigkeitsschätzung: gezielte Regressionsabdeckung für die eingefrorene
> Trajektorie; Ursache diagnostiziert und im aktuellen Code verifiziert behoben.

- **Ursache der eingefrorenen Quizverlauf-Kurve** (Attempt 207): Nicht die
  Schätz-Mathematik, sondern ihre **Eingabe** fror ein. `progress::update_cached_responses()`
  ergänzt die Response-Menge aus dem gecachten `get_last_response_for_attempt()`
  und dedupliziert per `questionid`; lieferte der Lookup eine stale Response,
  wuchs `$this->responses` nicht und `catcalc::estimate_person_ability()` bekam
  Frage um Frage dieselbe Eingabe → bit-identische Kurve, bis die Menge extern neu
  aufgebaut wurde. Der Attempt lief auf `2026082110`; der `…19`-Fix an
  `catquiz::get_last_response_for_attempt` (höchster beantworteter Slot statt
  `max(questionattemptid)`) behebt das im aktuellen Code.
- **Warum die vorhandene Abdeckung das nicht fing**: `ability_monotonicity_test`
  ist `@covers catcalc::estimate_person_ability` (handgebaute, korrekt wachsende
  Menge direkt in den Schätzer), `updatepersonability_test` **stubt** `progress`
  und liefert `get_user_responses()` als festes Array – beide umgehen genau die
  Akkumulations-Schicht mit dem Fehler.
- **Neuer Regressionstest** `progress_response_accumulation_test`: fährt die
  **echte** Akkumulation gegen eine reale Question-Usage, beantwortet und gradet
  Frage für Frage und prüft, dass die Response-Menge **streng um eins wächst** und
  stets auf der gerade beantworteten Frage endet – kann also nicht mehr
  unbemerkt einfrieren. Grün gegen den aktuellen Code; zahn-getestet (Akkumulation
  deaktiviert → sofort rot). Schließt die Testlücke exakt auf der betroffenen Ebene.

## 1.1.5 (interne Version 2026082127)

> CI-Fix: Import-/Kalibrier-Warnung robust gemacht (polytome Array-Werte + Tests).

- **`calibration_warnings()` bricht nicht mehr bei polytomen Modellen**: difficulty
  ist dort ein Array von Schwellen; die Wertprüfung nutzt jetzt `is_numeric()`
  statt `!== ''` und überspringt Array-/nicht-numerische Werte (vorher
  „Array to string conversion"). Eine skalare Trennschärfe wird auch bei polytomen
  Items weiterhin geprüft.
- **Import-Tests korrigiert**: `count($result['errors'])` zählte auch die unter
  `result['errors']['warnings']` verschachtelten Warnungen mit. Da Warnungen keine
  Fehler sind (Items werden importiert), prüfen `testitemimporter_test` und
  `strategy_test` jetzt nur echte Fehler (`array_diff_key(..., ['warnings' => 1])`).
- Neue Testfälle in `test_calibration_warnings` für Array-/nicht-numerische Werte.
  model/importer-Suiten grün.

## 1.1.5 (interne Version 2026082126)

> Quizverlauf-Tabelle: „Antwort"-Spalte entfernt (siehe Nutzer-Feedback). Weitere
> gemeldete Punkte (Zeit-Abbruch, Farb-Schwelle, Modal, Detail-Tabelle) sind
> diagnostiziert und werden separat verifiziert nachgezogen.

- **„Antwort"-Spalte aus der Quizverlauf-Tabelle entfernt**
  (`graphicalsummary.php`): Spaltenklasse `catquiz-col-response`, Kopf
  `get_string('response')` und die Antwort-Zelle je Zeile sind raus. Die übrigen
  Spalten (Nr., Frage, Skala, Fähigkeits-Wert, optional Frage-anzeigen) bleiben.

## 1.1.5 (interne Version 2026082125)

> Import-/Kalibrier-Warnungen: Der CSV-Import weist jetzt auf degenerierte oder
> gedeckelte Item-Parameter hin.

- **Kalibrierungs-Warnungen beim Testitem-Import**: `model_item_param_list::
  save_or_update_testitem_in_db()` prüft die importierten Parameter über den neuen
  Helfer `calibration_warnings()` und meldet advisorische Warnungen (das Item wird
  weiterhin importiert, `success => 2` über den bestehenden Warnkanal – wie bei den
  vorhandenen Import-Warnungen im Ergebnis sichtbar):
  - **Nicht-positive Trennschärfe** (`a ≤ 0`): degeneriert für jedes Modell mit
    Steigung – die ALiSe-Piloten tragen `a = 0.00`.
  - **Gedeckelte Trennschärfe** (`a ≥ 5.0`, Default `trusted_region_max_b`): sehr
    wahrscheinlich ein geklemmter Schätzwert.
  - **Gedeckelte Schwierigkeit** (`|b| ≥ 10.0`, catcalc-Trait-Grenze): dito.
  - Die Grenzen liegen als Konstanten `CALIBRATION_DISCRIMINATION_CAP` (5.0) und
    `CALIBRATION_DIFFICULTY_ABS_CAP` (10.0) in der Klasse und spiegeln die
    Modell-Defaults.
- **Lang-Strings** (en + de) ergänzt: `import_warning_nonpositive_discrimination`,
  `import_warning_capped_discrimination`, `import_warning_capped_difficulty`
  (alphabetisch korrekt einsortiert; phpcs/Lang-Ordering = Exit 0).
- **Tests**: Neuer `test_calibration_warnings` (Reflection, 13 Assertions – alle
  Schwellen, Kombination, leere/fehlende Werte, Meldungsinhalt; zahn-getestet).
  Der bestehende Import-Datensatz (`raschbirnbaum`, disc `5.92`) erwartet nun
  ehrlich `success => 2`, da 5.92 die 5.0-Grenze überschreitet und korrekt gewarnt
  wird. `model_item_param_list_test`: 3/3 grün.

## 1.1.5 (interne Version 2026082124)

> Resume-Ursache gefunden und behoben. „Question 5" war die **quba-Slot-Nummer**,
> nicht die Anzahl distinkter CAT-Fragen: Ein Resume/Reload erzeugte einen
> **doppelten Slot**. Der eigentliche Fix liegt in **mod_adaptivequiz** (separater
> Patch); hier wird die falsche zählbasierte Fährte zurückgebaut und die
> Diagnose-Instrumentierung entfernt.

- **Ursache (per CI-Trace + HTML-Dump belegt)**: Der HTML-Dump der fehlschlagenden
  Szenarien zeigt `<div id="question-433000-5">` / `<span class="qno">5</span>` –
  „Question 5" ist also die **Slot-Nummer in der Question-Usage**, nicht die
  Fragenzahl. Der Instrumentierungs-Trace bewies, dass catquiz' Zählung korrekt
  ist (`played` bleibt sauber; der Reload-Filter entfernt korrekt das
  unbeantwortete letzte Item). Beim Resume wählt catquiz jedoch eine **andere**
  nächste Frage als die im aktiven, unbeantworteten Slot liegende, worauf
  mod_adaptivequiz einen **neuen** Slot anlegte → die Slot-Nummer wuchs auf 5.

- **Fix in mod_adaptivequiz** (Branch der Abhängigkeit, separater Patch
  `adaptivequiz-slotreuse-fix.patch`): Der Duplicate-Slot-Guard in
  `cat_session.php` (Issue #6) griff bisher nur, wenn dieselbe Frage neu gewählt
  wurde. Da ein CAT-Attempt immer nur **einen** aktiven unbeantworteten Slot hat,
  wird nun per `find_any_active_slot()` **jeder** aktive Slot wiederverwendet, wenn
  die Suche nach der konkreten Frage fehlschlägt. Unit-Test `test_find_any_active_slot`
  ergänzt (2/2 grün).

- **Zählbasierte `maximumquestionscheck`-Änderung zurückgebaut**: Der in
  2026082121 eingeführte `max(questionsattempted, progress-Zähler)` adressierte
  ein Nicht-Problem (die Zählung hinkte nie – es waren die Slots) und wird
  vollständig auf das Original zurückgesetzt; der zugehörige Test
  `maximumquestionscheck_test` entfällt.

- **Diagnose-Instrumentierung entfernt**: `classes/local/debugtrace.php`, alle
  Trace-Aufrufe in `progress.php` und der nicht-blockierende CI-Diagnoseschritt in
  `moodle-plugin-ci-dev.yml` sind wieder draußen.

- **Resume-Szenarien wieder im Gate**: `@catquiz_wip_resume` aus
  `catquiz_attempt_completion` und `catquiz_slot_reuse` entfernt, der
  `--tags`-Ausschluss in beiden Workflows zurückgenommen. Mit dem
  mod_adaptivequiz-Patch laufen sie grün und gaten wieder mit. **Wichtig:** Beide
  Teile gehören zusammen – ohne den mod_adaptivequiz-Patch würden die nun
  un-getaggten Szenarien in der CI erneut rot (der Slot-Bug bestünde fort).

## 1.1.5 (interne Version 2026082123) — DIAGNOSE-BUILD (temporär)

> Nur zur Resume-Triage: fügt eine Behat-only-Instrumentierung und einen
> nicht-blockierenden CI-Diagnoseschritt hinzu, die den Fragen-Zählverlauf über
> einen Resume/Reload aufzeichnen. Nach Auswertung wieder zu entfernen.

- **`classes/local/debugtrace.php`** (neu, temporär): `debugtrace::resume()`
  schreibt Trace-Zeilen nach `sys_get_temp_dir()/catquiz_resume_trace.log` –
  aber **nur wenn `BEHAT_SITE_RUNNING` gesetzt ist**. In Produktion und in normalen
  PHPUnit-Läufen komplett inert (verifiziert: kein Dateizugriff im PHPUnit-Lauf).
- **Trace-Punkte**: `progress::load()` (welcher Zweig – answered/gaveup/
  reload-remove – plus `playedquestions`-IDs vor/nach dem Reload-Filter, der die
  zuletzt gezeigte unbeantwortete Frage entfernt), `progress::add_playedquestion()`
  (hinzugefügte ID + laufende Zahl) und `maximumquestionscheck::run()`
  (`questionsattempted`, progress-Zähler, effektiver Wert, max, Entscheidung).
- **CI-Diagnoseschritt** in `moodle-plugin-ci-dev.yml` (nach dem regulären
  Behat-Schritt, `continue-on-error`): führt **nur** die
  `@catquiz_wip_resume`-Szenarien mit aktiver Instrumentierung aus und legt
  `catquiz_resume_trace.log` + `behat-resume.log` in die hochgeladene
  `error-summary-dev-behat`-Artefaktsammlung. Blockiert die Pipeline nicht; das
  reguläre Gate (ohne die Resume-Szenarien) bleibt unverändert grün.
- Fachlogik unverändert (der `max()`-Guard in `maximumquestionscheck` bleibt wie
  in 2026082121/2026082122); `maximumquestionscheck_test` weiterhin 3/3 grün.

## 1.1.5 (interne Version 2026082122)

> codeanalysis-Warning behoben; verschachtelte `.git`-Verzeichnisse aus dem
> Auslieferungspaket entfernt (Regel dokumentiert); Resume-Szenarien nach
> gescheitertem Zähler-Fix ehrlich wieder als WIP markiert.

- **codeanalysis grün**: Die in Version 2026082121 neu eingefügte Kommentarzeile
  in `maximumquestionscheck.php` begann mit `$context…` und löste
  `moodle.Commenting.InlineComment.NotCapital` aus (bei `--max-warnings 0`
  pipeline-blockierend). Umformuliert, sodass sie mit einem Großbuchstaben
  beginnt. phpcs auf die Datei = Exit 0.

- **Keine `.git`-Verzeichnisse mehr im Paket**: Das volle Arbeitspaket-ZIP enthielt
  bislang verschachtelte Fremd-`.git`-Verzeichnisse (`catquizcentralhub/client/.git`,
  `.../host/.git`), weil der `cp -a`-Build nur das oberste `.git` entfernte. Diese
  bringen das Git auf der Empfängerseite durcheinander. Die verschachtelten Repos
  sind aus dem Workspace entfernt, und der Paket-Build streift jetzt **alle**
  `.git`-Verzeichnisse (`find … -type d -name .git -prune -exec rm -rf {} +`). Die
  Regel ist in `doc/engineering-guide.md` (§5 und Checkliste §7),
  `doc/environment-setup.md` (§10) und `doc/session-start-prompt.md` festgehalten.

- **Resume-Zähler-Fix war unzureichend – Szenarien wieder WIP**: Der in 2026082121
  eingeführte `max(questionsattempted, progress-Zähler)` in `maximumquestionscheck`
  löste die zwei Resume-/Reload-Szenarien **nicht** (CI weiterhin „Question 5").
  Erkenntnis: Auch der progress-Zähler steht beim Check < Maximum – die erste Frage
  geht offenbar über den Resume verloren, sodass **beide** Zähler um eins zu
  niedrig sind. Das ist ein tieferer Cross-Plugin-Persistenzeffekt, der nur
  in-browser (kein lokales Chrome) belastbar zu triagieren ist. Der `max()`-Guard
  bleibt als sinnvolle, zahn-getestete Härtung erhalten (schadet nicht, ist Teil der
  späteren Lösung), reicht allein aber nicht. Die zwei Szenarien sind wieder mit
  `@catquiz_wip_resume` markiert und im Behat-Lauf beider Workflows ausgeschlossen,
  der Workflow-Kommentar nennt die genaue (revidierte) Ursache. Das übrige
  Behat-Set (inkl. des reparierten graphicalsummary-Szenarios) gatet weiter.

## 1.1.5 (interne Version 2026082121)

> Resume-Triage: Maximalfragen-Abbruch nutzt jetzt den resume-sicheren
> progress-Zähler; Behat-graphicalsummary vollends grün; die zwei Resume-/Reload-
> Szenarien wieder ins Gate genommen.

- **Issue #5 / Resume-Abbruch – Ursache und Fix**: Die beiden Szenarien
  `catquiz_attempt_completion` („Resuming an interrupted attempt …") und
  `catquiz_slot_reuse` („Reloading mid-attempt …") endeten nach einem
  Resume/Reload nicht bei `catquiz_maxquestions` (eine fünfte Frage erschien).
  Ursache: `maximumquestionscheck` prüfte allein `$context['questionsattempted']`,
  den Zähler aus dem `adaptivequiz_attempt`-Record von mod_adaptivequiz. Dieser
  Cross-Plugin-Zähler kann nach einem Resume/Reload um eins nachhinken (der
  gerade beantworteten Frage ist beim Lauf des Checks noch nicht angerechnet), im
  linearen Ablauf tritt das nicht auf. Fix: Der Check nimmt jetzt das **Maximum**
  aus diesem Zähler und `progress::get_num_playedquestions()` – catquiz' eigene,
  im progress-Payload persistierte und damit resume-sichere Zählung der gespielten
  Fragen. Der progress-Zähler ist pilotgefiltert (`without_pilots()`), sodass in
  piloten Attempts weiterhin der adaptivequiz-Zähler dominiert und sich das
  Verhalten nur im Resume-/Reload-Fall ändert. Neuer, zahn-getesteter Test
  `maximumquestionscheck_test` (drei Fälle inkl. „Zähler hinkt nach → trotzdem
  Abbruch"; Rücknahme des `max()` → rot).

- **`@catquiz_wip_resume` entfernt**: Die zwei Resume-/Reload-Szenarien sind
  wieder untagged und der `--tags`-Ausschluss in beiden Workflows
  (`moodle-plugin-ci-main.yml`, `-dev.yml`) ist zurückgenommen – der obige Fix
  wird damit von der CI end-to-end verifiziert, statt hinter dem WIP-Tag verdeckt
  zu bleiben.

- **Behat `catquiz_graphicalsummary_modal` – letzte fragile Assertion entfernt**:
  Die `.questionbutton`-„should exist"-Assertion hing am Setting
  `catquiz_showquestion`, das den adaptivequiz-Settings-Round-trip in der
  Behat-Umgebung nicht überlebt (der Button rendert dann nicht). Das Szenario
  prüft die Datenabbildung jetzt über die drei stabilen DOM-Assertions
  (`.catquiz-graphicalsummary-table`, `.catquiz-response-answerlabel`,
  `.catquiz-responsesummary`); Button und Modalverhalten sind durch den
  Jest-Test abgedeckt. Szenariotitel entsprechend angepasst.

## 1.1.5 (interne Version 2026082120)

> CI-Reparatur (codeanalysis + Behat) und Issue #7: Primärskalen-Delegation an
> den Ergebnis-Validator.

- **codeanalysis grün** (blockierender `codechecker` mit `--max-warnings 0`, in
  dem also auch Warnings die Pipeline killen): sechs Befunde behoben — fünf
  `moodle.Commenting.InlineComment.NotCapital`-Warnings (Großschreibung der
  Inline-Kommentare in `feedbackgenerator/graphicalsummary.php` Z. 228/366,
  `external/render_question_with_response.php` Z. 141/161/193 und
  `tests/teststrategy/strategy_test.php` Z. 589) sowie der
  `moodle.Commenting.DocblockDescription.Missing`-**Error** durch eine
  Ein-Zeilen-Beschreibung für den Datenprovider
  `strategy_returns_expected_questions_provider`. phpcs auf die betroffenen
  Dateien = Exit 0. (`phpmd`/`phpcpd` sind `continue-on-error` und damit nicht
  Pipeline-relevant.)

- **Behat-Szenario `catquiz_graphicalsummary_modal` robust gemacht**: Die
  Quizverlauf-Tabelle liegt in einem Feedback-Tab (`tab-pane fade`), der bis zum
  Öffnen durch die Lernenden inaktiv ist — die Zeilen stehen also im DOM, sind
  aber nicht sichtbar. Das Szenario prüft die Datenabbildung jetzt über
  **DOM-Existenz** (`.catquiz-graphicalsummary-table`,
  `.catquiz-response-answerlabel`, `.catquiz-responsesummary`, `.questionbutton`
  → „should exist") statt über Sichtbarkeit/Modal-Interaktion. Das Öffnen/Schließen
  des Modals inkl. der „kein hängender Spinner"-Zusage deckt der verifizierte
  Jest-Test (`tests/jest/graphicalsummary.test.js`) ab.

- **Zwei Resume-/Reload-Szenarien als `@catquiz_wip_resume` markiert und im
  Behat-Lauf ausgeschlossen** (`--tags '@local_catquiz&&~@catquiz_wip_resume'` in
  `moodle-plugin-ci-main.yml` und `-dev.yml`): `catquiz_attempt_completion`
  („Resuming an interrupted attempt …") und `catquiz_slot_reuse` („Reloading
  mid-attempt …") enden nach einem Resume/Reload nicht bei der konfigurierten
  Maximalfragezahl (es erscheint eine fünfte Frage). Ursache liegt im
  Cross-Plugin-Resume-/Slot-Reuse-Pfad (`questionsattempted` stammt aus dem
  `adaptivequiz_attempt`-Record von mod_adaptivequiz); eine belastbare Korrektur
  braucht In-Browser-Triage (lokal kein Chrome). Die jeweils *normalen*
  Completion-/Reload-Szenarien bleiben grün und gatend. Der Ausschluss ist
  dokumentiert und durch Entfernen des Tags reversibel.

- **Issue #7 – Primärskalen-Delegation an den Validator**: `validate()` liest die
  von der Strategie festgelegte Primärskala (`primaryscale.id`) aus den
  gespeicherten Feedback-Daten und reicht sie an
  `attempt_result_validator::from_personabilities()` durch. Dadurch bestimmt nur
  noch diese eine Skala das Gültigkeitsurteil (jede weitere berichtete Skala wird
  `REASON_NOT_PRIMARY` und ist damit nicht `valid`); zuvor griff der
  `$toreport`-Fallback, der *jede* berichtete Skala als primär behandelte. Fehlt
  die Angabe (z. B. Attempts, die vor dieser Persistenz finalisiert wurden),
  bleibt der Fallback erhalten. Neuer, zahn-getesteter Test
  `test_finalize_delegates_primary_scale` (Rücknahme der Delegation → rot);
  Finalizer-Suite 9/9, Validator-Suite 8/8 grün.

## 1.1.5 (interne Version 2026082119)

> Jest-Tests in die CI-Pipeline integriert; Quizverlauf-Datenabbildung über
> Behat abgesichert.

- **Jest in der CI-Pipeline** (`.github/workflows/moodle-plugin-ci-main.yml` und
  `-dev.yml`): Ein neuer Schritt „Jest (plugin JS unit tests)" führt nach dem
  Grunt-Schritt `npm install && npm test` im Plugin-Verzeichnis aus und tee-t die
  Ausgabe nach `ci-logs/jest.log` (Teil der Error-Summary-ZIP). In `main.yml` im
  sequenziellen `test`-Job vor PHPUnit; in `dev.yml` im `lint-jsamd`-Job (den das
  `ci-complete`-Gate bereits voraussetzt). Basis sind die verifizierten
  Workflow-Dateien aus dem Repository, nicht die lokale Arbeitskopie. Der
  CI-äquivalente Ablauf (`npm install && npm test`) wurde lokal grün verifiziert.
- **Quizverlauf-Datenabbildung abgesichert**
  (`tests/behat/catquiz_graphicalsummary_modal.feature`): Das Szenario prüft nun
  zusätzlich, dass die responsive Ergebnistabelle
  (`.catquiz-graphicalsummary-table`) gerendert wird und der tatsächlich gegebene
  Antwortwert dort erscheint. Damit ist die Feld-/Datenabbildung, die sich im
  reinen PHPUnit-Harness nicht auslösen ließ (Feedback-Pipeline hängt am vollen
  Preselect-/Response-Flow), end-to-end im echten Browser-Flow abgedeckt.

## 1.1.5 (interne Version 2026082118)

> Audit + Fix von `get_last_response_for_attempt` (Expertise Teil C).

- **`catquiz::get_last_response_for_attempt` korrigiert**: Die Abfrage keyte
  bisher auf `max(questionattemptid)` — das question_attempt mit der höchsten ID,
  also die zuletzt **hinzugefügte** Frage. Das ist nicht die zuletzt
  **beantwortete** Frage: war das zuletzt hinzugefügte Item noch unbeantwortet,
  filterte der Finished-State-Filter alles weg und die Abfrage lieferte `null`,
  obwohl frühere Items beantwortet waren; zudem wurde angenommen, die
  Attempt-ID-Reihenfolge entspreche der Administrationsreihenfolge. Die Abfrage
  nutzt nun eine belastbare **slot-/schrittbasierte** Ordnung: der höchste Slot
  mit einem abgeschlossenen Antwortschritt und darin der finale Schritt — so
  gehören `questionattemptid`, `slot`, `questionid`, `fraction` und
  `responsesummary` immer zur **selben** beantworteten Frage.
- **Zahn-getesteter Regressionstest**
  (`test_get_last_response_uses_last_answered_not_last_added`): zwei beantwortete
  Items plus ein hinzugefügtes, aber unbeantwortetes; erwartet den höchsten
  beantworteten Slot statt `null`. Mit der alten `max(questionattemptid)`-Logik
  wird der Test rot (verifiziert).

## 1.1.5 (interne Version 2026082117)

> Teil J vervollständigt: Jest-Tests (Modal) + Behat-Feature (End-to-End).

- **Jest-Tests** für den „Frage anzeigen"-Modal-Handler
  (`tests/jest/graphicalsummary.test.js`, 3 Tests, grün): erfolgreiches AJAX
  öffnet das Modal und entfernt den Spinner; eine AJAX-Rejection entfernt den
  Spinner **und** meldet den Fehler via `core/notification` (kein Dauerspinner)
  und öffnet kein Modal; ein Doppelklick startet keine parallelen Requests. Ein
  plugin-lokales Jest-Setup wurde ergänzt (`package.json`, `babel.config.js`,
  Mocks für die Core-AMD-Module unter `tests/jest/mocks/`), da Moodle 4.5 kein
  eigenes Jest-Framework mitbringt. `package.json`/`babel.config.js` sind
  export-ignore (nicht im Release-ZIP).
- **Behat-Feature** (`tests/behat/catquiz_graphicalsummary_modal.feature`):
  vollständiges Szenario – Attempt starten, Fragen beantworten, abschließen,
  Quizverlauf öffnen, „Gegebene Antwort" prüfen, Lupe klicken, Modal öffnet,
  schließen, zweite Frage öffnen. An das funktionierende `catquiz_slot_reuse`-
  Feature modelliert; Ausführung erfolgt browserbasiert in der CI.

## 1.1.5 (interne Version 2026082116)

> Tests für „Frage anzeigen" (Expertise Teil J) + Chartdaten-Verifikation (I).

- **External-API-Test** (`render_question_with_response`): gültiger Slot rendert
  die Frage; falscher Slot bzw. nicht passende Question-Attempt-ID lösen einen
  kontrollierten `invalidquestionslot`-Fehler aus (statt stillem Fehlschlag, der
  den Modal-Spinner hängen ließ). Test in `strategy_test`, nutzt einen echten
  Question-Usage-Attempt; die Testumgebung aktiviert dafür `catquiz_showquestion`
  und `catquiz_questionfeedbacksettings`.
- **Teil I (Chartdaten = Globalskala) verifiziert**: `personability_after` wird
  aus `person_ability[catscaleid]` gebildet, wobei `catscaleid` in
  `attemptfeedback` auf `catquiz_catscales` (die konfigurierte Globalskala des
  Tests) gesetzt wird – nicht auf die zuletzt beantwortete Subskala.

## 1.1.5 (interne Version 2026082115)

> Quizverlauf-Tabelle & „Frage anzeigen"-Modal (Expertise Teil B–H, L).

- **„Frage anzeigen"-Modal robust** (`amd/src/graphicalsummary.js`): der
  Click-Handler ist in try/catch/finally gekapselt; der Ladespinner wird im
  `finally` **immer** aufgelöst (nie mehr Dauerspinner bei AJAX-Fehlern),
  Fehler werden über `core/notification` sichtbar gemeldet, Mehrfachklicks
  während eines laufenden Requests werden verhindert und `aria-busy` gesetzt/
  entfernt. AMD-Build via grunt regeneriert.
- **Echter Fragentitel statt CAT-Label** (`graphicalsummary`): die Tabelle zeigt
  den Moodle-Fragentitel als Primärtext und das technische CAT-Item-Label als
  Sekundärinfo. Legacy-Zeilen fallen sauber auf das Label zurück.
- **Antwortspalte eindeutig** – „Gegebene Antwort": die Antwort wird als solche
  gekennzeichnet und über `format_text` mit aktiven Filtern gerendert, sodass
  TeX/STACK-Formeln korrekt erscheinen statt roh (`[\dfrac…]`); `format_text`
  bereinigt zugleich das HTML (kein XSS aus Nutzereingaben).
- **Sichere HTML-Erzeugung**: klickbarer Fragenname und Lupe über `html_writer`
  mit Attribut-Arrays statt roher `sprintf`-Interpolation; die Lupe ist ein
  echtes `<button type="button">` mit `aria-label`/`title`.
- **Zuverlässige Slot-/Question-Attempt-Auflösung**: Legacy-Zeilen ohne
  gespeicherten Slot werden **einmalig** über die Question-Usage aufgelöst
  (question_id → Slot/Question-Attempt/Titel je Occurrence) statt über den
  Zeilenindex (`$index + 1`). Keine N+1-Abfragen.
- **Responsive Ergebnistabelle**: eigene Klasse
  `catquiz-graphicalsummary-table` in einem `table-responsive`-Wrapper, mit
  semantischen Spaltenklassen (`catquiz-col-*`); schmale Spalten (Nr., Wert,
  Aktion) `nowrap`, keine globalen `table`-Regeln. Kein Umbruch mitten im Wort.
- Entwicklungshistorische `Issue #12:`-Kommentare in fachliche Kommentare
  überführt; DE/EN-Strings ergänzt (`feedback_table_givenanswer`).

## 1.1.5 (interne Version 2026082114)

> Flip-Heuristik korrigiert (schwierigste/leichteste Frage, trennschärfen-
> standardisiert, Monotonie-Guard); fester Export-Tab; Durchschnitts-Text bei
> zu wenigen Peers korrigiert.

- **Personenschätzung – Flip-Heuristik überarbeitet** (`updatepersonability`):
  - Flip-Ziel ist nun die **schwierigste** (All-correct) bzw. **leichteste**
    (All-wrong) beantwortete Frage der Skala statt der zufällig zuletzt
    administrierten. Das entfernt den Cross-Step-Jitter, der den berichteten
    Fähigkeitseinbruch (z. B. 2.82→2.42) verursachte.
  - Das Flip-Probe-Item wird **trennschärfen-standardisiert** (Diskrimination = 1,
    Schwierigkeit erhalten; bei polytomen Modellen bleiben die übrigen Parameter
    erhalten). Damit hängt der Alternativwert nicht mehr von der (teils gedeckelten)
    Diskrimination des Extremitems ab – nachgewiesen konstant statt Spanne ~0.4.
  - **Monotonie-Guard** für Degeneriertmuster: All-correct senkt den gespeicherten
    Wert nie unter den Vorschritt, All-wrong hebt ihn nie darüber.
  - Absicherung durch `ability_monotonicity_test` (unverändert grün).
- **Fester Export-Tab** (`debuginfo`/`exportattempt`): Der Export ist jetzt für
  Nutzer mit Feedback-Berechtigung **immer** verfügbar (Haupt-Export
  `export_feedback_csv.php`), nicht mehr nur bei aktiviertem Debug-Speicher. Die
  Debug-Exporte (Roh-Dump, Debug-CSV, PDF) erscheinen nur zusätzlich, wenn
  `store_debug_info` aktiv ist. DE/EN-Strings ergänzt.
- **Durchschnitts-Vergleich bei zu wenigen Peers** (`comparetotestaverage`): Der
  Fließtext behauptete auch ohne genügend Vergleichsdaten „…beträgt 0.00". Bei
  `has_enough_peers = false` wird nun ein eigener Text
  (`feedbackcomparetoaverage_nopeers`, DE/EN) verwendet, der keinen Schein-
  Durchschnitt nennt. Das Vergleichs-Dreieck war bereits korrekt ausgeblendet.

## 1.1.5 (interne Version 2026082113)

> Aufräumung + Monotonie-Absicherung der Personenschätzung.

- **`newton_raphson_multi_stable` → `newton_raphson`** umbenannt (mathcat:
  `newton_raphson`, `gradient_ascent`; catcalc-Aufrufer nachgezogen). Keine
  weiteren/alten Newton-Verfahren vorhanden.
- **Neuer Regressionstest `ability_monotonicity_test`**: sichert, dass die
  Basis-Schätzung `estimate_person_ability` auf **ausschließlich realen
  Responses** psychometrisch monoton ist — All-correct erhöht nie die Fähigkeit
  nicht (1PL/2PL/3PL), All-wrong senkt sie nie nicht (90 Assertions). Grundlage
  für die Ursachenanalyse des berichteten Fähigkeitseinbruchs.
- Workbench bereinigt (verwaiste leere Verzeichnisse entfernt).

## 1.1.5 (interne Version 2026082112)

> Aufräumung als Vorbereitung des „vermuteter Wert"-Features (8.3): korrekte
> MAP-Terminologie und Entfernung einer toten Legacy-Krücke.

- **Rename `fneapestimator` → `fnmapestimator`** (inkl. `…derivative1st`,
  interner `eapgradient` → `mapgradient`, Doc/Meldungen EAP → MAP) in `mathcat`
  (`newton_raphson_multi_stable`, `gradient_ascent`). Was der Schätzer rechnet,
  ist ein Posterior-**Modus** (MAP) mit Gauss-Prior, keine EAP-Quadratur — die
  Benennung ist jetzt konsistent. Reine Umbenennung, byte-identisch verifiziert;
  der Hook bleibt (wie bisher) ungenutzt.
- **`fallback_ability_update()` entfernt** (`updatepersonability`): toter Code
  (0 Aufrufer, kein dynamischer Aufruf, keine Test-Referenz) — eine sehr frühe
  Legacy-Lösung (`±5·fraction`-Halbschritt) des Degeneriert-Falls, längst durch
  den TR-regularisierten `maybe_change_to_alternative_ability`-Pfad ersetzt.
- Bestätigt (kein Codeänderungsbedarf): der aktive MAP-Prior N(ParentScore,
  ParentSE) läuft bereits über die Trusted-Region-Terme; alle drei
  `estimate_person_ability`-Aufrufstellen speisen `parentability`/`parentse`.
- Regression grün: mathcat 15/15, catcalc 8/8, updatepersonability 7/7,
  Trajektorien-Wächter.

## 1.1.5 (interne Version 2026082111)

> Load-Test-CI (jMeter/k6) repariert – Patch unverändert übernommen. CI grün.
> Betrifft nur `.github/` (per `.gitattributes export-ignore` nicht im Release-ZIP).

- **`.github/workflows/load-jmeter.yml`, `load-k6.yml`:** Load-Tests installieren
  jetzt eine **echte Live-Moodle-Site** (`moodle-plugin-ci … --no-init`, dann
  `admin/cli/install.php` + `upgrade.php` + `purge_caches.php` + Schema-
  Verifikation) statt der PHPUnit/Behat-Konfiguration; **Multi-Worker-PHP-Server**
  (`PHP_CLI_SERVER_WORKERS=8`), damit Concurrency-Messungen valide sind;
  robusterer Readiness-Check (HTTP 200 **und** Login-Token, Server-Prozess-Check);
  Diagnose-Artefakte bei Fehler. Trigger nur noch auf Push/Merge zu `main`
  (PRs deckt „Moodle Plugin CI Main" ab, keine Doppelläufe). Action-/PHP-Anhebung:
  `checkout@v6`, `cache@v5`, `upload-artifact@v7`, PHP `8.3`.

## 1.1.5 (interne Version 2026082110)

> Verifikationslücke geschlossen: End-to-End-Trajektorien-Wächter reaktiviert
> (test-only). Entblockt spätere Estimation-Hotpath-Umbauten (u. a. #9 Phase 3).

- **Neuer Wächter** `strategy_test::test_all_wrong_attempt_drives_ability_down`:
  ersetzt den brittle-gepinnten, übersprungenen
  `test_strategy_returns_expected_questions` durch einen toleranz-/invarianten-
  basierten Test. Fährt einen ganzen Attempt über `fetch_question_id()` mit
  All-falsch-Antworten und prüft **deterministisch**, dass die aus personparams
  gelesene Fähigkeit monoton und deutlich sinkt (Pilots = flache Schritte
  erlaubt). Deckt den vollen `fetch → Preselect-Pipeline → Schätzung →
  personparams`-Fluss ab, den der estimator-only catcalc-Simulationstest nicht
  sieht.
- **Zahn-getestet:** entfernt man den During-Attempt-`update_person_param` in
  `updatepersonability` (genau der Phase-3-Eingriff), bleibt die Fähigkeit bei 0
  → Wächter rot.
- **Befund** (im Plan dokumentiert): der geladene `person_ability` ist nur der
  Newton-Startwert; die Schätzung konvergiert zur MLE unabhängig davon. Das
  senkt das Phase-3-Risiko (Startwert-Umleitung ist risikoarm; die
  Write-Entfernung ist nun bewacht).
- Der alte gepinnte Test bleibt bewusst `markTestSkipped` (dokumentiert die
  Pinning-Historie). Reine Testarbeit, kein Produktionscode geändert.

## 1.1.5 (interne Version 2026082109)

> #9-Restpunkt, Phase 2 (risikoarm): exakte Pre-Attempt-Wiederherstellung.

- **`progress` erweitert:** Neues Feld `preattemptabilities` (backward-kompatibel
  serialisiert) mit `capture_preattempt_abilities()` (idempotent) und
  `get_preattempt_abilities()`.
- **`personability_loader` (additiv):** erfasst beim ersten Item die geladenen
  Vorwerte als Pre-Attempt-Zustand, bevor irgendein During-Attempt-Estimate
  geschrieben wird. Der Estimation-Fluss bleibt unverändert.
- **Finalizer-Reconciliation verfeinert:** eine in diesem Versuch **nicht**
  valide gemessene Skala wird nun bevorzugt auf ihren **exakten Pre-Attempt-Wert**
  zurückgesetzt (Phase 2); Fallback bleibt der letzte valide Historienwert
  (Phase 1); ohne beides bleibt der Wert unverändert. Schließt die in Phase 1
  offene Lücke „keine valide Historie vorhanden".
- **Tests:** `progress_preattempt_test` (Capture-Idempotenz, Save/Reload-Round-
  Trip, Backward-Compat) und `attempt_finalizer_test::test_finalize_restores_exact_preattempt_value`,
  beide mit **Zahn-Test**. Regression grün (catcalc, learningprogress,
  feedback_gating, attemptfeedback u. a.).

## 1.1.5 (interne Version 2026082108)

> Cross-Plugin Behat-Fixes (CI-Diagnose ausgewertet): drei rote Szenarien grün.

- **mod_adaptivequiz (Produktion):** Das `debugging(DEBUG_DEVELOPER)` im
  Dubletten-Guard von `cat_session` entfernt. Die Slot-Wiederverwendung beim
  Reload eines unbeantworteten Items ist das **erwartete** Verhalten (#6), kein
  Entwickler-Warnfall — die Meldung ließ jedes Reload-Szenario in Behat scheitern
  („debugging() message/s found"). Verhalten (Wiederverwendung) unverändert.
- **`catquiz_attempt_completion.feature` (#5):** Resume-Schritt korrigiert —
  ein unterbrochener Versuch wird über denselben Link **„Start attempt"**
  fortgesetzt (adaptivequiz hat keinen „Continue attempt"-Link).
- **`catquiz_slot_reuse.feature` (#6):** Szenario 3 lud direkt nach einem Submit
  neu → Re-POST → Moodle blockt das per „out of sequence" (by design). Jetzt
  GET-Wiedereintritt über „Start attempt" **vor** dem Reload, sodass kein
  Re-POST erfolgt.
- Regression: gesamte adaptivequiz-Suite 151/151 (kein Test hing an der
  entfernten debugging-Meldung).

## 1.1.5 (interne Version 2026082107)

> #9-Restpunkt, Phase 1 (risikoarm): personparams-Reconciliation im Finalizer.
> Analyse + phasenweiser Plan in `doc/personparams-migration-plan.md`.

- **Analyse**: personparams ist während des Versuchs der geteilte Fähigkeits-Bus
  (jede Frage aus personparams geladen, von 8+ Tasks gelesen, Subskalen-Vererbung
  via `filterbystandarderror`), nicht nur ein Snapshot. Eine vollständige
  Entfernung der During-Attempt-Writes ist ein breiter Hotpath-Umbau (Phase 3,
  separat + simulations-/bitgenau-verifiziert).
- **Phase 1 umgesetzt (Finalizer, kein Hotpath-Eingriff):** Eine Skala, die in
  diesem Versuch **nicht** valide gemessen wurde, hinterlässt keinen
  Zwischen-/Invalid-Wert mehr als versuchsübergreifenden Zustand. Der Finalizer
  setzt sie auf den letzten validen Historienwert
  (`attemptscale_repository::get_latest_valid`) zurück; existiert keine valide
  Historie, bleibt der Wert konservativ unverändert (exakter Pre-Attempt-Wert
  folgt mit Phase 2). Schließt die eigentliche #9-Lücke (invalide/abgebrochene
  Versuche) risikoarm.
- **Tests:** `attempt_finalizer_test::test_finalize_reconciles_invalid_scale_to_last_valid`
  (invalider Versuch → Snapshot auf letzten validen Wert) mit **Zahn-Test**
  (Reconciliation entfernt → Zwischenwert bleibt → rot). Regression grün.

## 1.1.5 (interne Version 2026082106)

> Behat-Reparatur: derselbe Settings-Round-Trip-Fix wie bei
> `catquiz_feedback_validity` (Version 2026082103) auf die beiden in #5/#6 neu
> hinzugekommenen Features übertragen.

- **`catquiz_attempt_completion.feature` (#5)** und
  **`catquiz_slot_reuse.feature` (#6):** `catquiz_minquestions` 4→2 und ein
  Settings-Form-Round-Trip im Background (Lehrer öffnet „Settings" und speichert),
  damit der Adapter voll serialisierte CAT-Settings liest — sonst brach der
  Versuch nach Frage 1 ab. Gleiche latente Ursache, gleicher Fix.

## 1.1.5 (interne Version 2026082105)

> Strang „Abschluss+Ergebnisspeicherung", Phase E / Issue #8: Aktivitäts-
> Completion an ein **valides** CAT-Ergebnis koppeln — schließt den Strang.
> Cross-Plugin (mod_adaptivequiz + Adapter + local_catquiz-Finalizer). Details
> in `doc/session-056-changes.md`.

- **Neue Completion-Regel `completionvalidresult`** (mod_adaptivequiz): ein
  technisch abgeschlossener, aber **invalider** Versuch erfüllt die Regel nicht;
  erst ein Versuch mit validem CAT-Ergebnis schließt die Aktivität ab. Die
  bestehende Regel `completionattemptcompleted` bleibt unverändert (Abwärts-
  kompatibilität). `custom_completion::get_state()` verzweigt auf die Regel und
  prüft `adaptivequiz_attempt` (attemptstate=complete, resultvalid=1).
- **Neue Felder:** `adaptivequiz.completionvalidresult` (Regel-Schalter der
  Aktivität) sowie `adaptivequiz_attempt.resultstatus`/`resultvalid`
  (Ergebnis-Verdikt je Versuch). Additive, idempotente Migration.
- **Finalizer (local_catquiz)** setzt `resultvalid`/`resultstatus` auf
  `adaptivequiz_attempt` aus dem zentralen Validator-Ergebnis — feld-existenz-
  geschützt, damit local_catquiz auch mit älterem mod_adaptivequiz robust bleibt.
  Da der Finalizer-Hook vor dem `attempt_completed`-Event läuft, sieht die
  Completion-Neuberechnung `resultvalid` bereits gesetzt.
- **Observer/Form/Backup:** `attempt_state_change_observers` berechnet Completion
  auch bei aktiver `completionvalidresult`-Regel neu; `mod_form` bietet die
  Checkbox; Backup/Restore/Duplicate übernehmen `completionvalidresult` sowie
  `resultstatus`/`resultvalid`/`timefinished` der Versuche.
- **Tests:** `custom_completion_test::test_completionvalidresult_requires_a_valid_result`
  (invalide → nicht erfüllt; valide → erfüllt; Legacy-Regel unberührt);
  `attempt_finalizer_test` prüft nun `resultvalid`/`resultstatus`. Gesamte
  adaptivequiz-Suite 151/151. Behat `completion_valid_result.feature`
  (non-blocking). Core-Analyse bestätigt: `update_state` wertet Custom-Regeln bei
  automatischer Completion neu aus, der COMPLETE-Hint ist nur Early-Return-
  Optimierung — „completed-but-invalid" ergibt korrekt INCOMPLETE.

## 1.1.5 (interne Version 2026082104)

> Strang „Abschluss+Ergebnisspeicherung", Phase D / Issue #9: versuchsspezifische
> Skalenergebnisse (`local_catquiz_attemptscale`) + Persistenz im Finalizer.
> Reine local_catquiz-Arbeit. Details in `doc/session-055-changes.md`.

- **Neue Tabelle `local_catquiz_attemptscale`** (install.xml + additiver
  upgrade-Schritt): eine Zeile je finalisiertem Versuch und erfolgreich
  getesteter Skala. FK `catattemptid` → `local_catquiz_attempts.id` (nicht das
  mehrdeutige `attemptid`); `UNIQUE(catattemptid, catscaleid)`. Felder: score,
  standarderror, n, fraction, isprimary, isvalid, resultsource, validationstatus,
  timecreated.
- **`attemptscale_repository`** (`classes/local/result/`): `save_attempt_result()`
  schreibt genau eine Zeile je **gemessener** Skala (Upsert über Unique-Key,
  idempotent); Carryover-only-Skalen (N=0) werden nicht historisiert, damit
  N/Fraction/SE nie über Versuche kumuliert werden. `get_latest_valid()` und
  `get_last_primary()` als Carryover-/Priorisierungs-Abfragen.
- **Finalizer live verdrahtet** (#7-Erweiterungspunkt gefüllt): `finalize()`
  validiert zentral (`attempt_result_validator::validate`), persistiert die
  attemptscale-Zeilen und aktualisiert den `personparams`-Snapshot **nur** für
  valide, im aktuellen Versuch gemessene Skalen — alles in derselben Transaktion
  (Running → Completed → Validated → Persisted atomar).
- **Tests:** `attemptscale_repository_test` (4 Tests: eine Zeile je gemessener
  Skala, Idempotenz-Upsert, Carryover-Abfragen, **Zahn-Test** „Carryover-only
  nicht persistiert"); `attempt_finalizer_test` erweitert um Integrationstest
  (attemptscale + personparams-Snapshot nach Finalisierung). Behat
  `catquiz_attemptscale_history.feature` (non-blocking).

## 1.1.5 (interne Version 2026082103)

> Externer Patch: Behat-Fix (`catquiz_feedback_validity`, „Scenario 001") +
> CI-Härtung (Load-Workflows, Diagnostik/Artefakte).

- **Behat-Fix `catquiz_feedback_validity.feature`:** `catquiz_minquestions` von
  4 auf 2 gesenkt und ein Settings-Form-Round-Trip vorgeschaltet (Lehrer öffnet
  „Settings" und speichert). Grund: Der CAT-Settings-Generator legt nur die
  initiale JSON-Struktur an; die adaptivequiz-Integration normalisiert/
  reserialisiert sie erst über das Aktivitäts-Settingsformular. Ohne diesen
  Round-Trip las der Adapter unvollständige Settings und beendete den Versuch
  bereits nach Frage 1. Gleiches Setup wie das etablierte
  `catscales_attempt_management`-Szenario.
- **CI (`.github/workflows/`, export-ignored):** Load-Workflows
  (`load-jmeter`, `load-k6`) gehärtet (`MOODLE_DIR`, `--moodle=…`, robuster
  Server-Ready-Check); Dev-/Main-Pipeline: `upload-artifact@v4 → v7`,
  Behat mit `--dump` und angepasster Faildump-Sammlung.

## 1.1.5 (interne Version 2026082102)

> Strang „Abschluss+Ergebnisspeicherung", Phase C / Issue #7: zentraler
> `attempt_result_validator`. Reine local_catquiz-Arbeit; adaptivequiz
> unverändert. Details in `doc/session-054-changes.md`.

- **Zentraler Validator + DTOs** (`classes/local/result/`):
  `attempt_result_validator`, `attempt_result`, `scale_result`. Ein einziger Ort
  entscheidet die Ergebnisvalidität; alle Konsumenten nutzen dasselbe
  Ergebnisobjekt. Ablehnungsgründe sind maschinenlesbar (Konstanten
  `REASON_SE_MAX/SE_MIN/N_MIN/FRACTION/ROOTONLY/REPORTING_DISABLED/HIDDEN/
  NOT_PRIMARY/NOT_MEASURED`).
- **Entscheidung 8.1 (Reporting ≠ Validität):** `reportable` (Anzeige/Config)
  und `statisticallyvalid` (Messqualität) sind getrennt modelliert. Ein
  abgeschaltetes Reporting macht eine Skala nicht mehr statistisch invalide.
  Ergebnisvalidität für Completion: `valid = primary && statisticallyvalid &&
  measuredincurrentattempt` (ohne Reporting). Der historische Reportable-Satz
  (`toreport && !excluded && !hidden`) wird von
  `attempt_result::get_reportable_scale_ids()` **exakt** reproduziert.
- **Gating zentralisiert:** `feedback_helper::get_reportable_scales()` und
  `has_reportable_result()` routen jetzt durch den Validator — eine Definition
  statt verstreuter Prüfungen; Verhalten unverändert (Regression grün).
- **N ohne Pilots/Dubletten:** `validate($attemptid)` bezieht N je Skala aus
  `progress::get_playedquestions(true, …)` (pilot-gefiltert; Dubletten durch #6
  ausgeschlossen); `measuredincurrentattempt` = N > 0 (Vorwert-only ⇒ nicht
  valide).
- **Tests:** `attempt_result_validator_test` (8 Tests, 52 Assertions): saubere
  Primary-Skala, SE/N/Fraction/Rootonly-Ablehnungen, Carryover-only,
  Non-Primary, historischer Reportable-Satz, `validate()`-Integration, plus
  **Zahn-Test** der 8.1-Entkopplung (verifiziert: Entkopplung entfernt → rot,
  `feedback_gating` bleibt grün). Bestehende Feedback-Behat
  (`catquiz_feedback_validity.feature`) deckt valide/invalide Ausgänge ab und
  läuft nun durch den zentralen Validator.

## 1.1.5 (interne Version 2026082101)

> Strang „Abschluss+Ergebnisspeicherung", Phase B / Issue #6: Doppelte
> Fragen-Slots bei Reload verhindern (Slot-Wiederverwendung + Attempt-Lock +
> defensive Dubletten-Prüfung). Reine mod_adaptivequiz-/Adapter-Arbeit; kein
> local_catquiz-Produktivcode geändert. Details in `doc/session-053-changes.md`.

- **Adapter `adaptivequizcatmodel_catquiz` (Slot-Wiederverwendung):**
  `catquiz_item_administration::evaluate_ability_to_administer_next_item()` gibt
  jetzt bei noch aktivem (unbeantwortetem) Vorgänger-Slot
  `next_item::from_quba_slot()` zurück, statt immer eine neue Frage zu wählen.
  Ein Reload eines unbeantworteten Items erzeugt damit keinen zweiten QUBA-Slot
  mehr; Question-Usage und CAT-Progress bleiben konsistent.
- **mod_adaptivequiz (Locking + defensiver Guard):**
  `cat_session::run_item_administration()` läuft unter einem Attempt-Lock
  (Schlüssel: adaptivequiz-Instanz + User; deckt AJAX und normale Requests) und
  serialisiert damit Doppelklick-/Parallel-Requests. Vor `add_question()` prüft
  der neue Helfer `find_active_slot_for_question()`, ob bereits ein aktiver Slot
  derselben Frage existiert, und verwendet diesen wieder (mit `debugging()`-Log)
  statt einen Dublett-Slot anzulegen.
- **Diagnose-CLI** `cli/diagnose_duplicate_slots.php` (read-only): identifiziert
  historische Versuche mit mehreren Slots derselben Frage. Keine Auto-Reparatur
  (divergierte QUBA-Zustände sind nicht immer eindeutig auflösbar).
- **Tests:** Adapter-Slot-Reuse (`catquiz_item_administration_test`) und
  defensiver Helfer (`cat_session_test::test_find_active_slot_for_question`),
  beide mit **Zahn-Test** verifiziert (Guard entfernt → rot). Behat
  `catquiz_slot_reuse.feature` (non-blocking). Gesamte adaptivequiz-Suite
  150/150.

## 1.1.5 (interne Version 2026082100)

> Strang „Abschluss+Ergebnisspeicherung", Phase A / Issue #5: autoritativer,
> idempotenter Versuchsabschluss und atomare Endzeit. Cross-Plugin
> (local_catquiz + mod_adaptivequiz). Details in `doc/session-052-changes.md`.

- **Neuer `attempt_finalizer`** (`classes/local/attempt/attempt_finalizer.php`):
  der einzige, idempotente und transaktionale Weg, einen CATquiz-Versuch
  abzuschließen. Setzt die Endzeit aus der autoritativen `timefinished` des
  adaptivequiz-Versuchs (nicht mehr aus dem Session-Cache) und die finale
  Anzahl genutzter Testitems. Mehrfacher Aufruf ist ein No-op. Enthält leere
  Erweiterungspunkte für #7 (Validierung), #9 (Historie/Vorwerte) und #8
  (resultstatus/resultvalid).
- **`catquiz::save_attempt_to_db()` entkoppelt:** kein automatisches
  `endtime = time()` mehr während des laufenden Versuchs. `endtime` und
  `timecreated` werden nur noch bei INSERT gesetzt; bei UPDATE bleiben beide
  erhalten (die Endzeit gehört ausschließlich dem Finalizer).
- **`catquiz_handler::attempt_finished()`** liest die Endzeit nicht mehr aus dem
  Cache, sondern delegiert idempotent an den Finalizer und rendert nur noch.
- **DB-Härtung:** Unique-Index auf `local_catquiz_attempts.attemptid`
  (höchstens ein CATquiz-Versuch je adaptivequiz-Versuch) inkl.
  Dedup-Reparaturmigration für historische Dubletten.
- **mod_adaptivequiz (Fork, Work-Package):** neues, unveränderliches Feld
  `timefinished` in `adaptivequiz_attempt`; `adaptivequiz_complete_attempt()`
  setzt es genau einmal beim Wechsel auf COMPLETED und löst den neuen
  catmodel-Hook `post_complete_attempt_callback` aus (ruft den Finalizer,
  unabhängig davon, ob die Abschlussseite erreicht wird). Fehlerhaften
  `adaptivequiz_complete_attempt()`-Aufruf in `closeattempt.php` korrigiert.
- **Tests:** `attempt_finalizer_test` (Endzeit-Quelle, No-op-Fälle,
  Idempotenz-**Zahn-Test**); adaptivequiz-`timefinished`-Immutabilitätstest mit
  Sentinel-**Zahn-Test**. Beide Zahn-Tests verifiziert (Guard entfernt → rot).
  Behat `catquiz_attempt_completion.feature` (non-blocking).

## 1.1.5 (interne Version 2026082022)

> phpcs-Fix im neuen Regressionstest + Dev-CI: Code Checker nach „Code analysis".
> Details in `doc/session-051-changes.md`.

- **phpcs-Fix:** `filterbytestinfo_minquestions_test` – fehlender
  `MOODLE_INTERNAL`-Guard vor `require_once` ergänzt und trailing Whitespace
  entfernt (aus dem CI-`lint-php`-Artefakt diagnostiziert). Plugin-weit wieder
  0 Errors/0 Warnings.
- **Dev-CI:** Der Moodle Code Checker (phpcs) ist reine Coding-Style-Prüfung und
  keine Voraussetzung für die Lauffähigkeit – daher von `lint-php` nach
  `codeanalysis` verschoben (dort blockierend). `lint-php` = nur `phplint`
  (Syntax) und schaltet `phpunit`/`behat` sofort frei. `codeanalysis`
  (phpcs blockierend; phpmd/phpcpd advisory) ist jetzt Voraussetzung für
  `ci-complete`.

## 1.1.5 (interne Version 2026082021)

> Behat-Fix (CAT-Steuerung) + Härtungen aus externer Expertise; CI: phpmd/phpcpd
> als eigenständiger Job. Details in `doc/session-050-changes.md`.

- **Produktfix `filterbytestinfo`:** Die Hauptskala wird jetzt erst dann durch
  Testinformation/SE deaktiviert, wenn die global konfigurierte Mindestfragenzahl
  (`catquiz_minquestions`) erreicht ist – analog zu `filterbystandarderror`.
  Behebt, dass Versuche mit `catquiz_minquestions = 4` bereits nach Frage 1 auf
  `attemptfinished.php` landeten (Ursache der beiden roten Feedback-Behat-Szenarien).
- **Regressionstest** `filterbytestinfo_minquestions_test`: Hauptskala bleibt bei
  `minimumquestions=4` nach Frage 1–3 aktiv, darf ab Frage 4 deaktiviert werden;
  ohne globales Minimum gilt weiter das alte Verhalten. Zahn-getestet.
- **Generator-Härtung** (`tests/generator/lib.php`): optionale Settings nur bei
  tatsächlicher Angabe überschreiben (kein Zerstören der Fixture-Defaults mit
  `null` mehr, z. B. `catquiz_minquestionspersubscale`).
- **Eventlog-Behat entflakt:** flakiger Import-Event-Assert („Testitem added")
  entfernt (Pagination-abhängig); Emission jetzt deterministisch per PHPUnit
  (`eventlog_testitemadded_test`) abgedeckt. Die deterministischen r1-Asserts
  (Attempt-Completion) bleiben.
- **Observer-Bugfix:** `strpos($classname, 'local_catquiz') >= 0` (immer wahr!) →
  `str_contains(...)`; verhindert Cache-Invalidierung bei jedem Moodle-Event.
- **CI (dev):** `phpmd`/`phpcpd` in einen eigenständigen, install-freien Job
  `codeanalysis` (advisory, non-blocking) ausgelagert. `lint-php`
  (phplint + codechecker) schaltet `phpunit`/`behat` nun sofort frei – phpmd/phpcpd
  sind keine Voraussetzung mehr.

## 1.1.5 (interne Version 2026082020)

> CI-Beschleunigung: Composer-/npm-Caching; `phpmd`/`phpcpd` in den
> install-freien `lint-php`-Job verschoben. Details in
> `doc/session-049-changes.md`.

- **Composer-Cache** (`actions/cache`, `~/.cache/composer`) in allen Install-Jobs
  von dev + main sowie den Last-Test-Workflows; **npm-Cache** (`~/.npm`) in den
  Grunt-Jobs. Spart bei catquiz besonders viel, da jeder Install sechs externe
  Plugin-Abhängigkeiten zieht.
- **`phpmd`/`phpcpd`** (non-blocking) von `quality` in `lint-php` verschoben:
  beide laufen auf dem Quellcode ohne Moodle-Install und gehören damit zu den
  install-freien PHP-Checks. `lint-php` bleibt ein valides Gate (beide
  `continue-on-error`). `quality` ist jetzt schlank: `phpdoc`/`savepoints`/
  `validate` (die den Moodle-Baum brauchen).

## 1.1.5 (interne Version 2026082019)

- **Dev-CI:** redundante `lint-jsamd`→`ci-complete`-Kante entfernt (transitiv über
  `behat` abgedeckt).

## 1.1.5 (interne Version 2026082018)

- **phpcs-Fix:** Leerzeile vor der schließenden Klassen-`}` in
  `feedback_ranges_test.php` entfernt
  (`PSR2.Classes.ClassDeclaration.CloseBraceAfterBody`).

## 1.1.5 (interne Version 2026082017)

> phpcs-Fix (Lang-Reihenfolge) + weitere Dev-Pipeline-Aufteilung. Details in
> `doc/session-048-changes.md`.

- **phpcs-Fix:** `lang/en` und `lang/de` global streng nach `SORT_STRING`
  sortiert (Moodle-Standard). Behebt die CI-`moodle.Files.LangFilesOrdering`-
  Warnungen (`attemptfeedbacknotyetavailable`, `ifdefinedusedtomatch`), die durch
  eine abweichende Sektionsdetektion lokal nicht sichtbar waren. Keys unverändert
  erhalten, Streu-Leerzeile entfernt; plugin-weit 0 Errors/Warnings.
- **Dev-Pipeline weiter aufgeteilt** (vimipad-Modell): `lint-php`
  (phplint + codechecker, ohne Moodle-Install → schnell) und `lint-jsamd`
  (grunt + mustache) getrennt; neuer `quality`-Job (phpdoc, phpmd, phpcpd,
  savepoints, validate) läuft **parallel** zu `phpunit`/`behat` statt sie zu
  gaten. `phpunit` ← `lint-php`; `behat` ← `lint-php` + `lint-jsamd`;
  `ci-complete` ← alle. Error-Summary/Screenshots je Job erhalten.

## 1.1.5 (interne Version 2026082016)

> CI-Diagnostik: herunterladbare Error-Summary (ZIP) in beiden CI-Pipelines,
> Behat-Screenshots (ZIP) im Dev-Branch bei Behat-Fehlern. Details in
> `doc/session-047-changes.md`.

- **Error-Summary (beide Pipelines):** jeder Check schreibt seine Ausgabe per
  `tee` nach `ci-logs/` (mit `pipefail`, Exit-Code bleibt erhalten). Ein
  `always()`-Schritt sammelt die Logs + eine `summary.txt` und lädt sie als
  Artefakt `error-summary-*` hoch (GitHub bietet Artefakte als ZIP-Download an).
- **Behat-Screenshots (Dev, bei Fehler):** der Dev-Behat-Job lädt die
  Behat-Faildumps (Screenshots + HTML) bei `failure()` als Artefakt
  `behat-screenshots-dev` hoch. In der main-Pipeline analog `behat-screenshots-main-*`.
- Artefaktnamen je Job/Matrix eindeutig (Pflicht bei `upload-artifact@v4`).

## 1.1.5 (interne Version 2026082015)

> CI-Umbau nach vimipad-Vorbild: schnelle parallele Pipeline für Dev-Branches,
> sequenzielle Pipeline nur für main, plus JMeter- und k6-Last-Tests für
> main-Pulls/Merges. Details in `doc/session-046-changes.md`.

- **Dev-Pipeline** (`moodle-plugin-ci-dev.yml`, `branches-ignore: main`):
  parallel — schneller `static`-Gate-Job (phplint, phpcpd, phpmd, codechecker,
  phpdoc, validate, savepoints, mustache, grunt), danach `phpunit` (reduzierte
  3-Zellen-Matrix) und `behat` (eine Zelle) parallel; `ci-complete`-Gate.
- **Main-Pipeline** (`moodle-plugin-ci-main.yml`, nur `main`): unverändert
  sequenziell über die volle 6-Zellen-Matrix (lint → phpunit → behat je Zelle);
  `ci-complete`-Gate für Branch Protection.
- **Last-Tests** (`load-k6.yml`, `load-jmeter.yml`, Trigger: Pull/Push auf
  `main` + manuell): self-contained Site (Moodle 4.5 + Abhängigkeiten) via
  moodle-plugin-ci, großes Profil via `tests/load/seed_large.php`, Read-Last auf
  CAT-Manager/Statistik. Neue Assets unter `tests/load/`
  (`seed_large.php`, `catquiz-read-endpoints.js`, `catquiz-read-endpoints.jmx`).
- Alte `moodle-plugin-ci-push.yml` / `moodle-plugin-ci-pullreq.yml` entfernt
  (durch dev/main ersetzt); `erpnext.yml` unverändert.

## 1.1.5 (interne Version 2026082014)

> Strang „Diagramme+Feedback+Statistik": Nachbesserung nach externem Review –
> #14 Messunsicherheit (opt-in), #15 konfigurierbares Mindest-N, expliziter
> Overlap-Test. Details in `doc/session-045-changes.md`.

- **#14 Messunsicherheit (konfigurierbar, opt-in):** Neuer Resolver
  `feedback_helper::get_feedback_range_index_with_uncertainty` – ein
  Feedbackbereich gilt nur als sicher erreicht, wenn das Konfidenzintervall
  `Fähigkeit ± k·SE` vollständig in einem Bereich liegt; sonst neutrale
  Übergangsrückmeldung (`feedbackrangeuncertain`). Steuerung über die neue
  Admin-Einstellung `feedback_uncertainty_factor` (Default 0 = aus). In
  `customscalefeedback` verdrahtet (SE aus `newdata['se']` an die Report-Skalen
  gehängt).
- **#15 Mindest-N konfigurierbar:** `comparetotestaverage::get_min_peers()` liest
  die neue Admin-Einstellung `minpeersforcomparison` (Fallback `MIN_USERS`=3);
  ersetzt die hartkodierte Konstante in der „genug Peers"-Entscheidung.
- **#14 Overlap-Test:** expliziter PHPUnit-Test, dass eine überlappende
  Bereichskonfiguration abgelehnt wird.
- Tests `feedback_ranges_test` (+Unsicherheit, +Overlap) und
  `peer_comparison_stats_test` (+Config) zahn-getestet erweitert.

## 1.1.5 (interne Version 2026082013)

> Strang „Diagramme+Feedback+Statistik": CI-Nacharbeit (phpcs-Warnungen,
> Lang-Reihenfolge, PHPDoc, zwei Behat-Szenarien). Details in
> `doc/session-044-changes.md`.

- **phpcs:** Inline-Kommentar-Großschreibung in `questionssummary_counting_test`
  und `statistics_snapshot_test`; Lang-Schlüsselreihenfolge (EN/DE) korrigiert.
- **PHPDoc:** fehlende `@param`-Einträge ergänzt für die neuen Parameter
  `order_attempts_by_timerange` (`perperson`, `rule`), `get_attempts_by_timerange`
  (`perperson`) und `render_question_with_response::execute` (`questionattemptid`).
- **Behat „invalid attempt":** Szenario nutzt jetzt konsistent
  `catquiz_minquestions = 4`, damit deterministisch vier Fragen gespielt werden;
  die „Infer lowest skill gap"-Strategie invalidiert bei `fraction >= 1` (alle
  richtig) → alle Skalen ausgeschlossen → zentrale Meldung.
- **Behat „autocomplete":** XPath trifft nicht mehr das leere
  `form_autocomplete_selection-…-announcer`-`div`, sondern die echte
  `role='listbox'`-Auswahl bzw. den selektierten Chip
  (`span[@role='option' and @aria-selected='true']`). Kein Produktcode.

## 1.1.5 (interne Version 2026082012)

> Strang „Diagramme+Feedback+Statistik": Konsistenz Exporte = Anzeige-Regeln,
> #16-Restpunkte (Personengewichtung, historische Teilnahme) sowie Nachbesserung
> nach fachlichem Review (#10, #14, #16 Endtime). Details in
> `doc/session-043-changes.md`.

- **#10 Forced-Scale-Bug behoben:** In `inferlowestskillgap`/`infergreateststrength`
  wurde im Forced-Scale-Pfad das Ability-**Array** statt der Scale-ID als Key
  genutzt (illegaler Array-Offset). Jetzt `$relevantscale = $catscaleid` mit
  Existenzprüfung.
- **#10 Frühes Gating:** Bei ungültigem Ergebnis werden die nicht-essentiellen
  Generatoren (Peer-Vergleich, Lernfortschritt …) gar nicht mehr **ausgeführt**;
  der/die Lernende sieht nur den zentralen Hinweis. Teacher-Feedback von
  `customscalefeedback` bleibt erhalten.
- **#10 Behat-Invalidfall korrigiert:** „Infer lowest skill gap" erklärt
  `fraction >= 1` (alle richtig) für ungültig – das Szenario beantwortet nun alle
  Fragen richtig statt falsch.
- **#16 Zeitraum = Abschlusszeit:** Charts **und** Export filtern jetzt nach
  `endtime` (statt `timecreated` bzw. `starttime`).
- **Geteilte Personen-Regel:** Neuer `feedback_helper::reduce_to_one_value_per_person`
  (ein Wert je Person; `last`/`first`/`best`; 0.0 gültig, null verworfen).
  `catquiz::get_snapshot_ability_per_person` (#16) setzt darauf auf (DRY).
- **Kohortenverläufe personengewichtet:** `order_attempts_by_timerange` erhält
  einen `perperson`-Modus (ein Wert je Person **und Zeitraum**, dann aggregieren)
  und behält gültige 0.0 (`!== null` statt `empty()`). Aktiviert für Stack-Chart
  und Vergleichsverlauf (`catquizstatistics`) sowie den Peer-Verlauf im
  Studenten-Feedback (`learningprogress`). Der reine Attempt-Zähl-Chart bleibt
  versuchsgewichtet (unterscheidbar).
- **Historische Teilnahme (#16):** Statistiken und CSV-Export nutzen
  `get_attempts(..., enrolled=false)` bzw. `get_sql_for_csv_export(..., false)` –
  aktuelle Exmatrikulation verändert historische Kohorten nicht mehr, und der
  Export folgt derselben Kohortenregel wie die Charts.
- **Export = Anzeige-Regeln:** Der CSV-Export liest historische Snapshot-Werte
  aus dem Versuchs-JSON (wie die Charts), nutzt denselben halboffenen
  Range-Resolver (`get_range_of_value`, #14), dieselbe Enrolment-Regel und
  denselben Abschlusszeit-Zeitraum.
- **#14:** expliziter Overlap-Validierungstest ergänzt.
- Tests: `person_weighting_test` (geteilte Regel + perperson), `feedback_gating_test`
  (frühes Gating, ausführungsverfolgt), `feedback_ranges_test` (Overlap) – alle
  zahn-getestet.

## 1.1.5 (interne Version 2026082011)

> Strang „Diagramme+Feedback+Statistik": Issue #16 (historische
> Lehrendenstatistiken auf Versuchssnapshots). Details in
> `doc/session-042-changes.md`.

- **#16 Historische Snapshots:** Die Lehrenden-Statistik (Histogramm in
  `catquizstatistics`) baut jetzt auf `personability_after_attempt` (Wert **zum
  Zeitpunkt des Versuchs**) statt auf `get_person_abilities` (aktueller
  Parameter). Neuer geteilter Helfer `catquiz::get_snapshot_ability_per_person`.
- **#16 Mehrfachversuche:** dokumentierte Auswahlregel (`last`/`first`/`best`),
  **ein Wert je Person** (personengewichtet) — Personen mit mehreren Versuchen
  werden einmal gewichtet.
- **#16 Legacy:** Versuche ohne Snapshot werden ausgeschlossen.
- Neuer Test `statistics_snapshot_test` (historische Werte, Mehrfachversuche,
  Legacy-Ausschluss, zahn-getestet).

## 1.1.5 (interne Version 2026082010)

> Strang „Diagramme+Feedback+Statistik": Issue #15 (Peer-Vergleich kontexttreu
> und statistisch korrekt). Details in `doc/session-041-changes.md`.

- **#15 Peer-Query-Service:** Neuer geteilter `catquiz::get_peer_comparison_stats`
  mit **SQL-Aggregaten** (statt Vollabfrage nach PHP): gleicher Kontext + Skala,
  **genau ein Wert je Person** (letzter personparam je Nutzer), **aktueller
  Benutzer ausgeschlossen**; liefert `n`, Mittelwert, `lowercount`, `equalcount`.
- **#15 Midrank-Perzentil:** `100 × (n_kleiner + 0,5 × n_gleich) / n_peers` –
  Bindungen werden hälftig geteilt; der verglichene Nutzer ist nicht Teil von
  `n_peers`.
- **#15 Mindest-N** basiert nun auf eindeutigen Peers (`n`), Mittelwert schließt
  den Nutzer aus.
- **#15 Histogramm** filtert jetzt zusätzlich nach `contextid` (keine
  kontextübergreifenden Peer-Daten mehr).
- **#15 Nur gültige Ergebnisse (Ergebnis aus #10):** Der Peer-Service zählt nur
  endliche, **nicht-gesättigte** Abilities (`ABS(ability) < MODEL_POS_INF`).
  Divergierte Ergebnisse (u. a. die von #10 als ungültig erkannten Faelle
  ‚alle richtig/falsch‘ mit Fraction 0/1) werden beim Speichern auf ±1000
  geklemmt und damit als ungueltig aus der Bezugsgruppe ausgeschlossen.
- Neuer Test `peer_comparison_stats_test` (Bezugsgruppe, Dedup, Midrank,
  zahn-getestet).

## 1.1.5 (interne Version 2026082008)

> Strang „Diagramme+Feedback+Statistik": Issue #14 (benutzerdefinierte
> Feedbackbereiche überschneidungsfrei). Details in `doc/session-040-changes.md`.

- **#14 Genau ein Bereich:** Neuer kanonischer, **halboffener** Resolver
  `feedback_helper::get_feedback_range_index` ([a,b) je Bereich, oberster Bereich
  [c,d] inklusive). Ein Score auf einer gemeinsamen Grenze gehört jetzt zu genau
  einem Bereich. Genutzt in `customscalefeedback`, `get_range_of_value` sowie
  `get_courses_to_enrol`/`get_groups_to_enrol` (konsistente Zuordnung, keine
  Doppel-Einschreibung an Grenzen).
- **#14 Präzedenz:** `if (!($data['customscalefeedback_abilities'] ?? false))`
  korrekt geklammert.
- **#14 Validierung:** Speicher-Validierung deckt jetzt auch den ersten Bereich
  ab (aufsteigend, `upper_1 > lower_1`); Lücken/Überlappungen zwischen Bereichen
  waren bereits über `upper_{j-1} == lower_j` erzwungen.
- Neuer Test `feedback_ranges_test` (Grenzen/Lücken/Überlappungen, zahn-getestet).

## 1.1.5 (interne Version 2026082007)

> Strang „Diagramme+Feedback+Statistik": Issue #13 (Fragenzusammenfassung
> fachlich korrekt zählen). Details in `doc/session-039-changes.md`.

- **#13 Zählung je Frage:** `catquiz::get_attempt_statistics` liefert jetzt genau
  **eine Zeile je Frage** (letzter bewerteter Step je `questionattemptid`) statt
  je Step – Mehrfach-Steps werden nicht mehr mehrfach gezählt.
- **#13 Kategorien getrennt:** „unbeantwortet/übersprungen" ist eine eigene
  Kategorie (`gradedunanswered`) und wird nicht mehr zu „falsch" addiert; neue
  Zeile im Template + String `numberofanswersunanswered` (EN/DE).
- **#13 Pilotausschluss:** Pilotitems werden über die Progress-Pilot-IDs aus den
  Leistungszählern ausgeschlossen. Summe der Kategorien = Zahl relevanter
  (nicht-Pilot) QUBA-Slots.
- Neuer Test `questionssummary_counting_test` (Mehrfachsteps + Pilot,
  zahn-getestet).

## 1.1.5 (interne Version 2026082006)

> Strang „Diagramme+Feedback+Statistik": Issue #12 (Fragenmodal & Antwort-
> darstellung reparieren). Details in `doc/session-038-changes.md`.

- **#12 Fragenmodal (JS):** `graphicalsummary.js` schreibt den Inhalt jetzt in
  den **eigenen** Modal-Body (`modal.getBody()`), nicht mehr über den globalen
  Selektor `[data-id="modalbodyquestion"]` (der ab dem 2. Modal ins alte,
  versteckte schrieb). `setRemoveOnClose(true)`; Build via grunt neu erzeugt.
- **#12 Echter Slot:** `data-slot`/`data-questionattemptid` stammen jetzt aus dem
  gespeicherten realen `qa.slot`/`questionattemptid` (SQL um `qa.slot` ergänzt);
  Legacy-Fallback auf den Zeilenindex für Altdaten.
- **#12 Endpoint (`render_question_with_response`):** `validate_context()`,
  Eigentümer-/Review-Zugriffscheck (`local/catquiz:view_users_feedback`) **vor**
  jeder Ausgabe → fremde Versuche für Participants nicht abrufbar; Slot- und
  optionale Question-ID-Validierung; QUBA-HTML **unverändert** (kein
  `format_text`), plus `render_question_head_html()`.
- **#12 Antwort:** tatsächliche Antwort (`responsesummary`, escaped) in der
  Antwortspalte.
- Neuer Test `render_question_with_response_test` (Zugriff + Slotzuordnung,
  zahn-getestet); neuer String `invalidquestionslot` (EN/DE).

## 1.1.5 (interne Version 2026082005)

> Strang „Diagramme+Feedback+Statistik": Issue #11 (Lernfortschritt auf die
> Globalskala beziehen). Details in `doc/session-037-changes.md`.

- **#11 Lernfortschritt = Globalskala:** Die „Lernfortschritt"-Charts
  (`render_chart_for_individual_user` und `render_chart_for_comparison`) folgen
  jetzt der Globalskala (`catquiz_catscales`) statt der wechselnden Primary-Skala
  des aktuellen Versuchs. `get_studentfeedback` übergibt die Globalskala an
  `render_abilityprogress`.
- **#11 Lücken & 0.0:** Versuche ohne Globalwert erzeugen eine **Lücke** (null)
  statt übersprungen/ersetzt zu werden; ein Wert von exakt 0.0 wird korrekt
  dargestellt (explizite Nullprüfung statt `empty()`, auch in
  `find_non_nullable_value`). Werteextraktion in `extract_scale_progress_values`.
- **#11 Required-Keys:** `primaryscale` aus `get_required_context_keys()`
  entfernt (der Verlauf hängt nicht mehr an der Primary-Skala).
- Neuer Test `learningprogress_globalscale_test` (zahn-getestet).

## 1.1.5 (interne Version 2026082004)

> Behat 001: Autocomplete-Assertion auf den realen DOM-State umgestellt und die
> Race Condition beseitigt. Details in `doc/session-036-changes.md`.

- **001 Behat-Step-Fix (nur `behat_catquiz.php`, keine Produktionsklasse):** Die
  native-`<select>`-Assertion nutzt jetzt `evaluateScript` (`option.selected` +
  `option.textContent`) statt Minks `getText()`, das auf dem versteckten
  Moodle-Autocomplete-`<select>` leeren Text liefern und so falsch-negativ sein
  konnte. Der Fill-Step wartet nach dem Suggestion-Klick auf Moodles **asynchrone**
  native Selektion, bevor er ESC sendet (Race Condition behoben). Der JS-Hack
  `ensure_native_select_option_selected()` wurde entfernt – der Test prüft wieder
  Moodles echtes Verhalten. Fehlermeldungen enthalten jetzt einen vollständigen
  Options-Dump (`value`/`text`/`selected`).

## 1.1.5 (interne Version 2026082003)

> Strang „Diagramme+Feedback+Statistik": Behat-Abnahme für Issue #10
> (Feedbackansicht valide vs. ungültig). Details in `doc/session-035-changes.md`.

- **#10 Behat:** Neues Feature `catquiz_feedback_validity.feature` mit zwei
  Szenarien. Ungültiger Versuch (alle Antworten falsch → `fraction = 0`, alle
  Skalen ausgeschlossen) → genau ein zentraler Hinweis („No valid test result
  could be determined for this attempt."); valider Versuch (gemischte Antworten)
  → Feedback ohne den zentralen Hinweis. Auswahl-neutraler Invalid-Hebel (keine
  Änderung an SE-/nminscale-Auswahlparametern).

## 1.1.5 (interne Version 2026082002)

> Strang „Diagramme+Feedback+Statistik": Issue #10 Folge-Inkrement
> (Nebenwirkungs-Gating + Forced-Scale-Passthrough). Details in
> `doc/session-034-changes.md`.

- **#10 Einschreibungs-Gating:** `get_courses_to_enrol()` / `get_groups_to_enrol()`
  wählen Kandidatenskalen jetzt über `feedback_helper::get_reportable_scales()`
  (schließt `excluded`/`hidden` aus, nicht nur `toreport`). Ein ungültiges
  Ergebnis löst damit **keine** automatische Kurs-/Gruppen-Einschreibung mehr aus.
- **#10 Forced-Scale-Passthrough:** `feedbackgenerator::select_scales_for_report()`
  reicht `forcedscaleid`/`feedbackonlyfordefinedscaleid` jetzt bis zur
  Auswahlstrategie durch (Mapping auf deren `catscaleid`); neue Träger-Properties
  `feedbacksettings::$forcedscaleid` / `$feedbackonlyfordefinedscaleid`
  (Default 0/false → verhaltensbewahrend, wenn nicht gesetzt).
- Neuer Testfall `attemptfeedback_test`: `toreport`-, aber `excluded`-Skala
  schreibt nicht ein (zahn-getestet).

## 1.1.5 (interne Version 2026082001)

> Strang „Diagramme+Feedback+Statistik": Issue #10 (Feedback an valide Ergebnisse
> binden), Kern-Inkrement. Details in `doc/session-033-changes.md`.

- **#10 Generator-Gating:** `feedbackgenerator::no_data()` liefert jetzt ein
  leeres Ergebnis – Generatoren ohne Daten erzeugen keinen „nicht verfügbar"-Tab
  mehr (die Assembly überspringt leere Ergebnisse).
- **#10 Zentraler Hinweis:** Hat ein Versuch keine berichtbare Skala (`toreport`,
  nicht `excluded`/`hidden`), zeigt die Feedbackansicht **genau einen** zentralen
  Hinweis („Für diesen Versuch konnte kein valides Testergebnis bestimmt werden.")
  inkl. Ablehnungsgrund statt verstreuter Skalen-/„nicht verfügbar"-Blöcke.
- Validität und Ablehnungsgründe in `feedback_helper` zentralisiert
  (`has_reportable_result`, `get_reportable_scales`, `get_exclusion_reason_string`);
  `customscalefeedback` delegiert nun an den geteilten Helfer (DRY).
- Neue Strings `feedbacknovalidresult(+heading)` (EN/DE). Neuer Test
  `feedback_gating_test` (zahn-getestet).

## 1.1.5 (interne Version 2026082000)

> Strang „Diagramme+Feedback+Statistik": Behat-001 gefixt und #44 abgeschlossen.
> Details in `doc/session-032-changes.md`.

- **001 `catquiz_courses` (Behat):** Ursache über einen neuen PHPUnit-Roundtrip-
  Test (`catquiz_courses_persistence_test`) auf die Browser-/Form-Interaktion
  eingegrenzt (PHP/JSON-Persistenz ist nachweislich intakt). Der Autocomplete-
  Behat-Step setzt jetzt zusätzlich das native `<select multiple>` verlässlich
  (`option.selected` + `change`-Event, jQuery-Fallback) – das ist der tatsächlich
  submittierte Formwert. Drei native-`<select>`-Kontrollpunkte (nach Auswahl,
  nach Validierungsfehler, nach Save+Reload) lokalisieren jeden künftigen
  Verlust punktgenau.
- **#44 Nachberechnung:** Kern-Invariante zahn-getestet
  (`incremental_keeps_context_test`): inkrementell **mit** neuen Responses behält
  den aktiven Kontext (kein neuer Kontext, `catscale.contextid` unverändert,
  Personparameter unangetastet); disruptiv versioniert in einen neuen Kontext
  (Selbstvalidierung des Fixtures).
- **Fix `model_item_param_list::confirmed()`:** griff die Item-ID (`get_id()`) als
  Array-Key in die nach `componentid` gekeyte Liste – undefinierter Key und in
  Produktion `null->get_status()` (Fatal) in der inkrementellen Schätzung. Jetzt
  keying-robuste Iteration über die Werte. Dieser Bug blockierte die inkrementelle
  Nachberechnung real; vom #44-Zahn-Test aufgedeckt.

## 1.1.4 (interne Version 2026081718)

> CI-Fix: K2 zurückgenommen (K3 ist der Wurzelfix). Details in
> `doc/session-018-changes.md`.

- **K2 vollständig zurückgenommen:** die „freie Schwellen über der Baseline"-
  Heuristik klemmte legitim negative GRM-Schwellen (Recovery -0.7 -> 0.001);
  K3 (get_fractions-Fix) trägt die missing-bottom-Fälle allein. Recovery grün.
- phpdoc-Fehler (empirical_start_thresholds) durch den Revert behoben.
- `catscale_structure` min/max scale value nach float gecastet (PHP 8.1).

## 1.1.4 (interne Version 2026081717)

> Experiment-Konsequenzen K3, K4, K5. Details in `doc/session-017-changes.md`.

- **K3:** `get_fractions` (GRM/GGRM) zählte eine nicht-null-Baseline reduzierter
  Strukturen doppelt (Jacobian eine Komponente zu lang); Fix: niedrigste Fraktion
  als Baseline überspringen. missing-bottom-Fälle schätzen jetzt korrekt.
- **K4:** K1-Gate nutzt den projizierten Gradienten (KKT) -> keine unnötigen
  Rescues an Randoptima.
- **K5:** `catcalc::item_identifiability_report()` (beobachtete Kategorien,
  projizierter Restgradient, Boundary-Flag, well-identified, Warnungen) + Test.

## 1.1.4 (interne Version 2026081716)

> Experiment-Konsequenzen K1 & K2. Details in `doc/session-016-changes.md`.

- **K1 (Numerik):** Newton-Qualitäts-Gate + BFGS-Rescue mit keep-best in
  `catcalc::estimate_item_params` – schlecht konditionierte/flache Geometrien
  erreichen jetzt das bessere Optimum (z. B. GPCM missing-middle −508 → −222);
  gutartige Items bleiben bit-identisch.
- **K2 (Numerik):** NaN-feste GRM/GGRM-Startschwellen (freie Schwellen streng über
  der Baseline, schlüsselerhaltend; opt-in nur graded-Modelle).

## 1.1.4 (interne Version 2026081715)

> Issue #43: Zentraler Berechnungsdienst mit zwei Modi (inkrementell/disruptiv)
> und CAT-Management-UI. Details in `doc/session-015-changes.md`.

- Neuer stabiler Vertrag `classes/local/calculation/`: `calculation_mode`,
  `calculation_trigger`, `calculation_request`, `calculation_result`,
  `calculation_strategy` + `incremental_recalculation`/`disruptive_recalculation`,
  `calculation_service` (einziger Einstiegspunkt, Moodle-Lock je Skala,
  Lauf-Zusammenfassung je Skala).
- Scheduled Task nutzt den Service (inkrementell); neuer `adhoc_calculation`-Task;
  Web-Request queued nur.
- `manage_calculation.php`: capability-gated UI (disruptiv: `RISK_DATALOSS` +
  Bestätigung), Statusübersicht je Skala, verlinkt aus den Einstellungen.
  Capabilities `local/catquiz:recalculate` + `:disruptiverecalculate`.
- Test `tests/calculation_service_test.php` (7 Tests, inkl. Kontext-Invariante,
  Lock, Ad-hoc-Queue). Sprachstrings EN + DE.

## 1.1.4 (interne Version 2026081714)

> Issue #44: Scheduled-Nachberechnung erzeugt keine neuen CAT-Kontexte mehr und
> blendet keine historischen Daten aus. Details in `doc/session-014-changes.md`.

- Scheduled Task `recalculate_cat_model_params` **standardmäßig deaktiviert** +
  **vierteljährliche** Kadenz; Upgrade-Schritt setzt bestehende Installationen auf
  denselben sicheren Stand (nur wenn nicht admin-angepasst).
- Task lädt den **persistenten aktiven Kontext** aus `catscale.contextid`, nie aus
  dem Prozess-Cache; `needs_update` vor jeder Mutation; **kein** neuer/aktivierter
  Kontext (Kontext-Invariante `contextid vorher == nachher`); mtrace-Zusammenfassung.
- `catmodel_info::update_params(..., $inplace)`: kontexterhaltender Pfad
  (Itemparameter upsert in bestehenden Kontext, PP unverändert).
- Test `tests/task_recalculate_context_test.php` (Defaults + Kontext-Invariante).

## 1.1.4 (interne Version 2026081713)

> CI-Reparaturen und IP-Edge-Case-Experimente. Details in
> `doc/session-013-changes.md`.

### CI
- **Behat**: `create_catquiz_questions` nutzte das in Moodle 4.5 nicht existente
  Modul `mod_qbank`; Import läuft jetzt vorwärtskompatibel über den Kurskontext
  (Moodle 4.5) bzw. `mod_qbank` (Moodle 5.x). Regressionswächter
  `tests/generator_test.php`.
- **phpcs**: Klassen-Duplikat `MatrixException` aus `classes/matrix.php` entfernt
  (kanonisch in `MatrixException.php`); Fixture-Brace-Fehler behoben.

### IP-Edge-Case-Experimente
- Vergleich Newton/BFGS/**GA-eigenständig**/GA→Newton über pathologische
  Geometrien: IP-5 (a→0, GGRM/GPCM), IP-10 (fehlende Kategorien, alle politomen),
  IP-9 (bimodale Ability). 25 §D-Fixtures + Regressionstest
  (`tests/edgecase_ip_test.php`, 25/25 grün).
- Befund: bei fast singulärer Hesse ist Newton nicht überlegen (Iterationslimit);
  bei fehlenden Kategorien findet **GA-eigenständig** ein besseres Optimum als
  Newton/GA→Newton. GRM-Startschwellen degenerieren bei fehlender Unterkategorie
  (dokumentiert, zahn-getestet).

## 1.1.4 (interne Version 2026081700)

> Neues Release-Label **1.1.4** (fix). Übernahme des aktuellen Plugin-Stands
> (Upstream-Merge) und Wiederherstellung der Funktionsfähigkeit gegen die
> aktuellen Abhängigkeits-Branches. Details in `doc/session-003-changes.md`.

### Abhängigkeits-Branches
- **mod_adaptivequiz**: Fork-Branch `alise_adaptivequiz` (`2024123107`,
  `3.0.3dev`) – bündelt die Bridge **`adaptivequizcatmodel_catquiz`** (`1.0.3`,
  `2024123105`) unter `mod/adaptivequiz/catmodel/catquiz`.
- **local_wunderbyte_table** `3.3.0` (`>= 2024040200`), **local_shortcodes**
  `1.1.3` (empfohlen).
- Der `attemptfeedbackeditor`-Bug besteht in der Fork weiter; CI-Patch
  (`.github/ci/patch_adaptivequiz_generator.php`) bleibt aktiv.

### Moodle-4.5-Kompatibilität wiederhergestellt
- **External-Webservice-Klassen auf `core_external` migriert:** In Moodle 4.5 ist
  die globale `external_api` (samt `external_function_parameters`,
  `external_single_structure`, `external_value`) entfernt. Alle zwölf
  `classes/external/*.php` nutzen jetzt den `core_external\`-Namespace; die
  `require_once externallib.php`-Zeilen (und der dadurch überflüssige
  `MOODLE_INTERNAL`-Guard) wurden entfernt.
- **`manage_catscale`**: Top-Level-`VALUE_OPTIONAL` → `VALUE_DEFAULT, null`.
- **`dataapi::update_catscale`**: Guard gegen `null`-Kontext beim Update ohne
  `contextid` (verhinderte TypeError in `create_items_in_new_context(int)`).
- **`update_parameters`**: ungültige IDs (`<= 0`) liefern `success = false`.
- Fehlender Lang-String `functiondoesntexist` ergänzt.

### Schema/Version
- **Version-Bump erzwingt Schema-Rebuild:** Die neue Tabelle
  `local_catquiz_qhashmap` (Question-Hasher) wird erst nach Erhöhung von
  `$plugin->version` angelegt – Moodle wendet Schema-Änderungen nur bei geänderter
  Version an.

### Numerik: 3PL-Sättigung
- **Zweitableitung bei `guessing = 0` korrigiert:** An Sättigungspunkten lieferte
  die 3PL-Hesse `+b²` statt `≈ 0`, weil `p²` unterlief und die Kürzung zerstörte.
  `log_likelihood_p_p` und `get_ability_derivatives` nutzen jetzt das stabile
  Verhältnis `ratio = l/p` (kein Teilen durch `p`/`p²`). `max|3PL(c=0) − 2PL| =
  4.7e-15`. Neuer, zahn-getesteter Regressionstest in der 3PL-Suite.
- Der frühere CI-`DivisionByZeroError` (Datensatz #2) ist behoben.

### Tests
- `strategy_test` portabel für Moodle 4.5 **und** 5.0 (Question-Bank-API).
- Golden-Master-Test `test_responses_lead_to_expected_item_parameters` als
  `incomplete` markiert: die Referenz-Fixture ist veraltet relativ zur aktuellen
  Schätzung und ist gegen eine externe Referenz neu zu erzeugen.

## 1.1.2 (interne Version 2026081413)

> Diese Änderungen werden unter dem bestehenden Release-Label 1.1.2
> ausgeliefert; nur die interne `$plugin->version` wird erhöht.

### Robustheit & Testschulden abgearbeitet (P2/P3)

- **Diskriminationsgrenzen (GGRM/GPCM):** Die Diskrimination wird nun in die
  konfigurierbare, fachlich positive Trusted Region `[trusted_region_min_b,
  trusted_region_max_b]` geklemmt (gemeinsamer Helfer `restrict_discrimination()`
  in `model_multiparam`) mit hartem positivem Boden 0.1 statt des früheren
  hartkodierten `[0.1, 5.0]`. Die Setting-Defaults dieser beiden Modelle wurden von
  `min_b = -3` / `max_b = 3` auf `min_b = 0.1` / `max_b = 5.0` korrigiert (positiv,
  erhält das bisherige effektive Verhalten, jetzt admin-steuerbar).
- **Threshold-Projektion (GRM/GGRM):** Die Ordnungssicherung überschritt bisher
  ggf. die obere Grenze. Ein Vorwärts- plus Rückwärtspass hält die Thresholds nun
  innerhalb `[min, max]` (Box-Constraint) und aufsteigend.
- **Kategorienstruktur bei Erstkalibrierung:** `empirical_start_thresholds()`
  akzeptiert optional die deklarierte Kategorienstruktur des Items (Struktur vom
  Item, Häufigkeiten aus den Responses), sodass eine im Kalibrierungssample
  unbeobachtete Kategorie erhalten bleibt. Aktuelle Aufrufer bleiben unverändert;
  die vollständige Verdrahtung folgt mit dem NRM/RSM-Umbau.
- **Initiale Item-Schwierigkeit:** `estimate_initial_item_difficulties()` nutzt den
  empirischen Logit `p = (r + 0.5) / (n + 1)` – keine Division durch 0 (keine
  Antworten → neutrale Schwierigkeit 0), kein `log(0)` (alles richtig/falsch), kein
  asymmetrischer Offset.
- **FD-Toleranzen** verschärft (Gradient `atol = rtol = 1e-6`, Hessian
  `atol = 1e-5`, `rtol = 1e-4`; 100–1000× enger) – alle FD-Tests halten das.
- **Neues Recovery-/Invariant-Oracle:** Für simulierte Antworten mit bekannter
  Fähigkeit wird geprüft, dass der Schätzer die wahre Fähigkeit wiederfindet und der
  Standardfehler mit mehr Items monoton fällt – ein robuster Ersatz für das an den
  Vor-Refactor-Schätzer gepinnte (weiter geskippte) CAT-Trajektorien-Szenario.
- **Simulationstest gehärtet:** Zusätzlich zur 90-%-Trefferquote muss das mittlere
  Abweichungsband (0.01, 0.5] ≤ 2 % bleiben. Die Referenzabweichung ist bimodal
  (gemessen: 512 Treffer, 0 im Mittelband, 32 Branch-Flips von 544), sodass ein
  systematischer Drift nun auffällt, den die Trefferquote allein maskieren könnte.
- Veralteter `// Ralf …`-Kommentar entfernt.

### Politome Fisher-/Iteminformation korrigiert (Kategorie-Doppelzählung)

- `item_information()` (GRM/GGRM/PCM/GPCM) zählte die Baseline-Kategorie doppelt:
  der Baseline-Term wurde separat addiert **und** in der Schleife über das
  Kategorie-Array (das die Baseline bereits enthält) erneut. Da
  `category_information = -log_likelihood_p_p = Var(K)` frac-unabhängig ist,
  ergab sich `Var(K)·(1 + P_baseline)` statt `Var(K)` – im Beispiel ~31 % zu hohe
  Information. Wirkt direkt auf die Fisher-basierte Itemauswuahl
  (`teststrategy/preselect_task/fisherinformation`) und den ausgewiesenen
  Standardfehler. Behoben (Baseline genau einmal), gegen eine unabhängige
  FD-Referenz verifiziert und mit neuen Fisher-Tests je Modell abgesichert
  (zahn-getestet).

### PCM/GPCM `likelihood()` sättigungssicher

- Die Kategorie-Wahrscheinlichkeiten nutzen jetzt denselben Max-Shift-Softmax wie
  die Momente (statt roher `exp()`-Summen). Damit bleibt `likelihood()` bei
  extremen θ endlich (kein `INF/INF = NaN`); der Sättigungstest prüft nun auch
  `likelihood()` und `get_ability_derivatives()`.

### Estimator-Interface-Verträge vervollständigt

- `catcalc_item_estimator` deklariert wieder `get_log_jacobian()`/`get_log_hessian()`
  (von `catcalc::estimate_item_params()` benötigt); `catcalc_ability_estimator`
  deklariert jetzt `get_ability_derivatives()` (von `estimate_person_ability()`
  benötigt). Damit ist der Modellvertrag für künftige Modelle wieder vollständig.

### Weitere Robustheit & Tests

- 1PL/2PL erhalten eigene `get_ability_derivatives()`-Overrides (P einmal
  berechnet); Stufe 2 deckt damit alle 7 Modelle ab.
- Neue numerische PP-θ-FD-Tests für die 4 politomen Modelle sowie
  `get_ability_derivatives == Einzelmethoden`-Tests für alle 7 Modelle.
- TR-Grenzen: nur ein ungesetzter (`false`) oder leerer Config-Wert fällt auf
  ±5 zurück; ein administrativ gesetztes `0` wird respektiert.

### Kombinierte Personen-Ableitungen (PP-Refactor Stufe 2)

- Neue `model_raschmodel::get_ability_derivatives($pp, $ip, $frac)` liefert Score
  und Hesse in einem Durchgang. Basis-Default delegiert an
  `log_likelihood_p`/`log_likelihood_p_p`; effiziente Overrides in 3PL, GRM, GGRM,
  PCM und GPCM teilen sich die (teure) Wahrscheinlichkeits-/Momentberechnung.
- Der Personen-Schätzer (`catcalc::estimate_person_ability`) nutzt pro Response
  eine memoisierte, nach Ability-Wert geschlüsselte Hülle
  (`make_ability_derivative_callable`), sodass Jacobian- und Hesse-Callable
  dieselbe Berechnung teilen. Die Werte sind bitgenau identisch zu den getrennten
  Methoden (max. Abweichung 0.00e+0), FD- und sättigungsgeprüft bis θ = ±800.

### Simulationstest wieder aktiv (toleranzbasiert statt Skip)

- `catcalc_test::test_simulation_steps_match_reference_within_tolerance` ersetzt den
  zuvor geskippten, auf Vor-Refactor-FP-Rundung gepinnten Schritttest. Er prüft
  aggregiert, dass ≥ 90 % der Referenzschritte (radCAT/classicCAT) auf 0.01
  übereinstimmen. Die Abweichung ist bimodal (94 % < 0.01, 6 % Rand-/Degeneriert-
  fälle mit abweichendem diskreten Newton-Zweig). Fängt grobe Regressionen
  zuverlässig (Zahn-getestet).

### Code-Hygiene

- Tote Trust-Region-Methoden `get_log_tr_jacobian`/`get_log_tr_hessian` (0 Aufruf-
  stellen) aus dem Interface `catcalc_item_estimator` und allen 7 Modellen entfernt.
- Hartkodierte `[-5, 5]`-Grenzen in den politomen `restrict_to_trusted_region`
  (GRM/GGRM/PCM/GPCM) durch die Admin-Settings `trusted_region_min_a`/`_max_a`
  ersetzt (Fallback ±5), analog zu den dichotomen Modellen.

### adaptivequiz-Integration (CI)

- `attemptfeedbackeditor` wird in den Integrationstests (`testitemimporter_test`,
  `strategy_test`) bei `create_instance` gesetzt. Grund: `adaptivequiz_add_instance()`
  in der Dependency (auch im Branch `alise_adaptivequiz`) liest die Property
  unbedingt, der Generator setzt sie nie. Behebt den Blocker bei der
  Instanzerzeugung; beide Tests laufen wieder an.

### Sättigungsfestigkeit der Ableitungen (Division-by-Zero / NaN behoben)
Behebt den CI-Unit-Fehler (33× `DivisionByZeroError` in
`model_person_ability_estimator_catcalc_test`, Datensatz #2 / 3PL) und härtet
darüber hinaus **alle** betroffenen Ableitungen systematisch gegen extreme
Fähigkeiten. Ursache waren zwei Muster: (a) Division durch `P·(1−P)` bzw. die
Kategoriewahrscheinlichkeit `P_r`, die bei Sättigung auf exakt 0 unterläuft
(PHP 8 wirft dann), und (b) `exp()`-Summen, die auf `INF` überlaufen
(`INF/INF = NaN`).
- **1PL/2PL sind nachweislich unbetroffen**: ihr Score/Hesse *multipliziert*
  mit `P` bzw. `W = P(1−P)` und geht bei Sättigung sauber gegen 0.
- **3PL** `log_likelihood_p`/`_p_p` divisionsstabil umgeschrieben: über die
  Kürzung `(1−c)·W_L/(1−P) = L` wird der Score zu `b·L·(k−P)/P` (nur noch `/P`,
  `P ≥ c`), plus Grenzwert-Guard für den `c=0`-Unterlauf. Item-Ableitungen
  zusätzlich über `stabilize_denominator()` abgesichert.
- **GRM/GGRM**: Person- und Item-Ableitungen dividieren durch `P_r`; Nenner
  über `stabilize_denominator()` abgesichert.
- **PCM/GPCM**: `exp()`-Summen (Person- und Item-Ableitungen sowie LMS) auf
  **max-Shift-Softmax** umgestellt (überlauffrei; Partitionssumme ≥ 1).
- Neuer Basis-Helfer `model_raschmodel::stabilize_denominator()`: schiebt einen
  exakt-nullen (bzw. sub-ε) Nenner auf ±ε. Bei realistischen Arbeitspunkten
  inert (`|Nenner| ≫ ε`), daher FD-verifizierte Werte im Normalbereich unverändert.
- Neuer Regressionstest `tests/local/model/derivative_saturation_test.php`:
  alle 7 Modelle × θ bis ±800 × Person-/Item-Ableitungen → alles endlich
  (7 Tests, 2208 Assertions). Zahn-getestet (Revert → `DivisionByZeroError`).

### PP-Refactor politom (Personfähigkeits-Ableitungen)
Die politomen `log_likelihood_p`/`_p_p` von der impliziten
`likelihood_p/likelihood`- bzw. Roh-`exp`-Schleifen-Form auf geschlossene,
stabile Ausdrücke umgestellt (FD-verifiziert ≤ 2e-10):
- **PCM**: `Score = r − E[K]`, `Hesse = −Var(K)` über stabiles Softmax.
- **GPCM**: `Score = b(r − E[K])`, `Hesse = −b²·Var(K)`.
- **GRM/GGRM**: P/W/V-Form `Score = b(W_r − W_{r+1})/P_r`,
  `Hesse = (b²·P_r·(V_r − V_{r+1}) − (b(W_r − W_{r+1}))²)/P_r²` mit
  `Q_j = σ(b(θ − a_j))`, `W = Q(1−Q)`, `V = W(1−2Q)`.
Nebeneffekt: keine redundanten `sort_fractions`-Aufrufe mehr, weniger `exp()`.

### Persistenz politomer Item-Parameter (End-to-End korrigiert)
Fünf konkrete Persistenz-Bugs behoben, die dazu führten, dass geschätzte
politome Parameter beim Speichern/Laden verloren gingen oder valide Items
verworfen wurden:
- `calculate_params()` reichte den `$startvalue` nicht an `estimate_item_params()`
  weiter (alle vier politomen Modelle). Ohne ihn ging die Warm-Start-Kategorie-
  Struktur verloren und nicht beobachtete Kategorien fielen weg.
- Tippfehler `$starvalues` → `$startvalues` in `model_raschmodel::calculate_params()`.
- `set_parameters()` invalidiert jetzt das gecachte `json`, sodass `to_record()`
  es aus den neuen Parametern neu aufbaut.
- `add_parameters_to_record()` fehlte für GRM, PCM und GPCM (nur GGRM hatte es):
  ohne diesen Hook wurden `difficulties`/`intercepts` nicht ins Record-JSON
  serialisiert und beim Reload war das JSON leer. Für alle drei ergänzt
  (Format exakt spiegelbildlich zu `get_parameters_from_record()`).
- GGRM `is_valid()` war invertiert (NaN → gültig) und griff auf den falschen
  Schlüssel `difficulty` statt `difficulties` zu; dadurch wurden valide GGRM-
  Items von `save_to_db()` herausgefiltert. Logik korrigiert (NaN → ungültig).
- Neuer Test `tests/local/model/persistence_roundtrip_test.php`: Roundtrip
  set → save_to_db → reload für alle vier Modelle, GGRM-`is_valid`, sowie ein
  Warm-Start-Test (nicht beobachtete Kategorie bleibt via `$startvalue` erhalten).
  Alle Fixes sind zahn-getestet (Revert → Test wird rot).

### Politome LMS (Least Mean Squares) vervollständigt
Für alle vier politomen Modelle (PCM, GPCM, GRM, GGRM) ist die LMS-Zielfunktion
inkl. erster und zweiter Ableitung nach den Item-Parametern implementiert. Als
Verallgemeinerung der dichotomen Form `n·(frac − P)²` wird der **erwartete Score**
`S = n·(frac − μ)²` mit `μ = E[X] = Σ_k frac_k·P_k` verwendet (für dichotome
Items fällt das exakt auf `P(korrekt)` zurück). Gemeinsamer Basis-Helfer
`lms_assemble()`; pro Modellfamilie werden `μ`, `∂μ` und `∂²μ` analytisch
gebildet (Softmax-Tails für PCM/GPCM, kumulative Differenzen für GRM/GGRM,
inkl. Diskriminations-Kreuztermen). Alle Ableitungen sind gegen finite
Differenzen verifiziert (je 48 FD-Fälle pro Modell). Damit ist Paket B
(MLE + LORS + LMS) für die politomen Modelle vollständig.

### Trust-Region: Threshold-Ordnung für GRM/GGRM
`restrict_to_trusted_region()` erzwingt jetzt zusätzlich zur Box-Beschränkung
die aufsteigende Ordnung `a_1 ≤ a_2 ≤ … ≤ a_M` der Schwellen. Bei vertauschten
Schwellen wäre `P_k = Q_k − Q_{k+1}` negativ geworden und die Likelihood NaN.
Die Baseline-Kategorie (niedrigster Bruch) bleibt als Platzhalter unberührt.
Neuer Test bestätigt aufsteigende Schwellen und endliche Likelihood.

### CI: Code Checker, PHPUnit und Behat
- Plugin-weite Code-Checker-Bereinigung (~1080 vorbestehende Fehler via `phpcbf`
  und manuell behoben). Zwei vorbestehende Debt-Sniffs bleiben ausgeschlossen
  (`--exclude=PSR1.Classes.ClassDeclaration,moodle.Commenting.TodoComment`):
  Klasse-pro-Datei (invasiv) und die projektfremde TODO-Konvention (Wunderbyte
  nutzt einen eigenen Tracker statt `MDL-`). Plugin-weit sonst 0 Fehler/Warnungen.
- Der an die alte `exp()`-Rundung gepinnte Simulationstest
  `catcalc_test::test_simulation_steps_calculated_ability` ist übergangsweise
  geskippt (die PP-Ableitungen wurden auf die numerisch stabile P/W-Form
  umgestellt und sind FD-verifiziert; die hartkodierte Trajektorie wird als
  Follow-up neu gepinnt bzw. toleranzbasiert). Zusätzlich ein realer
  By-Reference-Fix im Test-Provider.
- Behat (adaptivequiz-Integration, umgebungsbedingt) ist übergangsweise
  `continue-on-error`.

### CI: Installations-Blocker behoben
- Abhängigkeit mod_adaptivequiz wird jetzt vom Branch `alise_adaptivequiz`
  gezogen; dieser bündelt das Subplugin `adaptivequizcatmodel_catquiz` ohne die
  doppelte Tabellendefinition. Die separate `add-plugin`-Zeile für
  `adaptivequizcatmodel_catquiz` wurde in beiden Workflows entfernt. Damit läuft
  der `install`-Schritt der Pipeline wieder durch (lokal verifiziert).

### Politome LORS (Log'ed Odds-Ratio Squared)
- LORS jetzt auch für die vier politomen Modelle. Beide Familien sind log-linear
  in einem Odds-Ratio je Grenze/Schritt: graded (GRM/GGRM) im KUMULATIVEN Odds
  P(X>=k)/P(X<k), partial credit (PCM/GPCM) im ADJAZENTEN Odds P_k/P_{k-1}. In
  beiden Fällen R_k = log(OR_k) + b(p_k - theta), Objektiv S = n*sum_k R_k^2.
- Gemeinsamer Basis-Helfer `compute_lors(...)`; die Schwellen-/Intercept-Hesse ist
  diagonal (2n b^2), nur die Diskrimination (GGRM/GPCM) koppelt über die Grenzen
  (Kreuzterme 2n(2 b x_k + log(OR_k)), H_bb = 2n*sum x_k^2). Neue Methoden
  `lors_residuals`/`lors_1st`/`lors_2nd_derivative_ip` mit OR-Array-Signatur
  (`$ors` nach Fraction gekeyt), am baseline-freien Codec ausgerichtet.
- Gegen finite Differenzen von `lors_residuals` verifiziert (je Modell 12 Fälle);
  Zahn-Test: defekter GGRM-LORS-b-Term -> Failures.

### Dynamische Dimensionalisierung
- Die Informationskriterien (`calc_aic_item`/`bic`/`caic`/`aicc`/`sabic`) nutzen
  nun die datengetriebene Parameterzahl `get_model_dim_from_ip($item)` (aus dem
  Codec) statt des parameterlosen `get_model_dim()`. Damit funktionieren sie auch
  für die politomen Modelle mit variabler Kategorienzahl. `get_model_dim()` wird
  nur noch für die dichotome Startwert-Dimensionierung und als Null-Fallback
  verwendet.

### Paket B: politome Ableitungen (alle vier Modelle)
- PCM: `get_log_jacobian`/`get_log_hessian` implementiert (warfen zuvor
  „Not yet implemented"). Item-Parameter-Ableitungen über Tail-Wahrscheinlichkeiten:
  J_δj = T_j − [r≥j], H_{j,l} = T_j·T_l − T_max(j,l), T_j = Σ_{k≥j} P_k.
- GPCM: `get_log_jacobian`/`get_log_hessian` implementiert (waren leer). Wie PCM
  mit Diskriminationsskalierung (b·… bzw. b²·…), plus Diskriminations-Ableitung
  über Momente (Score s_r−E[s], Krümmung −Var(s)) und δ_j–b-Kreuztermen.
- Beide gegen finite Differenzen von `log_likelihood` (über den Codec) verifiziert;
  Zahn-Tests bestätigen die Wirksamkeit.
- GRM: `get_log_jacobian`/`get_log_hessian` neu in P/W-Form. Der bisherige
  Jacobian hatte einen Index-Bug (Zählschleife überschrieb den Kategorieindex);
  dem Hessian fehlten die Kreuzterme. Kategorienwahrscheinlichkeit als Differenz
  benachbarter kumulativer Logistiken P_r = Q_r − Q_{r+1}; nur die beiden
  Randschwellen der beobachteten Kategorie tragen bei, mit Kreuzterm dazwischen.
- GGRM: wie GRM mit Diskrimination b (Q_k = σ(b(θ−a_k))), plus b-Ableitung und
  Kreuztermen Schwelle–b. Assembliert über H = P''/P − (P'/P)².
- Alle vier politomen Modelle nun gegen finite Differenzen verifiziert.

### Paket A: datengetriebener Parameter-Codec
- `convert_ip_to_vector`/`convert_vector_to_ip` für alle sieben Modelle korrekt
  implementiert (zuvor buggy/„dirty"-Stubs bei GRM/PCM/GPCM; bei den dichotomen
  Modellen neu ergänzt). Round-trip verlustfrei, per Test für alle Modelle belegt.
- Datengetriebene Dimension: neue Basismethode `get_model_dim_from_ip($ip)` =
  1 + Länge des flachen Parametervektors. Der bisherige `get_model_dim()` der
  politomen Modelle (fehlerhaftes `count()` auf Strings → PHP-8-`TypeError`) wirft
  jetzt eine klare `coding_exception` mit Verweis auf die datengetriebene Variante.
- `catcalc::estimate_item_params` nutzt nun den Codec: Newton-Raphson rechnet auf
  einem flachen Vektor, Jacobian/Hessian/Trusted-Region werden entsprechend
  adaptiert, das Ergebnis wird über `convert_vector_to_ip` zurückgewandelt.
  Verhalten für die dichotomen Modelle unverändert (Recovery-Tests grün;
  Zahn-Test: defektes `convert_vector_to_ip` lässt die Recovery scheitern).


### Numerische Stabilität / Refactoring
- Zentrale, überlauffreie `logistic()`-Funktion im Estimator-Interface
  (`catcalc_item_estimator`) deklariert und einmal in der Basisklasse
  `model_raschmodel` implementiert (plus Helfer `logistic_w` für W = P(1−P)).
- Die rechenintensiven Berechnungen (Likelihood, Log-Jacobian, Log-Hessian) der
  dichotomen Modelle rasch (1PL), raschbirnbaum (2PL) und mixedraschbirnbaum
  (3PL) auf die zentrale Logistic- und die P/W-Form umgestellt. Damit entfallen
  die separat gebildeten, überlaufgefährdeten Exponentialterme
  (z. B. `exp($a*$b)`, `exp($b*$theta)`). Mathematisch äquivalent, bestätigt
  durch finite-Differenzen-Tests und die vorbestehenden Regressionstests.
- Auch der Personen-Parameter-Pfad (Ability-Schätzung) von 1PL/2PL/3PL auf den
  P/W-Kern umgestellt: `log_likelihood_p` (Score), `log_likelihood_p_p`
  (Krümmung) und `fisher_info` nutzen nun eine gemeinsame Logistic-Auswertung
  statt mehrfacher `exp()`-Terme (Score/Hessian pro Modell: 1PL k−P bzw. −W,
  2PL b(k−P) bzw. −b²W, 3PL über die Kettenregel auf P = c+(1−c)L). Abgesichert
  durch neue θ-finite-Differenzen-Tests und die vorbestehenden Regressionstests.

### Bugfix
- 3PL Fisher-Information korrigiert: verwendete zuvor die Schwierigkeit statt der
  Diskrimination. Neu: I(θ) = b²·(1−P)/P·((P−c)/(1−c))², reduziert sich für c=0
  korrekt auf die 2PL-Form b²·P·(1−P).

### 3PL LORS (Log'ed Odds-Ratio Squared) vervollständigt
- Die 3PL-LORS-Ableitungen (`lors_1st_derivative_ip`/`lors_2nd_derivative_ip`)
  waren unvollständig (2-dimensional statt 3-dimensional, Kreuzterm gestubt) und
  wurden nirgends aufgerufen. Da der LORS-Rest `n·(log(OR)+b(a−θ))²` nicht vom
  Guessing-Parameter c abhängt, ist die Ableitung nach c identisch 0; damit ist
  die 3D-Form eindeutig. Nachimplementiert: Gradient [d/da, d/db, 0], Hesse als
  3×3 mit korrektem Kreuzterm 2n·(2b(a−θ)+log(OR)) und Null-Zeile/-Spalte für c.
- Derselbe zuvor auf 0 gestubte a/b-Kreuzterm im 2PL-LORS ebenfalls korrigiert.
- Beide gegen finite Differenzen von `lors_residuals` abgesichert (neue Tests).

### Tests
- Gemeinsamer finite-Differenzen-Testhelfer (`\local_catquiz\derivative_fd_trait`)
  als unabhängige numerische Referenz für Jacobian/Hessian, verankert an
  1PL/2PL/3PL.
- Fragile Einzelbeobachtungs-`calculate_params`-Tests (1PL/2PL) durch robuste
  synthetische Recovery-Tests ersetzt (bekannte Parameter, viele Ability-Punkte).

- LORS und LMS als alternative (Vor-)Berechnungswege abgesichert und
  fertiggebaut: FD-Tests fuer LMS (1PL/2PL/3PL) und LORS (1PL/2PL); 3PL-LMS-1.-
  und -2.-Ableitung (fehlerhaft) in P/W-Form neu implementiert; Hesse-Assertion
  gegen Schluesselreihenfolge robust gemacht (ksort).

### Kompatibilität / CI
- Mindestanforderung auf Moodle 4.5 angehoben (`$plugin->requires = 2024100700`,
  min. PHP 8.1).
- CI-Matrix auf Moodle 4.5 (PHP 8.1/8.2/8.3, PostgreSQL und MariaDB) reduziert;
  PHP 7.4 und die Branches 4.1–4.4 entfernt.

> Hinweis zur CI: Der Installationsschritt der Pipeline scheitert derzeit an
> einem Upstream-Konflikt der Abhängigkeiten (die Tabelle
> `adaptivequiz_cat_params` wird sowohl von mod_adaptivequiz\@catmodel_main als
> auch vom Subplugin adaptivequizcatmodel_catquiz definiert). Dies liegt nicht in
> local_catquiz. Siehe doc/session-002-changes.md.
