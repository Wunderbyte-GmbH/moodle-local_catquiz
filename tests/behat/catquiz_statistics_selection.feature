@local @local_catquiz @javascript
Feature: Every chart of the statistics counts the same attempts; the first attempt of each person can be chosen (issue #88).
  Two people with two attempts each. A person is coloured by their latest valid result; with attempts=first,
  only the first completed attempt of each person counts, valid or not. "No attempts" counts who could
  have taken the test and has no attempt.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | student2 | Student2  | Test     | toolgenerator2@example.com |
      | student3 | Student3  | Test     | toolgenerator5@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
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
      | activity | name             | course | idnumber | content                            |
      | page     | All attempts     | C1     | page1    | [catquizstatistics]                |
      | page     | First attempts   | C1     | page2    | [catquizstatistics attempts=first] |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | Classical test | 2                    | 2                    | 0.0                       | 1000.0                    | 1                  |
    And the "shortcodes" filter is "on"
    ## student1: valid, then an invalid repetition. student2: invalid first, then valid.
    And the following CAT results of "adaptivecatquiz1" exist:
      | user     | days ago | ability | valid |
      | student1 | 3        | 1.0     | yes   |
      | student1 | 2        | -1.0    | no    |
      | student2 | 2        | 2.0     | no    |
      | student2 | 1        | 0.5     | yes   |

  Scenario: All attempts: two people with two attempts, both with a valid result; one without an attempt
    Given I am on the "page1" "page activity" page logged in as teacher
    When I click on "Testusage" "link" in the "#region-main" "css_element"
    And I click on "Show chart data" "link" in the "//div[contains(@id, '-testusage')]//div[@data-chartname='attemptsperperson']" "xpath_element"
    ## Columns: "No ability was calculated", "Ability level 1". Rows: the number of attempts.
    Then "//div[@data-chartname='attemptsperperson']//tr[th[normalize-space(.)='No attempts']]/td[1][normalize-space(.)='1']" "xpath_element" should exist
    And "//div[@data-chartname='attemptsperperson']//tr[th[normalize-space(.)='2']]/td[2][normalize-space(.)='2']" "xpath_element" should exist
    And "//div[@data-chartname='attemptsperperson']//tr[th[normalize-space(.)='2']]/td[1][normalize-space(.)='0']" "xpath_element" should exist
    And "//div[@data-chartname='attemptsperperson']//tr[th[normalize-space(.)='1']]/td[2][normalize-space(.)='0']" "xpath_element" should exist

  Scenario: First attempts: one attempt per person, the first one - valid or not
    Given I am on the "page2" "page activity" page logged in as teacher
    When I click on "Testusage" "link" in the "#region-main" "css_element"
    And I click on "Show chart data" "link" in the "//div[contains(@id, '-testusage')]//div[@data-chartname='attemptsperperson']" "xpath_element"
    Then "//div[@data-chartname='attemptsperperson']//tr[th[normalize-space(.)='No attempts']]/td[1][normalize-space(.)='1']" "xpath_element" should exist
    ## student1's first result is valid, student2's is not.
    And "//div[@data-chartname='attemptsperperson']//tr[th[normalize-space(.)='1']]/td[2][normalize-space(.)='1']" "xpath_element" should exist
    And "//div[@data-chartname='attemptsperperson']//tr[th[normalize-space(.)='1']]/td[1][normalize-space(.)='1']" "xpath_element" should exist
    And "//div[@data-chartname='attemptsperperson']//tr[th[normalize-space(.)='2']]/td[2][normalize-space(.)='0']" "xpath_element" should exist
