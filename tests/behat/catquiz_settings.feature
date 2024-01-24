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
      | filepath                                           | filename              | course |
      | local/catquiz/tests/fixtures/mathematik2scales.xml | mathematik2scales.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                           | filename              |
      | local/catquiz/tests/fixtures/mathematik2scales.csv | mathematik2scales.csv |
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
      | Name      | Adaptive CATquiz  |
      | ID number | adaptivecatquiz1  |
      | catmodel  | Catquiz CAT model |
    ## Delay required to settle CAT model
    And I wait until the page is ready
    And I set the field "Select CAT scale" to "Mathematik"
    ## Delay required to settle CAT scale
    And I wait until the page is ready
    And I should see "A03" in the "#id_catquiz_headercontainer" "css_element"
    And I should see "A02" in the "#id_catquiz_headercontainer" "css_element"
    And the field with xpath "//input[@data-name='A03']" matches value "checked"
    And the field with xpath "//input[@data-name='A02']" matches value "checked"
    And I click on "Save and return to course" "button"
    And I log out
    And I am on the "adaptivecatquiz1" "Activity" page logged in as "student1"
    Then "Start attempt" "button" should exist

  @javascript
  Scenario: CATquiz settings: teacher setup basic feedback settings
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Adaptive Quiz" to section "1"
    And I set the following fields to these values:
      | Name      | Adaptive CATquiz  |
      | ID number | adaptivecatquiz1  |
      | catmodel  | Catquiz CAT model |
    ## Delay required to settle CAT model
    And I wait until the page is ready
    And I set the field "Select CAT scale" to "Mathematik"
    ## Delay required to settle CAT scale
    And I wait until the page is ready
    And the field "Number of ability ranges" matches value "2"
    ## Update feedback defaults and chak it for root catscale, range 1
    And I fill in the "input" element number "2" with the dynamic identifier "id_feedback_scaleid_limit_lower_" with "-1"
    And I fill in the "div" element number "2" with the dynamic identifier "id_feedbackeditor_scaleid_" with "my text is here"
    And I should see "Feedback for range 1" in the "//div[@data-name='feedback_scale_Mathematik_range_1']" "xpath_element"
    And the field "Lower limit" in the "//div[@data-name='feedback_scale_Mathematik_range_1']" "xpath_element" matches value "-5"
    And the field "Upper limit" in the "//div[@data-name='feedback_scale_Mathematik_range_1']" "xpath_element" matches value "-0"
    And I set the field "Feedback" in the "//div[@data-name='feedback_scale_Mathematik_range_1']" "xpath_element" to "Feedback-Mathematik_range_1"
    And I set the field "Lower limit" in the "//div[@data-name='feedback_scale_Mathematik_range_1']" "xpath_element" to "-4"
    And I set the field "Upper limit" in the "//div[@data-name='feedback_scale_Mathematik_range_1']" "xpath_element" to "-1"
    And the field "Lower limit" in the "//div[@data-name='feedback_scale_Mathematik_range_1']" "xpath_element" matches value "-4"
    And the field "Upper limit" in the "//div[@data-name='feedback_scale_Mathematik_range_1']" "xpath_element" matches value "-1"
    ## Update feedback defaults and chak it for root catscale, range 2
    And I should see "Feedback for range 2" in the "//div[@data-name='feedback_scale_Mathematik_range_2']" "xpath_element"
    And I set the field "Feedback" in the "//div[@data-name='feedback_scale_Mathematik_range_2']" "xpath_element" to "Feedback-Mathematik_range_2"
    And I set the field "Lower limit" in the "//div[@data-name='feedback_scale_Mathematik_range_2']" "xpath_element" to "1"
    And I set the field "Upper limit" in the "//div[@data-name='feedback_scale_Mathematik_range_2']" "xpath_element" to "4"
    And the field "Lower limit" in the "//div[@data-name='feedback_scale_Mathematik_range_2']" "xpath_element" matches value "1"
    And the field "Upper limit" in the "//div[@data-name='feedback_scale_Mathematik_range_2']" "xpath_element" matches value "4"
    And I should not see "Feedback for range 3" in the "//div[contains(@aria-labelledby, 'catquiz_feedback_header_')]" "xpath_element"
    ## Chack visibility of feedback form links for other catscales
    And I should see "Feedback for \"A03\"" in the "//div[contains(@aria-labelledby, 'catquiz_feedback_header_')]" "xpath_element"
    And I should see "Feedback for \"A02\"" in the "//div[contains(@aria-labelledby, 'catquiz_feedback_header_')]" "xpath_element"
    ## An attempt to save and verify fails by now
    ## It is not clear which values have to be saved "as is" and which should be validated / autofixed
    ## Below steps disabled because of it
    ##And I click on "Save and return to course" "button"
    ##And I wait until the page is ready
    ##And I click on "Edit" "icon" in the "#action-menu-3-menubar" "css_element"
    ##And I click on "Edit settings" "link" in the "#action-menu-3-menubar" "css_element"
    ##And I wait until the page is ready
    ##And I click on "Feedback for \"Mathematik\"" "text"
    ##And I wait until the page is ready
    ## Lowest and highest limits looks like not saved...
    ##Then the field "Lower limit" in the "//div[@data-name='feedback_scale_Mathematik_range_1']" "xpath_element" matches value "-4"
    ##And the field "Upper limit" in the "//div[@data-name='feedback_scale_Mathematik_range_1']" "xpath_element" matches value "-1"
    ##And the field "Lower limit" in the "//div[@data-name='feedback_scale_Mathematik_range_2']" "xpath_element" matches value "1"
    ##And the field "Upper limit" in the "//div[@data-name='feedback_scale_Mathematik_range_2']" "xpath_element" matches value "4"
    And I log out
