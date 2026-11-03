@local @local_catquiz @javascript
Feature: A prior without spread: fifty earlier participants with the same ability (issue #106).
  Fifty or more earlier results of a scale that are all the same give the prior of the ability a spread of zero. The
  estimator raises it to its minimum and reports that as an event; the attempt goes on as usual: after the first
  answer the next question, finite abilities and standard errors, and the result page at the end.

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
      | C1     | adaptivecatquizA | catquiz  | Simulation | Infer all subscales | 4                    | 8                    | 0                               | 0.0                       | 1000.0                    | 1                  |
    And the "shortcodes" filter is "on"

  Scenario: A whole attempt after fifty identical earlier results reaches the result page with finite values
    Given 50 earlier participants have the valid CAT result "0.5" at "adaptivecatquizA"
    And I am on the "adaptivecatquizA" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "Question 2"
    When I answer "richtige Antwort" to every question until the attempt ends, at most 10 times
    Then I should not see "Submit answer"
    And I should not see "Exception"
    And the last attempt of "student1" at "adaptivecatquizA" stored only finite abilities
    And a numeric fallback "nospread" was logged
    ## The result page, as the person sees it at the end: all correct gives no overall result, and says why.
    When I request the result page of the last attempt of "student1" at "adaptivecatquizA"
    Then the requested page shows "all answers were correct" and not "This test cannot be continued"

  Scenario: The same with only wrong answers
    Given 50 earlier participants have the valid CAT result "-1.25" at "adaptivecatquizA"
    And I am on the "adaptivecatquizA" Activity page logged in as student2
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "falsche Antwort 1" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "Question 2"
    When I answer "falsche Antwort 1" to every question until the attempt ends, at most 10 times
    Then I should not see "Submit answer"
    And the last attempt of "student2" at "adaptivecatquizA" stored only finite abilities
    And a numeric fallback "nospread" was logged
    When I request the result page of the last attempt of "student2" at "adaptivecatquizA"
    Then the requested page shows "all answers were wrong" and not "This test cannot be continued"
