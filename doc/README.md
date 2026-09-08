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
- **environment-setup.md** – Reproduzierbarer Aufbau der Test-/Laufzeitumgebung
  (Moodle, PHP, PostgreSQL, PHPUnit, Behat, phpcs/moodle-cs, Gherkin/Mustache-Lint)
  inkl. verifizierter Fixpunkte und typischer Stolpersteine.
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
