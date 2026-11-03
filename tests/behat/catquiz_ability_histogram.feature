@local @local_catquiz @javascript
Feature: The ability profile is drawn without item parameters (issue #150).
  The histogram of the ability scores in the personal feedback and in [catquizstatistics] is drawn from the
  historical results. When no item has parameters - a new calibration context, only pilot items, parameters not
  calculated yet - the test information line cannot be computed: it is left out, and a teacher is told why.
  Before, the bars took their labels from that line: the browser refused the chart and the page kept an empty
  chart area.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | student2 | Student2  | Test     | toolgenerator2@example.com |
      | student3 | Student3  | Test     | toolgenerator5@example.com |
      | student4 | Student4  | Test     | toolgenerator6@example.com |
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
      | teacher  | C1     | editingteacher |
    And the following "local_catquiz > questions" exist:
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                          | filename             |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name   | course | section | idnumber         | intro   |
      | adaptivequiz | Quiz A | C1     | 1       | adaptivecatquizA | Quiz A. |
    And the following "activities" exist:
      | activity | name       | course | idnumber | content             |
      | page     | Statistics | C1     | page1    | [catquizstatistics] |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy      | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquizA | catquiz  | Simulation | Infer all subscales | 12                   | 12                   | 1                               | 0.0                       | 1000.0                    | 1                  |
    And the "shortcodes" filter is "on"
    ## Every chart the browser draws is recorded: labels, series and their lengths, and errors of the page.
    And the charts drawn in the browser are recorded

  @javascript
  Scenario Outline: The feedback draws the bars from the results when no item has parameters (lazy <lazy>)
    Given the following config values are set as admin:
      | config           | value  | plugin        |
      | lazyfeedbacktabs | <lazy> | local_catquiz |
    And I am on the "adaptivecatquizA" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out
    And I am on the "adaptivecatquizA" Activity page logged in as student1
    And I click on "Start attempt" "link"
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
    And I click on "falsche Antwort 1" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 5"
    And I click on "richtige Antwort" "text" in the "Question 5" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 6"
    And I click on "falsche Antwort 1" "text" in the "Question 6" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 7"
    And I click on "richtige Antwort" "text" in the "Question 7" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 8"
    And I click on "falsche Antwort 1" "text" in the "Question 8" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 9"
    And I click on "richtige Antwort" "text" in the "Question 9" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 10"
    And I click on "falsche Antwort 1" "text" in the "Question 10" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 11"
    And I click on "richtige Antwort" "text" in the "Question 11" "question"
    And I click on "Submit answer" "button"
    And I should see "Question 12"
    And I click on "falsche Antwort 1" "text" in the "Question 12" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I log out
    ## Three more results: two next to 0 (the bar of 0.25), one next to the minimum (the bar of -4.75).
    And the following CAT results of "adaptivecatquizA" exist:
      | user     | days ago | ability | valid |
      | student2 | 2        | 0.1     | yes   |
      | student3 | 1        | 0.1     | yes   |
      | student4 | 1        | -4.9    | yes   |
    And the items of the CAT scale "Simulation" have no parameters
    ## The participant: the bars with their values - a canvas alone is drawn also for a chart without labels -
    ## no test information, no note about it.
    When I log in as "student1"
    And I open the result page of the last attempt of "student1" in "adaptivecatquizA"
    And I click on "Overview" "link" in the "#region-main" "css_element"
    And I wait until "[data-region='local_catquiz-abilityprofile'] .chart-image canvas" "css_element" exists
    Then "[data-region='local_catquiz-abilityprofile'] .chart-image canvas" "css_element" should be visible
    And the recorded charts were drawn without an error
    And "[data-region='local_catquiz-chart-notice']" "css_element" should not exist
    And I click on "Show chart data" "link" in the "[data-region='local_catquiz-abilityprofile']" "css_element"
    And "//div[@data-region='local_catquiz-abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='0.25']]/td[1][number(normalize-space(.)) >= 2]" "xpath_element" should exist
    And "//div[@data-region='local_catquiz-abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='-4.75']]/td[1][normalize-space(.)='1']" "xpath_element" should exist
    And "//div[@data-region='local_catquiz-abilityprofile']//table//tr[th[@scope='row']][20]" "xpath_element" should exist
    And "//div[@data-region='local_catquiz-abilityprofile']//table//tr[th[@scope='row']]/td[2]" "xpath_element" should not exist
    And I log out
    ## The teacher: bars with their values in the table, and why the test information is missing.
    When I am on the "adaptivecatquizA" Activity page logged in as teacher
    And I visit the CAT attempt feedback page for the last attempt of "student1"
    And I wait until the page is ready
    And I click on "Overview" "link" in the "#region-main" "css_element"
    And I wait until "[data-region='local_catquiz-abilityprofile'] .chart-image canvas" "css_element" exists
    Then "[data-region='local_catquiz-abilityprofile'] .chart-image canvas" "css_element" should be visible
    And the recorded charts were drawn without an error
    And I should see "The test information is not shown" in the "[data-region='local_catquiz-abilityprofile']" "css_element"
    And I click on "Show chart data" "link" in the "[data-region='local_catquiz-abilityprofile']" "css_element"
    And "//div[@data-region='local_catquiz-abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='0.25']]/td[1][number(normalize-space(.)) >= 2]" "xpath_element" should exist
    And "//div[@data-region='local_catquiz-abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='-4.75']]/td[1][normalize-space(.)='1']" "xpath_element" should exist
    And "//div[@data-region='local_catquiz-abilityprofile']//table//tr[th[@scope='row']][20]" "xpath_element" should exist
    Examples:
      | lazy |
      | 1    |
      | 0    |

  @javascript @accessibility
  Scenario Outline: The statistics draw the bars from the results when no item has parameters (lazy <lazy>)
    Given the following config values are set as admin:
      | config           | value  | plugin        |
      | lazyfeedbacktabs | <lazy> | local_catquiz |
    ## One value per person, the latest valid: student1 next to 0, then invalid; the others as in the feedback.
    And the following CAT results of "adaptivecatquizA" exist:
      | user     | days ago | ability | valid |
      | student1 | 3        | 0.2     | yes   |
      | student1 | 2        | 3.0     | no    |
      | student2 | 2        | 0.1     | yes   |
      | student3 | 1        | 0.1     | yes   |
      | student4 | 1        | -4.9    | yes   |
    And the items of the CAT scale "Simulation" have no parameters
    When I am on the "page1" "page activity" page logged in as teacher
    And I wait until "[data-chartname='abilityprofile'] .chart-image canvas" "css_element" exists
    Then "[data-chartname='abilityprofile'] .chart-image canvas" "css_element" should be visible
    And the recorded charts were drawn without an error
    And I should see "The test information is not shown" in the "[data-chartname='abilityprofile']" "css_element"
    And I click on "Show chart data" "link" in the "[data-chartname='abilityprofile']" "css_element"
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='0.25']]/td[1][normalize-space(.)='3']" "xpath_element" should exist
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='2.75']]/td[1][normalize-space(.)='0']" "xpath_element" should exist
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='-4.75']]/td[1][normalize-space(.)='1']" "xpath_element" should exist
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row']][20]" "xpath_element" should exist
    And "[data-chartname='abilityprofile'] [data-region='local_catquiz-chart-notice'].alert-warning" "css_element" should not exist
    And the "[data-chartname='abilityprofile']" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests

    Examples:
      | lazy |
      | 1    |
      | 0    |

  @javascript
  Scenario: A partly calibrated pool: the test information of the items with parameters, for teachers only
    ## SimA and its subscales lose their parameters; SimB and SimC keep theirs.
    And the following CAT results of "adaptivecatquizA" exist:
      | user     | days ago | ability | valid |
      | student1 | 3        | 0.2     | yes   |
      | student2 | 2        | 0.1     | yes   |
      | student3 | 1        | 0.1     | yes   |
      | student4 | 1        | -4.9    | yes   |
    And the items of the CAT scale "SimA" have no parameters
    When I am on the "page1" "page activity" page logged in as teacher
    And I wait until "[data-chartname='abilityprofile'] .chart-image canvas" "css_element" exists
    Then "[data-chartname='abilityprofile'] .chart-image canvas" "css_element" should be visible
    And the recorded charts were drawn without an error
    And I should not see "The test information is not shown" in the "[data-chartname='abilityprofile']" "css_element"
    And I click on "Show chart data" "link" in the "[data-chartname='abilityprofile']" "css_element"
    ## The statistics list the test information first: column 1 is the line, column 2 the bars.
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='col']][contains(., 'Test information')]" "xpath_element" should exist
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='0.25']]/td[1][number(normalize-space(.)) > 0]" "xpath_element" should exist
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='0.25']]/td[2][normalize-space(.)='3']" "xpath_element" should exist
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='-4.75']]/td[2][normalize-space(.)='1']" "xpath_element" should exist
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row']][20]" "xpath_element" should exist
    And I log out
    ## Participants have no access to the statistics: neither the profile nor the test information.
    When I am on the "page1" "page activity" page logged in as student1
    Then "[data-chartname='abilityprofile']" "css_element" should not exist
    And I should not see "Test information"

  @javascript
  Scenario: A new calibration context without parameters: the bars of the history, no test information
    ## The results stay in the old context; the scale moves through the scale update, its items with it, and
    ## the items have no parameters yet in the new context.
    And the following CAT results of "adaptivecatquizA" exist:
      | user     | days ago | ability | valid |
      | student1 | 3        | 0.2     | yes   |
      | student2 | 2        | 0.1     | yes   |
      | student3 | 1        | 0.1     | yes   |
      | student4 | 1        | -4.9    | yes   |
    And the CAT scale "Simulation" is switched to a new CAT context
    And the items of the CAT scale "Simulation" have no parameters
    When I am on the "page1" "page activity" page logged in as teacher
    And I wait until "[data-chartname='abilityprofile'] .chart-image canvas" "css_element" exists
    Then "[data-chartname='abilityprofile'] .chart-image canvas" "css_element" should be visible
    And the recorded charts were drawn without an error
    And I should see "The test information is not shown" in the "[data-chartname='abilityprofile']" "css_element"
    And I click on "Show chart data" "link" in the "[data-chartname='abilityprofile']" "css_element"
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='0.25']]/td[1][normalize-space(.)='3']" "xpath_element" should exist
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='-4.75']]/td[1][normalize-space(.)='1']" "xpath_element" should exist
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='0.25']]/td[2]" "xpath_element" should not exist

  @javascript
  Scenario: Lowest skill gap, scale moved to an empty context: every valid measurement and the test information of the results
    ## Test 20 (re-audit 27.10.2026): under "lowest skill gap" the root scale is measured but stored as not the
    ## reported scale. Then the scale is attached to a new context without parameters; the results stay in the old one.
    Given the following CAT results of "adaptivecatquizA" exist under the lowest skill gap:
      | user     | days ago | root ability | reported scale | reported ability |
      | student1 | 3        | 0.1          | SimA           | -1.0             |
      | student2 | 2        | 0.2          | SimB           | -0.5             |
      | student3 | 1        | -4.9         | SimC           | -4.0             |
    And the following CAT results of "adaptivecatquizA" exist:
      | user     | days ago | ability | valid |
      | student4 | 1        | 3.0     | no    |
    And the CAT scale "Simulation" is attached to the empty CAT context "Recalibration not calculated"
    When I am on the "page1" "page activity" page logged in as teacher
    And I wait until "[data-chartname='abilityprofile'] .chart-image canvas" "css_element" exists
    Then "[data-chartname='abilityprofile'] .chart-image canvas" "css_element" should be visible
    And the recorded charts were drawn without an error
    And I should see "n = 3 persons" in the "[data-chartname='abilityprofile']" "css_element"
    And I should see "Out of 4 completed attempts of 4 persons in the selection; 1 of them have no valid measurement in this scale." in the "[data-chartname='abilityprofile']" "css_element"
    And I should see "Test information:" in the "[data-chartname='abilityprofile']" "css_element"
    And I should not see "The test information is not shown" in the "[data-chartname='abilityprofile']" "css_element"
    And I should not see "Recalibration not calculated" in the "[data-chartname='abilityprofile']" "css_element"
    And I click on "Show chart data" "link" in the "[data-chartname='abilityprofile']" "css_element"
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='0.25']]/td[2][normalize-space(.)='2']" "xpath_element" should exist
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='-4.75']]/td[2][normalize-space(.)='1']" "xpath_element" should exist
    And "//div[@data-chartname='abilityprofile']//table//tr[th[@scope='row'][normalize-space(.)='2.75']]/td[2][normalize-space(.)='0']" "xpath_element" should exist

  @javascript
  Scenario: A chart the browser cannot draw says so instead of leaving an empty area
    ## The chart of the live case goes past the check of the server on purpose: the test information without
    ## values. core/chart_builder threw "Invalid values received for series." where nobody caught it.
    Given I log in as "teacher"
    When I am on fixture page "/local/catquiz/tests/behat/fixtures/chart_refusal.php"
    And I wait until "[data-region='local_catquiz-fixture-fine'] .chart-image canvas" "css_element" exists
    Then I should see "This chart could not be drawn" in the "[data-region='local_catquiz-fixture-live']" "css_element"
    And "[data-region='local_catquiz-fixture-live'] canvas" "css_element" should not exist
    And "[data-region='local_catquiz-fixture-live'] .chart-table" "css_element" should not be visible
    And "[data-region='local_catquiz-fixture-fine'] .chart-image canvas" "css_element" should be visible
    And I should not see "This chart could not be drawn" in the "[data-region='local_catquiz-fixture-fine']" "css_element"

  @javascript
  Scenario: Lowest skill gap without a valid root value: no bar of one person, the reported subscales instead
    ## Re-audit 28.10.2026, test 20: the root scale is nowhere measured validly; one attempt was finalised after
    ## results were stored, without a stored row, carrying a root value. It made the single bar of the live case.
    Given results of CAT attempts are stored since 30 days ago
    And the following CAT results of "adaptivecatquizA" exist under the lowest skill gap:
      | user     | days ago | root ability | reported scale | reported ability | root reasons                                   |
      | student1 | 3        | 0.7          | SimA           | -1.0             | se_max,reporting_disabled,not_primary          |
      | student2 | 2        | 0.7          | SimB           | -0.5             | se_max,reporting_disabled,not_primary          |
    And the following CAT attempts of "adaptivecatquizA" ended without a stored result:
      | user     | days ago | root ability |
      | student3 | 1        | -1.3505      |
    When I am on the "page1" "page activity" page logged in as teacher
    And I wait until "[data-chartname='detectedscales'] .chart-image canvas" "css_element" exists
    And I wait until "[data-chartname='abilityprofile'] [data-region='local_catquiz-abilityprofile-novalue']" "css_element" exists
    Then I should see "None of the 3 completed attempts of the selection has a valid measurement in “Simulation”." in the "[data-chartname='abilityprofile']" "css_element"
    And I should see "2 persons have a valid reported result" in the "[data-chartname='abilityprofile']" "css_element"
    And I should see "n = 0 persons" in the "[data-chartname='abilityprofile']" "css_element"
    And I should see "Out of 3 completed attempts of 3 persons in the selection" in the "[data-chartname='abilityprofile']" "css_element"
    And I should see "every test of the scale in this course (1)" in the "[data-chartname='abilityprofile']" "css_element"
    ## No bar - but the test information of the pool does not depend on the persons (re-audit 02.11.2026): its own
    ## line, unscaled, for the teacher.
    And "[data-chartname='abilityprofile'] [data-region='local_catquiz-abilityprofile-novalue'] canvas" "css_element" should not exist
    And I wait until "[data-chartname='abilityprofile'] [data-region='local_catquiz-testinfo-withoutbars'] .chart-image canvas" "css_element" exists
    And I should see "Test information of the current item pool of “Simulation”" in the "[data-chartname='abilityprofile']" "css_element"
    And "[data-chartname='detectedscales'] .chart-image canvas" "css_element" should be visible
    And the recorded charts were drawn without an error

  @javascript @accessibility
  Scenario: Lowest skill gap in the usage charts: a valid reported subscale is not "no ability" (issue #160)
    ## Test 20: the root scale is nowhere measured validly, but the reported subscale is valid for two of three.
    Given results of CAT attempts are stored since 30 days ago
    And the following CAT results of "adaptivecatquizA" exist under the lowest skill gap:
      | user     | days ago | root ability | reported scale | reported ability | root reasons                          |
      | student1 | 3        | 0.7          | SimA           | -1.0             | se_max,reporting_disabled,not_primary |
      | student2 | 2        | 0.7          | SimB           | -0.5             | se_max,reporting_disabled,not_primary |
    And the following CAT attempts of "adaptivecatquizA" ended without a stored result:
      | user     | days ago | root ability |
      | student3 | 1        | -1.3505      |
    When I am on the "page1" "page activity" page logged in as teacher
    And I click on "Testusage" "link" in the "#region-main" "css_element"
    And I wait until "[data-chartname='attemptsperperson'] .chart-image canvas" "css_element" exists
    And I wait until "[data-chartname='attemptspertimerange'] .chart-image canvas" "css_element" exists
    Then I should see "this applies to 2 attempt(s) here" in the "[data-chartname='attemptsperperson']" "css_element"
    And I should see "this applies to 2 attempt(s) here" in the "[data-chartname='attemptspertimerange']" "css_element"
    ## The legend of the data table: grey means "no valid reported result", and it holds one person, not three.
    When I click on "Show chart data" "link" in the "[data-chartname='attemptsperperson']" "css_element"
    Then I should see "No valid reported result" in the "[data-chartname='attemptsperperson'] .chart-table-data" "css_element"
    And I should not see "No ability was calculated" in the "[data-chartname='attemptsperperson']" "css_element"
    When I click on "Show chart data" "link" in the "[data-chartname='attemptspertimerange']" "css_element"
    Then I should see "No valid reported result" in the "[data-chartname='attemptspertimerange'] .chart-table-data" "css_element"
    And the "[data-chartname='attemptsperperson']" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
    And the "[data-chartname='attemptspertimerange']" "css_element" should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests
    And the recorded charts were drawn without an error
