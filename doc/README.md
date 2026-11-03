# doc/ – Übersicht

Zwei Arten von Dokumenten:

## Dauerhaft (sitzungsübergreifend, vor der Arbeit lesen)

- **decision-attempt-identity.md** – Warum `local_catquiz_attempts` bei
  `UNIQUE(attemptid)` bleibt und kein zusammengesetzter Schlüssel eingeführt wird.
- **engineering-guide.md** – Wiederverwendbare Lehren: numerische Stabilität der
  IRT-Ableitungen, Verifikationsdisziplin (FD-Harness, Bitgenau-Abgleich,
  Sättigungs-Stresstest, Zahn-Test, toleranzbasierte Trajektorientests), die
  PHP-Scoping-Falle im memoisierten Schätzer, Verifikationsumgebung/-befehle,
  Versionierung/Auslieferung, adaptivequiz/CI, Auslieferungs-Checkliste.
- **alise-documentation-plan.md** – Empfohlene weitere Dokumente für die
  ALiSe-Arbeitsstränge (Numerische Algorithmen; Diagramme+Feedback+Statistik;
  Abschluss+Ergebnisspeicherung; Privacy/Datenschutz; Performance CAT-Manager &
  Statistik; Performance Testadministration; Migration zu Moodle 5.x; zukünftige
  IRT-Modelle) inkl. Zweck, Inhalt, Priorität und Abhängigkeiten.
- **result-contract.md** – Wie CATquiz-Ergebnisse über den Adapter ins Gradebook der
  Aktivität kommen (mod_adaptivequiz #14, Adapter #7).
- **attempt-trace.md** – Versuchs-Trace (#136): einschalten, Spans, Datenschutz, Export,
  Messvorgehen für LTI-Starts.
- **strategy-semantics.md** – Wann endet ein Test? Ursachen von #134/#135, Fähigkeitsmatrix der
  Strategien, Stopps je Strategie, Tests und Zahn-Tests.
- **experiment-136-slo.md** – Anleitung zur Messung der Antwortzeiten im Betrieb (#136): Steckbrief,
  Messläufe kalt/warm/parallel, Export, Auswertung.
- **issue-136-instanzcache.md** – Konzept-Issue für 1.3: instanzweiter Cache des Item-Pools mit
  inkrementell gepflegter Pilotzahl, Generationszählern und nächtlichem Heil-Task.
- **id-namespaces.md** – Id-Namensräume: Benennung von Host- und CAT-Versuchs-Id, die
  bewusste Ausnahme `local_catquiz_attempts.attemptid`, zentrale Übersetzung,
  id-geschlüsselte Arrays, Kollisionstests (#107).
- **environment-setup.md** – Reproduzierbarer Aufbau der Test-/Laufzeitumgebung
  (Moodle, PHP, PostgreSQL, PHPUnit, Behat, phpcs/moodle-cs, Gherkin/Mustache-Lint)
  inkl. verifizierter Fixpunkte und typischer Stolpersteine.
- **release-1.2.0-changes.md** – Änderungsbericht vom Wunderbyte-v3-Stand zu
  1.2.0: Testdurchführung, Ergebnispersistenz, Feedback, Performance, Sicherheit.
  PR-tauglich und menschenlesbar; die Einzelheiten stehen in den Session-Doks.
- **session-start-moodle5-migration.md** – Startpunkt für den Strang „Migration
  zu Moodle 5.x": die offenen Issues aus allen drei Repositories in einer
  Reihenfolge, was aus Sitzung 104 bereits einzahlt, und die Werkzeuge dafür.
- **session-start-prompt.md** – Kopierfertiger Prompt für den Start neuer
  Sitzungen (Projekt, Pfade, Prüf-Ablauf, verbindliche Disziplinen, Auslieferung).

## Historisch (Änderungshistorie je Durchgang)

- **session-001-changes.md** – Absicherung der dichotomen CAT-Modelle.
- **session-002-changes.md** – P/W-Refactor, politome Ableitungen, Codec,
  LORS/LMS, Persistenz-Fixes, Sättigungsfestigkeit, PP-Refactor Stufe 2,
  toleranzbasierter Simulationstest, Code-Hygiene, adaptivequiz-Diagnose.
- **session-003-changes.md** – Branch-Integration (aktueller Stand als Basis),
  Dependency-Branches (alise_adaptivequiz + gebündelte Bridge), Moodle-4.5-
  Migration external_api→core_external, Version-Bump-Schema-Rebuild,
  3PL-ratio-Sättigungsfix, strategy_test-Portabilität, Release 1.1.4.
- **session-004…012-changes.md** – Numerik-/Politom-Arbeitsstränge, M1/M2-
  Schätzer-Vergleich (Newton/BFGS/GA→Newton), politome IP-Recovery-Referenz.
- **session-013-changes.md** – CI-Fixes (Behat/qbank-Kontext, phpcs/MatrixException),
  IP-Edge-Case-Experimente (IP-5/IP-10/IP-9, GA eigenständig) inkl. §D-Fixtures
  und Regressionstest.
- **session-014-changes.md** – Issue #44: Scheduled-Nachberechnung erzeugt keine
  neuen Kontexte mehr (sichere Defaults, persistenter Kontext-Load, In-Place-Update).
- **session-015-changes.md** – Issue #43: zentraler Berechnungsdienst mit zwei Modi
- **session-016-changes.md** – Experiment-Konsequenzen K1 (Newton-Gate/BFGS-Rescue) und K2 (NaN-feste GRM-Startschwellen) umgesetzt.
- **session-017-changes.md** – Experiment-Konsequenzen K3 (reduzierte Struktur), K4 (KKT-Gate), K5 (Identifizierbarkeits-Report) umgesetzt.
- **session-018-changes.md** – CI-Fix: K2 zurückgenommen (K3 ist der Wurzelfix), phpdoc + catscale-float-cast.
  (inkrementell/disruptiv), Lock-API, Lauf-Zusammenfassung und CAT-Management-UI.
- **session-019…064-changes.md** – Numerik-, Politom- und Feedback-Arbeitsstränge,
  Diagramme/Feedback/Statistik (Issues #12–#16), Ergebnisspeicherung und
  Abschluss-Logik (Strang C, Issues #5–#9).
- **session-065…084-changes.md** – „CI grün"-Strang, siehe die Themenübersicht
  unten.
- **issues/** – ausformulierte Issue-Entwürfe und Review-Abgleiche:
  `strang-c-dod-review.md` (DoD-Gegenprüfung #5–#9 mit Fundstellen),
  `issue-7-validity-vs-reporting.md`, `backend-invalid-itemparams.md`.
- **experiments-ip-edgecases.md** – strukturierte Zusammenstellung der
  IP-Edge-Case-Experimente (Annahmen, Durchführung, Ergebnisse, Befunde,
  Konsequenzen).

### Themenübersicht der Sitzungen 065–084 (Release 1.1.5)

Der Strang begann als CI-Reparatur und förderte mehrere echte Defekte zutage:

| Thema | Sitzung(en) |
|---|---|
| PHPDoc-CI-Fix, Behat-Erstdiagnose | 065 |
| Feedback-Farben: abgeschnittene Skala-Grenzen (`floatval("1,5")`) | 066 |
| Modell-Vertrag für Itemparameter (alle 7 Modelle), Pilot-Erkennung | 067, 076 |
| Quizverlauf-Tabelle: Breite, Korrektheits-Icon, Modal-Fix | 068 |
| Maximalfragezahl: richtige Zählgröße (beantwortet statt angezeigt) | 069, 070, 074 |
| CSV-Import: Formel-Escaping nullte negative Difficulties | 070 |
| „Question 5": nicht-monotoner Last-Response-Cache | 071 |
| Chart/Legende auf eine Bezugsgröße, Debug-Warnungen im Export | 072 |
| Strang-C-Review gegen die Codebasis, Arbeitsplan | 073 |
| Autoritativer Versuchsabschluss (Cron, keine erfundene Endzeit) | 075 |
| Live-Carryover aus der Versuchshistorie | 077 |
| Feedback-Pfad auf das Ergebnis-DTO, `excluded` aufgetrennt | 078, 081 |
| CAT-Simulationsmatrix reaktiviert (referenzfreie Invarianten) | 079, 080 |
| Behat-Abdeckung #5–#9, Nachbesserung, zwei UI-Sackgassen | 081, 082, 083 |
| Adapter: Report-Callback, Workflows, Tests, toter Code | 084 |
| Rechte im tatsächlichen Kurs-/Modulkontext (#18), Kontext-Resolver | 087 |
| DB-Indizes (#25), NULL-sichere Dubletten-Bereinigung, redundante Indizes | 088 |
| Fragelisten entschlackt, Lazy-Vorschau (#20) | 089 |
| Fragetext-Suche, Detailansicht (#19), CI-Grunt/browserslist | 090 |
| Statistik eingeschränkt (#21), Add-Items ohne GROUP_CONCAT (#22) | 091 |
| Skalenbaum ohne N+1 und quadratischen Aufbau (#24) | 092 |
| Itemparameter-Gültigkeit sichtbar, Pilotitems gelistet (#54) | 093 |
| CI-Grunt entschärft, Playwright eingeführt | 094 |
| Diagrammdaten serverseitig aggregiert (#23) | 095 |
| Review-Befunde: Upgrade-Ausgabe, Kontextauflösung | 096 |
| Behat-Fix, Gruppen im Kurskontext (#18), Playwright | 097 |
| #23 im Browser, Lasttest-Ergebnisse | 098 |
| Aufbewahrung des Fortschritts (#56), Skalierungsbefund | 099 |
| Externes Review: acht Sicherheits- und Korrektheitsbefunde | 100 |
| #54-Reste, #23-Reste, JSON-Aggregations-Issue | 101 |
| Review-Fixes, Aufrufpfad-Prüfung | 102 |
| Arbeitspaket 1.1.7: #62, #59, #29 | 103 |
| #65 Kill-Switch, #64-Diagnose repariert | 103 |
| Kursseiten-TypeError, Docblocks | 102 |

Übergeordnet: `../CHANGELOG.md` (Auslieferungsnotizen unter Release-Label 1.1.6),
`../README.md` (Plugin-Beschreibung).
- `issue-141-testlaenge.md` – Testlänge (Minimum/Maximum), Produktionsbefund 33 Slots, Export, Regrade (#141, mod_adaptivequiz #19).
- `audit-2026-10-14.md` – Abnahmebericht der BUG-Audit-Restarbeiten: AK/DoD je Issue mit Nachweis, Testläufe, offene Punkte.
- `re-audit-2026-10-15.md` – Abarbeitung des Re-Audits: Stand je AK/DoD für mod_adaptivequiz #14 und #136, #142–#145, was erledigt ist und was nur mit Staging-Messung oder 1.3 schließbar ist.
- `statistik-119-132.md` – Lernverlauf als kumulative Reihe (#119), Grundgesamtheit „Ohne Versuch“ (#132), Nachweise.
- `issue-139-frage-modal.md` – Frage-Modal: Zugriffsregeln, Fehlerpfad, STACK, doppelt ausgeführtes JS der lazy Tabs, Nachweise (#139).
- `trusted-region-prior.md` – Streuung null/nahe null/keine Zahl im Trusted-Region-Prior: Regel, Audit, Tests (#116).
- `speicher-und-rechenpfade.md` – Ableitungs-Cache, kompakter Fortschritt, CLASSIC ohne Pool-Fisher-Information: Messungen, Kompatibilität (#143–#145).
- `feedback-limits.md` – Äußere Grenzen der Feedbackbereiche folgen der Globalskala (#86).
- `settings-validation.md` – Settings-Validator: Codes, Budget der Abdeckung, Speicherwege (#60).
- `progress-read-contract.md` – Lesevertrag des Fortschritts, Ergebnisseite ohne Fortschritt (#149).
- `runtime-pool-cache.md` – Geteilter Laufzeit-Pool: Invalidierung (#146), Reihenfolge und Wirkungsanalyse (#147).
- `p0-nachweise-2026-10-18.md` – Nachweise zum Nacharbeitsauftrag 18.10. und Kontroll-Re-Audit 19.10.: Zahn-Tests, volle Suiten (PG/MariaDB, 4.5/5.1), Behat, Langlauf, offene Punkte.
- `issue-150-ability-histogram.md` – Fähigkeitsprofil ohne Testinformation: Ursache, Änderung, Stand je AK/DoD, Prüfhilfe für die Live-Instanz (#150).
- `issue-150-chart-inventory.md` – Inventur aller Plugin-Diagramme auf leere oder ungleich lange Serien, Befunde und Issue-Vorschläge außerhalb #150.
- `issue-150-nachweise.md` – Nachweise zum Re-Audit vom 23.10.2026: Prüfstand mit Commits und Versionen, PHPUnit 4.5/5.1/5.2/5.3 × PostgreSQL/MariaDB, Jest, AMD, Behat, Zahn-Tests, Livecheck-Snippet; Rohdaten in `evidence/issue-150/` (#150).
- `folge-issues-150.md` – Folge-Issues zu #150: #151 Zeitbereiche über Zeitzonen und Sommerzeit, #152 Verlauf alter Feedbackdaten und Fehlerisolierung der Feedbackbereiche, #153 Items nicht installierter Modelle, #154 Jest Bootstrap 5, #155 CI-Skript entfernt; Abgleich mit AK/DoD, Rot vor dem Fix, Läufe; Rohdaten in `evidence/folge-issues-150/`.
- `ci-2026-10-25.md` – CI-Läufe vom 25.10.2026: Ursache je rotem Job (phpdoc, Classic auf Moodle 5.3, Install-Matrix mit falscher Action, Pfaden und `-e`, Abhängigkeiten auf GitHub zu alt), Korrekturen, lokale Nachstellung der Install-Matrix.
- `re-audit-2026-10-26.md` – Re-Audit 26.10.2026: GitHub-SHAs und Actions-Runs je Linie, tatsächlich abgedeckte Matrix, Adapter #9 (CI strikt, Zahn), #150 Browser-Exception und Payload vor/nach dem Fix, Behat-Matrix 4.5–5.3 × PG/MariaDB, Zuordnung #151–#155; Kommentarentwürfe in `re-audit-2026-10-26-kommentare.md`, Rohdaten in `evidence/re-audit-2026-10-26/`.
- `re-audit-2026-10-27.md` – Nacharbeiten Re-Audit 27.10.2026 (local_catquiz P0/P1, Security, Host, Adapter): Stände, Code-Befunde (#106 kein Ergebnis bei Fraction 0/1, #149 Leser schreibt, #95 Einfüge-Wettlauf 1.3), Matrix, Stand je Issue, Grenzen; Kommentarentwürfe in `re-audit-2026-10-27-kommentare.md`, Belege je Issue in `evidence/re-audit-2026-10-27/issue-<nr>-evidence.md`.
- `re-audit-2026-10-28.md` – Nacharbeiten #150 (Auftrag mit Patch vom 28.10.) und Re-Audit-Stand 28.10.: Prüfung des Patches, Herkunft eines Ergebnisses (`resultsstoredsince`), Ergebnisvertrag, Itempool je Kontext (Zeile mit aktivem Parametersatz), Stufenzähler der Testinformation, Lesepfad von `progress::load()`, #156, Matrix, #17 v3; Belege in `evidence/re-audit-2026-10-28/`.
- `issue-157-context-item-pool.md` – #157: Itempool je CAT-Kontext (nur Items des Kontexts, aktiver Parameter des Items, defekte Verweise als Integritätsfehler), laufender Versuch bleibt in seinem Kontext, Aufrufstellen-Audit, Testmatrix T1–T12, Vorher/Nachher- und Audit-CLI, offene Entscheidungen.
