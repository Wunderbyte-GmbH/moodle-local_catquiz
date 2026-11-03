@local @local_catquiz @javascript
Feature: A classical test over a scale hierarchy goes on after the first answer.
  Reported in issue #64: a classical test with 24 calculated items in a hierarchy of root, domain and
  two subscales ended after the first answered question with "minimum not reached", although 23 items
  were left, the maximum was 250 and the maximum per scale 8. Played here through the activity and
  the CAT model adapter, without any worker.

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
    ## Simulation > SimA > {SimA01, SimA02}, twelve calculated items each.
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                                 | filename                    |
      | local/catquiz/tests/fixtures/simulation_24_hierarchy.csv | simulation_24_hierarchy.csv |
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy  | catquiz_minquestions | catquiz_maxquestions | catquiz_maxquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Classical test  | 3                    | 250                  | 8                               | 0.05                      | 0.30                      | 1                  |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: The second and the third question follow the first
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "Question 2"
    And I should not see "minimum number of questions was not reached"
    And I click on "richtige Antwort" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 3"
    And I should not see "minimum number of questions was not reached"
