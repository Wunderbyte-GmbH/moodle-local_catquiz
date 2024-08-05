@local @local_catquiz @javascript
Feature: As an admin I perform import of catscales along with questions to check Scales and Feedbacks.

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

  @javascript
  Scenario: Catscales import: admin imports catscales for already imported questions and verified it
    Given the following "user private files" exist:
      | user     | filepath                                           | filename              |
      | admin    | local/catquiz/tests/fixtures/mathematik2scales.csv | mathematik2scales.csv |
    And I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "Import" "link" in the "#region-main" "css_element"
    And I click on "Choose a file..." "button"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "mathematik2scales.csv" "link"
    And I click on "Select this file" "button"
    And I set the field "CSV separator" to ";"
    And I wait "1" seconds
    When I press "Submit"
    And I wait until the page is ready
    Then I should see "Import was successful. 3 record(s) treated."
    ## TODO: potential issue - need to reload page?
    And I reload the page
    And I wait until the page is ready
    And I click on "CAT scales" "link" in the "#region-main" "css_element"
    And I should see "Mathematik"
    And I should see "A03"
    And I should see "A02"
    And I click on "Edit" "link" in the "[data-name=\"Mathematik\"]" "css_element"
    And the following fields match these values:
      | Name                               | Mathematik        |
      | Person ability minimum             | -2.00             |
      | Person ability maximum:            | 2.00              |
    And I press "Save changes"
    And I click on "Questions" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Mathematik"
    And I wait "120" seconds
    And I should see "3 of 3 records found"
    And I wait "1" seconds
    ## And I set the field "Search" to "Rechenregel"
    And I set the field with xpath "//input[contains(@name, 'search-catscale')]" to "Rechenregel"
    And I wait "1" seconds
    And I should see "1 of 3 records found"
    And I should see "-2.8624" in the "[data-label=\"difficulty\"]" "css_element"
    And I should see "1.0814" in the "[data-label=\"discrimination\"]" "css_element"
    And I should see "0.0000" in the "[data-label=\"guessing\"]" "css_element"

  @javascript
  Scenario: Catscales import: admin verified already imported catscales
    Given the following "local_catquiz > importedcatscales" exist:
      | filepath                                           | filename              |
      | local/catquiz/tests/fixtures/mathematik2scales.csv | mathematik2scales.csv |
    And I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "CAT scales" "link" in the "#region-main" "css_element"
    And I should see "Mathematik"
    And I should see "A03"
    And I should see "A02"
    And I click on "Questions" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Mathematik"
    And I should see "3 of 3 records found"
    And I wait "1" seconds
    ## And I set the field "Search" to "Rechenregel"
    And I set the field with xpath "//input[contains(@name, 'search-catscale')]" to "Rechenregel"
    And I wait "1" seconds
    And I should see "1 of 3 records found"
    And I should see "-2.8624" in the "[data-label=\"difficulty\"]" "css_element"
    And I should see "1.0814" in the "[data-label=\"discrimination\"]" "css_element"
    And I should see "0.0000" in the "[data-label=\"guessing\"]" "css_element"

  @javascript
  Scenario: Catscales import: admin toggle status and disable imported questions
    Given the following "local_catquiz > importedcatscales" exist:
      | filepath                                           | filename              |
      | local/catquiz/tests/fixtures/mathematik2scales.csv | mathematik2scales.csv |
    And I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "Questions" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Mathematik"
    And I should see "3 of 3 records found"
    ## Sort to match pgsql and mysql
    And I click on "Discrimination" "text" in the "" "css_element"
    ## Disable 3rd question
    And I should see "42.8047" in the "//tr[contains(@id, 'questionstable_r3')]" "xpath_element"
    And the "class" attribute of "//div[contains(@class, 'questionstable')]//tr[contains(@id, 'questionstable_r3')]//i[@title='Activate/Disable']" "xpath_element" should not contain "fa-eye-slash"
    And I click on "togglestatus-" "link" in the "//div[contains(@class, 'questionstable')]//tr[contains(@id, 'questionstable_r3')]" "xpath_element"
    And I wait "1" seconds
    And I should see "You are about to change the activity status of the following item" in the ".modal.show" "css_element"
    And I click on "Confirm" "button" in the ".modal.show" "css_element"
    And I wait "1" seconds
    And the "class" attribute of "//div[contains(@class, 'questionstable')]//tr[contains(@id, 'questionstable_r3')]//i[@title='Activate/Disable']" "xpath_element" should contain "fa-eye-slash"
    ## Delete 1st question
    And I should see "0.5201" in the "//tr[contains(@id, 'questionstable_r1')]" "xpath_element"
    And I click on "removetestitem-" "link" in the "//div[contains(@class, 'questionstable')]//tr[contains(@id, 'questionstable_r1')]" "xpath_element"
    And I wait "1" seconds
    And I should see "You are about to delete the following item:" in the ".modal.show" "css_element"
    And I click on "Confirm" "button" in the ".modal.show" "css_element"
    And I wait "1" seconds
    And I should not see "0.5201" in the "//tr[contains(@id, 'questionstable_r1')]" "xpath_element"
    And I should see "2 of 2 records found"
