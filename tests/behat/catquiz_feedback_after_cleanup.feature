@local @local_catquiz @javascript
Feature: The result of a finished attempt is shown the same after its progress was removed (issue #159).
  The retention task removes the progress of finished attempts. The result page then reads what was stored with the
  attempt when it was finalised - read only: nothing is played again and nothing is estimated again with today's
  parameters. Before, it ended with "No overall result" and with sections that "could not be shown".

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
      | activity     | name   | course | section | idnumber         | intro   |
      | adaptivequiz | Quiz A | C1     | 1       | adaptivecatquizA | Quiz A. |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquizA | catquiz  | Simulation | Classical test | 3                    | 4                    | 0                               | 0.0                       | 1000.0                    | 1                  |

  Scenario Outline: Participant and teacher see the same result before and after the cleanup (lazy <lazy>)
    Given the following config values are set as admin:
      | config           | value  | plugin        |
      | lazyfeedbacktabs | <lazy> | local_catquiz |
    And I am on the "adaptivecatquizA" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should not see "Submit answer"
    ## The participant's result page is the one the attempt ends on (attemptfinished.php).
    And I should not see "No overall result"
    And I remember the result page as "participant"
    And I log out
    And I am on the "adaptivecatquizA" Activity page logged in as teacher
    And I open the CAT feedback page of the last attempt of "student1" at "adaptivecatquizA"
    And I remember the result page as "teacher"
    And I log out
    When the retention task removes the progress of finished CAT attempts
    ## Re-login after the cleanup and the purge of the caches.
    And I am on the "adaptivecatquizA" Activity page logged in as student1
    Then the result page "participant" shows the same values and labels again
    And I should not see "could not be shown"
    And I reload the page
    And the result page "participant" shows the same values and labels again
    And I log out
    And I am on the "adaptivecatquizA" Activity page logged in as teacher
    And the result page "teacher" shows the same values and labels again
    And I should not see "could not be shown"

    Examples:
      | lazy |
      | 0    |
      | 1    |
