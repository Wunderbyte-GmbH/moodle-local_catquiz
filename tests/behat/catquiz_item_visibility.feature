@local @local_catquiz @javascript
Feature: A question hidden in the question bank or deactivated in a scale (issue #82).
  Two levels hide a question: the question bank for every use, the CAT manager in one scale. The
  question bank wins - no scale brings a hidden question back - and the scale narrows further. The
  manager says in words which level hides a question, and no attempt is given a question the bank hides.

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
    ## Simulation > SimA > {SimA01, SimA02}, twelve items each; SIMA01-00 is the first of the form.
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                                 | filename                    |
      | local/catquiz/tests/fixtures/simulation_24_hierarchy.csv | simulation_24_hierarchy.csv |
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
    ## A classical test of three questions: the first three items of the form, by item id.
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Classical test | 3                    | 3                    | 0.0                       | 1000.0                    | 1                  |
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: Hidden in the question bank: the manager says so, and the attempt never asks it
    Given the question "SIMA01-00" is hidden in the question bank
    And I log in as "admin"
    When I open the questions of the CAT scale "SimA01" in the CAT manager
    And I wait until the page is ready
    Then I should see "Hidden in the question bank"
    And "[data-availability='globalhidden']" "css_element" should exist
    And "[data-availability='active']" "css_element" should exist
    When I open the CAT item "SIMA01-00" of the CAT scale "SimA01" in the CAT manager
    And I wait until the page is ready
    Then I should see "Hidden in the question bank"
    And "[data-id='testitem-toggle-status']" "css_element" should not exist
    And the CAT pool of "SimA01" does not contain the question "SIMA01-00"
    And I log out
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 2"
    And I click on "richtige Antwort" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then the last CAT attempt of "student1" did not ask "SIMA01-00"

  @javascript
  Scenario: Deactivated in one scale, the question stays available in another
    Given the question "SIMA01-00" is also an item of the CAT scale "SimA02"
    And the question "SIMA01-00" is deactivated in the CAT scale "SimA01"
    And I log in as "admin"
    When I open the questions of the CAT scale "SimA01" in the CAT manager
    And I wait until the page is ready
    Then I should see "Deactivated in this scale"
    And "[data-availability='scaleinactive']" "css_element" should exist
    When I open the questions of the CAT scale "SimA02" in the CAT manager
    And I wait until the page is ready
    Then "[data-availability='scaleinactive']" "css_element" should not exist
    And "[data-availability='active']" "css_element" should exist
    When I open the CAT item "SIMA01-00" of the CAT scale "SimA02" in the CAT manager
    And I wait until the page is ready
    Then I should see "Availability: Active"
    And the CAT pool of "SimA01" does not contain the question "SIMA01-00"
    And the CAT pool of "SimA02" contains the question "SIMA01-00"

  @javascript
  Scenario: The parameters of a question belong to a model that is not installed: the attempt runs without it (issue #153)
    ## The first question of the form. Before, the first question of every attempt ended with an error.
    Given the parameters of the questions "SIMA01-00" belong to a model that is not installed
    ## The manager reads why the question is not played.
    And I log in as "admin"
    When I open the questions of the CAT scale "SimA01" in the CAT manager
    And I wait until the page is ready
    Then I should see "Not played: the model of its parameters is not installed"
    And "[data-availability='modelnotinstalled']" "css_element" should exist
    And I log out
    When I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Question 1"
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 2"
    And I click on "richtige Antwort" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Question 3"
    And I click on "richtige Antwort" "text" in the "Question 3" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then I should not see "technical problem"
    And the last CAT attempt of "student1" did not ask "SIMA01-00"
