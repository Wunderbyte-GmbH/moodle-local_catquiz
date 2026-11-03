@local @local_catquiz @javascript
Feature: Feedback output is bound to a valid CAT result.
  As a student I only see per-scale feedback when my attempt produced a valid
  result. When no scale can be reported, I see a single central notice instead
  of scattered "not available" blocks (issue #10).

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
    ## TinyMCE misbehaves in acceptance tests; use a plain editor.
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy         | catquiz_selectfirstquestion | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer lowest skill gap | -2                          | 2                    | 4                    | 0.0                       | 1000.0                    | 2                       |
    ## The CAT settings generator creates the initial JSON structure, but the
    ## adaptivequiz integration normalises and reserialises it through the activity
    ## settings form. This is the same setup used by the established multi-question
    ## catscales_attempt_management Behat scenario. Without this round-trip the
    ## adapter can read incomplete settings and finish the attempt after question 1.
    ## Standard-error/test-information stopping is deliberately made non-binding;
    ## maxquestions = 4 therefore determines the end of the attempt.
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: An invalid attempt shows a single central notice, not per-scale feedback
    ## The "Infer lowest skill gap" strategy declares a fraction of >= 1 (every
    ## answer correct) invalid, because with no wrong answer there is no skill gap
    ## to infer. Answering every question correctly therefore excludes every scale
    ## and no valid result can be determined.
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I click on "richtige Antwort" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "richtige Antwort" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "No overall result can be determined"
    And I should see "because in the whole test all answers were correct"

  @javascript
  Scenario: A valid attempt shows feedback and not the central notice
    ## A mix of correct and incorrect answers yields a reportable scale.
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
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
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should not see "No overall result can be determined"

  @javascript
  Scenario: An invalid attempt keeps its quiz history and, for authorised users, its export (issue #120)
    ## What reads the result stays out - the overview compares with others and is not offered.
    ## What the attempt contained stays: the quiz progress summary, and the export for a teacher.
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I click on "richtige Antwort" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "richtige Antwort" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "No overall result can be determined"
    And I should see "Quiz progress summary"
    And I should not see "Overview"
    And "Statistics & export" "link" should not exist
    And "Summary" "link" should not exist
    When I click on "Quiz progress summary" "link"
    Then I should see "During an attempt your ability is recalculated after each response"
    And I log out
    ## The export is for users allowed to export attempts - here the administrator.
    When I log in as "admin"
    And I visit the CAT attempt feedback page for the last attempt of "student1"
    Then I should see "No overall result can be determined"
    And I should see "Quiz progress summary"
    When I click on "Statistics & export" "link"
    Then I should see "Export attempt (CSV)"
    And "//table[contains(@class, 'catquiz-attempt-statistics')]//tr[th[normalize-space(.)='Correct']]/td[normalize-space(.)='4']" "xpath_element" should exist
    And I should see "Time taken" in the ".catquiz-attempt-statistics" "css_element"

  @javascript
  Scenario: All answers wrong is not a valid result either, and the attempt stays reviewable (issues #138, #140)
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "falsche Antwort 1" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "falsche Antwort 1" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "falsche Antwort 1" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "No overall result can be determined"
    And I should see "because in the whole test all answers were wrong"
    And I log out
    ## The invalid attempt is not hidden away: the teacher opens it like any other.
    When I log in as "teacher"
    And I visit the CAT attempt feedback page for the last attempt of "student1"
    Then I should see "No overall result can be determined"

  @javascript
  Scenario: A question administered twice is named for the reviewer and changes nothing (issue #125)
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
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
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I log out
    And the last CAT attempt of "student1" administered its first question a second time
    When I log in as "teacher"
    And I visit the CAT attempt feedback page for the last attempt of "student1"
    Then I should see "Technical note: questions administered more than once"
    And I should see "original slot 1, duplicate slot 5"
    And I should see "was not used as a reason to invalidate the result"
    And I should not see "No overall result can be determined"

  ## A scale whose reporting checkbox is switched off: catquiz_written_feedback.feature (issue #117) sets
  ## it in the stored settings, for the reason below.
  ## The setting is an advcheckbox, so Moodle renders TWO inputs per scale with the
  ## same name and label - a hidden value="0" input plus the visible checkbox - and
  ## the label-based Behat step matches the hidden one first ("element not
  ## interactable"). Addressing it by id is not an option either, because the ids
  ## contain the generated scale id and therefore change from run to run.
  ## The behaviour is covered at the gate instead, where it can be asserted
  ## precisely: feedback_result_gate_test asserts that such a scale is NOT
  ## displayable while staying statistically valid, so its result is still
  ## persisted and available for carry-over.
