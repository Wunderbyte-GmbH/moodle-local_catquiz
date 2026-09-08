# Session-Start-Prompt

Zum Kopieren an den Anfang einer neuen Sitzung. Er stellt den Kontext her, damit
Claude ohne Rückfragen produktiv weiterarbeiten kann. Bei Bedarf den Abschnitt
„Aktueller Stand / Aufgabe" ersetzen.

---

```text
Projekt: Moodle-Plugin local_catquiz (ALiSe CAT Quiz) – Computer-Adaptive-Testing
mit 7 IRT-Catmodel-Subplugins unter catmodel/ (rasch=1PL, raschbirnbaum=2PL,
mixedraschbirnbaum=3PL, grm, grmgeneralized=GGRM, pcm, pcmgeneralized=GPCM).

Sprache: Deutsch. Bitte durchgehend auf Deutsch antworten.

Arbeitsweise & Pfade:
- QUELLE (Arbeitskopie, wird ausgeliefert): /home/claude/catquiz
- SPIEGEL (Wegwerf-Kopie im Moodle-Baum, gegen die getestet wird):
  /home/claude/moodle/local/catquiz  – nie dort entwickeln.
- Verifikationsumgebung: echtes Moodle 4.5.13, PHP 8.3, PostgreSQL 16,
  PHPUnit 9.6. Aufbau-Details in doc/environment-setup.md.
- Kanonischer Prüf-Ablauf:
    cd /home/claude/moodle; sudo service postgresql start; sleep 2
    rm -rf local/catquiz && cp -a /home/claude/catquiz local/catquiz
    php -d max_input_vars=5000 admin/tool/phpunit/cli/init.php --no-composer-self-update
    vendor/bin/phpunit local/catquiz/catmodel/<modell>/tests/<modell>_test.php
- phpcs: /tmp/moodlecs/vendor/bin/phpcs --standard=moodle --severity=1
    --extensions=php --exclude=PSR1.Classes.ClassDeclaration,moodle.Commenting.TodoComment .
  (Exit 0 = sauber; phpcbf zum Auto-Fix.)

Verbindliche Disziplinen (Details in doc/engineering-guide.md):
- Numerische Ableitungen sättigungssicher halten: stabilize_denominator gegen
  Division-Unterlauf, Max-Shift-Softmax gegen exp-Überlauf, „multiplizieren statt
  dividieren"; endlich bis theta=±800 (Regressionstest derivative_saturation_test).
- Verifikation: FD-Harness für Item-Ableitungen; bei Refactorings Bitgenau-Abgleich
  (Ziel 0.0e+0) gegen den alten Pfad; Sättigungs-Stresstest.
- ZAHN-TEST: jeder Fix/Guard muss rot werden, wenn man ihn zurücknimmt.
- Sensible Trajektorien toleranzbasiert-aggregiert prüfen, nicht punktgepinnt.
- Achtung PHP-Scoping: pro-Iteration-Zustand nie via `use (&$var)` in einer
  schleifenlokal reassignten Variable halten – in eigenen Funktions-Scope kapseln.

Auslieferung:
- $plugin->release bleibt fix (1.1.5); nur $plugin->version je Auslieferung erhöhen.
- Zwei ZIPs, Top-Ordner catquiz/: „-release.zip" via `git archive` (respektiert
  .gitattributes export-ignore: ohne .github/, doc/, Tooling) und „-<version>.zip"
  (volles Arbeitspaket inkl. doc/). Nach /mnt/user-data/outputs kopieren und
  present_files. Abschlussbericht ehrlich, inkl. verbleibender Übergangszustände.
  Ausgelieferte ZIPs dürfen KEIN .git-Verzeichnis enthalten (auch keine
  verschachtelten wie catquizcentralhub/*/.git): Voll-Paket nach `cp -a` mit
  `find <ziel> -type d -name .git -prune -exec rm -rf {} +` bereinigen; Kontrolle
  `unzip -l … | grep -c '/.git/'` = 0. Verschachtelte Fremd-.git auch im Workspace
  löschen.

Abhängigkeiten/CI: mod_adaptivequiz 3.0.0 (ralferlebach, Branch v-3.0) – bündelt
den Adapter adaptivequizcatmodel_catquiz unter catmodel/catquiz, dieser hat
inzwischen ein eigenes Repo MIT eigener CI (dev + main, nur lint-php + phpunit);
local_wunderbyte_table (main), filter_shortcodes (master),
catquizcentralhub_{host,client}. Behat ist blockierend und grün (19 Szenarien);
lokal mangels Chrome nicht lauffähig – neue Szenarien lassen sich nur schreiben,
verifiziert werden sie erst im CI-Lauf.

Cross-Plugin-Achtung: Repo-Stand und die in mod_adaptivequiz gebündelte Kopie des
Adapters können auseinanderlaufen. Gebaut wird aus dem Repo – Befunde also immer
dort verifizieren, nicht an der gebündelten Kopie (Lehre aus session-084).

Moodle-4.5-Hinweis: Webservice-Klassen unter classes/external/ nutzen den
core_external\-Namespace (globale external_api ist in 4.5 entfernt). Bei
Schema-Änderungen (install.xml/upgrade.php) IMMER $plugin->version erhöhen, sonst
wird das Schema nicht neu gebaut.

Dokumentation (doc/): engineering-guide.md (Lehren), environment-setup.md (Setup),
alise-documentation-plan.md (geplante Doks für die Arbeitsstränge A–H),
session-0XX-changes.md (Historie), README.md (Doc-Index inkl. Themenübersicht
065–084), issues/ (Issue-Entwürfe und der DoD-Review-Abgleich zu #5–#9).

Vor jeder Änderung: die relevanten SKILL.md und doc/engineering-guide.md
berücksichtigen. Vor Auslieferung die Checkliste in engineering-guide.md §7.

Aktueller Stand (Ende Aug 2026, Release 1.1.5, interne Version 2026082151):
CI ist grün – local_catquiz (lint, codeanalysis, quality, phpunit, behat) ebenso
wie der Adapter mit seiner neuen eigenen CI. Strang C (#5–#9) ist inhaltlich
abgearbeitet; der Abgleich gegen die DoDs steht in doc/issues/strang-c-dod-review.md.

Bekannte Restpunkte:
- test_given_responses_lead_to_expected_abilities ist markTestIncomplete: Die
  Harness fälscht den Attempt-Record; sobald der CAT endet, stirbt der
  Feedback-Pfad. Braucht eine Neufassung mit echtem Versuch, keinen Fix.
- Zwei Behat-Fälle sind bewusst NICHT abgedeckt (Begründung als Kommentar in der
  jeweiligen Feature-Datei): die Reporting-Checkbox ist per Label nicht
  adressierbar (advcheckbox rendert zwei gleichnamige Inputs) – dafür deckt
  feedback_result_gate_test den Sachverhalt am Gate ab.
- Aufräumarbeit ohne Dringlichkeit: get_exclusion_reason_string() wird noch vom
  Hinweis aus Issue #10 genutzt.

Aufgabe:
<hier den konkreten Auftrag einsetzen; falls unklar, zuerst git-Status/CHANGELOG
und die jüngste session-0XX-changes.md sichten>
```

---

## Hinweise zur Nutzung

- Der Block ist bewusst kompakt: Er verweist auf die Detaildokumente statt sie zu
  duplizieren. Wenn ein Strang aus `alise-documentation-plan.md` bearbeitet wird,
  den passenden Absatz von dort zusätzlich anhängen.
- Die interne `$plugin->version` und der genaue Testzähler-Stand ändern sich je
  Sitzung – nicht in den Prompt hartkodieren, sondern zu Sitzungsbeginn aus
  `version.php` bzw. der jüngsten `session-00X-changes.md` lesen.
- Ist Verhalten seit dem letzten Stand unklar (z. B. neue CI-Logs), zuerst
  reproduzieren und gegen die Referenz prüfen, bevor Änderungen erfolgen.
