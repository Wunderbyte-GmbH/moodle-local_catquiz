@local @local_catquiz @javascript @local_catquiz_shortcodes
Feature: As a teacher I want to use shortcodes to display adaptive quiz test results with catquiz functinality.

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
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy         | catquiz_selectfirstquestion | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer lowest skill gap | startwitheasiestquestion    | 4                    | 0.4                       | 0.6                       | 2                       |
    ## Below steps are required to save a correct JSON settings
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    ## And I set the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_1']" "xpath_element" to "Feedback-Simulation_range_1"
    ## And I set the field "Feedback" in the "//div[@data-name='feedback_scale_Simulation_range_2']" "xpath_element" to "Feedback-Simulation_range_2"
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: CatQuiz: Pass adaptive quiz attempt and displaying feedback with question summary in a Page resource via the shortcode
    Given the following "activities" exist:
      | activity | name           | course | section | idnumber       | intro                                   |
      | label    | Adaptive Panel | C1     | 1       | adaptivelabel1 | [catquizfeedback show=questionssummary] |
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
    And I wait until the page is ready
    ## Verify of data on quiz's "Feedback" page
    ## Verify of ability score
    ## Verify of feedback
    ## Verify of question summary
    ##And I should see "4 evaluated items" in the "//div[contains(@data-target, 'catquizfeedbackabilitiesplayedquestions_']" "xpath_element"
    ## Verify of data in label on course page: recent attempt
    When I am on "Course 1" course homepage
    ## Verify of ability score
    ## Verify of feedback

  @javascript
  Scenario: CatQuiz: Pass two adaptive quiz attempts and displaying both in a Page resource via the shortcode
    Given the following "activities" exist:
      | activity | name           | course | section | idnumber       | intro                                |
      | label    | Adaptive Panel | C1     | 1       | adaptivelabel1 | [catquizfeedback numberofattempts=2] |
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
    And I wait until the page is ready
    ## Verify of data on quiz's "Feedback" page
    And I am on the "adaptivecatquiz1" Activity page
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
    And I wait until the page is ready
    ## Verify of data on quiz's "Feedback" page
    ## Verify of data in label on course page: recent attempt
    When I am on "Course 1" course homepage
    ## Verify of ability score
    ## Verify of feedback

  @javascript
  Scenario: CatQuiz: Displaying feedback in a Page resource via the shortcode with primaryscale parameter
    Given the following "activities" exist:
      | activity | name           | course | section | idnumber       | intro                                    |
      | label    | Adaptive Panel | C1     | 1       | adaptivelabel1 | [catquizfeedback primaryscale=strongest] |
      ## Below case does not working
      ##| label    | Adaptive Panel | C1     | 1       | adaptivelabel1 | [catquizfeedback primaryscale=SimA] |
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
    And I wait until the page is ready
