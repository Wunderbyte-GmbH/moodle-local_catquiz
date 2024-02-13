@local @local_catquiz @javascript

Feature: As an admin I perform basic catquiz actions - create, update, delete, subscription.

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

  @javascript
  Scenario: Catscales management: admin create a catscale, edit it, subscribe, unsubscribe, delete
    Given I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    ## And I follow "#catscales-tab"
    And I click on "CAT scales" "link" in the "#region-main" "css_element"
    And I follow "Create"
    And the following fields match these values:
      | Person ability minimum             | -5                |
      | Person ability maximum:            | 5                 |
    And I set the following fields to these values:
      | Name                               | Math              |
      | Description                        | Description: Math |
      | Person ability minimum             | -4                |
      | Person ability maximum:            | 4                 |
    And I press "Save changes"
    ## And I wait "1" seconds
    And I wait until the page is ready
    Then I should see "Math" in the "[data-name=\"Math\"]" "css_element"
    ## And I follow "Subscribe"
    ## Exact precise click
    And I click on "Subscribe" "link" in the "[data-name=\"Math\"]" "css_element"
    Then I should see "Subscribed" in the "[data-name=\"Math\"]" "css_element"
    And I follow "Edit"
    And the following fields match these values:
      | Name                               | Math              |
      | Description                        | Description: Math |
      | Person ability minimum             | -4                |
      | Person ability maximum:            | 4                 |
    And I set the following fields to these values:
      | Name                               | Mathematics       |
    And I press "Save changes"
    ## And I wait "1" seconds
    And I wait until the page is ready
    ## TODO: should return to the same tab?
    And I click on "CAT scales" "link" in the "#region-main" "css_element"
    Then I should see "Mathematics" in the "[data-name=\"Mathematics\"]" "css_element"
    And I follow "Subscribed"
    Then I should see "Subscribe" in the "[data-name=\"Mathematics\"]" "css_element"
    And I follow "Delete"
    ## TODO: should return to the same tab?
    And I click on "CAT scales" "link" in the "#region-main" "css_element"
    Then I should see "Create" in the ".grid .list-group-item" "css_element"

  @javascript
  Scenario: Catscales management: admin create a catscale with subitems than edit, subscribe, unsubscribe, delete subitem
    Given I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "CAT scales" "link" in the "#region-main" "css_element"
    And I follow "Create"
    And I set the following fields to these values:
      | Name                               | Mathematics |
      | Person ability minimum             | -4          |
      | Person ability maximum:            | 4           |
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Mathematics" in the "[data-name=\"Mathematics\"]" "css_element"
    And I follow "Create"
    And I set the field "Name" to "Arithmetics"
    And I set the field "Parent CAT scale - None if top level CAT scale" to "Mathematics"
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Arithmetics" in the "[data-name=\"Arithmetics\"]" "css_element"
    And I follow "Create"
    And I set the field "Name" to "Multiplication"
    And I set the field "Parent CAT scale - None if top level CAT scale" to "Mathematics"
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Multiplication" in the "[data-name=\"Multiplication\"]" "css_element"
    And I follow "Create"
    And I set the field "Name" to "Geometrie"
    And I set the field "Parent CAT scale - None if top level CAT scale" to "Mathematics"
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Geometrie" in the "[data-name=\"Geometrie\"]" "css_element"
    And I click on "Subscribe" "link" in the "[data-name=\"Geometrie\"]" "css_element"
    Then I should see "Subscribed" in the "[data-name=\"Geometrie\"]" "css_element"
    And I click on "Edit" "link" in the "[data-name=\"Geometrie\"]" "css_element"
    And I set the field "Name" to "Geometry"
    And I set the field "Parent CAT scale - None if top level CAT scale" to "Mathematics"
    And I press "Save changes"
    And I wait until the page is ready
    ## TODO: should return to the same tab? Sometime tab not switched back to CAT scales after editing of catscale
    And I click on "CAT scales" "link" in the "#region-main" "css_element"
    Then I should see "Geometry" in the "[data-name=\"Geometry\"]" "css_element"
    And I click on "Subscribed" "link" in the "[data-name=\"Geometry\"]" "css_element"
    Then I should see "Subscribe" in the "[data-name=\"Geometry\"]" "css_element"
    And I click on "Delete" "link" in the "[data-name=\"Geometry\"]" "css_element"
    And I wait until the page is ready
    ## TODO: should return to the same tab? Sometime tab not switched back to CAT scales after editing of catscale
    And I click on "CAT scales" "link" in the "#region-main" "css_element"
    Then I should not see "Geometry"
