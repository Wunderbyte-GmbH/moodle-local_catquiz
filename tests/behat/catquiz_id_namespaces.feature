@local @local_catquiz @javascript
Feature: The CAT attempt and the attempt of the host are two id spaces (issues #95, #96, #101).
  local_catquiz_attempts.id is the CAT attempt; adaptivequiz_attempt.id the attempt of the host, kept in
  local_catquiz_attempts.attemptid. Progress, results and history refer to the CAT attempt. A saved state that does not
  belong to the attempt it is found under is refused with a controlled message, and it is neither taken over nor
  overwritten. The ids of the CAT attempts are moved away from those of the host, so that a mix-up shows.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | student2 | Student2  | Test     | toolgenerator2@example.com |
      | student3 | Student3  | Test     | toolgenerator5@example.com |
      | student4 | Student4  | Test     | toolgenerator6@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
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
    And the following "activities" exist:
      | activity | name       | course | idnumber | content             |
      | page     | Statistics | C1     | page1    | [catquizstatistics] |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy      | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquizA | catquiz  | Simulation | Classical test      | 3                    | 4                    | 0                               | 0.0                       | 1000.0                    | 1                  |
    And the "shortcodes" filter is "on"

  Scenario: A whole attempt with different ids: start, resume, end, result and feedback refer to the CAT attempt
    Given the next CAT attempt ids start at 5000
    And I am on the "adaptivecatquizA" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 2"
    And the CAT attempt of the last attempt of "student1" at "adaptivecatquizA" has its own id and everything refers to it
    ## Resume over the activity page: the progress is found under the CAT attempt.
    When I am on the "adaptivecatquizA" Activity page
    And I click on "Continue attempt" "button"
    And I wait until the page is ready
    Then I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I click on "falsche Antwort 1" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should not see "Submit answer"
    And the CAT attempt of the last attempt of "student1" at "adaptivecatquizA" has its own id and everything refers to it
    And I log out
    ## The teacher opens the feedback of the attempt as from the report page.
    When I am on the "adaptivecatquizA" Activity page logged in as teacher
    And I request the CAT feedback page of the last attempt of "student1" at "adaptivecatquizA"
    Then the requested page shows "Simulation" and not "This test cannot be continued"
    ## The export of the attempt follows the same attempt.
    When I request the CSV export of the last attempt of "student1" at "adaptivecatquizA"
    Then the export names the last attempt of "student1" at "adaptivecatquizA" with 4 questions and no duplicate

  Scenario: A saved state of another person under the id of the attempt is refused, not taken over or overwritten
    Given the next CAT attempt ids start at 5000
    And I am on the "adaptivecatquizA" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 2"
    ## The collision: the saved state under the id of this CAT attempt now belongs to student2.
    And the saved state of the last attempt of "student1" at "adaptivecatquizA" belongs to "student2"
    And I remember the saved state of the last attempt of "student1" at "adaptivecatquizA"
    ## Showing the open question reads no saved state; choosing the next question after an answer does.
    When I answer "richtige Antwort" to question 2 and the page refuses with "This test cannot be continued"
    Then the saved state of the last attempt of "student1" at "adaptivecatquizA" still belongs to "student2" and is unchanged
    ## The other person sees nothing of it either.
    And I log out
    When I am on the "adaptivecatquizA" Activity page logged in as student2
    Then I should not see "You have an attempt in progress"
