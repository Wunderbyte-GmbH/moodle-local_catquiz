@local @local_catquiz @javascript
Feature: A template of CAT settings is overwritten or copied, as chosen (issue #89).
  "Overwrite the chosen template" changes exactly the template chosen, under its id. "Save as a new
  template" makes a template with an id and a name of its own and leaves the chosen one as it was.
  Names of templates are unique.

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
    And the CAT settings of "source1" are offered as the template "Template A"

  Scenario: Overwrite the chosen template: the same template, the new values; the activity names it
    Given I am on the "target1" Activity page logged in as admin
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    When I set the field "Choose a test environment" to "Template A"
    And I wait until the page is ready
    And I expand all fieldsets
    And I set the field "maxquestionsgroup[catquiz_maxquestions]" to "9"
    And I set the field "Template" to "Overwrite the chosen template with these settings"
    And I click on "Save and display" "button"
    Then there is 1 CAT template
    And the CAT template "Template A" stores "maxquestionsgroup.catquiz_maxquestions" as "9"
    And the CAT settings of "target1" are based on the template "Template A"
    # Opened again, the template offers the values it was overwritten with.
    And I am on the "source1" Activity page
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    And I set the field "Choose a test environment" to "Template A"
    And I wait until the page is ready
    And I expand all fieldsets
    And the following fields match these values:
      | maxquestionsgroup[catquiz_maxquestions] | 9 |

  Scenario: Save as a new template: a template of its own; the chosen one stays as it was
    Given I am on the "target1" Activity page logged in as admin
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    When I set the field "Choose a test environment" to "Template A"
    And I wait until the page is ready
    And I expand all fieldsets
    And I set the field "maxquestionsgroup[catquiz_maxquestions]" to "11"
    And I set the field "Template" to "Save these settings as a new template"
    And I set the field "Name of the template" to "Template B"
    And I click on "Save and display" "button"
    Then there are 2 CAT templates
    And the CAT template "Template A" stores "maxquestionsgroup.catquiz_maxquestions" as "7"
    And the CAT template "Template B" stores "maxquestionsgroup.catquiz_maxquestions" as "11"
    And the CAT settings of "target1" are based on the template "Template B"

  Scenario: A new template may not take the name of another one
    Given I am on the "target1" Activity page logged in as admin
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    When I set the field "Template" to "Save these settings as a new template"
    And I set the field "Name of the template" to "Template A"
    And I click on "Save and display" "button"
    Then I should see "There is already a template named \"Template A\"."
    And there is 1 CAT template
