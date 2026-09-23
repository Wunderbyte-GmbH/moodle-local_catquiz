# Setup der Test- & Laufzeitumgebung

Anleitung, um die Verifikationsumgebung für `local_catquiz` von Grund auf
aufzusetzen: Moodle, PHP, PostgreSQL, PHPUnit, Behat, Code-Style (phpcs/moodle-cs)
und Gherkin-/Mustache-Lint. Geschrieben für die Arbeit im Container; die
verifizierten Fixpunkte dieser Umgebung stehen in §0.

> Konvention: **Quelle** = `/home/claude/catquiz` (Arbeitskopie, wird
> ausgeliefert). **Spiegel** = `/home/claude/moodle/local/catquiz` (Wegwerf-Kopie
> im Moodle-Baum, gegen die getestet wird). Nie im Spiegel entwickeln – er wird
> bei jedem Testlauf überschrieben.

---

## 0. Zielzustand (verifizierte Fixpunkte)

| Komponente        | Wert                                                         |
|-------------------|--------------------------------------------------------------|
| Moodle            | 4.5.13, Branch `MOODLE_405_STABLE`, `$branch=405`            |
| PHP               | 8.3.x (CLI, NTS)                                              |
| DB                | PostgreSQL 16.x, `dbname=moodle`, `dbuser=moodle`, `prefix=mdl_` |
| PHPUnit           | 9.6.x (aus Moodle-Core-Composer)                             |
| Moodle-Pfad       | `/home/claude/moodle`                                        |
| dataroot          | `/home/claude/moodledata`                                    |
| phpunit_dataroot  | `/home/claude/moodledata_phpu`, `phpunit_prefix=phpu_`       |
| phpcs / moodle-cs | `/tmp/moodlecs` mit `moodlehq/moodle-cs 3.7.0` (exakt wie CI) |
| moodle-plugin-ci  | v4 unter `/home/claude/ci` (reproduziert die CI-Checks lokal) |
| Abhängigkeiten    | mod_adaptivequiz **3.0.0** (ralferlebach `v-3.0`) + Adapter adaptivequizcatmodel_catquiz `v-3.0`; catquizcentralhub_{host,client}; local_wunderbyte_table (main); filter_shortcodes (master) |
| Locale            | `en_AU.UTF-8` (vom PHPUnit-Init verlangt)                     |
| PHPDoc-Checker    | `local_moodlecheck` im Moodle-Baum (= CI-Job `phpdoc`)        |

---

## 1. Systempakete

```bash
apt-get update
# PHP 8.3 + die von Moodle geforderten Erweiterungen
apt-get install -y php-cli php-pgsql php-xml php-mbstring php-curl \
     php-gd php-zip php-intl php-soap php-ctype php-iconv php-simplexml \
     postgresql postgresql-client git unzip
# Composer (falls nicht vorhanden)
which composer || (php -r "copy('https://getcomposer.org/installer','ci.php');" \
     && php ci.php --install-dir=/usr/local/bin --filename=composer && rm ci.php)
```

Moodle verlangt `max_input_vars >= 5000`. Für `admin/cli/*` genügt das Flag am
Aufruf. **Der PHPUnit-Init prüft jedoch die effektive `php.ini`** und ignoriert
`-d`, weil er die Prüfung in einem Unterprozess ausführt – dort scheitert er mit
„this test must pass - PHP setting max_input_vars must be at least 5000".
Deshalb einmalig dauerhaft setzen:

```bash
PHPINI=$(php -i | grep "Loaded Configuration File" | awk '{print $NF}')
grep -q '^max_input_vars' "$PHPINI" \
  && sed -i 's/^max_input_vars.*/max_input_vars=5000/' "$PHPINI" \
  || echo 'max_input_vars=5000' >> "$PHPINI"
```

Für Einzelaufrufe wird das
Flag bei den CLI-Aufrufen mitgegeben (siehe §6).

Der PHPUnit-Init verlangt zusätzlich das Locale `en_AU.UTF-8`, sonst bricht er mit
„Required locale 'en_AU.UTF-8' is not installed" ab:

```bash
apt-get install -y locales
locale-gen en_AU.UTF-8
```

## 2. PostgreSQL: Start, Rolle, Datenbank

```bash
# Server starten. Achtung: im CLI-Container ist `sudo` häufig NICHT vorhanden.
service postgresql start             # ohne sudo; alternativ: pg_ctlcluster 16 main start
# Rolle/DB einmalig anlegen (als postgres-Systemnutzer, wo verfügbar):
su postgres -c "psql -c \"CREATE ROLE moodle LOGIN PASSWORD 'moodle';\""
su postgres -c "psql -c \"CREATE DATABASE moodle OWNER moodle;\
```

> Der DB-Server läuft nach einem Container-Neustart oft nicht automatisch – vor
> Testläufen immer den Start voranstellen. **`sudo` fehlt im Container häufig** →
> dann `service postgresql start` bzw. `pg_ctlcluster 16 main start` (ohne sudo).

## 3. Moodle beziehen und konfigurieren

```bash
cd /home/claude
git clone --branch MOODLE_405_STABLE --depth 1 \
     https://github.com/moodle/moodle.git moodle
mkdir -p /home/claude/moodledata /home/claude/moodledata_phpu
```

`/home/claude/moodle/config.php` (Minimalkonfiguration, PostgreSQL, inkl. PHPUnit
und optional Behat):

```php
<?php
unset($CFG); global $CFG; $CFG = new stdClass();
$CFG->dbtype='pgsql'; $CFG->dblibrary='native';
$CFG->dbhost='localhost'; $CFG->dbname='moodle';
$CFG->dbuser='moodle'; $CFG->dbpass='moodle'; $CFG->prefix='mdl_';
$CFG->dboptions=['dbpersist'=>0,'dbport'=>5432,'dbsocket'=>''];
$CFG->wwwroot='http://localhost';
$CFG->dataroot='/home/claude/moodledata';
$CFG->admin='admin';
$CFG->directorypermissions=0777;

// PHPUnit.
$CFG->phpunit_prefix='phpu_';
$CFG->phpunit_dataroot='/home/claude/moodledata_phpu';

// Behat (optional, nur für lokale Behat-Läufe – siehe §7).
// $CFG->behat_prefix='beha_';
// $CFG->behat_dataroot='/home/claude/moodledata_behat';
// $CFG->behat_wwwroot='http://localhost:8000';

require_once(__DIR__.'/lib/setup.php');
```

Normal-Installation der DB-Tabellen (für Behat/Integration nötig; PHPUnit nutzt
eine eigene Test-DB, siehe §6):

```bash
cd /home/claude/moodle
php -d max_input_vars=5000 admin/cli/install_database.php \
    --agree-license --fullname="CATQuiz Dev" --shortname="catdev" \
    --adminpass="Admin123!" --adminemail="admin@example.com"
```

## 4. Plugin und Abhängigkeiten einspielen

```bash
cd /home/claude/moodle
# local_catquiz aus der Quelle spiegeln
rm -rf local/catquiz && cp -a /home/claude/catquiz local/catquiz

# Abhängigkeiten. Stand seit Sommer 2026: adaptivequiz + Bridge aus dem v-3.0-Zweig
# (ralferlebach). v-3.0 bündelt die Bridge NICHT automatisch – Adapter separat unter
# mod/adaptivequiz/catmodel/catquiz einspielen (Subplugin-Typ adaptivequizcatmodel).
git clone --depth 1 --branch v-3.0 \
    https://github.com/ralferlebach/moodle-mod_adaptivequiz.git mod/adaptivequiz
git clone --depth 1 --branch v-3.0 \
    https://github.com/ralferlebach/moodle-adaptivequizcatmodel_catquiz.git \
    mod/adaptivequiz/catmodel/catquiz
git clone --depth 1 --branch main \
    https://github.com/Wunderbyte-GmbH/moodle-local_wunderbyte_table.git local/wunderbyte_table
git clone --depth 1 --branch master \
    https://github.com/branchup/moodle-filter_shortcodes.git filter/shortcodes
# catquizcentralhub-Subplugins installieren unter local/catquiz/catquizcentralhub/{host,client}
git clone --depth 1 https://github.com/ralferlebach/moodle-catquizcentralhub_host.git \
    local/catquiz/catquizcentralhub/host
git clone --depth 1 https://github.com/ralferlebach/moodle-catquizcentralhub_client.git \
    local/catquiz/catquizcentralhub/client
rm -rf mod/adaptivequiz/.git mod/adaptivequiz/catmodel/catquiz/.git \
    local/wunderbyte_table/.git filter/shortcodes/.git \
    local/catquiz/catquizcentralhub/host/.git local/catquiz/catquizcentralhub/client/.git

# Upgrade der DB nach dem Einspielen der Plugins
php -d max_input_vars=5000 admin/cli/upgrade.php --non-interactive
```

> **Achtung v-3.0-Generator (ersetzt den alten attemptfeedbackeditor-Workaround):**
> Der mod_adaptivequiz-Test-Generator verlangt beim Erzeugen einer Instanz jetzt
> zwingend `questionpool` bzw. `questionpoolnamed` und liest `attemptfeedbackeditor`
> NICHT mehr. Tests, die adaptivequiz-Instanzen erzeugen, müssen eine Fragenkategorie
> mitgeben – PHPUnit: `create_instance([... 'questionpool' => [$cat->id]])` (Kategorie
> vorher via core_question-Generator anlegen); Behat: Spalte `questionpoolnamed` mit
> einer zuvor über `the following "question categories" exist:` angelegten Kategorie.
>
> **plugininfo-Pflicht:** mod_adaptivequiz muss die Klasse
> `\mod_adaptivequiz\plugininfo\adaptivequizcatmodel` enthalten (Typ-Artefakt des
> Parents). Fehlt sie, bricht `moodle-plugin-ci` die Installation bei der
> Subplugin-Typ-Prüfung mit einer debugging-Meldung ab.

## 5. Code-Style: phpcs / moodle-cs

```bash
export COMPOSER_ALLOW_SUPERUSER=1          # im Container läuft man als root
mkdir -p /tmp/moodlecs && cd /tmp/moodlecs
composer require --dev moodlehq/moodle-cs:^3.7
```

**Ohne Registrierung der Standard-Pfade** meldet phpcs nur
„the 'moodle' coding standard is not installed". Der moodle-Standard baut auf
PHPCSExtra auf, beide Pfade müssen eingetragen werden:

```bash
/tmp/moodlecs/vendor/bin/phpcs --config-set installed_paths \
    /tmp/moodlecs/vendor/phpcsstandards/phpcsextra,/tmp/moodlecs/vendor/moodlehq/moodle-cs/moodle
/tmp/moodlecs/vendor/bin/phpcs -i        # Kontrolle: "… and moodle"
```

Prüfen (Exit 0 = sauber). Die zwei genannten Sniffs werden bewusst ausgeschlossen:

```bash
cd /home/claude/catquiz
/tmp/moodlecs/vendor/bin/phpcs --standard=moodle --severity=1 --extensions=php \
    --exclude=PSR1.Classes.ClassDeclaration,moodle.Commenting.TodoComment .
# Auto-Fix, was maschinell fixbar ist:
/tmp/moodlecs/vendor/bin/phpcbf --standard=moodle --extensions=php .
```

### 5a. PHPDoc-Checker (der CI-Quality-Job)

`moodle-plugin-ci phpdoc` ist `local_moodlecheck`. Lokal reproduzierbar über:

```bash
cd /home/claude/moodle
git clone --depth 1 https://github.com/moodlehq/moodle-local_moodlecheck.git local/moodlecheck
php local/moodlecheck/cli/moodlecheck.php --path=local/catquiz     # 0 <error> = sauber
```

Immer **plugin-weit** laufen lassen, nicht nur über die geänderten Dateien: Das
Einfügen einer Methode verwaist gern den Docblock der folgenden – die häufigste
Ursache für einen roten Quality-Job (Engineering-Guide §4).

## 6. PHPUnit (Unit-/Integrationstests)

Nach **jedem** Neu-Spiegeln des Plugins die PHPUnit-Testumgebung reinitialisieren
(Klassen-/Datenprovider-Registrierung):

```bash
cd /home/claude/moodle
rm -rf local/catquiz && cp -a /home/claude/catquiz local/catquiz
php -d max_input_vars=5000 admin/tool/phpunit/cli/init.php --no-composer-self-update
```

Einzelne Suite / gesamtes Plugin:

```bash
XDEBUG_MODE=off vendor/bin/phpunit --no-coverage \
    local/catquiz/catmodel/rasch/tests/rasch_test.php
XDEBUG_MODE=off vendor/bin/phpunit --no-coverage \
    --filter test_ability_can_be_calculated_with_all_models \
    local/catquiz/tests/catcalc_test.php
```

> **Schwerer Datensatz:** Die 3PL-Personabilitäts-Schätzung ist rechenintensiv
> und kann das Container-Timeout überschreiten. Isoliert per `--filter` laufen.
> **Wegwerf-CLI-Skripte** (FD-/Sättigungs-Harness) gehören in den *Spiegel*
> (`local/catquiz/cli/`), nicht in die Quelle – sie werden vom `cp -a`
> überschrieben und müssen danach neu angelegt werden.
> **`XDEBUG_MODE=off ... --no-coverage`** spart Zeit/Speicher. Mehrere Testdateien
> in EINEM Aufruf zählt Moodles PHPUnit-Runner oft nur als eine – im Zweifel
> einzeln laufen.

## 7. Behat (Akzeptanztests)

Behat benötigt zusätzlich einen Browser-Treiber (Chrome/Chromedriver oder
Selenium) und die `behat_*`-Konfiguration aus §3. Im reinen CLI-Container ist ein
lokaler Behat-Lauf i. d. R. **nicht** praktikabel – dort wird Behat über
`moodle-plugin-ci` in der CI ausgeführt. Setup, falls ein Browser verfügbar ist:

```bash
cd /home/claude/moodle
# behat_* in config.php aktivieren (siehe §3), dann:
php -d max_input_vars=5000 admin/tool/behat/cli/init.php
# In einem Terminal den Testserver, in einem zweiten Chromedriver starten, dann:
vendor/bin/behat --config /home/claude/moodledata_behat/behatrun/behat/behat.yml \
    --tags @local_catquiz
```

> Aktueller CI-Stand (Aug 2026): Behat ist **blockierend** und grün – 19 Szenarien.
> Der frühere `continue-on-error`-Zustand (attemptfeedbackeditor-Dependency-Bug)
> ist überholt. Lokal ist Behat mangels Chrome/Selenium weiterhin nicht lauffähig;
> neue Szenarien lassen sich nur schreiben, nicht ausführen – die Verifikation
> liefert erst der CI-Lauf.

## 8. Lint für Gherkin, Mustache, PHP (wie in der CI)

Am einfachsten über `moodle-plugin-ci`, das die CI ebenfalls nutzt:

```bash
cd /home/claude
composer create-project -n --no-dev --prefer-dist moodlehq/moodle-plugin-ci ci ^4
export PATH="$PATH:/home/claude/ci/bin:/home/claude/ci/vendor/bin"

cd /home/claude/moodle
moodle-plugin-ci phplint  local/catquiz     # PHP-Syntax
moodle-plugin-ci phpcs    local/catquiz     # Code-Style (Moodle-Standard)
moodle-plugin-ci mustache local/catquiz     # Mustache-Templates
moodle-plugin-ci grunt    local/catquiz     # JS/CSS Build-Konsistenz
moodle-plugin-ci phpunit  local/catquiz
moodle-plugin-ci behat    local/catquiz     # nur mit Browser-Treiber
```

Die **blockierenden** CI-Schritte (fail = roter Lauf) sind `codechecker`, `phpdoc`,
`phpunit`, `behat`. `phpcpd` und `phpmd` sind bewusst `continue-on-error`
(non-blocking) – ihre Meldungen (z. B. Duplikate in Riesen-Fixtures, „zu lange
Methoden") kippen den Lauf nicht. `codechecker` läuft mit `--max-warnings 0`, d. h.
JEDE Warnung blockt: u. a. `moodle.Files.LangFilesOrdering` (lang-Strings müssen
alphabetisch sortiert sein) und `moodle.PHPUnit.TestCaseCovers.Missing` (jede
Testmethode braucht `@covers`).

> **LangFilesOrdering lokal reproduzieren:** Der Sniff läuft nur im Moodle-Baum ab
> Version 404 – ohne Baum-Kontext greift er nicht. Direkt erzwingen und
> auto-sortieren (phpcbf) mit `--runtime-set moodleBranch 405`:
> ```bash
> /tmp/moodlecs/vendor/bin/phpcbf --standard=moodle --warning-severity=1 \
>     --runtime-set moodleBranch 405 \
>     lang/en/local_catquiz.php lang/de/local_catquiz.php
> ```

Gherkin-Dateien (`tests/behat/*.feature`) werden von `moodle-plugin-ci behat`
bzw. dem Behat-Runner geparst; reine Syntaxprüfung ohne Ausführung via
`vendor/bin/behat --dry-run`.

## 9. Der kanonische Spiegel-und-Prüf-Ablauf

Ein Durchlauf, der die häufigste Schleife abbildet:

```bash
cd /home/claude/moodle
service postgresql start; sleep 2
rm -rf local/catquiz && cp -a /home/claude/catquiz local/catquiz
php -d max_input_vars=5000 admin/tool/phpunit/cli/init.php --no-composer-self-update
vendor/bin/phpunit local/catquiz/catmodel/<modell>/tests/<modell>_test.php
```

## 10. Typische Stolpersteine

- **DB down:** Server-Start vergessen → Verbindungsfehler. Im Container läuft man
  als **root**, `sudo` ist meist gar nicht installiert → `service postgresql start`
  bzw. `pg_ctlcluster 16 main start`; DB-Rolle über `su postgres -c "psql …"`.
  Nach jeder Container-Pause erneut starten.
- **PHPUnit nicht reinitialisiert** nach `cp -a` → „No tests executed" oder
  veraltete Datenprovider.
- **`max_input_vars`** fehlt → PHPUnit-Init bricht mit Moodle-Umgebungscheck ab.
- **CLI-Harness weg:** Wegwerf-Skripte im Spiegel werden vom `cp -a` gelöscht.
- **Verschachtelte `.git`-Verzeichnisse:** `cp -a` kopiert auch Fremd-`.git` (z. B.
  `catquizcentralhub/client/.git`, `.../host/.git`) in den Spiegel und ins volle
  Arbeitspaket-ZIP – das bringt das Git auf der Empfängerseite durcheinander.
  Solche verschachtelten Repos im Workspace löschen und das Voll-Paket nach
  `cp -a` mit `find <ziel> -type d -name .git -prune -exec rm -rf {} +` von **allen**
  `.git` befreien (nicht nur dem obersten). Kontrolle: `unzip -l … | grep -c
  '/\.git/'` = 0. (Siehe engineering-guide §5/§7.)
- **Docblock-Falle:** Neue Methode vor einer privaten Methode eingefügt →
  verwaister Docblock → phpcs „Missing docblock". Nach Einfügungen phpcs laufen.
- **Benannte vs. `#N`-Datensätze:** Manche PHPUnit-Datenprovider hier sind
  *benannt* – `--filter '#2'` matcht dann nichts; nach Provider-Schlüssel filtern.
  (Der 3PL-Estimator-Provider ist numerisch indiziert: `#2` = 3PL.)
- **Schema nicht angewandt:** Wird `db/install.xml`/`db/upgrade.php` geändert, aber
  `$plugin->version` nicht erhöht, baut Moodle das (Test-)Schema nicht neu – neue
  Tabellen fehlen (z. B. `local_catquiz_qhashmap does not exist`). Immer Version
  erhöhen und PHPUnit reinitialisieren.
- **Neue Testdateien unter `cli/`** werden von PHPUnit nicht entdeckt – Ad-hoc-
  Tests unter `local/catquiz/tests/` ablegen und reinitialisieren.
- **adaptivequiz-Generator (v-3.0):** `create_instance` ohne `questionpool`/
  `questionpoolnamed` → `coding_exception`. Kategorie mitgeben; `attemptfeedbackeditor`
  ist obsolet.
- **codechecker `--max-warnings 0`:** unsortierte lang-Strings
  (`moodle.Files.LangFilesOrdering`) und fehlende `@covers`
  (`moodle.PHPUnit.TestCaseCovers.Missing`) blocken den Lauf, obwohl es „nur"
  Warnungen sind. lang-Dateien mit `phpcbf --runtime-set moodleBranch 405` sortieren.
- **LangFilesOrdering triggert lokal nicht** ohne Moodle-Baum/Version → im Spiegel
  laufen oder `--runtime-set moodleBranch 405` setzen.
- **plugininfo fehlt:** ohne `\mod_adaptivequiz\plugininfo\adaptivequizcatmodel`
  bricht die Plugin-Installation in der CI ab (Subplugin-Typ-Prüfung).
- **Locale `en_AU.UTF-8` fehlt** → PHPUnit-Init bricht ab („Required locale … is
  not installed"). `locale-gen en_AU.UTF-8` (Paket `locales`).
- **phpcs ohne `installed_paths`** → „the 'moodle' coding standard is not
  installed". PHPCSExtra **und** moodle-cs eintragen (siehe §5).
- **Lange Läufe sprengen das Tool-Timeout:** Die CAT-Simulationsmatrix
  (`strategy_test`) braucht ~8–10 Minuten. Im Hintergrund starten und pollen
  (`nohup … > /tmp/x.log 2>&1 &`, dann `sleep`/`tail`), statt sie im Vordergrund
  laufen zu lassen.
- **Ein ZIP löscht nichts.** Wird eine Datei aus dem Paket entfernt, verschwindet
  sie beim Entpacken über einen vorhandenen Checkout **nicht**. Löschungen
  brauchen eine explizite Anweisung (`git rm …`) neben dem Paket – sonst meldet
  z. B. der Autoload-Wächter des Adapters die längst „entfernten" Klassen erneut.
- **CRLF verfälscht Auswertungen:** Dateien mit Windows-Zeilenenden liefern bei
  `grep`/`preg_match` ein abschließendes `\r` mit. Ein daraus gebauter
  Klassenname schlägt bei `class_exists()` fehl – das sah in dieser Sitzung nach
  einem Autoload-Fehler aus, wo keiner war. Vor solchen Prüfungen `trim()` bzw.
  `tr -d '\r'` verwenden.
