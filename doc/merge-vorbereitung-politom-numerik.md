# Merge-Vorbereitung: Numerik-Engine ↔ Politom-/Refactor-Branch

Dauerhaftes Planungsdokument. Bereitet den Merge des Branches
`implement-numerical-algorithms` (Numerik-Engine, Phasen M0/M2-dichotom
abgeschlossen) mit dem Branch `implement-jacobian&hessian-for-politomous-models`
vor und legt die anschließende Post-Merge-Runde (M1 + M2 politom) fest. Alle
Befunde sind am Code dieses Branches verifiziert.

## 1. Stand der beiden Seiten

**Numerik-Branch (hier):** `matrix.php` (identity-Fix + 5 statische Vektor-/Matrix-
Helfer), `mathcat.php` (BFGS/GA auf `matrix.php` zurückgeführt), `catcalc.php`
(`build_itemparam_objective` ergänzt), neue Tests `matrix_test`,
`itemparam_recovery_test`. Newton unverändert; `matrix::solve()` und der
Newton-Codec-Anschluss bewusst geparkt.

**Politom-Branch:** Jacobian/Hessian und Härtungen der politomen Modelle
(grm, grmgeneralized, pcm, pcmgeneralized). Diese Modelle existieren in beiden
Linien und erben von `model_multiparam` → `model_raschmodel`.

## 2. Integrationsfläche (verifiziert)

### 2.1 Nested Item-Parameter vs. flache Startwerte — der Kernpunkt
Politome Modelle haben **verschachtelte** Item-Parameter:
`grm = ['difficulty', 'difficulties']`, `pcm = ['difficulty', 'intercepts']`,
GGRM/GPCM zusätzlich `discrimination`. `difficulties`/`intercepts` sind Arrays
(mehrere Schwellen) ⇒ `get_model_dim` = Summe der Komponenten ⇒ **echt
hochdimensional**.

`catcalc::estimate_item_params()` bildet die Startwerte aber flach-dichotom:
```php
$defaultstart = ['difficulty' => 0.50, 'discrimination' => 1.0, 'guessing' => 0.25];
$z0 = array_slice(array_merge($defaultstart, $startvalue), 0, $modeldim - 1);
```
Dieses `array_slice` über einen flachen Merge erzeugt **keine** korrekte
nested-`difficulties`-Struktur. Politome IP-Schätzung braucht daher eine
strukturtreue Startwert-/Parameterbehandlung – das ist der zentrale
Integrationspunkt.

### 2.2 Codec-Bereitschaft — hier entscheidet sich der Schätzer
Der Codec (`mathcat::array_to_vector`/`vector_to_array`) deckt **nested**
Strukturen ab (eigener Konvertierungstest „nested array"). **BFGS und Gradient
Ascent nutzen den Codec bereits** → sie sind für nested politome Parameter
vorbereitet. **Newtons Item-Pfad ist flach** (siehe 2.1) und nutzt den Codec
nicht (der geparkte N1.3-Anschluss).

Daraus folgt direkt die Erwartung aus der Planung: für **hochdimensionales
politomes IP ist BFGS der natürliche Kandidat**, weil es nested Parameter über den
Codec schon beherrscht, während Newtons Item-Einstieg strukturell flach ist.
Der Post-Merge-Schritt entscheidet per Messung zwischen zwei Wegen:
- **(A)** Newton item-seitig an den Codec anschließen (der geparkte N1.3, jetzt
  item-seitig) – dann bleibt Newton auch politom nutzbar; oder
- **(B)** BFGS (codec-nativ) als politomer IP-Vollschätzer, Newton bleibt der
  dichotome/1-dim-Default.
Die M2-dichotom-Messung (Session 005: Newton dichotom am günstigsten, BFGS-Vorteil
erst hochdimensional erwartet) spricht dafür, (B) zuerst zu messen.

### 2.3 Zielfunktion ist bereits modell-agnostisch
`catcalc::build_itemparam_objective` (Session 005) summiert die skalare
`log_likelihood` – die politomen Modelle exponieren sie ebenfalls. Die
BFGS/GA-Zielfunktion steht für politom also ohne weitere Arbeit bereit.

### 2.4 Item-Ableitungen vorhanden
Die politomen Modelle besitzen `get_log_jacobian`/`get_log_hessian`/
`log_likelihood` (über `model_multiparam`). Der Politom-Branch schärft/vervoll-
ständigt diese; die numerische Engine konsumiert sie über
`build_itemparam_jacobian`/`_hessian`/`_objective`.

## 3. Konfliktfläche (geteilte Dateien)

| Datei | Numerik-Branch | Konfliktrisiko beim Merge |
|-------|----------------|---------------------------|
| `classes/matrix.php` | identity-Fix + 5 statische Methoden | **niedrig** (Politom-Branch fasst matrix.php i. d. R. nicht an) |
| `classes/mathcat.php` | BFGS/GA-Helfer-Konsolidierung, Helfer entfernt | **niedrig–mittel** (Max-Shift-Softmax liegt in den Modell-Dateien, nicht in mathcat) |
| `classes/catcalc.php` | `build_itemparam_objective` (additiv) | **mittel** (beide Seiten könnten `estimate_item_params`/Startwerte/Builder berühren) |
| `catmodel/{grm,ggrm,pcm,gpcm}/…` | unangetastet | Politom-Branch besitzt diese – **keine** Kollision von hier |
| `tests/*`, `doc/session-00X` | neue Dateien | **niedrig** (neue Dateien, additiv) |
| `version.php` | interne Version erhöht | trivialer Konflikt, manuell auf max. setzen |

Auflösungshinweis: Die hiesigen Änderungen an `matrix.php`/`mathcat.php` sind
lokal begrenzt bzw. additiv; bei `catcalc.php` ist `build_itemparam_objective`
rein additiv und kann neben etwaigen politomen Startwert-Anpassungen bestehen.

## 4. Post-Merge-Verifikationsreihenfolge

1. **Numerischer Kern zuerst:** `matrix`, `mathcat`, `matrixcat` – müssen
   unverändert grün sein (vom Politom-Merge unberührt).
2. **Dichotome Absicherung:** `catcalc` (gefiltert) und `itemparam_recovery`
   (1PL/2PL/3PL) – dürfen nicht regredieren; sichert, dass der Merge die
   dichotome IP-Schätzung nicht verschoben hat.
3. **Politome Modell-Suiten:** grm/ggrm/pcm/gpcm – mit den vollständigen
   Jacobian/Hessian; erwartete Skip-Zahlen prüfen (einige Tests sind derzeit
   skipped; nach dem Merge erneut bewerten).
4. **Bitgenau-/Zahn-Kontrollen** der numerischen Refactors bleiben gültig.

## 5. Post-Merge-Phasen

### M1 – PP über alle Modelle
PP-Ableitungen der politomen Modelle sind erst nach dem Merge settled. Dann:
Newton vs. GA→Newton vs. BFGS gegen das PP-Oracle. **Voraussetzung:** die fehlende
PP-Oracle-Fixture (`SimulationSteps radCAT …csv`) wieder aufbauen – analog zum
dichotomen Recovery-Test besser als self-contained, seed-deterministischer Test
statt externer CSV.

### M2 – politomes IP (High-Dim)
Recovery-Referenz auch politom als self-contained Test (mehrere Schwellen).
Vergleich Newton (falls über 2.2-Weg A codec-fähig gemacht) vs. **BFGS**
(codec-nativ) vs. GA→BFGS. Hier wird die BFGS-für-hochdim-Hypothese erstmals
tatsächlich gemessen. Metriken wie in M2-dichotom: Recovery-Fehler +
Auswertungskosten.

### M3 – Verstetigung als Einstellungen
Nach den Messungen die Schätzerwahl je Pfad/Modell als Settings (analog
Discrimination-Bounds). Bis dahin Auswahl auf Experimentebene.

## 6. Offene Referenzen (aus M0/M2-dichotom übernommen)

- PP-Oracle-Fixture fehlt (M1-Voraussetzung).
- Geparkt bleiben: toter `mathcat`-Cluster, `matrixcat`-Redundanz (aber
  `multi_sum`/`build_callable_array` live), Kommentarbereinigung, doppelte
  `MatrixException`, `matrix::solve()`. Der Newton-Codec-Anschluss (N1.3) wird
  durch 2.2 wieder relevant und ist dort als Weg (A) verortet.
