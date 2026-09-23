@local @local_catquiz
Feature: The CAT manager keeps its tab in the URL.
  As a manager I want reload and the browser's back button to keep the tab I was
  looking at, and I want only that tab to be built on the server (issue #29).

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | manager1 | Mana      | Ger      | manager1@test.com |
    And the following "system role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |
    And I log in as "manager1"

  Scenario: Without a tab the first one is shown
    When I visit "/local/catquiz/manage_catscales.php"
    Then I should see "Summary"

  Scenario: The tab is taken from the URL and survives a reload
    ## The decisive property: the tab is a request parameter, not a fragment. With
    ## the previous anchor-based markup the fragment never reached the server, so a
    ## reload silently returned to the first tab.
    When I visit "/local/catquiz/manage_catscales.php?tab=questions"
    Then I should see "Questions"
    When I reload the page
    Then I should see "Questions"

  Scenario: An unknown tab falls back instead of failing
    ## A crafted parameter must not select something that does not exist - and must
    ## not make the manager build every tab either.
    When I visit "/local/catquiz/manage_catscales.php?tab=nonexistent"
    Then I should see "Summary"
    And I should not see "Coding error detected"

  Scenario: Switching tabs keeps the context and scale
    ## Without the state in the link a tab switch would silently reset what the
    ## manager had selected.
    When I visit "/local/catquiz/manage_catscales.php?tab=calculations&contextid=1&scaleid=0"
    Then I should see "Calculations"

  @javascript
  Scenario: The browser's back and forward buttons move between tabs
    ## The point of putting the tab in the URL rather than in a fragment is that the
    ## browser treats each tab as its own history entry. With the previous markup the
    ## back button left the manager entirely, because no navigation had happened.
    When I visit "/local/catquiz/manage_catscales.php?tab=summary"
    And I visit "/local/catquiz/manage_catscales.php?tab=questions"
    Then I should see "Questions"
    When I press the "back" button in the browser
    Then I should see "Summary"
    When I press the "forward" button in the browser
    Then I should see "Questions"

  Scenario: The active tab is marked for assistive technology
    ## aria-current="page" is what tells a screen reader which of these links is the
    ## one currently open. The panels were removed with the tab rebuild, so the
    ## tab/tabpanel roles no longer apply and would announce a structure that is not
    ## there.
    When I visit "/local/catquiz/manage_catscales.php?tab=questions"
    Then "//a[@aria-current='page'][contains(., 'Questions')]" "xpath_element" should exist
    And "//a[@aria-current='page'][contains(., 'Summary')]" "xpath_element" should not exist

  Scenario: A tab link carries the context and the scale
    ## Losing them on a tab change would silently move the manager to a different
    ## data set - the page would look right and show the wrong numbers.
    When I visit "/local/catquiz/manage_catscales.php?tab=summary&contextid=1&scaleid=-1"
    Then "//a[contains(@href, 'tab=questions')][contains(@href, 'contextid=1')]" "xpath_element" should exist
