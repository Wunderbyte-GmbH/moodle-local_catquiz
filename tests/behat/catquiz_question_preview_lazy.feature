@local @local_catquiz @javascript
Feature: The question list does not carry question texts and loads previews on demand.
  As a CAT manager I see a compact question list; the question text and its images
  are fetched only when I actually open a preview (issue #20).

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

  @javascript
  Scenario: The rendered list carries no question text until a preview is opened
    Given I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "Questions" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Simulation"
    And I should see "28 of 28 records found"
    ## Before issue #20 every row embedded the full formatted question text into a
    ## hidden modal. The rows now only carry a trigger with the question id.
    Then "//div[contains(@class, 'questionstable')]//a[@data-action='catquiz-question-preview']" "xpath_element" should exist
    ## The hidden per-row modal markup is gone.
    And "//div[contains(@class, 'questionstable')]//div[contains(@class, 'preview-question')]" "xpath_element" should not exist

  @javascript
  Scenario: Opening a preview loads the question text through the web service
    Given I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "Questions" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Simulation"
    And I should see "28 of 28 records found"
    When I click on "//div[contains(@class, 'questionstable')]//a[@data-action='catquiz-question-preview']" "xpath_element"
    And I wait until the page is ready
    ## The modal is created by core/modal only after the AJAX call returned.
    Then "//div[contains(@class, 'modal-body')]" "xpath_element" should exist
