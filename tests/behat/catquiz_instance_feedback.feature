@local @local_catquiz @javascript
Feature: The result page of an attempt shows the feedback of that attempt's activity (mod_adaptivequiz #22).
  Two CATquiz activities in one course, one participant, each activity with feedback texts of its own. Every
  result page shows the feedback of its attempt's activity - also when it is opened again after an attempt in
  the other activity - and never the other's.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | student2 | Student2  | Test     | toolgenerator2@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname      | shortname |
      | Course 1      | C1        |
      | Target course | TC        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
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
      | adaptivequiz | Quiz B | C1     | 1       | adaptivecatquizB | Quiz B. |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy      | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbackprefix |
      | C1     | adaptivecatquizA | catquiz  | Simulation | Infer all subscales | 12                   | 12                   | 1                               | 0.0                       | 1000.0                    | Feedback of quiz A for |
      | C1     | adaptivecatquizB | catquiz  | Simulation | Infer all subscales | 12                   | 12                   | 1                               | 0.0                       | 1000.0                    | Feedback of quiz B for |

  @javascript
  Scenario: Each attempt shows the feedback of its own activity, also when opened again later
    Given I am on the "adaptivecatquizA" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out
    And I am on the "adaptivecatquizB" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out
    When I am on the "adaptivecatquizA" Activity page logged in as student1
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
    Then I should see "Feedback of quiz A for Simulation"
    And I should not see "Feedback of quiz B for"
    And I log out
    And I am on the "adaptivecatquizB" Activity page logged in as student1
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
    And I should see "Feedback of quiz B for Simulation"
    And I should not see "Feedback of quiz A for"
    When I open the result page of the last attempt of "student1" in "adaptivecatquizA"
    Then I should see "Feedback of quiz A for Simulation"
    And I should not see "Feedback of quiz B for"
    ## Links taken apart (mod_adaptivequiz #22): the result page is bound to attempt, instance and course module;
    ## a link with a part of the other activity's attempt shows no feedback at all.
    When I request the result page of the last attempt of "student1" at "adaptivecatquizA"
    Then the requested page shows "Feedback of quiz A for Simulation" and not "Feedback of quiz B for"
    When I request it again with the uattid of the last attempt of "student1" at "adaptivecatquizB"
    Then the requested page shows "nothing" and not "Feedback of quiz"
    When I request the result page of the last attempt of "student1" at "adaptivecatquizA"
    And I request it again with the id of the last attempt of "student1" at "adaptivecatquizB"
    Then the requested page shows "nothing" and not "Feedback of quiz"
    When I request the result page of the last attempt of "student1" at "adaptivecatquizA"
    And I request it again with the cmid of the last attempt of "student1" at "adaptivecatquizB"
    Then the requested page shows "nothing" and not "Feedback of quiz"
    And I log out
    ## Another participant with the links of student1: neither the result page nor the CATquiz feedback page.
    When I log in as "student2"
    And I request the result page of the last attempt of "student1" at "adaptivecatquizA"
    Then the requested page shows "nothing" and not "Feedback of quiz"
    When I request the CAT feedback page of the last attempt of "student1" at "adaptivecatquizB"
    Then the requested page shows "nothing" and not "Feedback of quiz"
    And I log out
    ## The teacher: each attempt's feedback page shows its own activity's feedback, never the other's.
    When I log in as "teacher"
    And I request the CAT feedback page of the last attempt of "student1" at "adaptivecatquizA"
    Then the requested page shows "Feedback of quiz A for Simulation" and not "Feedback of quiz B for"
    When I request the CAT feedback page of the last attempt of "student1" at "adaptivecatquizB"
    Then the requested page shows "Feedback of quiz B for Simulation" and not "Feedback of quiz A for"
