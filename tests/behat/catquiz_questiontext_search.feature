@local @local_catquiz @javascript
Feature: Question texts stay searchable although the list no longer carries them.
  As a CAT manager I can search inside question texts through a dedicated search,
  because the list itself deliberately no longer transports them (issue #20).

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
  Scenario: A search term nobody uses empties the list instead of ignoring the search
    ## This is the scenario that caught the real defect: the restriction used to be
    ## added while rendering, which is after the table has serialised its own SQL into
    ## the cached instance that AJAX reloads are built from. The first page looked
    ## filtered and every reload silently showed all records again.
    Given I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "Questions" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Simulation"
    And I should see "28 of 28 records found"
    When I set the field "Search in question texts" to "zzzznotfoundzzzz"
    And I click on "#catquiz-questiontext-search button[type='submit']" "css_element"
    And I wait until the page is ready
    Then I should not see "28 of 28 records found"

  @javascript
  Scenario: The search field keeps the selected scale and its term
    Given I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "Questions" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Simulation"
    And I should see "28 of 28 records found"
    When I set the field "Search in question texts" to "Antwort"
    And I click on "#catquiz-questiontext-search button[type='submit']" "css_element"
    And I wait until the page is ready
    ## Submitting a GET form must not drop the scale and context, otherwise the user
    ## silently ends up looking at a different list than before.
    Then the field "Search in question texts" matches value "Antwort"
    ## The scale must survive the round trip too - the term alone would not prove it.
    And the field "Scale" matches value "Simulation"
