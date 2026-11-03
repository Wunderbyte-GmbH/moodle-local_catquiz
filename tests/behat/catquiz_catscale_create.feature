@local @local_catquiz @javascript
Feature: A new CAT scale gets its context together with it (issue #81).
  "New default context for scale" creates a context of its own for a root scale - before the scale is
  stored, so the scale never points to nothing. A subscale stores no context and uses its root's.

  @javascript
  Scenario: Root scale with a new default context, then a subscale below it
    Given I log in as "admin"
    And I visit "/local/catquiz/manage_catscales.php?tab=catscales"
    And I wait until the page is ready
    ## The "Create" of the first block creates a root scale; the one in a scale's row a subscale.
    When I click on "//li[not(@data-id)]//a[@data-action='create']" "xpath_element"
    And I wait until the page is ready
    And I set the following fields in the "Manage CAT scale" "dialogue" to these values:
      | Name                    | Neue Root                     |
      | Choose CAT context      | New default context for scale |
      | Person ability minimum: | -4                            |
      | Person ability maximum: | 4                             |
    And I click on "Save changes" "button" in the "Manage CAT scale" "dialogue"
    And I wait until the page is ready
    Then I should see "Neue Root"
    And the CAT scale "Neue Root" has a context of its own
    When I click on "Edit" "link" in the "[data-name=\"Neue Root\"]" "css_element"
    And I wait until the page is ready
    Then I should see "autocontext_Neue Root_" in the "Manage CAT scale" "dialogue"
    And I click on "Cancel" "button" in the "Manage CAT scale" "dialogue"
    When I click on "Create" "link" in the "[data-name=\"Neue Root\"]" "css_element"
    And I wait until the page is ready
    And I set the following fields in the "Manage CAT scale" "dialogue" to these values:
      | Name | Neue Subskala |
    And I click on "Save changes" "button" in the "Manage CAT scale" "dialogue"
    And I wait until the page is ready
    Then I should see "Neue Subskala"
    And the CAT scale "Neue Subskala" has no context of its own and uses the one of "Neue Root"
