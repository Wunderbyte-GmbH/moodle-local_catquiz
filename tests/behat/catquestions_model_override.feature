@local @local_catquiz @javascript
Feature: As an admin I perfrom model settings customizations over an imported of catscales and questions

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
  Scenario: Catqusetions: model overides for imported questions
    Given the following "local_catquiz > importedcatscales" exist:
      | filepath                                          | filename             |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |
    And I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "Questions" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Simulation"
    And I should see "28 of 28 records found"
    ## Sort to match pgsql and mysql
    And I click on "Discrimination" "text" in the "" "css_element"
    ## Edit 1st question
    And I should see "0.3100" in the "//tr[contains(@id, 'questionstable_r1')]" "xpath_element"
    And I click on "managedetails-" "link" in the "//div[contains(@class, 'questionstable')]//tr[contains(@id, 'questionstable_r1')]" "xpath_element"
    And I should see "Schwierigkeit: 1.36" in the "#questiondetailview .questionpreview" "css_element"
    And I should see "Skala: B/B02" in the "#questiondetailview .questionpreview" "css_element"
    And I click on "Edit" "button" in the "#lcq_model_override_form" "css_element"
    ## Verify default settings
    And the following fields match these values:
      | 3PL mixed Rasch-Birnbaum model | Not yet calculated |
      | 1PL Rasch model                | Not yet calculated |
      | 2PL Rasch-Birnbaum model       | Manually updated   |
      | override_raschbirnbaum[difficulty]     | 1.3600 |
      | override_raschbirnbaum[discrimination] | 0.3100 |
    And I should see "Manually updated" in the ".card.likelihood" "css_element"
    And I should see "2PL Rasch-Birnbaum model" in the ".card.likelihood" "css_element"
    ## Verify hidden elements in case of "Not yet calculated" settings
    And the "hidden" attribute of "//div[contains(@id, 'fgroup_id_override_mixedraschbirnbaum_')]" "xpath_element" should contain "true"
    And the "hidden" attribute of "//div[contains(@id, 'group_id_override_rasch_')]" "xpath_element" should contain "true"
    ## Verify of status update for the "2PL Rasch-Birnbaum" model
    And I set the field "2PL Rasch-Birnbaum model" to "Calculated"
    And the "override_raschbirnbaum[difficulty]" "field" should be disabled
    And the "override_raschbirnbaum[discrimination]" "field" should be disabled
    ## And I wait "31" seconds
    And I click on "Save changes" "button" in the "#lcq_model_override_form" "css_element"
    And I reload the page
    And I wait "1" seconds
    And I should see "Calculated" in the ".card.likelihood" "css_element"
    And I should see "2PL Rasch-Birnbaum model" in the ".card.likelihood" "css_element"
