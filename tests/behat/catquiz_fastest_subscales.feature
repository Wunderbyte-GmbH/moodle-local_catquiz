@local @local_catquiz @javascript
Feature: The strategy CAT (FASTEST) over several subscales, with real questions (issue #135).
  A per-scale maximum does not limit FASTEST: the attempt draws from several subscales, a subscale that
  reached the per-scale maximum keeps its items, and only the global maximum ends the test.

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
    ## 28 questions in Simulation: subscales SimA, SimB and SimC, each with subscales of its own.
    And the following "local_catquiz > questions" exist:
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                           | filename              |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
    ## At most 2 per scale, which FASTEST does not apply; the precision is never reached, the maximum of 8 ends.
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_maxquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | CAT            | 6                    | 8                    | 1                               | 2                               | 0.0                       | 1000.0                    | 1                  |

  @javascript
  Scenario: FASTEST asks up to the global maximum, from several subscales, each question once
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 4"
    And I click on "falsche Antwort 1" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 6"
    And I click on "falsche Antwort 1" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 8"
    And I click on "falsche Antwort 1" "text" in the "Question 8" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should not see "Question 9"
    And "Submit answer" "button" should not exist
    And the last CAT attempt of "student1" asked 8 different questions from at least 2 subscales of "Simulation"
