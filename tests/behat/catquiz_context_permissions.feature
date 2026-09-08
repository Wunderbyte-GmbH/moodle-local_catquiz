@local @local_catquiz @javascript
Feature: CAT quiz permissions are judged in the course context of the attempt.
  As a teacher of the course an attempt belongs to I see the teacher feedback,
  even though I hold no site wide role. As a participant I never see it (issue #18).

  Background:
    Given the following "users" exist:
      | username    | firstname   | lastname | email                      |
      | student1    | Student1    | Test     | toolgenerator1@example.com |
      | teacher     | Teacher     | Test     | toolgenerator3@example.com |
      | nonedteach  | Nonediting  | Test     | toolgenerator5@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    ## The teachers are enrolled in this course only and hold no system role.
    ## That is exactly the situation the old system-context check got wrong.
    And the following "course enrolments" exist:
      | user       | course | role           |
      | student1   | C1     | student        |
      | teacher    | C1     | editingteacher |
      | nonedteach | C1     | teacher        |
    And the following "local_catquiz > questions" exist:
      | filepath                                                            | filename                               | course |
      | local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml | quiz-adaptivetest-Simulation-small.xml | C1     |
    And the following "local_catquiz > importedcatscales" exist:
      | filepath                                          | filename             |
      | local/catquiz/tests/fixtures/simulation_small.csv | simulation_small.csv |
    ## TinyMCE misbehaves in acceptance tests; use a plain editor.
    And the following config values are set as admin:
      | config      | value         |
      | texteditors | atto,textarea |
    And the following "activities" exist:
      | activity     | name             | course | section | idnumber         | intro               |
      | adaptivequiz | My Adaptive Quiz | C1     | 1       | adaptivecatquiz1 | Adaptive Quiz Intro |
    And the following "local_catquiz > testsettings" exist:
      | course | adaptivecatquiz  | catmodel | catscales  | cateststrategy         | catquiz_selectfirstquestion | catquiz_minquestions | catquiz_maxquestions | catquiz_standarderror_min | catquiz_standarderror_max | numberoffeedbackoptions |
      | C1     | adaptivecatquiz1 | catquiz  | Simulation | Infer lowest skill gap | -2                          | 2                    | 4                    | 0.0                       | 1000.0                    | 2                       |
    ## Same settings round-trip as the established feedback scenarios: the CAT
    ## settings generator writes the initial JSON, but the adaptivequiz integration
    ## normalises it through the activity settings form. Without this the adapter
    ## can read incomplete settings and finish the attempt after question 1.
    And I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I follow "Settings"
    And I wait until the page is ready
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: An editing teacher of the course may review a participant's attempt
    ## Before issue #18 this page set the system context and required
    ## local/catquiz:manage_catscales there, so a teacher of the very course the
    ## attempt belongs to was locked out of reviewing it.
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
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
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I log out
    When I am on the "adaptivecatquiz1" Activity page logged in as teacher
    And I visit the CAT attempt feedback page for the last attempt of "student1"
    And I wait until the page is ready
    Then I should not see "You do not have permission to review this attempt."
    And "//div[contains(@class, 'catquiz_feedback')]" "xpath_element" should exist

  @javascript
  Scenario: A non-editing teacher of the course may review the attempt as well
    Given I am on the "adaptivecatquiz1" Activity page logged in as student1
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
    And I click on "falsche Antwort 2" "text" in the "Question 4" "question"
    And I click on "Submit answer" "button"
    And I wait until the page is ready
    And I log out
    When I am on the "adaptivecatquiz1" Activity page logged in as nonedteach
    And I visit the CAT attempt feedback page for the last attempt of "student1"
    And I wait until the page is ready
    Then I should not see "You do not have permission to review this attempt."
    And "//div[contains(@class, 'catquiz_feedback')]" "xpath_element" should exist

  ## NOT covered in the UI: the denial case (a participant opening another
  ## participant's attempt). The page correctly answers with a moodle_exception, but
  ## Behat treats every Moodle error page (div[@data-rel='fatalerror']) as a failed
  ## step, so a UI scenario could only pass by weakening the page to a soft
  ## notification - trading real access control for testability. The denial is
  ## asserted precisely at unit level instead:
  ## context_resolver_test::test_participant_sees_only_themselves and
  ## ::test_teacher_of_other_course_has_no_access.
  ##
  ## NOT covered here: cross-course isolation (a teacher of course B must not see
  ## attempts of course A). Driving that through the UI would need a second course
  ## with its own quiz, questions and scales purely to assert a denial that is far
  ## more precisely expressed as a unit assertion. It is covered by
  ## context_resolver_test::test_teacher_of_other_course_has_no_access.
  ##
  ## Also NOT covered: the "Feedback for teachers" tabs. Only the pilotquestions and
  ## debuginfo generators emit teacher feedback at all - the first needs piloted
  ## items in the attempt, the second needs debugging enabled - so with this fixture
  ## the tab is absent regardless of permissions and would assert nothing about them.
