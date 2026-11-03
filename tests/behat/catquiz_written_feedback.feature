@local @local_catquiz @javascript
Feature: Which scales the written feedback and the detail tab show (issues #117, #118).
  Every scale gets a written feedback text with its name. Shown are the scales the strategy reports
  that were measured in this attempt with at least the minimum per scale, answered neither all right
  nor all wrong, with reporting switched on - each strategy in its own way.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname      | shortname |
      | Course 1      | C1        |
      | Target course | TC        |
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

  @javascript
  Scenario: All subscales: every measured, valid subscale has its written feedback (issue #117)
    ## Twelve answers, right and wrong in turn, one question per scale at least: two per scale in all seven
    ## subscales would take 14 and is refused before the start (#60, coveragebudget). A subscale with only
    ## one answer, or two alike, has no valid result (#140) and is left out: SimA01, SimA02, SimB02, SimB03
    ## and SimC01. The detail tab of this attempt: SimA 2, SimB 6, SimB01 3, SimC 4, SimC02 2 of 12
    ## questions. Every valid subscale is shown, not just one.
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer all subscales | 12                   | 12                   | 1                               | 0.0                       | 1000.0                    | 1                  |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "falsche Antwort 1" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 1" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 8"
    And I click on "falsche Antwort 1" "text" in the "Question 8" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 9"
    And I click on "richtige Antwort" "text" in the "Question 9" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 10"
    And I click on "falsche Antwort 1" "text" in the "Question 10" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 11"
    And I click on "richtige Antwort" "text" in the "Question 11" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 12"
    And I click on "falsche Antwort 1" "text" in the "Question 12" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "Written feedback for SimA"
    And I should see "Written feedback for SimB"
    And I should see "Written feedback for SimB01"
    And I should see "Written feedback for SimC"
    And I should see "Written feedback for SimC02"
    And I should not see "Written feedback for SimA01"
    And I should not see "Written feedback for SimA02"
    And I should not see "Written feedback for SimB02"
    And I should not see "Written feedback for SimB03"
    And I should not see "Written feedback for SimC01"
    ## Issue #77: the subscales by name, in the attempt's own result and on the result page read from the
    ## stored attempt.
    And the following lines appear in this order in the main region:
      | Written feedback for Simulation |
      | Written feedback for SimA       |
      | Written feedback for SimB       |
      | Written feedback for SimB01     |
      | Written feedback for SimC       |
      | Written feedback for SimC02     |
    When I open the result page of the last attempt of "student1"
    Then I should see "Written feedback for SimB01"
    And I should not see "Written feedback for SimB02"
    And the following lines appear in this order in the main region:
      | Written feedback for Simulation |
      | Written feedback for SimA       |
      | Written feedback for SimB       |
      | Written feedback for SimB01     |
      | Written feedback for SimC       |
      | Written feedback for SimC02     |

  @javascript
  Scenario: Relevant subscales: several valid subscales at once, the invalid ones left out (issues #117, #140)
    ## SimB03 is answered all wrong and SimC01 all right: no valid result. SimA is not measured.
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer relevant subscales | 12                   | 12                   | 2                               | 0.0                       | 1000.0                    | 1                  |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "falsche Antwort 1" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 1" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 8"
    And I click on "falsche Antwort 1" "text" in the "Question 8" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 9"
    And I click on "richtige Antwort" "text" in the "Question 9" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 10"
    And I click on "falsche Antwort 1" "text" in the "Question 10" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 11"
    And I click on "richtige Antwort" "text" in the "Question 11" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 12"
    And I click on "falsche Antwort 1" "text" in the "Question 12" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "Written feedback for SimB01"
    And I should see "Written feedback for SimB02"
    And I should see "Written feedback for SimC02"
    And I should not see "Written feedback for SimB03"
    And I should not see "Written feedback for SimC01"
    And I should not see "Written feedback for SimA"

  @javascript
  Scenario: Lowest skill gap: one scale in writing, every valid one in the detail tab, the selected one marked (issue #118)
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer lowest skill gap | 12                   | 12                   | 2                               | 0.0                       | 1000.0                    | 1                  |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "falsche Antwort 1" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 1" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 8"
    And I click on "falsche Antwort 1" "text" in the "Question 8" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 9"
    And I click on "richtige Antwort" "text" in the "Question 9" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 10"
    And I click on "falsche Antwort 1" "text" in the "Question 10" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 11"
    And I click on "richtige Antwort" "text" in the "Question 11" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 12"
    And I click on "falsche Antwort 1" "text" in the "Question 12" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "Written feedback for SimB02"
    And I should not see "Written feedback for SimB01"
    When I click on "Details of your result" "link"
    Then I should see "selected" in the "SimB02" "table_row"
    And I should see "SimB01" in the "region-main" "region"
    And I should see "SimB03" in the "region-main" "region"
    And I should not see "selected" in the "SimB01" "table_row"
    And I should not see "selected" in the "SimB03" "table_row"
    And I should see "Reference scale"

  @javascript
  Scenario: Greatest strength: one scale in writing, every valid one in the detail tab, the selected one marked (issues #118, #128)
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer greatest strength | 12                   | 12                   | 2                               | 0.0                       | 1000.0                    | 1                  |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "falsche Antwort 1" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 1" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 8"
    And I click on "falsche Antwort 1" "text" in the "Question 8" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 9"
    And I click on "richtige Antwort" "text" in the "Question 9" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 10"
    And I click on "falsche Antwort 1" "text" in the "Question 10" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 11"
    And I click on "richtige Antwort" "text" in the "Question 11" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 12"
    And I click on "falsche Antwort 1" "text" in the "Question 12" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    ## The strongest valid subscale is SimC01 (ability 0.00 against -0.38 for SimB02 and -0.13 for SimC02).
    Then I should see "Written feedback for SimC01"
    And I should not see "Written feedback for SimB02"
    And I should not see "Written feedback for SimC02"
    When I click on "Details of your result" "link"
    Then I should see "selected" in the "SimC01" "table_row"
    And I should see "SimB02" in the "region-main" "region"
    And I should see "SimC02" in the "region-main" "region"
    And I should not see "selected" in the "SimB02" "table_row"
    And I should not see "selected" in the "SimC02" "table_row"
    And I should see "Reference scale"

  @javascript
  Scenario: Relevant subscales: a scale whose reporting is switched off has no written feedback (issue #117)
    ## The answers of the scenario above; SimB01 is valid but not to be reported.
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer relevant subscales | 12                   | 12                   | 2                               | 0.0                       | 1000.0                    | 1                  |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out
    And the scale "SimB01" is not reported by "adaptivecatquiz1"
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "falsche Antwort 1" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 1" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 8"
    And I click on "falsche Antwort 1" "text" in the "Question 8" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 9"
    And I click on "richtige Antwort" "text" in the "Question 9" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 10"
    And I click on "falsche Antwort 1" "text" in the "Question 10" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 11"
    And I click on "richtige Antwort" "text" in the "Question 11" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 12"
    And I click on "falsche Antwort 1" "text" in the "Question 12" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should not see "Written feedback for SimB01"
    And I should see "Written feedback for SimB02"
    And I should see "Written feedback for SimC02"
