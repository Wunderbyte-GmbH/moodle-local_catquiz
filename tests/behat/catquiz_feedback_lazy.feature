@local @local_catquiz
Feature: The feedback of a CAT test is edited one scale at a time (issue #51).
  With a large scale tree the settings had three editors per scale - several hundred - and the page was
  no longer delivered completely. Only the scale being edited has its fields now; every other scale keeps
  its values, unsaved ones included, until it is opened.

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

  @javascript
  Scenario: Two hundred more scales: the form has the editors of one, and every scale can be opened and saved
    Given the CAT scale "Simulation" has 200 more subscales
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         |
      | adaptivequiz | Adaptive CATquiz | C1     | 1       | adaptivecatquiz1 |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy         | catquiz_selectfirstquestion | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer lowest skill gap | -2                          | 7                    | 0.4                       | 0.6                       | 2                       |
    When I am on the "adaptivecatquiz1" Activity page logged in as teacher1
    And I follow "Settings"
    And I wait until the page is ready
    Then the settings form has 2 feedback editors
    And the settings form carries the feedback of at least 200 more scales
    And "#page-footer" "css_element" should exist
    And "Save and display" "button" should exist
    When I click on "Feedback for “Simulation”" "text"
    And I click on "Feedback for “Simulation extra 150”" "text"
    And I wait until the page is ready
    Then the settings form has 2 feedback editors
    And I set the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation extra 150_range_1']" "xpath_element" to "Feedback for extra 150"
    And I click on "Save and display" "button"
    And "Save and display" "button" should not exist
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Feedback for “Simulation”" "text"
    And I click on "Feedback for “Simulation extra 150”" "text"
    And I wait until the page is ready
    Then the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation extra 150_range_1']" "xpath_element" matches value "Feedback for extra 150"
    And the settings form has 2 feedback editors

  @javascript
  Scenario: Switching between scales keeps what was typed, and saving keeps it for every scale
    Given the following "activities" exist:
      | activity     | name             | course | section | idnumber         |
      | adaptivequiz | Adaptive CATquiz | C1     | 1       | adaptivecatquiz1 |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy         | catquiz_selectfirstquestion | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer lowest skill gap | -2                          | 7                    | 0.4                       | 0.6                       | 2                       |
    When I am on the "adaptivecatquiz1" Activity page logged in as teacher1
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Feedback for “Simulation”" "text"
    And I set the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" to "Typed for the root"
    And I set the field "Feedback to be displayed in color bar legend" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" to "Root legend"
    And I click on "Feedback for “SimA”" "text"
    And I wait until the page is ready
    Then "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" should not exist
    And I set the field "Feedback" in the "//div[@data-name='feedback_scale_SimA_range_1']" "xpath_element" to "Typed for SimA"
    When I click on "Edit feedback for “Simulation”" "button"
    And I wait until the page is ready
    Then the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "Typed for the root"
    And the field "Feedback to be displayed in color bar legend" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "Root legend"
    And "//div[@data-name='feedback_scale_SimA_range_1']" "xpath_element" should not exist
    And the settings form has 2 feedback editors
    When I click on "Save and display" "button"
    And "Save and display" "button" should not exist
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Feedback for “Simulation”" "text"
    Then the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "Typed for the root"
    And I click on "Feedback for “SimA”" "text"
    And I wait until the page is ready
    And the field "Feedback" in the "//div[@data-name='feedback_scale_SimA_range_1']" "xpath_element" matches value "Typed for SimA"

  Scenario: Without JavaScript a scale is opened by its button, keeping what the other scale was sent with
    Given the following "activities" exist:
      | activity     | name             | course | section | idnumber         |
      | adaptivequiz | Adaptive CATquiz | C1     | 1       | adaptivecatquiz1 |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy         | catquiz_selectfirstquestion | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer lowest skill gap | -2                          | 7                    | 0.4                       | 0.6                       | 2                       |
    When I am on the "adaptivecatquiz1" Activity page logged in as teacher1
    And I follow "Settings"
    And I set the field "Feedback to be displayed in color bar legend" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" to "Root legend"
    And I press "Edit feedback for “SimB”"
    Then the settings form has 2 feedback editors
    And "//div[@data-name='feedback_scale_SimB_range_1']" "xpath_element" should exist
    And "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" should not exist
    When I press "Edit feedback for “Simulation”"
    Then the field "Feedback to be displayed in color bar legend" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "Root legend"
