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
      | catmodel                                              | empty |
    And I wait until the page is ready
    And I set the following fields to these values:
      | Name             | Adaptive CATquiz  |
      | ID number        | adaptivecatquiz1  |
      | catmodel         | Catquiz CAT model |
      | catquiz_selectteststrategy | 1 |
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
    Given the following "activities" exist:
      | activity     | name             | course | section | idnumber         |
      | adaptivequiz | Adaptive CATquiz | C1     | 1       | adaptivecatquiz1 |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher1
    And I follow "Settings"
    ## And I wait until the page is ready
    And I set the following fields to these values:
      | catmodel                                              | empty |
    And I wait until the page is ready
    And I set the following fields to these values:
      | catmodel                                              | Catquiz CAT model |
      | Select CAT scale                                      | Simulation        |
      | catquiz_selectteststrategy                            | 1                 |
      | catquiz_standarderrorgroup[catquiz_standarderror_min] | 0.4               |
      | catquiz_standarderrorgroup[catquiz_standarderror_max] | 0.6               |
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
    And I follow "Settings"
    ## Verify disabled scale and sub-scale after save
    And the field with xpath "//input[@data-name='SimA']" matches value "checked"
    And the field with xpath "//input[@data-name='SimA02']" matches value "checked"
    And the field with xpath "//input[@data-name='SimB']" matches value "checked"
    And the field with xpath "//input[@data-name='SimB02']" matches value "checked"
    And the field with xpath "//input[@data-name='SimB03']" matches value ""
    And the field with xpath "//input[@data-name='SimC']" matches value ""
    And I should not see "SimC01" in the "#id_catquiz_headercontainer" "css_element"
    And I should not see "SimC02" in the "#id_catquiz_headercontainer" "css_element"

  @javascript
  Scenario: CATquiz settings: admin setup custom minmax abilities and teacher validate it
    Given I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    And I click on "CAT scales" "link" in the "#region-main" "css_element"
    And I click on "Edit" "link" in the "[data-name=\"Simulation\"]" "css_element"
    And I set the following fields to these values:
      | Person ability minimum: | -6 |
      | Person ability maximum: | 6  |
    And I press "Save changes"
    And I log out
    When the following "activities" exist:
      | activity     | name             | course | section | idnumber         |
      | adaptivequiz | Adaptive CATquiz | C1     | 1       | adaptivecatquiz1 |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy         | catquiz_selectfirstquestion | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer lowest skill gap | startwitheasiestquestion    | 7                    | 0.4                       | 0.6                       | 3                       |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher1
    And I follow "Settings"
    And I click on "Feedback for \"Simulation\"" "text"
    Then I should see "-6" in the "//div[@data-name='feedback_scale_Simulation_range_1']//div[@id='fitem_id_lowest_limit']" "xpath_element"
    And the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "-2"
    And the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "-2"
    And the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "2"
    And the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_3']" "xpath_element" matches value "2"
    And I should see "6" in the "//div[@data-name='feedback_scale_Simulation_range_3']//div[@id='fitem_id_highestvalue']" "xpath_element"

  @javascript
  Scenario: CATquiz settings: teacher setup question settings and validate it
    Given the following "activities" exist:
      | activity     | name             | course | section | idnumber         |
      | adaptivequiz | Adaptive CATquiz | C1     | 1       | adaptivecatquiz1 |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher1
    And I follow "Settings"
    And I set the following fields to these values:
      | catmodel                                              | empty |
    And I wait until the page is ready
    And I set the following fields to these values:
      | catmodel                                   | Catquiz CAT model                |
      | Select CAT scale                           | Simulation                       |
      | Passing level in %                         | 500                              |
      | Purpose of test                            | Infer lowest skill gap           |
      | Activate pilot mode                        | 1                                |
      | Proportion of questions to be piloted in % | 20                               |
      | Start new test                             | 1                                |
      | Otherwise start                            | with a medium difficult question |
      | Limit time for attempt                     | 1                                |
      ## Intentional error - no time values
      ## Intentional error min > max
      | maxquestionsscalegroup[catquiz_minquestionspersubscale] | 3 |
      | maxquestionsscalegroup[catquiz_maxquestionspersubscale] | 1 |
      ## Intentional error min > max
      | maxquestionsgroup[catquiz_minquestions] | 10 |
      | maxquestionsgroup[catquiz_maxquestions] | 3  |
      ## Intentional error - catquiz_standarderror_max out of range
      | catquiz_standarderrorgroup[catquiz_standarderror_min] | 0.4 |
      | catquiz_standarderrorgroup[catquiz_standarderror_max] | 2   |
    When I click on "Save and display" "button"
    ## Errors validation 1: invalid numbers or min > max or no values
    Then I should see "Input a number from 0 to 100" in the "#fitem_id_catquiz_passinglevel" "css_element"
    And I should see "Minimum must be less than maximum" in the "#fgroup_id_maxquestionsgroup" "css_element"
    And I should see "Minimum must be less than maximum" in the "#fgroup_id_maxquestionsscalegroup" "css_element"
    ## Decision - larger than 1 values allowed somehow
    ## And I should see "Please enter values between 0 and 1." in the "#fgroup_id_catquiz_standarderrorgroup" "css_element"
    And I should see "Input at least one value of time limit" in the "#fgroup_id_catquiz_timelimitgroup" "css_element"
    And I set the following fields to these values:
      ## Fix errors
      | Passing level in %                                      | 50 |
      ##| catquiz_standarderrorgroup[catquiz_standarderror_max] | 0.6 |
      ## Intentional error catquiz_minquestionspersubscale > catquiz_maxquestions
      | maxquestionsscalegroup[catquiz_minquestionspersubscale] | 15 |
      | maxquestionsscalegroup[catquiz_maxquestionspersubscale] | 30 |
      | maxquestionsgroup[catquiz_minquestions]                 | 3  |
      | maxquestionsgroup[catquiz_maxquestions]                 | 10 |
      ## Intentional error - incorrect time values
      | catquiz_timelimitgroup[catquiz_maxtimeperattempt]       | 5   |
      | catquiz_timelimitgroup[catquiz_timeselect_attempt]      | min |
      | catquiz_timelimitgroup[catquiz_maxtimeperitem]          | 15  |
      | catquiz_timelimitgroup[catquiz_timeselect_item]         | min |
    And I click on "Save and display" "button"
    ## Errors validation 2
    ## Validation for min questions per scale <= max questions per test.
    And I should see "Per scale minimum must be less than per test maximum" in the "#fgroup_id_maxquestionsgroup" "css_element"
    ## not implemented yet - validation for time - maximum time per attempt must be greater than maximum time per itemif
    ## And I should see "Maximum time per attempt must be greater than maximum time per item" in the "#fgroup_id_catquiz_timelimitgroup" "css_element"
    And I set the following fields to these values:
      | maxquestionsscalegroup[catquiz_minquestionspersubscale] | 1 |
      | maxquestionsscalegroup[catquiz_maxquestionspersubscale] | 3 |
      | catquiz_timelimitgroup[catquiz_maxtimeperattempt]       | 5 |
      | catquiz_timelimitgroup[catquiz_maxtimeperitem]          | 1 |
    And I click on "Save and display" "button"
    And I follow "Settings"
    ## Verify all root catscales active by default
    And the following fields match these values:
      | catmodel                                   | Catquiz CAT model         |
      | Select CAT scale                           | Simulation                |
      | Passing level in %                         | 50                        |
      | Purpose of test                            | Infer lowest skill gap    |
      | Activate pilot mode                        | 1                         |
      | Proportion of questions to be piloted in % | 20                        |
      | Start new test                             | 1                         |
      | catquiz_standarderrorgroup[catquiz_standarderror_min]   | 0.4 |
      ##| catquiz_standarderrorgroup[catquiz_standarderror_max] | 0.6 |
      | catquiz_standarderrorgroup[catquiz_standarderror_max]   | 2   |
      | maxquestionsscalegroup[catquiz_minquestionspersubscale] | 1   |
      | maxquestionsscalegroup[catquiz_maxquestionspersubscale] | 3   |
      | maxquestionsgroup[catquiz_minquestions]                 | 3   |
      | maxquestionsgroup[catquiz_maxquestions]                 | 10  |
      | Limit time for attempt                                  | 1   |
      | catquiz_timelimitgroup[catquiz_maxtimeperattempt]       | 5   |
      | catquiz_timelimitgroup[catquiz_timeselect_attempt]      | min |
      | catquiz_timelimitgroup[catquiz_maxtimeperitem]          | 1   |
      | catquiz_timelimitgroup[catquiz_timeselect_item]         | min |

  @javascript
  Scenario: CATquiz settings: teacher setup basic feedback settings
    Given the following "activities" exist:
      | activity     | name             | course | section | idnumber         |
      | adaptivequiz | Adaptive CATquiz | C1     | 1       | adaptivecatquiz1 |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher1
    And I follow "Settings"
    ## And I wait until the page is ready
    And I set the following fields to these values:
      | catmodel                                              | empty |
    And I wait until the page is ready
    And I set the following fields to these values:
      | catmodel                                              | Catquiz CAT model |
      | Select CAT scale                                      | Simulation        |
      | catquiz_selectteststrategy                            | 1                 |
      | catquiz_standarderrorgroup[catquiz_standarderror_min] | 0.4               |
      | catquiz_standarderrorgroup[catquiz_standarderror_max] | 0.6               |
    ## Delay required to settle CAT scale
    When I wait until the page is ready
    And the field "Number of ability ranges" matches value "2"
    ## Update feedback defaults and chak it for root catscale, range 2
    And I fill in the "autocomplete" element number "2" with the dynamic identifier "fitem_id_catquiz_courses_" with "Course 1"
    ## No lower limit for range 1 so index is 1
    And I fill in the "input" element number "1" with the dynamic identifier "id_feedback_scaleid_limit_lower_" with "-1"
    And I fill in the "input" element number "2" with the dynamic identifier "id_enrolment_message_checkbox_" with "1"
    And I fill in the "editor" element number "2" with the dynamic identifier "id_feedbackeditor_scaleid_" with "my text is here"
    And I fill in the "wb_colourpicker" element number "2" with the dynamic identifier "fitem_id_wb_colourpicker_" with "6"
    ## The data-attribute from form's custom 'div'-delimited group has been used
    ## Lowest and highest limits are not editable
    ## Update feedback defaults and chek it for root catscale, range 1
    And I should see "Feedback for range 1" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element"
    And I should see "-5" in the "//div[@data-name='feedback_scale_Simulation_range_1']//div[@id='fitem_id_lowest_limit']" "xpath_element"
    And the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "-0"
    And I set the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" to "Feedback-Simulation_range_1"
    And I set the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" to "-1"
    And the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "-1"
    ## Update feedback defaults and check it for root catscale, range 2
    And I should see "Feedback for range 2" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element"
    And I should see "5" in the "//div[@data-name='feedback_scale_Simulation_range_2']//div[@id='fitem_id_highestvalue']" "xpath_element"
    And I set the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" to "Feedback-Simulation_range_2"
    ## Intentional error
    And I set the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" to "1"
    And the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "1"

    And I should not see "Feedback for range 3" in the "//div[contains(@aria-labelledby, 'catquiz_feedback_header_')]" "xpath_element"
    ## Check visibility of feedback form links for other catscales
    And I should see "Feedback for \"SimA\"" in the "//div[contains(@aria-labelledby, 'catquiz_feedback_header_')]" "xpath_element"
    And I should see "Feedback for \"SimB\"" in the "//div[contains(@aria-labelledby, 'catquiz_feedback_header_')]" "xpath_element"
    And I should see "Feedback for \"SimC\"" in the "//div[contains(@aria-labelledby, 'catquiz_feedback_header_')]" "xpath_element"
    ## Save and verify feedback configuration
    And I click on "Save and display" "button"
    And I wait until the page is ready
    And I click on "Feedback for \"Simulation\"" "text"
    ## Error "no gap" expected
    And I should see "No gap in the feedbackrange allowed. Please start min in new range with max value of last range." in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element"
    And I set the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" to "-1"
    And I click on "Save and display" "button"
    And I follow "Settings"
    And I wait until the page is ready
    Then I click on "Feedback for \"Simulation\"" "text"
    And I wait until the page is ready
    ## Range 2 - wb_colourpicker:
    ## -direct xpath access
    And the "class" attribute of "(//div[contains(@id, 'fitem_id_wb_colourpicker_')])[2]//span[@data-colour='6']" "xpath_element" should contain "selected"
    ## Not visibla after save - is it a bug?
    ##And I should see "6" in the "(//div[contains(@id, 'fitem_id_wb_colourpicker_')])[2]//span[@class='colourselectnotify']" "xpath_element"

    ## Range 2 - autocomplete:
    ## -direct xpath access
    And I should see "Course 1" in the "(//div[contains(@id, 'fitem_id_catquiz_courses_')])[2]//div[contains(@id, 'form_autocomplete_selection-')]" "xpath_element"
    ## -the data-attribute from form's custom 'div'-delimited group has been used
    And I should see "Course 1" in the "//div[@data-name='feedback_scale_Simulation_range_2']//div[contains(@id, 'form_autocomplete_selection-')]" "xpath_element"
    ## -native selection does not work with autocomplete
    ##And the field "Subscription to a course" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "Course 1"

    ## Ranges 1 and 2 - other fields, mixed access:
    And the field "Inform participants about group or course enrollment using the standard text." in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "1"
    And the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "Feedback-Simulation_range_1"
    And the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "Feedback-Simulation_range_2"
    ## Lowest and highest limits looks like not saved - calculated falues being forced...
    And I should see "-5" in the "//div[@data-name='feedback_scale_Simulation_range_1']//div[@id='fitem_id_lowest_limit']" "xpath_element"
    And the field "Upper limit" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" matches value "-1"
    And the field "Lower limit" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" matches value "-1"
    And I should see "5" in the "//div[@data-name='feedback_scale_Simulation_range_2']//div[@id='fitem_id_highestvalue']" "xpath_element"
    And I log out
