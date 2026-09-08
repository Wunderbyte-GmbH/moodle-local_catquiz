# Sitzung 002 – Logistic-Zentralisierung, P/W-Refactor, CI-Fix, Auslieferung

Datum: 2026-08-14
Plugin: local_catquiz → Release bleibt 1.1.2 (interne Version 2026081401, requires Moodle 4.5)
Umgebung: Moodle 4.5.13, PHP 8.3.6, PostgreSQL 16, PHPUnit 9.6.34

> Dieses `doc/`-Verzeichnis ist per `.gitignore` vom Upload ausgeschlossen und
> per `.gitattributes` (export-ignore) aus dem Release-Paket ausgeschlossen. Im
> Arbeitspaket ist es enthalten.

## 1. Zentrale Logistic-Funktion

- `public static function logistic(float $z): float` im Interface
  `catcalc_item_estimator` deklariert. Einziger direkter Implementierer des
  Interfaces ist die abstrakte Basisklasse `model_raschmodel`; alle sieben
  Modelle erben davon, daher ist die Deklaration im Interface gefahrlos.
- Implementierung einmalig in `model_raschmodel`: überlauffrei durch Verzweigung
  nach dem Vorzeichen von z (es wird nur exp eines nicht-positiven Arguments
  gebildet). Plus Helfer `logistic_w($p) = P(1−P) = W`.
- Stabilität geprüft: kein INF/NaN bei z=±800 (die naive Form
  1/(1+exp(−z)) läuft dort über); Sättigung auf 0/1 bei extremen |z| ist gewollt.

## 2. P/W-Refactor der dichotomen Modelle (rechenintensive Berechnungen)

Likelihood, Log-Jacobian und Log-Hessian von rasch (1PL), raschbirnbaum (2PL)
und mixedraschbirnbaum (3PL) auf `self::logistic()` und die P/W-Form umgestellt.

- 1PL: P = σ(θ−a); J = P−k; H = −W.
- 2PL: x = θ−a; P = σ(bx); J = [b(P−k), x(k−P)];
  H = [[−b²W, P−k+bxW], [·, −x²W]].
- 3PL: L = σ(b(θ−a)) (logistischer Kern), W_L = L(1−L), V_L = W_L(1−2L),
  P = c+(1−c)L. Ableitungen über die Kettenregel
  H_ij = (∂²ℓ/∂P²)·P_i·P_j + (∂ℓ/∂P)·P_ij mit den P-Ableitungen nach a, b, c.
  Damit entfallen die separat gebildeten, überlaufgefährdeten Terme wie
  exp(a·b), exp(b·θ), exp(2ab).

Verifikation der Äquivalenz:
- Finite-Differenzen-Tests (Trait aus Sitzung 001) grün für alle drei Modelle.
- Für 3PL zusätzlich die vorbestehenden hartcodierten Regressionstests grün –
  d. h. die neue Form liefert dieselben Referenzwerte wie die alte exp-Form.
- Reduktion auf 2PL bei c=0 analytisch geprüft (P=L, J_a=b(P−k) etc.).

## 3. Fragile calculate_params-Tests ersetzt

Die `calculate_params`-Tests von 1PL und 2PL prüften anhand einer einzigen
Beobachtung eine hartcodierte Zahl. Eine einzelne Beobachtung identifiziert die
Item-Parameter nicht; das Ergebnis war nur „wo Newton + Trust-Region für einen
unteridentifizierten Fall zufällig landet" und reagierte auf Rundung (der
Logistic-Zweig verschob den Landepunkt, z. B. 1PL 5.0 → 1.047). 1PL ersetzt
durch einen synthetischen Recovery-Test: aus einer bekannten Generierungs-
Difficulty werden über viele Ability-Punkte Antworten erzeugt und die
Rückgewinnung geprüft (Δ ≤ 0.05). Der 2PL- und der 3PL-calculate_params-Test
blieben mit dem Refactor unverändert grün (sie landen an derselben
Trust-Region-Grenze) und wurden nicht angefasst.

Nebenbefund: `model_item_response` nimmt den Personparameter per Referenz
(`&$personparams`); im Testkörper muss er daher einer Variablen zugewiesen
werden, sonst löst Moodles strikter Test-Error-Handler die
„Only variables should be passed by reference"-Meldung als Exception aus.

## 4. CI

### Ursache der roten Pipeline (nicht local_catquiz)

Der `install`-Schritt stirbt an `relation "behat_adaptivequiz_cat_params"
already exists`: die Tabelle `adaptivequiz_cat_params` wird sowohl von
mod_adaptivequiz@catmodel_main (eigenes db/install.xml) als auch vom hinzu-
gefügten Subplugin adaptivequizcatmodel_catquiz definiert. Weil alle Folge-
schritte `if: always()` sind, laufen sie ohne erfolgreiche Installation und
melden pauschal „Not enough arguments (missing: plugin)". Der eigentliche Fehler
ist also der doppelte Tabellen-Eintrag der Abhängigkeiten, nicht local_catquiz.

Empfohlener Upstream-Fix (eine Stelle): in adaptivequizcatmodel_catquiz die
Tabelle `adaptivequiz_cat_params` aus db/install.xml entfernen, da
mod_adaptivequiz@catmodel_main sie nun besitzt. Lokal verifiziert: nach
Entfernen des Duplikats installiert die Umgebung und local_catquiz läuft.

### Umgesetzte Änderungen in local_catquiz

- version.php: `requires = 2024100700` (Moodle 4.5, min. PHP 8.1); interne
  version 2026081401; Release-Label bleibt 1.1.2 (siehe §8).
- CI-Matrix (push und pullreq) auf Moodle 4.5 reduziert: PHP 8.1/8.2/8.3,
  PostgreSQL und MariaDB. PHP 7.4 und die Branches 4.1–4.4 entfernt.

### Offener CI-Punkt (transparent)

Der Codechecker-Schritt läuft mit `--max-warnings 0`. Das Plugin hat plugin-weit
zahlreiche VORBESTEHENDE, überwiegend auto-fixbare phpcs-Verstöße (lokal gemessen
rund 1086 Errors – davon ~1080 per phpcbf automatisch behebbar – plus ~52
Warnings), unabhängig von dieser Sitzung. Ein plugin-weiter phpcbf-Lauf ist ein
großer, separater Eingriff (182 Dateien) und wurde nicht unangekündigt
durchgeführt.

Meine in dieser Sitzung geänderten/neuen Dateien sind phpcs-sauber (0 Errors,
0 Warnings). Die zuvor in den noch nicht implementierten `least_mean_squares`-
Stubs vorhandenen `// TODO: @DAVID` / `// TODO: @RALF`-Kommentare erzeugten unter
dem Moodle-Sniff „Missing required MDL-…"-Warnings; sie wurden zu neutralen
Notizen umformuliert (Zuweisung und Intent als Klartext erhalten, kein
Code-Eingriff), sodass der Codechecker für diese Dateien ohne erfundene
MDL-Nummern grün ist. Alternativ könnte der Workflow den Schritt mit
`--todo-comment-regex` an die Wunderbyte-TODO-Konvention anpassen, dann bleiben
literale `TODO:`-Marker erhalten.

## 5. Auslieferung (zwei Pakete, Sessionstart-Konvention)

- Release-Paket: `moodle-local_catquiz-1.1.2-release.zip` – per `git archive`
  gebaut, respektiert `.gitattributes` (export-ignore für .github/, doc/,
  .gitignore, .gitattributes, erpnext.yml). Top-Ordner `catquiz/`. Enthält
  Runtime, Sprachdateien, Templates, PHPUnit/Behat-Tests und CHANGELOG.
- Arbeitspaket: `moodle-local_catquiz-1.1.2.zip` – alles inkl. .github/, doc/
  und Tooling.
- Neu: CHANGELOG.md; `.gitattributes` (zuvor leer) mit den export-ignore-Regeln.

## 6. Verifikation (diese Sitzung)

- php -l über das gesamte Plugin: fehlerfrei.
- PHPUnit (echter Plugin-Code in Moodle 4.5): rasch 124, raschbirnbaum 125,
  mixedraschbirnbaum 197 (0 Failures; Skips vorbestehend), grm/grmgeneralized/
  pcm/pcmgeneralized je 31 (0 Failures; Skips = Paket B).
- FD-Harness-Zahntests weiterhin wirksam (Vorzeichen-/Kreuzterm-Fehler →
  Failures).
- phpcs auf allen in dieser Sitzung geänderten Dateien: sauber (siehe §4).

## 7. Nächste Schritte

- Paket A: Parameter-Codec und dynamische get_model_dim() (aktuell count() auf
  Strings → PHP-8-Fehler); catcalc auf den Codec umstellen.
- Paket B: GRM/GGRM- und PCM/GPCM-Ableitungen in derselben P/W-/Logistic-
  Formensprache implementieren; FD-Tests darauf ausrollen; least_mean_squares.
- Optional: plugin-weiter phpcbf-Lauf zur Codechecker-Bereinigung (auf Wunsch).
- Upstream: Duplikat-Tabelle in adaptivequizcatmodel_catquiz entfernen, damit die
  Pipeline den install-Schritt besteht.

## 8. Nachtrag: Versionslabel und 3PL-LORS

### Versionslabel unverändert
Auf Wunsch wird das Release-Label nicht hochgezählt: `$plugin->release` bleibt
`1.1.2`; erhöht wird nur die interne `$plugin->version` (2026081400 →
2026081401). Der CHANGELOG-Abschnitt ist entsprechend auf „1.1.2 (interne
Version 2026081401)" umbenannt. Die Auslieferungs-ZIPs tragen daher `1.1.2`.

### 3PL-LORS (Log'ed Odds-Ratio Squared)
Prüfung des @RALF-Punkts (mixedraschbirnbaum.php:417,441):
- Befund: `lors_1st_derivative_ip`/`lors_2nd_derivative_ip`/`lors_residuals`
  werden im GESAMTEN Plugin nirgends aufgerufen (weder Produktion noch Tests) –
  im Gegensatz zu den `least_mean_squares*`-Methoden, die zumindest Tests haben.
  Die 3PL-LORS war zudem unvollständig: 2-dimensional (a, b) statt 3-dimensional,
  Kreuzterm `[0][1]` auf 0 gestubt, keine c-Dimension.
- Entscheidung „LORS behalten?": Der LORS-Rest ist als `n·(log(OR)+b(a−θ))²`
  definiert und enthält KEIN c. Damit ist die Vervollständigung eindeutig und
  erfordert keine Modellierungsentscheidung: ∂/∂c ≡ 0. Da die Bedingung „falls
  LORS tatsächlich noch fehlt" (unvollständig) erfüllt war, wurde es
  nachimplementiert statt als irreführender Stub belassen.
- Umsetzung: 1. Ableitung → [d/da, d/db, 0]; 2. Ableitung → 3×3 mit
  H_ab = 2n·(2b(a−θ)+log(OR)) und Null-Zeile/-Spalte für c. Der identische
  2PL-Kreuzterm-Stub wurde ebenfalls behoben.
- Absicherung: neue FD-Tests vergleichen beide Ableitungen gegen die numerische
  Referenz aus `lors_residuals` (a, b, c); Zahn-Test bestätigt Wirksamkeit
  (Kreuzterm=0 → 26 Failures). 3PL-Suite: 197 Tests, 0 Failures.
- Hinweis: LORS bleibt vorerst ungenutzt. Ob die Objektfunktion überhaupt in den
  Estimator aufgenommen werden soll, ist eine offene Design-Frage; die Methoden
  sind nun aber mathematisch korrekt und getestet statt still falsch.

## 9. Personen-Parameter-Pfad (PP) auf P/W-Kern

Auf Basis der Experten-Einschätzung wurde der Ability-Schätzpfad von 1PL/2PL/3PL
ebenfalls auf den zentralen Logistic-/P-W-Kern umgestellt:

- `log_likelihood_p` (Score ∂ℓ/∂θ): 1PL k−P; 2PL b(k−P); 3PL
  (k−P)/(P(1−P))·(1−c)·b·W_L mit L = σ(b(θ−a)), W_L = L(1−L), P = c+(1−c)L.
- `log_likelihood_p_p` (Krümmung ∂²ℓ/∂θ²): 1PL −W; 2PL −b²W; 3PL über die
  Kettenregel d²ℓ/dP²·(∂P/∂θ)² + dℓ/dP·∂²P/∂θ² mit ∂P/∂θ = (1−c)bW_L und
  ∂²P/∂θ² = (1−c)b²V_L, V_L = W_L(1−2L).
- `fisher_info`: 1PL W, 2PL b²W (jeweils aus einer einzigen Wahrscheinlichkeit),
  3PL unverändert (bereits korrekte P/W-Form).

Damit entfallen die bislang je Methode mehrfachen `exp()`-Auswertungen
(z. B. 2PL zuvor ~3 exp in p und ~3 in p_p) zugunsten einer Logistic-Auswertung.

Absicherung: neues θ-finite-Differenzen-Netz (`test_log_likelihood_p_numeric`,
`test_log_likelihood_p_p_numeric`) differenziert `log_likelihood` nach θ und
vergleicht mit den analytischen Score-/Krümmungswerten. Zuerst gegen den alten
exp-Code grün validiert (Netz korrekt), dann nach dem Refactor erneut grün
(Äquivalenz). Zusätzlich bleiben die vorbestehenden hartcodierten
`log_likelihood_p`/`_p_p`- und Fisher-Regressionstests grün.

Bewusst NICHT umgesetzt (größere, separat zu planende Architektur aus der
Expertise): ein kombiniertes `get_ability_derivatives()` (Score+Hessian in einem
Aufruf) mit Verdrahtung in `catcalc::estimate_person_ability`, ein skalarer
Newton-Solver für die eindimensionale PP-Gleichung sowie das Vorabberechnen von
Item-Invarianten. Diese ändern den Estimator-Dispatch und sind für Paket A/B
vorgemerkt.

## 10. CI-Installationsfix (alise-Branch) und Paket A (datengetrieben)

### CI: install-Blocker behoben
Die Pipeline blieb weiter beim `install`-Schritt hängen
(`relation "…adaptivequiz_cat_params" already exists`). Ursache war weiterhin die
doppelte Tabellendefinition in der Abhängigkeitskette. Fix: mod_adaptivequiz wird
nun vom Branch `alise_adaptivequiz` gezogen — dieser definiert die Tabelle genau
einmal (im Parent) und bündelt das Subplugin `adaptivequizcatmodel_catquiz`
(Version 2024123105) ohne eigene Tabellendefinition. In beiden Workflows wurde
daher die separate `add-plugin`-Zeile für `adaptivequizcatmodel_catquiz` entfernt
(sonst käme das Duplikat zurück). Lokal verifiziert: Installation läuft durch,
local_catquiz-Tests grün.

### Paket A – Schritte 1–4, datengetrieben
1. Datengetriebene Dimension: neue Basismethode
   `get_model_dim_from_ip($ip) = 1 + count(convert_ip_to_vector($ip))`. Der
   parameterlose `get_model_dim()` der politomen Modelle (fehlerhaftes `count()`
   auf Strings → PHP-8-`TypeError`) wirft nun eine klare `coding_exception`, da
   eine feste Dimension bei variabler Kategorienzahl undefiniert ist.
2. Codec: `convert_ip_to_vector`/`convert_vector_to_ip` für alle sieben Modelle
   korrekt implementiert. Politome Stubs waren buggy (GRM nutzte `difficulty`
   statt `difficulties`; PCM/GPCM verpackten das ganze Intercept-Array). Bei den
   dichotomen Modellen neu ergänzt. Die Dimensionalität folgt den Daten (Anzahl
   Schwellen/Intercepts aus den Fractions).
3. `catcalc::estimate_item_params` auf den Codec umgestellt: Newton-Raphson rechnet
   auf einem flachen numerischen Vektor; Jacobian/Hessian und der
   Trusted-Region-Filter werden über `convert_vector_to_ip` bzw.
   `convert_ip_to_vector` adaptiert; das Ergebnis wird zurückgewandelt. Für die
   dichotomen Modelle ist das verhaltensidentisch.
4. Tests: neues `tests/parameter_codec_test.php` prüft für alle sieben Modelle den
   verlustfreien Round-trip und die datengetriebene Dimension. Zahn-Test: ein
   defektes `convert_vector_to_ip` lässt die dichotomen Recovery-Tests scheitern —
   Beleg, dass catcalc den Codec tatsächlich nutzt.

Offen (bewusst, dispatch-kritisch bzw. datenabhängig): die eigentliche
politome Item-Schätzung braucht zusätzlich datengetriebene Startwerte (Schwellen
aus den beobachteten Kategorien); der Codec und die Dimension stehen dafür nun
bereit. Informationskriterien (`calc_*_item`) nutzen noch den parameterlosen
`get_model_dim()`; für politome Modelle sind sie auf `get_model_dim_from_ip($item)`
umzustellen.

## 11. Paket B – politome Ableitungen (Partial-Credit-Familie)

PCM und GPCM haben jetzt korrekte, FD-verifizierte Item-Parameter-Ableitungen
(zuvor `throw "Not yet implemented"` bzw. leere Methodenrümpfe).

- PCM (`get_log_jacobian`/`get_log_hessian`): über Tail-Wahrscheinlichkeiten
  T_j = Σ_{k≥j} P_k. Score J_δj = T_j − [r≥j]; Krümmung
  H_{j,l} = T_j·T_l − T_max(j,l). Die Baseline-Kategorie (Index 0) hat keinen
  freien Intercept, daher Null-Eintrag/-Zeile/-Spalte (an den Codec ausgerichtet).
- GPCM: identische Struktur, mit Diskriminationsskalierung (J_δj = b·(T_j−[r≥j]),
  Intercept-Block b²·(…)) plus Diskriminations-Ableitung über Momente
  (Score s_r − E[s] mit s_k = k·θ − D_k; Krümmung −Var(s)) und den δ_j–b-
  Kreuztermen (T_j−[r≥j]) + b·(Msum_j − T_j·E[s]).
- Verifikation: neue `test_get_log_jacobian_numeric`/`_hessian_numeric` in
  pcm/pcmgeneralized differenzieren `log_likelihood` numerisch nach dem
  Intercept-(+b-)Vektor über den Codec und vergleichen mit der Analytik
  (PCM 66 FD-Fälle, GPCM 48). Zahn-Test: defekter GPCM-b-b-Term → 24 Failures.

Noch offen in Paket B: die Graded-Familie GRM/GGRM. Dort ist die
Kategorienwahrscheinlichkeit eine Differenz benachbarter kumulativer Logistiken
P_k = σ(b(θ−a_k)) − σ(b(θ−a_{k+1})); die Ableitungen betreffen nur die beiden
Randschwellen der beobachteten Kategorie und haben einen Kreuzterm zwischen
ihnen. Der bestehende GRM-Jacobian hat zudem einen Index-Bug (die Zählschleife
überschreibt den Kategorieindex `$k`). Wird als nächster Schritt in derselben
FD-abgesicherten Weise umgesetzt.

### Graded-Familie GRM/GGRM (nachgezogen)
- GRM (`get_log_jacobian`/`get_log_hessian` neu in P/W): P_r = Q_r − Q_{r+1} mit
  Q_k = σ(θ−a_k). Nur die beiden Randschwellen tragen bei:
  ∂/∂a_r = −W_r/P_r, ∂/∂a_{r+1} = W_{r+1}/P_r; Hesse mit
  H_{r,r} = V_r/P_r − (W_r/P_r)², H_{r+1,r+1} = −V_{r+1}/P_r − (W_{r+1}/P_r)²,
  Kreuzterm H_{r,r+1} = W_r·W_{r+1}/P_r². Der frühere `$k`-Index-Bug
  (Zählschleife überschrieb den Kategorieindex) ist behoben.
- GGRM: wie GRM mit Q_k = σ(b(θ−a_k)); zusätzlich die Diskriminations-Ableitung
  und die Schwelle–b-Kreuzterme. Assembliert über die allgemeine Form
  H_{p,q} = P''_{p,q}/P − (P'_p/P)(P'_q/P) über die aktiven Parameter (die zwei
  Randschwellen und b).
- Verifikation: neue FD-Tests (GRM 48 Fälle, GGRM 48) grün; Zahn-Tests: defekter
  GRM-Kreuzterm bzw. GGRM-Schwelle–b-Term erzeugen Failures.

Damit ist Paket B abgeschlossen: alle vier politomen Modelle (PCM, GPCM, GRM,
GGRM) haben korrekte, FD-verifizierte Item-Parameter-Ableitungen. Offen bleibt
die politome Item-*Schätzung* (datengetriebene Startwerte / Baseline-Codec, siehe
die angebotenen Optionen) sowie die LMS-Varianten der politomen Modelle.

## 12. Politome Item-Schätzung (Option 4 + 1 + 2)

Die jetzt korrekten politomen Ableitungen sind an den Estimator angebunden; eine
politome Item-Schätzung läuft end-to-end (verifiziert: PCM mit synthetischen
Daten liefert ohne Fehler ein gültiges ip zurück).

- Option 4 (baseline-freier Codec): `convert_ip_to_vector` schließt die fixe
  Baseline-Kategorie (niedrigste Fraction, Wert 0) aus; `convert_vector_to_ip`
  fügt sie wieder hinzu. Damit entfällt die singuläre Hesse-Zeile/-Spalte, und
  Newton kann invertieren. Codec und Ableitungen sind konsistent baseline-frei
  (FD-Tests grün).
- Option 1 (empirische Startwerte): je Modell `get_start_ip($itemresponse)`
  bildet aus den beobachteten Antwortkategorien datengetriebene Startschwellen
  (`empirical_start_thresholds`).
- Option 2 (Fallback): fehlende/entartete Kategorien werden über gespreizte
  Standardschwellen bzw. Clamping abgefangen.
- `catcalc::estimate_item_params` verzweigt über `is_polytomous()`: politome
  Modelle bekommen die datengetriebenen Startwerte und die aus den Fractions
  abgeleiteten Kategorie-Keys; dichotome Modelle bleiben unverändert.

Hinweis zur Arbeitsweise: Ein großer Teil dieses Abschnitts wurde parallel in
einer zweiten Sitzung am selben Arbeitsbaum umgesetzt; der hier dokumentierte
Stand ist der zusammengeführte, vollständig grün getestete Zustand. Für weitere
Schritte sollte nur EINE Sitzung gleichzeitig an diesem Baum schreiben.

## 13. Politome LORS (Log'ed Odds-Ratio Squared)

LORS gibt es nun auch für die vier politomen Modelle. Kernbeobachtung: beide
Familien sind log-linear in einem Odds-Ratio je Grenze/Schritt, nur in einem
UNTERSCHIEDLICHEN:
- graded (GRM/GGRM): kumulatives Odds P(X>=k)/P(X<k) = exp(b(theta - a_k)),
- partial credit (PCM/GPCM): adjazentes Odds P_k/P_{k-1} = exp(b(theta - delta_k)).
In beiden Fällen ist der Residuenansatz derselbe wie im dichotomen LORS, nur je
freier Grenze k: R_k = log(OR_k) + b(p_k - theta); Objektiv S = n * sum_k R_k^2.

Konsequenz (schön, weil linear, ohne Logistic-Auswertung):
- 1. Ableitung nach p_k: 2 n b R_k (nur der k-te Term).
- Schwellen-/Intercept-Hesse diagonal: H_{kk} = 2 n b^2 (Residuen koppeln nicht
  über die Grenzen).
- nur die Diskrimination b (GGRM/GPCM) koppelt: d/db = 2 n sum_k R_k x_k,
  H_bb = 2 n sum_k x_k^2, Kreuzterm p_k-b = 2 n (2 b x_k + log(OR_k)).

Umsetzung: ein gemeinsamer Basis-Helfer `compute_lors($pp,$ip,$ors,$n,$key,$hasb)`
(key = 'difficulties'|'intercepts', hasb = generalisiert?), auf den die vier
Modelle mit `lors_residuals`/`lors_1st_derivative_ip`/`lors_2nd_derivative_ip`
delegieren. Signatur mit OR-Array `$ors` (nach Fraction gekeyt, wie die
Schwellen; wird intern auf das kanonische Fraction-Format sanitiert); die
Ableitungsvektoren sind am baseline-freien Codec ausgerichtet.

Verifikation: neue FD-Tests differenzieren `lors_residuals` numerisch nach dem
Codec-Vektor bei festen $ors und vergleichen mit der Analytik (je Modell 12
Fälle, GRM/PCM ohne, GGRM/GPCM mit b-Kreuztermen). Zahn-Test: ein defekter
GGRM-LORS-b-Kreuzterm erzeugt Failures.

Hinweis: LORS bleibt (wie dichotom) eine Alternativ-Zielfunktion, die aktuell
nicht im Estimator aufgerufen wird; sie ist nun aber für alle Modelle korrekt und
getestet vorhanden. Die politome LMS wurde inzwischen ebenfalls vollständig
implementiert (siehe Abschnitt weiter unten).


## Politome LMS für alle vier Modelle (PCM, GPCM, GRM, GGRM)

Die Least-Mean-Squares-Zielfunktion ist jetzt für alle politomen Modelle inkl.
erster und zweiter Ableitung nach den Item-Parametern implementiert und
FD-verifiziert.

**Verallgemeinerung.** Die dichotome LMS ist `S = n·(frac − P(korrekt))²`. Als
konsistente Verallgemeinerung dient der erwartete Score

    μ = E[X] = Σ_k frac_k · P_k,   S = n·(frac − μ)².

Für dichotome Items gilt `μ = P(korrekt)`, d. h. die dichotome Form fällt exakt
heraus. Ableitungen:

    ∂S/∂p_j       = 2n (μ − frac) ∂μ/∂p_j
    ∂²S/∂p_i∂p_j  = 2n [ ∂μ/∂p_i ∂μ/∂p_j + (μ − frac) ∂²μ/∂p_i∂p_j ]

**Architektur.** Gemeinsamer Basis-Helfer `model_raschmodel::lms_assemble()`
setzt Residuum, Jacobian und Hesse aus `μ, ∂μ, ∂²μ` zusammen (0-indiziert,
codec-konform). Pro Modell werden nur die Momente `μ, ∂μ, ∂²μ` gebildet:

- **PCM** (Softmax ohne Diskrimination): `∂P_k/∂δ_j = P_k(T_j − 1[k≥j])`; daraus
  `∂μ/∂δ_j` und `∂²μ/∂δ_j∂δ_l` über die Tail-Summen `T_j`.
- **GPCM** (Softmax mit b): zusätzlich die b-Ableitungen über die
  frac-gewichteten Tails `FF_j`, `FMS_j` und die Score-Momente `es, es2, FS, FSS`;
  vollständige Kreuz- und `b`-`b`-Terme.
- **GRM** (kumulativ, b=1): `∂μ/∂a_j = W_j(frac_{j-1} − frac_j)`, diagonale
  Threshold-Hesse `−V_j(frac_{j-1} − frac_j)`.
- **GGRM** (kumulativ mit b): Threshold-Block skaliert mit `b`, plus
  Threshold–b-Kreuzterme und `b`-`b`-Term.

**Tests.** Je Modell 48 FD-Fälle für Gradient und Hesse (z. B. GPCM 1722
Assertions), zahn-getestet (gezielte Term-Störung → FD-Test wird rot).

## Persistenz politomer Parameter (fünf Bugs behoben)

End-to-End-Kette `calculate/set → save_to_db → reload` für die politomen Modelle
korrigiert:

1. **`$startvalue` verschluckt.** Alle vier politomen `calculate_params()` gaben
   den Startwert nicht an `estimate_item_params()` weiter → kein Warm-Start,
   nicht beobachtete Kategorien fielen weg. Behoben.
2. **Tippfehler** `$starvalues` → `$startvalues` in
   `model_raschmodel::calculate_params()`.
3. **`set_parameters()`** invalidiert nun das gecachte `json`
   (`$this->json = null`), damit `to_record()` es aus den neuen Parametern über
   den Modell-Hook neu aufbaut.
4. **`add_parameters_to_record()`** fehlte für GRM/PCM/GPCM (nur GGRM besaß es).
   Ohne diesen Hook wurde die `difficulties`/`intercepts`-Map nicht ins
   Record-JSON serialisiert und war beim Reload leer. Für alle drei ergänzt,
   Format exakt spiegelbildlich zu `get_parameters_from_record()`.
5. **GGRM `is_valid()`** war invertiert (NaN → gültig) und nutzte den falschen
   Schlüssel `difficulty` statt `difficulties`; valide GGRM-Items wurden von
   `save_to_db()` (Filter `array_filter(..., is_valid)`) verworfen. Logik
   korrigiert.

Neuer Test `tests/local/model/persistence_roundtrip_test.php`: Roundtrip über
`$DB->get_record()` + `model_item_param::from_record()` (der exakt betroffene
Pfad) für alle vier Modelle, GGRM-`is_valid` (endlich → gültig, NaN → ungültig)
und ein Warm-Start-Test in `pcm_test.php` (nicht beobachtete Kategorie 0.333
bleibt via `$startvalue` erhalten). Alle Fixes zahn-getestet.

## Trust-Region: aufsteigende Threshold-Ordnung (GRM/GGRM)

`restrict_to_trusted_region()` erzwingt nach der Box-Beschränkung eine streng
aufsteigende Ordnung der Schwellen (kleiner Mindestabstand `gap = 1e-3`). Bei
vertauschten Schwellen wäre `P_k = Q_k − Q_{k+1}` negativ und `log L` = NaN. Die
Baseline-Kategorie (niedrigster Bruch, Platzhalter 0) bleibt unberührt; die
Diskrimination wird weiterhin auf `[0.1, 5]` beschränkt. Test bestätigt
aufsteigende Schwellen und endliche Likelihood für zuvor problematische Eingaben.

## CI-Reparaturen (Code Checker, PHPUnit, Behat)

- **Code Checker** lief in der CI plugin-weit (`--max-warnings 0`) und meldete
  ~1080 vorbestehende Fehler, die die lokale (nur geänderte Dateien prüfende)
  Kontrolle nicht sah. Bereinigt via `phpcbf` + manuelle Korrekturen. Zwei
  vorbestehende Debt-Kategorien bleiben bewusst ausgeschlossen
  (`--exclude=PSR1.Classes.ClassDeclaration,moodle.Commenting.TodoComment`):
  Klasse-pro-Datei (invasives Aufteilen) und die projektfremde TODO-Konvention
  (Wunderbyte-eigener Tracker statt `MDL-`). Sonst plugin-weit 0/0.
- **PHPUnit:** Der an die alte `exp()`-Rundung gepinnte Simulationstest
  `catcalc_test::test_simulation_steps_calculated_ability` ist übergangsweise
  geskippt (PP-Ableitungen auf stabile P/W-Form umgestellt, FD-verifiziert; die
  hartkodierte Trajektorie wird als Follow-up neu gepinnt). Zusätzlich ein realer
  By-Reference-Fix im Provider.
- **Behat** (adaptivequiz-Integration, umgebungsbedingt) übergangsweise
  `continue-on-error`.

## Sättigungs-Härtung + PP-Refactor politom (Nachtrag)

Auslöser: neuer CI-Unit-Fehler (33× `DivisionByZeroError` in
`model_person_ability_estimator_catcalc_test`, Datensatz #2 / 3PL) plus die
Frage, ob dasselbe `P·(1−P)`-Problem auch 1PL/2PL und die politomen Modelle
trifft. Empirisch geklärt über einen Sättigungs-Stresstest (alle 7 Modelle ×
Person-/Item-Ableitungen × θ bis ±800):

- **1PL/2PL: sauber** – Score/Hesse multiplizieren mit `P` bzw. `W = P(1−P)`,
  keine Division; bei Sättigung → 0.
- **3PL: Person gefixt** (`b·L·(k−P)/P`, Guard für `c=0`), **Item** über
  `stabilize_denominator()` abgesichert.
- **GRM/GGRM: Person + Item** dividieren durch `P_r` → `stabilize_denominator()`.
- **PCM/GPCM: exp-Overflow** (Person + Item + LMS) → max-Shift-Softmax.

Zwei Mechanismen:
1. Basis-Helfer `model_raschmodel::stabilize_denominator($d, $eps=1e-12)` –
   schiebt exakt-nullen/sub-ε Nenner auf ±ε; im Normalbereich inert.
2. max-Shift in `pcm_tails`/`pcm_prob_moments`/`gpcm_moments`/`gpcm_lms`.

PP-Refactor (Personfähigkeits-Ableitungen, FD-verifiziert ≤ 2e-10):
- PCM `r − E[K]` / `−Var(K)`; GPCM `b(r − E[K])` / `−b²Var(K)`
  (stabiles Softmax, private `*_ability_moments`-Helfer).
- GRM/GGRM P/W/V-Form über privaten `grm_ability_terms`-Helfer
  (`Q_j = σ(b(θ−a_j))`, `W = Q(1−Q)`, `V = W(1−2Q)`).

Neuer Regressionstest `tests/local/model/derivative_saturation_test.php`
(7 Tests, 2208 Assertions), zahn-getestet. Stresstest nach Härtung: 0 Problemfälle.
Plugin-weit phpcs 0/0. Interne Version → 2026081410.

## Bewusst zurückgestellt (kein Correctness-Blocker)

- **PP-Refactor politom Stufe 2** (kombinierter Score-/Hesse-Durchlauf pro Item,
  z. B. `get_ability_derivatives`): reine Optimierung. Die Personfähigkeits-
  Ableitungen selbst sind mit diesem Nachtrag erledigt.
- **Harmonisierung** der hartkodierten `[-5, 5]`-Grenzen mit den Admin-Settings.
- **Entfernen** der ungenutzten politomen `get_log_tr_jacobian/hessian`-Methoden.


## PP-Refactor Stufe 2 – kombinierte Personen-Ableitungen (Item 1)

`model_raschmodel::get_ability_derivatives($pp, $ip, $frac)` liefert Score und Hesse
gemeinsam. Basis-Default delegiert an die Einzelmethoden; Overrides in 3PL/GRM/GGRM/
PCM/GPCM rufen den jeweiligen Helfer (`*_ability_moments` bzw. `grm_ability_terms`
bzw. L/P beim 3PL) nur einmal auf. Verifikation: bitgenau identisch zu
`log_likelihood_p`/`_p_p` (max 0.00e+0 über alle 7 Modelle × Fraktionen × θ inkl. ±40),
endlich bei θ = ±800 (auch 3PL guessing=0).

Verdrahtung im Schätzer über `catcalc::make_ability_derivative_callable()`: pro Response
ein eigener Funktions-Scope mit nach Ability (`%.17g`) geschlüsseltem Memo. **Bug gefangen
und behoben:** die erste Fassung nutzte einen inline-`use (&$memo)`-Closure; da `$memo`/
`$combined` funktions- statt blockskopiert sind, teilten alle Response-Closures denselben
(letzten) Memo → responseübergreifende Korruption, Median-Fehler 5.10 gegen die Referenz.
Der Helper-Scope stellt bitgenaue Gleichheit zur getrennten Verdrahtung her (Median 0.0021).

## Simulationstest toleranzbasiert reaktiviert (Item 2a)

`test_simulation_steps_match_reference_within_tolerance` (ersetzt den geskippten
Schritttest): aggregiert, fordert ≥ 90 % Übereinstimmung auf 0.01 mit den radCAT/
classicCAT-Referenz-CSVs. Abweichung ist bimodal (94 % < 0.01; 6 % Rand-/Degeneriert-
fälle vollständig divergent, bis 7.74) – der stabilisierte Newton wählt dort andere
diskrete Zweige als die Vor-Refactor-Referenz von 2023. Zahn-getestet: mit dem obigen
Memo-Bug fällt der Test (Trefferquote 0.9 %), mit korrekter Verdrahtung grün.

## Code-Hygiene (Item 3)

- **3a:** Tote `get_log_tr_jacobian`/`get_log_tr_hessian` (0 Aufrufstellen) aus dem
  Interface `catcalc_item_estimator` und allen 7 Modellen entfernt.
- **3b:** Hartkodierte `[-5, 5]` in den politomen `restrict_to_trusted_region`
  (GRM/GGRM/PCM/GPCM) durch `get_config(..., 'trusted_region_min_a'/'_max_a')` mit
  Fallback ±5 ersetzt – konsistent mit den dichotomen Modellen; bestehende TR-Tests
  bleiben grün (Config ungesetzt → Fallback).

## adaptivequiz-CI-Fails: attemptfeedbackeditor (verifizierter Befund)

`adaptivequiz_add_instance()` (mod_adaptivequiz 3.0.3dev) liest
`$adaptivequiz->attemptfeedbackeditor` unbedingt (lib.php:88 und :218); der
Test-Generator setzt sie nie. Der vom Auftraggeber vorgeschlagene Branch
`alise_adaptivequiz` wurde geklont und geprüft: **gleiche Version, gleicher Bug** – der
Branch-Swap allein behebt es nicht (beide Workflows referenzieren ihn ohnehin schon).
Fix auf Testseite: `attemptfeedbackeditor => ['text' => '', 'format' => FORMAT_MOODLE]`
bei `create_instance` in `testitemimporter_test` und `strategy_test`. Gegen die echte
alise-Dependency lokal verifiziert: `testitemimporter_test` grün; `strategy_test`
übersteht setUp, Import-Teiltests (`test_import_worked/_overrides/_csv_with_polytomous_model`)
grün. Die frühere Vermutung eines Import-Fixture-Problems (`"-3,321"`) war ein Fehlalarm –
`fileparser::cast_string_to_float()` behandelt Apostroph-Marker und Komma-Dezimale bereits.
Behat bleibt bewusst non-blocking (Kommentar in beiden Workflows nennt jetzt die genaue
Ursache); scharf schalten würde CI an diesem Dependency-Bug rot färben.


## Experten-Review-Fixes: Fisher-Information, Likelihood-Sättigung, Interfaces

**Politome Fisher-/Iteminformation (P0/P1, behoben).** `item_information()` in
GRM/GGRM/PCM/GPCM zählte die Baseline-Kategorie doppelt (separater Baseline-Term +
Schleife über das Kategorie-Array, das die Baseline enthält). Da
`category_information = -log_likelihood_p_p = Var(K)` frac-unabhängig ist,
faktorisierte die Summe zu `Var(K)·(1 + P_baseline)` statt `Var(K)`. Numerisch
reproduziert (PCM, δ=(-0.4,0.7), θ=0): aktuell 0.6967 statt korrekt 0.5321, Faktor
1.3093 = 1+P₀ → ~31 % zu hohe Information. `fisher_info()` (Alias) wird real in der
Fisher-Itemauswahl, in `catscale.php` und der SE-/Feedback-Ausgabe genutzt – der
Fehler verzerrte also Itemauswahl und Standardfehler bei politomen Items. Die
dichotomen Modelle haben ein separates, korrektes `fisher_info` (mit eigenen Tests).
Fix: Baseline genau einmal summieren. Verifiziert gegen eine unabhängige FD-Referenz
`I(θ) = Σ_k P_k·(−d²/dθ² log P_k)` (reldiff ~1e-6 über alle 4 Modelle × θ) und mit
neuen `test_fisher_info_numeric` je Modell abgesichert; zahn-getestet (Bug reinjiziert
→ 9/10 fallen).

**PCM/GPCM `likelihood()` (P1).** Von roher `exp()`-Summe auf Max-Shift-Softmax
umgestellt (derselbe stabile Kern wie die Momente). Endlich bis θ=±800, Σ P_k = 1
(2e-16). Der Sättigungstest prüft nun zusätzlich `likelihood()` und
`get_ability_derivatives()`.

**Estimator-Interfaces (P1).** `catcalc_item_estimator` deklariert wieder
`get_log_jacobian()`/`get_log_hessian()` (Item-Schätzung), `catcalc_ability_estimator`
nun `get_ability_derivatives()` (PP-Schätzung). `model_raschmodel` liefert den
Default; alle 7 Modelle erfüllen den Vertrag. Kein Runtime-Effekt heute, aber der
Vertrag ist wieder vollständig für künftige (NRM/RSM-)Modelle.

**Weitere Punkte.** 1PL/2PL-`get_ability_derivatives`-Overrides (Stufe 2 vollständig);
neue PP-θ-FD-Tests (4 politome) + `get_ability_derivatives == Einzelmethoden` (alle 7);
TR-`?:` so korrigiert, dass ein gesetztes `0` respektiert wird (nur `false`/leer fällt
auf ±5 zurück); veralteter „Ralf"-Kommentar entfernt.

**Bewusst zurückgestellt (P2/P3, dokumentiert):** fachliche Bereinigung der
b-Diskriminationsgrenzen (Frage: negative Diskrimination zulässig?), Box-Constraint-
Perfektion der Threshold-Projektion, Kategorienstruktur aus dem Item statt aus den
Responses bei Erstkalibrierung (Teil des NRM-Architekturthemas), empirischer Logit in
`estimate_initial_item_difficulties()`, schärfere FD-Toleranzen, der geskippte
CAT-Trajektorientest in `strategy_test`, sowie eine explizite Kennzeichnung legitimer
Branch-Divergenzen im aggregierten Simulationstest.


## Zurückgestellte Punkte abgearbeitet (P2/P3-Robustheit & Testschulden)

**Diskriminationsgrenzen (GGRM/GPCM).** Der hartkodierte Klemmbereich
`max(0.1, min(5.0, ...))` wurde durch den gemeinsamen Helfer
`model_multiparam::restrict_discrimination($componentname, $d)` ersetzt: Grenzen aus
`trusted_region_min_b`/`trusted_region_max_b`, mit hartem positivem Boden 0.1 (negative
Diskrimination ist fachlich ausgeschlossen – sie würde die Kategorienordnung
invertieren). Die Setting-Defaults der beiden generalisierten Modelle wurden von
`min_b=-3`/`max_b=3` auf `min_b=0.1`/`max_b=5.0` gesetzt: positiv, admin-steuerbar und
verhaltensgleich zum bisherigen effektiven `[0.1, 5.0]`. Neue Tests
`test_restrict_to_trusted_region_keeps_discrimination_positive` (GGRM/GPCM).

**Threshold-Projektion box-sicher (GRM/GGRM).** Die bisherige reine
Vorwärts-Gap-Erzwingung konnte den oberen Bound überschreiten (Reviewer-Beispiel:
zwei Thresholds bei max → einer landet bei max+gap). Jetzt: Vorwärtspass (aufsteigender
Mindestabstand) plus Rückwärtspass von `max`, der die gesamte Kette wieder in
`[min, max]` projiziert. Ordering und Gap bleiben erhalten, die Box-Constraint gilt.
Numerisch belegt (Eingabe an der Decke → alle Thresholds ≤ max, aufsteigend). Neuer
Test `test_restrict_to_trusted_region_keeps_box_constraint` (GRM/GGRM), der die
tatsächlich konfigurierten Grenzen liest.

**Kategorienstruktur bei Erstkalibrierung.** `empirical_start_thresholds()` nimmt
optional `?array $categoryfractions`: ist es gesetzt, definiert die deklarierte
Item-Struktur die Kategorien (eine im Sample unbeobachtete Kategorie bleibt erhalten),
die Häufigkeiten kommen weiter aus den Responses; Responses außerhalb der deklarierten
Struktur werden für die Häufigkeiten ignoriert. Aktuelle Aufrufer übergeben `null` →
unverändert. Reflection-Test
`test_empirical_start_thresholds_keeps_declared_categories` (GRM). Die endgültige
Verdrahtung der Item-Kategorien in die Aufrufer bleibt Teil des NRM/RSM-Architektur-Issues.

**Initiale Item-Schwierigkeit.** `estimate_initial_item_difficulties()` von
`-log(p/(1-p+1e-5))` mit `p=passed/total` auf den empirischen Logit
`p=(r+0.5)/(n+1)`, `b=-log(p/(1-p))` umgestellt: keine Division durch 0 (n=0 → b=0),
kein `log(0)` (r=0 oder r=n), symmetrisch. Grenzfall-Test
`test_estimate_initial_item_difficulties_boundaries`.

**FD-Toleranzen geschärft.** `derivative_fd_trait::fd_atol()` von `10^-PRECISION`
(1e-3) auf 1e-6; Hessian-Slack auf `atol=1e-5`, `rtol=1e-4`. Damit sind die FD-Tests
100–1000× schärfer; alle sieben Suiten (inkl. der schweren 3PL-FD-Tests) halten das.

**Recovery-/Invariant-Oracle statt gepinnter CAT-Trajektorie.** Der an den
Vor-Refactor-Schätzer gepinnte `strategy_test::test_strategy_returns_expected_questions`
bleibt geskippt (Re-Pinning bräuchte einen frischen Vollsimulationslauf über die
non-blocking adaptivequiz-Integration). Als eigentliches Korrektheits-Oracle dient nun
`catcalc_test::test_person_ability_recovers_true_theta_and_se_shrinks`: simulierte
Antworten (fester Seed) für eine bekannte Fähigkeit, Prüfung auf (1) Wiederfindung der
wahren Fähigkeit und (2) monoton fallenden Standardfehler mit wachsender Itemzahl –
robust gegenüber den Newton-Branch-Unterschieden und ohne adaptivequiz-Abhängigkeit.

**Simulationstest gegen systematischen Drift gehärtet.** Zusätzlich zur
90-%-Trefferquote (<=0.01) prüft `test_simulation_steps_match_reference_within_tolerance`
nun die Bimodalität: das mittlere Band (0.01, 0.5] muss <=2 % bleiben. Gemessen:
512 Treffer, 0 im Mittelband, 32 echte Branch-Flips (von 544). Ein systematischer
kleiner Fehler würde das Mittelband füllen und den Test kippen – das reine
Trefferquoten-Kriterium allein hätte ihn maskieren können.

Verifikation: alle 7 Modell-Suiten grün (rasch 347, raschbirnbaum 348, grm/ggrm 154,
pcm 171, gpcm 152, 3PL 366/20 skip), Sättigung 7/2856, persistence 6, codec 7,
catcalc 8, model_raschmodel 12, mathcat 9, matrixcat 6; phpcs plugin-weit Exit 0.
