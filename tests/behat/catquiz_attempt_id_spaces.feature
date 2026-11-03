@local @local_catquiz @javascript
Feature: CAT attempts and activity attempts are numbered apart, and a saved state is only used by its own attempt (issues #95, #96, #101).
  The CAT attempt has an id of its own, next to the id of the activity's attempt. A test runs through to
  its feedback when the two differ, also when the activity attempt of one person has the number of the
  CAT attempt of another. A saved state that does not belong to its attempt is refused with a message.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | student2 | Student2  | Test     | toolgenerator2@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "local_catquiz > questions" exist:
      | filepath                                              | filename                 | course |
      | local/catquiz/tests/fixtures/catquiz_resources_mc.xml | catquiz_resources_mc.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                              | filename                 |
      | local/catquiz/tests/fixtures/catquiz_resources_mc.csv | catquiz_resources_mc.csv |
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | Classical test | 1                    | 2                    | 0.0                       | 1000.0                    | 1                  |

  Scenario: CAT attempts numbered ahead of the activity's: the test runs to its feedback
    Given the CAT attempt numbering is 7 ahead of the activity attempts
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then "[data-region='adaptivequiz-attempt-feedback']" "css_element" should be visible
    And I should see "Written feedback for Ressourcen"
    And the CAT attempt of "student1" is filed under its own activity attempt with a different id
    And I log out
    ## The result page is the participant's own; others see the attempt on its feedback page.
    When I log in as "admin"
    And I visit the CAT attempt feedback page for the last attempt of "student1"
    Then "//div[contains(@class, 'catquiz_feedback')]" "xpath_element" should exist
    And I should see "Written feedback for Ressourcen"

  Scenario: The activity attempt of one person has the number of another person's CAT attempt
    ## The CAT numbering runs ahead before the first attempt, so that its CAT id is still to come for the
    ## activity attempts.
    Given the CAT attempt numbering is 5 ahead of the activity attempts
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Text of resource question two."
    And I log out
    And the next activity attempt id equals the CAT attempt id of "student1"
    When I am on the "adaptivecatquiz1" Activity page logged in as student2
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "falsche Antwort 1" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then "[data-region='adaptivequiz-attempt-feedback']" "css_element" should be visible
    And the CAT attempt of "student2" is filed under its own activity attempt with a different id
    And the saved progress of the CAT attempt of "student1" still belongs to "student1"
    And I log out
    ## The first person goes on where they stopped.
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Continue attempt" "button"
    And I wait until the page is ready
    And I should see "Text of resource question two."
    And I click on "richtige Antwort" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then "[data-region='adaptivequiz-attempt-feedback']" "css_element" should be visible
    And the CAT attempt of "student1" is filed under its own activity attempt with a different id

  Scenario: A saved state of another person is refused with a message, and left as it is
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Text of resource question two."
    And the saved progress of the running CAT attempt of "student1" belongs to "student2"
    When I click on "richtige Antwort" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should see "This test cannot be continued: its saved state does not belong to this attempt."
    And I should see "My Adaptive Quiz"
    And "Submit answer" "button" should not exist
    And a progress integrity failure "usermismatch" was logged
    And the saved progress of the CAT attempt of "student1" still belongs to "student2"
