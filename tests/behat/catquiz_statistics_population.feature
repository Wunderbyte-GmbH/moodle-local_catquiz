@local @local_catquiz @javascript
Feature: Statistics count who could take the test and draw the learning progress of the cohort (issues #119, #132).
  "Not yet attempted" counts only the people who could have taken the test: here the members of the
  group the test is restricted to, not the other participants and not the teachers. The learning
  progress shows, for each day, the mean of everybody's latest valid result up to then - the same
  values in the statistics and in the personal feedback.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | student2 | Student2  | Test     | toolgenerator2@example.com |
      | student3 | Student3  | Test     | toolgenerator5@example.com |
      | student4 | Student4  | Test     | toolgenerator6@example.com |
      | student5 | Student5  | Test     | toolgenerator7@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
      | student5 | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "groups" exist:
      | name   | course | idnumber |
      | Target | C1     | G1       |
    And the following "group members" exist:
      | user     | group |
      | student1 | G1    |
      | student2 | G1    |
      | student3 | G1    |
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
      | activity | name       | course | idnumber | content             |
      | page     | Statistics | C1     | page1    | [catquizstatistics] |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | Classical test | 2                    | 2                    | 0.0                       | 1000.0                    | 1                  |
    And the activity "adaptivecatquiz1" is restricted to the group "Target"
    And the "shortcodes" filter is "on"
    ## A history: student2's later result is invalid; student4 took the test before the restriction.
    And the following CAT results of "adaptivecatquiz1" exist:
      | user     | days ago | ability | valid |
      | student1 | 3        | 1.0     | yes   |
      | student2 | 3        | 2.0     | yes   |
      | student2 | 2        | -5.0    | no    |
      | student4 | 1        | 0.0     | yes   |

  @javascript
  Scenario: Statistics count only the group without an attempt and show the cumulative cohort
    Given I am on the "page1" "page activity" page logged in as teacher
    ## Issue #132: of the group, only student3 has no attempt ("No attempts", no ability calculated).
    ## student5 cannot take the test and the teacher may not; both used to be counted.
    When I click on "Testusage" "link" in the "#region-main" "css_element"
    And I click on "Show chart data" "link" in the "//div[contains(@id, '-testusage')]" "xpath_element"
    Then "//div[contains(@id, '-testusage')]//tr[th[normalize-space(.)='No attempts']]/td[1][normalize-space(.)='1']" "xpath_element" should exist
    ## Issue #119: 1.5 on the first two days (the invalid result replaces nothing), then 1.0 when student4
    ## enters with 0.0, and the state carried to today.
    When I click on "Your Progress" "link" in the "#region-main" "css_element"
    And I click on "Show chart data" "link" in the "//div[contains(@id, '-learningprogress')]" "xpath_element"
    Then "(//div[contains(@id, '-learningprogress')]//tr[th[@scope='row']])[1]/td[1][normalize-space(.)='1.5']" "xpath_element" should exist
    And "(//div[contains(@id, '-learningprogress')]//tr[th[@scope='row']])[2]/td[1][normalize-space(.)='1.5']" "xpath_element" should exist
    And "(//div[contains(@id, '-learningprogress')]//tr[th[@scope='row']])[3]/td[1][normalize-space(.)='1']" "xpath_element" should exist
    And "(//div[contains(@id, '-learningprogress')]//tr[th[@scope='row']])[4]/td[1][normalize-space(.)='1']" "xpath_element" should exist
    And I should see "Average result of your peers in scale “Ressourcen”" in the "//div[contains(@id, '-learningprogress')]" "xpath_element"

  @javascript
  Scenario: The personal feedback shows the same cohort as the statistics
    Given I am on the "adaptivecatquiz1" Activity page logged in as student3
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I should see "Text of resource question two."
    And I click on "falsche Antwort 1" "text" in the "Question 2" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    When I click on "Your Progress" "link" in the "#region-main" "css_element"
    And I click on "Show chart data" "link" in the "#region-main" "css_element"
    ## The peers' column: the same values as the cohort of the statistics on the first three days.
    Then "(//tr[th[@scope='row']])[1]/td[2][normalize-space(.)='1.5']" "xpath_element" should exist
    And "(//tr[th[@scope='row']])[2]/td[2][normalize-space(.)='1.5']" "xpath_element" should exist
    And "(//tr[th[@scope='row']])[3]/td[2][normalize-space(.)='1']" "xpath_element" should exist
    And "(//tr[th[@scope='row']])[4]/td[2][normalize-space(.)='1']" "xpath_element" should exist
