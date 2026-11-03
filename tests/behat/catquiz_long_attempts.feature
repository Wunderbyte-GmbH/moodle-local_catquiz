@local @local_catquiz @javascript
Feature: Long attempts: FASTEST and CLASSIC are not cut short by per-scale limits (issue #135).
  A whole attempt in the browser, as long as the settings ask. FASTEST draws globally by information: a per-scale
  maximum of 2 does not end it before its global number of questions, although 2 per leaf scale is less. CLASSIC
  is a fixed form: a per-scale maximum of 1 does not shorten it. Each question is asked once, every answer graded,
  the stop reason is the global maximum.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    ## 28 questions in Simulation: subscales SimA, SimB and SimC, each with subscales of its own.
    And the following "local_catquiz > questions" exist:
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                           | filename              |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
  Scenario Outline: <strategy> asks all <n> questions though the per-scale maximum is <perscale>
    Given the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy | catquiz_minquestions | catquiz_maxquestions | catquiz_minquestionspersubscale | catquiz_maxquestionspersubscale | catquiz_standarderror_min | catquiz_standarderror_max | scalefeedbacktexts |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | <strategy>     | <n>                  | <n>                  | 1                               | <perscale>                      | 0.0                       | 1000.0                    | 1                  |
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    When I answer "richtige Antwort" and "falsche Antwort 1" in turn until the attempt ends, at most 30 times
    Then the last CAT attempt of "student1" asked <n> different questions from at least <subscales> subscales of "Simulation"
    And the attempts of "student1" at "adaptivecatquiz1" are:
      | state    | stop reason      | finished | questions | answered | duplicates | cat result |
      | complete | maximumquestions | yes      | <n>       | <n>      | no         | ended      |

    Examples:
      | strategy       | n  | perscale | subscales |
      | CAT            | 20 | 2        | 3         |
      | Classical test | 28 | 1        | 3         |
