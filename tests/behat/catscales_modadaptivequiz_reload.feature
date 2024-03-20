@local @local_catquiz @local_catquiz_quiz_attempt @javascript
Feature: As a teacher I want to ensure a correct behavior of an adaptive quiz attempt on page reloading or timeouts.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname    | email                       |
      | user1    | Username1 | Test        | toolgenerator1@example.com  |
      | user2    | Username2 | Test        | toolgenerator2@example.com  |
      | teacher  | Teacher   | Test        | toolgenerator3@example.com  |
      | manager  | Manager   | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user1    | C1     | student        |
      | user2    | C1     | student        |
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
      | activity     | name             | course | section | idnumber         |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy         | catquiz_selectfirstquestion | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer lowest skill gap | startwitheasiestquestion    | 7                    | 0.4                       | 0.6                       | 2                       |
    ## Below steps are required to save a correct JSON settings
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: Start adaptive quiz attempt and reload the frist question page
    Given I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I set the field "Purpose of test" to "Classical test"
    And I click on "Save and display" "button"
    And I log out
    And I log in as "user1"
    And I am on "Course 1" course homepage
    And I follow "My Adaptive Quiz"
    And I click on "Start attempt" "button"
    And I wait until the page is ready
    And I should see "Question 1"
    And I should see "Skala: A/A01"
    And I should see "Schwierigkeit: -4.45"
    And I should see "Trennschärtfe: 5.92"
    And I reload the page
    And I should see "Skala: A/A01"
    And I should see "Schwierigkeit: -4.45"
    And I should see "Trennschärtfe: 5.92"
