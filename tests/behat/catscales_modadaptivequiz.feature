@local @local_catquiz @javascript
Feature: As an admin I perform import of catquiz alonf with questions to check Scales and Feedbacks.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname     | email                       |
      | user1    | Username1  | Test        | toolgenerator1@example.com  |
      | user2    | Username2  | Test        | toolgenerator2@example.com  |
      | teacher  | Teacher    | Test        | toolgenerator3@example.com  |
      | manager  | Manager    | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user1    | C1     | student        |
      | user2    | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "local_catquiz > questions" exist:
      | filepath                                           | filename              | course |
      | local/catquiz/tests/fixtures/mathematik2scales.xml | mathematik2scales.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                           | filename              |
      | local/catquiz/tests/fixtures/mathematik2scales.csv | mathematik2scales.csv |
    And I log in as "teacher"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Adaptive Quiz" to section "1"
    And I set the following fields to these values:
      | Name                         | Adaptive Quiz               |
      | Description                  | Adaptive quiz description.  |
      | catmodel                     | Catquiz CAT model           |
      | Select CAT scale             | Mathematik                  |
      | Max. questions per test.     | 7                           |

    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: Start adaptive quiz attempt with catquiz model
    Given I log in as "user1"
    And I am on "Course 1" course homepage
    And I follow "Adaptive Quiz"
    And I click on "Start attempt" "button"
    And I click on "True" "radio"
    And I click on "Submit answer" "button"
    And I wait "20" seconds
    And I should see "Question 2"
