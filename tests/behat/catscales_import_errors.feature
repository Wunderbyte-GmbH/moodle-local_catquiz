@local @local_catquiz @javascript
Feature: As an admin I perform import of catscales which caused errors.

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
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |

  @javascript
  Scenario: Catscales import: admin imports catscales with no_parent_scales_given error
    Given the following "user private files" exist:
      | user     | filepath                                                            | filename                               |
      | admin    | local/catquiz/tests/fixtures/simulation_test_import_cases_noids.csv | simulation_test_import_cases_noids.csv |
    And I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "Import" "link" in the "#region-main" "css_element"
    And I click on "Choose a file..." "button"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "simulation_test_import_cases_noids.csv" "link"
    And I click on "Select this file" "button"
    And I set the field "CSV separator" to ";"
    And I wait "1" seconds
    When I press "Submit"
    And I wait until the page is ready
    Then I should see "Import was successful. 3 record(s) treated."
    And I should see "Items for scale SimA can not be localized, because there are no parent scales given."
    And I should see "Items for scale errorRoot can not be localized, because there are no parent scales given."
    ## TODO: potential issue - need to reload page?
    And I reload the page
    And I wait until the page is ready
    And I click on "CAT scales" "link" in the "#region-main" "css_element"
    And I should see "Simulation" in the "(//div[contains(@id, 'local-catscales-container-')])[1]//li[@data-parentid='0']" "xpath_element"
    And I should see "neueRoot" in the "(//div[contains(@id, 'local-catscales-container-')])[2]//li[@data-parentid='0']" "xpath_element"
    And I should see "SimA"
    And I should see "SimA07"
    And I should see "SimNEU"
    And I click on "Questions" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Simulation"
    And I should see "2 of 2 records found"
    And I set the field "Scale" to "neueRoot"
    And I should see "1 of 1 records found"
