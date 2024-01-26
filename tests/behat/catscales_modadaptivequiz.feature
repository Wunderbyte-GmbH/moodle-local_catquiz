@local @local_catquiz @javascript
Feature: As a student i want to take adaptive quiz tests with catquiz functinality.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname    | email                       |
      | user1    | Username1 | Test        | toolgenerator1@example.com  |
      | user2    | Username2 | Test        | toolgenerator2@example.com  |
      | teacher  | Teacher   | Test        | toolgenerator3@example.com  |
      | manager  | Manager   | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user1    | C1     | student        |
      | user2    | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "local_catquiz > questions" exist:
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                          | filename             |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |
    ## Unfortunately, in Moodle 4.3 TinyMCE has misbehavior which cause number of site-wide issues. So - we disable it.
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And I log in as "teacher"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Adaptive Quiz" to section "1"
    And I set the following fields to these values:
      | Name                         | My Adaptive Quiz            |
      | Description                  | Adaptive quiz description.  |
      | catmodel                     | Catquiz CAT model           |
      | Select CAT scale             | Simulation                  |
      | Max. questions per test.     | 7                           |
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: Start adaptive quiz attempt with catquiz model and Infer all subscales purpose
    Given I log in as "teacher"
    And I am on "Course 1" course homepage
    ##And I follow "My Adaptive Quiz"
    And I click on "My Adaptive Quiz" "link" in the "#section-1" "css_element"
    And I wait until the page is ready
    And I follow "Settings"
    And I wait until the page is ready
    And I set the field "Purpose of test" to "Infer all subscales"
    And I click on "Save and return to course" "button"
    And I log out
    And I log in as "user1"
    And I am on "Course 1" course homepage
    And I follow "My Adaptive Quiz"
    And I click on "Start attempt" "button"
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
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 3" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Ability score"
    And I should see "You performed better than 75.00% of your fellow students"
    And I should see "-1.28" in the "[data-original-title=\"parent scale Simulation\"]" "css_element"

@javascript
  Scenario: Start adaptive quiz attempt with catquiz model and Infer greatest strength purpose
    Given I log in as "teacher"
    And I am on "Course 1" course homepage
    ##And I follow "My Adaptive Quiz"
    And I click on "My Adaptive Quiz" "link" in the "#section-1" "css_element"
    And I wait until the page is ready
    And I follow "Settings"
    And I wait until the page is ready
    And I set the field "Purpose of test" to "Infer greatest strength"
    And I click on "Save and return to course" "button"
    And I log out
    And I log in as "user1"
    And I am on "Course 1" course homepage
    And I follow "My Adaptive Quiz"
    And I click on "Start attempt" "button"
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
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 3" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Ability score"
    And I should see "You performed better than 75.00% of your fellow students"
    And I should see "-1.61" in the "[data-original-title=\"parent scale Simulation\"]" "css_element"

@javascript
  Scenario: Start adaptive quiz attempt with catquiz model and Infer lowest skill gap purpose
    Given I log in as "teacher"
    And I am on "Course 1" course homepage
    ##And I follow "My Adaptive Quiz"
    And I click on "My Adaptive Quiz" "link" in the "#section-1" "css_element"
    And I wait until the page is ready
    And I follow "Settings"
    And I wait until the page is ready
    And I set the field "Purpose of test" to "Infer lowest skill gap"
    And I click on "Save and return to course" "button"
    And I log out
    And I log in as "user1"
    And I am on "Course 1" course homepage
    And I follow "My Adaptive Quiz"
    And I click on "Start attempt" "button"
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
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 3" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Ability score"
    ## No scale bar?
    ## And I should see "You performed better than 75.00% of your fellow students"
    And I should see "-2.18" in the "[data-original-title=\"parent scale Simulation\"]" "css_element"

  Scenario: Start adaptive quiz attempt with catquiz model and Classical test purpose
    Given I log in as "teacher"
    And I am on "Course 1" course homepage
    ##And I follow "My Adaptive Quiz"
    And I click on "My Adaptive Quiz" "link" in the "#section-1" "css_element"
    And I wait until the page is ready
    And I follow "Settings"
    And I wait until the page is ready
    And I set the field "Purpose of test" to "Classical test"
    And I click on "Save and return to course" "button"
    And I log out
    And I log in as "user1"
    And I am on "Course 1" course homepage
    And I follow "My Adaptive Quiz"
    And I click on "Start attempt" "button"
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
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 3" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Ability score"
    And I should see "You performed better than 75.00% of your fellow students"
    And I should see "-4.07" in the "[data-original-title=\"parent scale Simulation\"]" "css_element"

  Scenario: Start adaptive quiz attempt with catquiz model and Moderate CAT purpose
    Given I log in as "teacher"
    And I am on "Course 1" course homepage
    ##And I follow "My Adaptive Quiz"
    And I click on "My Adaptive Quiz" "link" in the "#section-1" "css_element"
    And I wait until the page is ready
    And I follow "Settings"
    And I wait until the page is ready
    And I set the field "Purpose of test" to "Moderate CAT"
    And I click on "Save and return to course" "button"
    And I log out
    And I log in as "user1"
    And I am on "Course 1" course homepage
    And I follow "My Adaptive Quiz"
    And I click on "Start attempt" "button"
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
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 3" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Ability score"
    ## No scale bar?
    ## And I should see "You performed better than 75.00% of your fellow students"
    And I should see "-3.86" in the "[data-original-title=\"parent scale Simulation\"]" "css_element"

  Scenario: Start adaptive quiz attempt with catquiz model and Radical CAT purpose
    Given I log in as "teacher"
    And I am on "Course 1" course homepage
    ##And I follow "My Adaptive Quiz"
    And I click on "My Adaptive Quiz" "link" in the "#section-1" "css_element"
    And I wait until the page is ready
    And I follow "Settings"
    And I wait until the page is ready
    And I set the field "Purpose of test" to "Radical CAT"
    And I click on "Save and return to course" "button"
    And I log out
    And I log in as "user1"
    And I am on "Course 1" course homepage
    And I follow "My Adaptive Quiz"
    And I click on "Start attempt" "button"
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
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 3" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Ability score"
    And I should see "You performed better than 75.00% of your fellow students"
    And I should see "-1.28" in the "[data-original-title=\"parent scale Simulation\"]" "css_element"