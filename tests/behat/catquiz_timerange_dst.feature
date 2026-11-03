@local @local_catquiz @javascript
Feature: Attempts per day across a change of daylight saving time (issue #151).
  The chart "attempts per period" has one bar per day of the person who looks at it, in the time zone of that
  person. The days were stepped in the time zone of the server: with the person in another zone, the day of a
  change of daylight saving time could be missing - its attempts were drawn under a bar of their own behind the
  others, and the series no longer matched the labels.

  Background:
    Given the following "users" exist:
      | username      | firstname | lastname | email                      | timezone      |
      | teacherutc    | Teacher   | UTC      | toolgenerator7@example.com | UTC           |
      | teacherberlin | Teacher   | Berlin   | toolgenerator8@example.com | Europe/Berlin |
      | student1      | Student1  | Test     | toolgenerator1@example.com | 99            |
      | student2      | Student2  | Test     | toolgenerator2@example.com | 99            |
      | student3      | Student3  | Test     | toolgenerator5@example.com | 99            |
      | student4      | Student4  | Test     | toolgenerator6@example.com | 99            |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user          | course | role           |
      | student1      | C1     | student        |
      | student2      | C1     | student        |
      | student3      | C1     | student        |
      | student4      | C1     | student        |
      | teacherutc    | C1     | editingteacher |
      | teacherberlin | C1     | editingteacher |
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
      | activity | name             | course | idnumber | content                                |
      | page     | Statistics, Oct. | C1     | pageoct  | [catquizstatistics endtime=1793102400] |
      | page     | Statistics, Mar. | C1     | pagemar  | [catquizstatistics endtime=1774944000] |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy      | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquizA | catquiz  | Simulation | Infer all subscales | 12                   | 12                   | 1                               | 0.0                       | 1000.0                    | 1                  |
    And the "shortcodes" filter is "on"

  @javascript
  Scenario: The end of summer time in Europe: server in Berlin, the teacher in UTC
    Given the following config values are set as admin:
      | config   | value         |
      | timezone | Europe/Berlin |
    And the following CAT results of "adaptivecatquizA" exist:
      | user     | end              | ability | valid |
      | student1 | 2026-10-24 23:45 | 1.0     | yes   |
      | student2 | 2026-10-25 10:00 | -1.0    | yes   |
      | student3 | 2026-10-25 22:00 | 1.0     | yes   |
      | student4 | 2026-10-27 09:00 | -1.0    | yes   |
    When I am on the "pageoct" "page activity" page logged in as teacherutc
    And I click on "Testusage" "link" in the "#region-main" "css_element"
    And I wait until "[data-chartname='attemptspertimerange'] .chart-image canvas" "css_element" exists
    Then "[data-chartname='attemptspertimerange'] .chart-image canvas" "css_element" should be visible
    And I click on "Show chart data" "link" in the "[data-chartname='attemptspertimerange']" "css_element"
    ## One row per day of the teacher, in order; the 25th - the day of the change - in its place.
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row']][1]/th[normalize-space(.)='24.10.2026']" "xpath_element" should exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row']][2]/th[normalize-space(.)='25.10.2026']" "xpath_element" should exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row']][3]/th[normalize-space(.)='26.10.2026']" "xpath_element" should exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row']][4]/th[normalize-space(.)='27.10.2026']" "xpath_element" should exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row']][5]" "xpath_element" should not exist
    ## Two attempts on the 25th, one on each other day: every attempt counted once, under its day.
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row'][normalize-space(.)='24.10.2026']][sum(td) = 1]" "xpath_element" should exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row'][normalize-space(.)='25.10.2026']][sum(td) = 2]" "xpath_element" should exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row'][normalize-space(.)='26.10.2026']][sum(td) = 0]" "xpath_element" should exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row'][normalize-space(.)='27.10.2026']][sum(td) = 1]" "xpath_element" should exist

  @javascript
  Scenario: The start of summer time in Europe: server in UTC, the teacher in Berlin
    Given the following config values are set as admin:
      | config   | value |
      | timezone | UTC   |
    And the following CAT results of "adaptivecatquizA" exist:
      | user     | end              | ability | valid |
      | student1 | 2026-03-28 22:45 | 1.0     | yes   |
      | student2 | 2026-03-29 09:00 | -1.0    | yes   |
      | student3 | 2026-03-30 07:00 | 1.0     | yes   |
      | student4 | 2026-03-31 06:00 | -1.0    | yes   |
    When I am on the "pagemar" "page activity" page logged in as teacherberlin
    And I click on "Testusage" "link" in the "#region-main" "css_element"
    And I wait until "[data-chartname='attemptspertimerange'] .chart-image canvas" "css_element" exists
    Then "[data-chartname='attemptspertimerange'] .chart-image canvas" "css_element" should be visible
    And I click on "Show chart data" "link" in the "[data-chartname='attemptspertimerange']" "css_element"
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row']][1]/th[normalize-space(.)='28.03.2026']" "xpath_element" should exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row']][2]/th[normalize-space(.)='29.03.2026']" "xpath_element" should exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row']][3]/th[normalize-space(.)='30.03.2026']" "xpath_element" should exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row']][4]/th[normalize-space(.)='31.03.2026']" "xpath_element" should exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row']][5]" "xpath_element" should not exist
    And "//div[@data-chartname='attemptspertimerange']//table//tr[th[@scope='row'][normalize-space(.)='29.03.2026']][sum(td) = 1]" "xpath_element" should exist
