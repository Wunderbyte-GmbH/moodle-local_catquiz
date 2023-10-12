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
    ##And the following "question bank questions" exist:
    ##  | user     | filepath                                        | filename              |
    ##  | admin | local/catquiz/tests/fixtures/mathematik2scales.xml | mathematik2scales.xml |
    And the following "user private files" exist:
      | user     | filepath                                        | filename              |
      | admin | local/catquiz/tests/fixtures/mathematik2scales.csv | mathematik2scales.csv |

  @javascript
  Scenario: CatQuiz Import: admin imports catscales for already imported questions and verified it
    Given I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    ## And I follow "#catscales-tab"
    And I click on "Import" "link" in the "#region-main" "css_element"
    And I click on "Choose a file..." "button"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "mathematik2scales.csv" "link"
    And I click on "Select this file" "button"
    And I set the field "CSV separator" to ";"
    And I wait "1" seconds
    And I press "Submit"
    And I wait until the page is ready
    ## Then I should see "Import was successful. 44 record(s) treated."
