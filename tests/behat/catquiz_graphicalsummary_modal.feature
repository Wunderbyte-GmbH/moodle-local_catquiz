@local @local_catquiz @javascript
Feature: The quiz progress summary shows readable question and answer data and
  opens the question in a modal.
  As a student who finished an adaptive quiz attempt, the progress summary shows
  the real question title (not only the technical CAT label), labels the given
  answer clearly, and lets me open each answered question in a modal via the
  magnifier, which must never leave a hanging spinner.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "local_catquiz > questions" exist:
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                          | filename             |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |
    ## TinyMCE misbehaves in acceptance tests; use a plain editor.
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy         | catquiz_selectfirstquestion | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions | catquiz_showquestion | catquiz_showquestionresponse |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer lowest skill gap | -2                          | 2                    | 4                    | 0.0                       | 1000.0                    | 2                       | 1                    | 1                            |
    ## Round-trip the generated CAT settings through the activity form so the
    ## adaptivequiz integration normalises and reserialises them.
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: The progress summary renders the labelled answered-question data
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "richtige Antwort" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    ## Issue #85: the tab loads its content when it is opened.
    And I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    ## The quiz-progress-summary feedback renders the answered-question table with
    ## per-question response data. Assert on DOM presence to secure the end-to-end data mapping - the
    ## responsive result table, the "Given answer:" label span, and the chosen
    ## answer value carried in the response-summary span. The magnifier button and
    ## its modal open/close behaviour - including the "no hanging spinner"
    ## guarantee - are covered by the Jest unit test in
    ## tests/jest/graphicalsummary.test.js (the button is gated on the
    ## catquiz_showquestion setting, which does not survive the adaptivequiz
    ## settings round-trip in this Behat environment).
    Then ".catquiz-graphicalsummary-table" "css_element" should exist
    And ".catquiz-response-answerlabel" "css_element" should exist
    And ".catquiz-responsesummary" "css_element" should exist
    ## The correctness indicator is gated on catquiz_showquestionresponse and is
    ## rendered as an icon in the column right after the question number.
    And ".catquiz-col-correctness" "css_element" should exist
    And ".catquiz-response-icon" "css_element" should exist

  @javascript @accessibility
  Scenario: The question dialogue of the progress summary is opened, used and left with the keyboard (issue #30)
    ## The magnifier needs catquiz_showquestion, which the generator does not carry through the
    ## round trip - the teacher sets it in the form, as in a course.
    Given I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I expand all fieldsets
    And I set the field "Show feedback for given responses" to "1"
    And I click on "Save and return to course" "button"
    And I log out
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "richtige Antwort" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    When I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    Then the page has no duplicate ids
    And the ".feedbackcontent-graphicalsummary" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
    ## Open: the dialogue takes the focus and passes axe-core.
    When I click on "(//table[contains(@class, 'catquiz-graphicalsummary-table')]//button[@aria-label='Show question'])[1]" "xpath_element"
    And I wait until ".modal.show .modal-dialog" "css_element" exists
    Then the focus is inside the open dialogue
    And the ".modal.show .modal-dialog" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
    And the page has no duplicate ids
    ## Tab stays in the dialogue.
    When I press the tab key
    Then the focus is inside the open dialogue
    When I press the tab key
    Then the focus is inside the open dialogue
    ## Escape closes it and gives the focus back to the button that opened it.
    When I press the escape key
    Then ".modal.show" "css_element" should not exist
    And the focused element is "(//table[contains(@class, 'catquiz-graphicalsummary-table')]//button[@aria-label='Show question'])[1]" "xpath_element"
    ## A second dialogue from the next button: the same behaviour, no ids of the first left behind twice.
    When I click on "(//table[contains(@class, 'catquiz-graphicalsummary-table')]//button[@aria-label='Show question'])[2]" "xpath_element"
    And I wait until ".modal.show .modal-dialog" "css_element" exists
    Then the focus is inside the open dialogue
    And the page has no duplicate ids
    When I press the escape key
    Then ".modal.show" "css_element" should not exist
    And the focused element is "(//table[contains(@class, 'catquiz-graphicalsummary-table')]//button[@aria-label='Show question'])[2]" "xpath_element"

  @javascript
  Scenario: The quiz history of an attempt stored in the older format is drawn (issue #152)
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "richtige Antwort" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I log out
    ## The rows carry the ability as personability, as before personability_after existed.
    And the quiz history of the last CAT attempt of "student1" is stored in the older format
    When I log in as "student1"
    And I open the result page of the last attempt of "student1"
    And I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    Then ".feedbackcontent-graphicalsummary .chart-image canvas" "css_element" should exist
    And "[data-region='local_catquiz-feedbackarea-notice']" "css_element" should not exist
    And I should not see "could not be loaded"
    ## The ability of every answered question is shown, none as an empty cell.
    And "//table[contains(@class, 'catquiz-graphicalsummary-table')]//tbody/tr[td[contains(@class, 'catquiz-col-ability')][normalize-space(.)='']]" "xpath_element" should not exist
    And "//table[contains(@class, 'catquiz-graphicalsummary-table')]//tbody/tr[4]" "xpath_element" should exist
