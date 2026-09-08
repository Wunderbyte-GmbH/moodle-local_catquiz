# Session 005 – M2-dichotom, erster Durchgang (Branch implement-numerical-algorithms, Release 1.1.4)

Historischer Änderungsbericht. Planung in `arbeitspaket-numerische-methoden-plan.md`
(Phase M2-dichotom). Dieser Durchgang legt die IP-Schätzung für BFGS/GA offen und
misst erstmals Newton vs. BFGS vs. GA→Newton auf der dichotomen Item-Kalibrierung.

## 1. `catcalc::build_itemparam_objective()` ergänzt

Das Item-Estimator-Interface (`catcalc_item_estimator`) exponiert nur Gradient
(`get_log_jacobian`) und Hessian (`get_log_hessian`), **keine skalare
Zielfunktion**. BFGS und Gradient Ascent brauchen aber die Zielfunktion. Neu:
`build_itemparam_objective($itemresponse, $model)` – Gegenstück zu
`build_itemparam_jacobian`, bildet Σ `log_likelihood($ability_r, $ip, $k_r)` über
die Responses (arrow-fn captured `$r` by value – dasselbe bewährte Muster wie im
Jacobian-Builder, keine Scoping-Falle). Nutzt die vorhandene skalare
`log_likelihood(pp, ip, k)` aus `catcalc_ability_estimator`, die item-tauglich ist
(ability + response fix, `ip` variabel). Rein additiv; Newton-Pfad unverändert.

**FD-Konsistenz** (2PL, fester Datensatz): der numerische Gradient der Zielfunktion
stimmt mit dem analytischen `get_log_jacobian` überein, max |diff| = 5.1e-9 → die
Zielfunktion ist die korrekte Stammfunktion des von Newton/BFGS genutzten Gradienten.

## 2. Vergleichs-Harness (Spiegel, wegwerf: `cli/ip_recovery_compare.php`)

Deterministische dichotome Recovery-Daten (Seed 12345, N=800 Fähigkeiten gleich-
verteilt auf [-3.5, 3.5], bekannte Item-Parameter als Ground Truth), dann Schätzung
zurück via Newton (Baseline, wie `estimate_item_params`), BFGS (Vollschätzer) und
GA→Newton (Warm-Start). Gemessen: Recovery-Fehler (max |geschätzt − wahr|) und
Kosten (Objective-/Gradienten-Auswertungen, Zeit). Auswertungszähler über
umhüllende Closures. Der Harness liegt bewusst nur im Spiegel, nicht im Plugin.

## 3. Messergebnis (Moodle 4.5.13, PHP 8.3.6, PostgreSQL 16, seed 12345, N=800)

| Modell | Schätzer     | Recovery-Fehler | Gradienten-Ausw. | (Obj-Ausw.) |
|--------|--------------|-----------------|------------------|-------------|
| 1PL    | Newton       | 0.0649          | 4                | –           |
| 1PL    | BFGS         | 0.0649          | 10               | 10          |
| 1PL    | GA→Newton    | 0.0649          | 35               | 33          |
| 2PL    | Newton       | 0.0745          | 7                | –           |
| 2PL    | BFGS         | 0.0745          | 20               | 20          |
| 2PL    | GA→Newton    | 0.0745          | 33               | 31          |
| 3PL    | Newton       | 0.2770          | 6                | –           |
| 3PL    | BFGS         | 0.2770          | 32               | 32          |
| 3PL    | GA→Newton    | 0.2770          | 290              | 288         |

**Kernbefunde:**
- Alle drei Schätzer konvergieren je Modell zum **identischen Optimum** (gleiche
  Recovery-Werte). Zugleich impliziter Konsistenznachweis: BFGS folgt der
  Zielfunktion mit dem Jacobian und landet, wo Newtons Gradient = 0 ist.
- **Newton ist auf dichotomem IP durchweg am günstigsten** (≤3-dim). BFGS kostet
  2–5× mehr Auswertungen, GA→Newton deutlich mehr (3PL: 290 vs. 6). Kein
  Genauigkeitsgewinn durch BFGS/GA hier.
- Bestätigt die Hypothese/Erwartung: der **High-Dim-Gewinn von BFGS kommt erst
  politom** (mehrere Schwellen), nach dem Merge. Der pre-merge-Durchgang
  M2-dichotom validiert damit Verdrahtung, Korrektheit und Recovery-Referenz.
- Der 3PL-Recovery-Fehler (0.277) ist ein **Identifizierbarkeits-/Datenthema**
  (Guessing bei gleichverteilten Fähigkeiten schwach bestimmt: difficulty 0.42 vs.
  0.70, guessing 0.14 vs. 0.20), kein Schätzerproblem – alle drei stimmen überein.

## 4. Offener Zustand / nächste Schritte

- **Checked-in Recovery-Test** (self-contained, deterministisch, ohne externe CSV)
  als dauerhafte Referenz – ersetzt die entfernten 1PL/2PL/3PL-Fixtures elegant.
  Achtung: unter Test-DB liefert `get_config` für die Discrimination-Bounds ggf.
  Defaults/null; `restrict_to_trusted_region`-Verhalten dort prüfen bzw. Settings
  im Test setzen, damit der Test nicht flaky wird. Toleranzen aus den obigen
  gemessenen Fehlern ableiten (mit Sicherheitsabstand).
- Produktive Verdrahtung von BFGS/GA in `estimate_item_params` (Auswahl per
  Experiment-Flag) – erst wenn ein Nutzen erkennbar ist; nach obiger Messung für
  dichotom **nicht** angezeigt (Newton bleibt Default).
- PP-Oracle-Fixture (`SimulationSteps radCAT …csv`) weiterhin fehlend – relevant
  für M1 (nach dem Merge).

## 5. Versionierung

`$plugin->release` bleibt **1.1.4**; interne `$plugin->version` von `2026081700`
auf **`2026081701`** erhöht.
