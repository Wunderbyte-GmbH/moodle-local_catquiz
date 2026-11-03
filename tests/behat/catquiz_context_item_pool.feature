@local @local_catquiz @javascript
Feature: An attempt in a new calibration context plays the calibrated items of that context (issue #157).
  The pattern of the production data: the scale got a new CAT context with a copy of its calibrated items; the items
  of the old context have no parameters and the lower ids. The pool used to take those rows - every question counted
  as a pilot, the estimate froze. Now the attempt plays the items of its context with their active parameters, the
  result is stored in that context, and the statistics compute the test information from these items.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "local_catquiz > questions" exist:
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                          | filename             |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
    And the following "activities" exist:
      | activity | name       | course | idnumber | content             |
      | page     | Statistics | C1     | page1    | [catquizstatistics] |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_maxquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | CAT            | 12                   | 12                   | 1                               | 4                               | 0.0                       | 1000.0                    | 1                  |
    And the "shortcodes" filter is "on"
    And the CAT scale "Simulation" is recalibrated into the CAT context "Recalibration" and its old items keep no parameters

  Scenario: A whole attempt plays the calibrated items of the new context, and the statistics see them
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    When I answer "richtige Antwort" and "falsche Antwort 1" in turn until the attempt ends, at most 30 times
    Then the questions of the last CAT attempt of "student1" are calibrated items of the CAT context "Recalibration"
    And the attempts of "student1" at "adaptivecatquiz1" are:
      | state    | stop reason      | finished | questions | answered | duplicates | cat result |
      | complete | maximumquestions | yes      | 12        | 12       | no         | ended      |
    And I log out
    When I am on the "page1" "page activity" page logged in as teacher
    And I wait until "[data-chartname='abilityprofile'] .chart-image canvas" "css_element" exists
    Then I should see "and its subscales with parameters in the CAT context “Recalibration”" in the "[data-chartname='abilityprofile']" "css_element"
    And I should see "n = 1 persons" in the "[data-chartname='abilityprofile']" "css_element"
    And I should not see "The test information is not shown" in the "[data-chartname='abilityprofile']" "css_element"
