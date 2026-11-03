@local @local_catquiz @javascript
Feature: The CAT settings reload for a template and for the number of feedback ranges, then save (issue #123).
  Changing the template, the scale or the number of feedback ranges reloads the settings form with a
  reason of its own. The reload keeps the values of that choice and stores nothing; the next
  "Save and display" stores the form.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | teacher1 | Teacher1  | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "local_catquiz > questions" exist:
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                          | filename             |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name   | course | section | idnumber |
      | adaptivequiz | Source | C1     | 1       | source1  |
      | adaptivequiz | Target | C1     | 1       | target1  |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz | catmodel | catscales  | cateststrategy         | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | source1         | catquiz  | Simulation | Infer lowest skill gap | 2                    | 7                    | 0.4                       | 0.6                       | 3                       |
      | C1     | target1         | catquiz  | Simulation | Classical test         | 3                    | 12                   | 0.3                       | 0.9                       | 2                       |
    And the CAT settings of "source1" are offered as the template "Template lowest gap"

  Scenario: Choosing a template reloads its values; more feedback ranges reload; saving stores both
    Given I am on the "target1" Activity page logged in as teacher1
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    And the following fields match these values:
      | Purpose of test                         | Classical test |
      | maxquestionsgroup[catquiz_maxquestions] | 12             |
    # The template: a reload with its values, nothing stored yet.
    When I set the field "Choose a test environment" to "Template lowest gap"
    And I wait until the page is ready
    And I expand all fieldsets
    Then the following fields match these values:
      | Purpose of test                                       | Infer lowest skill gap |
      | maxquestionsgroup[catquiz_maxquestions]               | 7                      |
      | catquiz_standarderrorgroup[catquiz_standarderror_min] | 0.4                    |
      | Number of ability ranges                              | 3                      |
    # The number of feedback ranges: a reload of its own, the template's values stay.
    When I set the field "Number of ability ranges" to "4"
    And I wait until the page is ready
    And I expand all fieldsets
    Then "//div[@data-name='feedback_scale_Simulation_range_4']" "xpath_element" should exist
    And the following fields match these values:
      | Purpose of test                         | Infer lowest skill gap |
      | maxquestionsgroup[catquiz_maxquestions] | 7                      |
    # A normal save stores the form.
    When I click on "Save and display" "button"
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    Then the following fields match these values:
      | Purpose of test                                       | Infer lowest skill gap |
      | maxquestionsgroup[catquiz_maxquestions]               | 7                      |
      | catquiz_standarderrorgroup[catquiz_standarderror_min] | 0.4                    |
      | Number of ability ranges                              | 4                      |
    And "//div[@data-name='feedback_scale_Simulation_range_4']" "xpath_element" should exist
    # The template itself is unchanged by the activity that used it.
    And I am on the "source1" Activity page
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    And the following fields match these values:
      | Number of ability ranges | 3 |

  Scenario: Another root scale and its subscales reload the form without storing anything; the next save stores them
    Given the following "local_catquiz > importedcatscales" exist:
      | filepath                                           | filename              |
      | local/catquiz/tests/fixtures/second_root_small.csv | second_root_small.csv |
    And I am on the "target1" Activity page logged in as teacher1
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    # The root scale: a reload with the subscales of the new root; stored is still the old one.
    When I set the field "Select CAT scale" to "Zweitskala"
    And I wait until the page is ready
    And I expand all fieldsets
    Then I should see "ZweitA" in the "#id_catquiz_headercontainer" "css_element"
    And I should see "ZweitB" in the "#id_catquiz_headercontainer" "css_element"
    And I should not see "SimA" in the "#id_catquiz_headercontainer" "css_element"
    And the field with xpath "//input[@data-name='ZweitB']" matches value "checked"
    And the stored CAT settings of "target1" use the CAT scale "Simulation"
    # A subscale off and on again: each a reload of its own, nothing stored.
    When I set the field with xpath "//input[@data-name='ZweitB']" to ""
    And I wait until the page is ready
    Then the field with xpath "//input[@data-name='ZweitB']" matches value ""
    And the field with xpath "//input[@data-name='ZweitA']" matches value "checked"
    And the stored CAT settings of "target1" use the CAT scale "Simulation"
    When I set the field with xpath "//input[@data-name='ZweitB']" to "1"
    And I wait until the page is ready
    Then the field with xpath "//input[@data-name='ZweitB']" matches value "checked"
    And I set the field with xpath "//input[@data-name='ZweitB']" to ""
    And I wait until the page is ready
    And the stored CAT settings of "target1" use the CAT scale "Simulation"
    # The normal save stores the root and the subscale choice; the form shows them again.
    When I click on "Save and display" "button"
    Then the stored CAT settings of "target1" use the CAT scale "Zweitskala"
    And the stored CAT settings of "target1" have the subscale "ZweitA" on
    And the stored CAT settings of "target1" have the subscale "ZweitB" off
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    And the field "Select CAT scale" matches value "Zweitskala"
    And the field with xpath "//input[@data-name='ZweitA']" matches value "checked"
    And the field with xpath "//input[@data-name='ZweitB']" matches value ""
