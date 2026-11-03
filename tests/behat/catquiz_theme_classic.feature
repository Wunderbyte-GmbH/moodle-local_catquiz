@local @local_catquiz @javascript
Feature: The central pages of CATquiz work under a Boost based theme other than Boost (issue #32).
  The styles of the plugin are scoped to its own regions and take their colours from the theme, the
  tabs and dialogues are those of the theme's Bootstrap. Under Classic the CAT manager, the
  statistics shortcode and the result page of an attempt are usable and pass axe-core.

  Background:
    # Classic is no longer part of the core from Moodle 5.3 on; without it there is nothing to check here.
    Given the "theme_classic" plugin is installed
    And the following "users" exist:
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
      | theme       | classic       |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
    And the following "activities" exist:
      | activity | name       | course | idnumber | content             |
      | page     | Statistics | C1     | page1    | [catquizstatistics] |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | Classical test | 2                    | 2                    | 0.0                       | 1000.0                    | 1                  |
    And the "shortcodes" filter is "on"
    And the following CAT results of "adaptivecatquiz1" exist:
      | user     | days ago | ability | valid |
      | student2 | 2        | 1.0     | yes   |

  @javascript @accessibility
  Scenario: The tabs of the CAT manager under Classic
    Given I log in as "admin"
    When I visit "/local/catquiz/manage_catscales.php?tab=summary"
    Then "//a[@aria-current='page'][contains(., 'Summary')]" "xpath_element" should exist
    And the page has no duplicate ids
    And the "#region-main" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
    When I visit "/local/catquiz/manage_catscales.php?tab=questions"
    And I set the field "Scale" to "Ressourcen"
    And I wait until the page is ready
    Then "//a[@aria-current='page'][contains(., 'Questions')]" "xpath_element" should exist
    And "#local-catquiz-manager-questions table" "css_element" should be visible
    And the page has no duplicate ids
    And the "#region-main" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
    When I visit "/local/catquiz/manage_catscales.php?tab=catscales"
    Then "//a[@aria-current='page'][contains(., 'CAT scales')]" "xpath_element" should exist
    And the page has no duplicate ids
    And the "#region-main" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests

  @javascript @accessibility
  Scenario: The statistics shortcode under Classic
    Given I am on the "page1" "page activity" page logged in as teacher
    Then I should not see "[catquizstatistics"
    And the page has no duplicate ids
    When I click on "Testusage" "link" in the "#region-main" "css_element"
    Then "//div[@role='tabpanel'][contains(@id, '-testusage')]" "xpath_element" should be visible
    And "//a[@role='tab'][@aria-selected='true'][contains(., 'Testusage')]" "xpath_element" should exist
    And the "#region-main" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests

  @javascript @accessibility
  Scenario: The result page of an attempt under Classic
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Text of resource question two."
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    Then the page has no duplicate ids
    And the "#region-main" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
    When I click on "Quiz progress summary" "link" in the "#region-main" "css_element"
    And I wait until ".catquiz-graphicalsummary-table" "css_element" exists
    Then ".catquiz-graphicalsummary-table" "css_element" should be visible
    And the page has no duplicate ids
    And the "#region-main" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
