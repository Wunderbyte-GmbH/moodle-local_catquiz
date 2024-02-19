@local @local_catquiz @javascript
Feature: As a teacher I want to use shortcodes to display adaptive quiz test results with catquiz functinality.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                       |
      | student1 | Student1  | Test     | toolgenerator1@example.com  |
      | student2 | Student2  | Test     | toolgenerator2@example.com  |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com  |
      | manager  | Manager   | Test     | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
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
    ## Unfortunately, in Moodle 4.3 TinyMCE has misbehavior which cause number of site-wide issues. So - we disable it.
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
      | label        | Adaptive Panel   | C1     | 1       | adaptivelabel1   | [catquizfeedback]   |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I set the following fields to these values:
      | catmodel                                              | Catquiz CAT model   |
      | Select CAT scale                                      | Simulation          |
      | Purpose of test                                       | Infer all subscales |
      | maxquestionsgroup[catquiz_maxquestions]               | 4                   |
      | catquiz_standarderrorgroup[catquiz_standarderror_min] | 0.4                 |
      | catquiz_standarderrorgroup[catquiz_standarderror_max] | 0.6                 |
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: CatQuiz: Pass adaptive quiz attempt and displaying feedback in a Page resource via the shortcode
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "button"
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
    And I should see "Ability score"
    And I should see "-1.35 (Standarderror: 0.51)"
    ## Verify feedback in label
    When I am on "Course 1" course homepage
    Then I should see "Ability score"
    And I should see "-1.35 (Standarderror: 0.51)"
    And I should see "You performed better than 50.00% of your fellow students for parent scale Simulation."
    And I log out
