@local @local_catquiz @javascript
Feature: The retention of attempt progress can be configured per CAT test.
  As an administrator I decide how long the working state of an attempt is kept,
  and a single test may only be stricter than the site, never more permissive
  (issue #56).

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "local_catquiz > questions" exist:
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                          | filename             |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |

  @javascript
  Scenario: The site default offers only the data sparing level
    ## With the shipped default the site permits nothing beyond "minimal", so the
    ## form must not offer an option that would be capped away on save.
    Given I log in as "admin"
    When I add an "adaptivequiz" activity to course "Course 1" section "1"
    ## The catquiz settings only appear once the CAT model is chosen, and the
    ## selector sits in a collapsed section of the activity form.
    And I expand all fieldsets
    And I set the field "catmodel" to "catquiz"
    ## The form reloads itself when the model changes; the submit button behind it
    ## is hidden and cannot be pressed.
    And I wait until the page is ready
    Then I should see "Retention of attempt progress"
    And the "catquiz_progressretention" select box should contain "Use the site default"
    And the "catquiz_progressretention" select box should contain "Minimal - delete after the attempt"
    And the "catquiz_progressretention" select box should not contain "Record the step by step trace"

  @javascript
  Scenario: Raising the site setting widens what a test may choose
    Given the following config values are set as admin:
      | progressretention | trace | local_catquiz |
    And I log in as "admin"
    When I add an "adaptivequiz" activity to course "Course 1" section "1"
    And I expand all fieldsets
    And I set the field "catmodel" to "catquiz"
    ## The form reloads itself when the model changes; the submit button behind it
    ## is hidden and cannot be pressed.
    And I wait until the page is ready
    ## Only now may a single test record a trajectory - the site setting is the
    ## upper bound, and the form follows it rather than accepting a choice that
    ## would silently not take effect.
    Then the "catquiz_progressretention" select box should contain "Record the step by step trace"
    And the "catquiz_progressretention" select box should contain "Keep the final state"
