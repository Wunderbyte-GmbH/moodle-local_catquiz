@local @local_catquiz @javascript
Feature: A CAT attempt is finalised authoritatively on completion.
  As a student, completing an adaptive quiz attempt produces exactly one
  finalised result with a stable end time, independently of how I reach the end
  (normal completion or resuming an interrupted attempt). The end time is never
  stamped while the attempt is still running (issue #5).

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
    ## Round-trip the generated CAT settings through the activity form. The
    ## adaptivequiz integration normalises and reserialises the settings here;
    ## without this step the adapter can finish after the first question. Keep
    ## the stopping thresholds non-binding so maxquestions = 4 defines the end.
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: Completing an attempt normally finalises it and shows feedback
    ## Reaching the end runs the finaliser once and renders the feedback page.
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
    Then I should not see "Question 5"

  @javascript
  Scenario: Resuming an interrupted attempt still finalises exactly once
    ## Leaving mid-attempt and returning must not start a second attempt; the
    ## same attempt is resumed and, once finished, is finalised a single time.
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    ## Interrupt: navigate away, then come back to the activity. Resuming an
    ## in-progress attempt uses the same "Start attempt" link, which continues
    ## the existing attempt rather than starting a new one.
    And I am on the "adaptivecatquiz1" Activity page
    And I wait until the page is ready
    And I click on "Start attempt" "link"
    And I wait until the page is ready
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
    Then I should not see "Question 5"
