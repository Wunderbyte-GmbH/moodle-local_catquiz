@local @local_catquiz @javascript
Feature: The questions of a finished attempt open in a modal on every page that shows the result (issue #139).
  The quiz progress summary offers each answered question in a modal that loads it only when it is
  opened. The participant sees their own questions with their files, also after the activity has been
  hidden or restricted; a teacher sees them for every participant; the course page with the
  [catquizfeedback] shortcode offers the same questions as the result page; a question that no longer
  exists is reported in the modal instead of a spinner that never ends; and a STACK question is shown
  as STACK renders it.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | student2 | Student2  | Test     | toolgenerator2@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
      | outsider | Outsider  | Test     | toolgenerator4@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher  | C1     | editingteacher |
    ## Two questions with an image of their own each: the file has to reach the modal.
    And the following "local_catquiz > questions" exist:
      | filepath                                                   | filename                      | course |
      | local/catquiz/tests/fixtures/catquiz_resources_mc.xml      | catquiz_resources_mc.xml      | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                                   | filename                      |
      | local/catquiz/tests/fixtures/catquiz_resources_mc.csv      | catquiz_resources_mc.csv      |
    ## TinyMCE misbehaves in acceptance tests; use a plain editor.
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |

  @javascript @accessibility
  Scenario: The participant opens the questions with their files, also once the activity is restricted or hidden
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | Classical test | 2                    | 2                    | 0.0                       | 1000.0                    | 1                  |
    ## The teacher releases the questions in the form, as in a course.
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    And I set the field "Show feedback for given responses" to "1"
    And I set the field "Indicate correctness of the given response" to "1"
    And I click on "Save and return to course" "button"
    And I log out
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Text of resource question one."
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Text of resource question two."
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    When I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    And I click on "Show question" "button" in the "Resource question one" "table_row"
    Then I should see "Text of resource question one." in the "Resource question one" "dialogue"
    And the image "Figure of question one" in the question dialogue has loaded
    ## Accessibility: the dialogue passes axe-core, takes the focus, closes with Escape and returns the
    ## focus to the button that opened it.
    And the "Resource question one" "dialogue" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
    And the focus is inside the open dialogue
    And I press the escape key
    And "Resource question one" "dialogue" should not be visible
    And the focused element is "Show question" "button" in the "Resource question one" "table_row"
    ## The chart of the tab is drawn once, not a second time over the table.
    And ".chart-image canvas + canvas" "css_element" should not exist
    And I click on "Show question" "button" in the "Resource question two" "table_row"
    And I should see "Text of resource question two." in the "Resource question two" "dialogue"
    And the image "Figure of question two" in the question dialogue has loaded
    And I click on "Close" "button" in the "Resource question two" "dialogue"
    ## A date restriction that has ended.
    When the activity "adaptivecatquiz1" is no longer available to participants
    And I open the result page of the last attempt of "student1"
    And I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    And I click on "Show question" "button" in the "Resource question two" "table_row"
    Then I should see "Text of resource question two." in the "Resource question two" "dialogue"
    And the image "Figure of question two" in the question dialogue has loaded
    And I click on "Close" "button" in the "Resource question two" "dialogue"
    ## And hidden on top of it.
    When the activity "adaptivecatquiz1" is hidden from participants
    And I open the result page of the last attempt of "student1"
    And I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    And I click on "Show question" "button" in the "Resource question one" "table_row"
    Then I should see "Text of resource question one." in the "Resource question one" "dialogue"
    And the image "Figure of question one" in the question dialogue has loaded
    And I click on "Close" "button" in the "Resource question one" "dialogue"
    ## A second modal one after the other, still hidden.
    And I click on "Show question" "button" in the "Resource question two" "table_row"
    And I should see "Text of resource question two." in the "Resource question two" "dialogue"
    And the image "Figure of question two" in the question dialogue has loaded
    ## On the network (mod_adaptivequiz #16, #18): the files come from pluginfile.php with HTTP 200 and an
    ## image, the modal HTML from the web service - for the owner, with the activity hidden.
    And the file of question "Resource question one" in the last attempt of "student1" is delivered to me
    And the file of question "Resource question two" in the last attempt of "student1" is delivered to me
    And the question "Resource question one" in the last attempt of "student1" is rendered for me
    And the question "Resource question two" in the last attempt of "student1" is rendered for me

  @javascript
  Scenario: A teacher opens a participant's questions; the course page offers the same; a missing question is reported
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | Classical test | 2                    | 2                    | 0.0                       | 1000.0                    | 1                  |
    And the following "activities" exist:
      | activity | name          | course | idnumber | content           |
      | page     | Your feedback | C1     | page1    | [catquizfeedback] |
    And the "shortcodes" filter is "on"
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    And I set the field "Show feedback for given responses" to "1"
    And I click on "Save and return to course" "button"
    And I log out
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Text of resource question two."
    And I click on "richtige Antwort" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    ## The shortcode on a course page: the same questions as on the result page.
    When I am on the "page1" "page activity" page
    And I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    And I click on "Show question" "button" in the "Resource question two" "table_row"
    Then I should see "Text of resource question two." in the "Resource question two" "dialogue"
    And the image "Figure of question two" in the question dialogue has loaded
    And I click on "Close" "button" in the "Resource question two" "dialogue"
    ## The shortcode follows the rule of the result page: the own finished attempt, also with the
    ## activity hidden (mod_adaptivequiz issues #16, #18).
    And the activity "adaptivecatquiz1" is hidden from participants
    And I am on the "page1" "page activity" page
    And I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    And I click on "Show question" "button" in the "Resource question one" "table_row"
    And I should see "Text of resource question one." in the "Resource question one" "dialogue"
    And the image "Figure of question one" in the question dialogue has loaded
    And I log out
    ## The teacher, on the review page of the attempt.
    When I log in as "teacher"
    And I visit the CAT attempt feedback page for the last attempt of "student1"
    And I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    And I click on "Show question" "button" in the "Resource question one" "table_row"
    Then I should see "Text of resource question one." in the "Resource question one" "dialogue"
    And the image "Figure of question one" in the question dialogue has loaded
    And I click on "Close" "button" in the "Resource question one" "dialogue"
    ## The reviewer with the capability, the activity still hidden: files and modal HTML on the network.
    And the file of question "Resource question one" in the last attempt of "student1" is delivered to me
    And the question "Resource question two" in the last attempt of "student1" is rendered for me
    And I log out
    ## Another participant of the course: neither the file nor the modal HTML of someone else's attempt.
    When I log in as "student2"
    Then the file of question "Resource question one" in the last attempt of "student1" is refused to me
    And the file of question "Resource question two" in the last attempt of "student1" is refused to me
    And the question "Resource question one" in the last attempt of "student1" is refused to me
    And I log out
    ## Someone without access to the course: the same.
    When I log in as "outsider"
    Then the file of question "Resource question one" in the last attempt of "student1" is refused to me
    And the question "Resource question one" in the last attempt of "student1" is refused to me
    And I log out
    And I log in as "teacher"
    And I visit the CAT attempt feedback page for the last attempt of "student1"
    And I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    ## The questions are removed while the page is open: the modal says so.
    When the questions of the last attempt of "student1" no longer exist
    And I click on "Show question" "button" in the "Resource question two" "table_row"
    Then I should see "This question of the attempt is no longer available." in the "Resource question two" "dialogue"
    And I wait until ".catquiz-graphicalsummary-table .loading-icon" "css_element" does not exist

  @javascript
  Scenario: A STACK question opens in the modal as STACK renders it, with the given answer
    Given STACK is connected to Maxima for this test
    And the following "local_catquiz > questions" exist:
      | filepath                                                   | filename                      | course |
      | local/catquiz/tests/fixtures/catquiz_resources_stack.xml   | catquiz_resources_stack.xml   | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                                   | filename                      |
      | local/catquiz/tests/fixtures/catquiz_resources_stack.csv   | catquiz_resources_stack.csv   |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | Classical test | 3                    | 3                    | 0.0                       | 1000.0                    | 1                  |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    And I set the field "Show feedback for given responses" to "1"
    And I set the field "Indicate correctness of the given response" to "1"
    And I click on "Save and return to course" "button"
    And I log out
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Text of resource question two."
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Text of the STACK question"
    And I set the field with xpath "//div[contains(@class, 'que stack')]//input[contains(@id, '_ans1')]" to "2"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    When I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    And I click on "Show question" "button" in the "Resource question STACK" "table_row"
    Then I should see "Text of the STACK question" in the "Resource question STACK" "dialogue"
    And ".modal.show .que.stack" "css_element" should exist
    And the field with xpath "//div[contains(@class, 'modal') and contains(@class, 'show')]//input[contains(@id, '_ans1')]" matches value "2"
    And ".modal.show .que.stack.correct" "css_element" should exist
    And ".modal.show .questionflag" "css_element" should not exist
