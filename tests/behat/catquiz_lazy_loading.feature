@local @local_catquiz @javascript
Feature: Feedback tabs, collapsed attempts and statistics charts load when they are opened (issue #85).
  The result page builds the main feedback at once; every further tab is filled through its own request
  when it is opened. The shortcode catquizfeedback shows the attempts beyond the first by their header and
  loads one when it is opened. The shortcode catquizstatistics builds each chart when its tab is shown.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | student2 | Student2  | Test     | toolgenerator2@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher  | C1     | editingteacher |
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
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | Classical test | 2                    | 2                    | 0.0                       | 1000.0                    | 1                  |
    And the "shortcodes" filter is "on"

  Scenario: A tab of the result page is loaded when it is opened, not before
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Text of resource question two."
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then "[data-region='local_catquiz-lazyfeedback'][data-generatorname='graphicalsummary']" "css_element" should exist
    And "[data-region='local_catquiz-lazyfeedback'][data-state='loaded']" "css_element" should not exist
    When I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until "[data-region='local_catquiz-lazyfeedback'][data-generatorname='graphicalsummary'][data-state='loaded']" "css_element" exists
    Then ".catquiz-graphicalsummary-table" "css_element" should exist
    ## The other tabs stay unloaded.
    And "[data-region='local_catquiz-lazyfeedback'][data-generatorname='learningprogress'][data-state='loaded']" "css_element" should not exist

  Scenario: The shortcode catquizfeedback loads a collapsed attempt when it is opened
    Given the following "activities" exist:
      | activity | name          | course | idnumber | content                               |
      | page     | Your feedback | C1     | page1    | [catquizfeedback numberofattempts=2] |
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Text of resource question two."
    And I click on "richtige Antwort" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I am on the "adaptivecatquiz1" Activity page
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "falsche Antwort 1" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Text of resource question two."
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    When I am on the "page1" "page activity" page
    ## The newest attempt is open and built; the older one shows its header only.
    Then "[data-region='local_catquiz-lazyfeedback'][data-generatorname='attempt']" "css_element" should exist
    And "[data-region='local_catquiz-lazyfeedback'][data-generatorname='attempt'][data-state='loaded']" "css_element" should not exist
    When I click on "(//div[contains(@id, '-heading')]//button)[2]" "xpath_element"
    And I wait until "[data-region='local_catquiz-lazyfeedback'][data-generatorname='attempt'][data-state='loaded']" "css_element" exists
    Then I should see "Quiz progress summary" in the "[data-region='local_catquiz-lazyfeedback'][data-generatorname='attempt']" "css_element"
    And I should not see "could not be loaded"

  Scenario: The shortcode catquizstatistics builds a chart when its tab is shown
    Given the following "activities" exist:
      | activity | name       | course | idnumber | content             |
      | page     | Statistics | C1     | page2    | [catquizstatistics] |
    And the following CAT results of "adaptivecatquiz1" exist:
      | user     | days ago | ability | valid |
      | student1 | 2        | 1.0     | yes   |
      | student2 | 1        | 0.5     | yes   |
    When I am on the "page2" "page activity" page logged in as teacher
    And I wait until "//div[contains(@id, '-overview')]//div[@data-region='local_catquiz-lazystatistics'][@data-state='loaded']" "xpath_element" exists
    Then "//div[contains(@id, '-testusage')]//div[@data-region='local_catquiz-lazystatistics'][@data-state='loaded']" "xpath_element" should not exist
    When I click on "Testusage" "link" in the "#region-main" "css_element"
    And I wait until "//div[contains(@id, '-testusage')]//div[@data-region='local_catquiz-lazystatistics'][@data-chart='attemptsperperson'][@data-state='loaded']" "xpath_element" exists
    Then "//div[contains(@id, '-testusage')]//div[@data-chartname='attemptsperperson']//a[contains(., 'Show chart data')]" "xpath_element" should exist
    And "//div[contains(@id, '-learningprogress')]//div[@data-region='local_catquiz-lazystatistics'][@data-state='loaded']" "xpath_element" should not exist
