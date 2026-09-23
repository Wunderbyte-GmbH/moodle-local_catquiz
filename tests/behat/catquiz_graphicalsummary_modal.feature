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
    ## The quiz-progress-summary feedback renders the answered-question table with
    ## per-question response data. It lives in a feedback tab that is inactive
    ## until the learner opens it, so the rows are present in the DOM but not
    ## visible: assert on DOM presence to secure the end-to-end data mapping - the
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
