# Ralf fragen
- [x] Was sollen min/max Werte für person ability trusted region sein? Wirklich Skalen-spezifisch oder [-5, 5]?
- [x] Berechnung in defCAT ist anders als bei mir. Es sieht so aus als würden im Code von Ralf die Skalen ausgeschlossen, deren Standardfehler zu groß ist, und danach wird das item mit der größten Fisherinformation ausgewählt. Bei mir wird hingegen speziell eine einzelne Skala zuerst herausgefiltert und danach aus dieser Skala das item mit der größten Fisherinformation ausgewählt.
- [x] Ich bin nicht sicher, ob der Code in 07_teststrategie_tester.php wirklich mit den Daten übereinstimmt.
      2. Laut Code müsste eigentlich so gut wie immer 0.0/1.0 als Mean/SD verwendet ($pp_parent und $sd_parent), allerdings passt das nicht mit den berechneten Werten im CSV zusammen.
- [-] Soll PP Start und SE Start im Formular konfiguriert werden können? -> nein, wird automatisch berechnet ansonsten 0/1
- [ ] Die standardfehler Werte im debug feedback basieren auf der Ability der Hauptskala. Ist das so in Ordnung oder sollte das eigentlich die Testinformation und das Testpotential der jeweiligen Subskala sein (also auf deren ability basieren)?
- [ ] Braucht es ein Formularfeld für se_max in den quiz settings? Oder default 1.0 ok?

# TODO
- [x] Commit für stepwise catscale test abschließen.
- [x] Test abilities in subscales are calculated correctly - compare with stepwise radCAT results.
    Values are not the same.
    Reason seems to be that in Ralf's code, the calculated ability of a parent scale is only reused if that scale had not all answers correct/incorrect.
    However, MEAN and SD are ALWAYS used from the parent scale.
- [x] Test standarderrors in subscales are calculated correctly - compare with stepwise radCAT results.
- [x] Calculate test potential: add function get_testpotential() to catscale class.
- [x] Calculate test information: add function get_testpotential() to catscale class.
- [x] Implement abort condititon: tp + ti < 1 / (max standardfehler)** 2
- [-] Einmal durchlaufen lassen
- [x] Iterieren über Skalen statt über fragen und weighted fisherinformation verwenden
- [ ] Werden inaktive Items eh nicht beim Quiz geladen? Mit breakpoint checken (items table status)
- [ ] Startwerte für Mean und SE berechnen: Wenn bereits für hauptskala vorhanden, verwende diesen Wert. Wenn nicht und es gibt >= Versuche, berechne Startwert daraus. Falls auch das nicht vorhanden, verwende 0,1.


Startwert setzen:
1. Falls vorhanden
2. Für jeden Versuch speichern für jede Skala: anzahl fragen, fraction, standardfehler, ability

# Refactor teststrategies

## preselect tasks
### classical cat

            maximumquestionscheck::class, x
            removeplayedquestions::class, x
            noremainingquestions::class, x
            mayberemovescale::class, x
            firstquestionselector::class,
            fisherinformation::class,
            addscalestandarderror::class,
            updatepersonability::class,
            strategyclassicscore::class,

### infer all subscales

            maximumquestionscheck::class, x
            removeplayedquestions::class, x
            updatepersonability::class,
            noremainingquestions::class, x
            fisherinformation::class, // Add the fisher information to each question.
            firstquestionselector::class, // If this is the first question of this attempt, return it here.
            lasttimeplayedpenalty::class,
            mayberemovescale::class, x // Remove questions from excluded subscales.
            maybe_return_pilot::class, x
            remove_uncalculated::class, x// Remove items that do not have item parameters.
            addscalestandarderror::class,
            filterbystandarderror::class,
            noremainingquestions::class, // Cancel quiz attempt if no questions are left.
            strategyfastestscore::class,

### infer lowest skillgap

            maximumquestionscheck::class, x
            removeplayedquestions::class, x
            updatepersonability::class,
            noremainingquestions::class, x
            fisherinformation::class, // Add the fisher information to each question.
            firstquestionselector::class, // If this is the first question of this attempt, return it here.
            lasttimeplayedpenalty::class,
            mayberemovescale::class, x // Remove questions from excluded subscales.
            maybe_return_pilot::class, x
            remove_uncalculated::class, x// Remove items that do not have item parameters.
            noremainingquestions::class, // Cancel quiz attempt if no questions are left.
            // Keep only questions that are assigned to the subscale where the user has the lowest ability.
            addscalestandarderror::class,
            filterbystandarderror::class,
            filterforsubscale::class,
            strategyfastestscore::class,

### radical cat

            maximumquestionscheck::class, x
            removeplayedquestions::class, x
            remove_uncalculated::class, x
            noremainingquestions::class, x
            mayberemovescale::class, x
            firstquestionselector::class,
            updatepersonability::class,
            lasttimeplayedpenalty::class,
            fisherinformation::class,
            addscalestandarderror::class,
            strategyfastestscore::class,


## Combined

    maximumquestionscheck
    removeplayedquestions
    maybe_return_pilot,
    remove_uncalculated::class
    noremainingquestions
    mayberemovescale


