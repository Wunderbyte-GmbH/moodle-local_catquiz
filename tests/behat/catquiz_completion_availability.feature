@local @local_catquiz @javascript
Feature: Completion and grade follow the valid result, and the result stays readable (issues #121, mod_adaptivequiz #14).
  With the rule "valid result" the activity is complete after a valid attempt and not after an
  invalid one. Either way the attempt ends on its result page - no new attempt is started - and
  the result stays readable after the activity has become unavailable.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
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
      | activity     | name             | course | section | idnumber         | intro               | completion | completionvalidresult |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro | 2          | 1                     |
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
  Scenario: A valid attempt completes the activity; its result stays readable once the activity is unavailable
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
    And I should not see "Question 5"
    ## The result page stays the result page: no new attempt is started on it.
    And "Submit answer" "button" should not exist
    When I am on "Course 1" course homepage
    Then the "Achieve a valid CAT result" completion condition of "My Adaptive Quiz" is displayed as "done"
    ## Gradebook and completion read the same result (mod_adaptivequiz #14).
    And I am on the "Course 1" "grades > User report > View" page
    And the following should exist in the "user-grade" table:
      | Grade item       | Grade      |
      | My Adaptive Quiz | 45.70      |
    And I am on "Course 1" course homepage
    ## The activity becomes unavailable; the own result stays readable.
    And the activity "adaptivecatquiz1" is no longer available to participants
    When I open the result page of the last attempt of "student1"
    Then I should not see "No overall result can be determined"
    And I should see "Details of your result"
    And "Submit answer" "button" should not exist

  @javascript
  Scenario: An invalid attempt does not complete the activity and ends on its notice, not on a new attempt
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
    And "Submit answer" "button" should not exist
    When I am on "Course 1" course homepage
    Then the "Achieve a valid CAT result" completion condition of "My Adaptive Quiz" is displayed as "todo"
    ## No valid result, no grade - not 0 (mod_adaptivequiz #14).
    And I am on the "Course 1" "grades > User report > View" page
    And the following should exist in the "user-grade" table:
      | Grade item       | Grade |
      | My Adaptive Quiz | -     |
    And I am on "Course 1" course homepage
    And the activity "adaptivecatquiz1" is no longer available to participants
    When I open the result page of the last attempt of "student1"
    Then I should see "No overall result can be determined"
