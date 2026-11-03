@local @local_catquiz @javascript
Feature: An open attempt is continued or started anew by choice, and an attempt out of time is ended (mod_adaptivequiz #5).
  The activity page no longer continues an attempt in progress silently. The person chooses: start a new
  attempt - the old one is ended and counted - or continue. When the time for the question on the screen
  is over, the attempt goes on with a new question. When the time for the attempt is over, it is ended
  as the time limit ends it, and a new start is offered.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student1 | Student1  | Test     | toolgenerator1@example.com |
      | student2 | Student2  | Test     | toolgenerator2@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
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
      | C1     | adaptivecatquiz1 | catquiz  | Ressourcen | Classical test | 1                    | 2                    | 0.0                       | 1000.0                    | 1                  |

  Scenario: An attempt in progress is continued, or ended for a new one
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Text of resource question two."
    When I am on the "adaptivecatquiz1" Activity page
    Then I should see "You have an attempt in progress"
    And "Start a new attempt" "button" should exist
    When I click on "Continue attempt" "button"
    And I wait until the page is ready
    Then I should see "Text of resource question two."
    ## A reload of the activity page offers the same choice again; nothing was ended by showing it.
    When I am on the "adaptivecatquiz1" Activity page
    And I reload the page
    Then I should see "You have an attempt in progress"
    When I click on "Start a new attempt" "button"
    And I click on "Yes" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should see "Text of resource question one."
    When I am on the "adaptivecatquiz1" Activity page
    Then I should see "You have an attempt in progress"

  Scenario: The time for the question on the screen is over: the attempt goes on with a new question
    Given the CAT settings of "adaptivecatquiz1" limit a question to 1 minutes
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Text of resource question one."
    And the question on the screen of "student1" was shown 5 minutes ago
    When I am on the "adaptivecatquiz1" Activity page
    Then I should see "The time for the question you were on has run out"
    When I click on "Continue with a new question" "button"
    And I wait until the page is ready
    Then I should see "Text of resource question two."
    And I should not see "Text of resource question one."

  Scenario: The time for the attempt is over: it is ended, and a new start is offered
    Given the CAT settings of "adaptivecatquiz1" limit an attempt to 5 minutes
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I should see "Text of resource question one."
    And the open attempt of "student1" started 10 minutes ago
    When I am on the "adaptivecatquiz1" Activity page
    Then I should see "The time for your previous attempt had run out, so it was ended."
    And I should not see "You have an attempt in progress"
    And "Start attempt" "link" should exist

  Scenario: Reload and back during the choice, a double click and parallel tabs leave one open attempt
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I should see "Text of resource question two."
    When I am on the "adaptivecatquiz1" Activity page
    And I click on "Start a new attempt" "button"
    ## The confirmation is open; a reload ends nothing.
    And I reload the page
    Then I should see "You have an attempt in progress"
    And the attempts of "student1" at "adaptivecatquiz1" are:
      | state      | stop reason | finished | questions | duplicates | cat result |
      | inprogress | -           | no       | 2         | no         | open       |
    ## Back out of the open confirmation: nothing either.
    When I click on "Start a new attempt" "button"
    And I press the "back" button in the browser
    And I am on the "adaptivecatquiz1" Activity page
    Then the attempts of "student1" at "adaptivecatquiz1" are:
      | state      | stop reason | finished | questions | duplicates | cat result |
      | inprogress | -           | no       | 2         | no         | open       |
    ## A double click on "Yes": the old attempt is ended once, one new attempt begins.
    When I send the resume action "restart" for the open attempt of "student1" at "adaptivecatquiz1" twice
    Then the attempts of "student1" at "adaptivecatquiz1" are:
      | state      | stop reason | finished | questions | duplicates | cat result |
      | complete   | restarted   | yes      | 2         | no         | ended      |
      | inprogress | -           | no       | 1         | no         | open       |
    ## Three tabs open the attempt at once: still one open attempt, its question not given twice.
    When I request the attempt page of "adaptivecatquiz1" 3 times at once
    Then the attempts of "student1" at "adaptivecatquiz1" are:
      | state      | stop reason | finished | questions | duplicates | cat result |
      | complete   | restarted   | yes      | 2         | no         | ended      |
      | inprogress | -           | no       | 1         | no         | open       |

  Scenario: A request that is not the person's own choice changes nothing
    Given I am on the "adaptivecatquiz1" Activity page logged in as student2
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I log out
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I am on the "adaptivecatquiz1" Activity page
    ## A stale or forged sesskey.
    When I send the resume action "restart" for the open attempt of "student1" at "adaptivecatquiz1" with a wrong sesskey
    ## Someone else's attempt.
    And I send the resume action "restart" for the open attempt of "student2" at "adaptivecatquiz1"
    ## An action the page never offers, and a new question while the time for the current one is not over.
    And I send the resume action "evil" for the open attempt of "student1" at "adaptivecatquiz1"
    And I send the resume action "newquestion" for the open attempt of "student1" at "adaptivecatquiz1"
    Then the attempts of "student1" at "adaptivecatquiz1" are:
      | state      | stop reason | finished | questions | duplicates | cat result |
      | inprogress | -           | no       | 2         | no         | open       |
    And the attempts of "student2" at "adaptivecatquiz1" are:
      | state      | stop reason | finished | questions | duplicates | cat result |
      | inprogress | -           | no       | 1         | no         | open       |
    ## A lost session.
    When I log out
    And I send the resume action "restart" for the open attempt of "student1" at "adaptivecatquiz1"
    Then the attempts of "student1" at "adaptivecatquiz1" are:
      | state      | stop reason | finished | questions | duplicates | cat result |
      | inprogress | -           | no       | 2         | no         | open       |

  Scenario: The attempt runs out of time while the choice is open: the time limit ends it, not the new start
    Given the CAT settings of "adaptivecatquiz1" limit an attempt to 5 minutes
    And I am on the "adaptivecatquiz1" Activity page logged in as student1
    And I click on "Start attempt" "link"
    And I wait until the page is ready
    And I click on "richtige Antwort" "text" in the "Question 1" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    When I am on the "adaptivecatquiz1" Activity page
    Then I should see "You have an attempt in progress"
    And the open attempt of "student1" started 10 minutes ago
    When I click on "Start a new attempt" "button"
    And I click on "Yes" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should see "Text of resource question one."
    And the attempts of "student1" at "adaptivecatquiz1" are:
      | state      | stop reason | finished | questions | duplicates | cat result |
      | complete   | timelimit   | yes      | 2         | no         | ended      |
      | inprogress | -           | no       | 1         | no         | open       |
