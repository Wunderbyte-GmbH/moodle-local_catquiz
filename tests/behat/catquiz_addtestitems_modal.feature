@local @local_catquiz @javascript
Feature: The dialogue to add test items is opened, used and left with the keyboard (issue #30).
  The questions tab of the CAT manager offers the items of the question bank in a dialogue. It is
  named by its title, takes the focus, keeps it while it is open, closes with Escape and gives the
  focus back to the button that opened it; nothing on the page uses an id twice.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following "local_catquiz > questions" exist:
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                          | filename             |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |

  @javascript @accessibility
  Scenario: The dialogue takes the focus, closes with Escape and with its button, and gives the focus back
    Given I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "Questions" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Simulation"
    And I should see "28 of 28 records found"
    Then the page has no duplicate ids
    And the "#region-main" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
    When I click on "Add test items to CAT scales" "button"
    Then "Add test items to CAT scales" "dialogue" should be visible
    And the focus is inside the open dialogue
    And the "Add test items to CAT scales" "dialogue" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
    And the page has no duplicate ids
    When I press the tab key
    Then the focus is inside the open dialogue
    When I press the escape key
    Then "Add test items to CAT scales" "dialogue" should not be visible
    And the focused element is "Add test items to CAT scales" "button"
    ## Opened a second time: the table is still in it, and the close button behaves like Escape.
    When I click on "Add test items to CAT scales" "button"
    Then "Add test items to CAT scales" "dialogue" should be visible
    And "table" "css_element" should exist in the "Add test items to CAT scales" "dialogue"
    When I click on "Close" "button" in the "Add test items to CAT scales" "dialogue"
    Then "Add test items to CAT scales" "dialogue" should not be visible
    And the focused element is "Add test items to CAT scales" "button"
