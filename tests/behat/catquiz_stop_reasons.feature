@local @local_catquiz @javascript
Feature: An attempt ends for a reason the participant is told, and the result follows it (issues #141, #134, #60).
  The pool runs out before the minimum: no valid result. The pool runs out after the minimum: a
  result. The time runs out: the attempt ends with what was answered in time. Settings no attempt can
  meet: the attempt does not start.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    ## Two questions: the pool is exhausted after the second.
    And the following "local_catquiz > questions" exist:
      | filepath                                              | filename                 | course |
      | local/catquiz/tests/fixtures/catquiz_resources_mc.xml | catquiz_resources_mc.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                              | filename                 |
      | local/catquiz/tests/fixtures/catquiz_resources_mc.csv | catquiz_resources_mc.csv |
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |

  @javascript
  Scenario: The pool runs out before the minimum: the participant is told, and there is no valid result
    ## Issue #60: settings no attempt can meet do not start (see below). The pool runs out before the minimum
    ## when it shrinks during the attempt: the question not shown yet is deactivated after the first.
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | CAT            | 2                    | 5                    | 0.0                       | 1000.0                    | 1                  |
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And the CAT items of the questions "student1" has not seen yet are deactivated
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "There are no further suitable questions for you at the moment" in the "Your test has ended" "dialogue"
    And I click on "OK" "button" in the "Your test has ended" "dialogue"
    And I should see "No overall result can be determined because in the whole test the minimum number of questions was not reached."
    And I should not see "Question 2"
    And "Submit answer" "button" should not exist
    ## Issue #120: below the minimum the attempt is shown all the same - the quiz history with content.
    And "Summary" "link" should not exist
    When I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    Then I should see "Resource question" in the ".catquiz-graphicalsummary-table" "css_element"
    And I should not see "No feedback available"
    And I log out
    ## Teachers: statistics and export, with the answer counted.
    When I log in as "admin"
    And I visit the CAT attempt feedback page for the last attempt of "student1"
    And I click on "Statistics & export" "link"
    And I wait until ".catquiz-attempt-statistics" "css_element" exists
    Then "//table[contains(@class, 'catquiz-attempt-statistics')]//tr[th[normalize-space(.)='Correct']]/td[normalize-space(.)='1']" "xpath_element" should exist
    And I should see "Export attempt (CSV)"

  @javascript
  Scenario: The pool runs out after the minimum: the participant is told, and the result stands
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | CAT            | 1                    | 5                    | 0.0                       | 1000.0                    | 1                  |
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "There are no further suitable questions for you at the moment" in the "Your test has ended" "dialogue"
    And I click on "OK" "button" in the "Your test has ended" "dialogue"
    And I should not see "minimum number of questions was not reached"
    And I should not see "No overall result can be determined"
    And I should see "Written feedback for Ressourcen"

  @javascript
  Scenario: The time runs out: the attempt ends with what was answered in time
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | Classical test | 1                    | 2                    | 0.0                       | 1000.0                    | 1                  |
    And the CAT settings of "adaptivecatquiz1" limit an attempt to 5 minutes
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Text of resource question two."
    And the running CAT attempt of "student1" started 10 minutes ago
    And I click on "richtige Antwort" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "The time available for this test has run out" in the "Your test has ended" "dialogue"
    ## The test's limit - not the time since the start, which is ten minutes here (mod_adaptivequiz #20).
    And I should see "The time limit was 5 mins." in the "Your test has ended" "dialogue"
    And I click on "OK" "button" in the "Your test has ended" "dialogue"
    And "Submit answer" "button" should not exist
    And "[data-region='adaptivequiz-attempt-feedback']" "css_element" should be visible
    ## The notice says the answers submitted are saved: both answers are stored and graded in the usage -
    ## also the one that arrived after the time had run out (mod_adaptivequiz #20).
    And the attempts of "student1" at "adaptivecatquiz1" are:
      | state    | stop reason | finished | questions | answered | duplicates | cat result |
      | complete | timelimit   | yes      | 2         | 2        | no         | ended      |

  @javascript
  Scenario: Settings no attempt can meet: the attempt does not start, and the participant is told why (issue #60)
    ## A minimum of 3 questions with two questions in the pool.
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | CAT            | 3                    | 5                    | 0.0                       | 1000.0                    | 1                  |
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    Then I should see "This test cannot be started"
    And I should see "The minimum of 3 questions cannot be reached"
    And I should not see "Question 1"
    And "Submit answer" "button" should not exist
