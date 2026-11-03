@local @local_catquiz @javascript
Feature: Two statistics and two feedbacks on one page keep their tabs apart (issue #30).
  Tabs, collapses and dialogues find their target by id. With the same shortcode twice on one page,
  every instance has ids of its own: a tab clicked in the second one opens its own panel and leaves
  the first one as it was, and no id of the page is used twice.

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
    And the following "activities" exist:
      | activity | name           | course | idnumber | content                                      |
      | page     | Two statistics | C1     | page1    | <p>[catquizstatistics]</p><p>[catquizstatistics]</p> |
      | page     | Two feedbacks  | C1     | page2    | <p>[catquizfeedback]</p><p>[catquizfeedback]</p>     |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | Classical test | 2                    | 2                    | 0.0                       | 1000.0                    | 1                  |
    And the "shortcodes" filter is "on"
    ## A result for the statistics; student1's own attempt is the only one of the feedback.
    And the following CAT results of "adaptivecatquiz1" exist:
      | user     | days ago | ability | valid |
      | student2 | 2        | 1.0     | yes   |

  @javascript @accessibility
  Scenario: A tab of the second statistics opens its own panel; the first statistics stays as it was
    Given I am on the "page1" "page activity" page logged in as teacher
    Then "(//ul[@role='tablist'][.//a[contains(@id, 'local-catquiz-statistics-')]])[2]" "xpath_element" should exist
    And the page has no duplicate ids
    When I click on "Testusage" "link" in the "(//ul[@role='tablist'][.//a[contains(@id, 'local-catquiz-statistics-')]])[2]" "xpath_element"
    Then "(//div[@role='tabpanel'][contains(@id, '-testusage')])[2]" "xpath_element" should be visible
    And "(//div[@role='tabpanel'][contains(@id, '-overview')])[2]" "xpath_element" should not be visible
    ## The first instance did not move.
    And "(//div[@role='tabpanel'][contains(@id, '-overview')])[1]" "xpath_element" should be visible
    And "(//div[@role='tabpanel'][contains(@id, '-testusage')])[1]" "xpath_element" should not be visible
    And "(//ul[@role='tablist'][.//a[contains(@id, 'local-catquiz-statistics-')]])[1]//a[@aria-selected='true'][contains(., 'Overview')]" "xpath_element" should exist
    And "(//ul[@role='tablist'][.//a[contains(@id, 'local-catquiz-statistics-')]])[2]//a[@aria-selected='true'][contains(., 'Testusage')]" "xpath_element" should exist
    ## Moodle 4.5 (Bootstrap 4) has no arrow keys between tabs; a click in the first list leaves the second.
    When I click on "Testusage" "link" in the "(//ul[@role='tablist'][.//a[contains(@id, 'local-catquiz-statistics-')]])[1]" "xpath_element"
    Then "(//div[@role='tabpanel'][contains(@id, '-testusage')])[1]" "xpath_element" should be visible
    And "(//div[@role='tabpanel'][contains(@id, '-testusage')])[2]" "xpath_element" should be visible
    And the "#region-main" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests

  @javascript @accessibility
  Scenario: A tab of the second feedback opens its own panel; the first feedback stays as it was
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Text of resource question two."
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    When I am on the "page2" "page activity" page
    Then "(//ul[@role='tablist'][contains(@id, 'local-catquiz-feedback-')])[2]" "xpath_element" should exist
    And the page has no duplicate ids
    When I click on "Quiz progress summary" "link" in the "(//ul[@role='tablist'][contains(@id, 'local-catquiz-feedback-')])[2]" "xpath_element"
    And I wait until "(//div[@role='tabpanel'][contains(@id, '-graphicalsummary-student')])[2]//table[contains(@class, 'catquiz-graphicalsummary-table')]" "xpath_element" exists
    Then "(//div[@role='tabpanel'][contains(@id, '-graphicalsummary-student')])[2]" "xpath_element" should be visible
    And "(//div[@role='tabpanel'][contains(@id, '-graphicalsummary-student')])[1]" "xpath_element" should not be visible
    And the page has no duplicate ids
    And the "#region-main" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
