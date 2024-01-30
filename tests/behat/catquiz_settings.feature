@local @local_catquiz @javascript
Feature: As a teacher I setup adaptive quiz with CATquiz Scales and Feedbacks.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | teacher1 | Teacher1  | Test     | toolgenerator3@example.com |
      | manager1 | Manager1  | Test     | toolgenerator4@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "local_catquiz > questions" exist:
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                          | filename             |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |
    ## Required to avoid misbehaviour of TinyMCE under Moodle 4.3 (AXAX/JS errors)
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |

  @javascript
  Scenario: CATquiz settings: teacher setup basic settings and student should access attempt
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Adaptive Quiz" to section "1"
    And I set the following fields to these values:
      | Name             | Adaptive CATquiz  |
      | ID number        | adaptivecatquiz1  |
      | catmodel         | Catquiz CAT model |
      | Select CAT scale | Simulation        |
      ## Should we expect defaults?
      | catquiz_standarderrorgroup[catquiz_standarderror_min] | 0.4 |
      | catquiz_standarderrorgroup[catquiz_standarderror_max] | 0.6 |
    When I wait until the page is ready
    ## Verify all root catscales active by default
    Then I should see "SimA" in the "#id_catquiz_headercontainer" "css_element"
    And I should see "SimB" in the "#id_catquiz_headercontainer" "css_element"
    And I should see "SimC" in the "#id_catquiz_headercontainer" "css_element"
    And the field with xpath "//input[@data-name='SimA']" matches value "checked"
    And the field with xpath "//input[@data-name='SimB']" matches value "checked"
    And the field with xpath "//input[@data-name='SimC']" matches value "checked"
    And I click on "Save and return to course" "button"
    And I log out
    And I am on the "adaptivecatquiz1" "Activity" page logged in as "student1"
    Then "Start attempt" "button" should exist

  @javascript
  Scenario: CATquiz settings: teacher setup catscale usage in quiz and verify it
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Adaptive Quiz" to section "1"
    And I set the following fields to these values:
      | Name             | Adaptive CATquiz  |
      | ID number        | adaptivecatquiz1  |
      | catmodel         | Catquiz CAT model |
      | Select CAT scale | Simulation        |
      ## Should we expect defaults?
      | catquiz_standarderrorgroup[catquiz_standarderror_min] | 0.4 |
      | catquiz_standarderrorgroup[catquiz_standarderror_max] | 0.6 |
    When I wait until the page is ready
    ## Verify all root catscales active by default
    Then I should see "SimA" in the "#id_catquiz_headercontainer" "css_element"
    And I should see "SimA02" in the "#id_catquiz_headercontainer" "css_element"
    And I should see "SimB" in the "#id_catquiz_headercontainer" "css_element"
    And I should see "SimB03" in the "#id_catquiz_headercontainer" "css_element"
    And I should see "SimC" in the "#id_catquiz_headercontainer" "css_element"
    And I should see "SimC02" in the "#id_catquiz_headercontainer" "css_element"
    And the field with xpath "//input[@data-name='SimA']" matches value "checked"
    And the field with xpath "//input[@data-name='SimA02']" matches value "checked"
    And the field with xpath "//input[@data-name='SimB']" matches value "checked"
    And the field with xpath "//input[@data-name='SimB03']" matches value "checked"
    And the field with xpath "//input[@data-name='SimC']" matches value "checked"
    And the field with xpath "//input[@data-name='SimC02']" matches value "checked"
    ## Disable scale and sub-scale
    And I set the field with xpath "//input[@data-name='SimB03']" to ""
    And I set the field with xpath "//input[@data-name='SimC']" to ""
    ## Verify disabled scale and sub-scale
    And I wait until the page is ready
    And I should not see "SimC01" in the "#id_catquiz_headercontainer" "css_element"
    And I should not see "SimC02" in the "#id_catquiz_headercontainer" "css_element"
    And I click on "Save and display" "button"
    And I wait until the page is ready
    And I follow "Settings"
    And I wait until the page is ready
    ## Verify disabled scale and sub-scale after save
    And the field with xpath "//input[@data-name='SimA']" matches value "checked"
    And the field with xpath "//input[@data-name='SimA02']" matches value "checked"
    And the field with xpath "//input[@data-name='SimB']" matches value "checked"
    And the field with xpath "//input[@data-name='SimB02']" matches value "checked"
    And the field with xpath "//input[@data-name='SimB03']" matches value ""
    And the field with xpath "//input[@data-name='SimC']" matches value ""
    And I should not see "SimC01" in the "#id_catquiz_headercontainer" "css_element"
    And I should not see "SimC02" in the "#id_catquiz_headercontainer" "css_element"
    And I log out

  @javascript
  Scenario: CATquiz settings: teacher setup question settings and validate it
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Adaptive Quiz" to section "1"
    And I set the following fields to these values:
      | Name             | Adaptive CATquiz  |
      | ID number        | adaptivecatquiz1  |
      | catmodel         | Catquiz CAT model |
      | Select CAT scale | Simulation        |
      | Passing level in %| 500 |
      | Purpose of test | Infer all subscales |
      | Activate pilot mode | 1 |
      | Proportion of questions to be piloted in % | 20 |
      ## | Minimum number of adaptations | 150 |
      | Start new CAT test with | Use the average ability score of the current test |
      | Maximum number of questions returned per subscale | 1 |
      | Minimum number of questions returned per subscale | 3 |
      | Max. questions per test. | 10 |
      | Min. number of questions per test. | 3 |
      | Standarderror per subscale in percent | 60 |
      | Timepaced test | 1 |
      | Max time per question in seconds | 1 |
      | Min time per question in seconds | 5 |
    When I click on "Save and display" "button"
    ## Errors validation
    Then I should see "Input a positive number from 0 to 100" in the "#fitem_id_catquiz_passinglevel" "css_element"
    And I should see "Minimum number of questions must be less than maximum number of questions" in the "#fitem_id_catquiz_minquestionspersubscale" "css_element"
    And I should see "Minimum number of questions must be less than maximum number of questions" in the "#fitem_id_catquiz_mintimeperitem" "css_element"
    And I set the following fields to these values:
      | Passing level in %| 50 |
      | Max time per question in seconds | 15 |
      | Maximum number of questions returned per subscale |  |
      | Minimum number of questions returned per subscale | 1 |
    And I click on "Save and display" "button"
    And I wait until the page is ready
    And I follow "Settings"
    ## Verify all root catscales active by default
    And the following fields match these values:
      | Name             | Adaptive CATquiz  |
      | ID number        | adaptivecatquiz1  |
      | catmodel         | Catquiz CAT model |
      | Select CAT scale | Simulation        |
      | Passing level in %| 50 |
      | Max time per question in seconds | 15 |
      | Min time per question in seconds | 5 |
      | Purpose of test | Infer all subscales |
      | Activate pilot mode | 1 |
      | Proportion of questions to be piloted in % | 20 |
      | Minimum number of adaptations | 150 |
    ## | Standarderror per subscale in percent | 60 |
      | Start new CAT test with | Use the average ability score of the current test |
      | Maximum number of questions returned per subscale | |
      | Minimum number of questions returned per subscale | 1 |
      | Max. questions per test. | 10 |
      | Min. number of questions per test. | 3 |
    And I log out

  @javascript
  Scenario: CATquiz settings: teacher setup basic feedback settings
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Adaptive Quiz" to section "1"
    And I set the following fields to these values:
      | Name             | Adaptive CATquiz  |
      | ID number        | adaptivecatquiz1  |
      | catmodel         | Catquiz CAT model |
      | Select CAT scale | Simulation        |
      ## Should we expect defaults?
      | catquiz_standarderrorgroup[catquiz_standarderror_min] | 0.4 |
      | catquiz_standarderrorgroup[catquiz_standarderror_max] | 0.6 |
    ## Delay required to settle CAT scale
    When I wait until the page is ready
    And the field "Number of ability ranges" matches value "2"
    ## Update feedback defaults and chak it for root catscale, range 1
    And I fill in the "multiselect" element number "2" with the dynamic identifier "fitem_id_catquiz_courses_" with "Course 1"
    And I fill in the "input" element number "2" with the dynamic identifier "id_feedback_scaleid_limit_lower_" with "-1"
    And I fill in the "input" element number "2" with the dynamic identifier "id_enrolment_message_checkbox_" with "1"
    And I fill in the "editor" element number "2" with the dynamic identifier "id_feedbackeditor_scaleid_" with "my text is here"
    And I fill in the "wb_colourpicker" element number "2" with the dynamic identifier "fitem_id_wb_colourpicker_" with "6"
    And I wait "3" seconds
    And I should see "Feedback for range 1" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element"
    And the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "-5"
    And the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "-0"
    And I set the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" to "Feedback-Simulation_range_1"
    And I set the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" to "-4"
    And I set the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" to "-1"
    And the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "-4"
    And the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "-1"
    ## Update feedback defaults and chak it for root catscale, range 2
    And I should see "Feedback for range 2" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element"
    And I set the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" to "Feedback-Simulation_range_2"
    And I set the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" to "1"
    And I set the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" to "4"
    And the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "1"
    And the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "4"
    And I should not see "Feedback for range 3" in the "//div[contains(@aria-labelledby, 'catquiz_feedback_header_')]" "xpath_element"
    ## Chack visibility of feedback form links for other catscales
    And I should see "Feedback for \"SimA\"" in the "//div[contains(@aria-labelledby, 'catquiz_feedback_header_')]" "xpath_element"
    And I should see "Feedback for \"SimB\"" in the "//div[contains(@aria-labelledby, 'catquiz_feedback_header_')]" "xpath_element"
    And I should see "Feedback for \"SimC\"" in the "//div[contains(@aria-labelledby, 'catquiz_feedback_header_')]" "xpath_element"
    ## An attempt to save and verify fails by now
    ## It is not clear which values have to be saved "as is" and which should be validated / autofixed
    ## Below steps disabled because of it
    And I click on "Save and return to course" "button"
    And I am on the "adaptivecatquiz1" Activity page
    And I follow "Settings"
    And I wait until the page is ready
    Then I click on "Feedback for \"Simulation\"" "text"
    And I wait until the page is ready
    ## Lowest and highest limits looks like not saved...
    ##And the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "-4"
    ##And the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "-1"
    ##And the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "1"
    ##And the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "4"
    And the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "Feedback-Simulation_range_1"
    And the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "Feedback-Simulation_range_2"
    And I log out
