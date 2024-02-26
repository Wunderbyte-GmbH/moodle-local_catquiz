@local @local_catquiz @javascript
Feature: As a admin I want to manage CAT scales along with obtained attempts data.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                       |
      | student1 | Student1  | Test     | toolgenerator1@example.com  |
      | student2 | Student2  | Test     | toolgenerator2@example.com  |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com  |
      | manager  | Manager   | Test     | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
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
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy      | catquiz_selectfirstquestion | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer all subscales | startwitheasiestquestion    | 4                    | 0.4                       | 0.6                       | 2                       |
    ## Below steps are required to save a correct JSON settings
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out
    ## Pass 1 attempt as a student
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
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
    And I log out

  @javascript
  Scenario: Catscales management: Review catscales when attempt data exist
    Given I log in as "admin"
    And I press "Catquiz"
    And I wait until the page is ready
    ## Verify Summary tab
    And I should see "7 of 7 records found" in the ".eventlogtable" "css_element"
    And I should see "Student1 Test" in the "#eventlogtable_r1" "css_element"
    And I should see "Attempt completed" in the "#eventlogtable_r1" "css_element"
    And I should see "in CAT scale Simulation completed by user" in the "#eventlogtable_r1" "css_element"
    ## Verify Questions tab
    And I click on "Questions" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Simulation"
    And I wait until the page is ready
    And I should see "28 of 28 records found" in the ".questionstable" "css_element"
    And I should see "1" in the ".questionstable" "css_element"
    ## Verify Tests & Templates tab
    And I click on "Tests & Templates" "link" in the "#region-main" "css_element"
    And I set the field "Scale" to "Simulation"
    And I wait until the page is ready
    ##And I should see "1 of 1 records found" in the ".wunderbyteTableClass" "css_element"
    And I should see "1 of 1 records found" in the "//div[contains(@class, 'testenvironmentstable')]" "xpath_element"
    And I should see "Dummy Quiz" in the "//tr[contains(@id, 'testenvironmentstable')]" "xpath_element"
    And I should see "mod_adaptivequiz" in the "//tr[contains(@id, 'testenvironmentstable')]" "xpath_element"
    And I should see "Visible" in the "//tr[contains(@id, 'testenvironmentstable')]" "xpath_element"
    And I should see "Active" in the "//tr[contains(@id, 'testenvironmentstable')]" "xpath_element"
    And I should see "Course 1" in the "//tr[contains(@id, 'testenvironmentstable')]" "xpath_element"
    ## Verify Quiz Attempts tab
    And I click on "Quiz Attempts" "link" in the "#region-main" "css_element"
    And I should see "1 of 1 records found" in the ".quizattemptstable" "css_element"
    And I should see "student1" in the "#quizattemptstable_r1" "css_element"
    And I should see "Simulation" in the "#quizattemptstable_r1" "css_element"
    And I should see "Course 1" in the "#quizattemptstable_r1" "css_element"
    And I should see "adaptivequiz" in the "#quizattemptstable_r1" "css_element"
    And I should see "Dummy Quiz" in the "#quizattemptstable_r1" "css_element"
    And I should see "Infer all subscales" in the "#quizattemptstable_r1" "css_element"
