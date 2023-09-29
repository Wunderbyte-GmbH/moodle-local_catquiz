<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_catquiz
 * @category    string
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/catquiz/lib.php');

$string['pluginname'] = 'ALiSe CAT Quiz';
$string['catquiz'] = 'Catquiz';

// Catquiz handler.
$string['catscale'] = 'CAT scale';
$string['catquizsettings'] = 'CAT quiz settings';
$string['selectmodel'] = 'Choose a model';
$string['model'] = 'Model';
$string['modeldeactivated'] = 'Deactivate CAT engine';
$string['usecatquiz'] = 'Use the catquiz engine for this test instance.';
$string['catscales'] = 'Define catquiz CAT scales';
$string['catscales:information'] = 'Define CAT scales: {$a->link}';
$string['catscalesname_exists'] = 'The name is already being used';
$string['cachedef_catscales'] = 'Caches the CAT scales of catquiz';
$string['catcatscales'] = 'CAT scales to be tested';
$string['catcatscales_help'] = 'Every CAT scales has testitems (questions) which will be used in the test.';
$string['nameexists'] = 'The name of the CAT scale already exists';
$string['createnewcatscale'] = 'Create new CAT scale';
$string['parent'] = 'Parent CAT scale - None if top level CAT scale';
$string['managecatscale'] = 'Manage CAT scale';
$string['managetestenvironments'] = 'Manage testenvironments';
$string['showlistofcatscalemanagers'] = "Show list of CAT scale managers";
$string['addcategory'] = "Add category";
$string['documentation'] = "Documentation";
$string['createcatscale'] = 'Create a CAT scale';
$string['cannotdeletescalewithchildren'] = 'Cannot delete CAT scale with children';
$string['passinglevel'] = 'Passing level in %';
$string['passinglevel_help'] = 'There is a level of personal competency that can be set for the test.';
$string['pilotratio'] = 'Rate of pilot questions';
$string['pilotratio_help'] = 'Floating point number that specifies how often pilot questions should be displayed. When a value of 0.5 is specified, then on average every second attempt will display a pilot question.';
$string['pilotattemptsthreshold'] = 'Pilotquestion attempt threshold';
$string['pilotattemptsthreshold_help'] = 'Questions with less attempts will be considered pilot questions';
$string['includepilotquestions'] = 'Include pilot questions in the quiz';

$string['timepacedtest'] = 'Timepaced test';
$string['maxtime'] = 'Max time for test';
$string['maxtimeperitem'] = 'Max time per question in seconds';
$string['mintimeperitem'] = 'Min time per question in seconds';
$string['actontimeout'] = 'Action on timeout';

$string['timeoutabortnoresult'] = 'Test aborted without result.';
$string['timeoutabortresult'] = 'Test aborted with result.';
$string['timeoutfinishwithresult'] = 'Test aborted after finished current question.';

$string['minmaxgroup'] = 'Add min and max value as decimal';
$string['minscalevalue'] = 'Min value';
$string['maxscalevalue'] = 'Max value';
$string['chooseparent'] = 'Choose parent scale';
$string['errorminscalevalue'] = 'Min value has to be smaller than max value';
$string['errorhastobefloat'] = 'Has to be a deciamal';

$string['addoredittemplate'] = "Add or edit template";

// Test Strategy.
$string['catquiz_teststrategyheader'] = 'Test strategy';
$string['catquiz_selectteststrategy'] = 'Select test strategy';

$string['teststrategy_base'] = 'Base class for test strategies';
$string['teststrategy_info'] = 'Info class for test strategies';
$string['teststrategy_fastest'] = 'Radical CAT';
$string['teststrategy_balanced'] = 'Moderate CAT';
$string['pilot_questions'] = 'Pilot questions';
$string['inferlowestskillgap'] = 'Infer lowest skill gap';
$string['infergreateststrength'] = 'Infer greatest strength';
$string['inferallsubscales'] = 'Infer all subscales';

$string['catquiz_selectfirstquestion'] = "Selection of first question";
$string['startwitheasiestquestion'] = "Start with the easiest question";
$string['startwithfirstofsecondquintil'] = "Start with the first question of the second quintil";
$string['startwithfirstofsecondquartil'] = "Start with the first question of the second quartil";
$string['startwithmostdifficultsecondquartil'] = "Start with the most difficult question of the second quartil";
$string['startwithaverageabilityoftest'] = "Use the average person ability of the current test";
$string['startwithcurrentability'] = "Use the person's current ability to determine the first question";

// Tests environment.
$string['newcustomtest'] = 'Custom test';
$string['lang'] = 'Language';
$string['component'] = 'Plugin';
$string['invisible'] = 'Invisible';
$string['edittestenvironment'] = 'Edit testenvironment';
$string['choosetest'] = 'Choose a test environment';
$string['parentid'] = 'Parent id';
$string['force'] = 'Force values';
$string['catscaleid'] = 'CAT scale ID';
$string['numberofquestions'] = '# questions';
$string['numberofusers'] = '# users';

// Cat contexts.
$string['addcontext'] = 'Add CAT context';
$string['managecatcontexts'] = 'Manage CAT contexts';
$string['manage_catcontexts'] = 'Manage CAT contexts';
$string['starttimestamp'] = 'Context start time';
$string['endtimestamp'] = 'Context end time';
$string['timemodified'] = 'Time modified';
$string['notimelimit'] = 'Kein Zeitlimit';
$string['attempts'] = 'Attempts';
$string['action'] = 'Action';
$string['searchcatcontext'] = 'Search CAT contexts';
$string['selectcatcontext'] = 'Select a CAT context';
$string['defaultcontextname'] = 'Default CAT context';
$string['defaultcontextdescription'] = 'Includes all test items';
$string['noint'] = 'Please enter an integer number';
$string['notpositive'] = 'Please enter a positive number';
$string['strategy'] = 'Strategy';
$string['max_iterations'] = 'Maximum number of iterations';
$string['model_override'] = 'Only use this model';

$string['starttimestamp'] = 'Starttime';
$string['endtimestamp'] = 'Endtime';

// Buttons.
$string['subscribe'] = 'Subscribe';
$string['subscribed'] = 'Subscribed';
$string['timemodified'] = 'Time modified';

// Events and Event Logs.
$string['target'] = 'Target';
$string['userupdatedcatscale'] = 'User with id {$a->userid} updated {$a->catscalelink}';
$string['catscale_updated'] = 'CAT scale updated';
$string['testitem'] = 'Testitem with id {$a}';
$string['add_testitem_to_scale'] = '{$a->testitemlink} added to {$a->catscalelink}';
$string['testiteminscale_added'] = 'Testitem added to CAT scale';
$string['testiteminscale_updated'] = 'Testitem updated in CAT scale';
$string['update_testitem_in_scale'] = '{$a->testitemlink} updated in {$a->catscalelink}';
$string['testitemactivitystatus_updated'] = 'Activity status of testitem updated.';
$string['update_testitem_activity_status'] = 'Activity status of {$a->testitemlink} changed.';
$string['activitystatussetinactive'] = 'Testitem is now inactive.';
$string['activitystatussetactive'] = 'Testitem is now active.';
$string['testitemstatus_updated'] = 'Status of testitem updated.';
$string['testitem_status_updated_description'] = 'Status of {$a->testitemlink} set to: {$a->statusstring}';
$string['catscale_created'] = 'CAT scale created';
$string['create_catscale_description'] = 'CAT scale {$a->catscalelink} with id {$a->objectid} created.';
$string['context_created'] = 'CAT context created.';
$string['create_context_description'] = 'CAT context {$a} created.';
$string['context_updated'] = 'CAT Context updated';
$string['update_context_description'] = 'CAT context {$a} updated.';
$string['logsafter'] = 'Logs after';
$string['logsbefore'] = 'Logs before';
$string['calculation_executed'] = 'Calculation executed.';
$string['executed_calculation_description'] = 'A calculation was executed of catscale {$a->catscalename} with id {$a->catscaleid} in context {$a->contextid} by user {$a->userid}. {$a->numberofitems} items were recalculated.';
$string['deletedcatscale'] = 'catscale that doesn`t exist anymore';
$string['attempt_completed'] = 'Attempt completed';
$string['complete_attempt_description'] = 'Attempt with id {$a->attemptid} in CAT scale {$a->catscalelink} completed by user {$a->userid}.';
$string['eventtime'] = 'Event time';
$string['eventname'] = 'Event name';
$string['testitem_imported'] = 'Testitem(s) imported';
$string['imported_testitem_description'] = '{$a} testitems were imported.';

// Message.
$string['messageprovider:catscaleupdate'] = 'Notification of CAT scale update';
$string['catscaleupdatedtitle'] = 'A CAT scale was updated';
$string['catscaleupdatedbody'] = 'A CAT scale was updated. TODO: more description.';
$string['messageprovider:updatecatscale'] = 'Is allowed to update catscale';

// Access.php.
$string['catquiz:canmanage'] = 'Is allowed to manage Catquiz plugin';
$string['catquiz:subscribecatscales'] = 'Is allowed to subscribe to Catquiz CAT scales';
$string['catquiz:manage_catscales'] = 'Is allowed to manage Catquiz CAT scales';

// Role.
$string['catquizroledescription'] = 'Catquiz Manager';

// Navbar.
$string['managecatscales'] = 'Manage CAT scales';
$string['test'] = 'Test Subscription';

// Assign testitems to catscale page.
$string['assigntestitemstocatscales'] = "Assign testitem to CAT scale";
$string['assign'] = "Assign";
$string['questioncategories'] = 'Question category';
$string['questiontype'] = 'Question type';
$string['addtestitemtitle'] = 'Add test items to CAT scales';
$string['addtestitembody'] = 'Do you want to add the following test items to the current CAT scale? <br> {$a->data}';
$string['addtestitemsubmit'] = 'Add';
$string['addtestitem'] = 'Add test items';
$string['usage'] = 'Usage';
$string['failedtoaddmultipleitems'] = '{$a->numadded} questions successfully added, failed with {$a->numfailed} questions: {$a->failedids}';
$string['testiteminrelatedscale'] = 'Test item is already assigned to a parent- or subscale';
$string['notyetcalculated'] = 'Not yet calculated';
$string['notyetattempted'] = 'No attempts';

$string['removetestitemtitle'] = 'Remove test item from CAT scale';
$string['removetestitembody'] = 'Do you want to remove the following test items from the current CAT scale? <br> {$a->data}';
$string['removetestitemsubmit'] = 'Remove';
$string['removetestitem'] = 'Remove test items';

$string['testitems'] = 'Test items';
$string['questioncontextattempts'] = '# Attempts in selected context';

// Students table.
$string['studentstats'] = 'Students';

// Email Templates.
$string['notificationcatscalechange'] = 'Hello {$a->firstname} {$a->lastname},
CAT scales have been changed on the Moodle platform {$a->instancename}.
This email informs you as the CAT Manager* responsible for those CAT scales of these changes . {$a->editorname} made the following changes to the CAT scale "{$a->catscalename}":
    {$a->changedescription}
You can review the current state here: {$a->linkonscale}';

// Catscale Dashboard.
$string['statistics'] = "Stats";
$string['models'] = "Models";
$string['previewquestion'] = "Preview question";
$string['personability'] = "Person ability";
$string['personabilities'] = "Person abilities";
$string['personabilitiesnodata'] = "No person abilities were calculated";
$string['itemdifficulties'] = "Item difficulties";
$string['itemdifficultiesnodata'] = "No item difficulties were calculated";
$string['somethingwentwrong'] = 'Something went wrong. Please contact your admin.';
$string['recalculationscheduled'] = 'Recalculation of the context parameters has been scheduled';
$string['scaledetailviewheading'] = 'Detailview of catscale {$a}';

// Table.
$string['label'] = "Label";
$string['name'] = "Name";
$string['questiontext'] = "Question text";

// Testitem Dashboard.
$string['testitemdashboard'] = "Testitem Dashboard";
$string['itemdifficulty'] = "Item difficulty";
$string['likelihood'] = "Likelihood";

$string['difficulty'] = "Difficulty";
$string['discrimination'] = "Discrimination";
$string['lastattempttime'] = "Last attempt";
$string['guessing'] = "Guessing";

$string['numberofanswers'] = "Answers total";
$string['numberofusagesintests'] = "In tests";
$string['numberofpersonsanswered'] = "By different persons";
$string['numberofanswerscorrect'] = "Correct";
$string['numberofanswersincorrect'] = "Wrong";
$string['numberofanswerspartlycorrect'] = "Partly correct";
$string['averageofallanswers'] = "Average";

$string['itemstatus_-5'] = "Manually excluded"; // STATUS_EXCLUDED_MANUALLY.
$string['itemstatus_0'] = "Not yet calculated"; // STATUS_NOT_CALCULATED.
$string['itemstatus_1'] = "Calculated"; // STATUS_CALCULATED.
$string['itemstatus_4'] = "Manually updated"; // STATUS_UPDATED_MANUALLY.
$string['itemstatus_5'] = "Manually confirmed"; // STATUS_CONFIRMED_MANUALLY.

// Student Details.
$string['studentdetails'] = "Student details";
$string['enroled_courses'] = "Enroled courses";
$string['questionresults'] = "Question results";
$string['daysago'] = '{$a} days ago';
$string['hoursago'] = '{$a} hours ago';
$string['noaccessyet'] = 'No access yet';

// Tasks.
$string['task_recalculate_cat_model_params'] = "Recalculate CAT parameters";

// CAT Manager.
$string['catmanager'] = "CAT-Manager";
$string['summary'] = "Summary";
$string['questions'] = "Questions";
$string['testsandtemplates'] = "Tests & Templates";
$string['calculations'] = "Calculations";
$string['versioning'] = "Versioning";
$string['catscalesheading'] = "CAT scales";
$string['subscribedcatscalesheading'] = "Subscribed CAT scales";
$string['summarygeneral'] = "General";
$string['summarynumberofassignedcatscales'] = "Number of assigned CAT scales";
$string['summarynumberoftests'] = "Number of assigned tests";
$string['summarytotalnumberofquestions'] = "Number of questions (total)";
$string['summarylastcalculation'] = "Last complete calculation";
$string['recentevents'] = "Recent Events";
$string['aria:catscaleimage'] = "Background pattern for this CAT scale";
$string['healthstatus'] = "Health status";
$string['catmanagernumberofsubscales'] = "Number of subscales";
$string['catmanagernumberofquestions'] = "Number of questions";
$string['integratequestions'] = "Integrate questions from subscales";
$string['noscaleselected'] = "No scale selected";
$string['norecordsfound'] = "There are no questions in this scale";
$string['selectsubscale'] = "Select a subscale";
$string['selectcatscale'] = "Scale:";
$string['versionchosen'] = 'Version chosen:';
$string['pleasechoose'] = 'please choose';
$string['quizattempts'] = 'Quiz Attempts';
$string['calculate'] = 'Calculate';

// CAT Manager Questions Table.
$string['type'] = 'Type';
$string['attempts'] = 'Attempts';
$string['addquestion'] = 'Add question from catalogue';
$string['addtest'] = 'Add existing test';
$string['checklinking'] = 'Check linking';
$string['confirmdeletion'] = 'You are about to delete the following item: <br> "{$a->data}"';
$string['confirmactivitychange'] = 'You are about to change the activity status of the following item: <br> "{$a->data}"';
$string['genericsubmit'] = 'Confirm';
$string['deletedatatitle'] = 'Delete';
$string['toggleactivity'] = 'Activity status';
$string['errorrecordnotfound'] = 'There was an error with the database query. The record was not found.';
$string['trashbintitle'] = 'Delete item';
$string['cogwheeltitle'] = 'Display details';
$string['eyeicontitle'] = 'Activate/Disable';

// Testitem Detail View.
$string['questionpreview'] = 'Question preview';
$string['backtotable'] = 'Back to testitems table';
$string['local_catquiz_toggle_testitemstatus_message'] = 'Testitem status was updated';
$string['togglestatus'] = 'Toggle status';

// CAT Quiz handler.
$string['noremainingquestions'] = "You ran out of questions";
$string['errorfetchnextquestion'] = "There was an error while selecting the next question";
$string['reachedmaximumquestions'] = "Reached maximum number of questions";
$string['error'] = "An error occured";
$string['id'] = "ID";
$string['abortpersonabilitynotchanged'] = "Person parameter did not change";
$string['emptyfirstquestionlist'] = "Can't select a start question because the list is empty";
$string['feedbackcomparetoaverage'] = 'You performed better than {$a}% of your fellow students.';
$string['feedbackneedsimprovement'] = "Don't you think that you can do better?";

// Quiz Feedback.
$string['attemptfeedbacknotavailable'] = "No feedback available";
$string['allquestionsincorrect'] = "Not available - all questions were answered incorrectly";
$string['allquestionscorrect'] = "Not available- all questions were answered correctly";
$string['questionssummary'] = "Summary";
$string['currentability'] = "Your current skill level";
$string['currentabilityfellowstudents'] = "Current skill level of your fellow students";
$string['feedbackbarlegend'] = "Color code";
$string['feedbackbarlegend_region_1'] = "Your future instructors consider this level of knowledge to be insufficient to keep up with the studies.";
$string['feedbackbarlegend_region_2'] = "With this level of knowledge, one can expect to encounter regular difficulties during the studies.";
$string['feedbackbarlegend_region_3'] = "Experience has shown that it is typically possible to complete the program within the standard study period with knowledge at this level.";
$string['feedbackbarlegend_region_4'] = "This domain suggests a prior knowledge that even exceeds the requirements of the subject studies.";
$string['teacherfeedback'] = "Feedback for teachers";

$string['catquiz_feedbackheader'] = "Feedback";
$string['noselection'] = "No selection";
$string['lowerlimit'] = "Lower limit";

$string['setcoursesforscaletext'] = 'Set for catscale {$a} the courses in which users failing the lower limit should be inscribed to.';

// Quiz attempts
$string['catcontext'] = 'CAT Context';
$string['totalnumberoftestitems'] = "Total number of questions";
$string['numberoftestitemsused'] = "Number of displayed questions";
$string['personabilitybeforeattempt'] = "Ability before attempt";
$string['personabilityafterattempt'] = "Ability after attempt";
$string['instance'] = "Test";
$string['teststrategy'] = "Teststrategy";
$string['starttime'] = "Start";
$string['endtime'] = "End";

// CSV Import Form.
$string['importcsv'] = 'Import CSV';
$string['importsuccess'] = 'Import was successful. {$a} record(s) treated.';
$string['importfailed'] = 'Import failed';
$string['dateparseformat'] = 'Date parse format';
$string['dateparseformat_help'] = 'Please, use date format like specified in CSV file. Help with <a href="http://php.net/manual/en/function.date.php">this</a> resource for options.';
$string['defaultdateformat'] = 'j.n.Y H:i:s';
$string['importcolumnsinfos'] = 'Informations about columns to be imported:';
$string['mandatory'] = 'mandatory';
$string['format'] = 'format';
$string['downloaddemofile'] = 'Download demofile';
$string['labelidnotunique'] = 'Label {$a} is not unique.';
$string['labelidnotfound'] = 'Label {$a} not found.';
$string['updatedrecords'] = '{$a} record(s) updated.';
$string['addedrecords'] = '{$a} record(s) added.';
$string['callbackfunctionnotdefined'] = 'Callback function is not defined.';
$string['callbackfunctionnotapplied'] = 'Callback function could not be applied.';
$string['canbesetto0iflabelgiven'] = 'Can be 0 if matching of testitem is via label.';
$string['ifdefinedusedtomatch'] = 'If defined, will be used to match.';
$string['fieldnamesdontmatch'] = 'The imported fieldnames don`t match the defined fieldnames.';
$string['checkdelimiteroremptycontent'] = 'Check if data is given and separated via the selected symbol.';
$string['wronglabels'] = 'Imported CSV not containing the right labels. Column {$a} can not be importet.';
$string['nolabels'] = 'No column labels defined in settings object.';
$string['checkdelimiter'] = 'Check if data is separated via the selected symbol.';
$string['scaleinformation'] = 'The id of the CAT scale the item should be assigned to.';
$string['scalenameinformation'] = 'The name of the CAT scale the item should be assigned to. If no catscale id given, matching is done via name.';
$string['dataincomplete'] = 'Record with componentid {$a->id} is incomplete and could not be treated entirely. Check field "{$a->field}".';
$string['modelinformation'] = 'This field is necessary to entirely treat the record. If it is empty, item can only be assigned to CAT scale.';
$string['parentscalenamesinformation'] = 'You can enter parent scales for the defined scale, in order to create the correct scale strucutre on the fly. Start with the highest parent and separate all children with |';

// Testenvironments table.
$string['notifyallteachers'] = 'Notify all teachers';
$string['notifyteachersofselectedcourses'] = 'Notify teachers of selected courses';

$string['close'] = 'Close';

// Shortcodes
$string['shortcodeslistofquizattempts'] = 'Returns a table of quiz attempts';

// Validation
$string['valuemustbegreaterzero'] = 'Value must be greater than zero.';