# Dokumentationsplan für ALiSe-Arbeitsstränge

Dieses Dokument empfiehlt, **welche weiteren Dokumente** die anstehenden
ALiSe-Arbeitsstränge sinnvoll begleiten – und in welcher Reihenfolge sie
entstehen sollten. Es ist bewusst als Plan gehalten: Es beschreibt Zweck,
Inhalt und Priorität jedes Dokuments, damit sie pro Strang gezielt beauftragt
werden können, statt alles auf einmal zu schreiben.

## Ausgangslage

Drei Stränge mit unterschiedlichen Domänen:

- **A – Numerische Algorithmen** (Branch
  `ralferlebach-implement-numerical-algorithms`): Aufarbeitung des Rechenkerns –
  Schätzung, Ableitungen, Stabilität. Gleiche Domäne wie die bisherige Arbeit an
  den 7 IRT-Modellen.
- **B – Diagramme + Feedback + Statistik** (Issues #10–#16): Ausgabeschicht –
  Lernenden-Feedback, Diagramme, Statistik/Reporting, eigene Attempt-Seite,
  `filter_shortcodes`-Einbindung.
- **C – Abschluss + Ergebnisspeicherung** (Issues #5–#9): Abbruch-/Abschluss­
  logik (Maximalfragen, SE-Schwelle, Zeitüberschreitung, keine Fragen mehr) und
  Persistenz/Export der Ergebnisse (`export_feedback_csv.php`, gespeicherte
  Fähigkeiten/Status je Skala).
- **D – Privacy / Datenschutz** (Label: Privacy): Moodle Privacy API / DSGVO –
  Deklaration, Export und Löschung personenbezogener Daten.
- **E – Performance: CAT Manager & Statistik** (Label: Performance
  Statistic+CAT Manager): Datenbank-/Renderlast von Dashboard und Statistik.
- **F – Performance: Testadministration** (Label: Performance Testadministration):
  Laufzeit von Fragenauswahl und Fähigkeitsschätzung während des Tests.
- **G – Migration zu Moodle 5.x** (Label: Migration zu Moodle 5.x): Anhebung auf
  die Moodle-5.x-Plattform inkl. Abhängigkeiten und CI.
- **H – Zukünftige IRT-Modelle** (Label: zukünftige IRT Modelle): neue Modelle
  jenseits der aktuellen sieben.

Bereits vorhanden: `engineering-guide.md` (wiederverwendbare Lehren),
`session-00X-changes.md` (Historie), `CHANGELOG.md`, `README.md`.

---

## Querschnitts-Dokumente (für alle Stränge zuerst)

Diese vier zahlen auf jeden Strang ein und sollten Vorrang haben.

### Q1 – Architektur- und Domänenkarte (`doc/architecture.md`)  · Priorität: hoch
Eine Landkarte, wie die Teile zusammenspielen: CAT-Scales/Kontexte → Item-Pool
(`model_item_param(_list)`) → Strategie/Engine (`teststrategy`, `catcalc`) →
Antwort-/Personparameter → Feedback/Statistik-Ausgabe → Persistenz/Export.
Für jeden Knoten: verantwortliche Klassen, Ein-/Ausgabe-Datenformen,
Verknüpfung zu mod_adaptivequiz. Ohne diese Karte muss jeder Strang die
Zusammenhänge neu rekonstruieren.

### Q2 – Glossar & Datenmodell (`doc/glossary-datamodel.md`)  · Priorität: hoch
Verbindliche Begriffe (Skala, Kontext, Item-Parameter, Fähigkeit θ,
Standardfehler SE, Fraktion, Trusted Region) und das persistente Datenmodell
(Tabellen, JSON-Payloads, Codec, Roundtrip-Garantien). Bindeglied v. a. für
Strang C, aber überall referenziert.

### Q3 – Verifikations- & CI-Playbook (`doc/verification-playbook.md`) · Prio: mittel
Extrakt aus dem Engineering-Guide als eigenständige, verlinkbare Checkliste:
Testumgebung aufsetzen, Suiten laufen, phpcs, Zahn-Test, Behat-Status,
Auslieferungs-Checkliste. Damit jeder Strang dieselbe Abnahmedisziplin nutzt.

### Q4 – Architektur-Entscheidungen (`doc/adr/NNNN-*.md`)  · Priorität: mittel
Kurze ADRs (Architecture Decision Records, ~1 Seite) für tragende Entscheidungen
mit Kontext/Entscheidung/Konsequenzen. Bereits abgeschlossene, die dokumentiert
gehören: baseline-freier Parameter-Codec; P/W-Form der Ableitungen;
`stabilize_denominator`/Max-Shift-Softmax als Stabilitätskontrakt;
toleranzbasierter Simulationstest; adaptivequiz-Testseiten-Workaround.

---

## Strang A – Numerische Algorithmen

### A1 – Mathematische Referenz (`doc/math-reference.md`)  · Priorität: hoch
Konsolidiert die verstreuten Herleitungen an **einen** Ort: für jedes der 7
Modelle die Wahrscheinlichkeit, Score/Hesse in P/W- bzw. E[K]/Var-Form (Person
und Item), die Stabilitätsumformungen und die Sättigungsgrenzfälle. Plus der
Schätzer: stabilisierter Newton-Raphson, Trusted Region, Startwerte. Diese
Referenz ist die Voraussetzung, um den Branch gefahrlos aufzuarbeiten.

### A2 – Modell-Implementierungskontrakt (`doc/model-contract.md`)  · Prio: hoch
Was ein `catmodel`-Subplugin erfüllen muss: Interfaces
(`catcalc_item_estimator`, `catcalc_ability_estimator`), Codec-Reihenfolge,
`restrict_to_trusted_region`, Ableitungsmethoden inkl. `get_ability_derivatives`,
Settings. Als Schritt-für-Schritt-Anleitung „So fügt man ein neues Modell hinzu"
mit Verweis auf FD-/Sättigungs-/Zahn-Test.

### A3 – Referenz-Validierung (`doc/reference-validation.md`)  · Prio: mittel
Wie gegen externe Referenzen (radCAT/classicCAT-CSVs, ggf. R/`mirt`/`catR`)
validiert wird, warum aggregiert-toleranzbasiert statt punktgepinnt, und wie neue
Referenzläufe erzeugt und eingecheckt werden.

---

## Strang B – Diagramme + Feedback + Statistik (#10–#16)

### B1 – Ausgabe- & Feedback-Architektur (`doc/feedback-architecture.md`) · Prio: hoch
Die Ausgabeschicht im Überblick: `output/attemptfeedback`,
`output/catquizstatistics`, Mustache-Templates, die eigene Attempt-Seite
(vgl. #757) und die `filter_shortcodes`-Einbindung. Für jeden Baustein: welche
Daten er erwartet und welche Darstellung er erzeugt.

### B2 – Feedback-Datenkontrakt (`doc/feedback-data-contract.md`)  · Prio: hoch
Der Vertrag zwischen Rechenkern und Darstellung: welche Größen die Diagramme
speisen (Fähigkeit je Skala, SE, Quantil-/Bereichsgrenzen, Item-Verlauf),
woher sie kommen und in welcher Form. Verhindert, dass Strang B Annahmen über
Strang-A-/C-Interna trifft.

### B3 – Diagramm-/Visualisierungskatalog (`doc/visualization-catalog.md`) · Prio: mittel
Pro Diagrammtyp: Zweck, Eingabedaten, Grenzfälle (fehlende Werte, sehr großes SE,
einzelne Skala vs. Baum), Barrierefreiheit/Lokalisierung. Bildet die #10–#16
konkret auf umzusetzende Artefakte ab.

### B4 – Behat-Teststrategie für Ausgabe (`doc/output-test-strategy.md`) · Prio: mittel
Da diese Features UI-lastig sind: welche Behat-Szenarien die Feedback-/Statistik-
Ansichten absichern, wie sie unabhängig von der adaptivequiz-Integration bleiben,
und wie sie in die (derzeit non-blocking) Behat-Stufe eingegliedert werden.

---

## Strang C – Abschluss + Ergebnisspeicherung (#5–#9)

### C1 – Abschluss- & Abbruchlogik (`doc/completion-logic.md`)  · Priorität: hoch
Alle Endebedingungen an einem Ort und eindeutig: Maximalfragezahl erreicht,
SE-Schwelle unterschritten, Zeit überschritten, keine passenden Fragen mehr,
Mindestfragezahl. Je Bedingung: auslösende Einstellung, resultierender
Ergebnisstatus, Verhalten gegenüber mod_adaptivequiz. (Bekannte Altlast:
Test bricht nicht bei Maximalfragezahl ab – solche Fälle hier als Referenz
festhalten.)

### C2 – Ergebnis- & Persistenzmodell (`doc/result-storage.md`)  · Priorität: hoch
Was genau persistiert wird (Fähigkeit, SE, Status, je Skala; Zeitpunkt; Kontext)
und wo; Beziehung zwischen Attempt, Personparameter und Feedback-Snapshot;
Roundtrip-Garantien (baut auf `persistence_roundtrip_test` auf) und
Migrations-/Upgrade-Pfade. Eng an Q2 gekoppelt.

### C3 – Export-/Berichtsformate (`doc/export-formats.md`)  · Priorität: mittel
Spaltendefinition und Semantik von `export_feedback_csv.php` (inkl. der
Statusspalte „Testresult"), Berechtigungen (`view_users_feedback`), Locale-/
Trennzeichen-Fragen (siehe Komma-Dezimal-Handling im Importer) und die
Konsistenz zwischen angezeigtem Feedback und exportierten Werten.

---

## Strang D – Privacy / Datenschutz (Label: Privacy)

Das Plugin speichert personenbezogene Daten (geschätzte Fähigkeiten, Antworten,
Feedback-Snapshots, Attempt-Zuordnung). Moodles Privacy API verlangt, dass ein
Plugin diese Daten deklariert, exportiert und löschen kann. Ohne Provider ist ein
DSGVO-konformer Produktivbetrieb nicht möglich.

### D1 – Datenschutz-Inventar & Provider-Kontrakt (`doc/privacy.md`) · Prio: hoch
Vollständiges Inventar: welche personenbezogenen Daten in welcher Tabelle/JSON
liegen, und das Mapping auf die Privacy API (`metadata`-Provider,
`export_user_data`, `delete_data_for_user`, `_users_in_context`,
`_all_users_in_context`, `get_contexts_for_userid`/`get_users_in_context`).
Plus Aufbewahrungs-/Löschregeln und die Abgrenzung zu den Daten, die
mod_adaptivequiz selbst hält. Baut direkt auf C2 (Ergebnis-/Persistenzmodell)
und Q2 (Datenmodell) auf – diese müssen zuerst stehen.

### D2 – Privacy-Teststrategie (`doc/privacy-tests.md`) · Prio: mittel
Wie der `provider` gegen Moodles `provider_testcase`-Muster abgesichert wird
(Export- und Löschpfade, Kontextauflösung), damit Datenschutzzusagen belastbar
sind.

## Strang E – Performance: CAT Manager & Statistik (Label: Performance Statistic+CAT Manager)

Der CAT-Manager (`manage_catscales.php`,
`output/catscalemanager/managecatscaledashboard`) und die Statistik-Ansichten
(`output/catquizstatistics`) aggregieren über große Item-Pools und Skalenbäume –
der wunde Punkt sind Datenbank-Last und Renderzeit.

### E1 – Performance-Profil CAT-Manager & Statistik (`doc/perf-manager-statistics.md`) · Prio: hoch
Die heißen Pfade benennen und vermessen: teure Queries/Aggregationen, die Nutzung
von `local_wunderbyte_table` (Server-seitige Sortierung/Filterung/Paginierung),
fehlende Indizes, Caching-Strategie (MUC), Lazy-Loading der Detailansichten.
Je Engpass: Messwert, Ursache, Gegenmaßnahme, Regressionswächter. Eng an
Strang B (Statistik-Ausgabe) und Q2 gekoppelt.

## Strang F – Performance: Testadministration (Label: Performance Testadministration)

Die Laufzeit *während* eines Tests: pro Antwort werden Fragenauswahl und
Fähigkeits-Neuschätzung (stabilisierter Newton) gerechnet. Hier zahlt die
bisherige numerische Arbeit direkt ein.

### F1 – Laufzeit-Budget Testadministration (`doc/perf-testadministration.md`) · Prio: hoch
Kostenmodell je Antwort: Fragenauswahl über den Itempool + Newton-Iterationen ×
Responses; wo PP-Stufe-2 (`get_ability_derivatives`, memoisierte Verdrahtung) und
Caching greifen; Skalierung mit Itempool- und Skalenbaum-Größe; Latenzziele und
Messmethodik (Profiling-Hooks, realistische Lastszenarien). Verweist auf den
Engineering-Guide (§1, §3) und Strang A. Wichtig: Optimierungen nur mit
Bitgenau-/Zahn-Test gegen die bestehende Schätzung.

## Strang G – Migration zu Moodle 5.x (Label: Migration zu Moodle 5.x)

### G1 – Moodle-5.x-Migrationsleitfaden (`doc/moodle5-migration.md`) · Prio: hoch
Konkreter Umstellungsplan: `$plugin->requires` und PHP-Mindestversion anheben;
entfernte/deprecatete Core-APIs prüfen und ersetzen (Output/Renderer, Forms,
DB-Layer, `task`, `string_manager`, Privacy-Signaturen); Kompatibilität der
Abhängigkeiten sicherstellen (adaptivequiz-Fork, `adaptivequizcatmodel_catquiz`,
`local_wunderbyte_table`, `local_shortcodes`); die CI-Matrix um
`MOODLE_500_STABLE` (und ggf. neuere) erweitern und Behat-/Selenium-Änderungen
nachziehen. Gekoppelt an Q3 (Verifikations-/CI-Playbook) und den Abschnitt
Abhängigkeiten & CI im Engineering-Guide.

## Strang H – Zukünftige IRT-Modelle (Label: zukünftige IRT Modelle)

### H1 – Roadmap zukünftige IRT-Modelle (`doc/future-irt-models.md`) · Prio: mittel
Kandidatenmodelle und ihre Einordnung: z. B. 4PL (oberes Asymptoten-/Slipping-
Parameter), Nominal Response Model, mehrdimensionale IRT/Testlet-Modelle. Je
Kandidat: Passung zum Modellkontrakt (A2), nötige Erweiterung der mathematischen
Referenz (A1), Auswirkungen auf die derzeitige Eindimensionalitäts-Annahme und
auf Schätzung/Stabilität, sowie eine Aufwands-/Nutzen-Priorisierung. Dient als
Filter, welche Modelle sich ohne Architekturbruch einfügen und welche nicht.

## Priorisierung & Reihenfolge

1. **Zuerst querschnittlich:** Q1 (Architekturkarte) und Q2 (Glossar/Datenmodell)
   – sie entlasten alle Stränge; Q2 ist zudem Voraussetzung für D (Privacy) und
   C (Ergebnisspeicherung).
2. **Pro Strang die „hoch"-Dokumente**, bevor die Umsetzung beginnt:
   A1+A2, B1+B2, C1+C2, D1, E1, F1, G1.
3. **Zeit-/anlassgetrieben einplanen:**
   - **D (Privacy)** vor jedem Produktivbetrieb mit EU-Nutzerdaten – Compliance-
     Gate, nicht optional. Direkt nach C2/Q2 angehen.
   - **G (Moodle 5.x)** entlang des Moodle-Release-Kalenders; früh die CI-Matrix
     erweitern, damit Regressionen sichtbar werden, bevor 4.5 ausläuft.
   - **E/F (Performance)** sobald reale Datenmengen/Last vorliegen; vorher
     Messpunkte definieren, damit „schneller" belegbar ist.
   - **H (zukünftige Modelle)** roadmap-begleitend; H1 als Filter, bevor ein
     konkretes Modell in A2 umgesetzt wird.
4. **Begleitend** Q3/Q4 sowie die „mittel"-Dokumente, sobald der jeweilige Strang
   in die Umsetzung geht.
5. ADRs (Q4) laufend mitschreiben – jede tragende Entscheidung sofort als ADR,
   nicht rückwirkend.

## Abhängigkeiten zwischen den Strängen (Kurzüberblick)

- **D (Privacy)** ⟵ C2 (Ergebnismodell), Q2 (Datenmodell): das Inventar folgt dem
  Persistenzmodell.
- **E (Perf. Statistik/Manager)** ⟵ B (Statistik-Ausgabe), Q2: optimiert die
  Datenwege der Anzeige.
- **F (Perf. Testadministration)** ⟵ A (Numerik), Engineering-Guide §1/§3:
  optimiert den Schätz-/Auswahl-Hotpath – nur mit Bitgenau-/Zahn-Test.
- **G (Moodle 5.x)** ⟵ Q3 (CI-Playbook), Engineering-Guide §6 (Abhängigkeiten/CI).
- **H (zukünftige Modelle)** ⟵ A1 (math. Referenz), A2 (Modellkontrakt).

## Konventionen pro Dokumenttyp

- **ADR:** Titel, Status, Kontext, Entscheidung, Konsequenzen, Alternativen –
  max. eine Seite, nummeriert, unveränderlich (Änderungen als neuer ADR).
- **Referenz-/Architekturdokumente:** dauerhaft, sitzungsunabhängig; Codeverweise
  über Klassen-/Methodennamen, nicht über Zeilennummern (die veralten).
- **Change-Logs** (`session-00X-changes.md`, `CHANGELOG.md`): bleiben historisch,
  nehmen keine Referenzinhalte auf – dafür sind die Dauer-Dokumente da.
