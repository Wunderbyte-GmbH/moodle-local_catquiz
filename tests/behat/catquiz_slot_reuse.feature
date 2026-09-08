@local @local_catquiz @javascript
Feature: Reloading an unanswered item does not create a duplicate slot.
  As a student, reloading or re-opening an unanswered question re-shows the same
  item in the same question-usage slot rather than creating a second slot for it,
  so the question usage and the CAT progress stay consistent (issue #6).

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
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy         | catquiz_selectfirstquestion | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer lowest skill gap | -2                          | 2                    | 4                    | 0.0                       | 1000.0                    | 2                       |
    ## Round-trip the generated CAT settings through the activity form. The
    ## adaptivequiz integration normalises and reserialises the settings here;
    ## without this step the adapter can finish after the first question. Keep
    ## the stopping thresholds non-binding so maxquestions = 4 defines the end.
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: Reloading an unanswered question re-shows the same item
    ## Without slot reuse the reload would add a second QUBA slot for the same
    ## item; with it, the very same question is shown again.
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I reload the page
    And I wait until the page is ready
    Then I should see "Question 1"
    And I should not see "Question 2"

  @javascript
  Scenario: Reloading mid-attempt still yields exactly the configured length
    ## A reload before answering must not inflate the number of administered
    ## items: the attempt still ends after the configured four questions.
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    ## Re-enter the attempt through the activity (a GET) before reloading, so the
    ## reload does not re-POST the previous answer - Moodle blocks such
    ## out-of-sequence resubmission by design. Resuming re-renders Question 2.
    And I am on the "adaptivecatquiz1" Activity page
    And I wait until the page is ready
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 2"
    And I reload the page
    And I wait until the page is ready
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should not see "Question 5"

  @javascript
  Scenario: Navigating back and forward does not create a duplicate slot
    ## The browser Back button re-enters an already answered question. The slot
    ## guard must recognise the existing active slot instead of adding a new one,
    ## so the attempt still ends after exactly the configured four questions.
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    ## Go back to the previous page and forward again.
    And I press the "back" button in the browser
    And I wait until the page is ready
    And I press the "forward" button in the browser
    And I wait until the page is ready
    ## Re-enter through the activity so no answer is re-posted out of sequence.
    And I am on the "adaptivecatquiz1" Activity page
    And I wait until the page is ready
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 2"
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should not see "Question 5"

  @javascript
  Scenario: Re-entering an unanswered item repeatedly keeps one slot
    ## Repeatedly re-opening the attempt while an item is unanswered must keep
    ## re-showing that same item. Each re-entry runs the item administration
    ## again, so without the slot guard every re-entry would add another slot and
    ## the attempt would run past its configured length.
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 2"
    And I am on the "adaptivecatquiz1" Activity page
    And I wait until the page is ready
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 2"
    And I am on the "adaptivecatquiz1" Activity page
    And I wait until the page is ready
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 2"
    And I should not see "Question 3"
    ## Finish the attempt: the repeated re-entries must not have consumed items.
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 4"
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should not see "Question 5"
