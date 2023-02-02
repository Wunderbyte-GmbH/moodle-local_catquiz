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
  Scenario: Admin create a catscale, edit it, subscribe, unsubscribe, delete
    Given I log in as "admin"
    And I press "Catquiz"
    And I follow "Manage CAT Scales"
    And I follow "Create"
    ## And I set the field "Name" to "Math"
    And I set the following fields to these values:
      | Name                               | Math |
      | minmaxgroup[catquiz_minscalevalue] | 5    |
      | minmaxgroup[catquiz_maxscalevalue] | 15   |
    And I press "Save changes"
    ## Then I should see "Math" in the ".grid .list-group-item" "css_element"
    Then I should see "Math" in the "[data-name=\"Math\"]" "css_element"
    And I follow "Subscribe"
    Then I should see "Subscribed" in the ".grid .list-group-item" "css_element"
    And I follow "Edit"
    And the following fields match these values:
      | Name                               | Math |
      | minmaxgroup[catquiz_minscalevalue] | 5    |
      | minmaxgroup[catquiz_maxscalevalue] | 15   |
    ## And I set the field "Name" to "Mathematics"
    And I set the following fields to these values:
      | Name                               | Mathematics |
      | minmaxgroup[catquiz_minscalevalue] | 6           |
      | minmaxgroup[catquiz_maxscalevalue] | 16          |
    And I press "Save changes"
    Then I should see "Mathematics" in the "[data-name=\"Mathematics\"]" "css_element"
    And I follow "Subscribed"
    Then I should see "Subscribe" in the ".grid .list-group-item" "css_element"
    And I follow "Delete"
    Then I should see "Create" in the ".grid .list-group-item" "css_element"

  @javascript
  Scenario: Admin create a catscale wiht subitems than edit, subscribe, unsubscribe, delete subitem
    Given I log in as "admin"
    And I press "Catquiz"
    And I follow "Manage CAT Scales"
    And I follow "Create"
    ## And I set the field "Name" to "Mathematics"
    And I set the following fields to these values:
      | Name                               | Mathematics |
      | minmaxgroup[catquiz_minscalevalue] | 5           |
      | minmaxgroup[catquiz_maxscalevalue] | 20          |
    And I press "Save changes"
    Then I should see "Mathematics" in the "[data-name=\"Mathematics\"]" "css_element"
    And I follow "Create"
    ## And I set the field "Name" to "Arithmetics"
    And I set the following fields to these values:
      | Name                               | Arithmetics |
      | minmaxgroup[catquiz_minscalevalue] | 6           |
      | minmaxgroup[catquiz_maxscalevalue] | 10          |
    And I set the field "Parent catscale - None if top level catscale" to "Mathematics"
    And I press "Save changes"
    Then I should see "Arithmetics" in the "[data-name=\"Arithmetics\"]" "css_element"
    And I follow "Create"
    ## And I set the field "Name" to "Multiplication"
    And I set the following fields to these values:
      | Name                               | Multiplication |
      | minmaxgroup[catquiz_minscalevalue] | 7              |
      | minmaxgroup[catquiz_maxscalevalue] | 11             |
    And I set the field "Parent catscale - None if top level catscale" to "Mathematics"
    And I press "Save changes"
    Then I should see "Multiplication" in the "[data-name=\"Multiplication\"]" "css_element"
    And I follow "Create"
    ## And I set the field "Name" to "Geometrie"
    And I set the following fields to these values:
      | Name                               | Geometrie |
      | minmaxgroup[catquiz_minscalevalue] | 8         |
      | minmaxgroup[catquiz_maxscalevalue] | 12        |
    And I set the field "Parent catscale - None if top level catscale" to "Mathematics"
    And I press "Save changes"
    Then I should see "Geometrie" in the "[data-name=\"Geometrie\"]" "css_element"
    And I click on "Subscribe" "link" in the "[data-name=\"Geometrie\"]" "css_element"
    Then I should see "Subscribed" in the "[data-name=\"Geometrie\"]" "css_element"
    And I click on "Edit" "link" in the "[data-name=\"Geometrie\"]" "css_element"
    And the following fields match these values:
      | Name                               | Geometrie |
      | minmaxgroup[catquiz_minscalevalue] | 8         |
      | minmaxgroup[catquiz_maxscalevalue] | 12        |
    And I set the field "Name" to "Geometry"
    And I set the field "Parent catscale - None if top level catscale" to "Mathematics"
    And I press "Save changes"
    Then I should see "Geometry" in the "[data-name=\"Geometry\"]" "css_element"
    And I click on "Subscribed" "link" in the "[data-name=\"Geometry\"]" "css_element"
    Then I should see "Subscribe" in the "[data-name=\"Geometry\"]" "css_element"
    And I click on "Delete" "link" in the "[data-name=\"Geometry\"]" "css_element"
    And I wait "1" seconds
    Then I should not see "Geometry"
    ## And I follow "Delete"
    ## And I wait "1" seconds
    ## Then I should see "Create" in the ".grid .list-group-item" "css_element"
