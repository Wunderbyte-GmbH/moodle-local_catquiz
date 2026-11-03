@local @local_catquiz @javascript
Feature: Feedback routing per strategy and result, repeated without doubling (issue #131).
  For each of the six strategies an attempt is played with a mixed and with an all-correct result. The courses and
  groups the person ends up in are checked against the stored results of the attempt: an invalid attempt routes
  nobody; a strategy that selects one scale routes it, the others every validly measured, reported scale. Routing
  the attempt again - a repeated finalisation, a retried task - adds no enrolment and no group membership.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname      | shortname |
      | Course 1      | C1        |
      | Target course | TC        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "groups" exist:
      | name              | course | idnumber |
      | Routed Simulation | C1     | RS       |
      | Routed SimA       | C1     | RA       |
      | Routed SimA01     | C1     | RA01     |
      | Routed SimA02     | C1     | RA02     |
      | Routed SimB       | C1     | RB       |
      | Routed SimB01     | C1     | RB01     |
      | Routed SimB02     | C1     | RB02     |
      | Routed SimB03     | C1     | RB03     |
      | Routed SimC01     | C1     | RC01     |
      | Routed SimC02     | C1     | RC02     |
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
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |

  Scenario Outline: <strategy>, answered <pattern>: the routing follows the stored results and is not repeated
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | routingcourse | routinggroupprefix |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | <strategy>     | 8                    | 8                    | 1                               | 0.0                       | 1000.0                    | TC            | Routed             |
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    When I answer "<first>" and "<second>" in turn until the attempt ends, at most 10 times
    Then "student1" is routed by the stored results of the last attempt at "adaptivecatquiz1" into "TC" and "Routed" groups
    When the last attempt of "student1" at "adaptivecatquiz1" is finalised and routed again
    Then "student1" is routed by the stored results of the last attempt at "adaptivecatquiz1" into "TC" and "Routed" groups
    And "student1" was routed into "TC" at most once

    Examples:
      | strategy                 | pattern     | first            | second            |
      | CAT                      | mixed       | richtige Antwort | falsche Antwort 1 |
      | CAT                      | all correct | richtige Antwort | richtige Antwort  |
      | Classical test           | mixed       | richtige Antwort | falsche Antwort 1 |
      | Classical test           | all correct | richtige Antwort | richtige Antwort  |
      | Infer all subscales      | mixed       | richtige Antwort | falsche Antwort 1 |
      | Infer all subscales      | all correct | richtige Antwort | richtige Antwort  |
      | Infer relevant subscales | mixed       | richtige Antwort | falsche Antwort 1 |
      | Infer relevant subscales | all correct | richtige Antwort | richtige Antwort  |
      | Infer lowest skill gap   | mixed       | richtige Antwort | falsche Antwort 1 |
      | Infer lowest skill gap   | all correct | richtige Antwort | richtige Antwort  |
      | Infer greatest strength  | mixed       | richtige Antwort | falsche Antwort 1 |
      | Infer greatest strength  | all correct | richtige Antwort | richtige Antwort  |
