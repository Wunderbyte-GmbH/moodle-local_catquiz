@local @local_catquiz @javascript
Feature: A duplicated CATquiz activity has the same settings, stored for itself (adaptivequizcatmodel_catquiz #1)

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | teacher1 | Teacher1  | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
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
      | activity     | name     | course | section | idnumber |
      | adaptivequiz | Original | C1     | 1       | original |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz | catmodel | catscales  | cateststrategy         | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | original        | catquiz  | Simulation | Infer lowest skill gap | 2                    | 7                    | 0.4                       | 0.6                       | 3                       |

  Scenario: Duplicate, compare, change the copy, the original keeps its settings
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I duplicate "Original" activity
    And I wait until the page is ready
    And I am on the "Original (copy)" "adaptivequiz activity editing" page
    And I wait until the page is ready
    And I expand all fieldsets
    Then the following fields match these values:
      | Purpose of test                                       | Infer lowest skill gap |
      | maxquestionsgroup[catquiz_maxquestions]               | 7                      |
      | catquiz_standarderrorgroup[catquiz_standarderror_min] | 0.4                    |
      | Number of ability ranges                              | 3                      |
    And I set the field "maxquestionsgroup[catquiz_maxquestions]" to "9"
    And I click on "Save and display" "button"
    And I am on the "Original" "adaptivequiz activity editing" page
    And I wait until the page is ready
    And I expand all fieldsets
    And the following fields match these values:
      | maxquestionsgroup[catquiz_maxquestions] | 7 |
