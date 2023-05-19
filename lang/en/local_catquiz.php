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

$string['pluginname'] = 'ALiSe CAT Quiz';
$string['catquiz'] = 'Catquiz';

// Catquiz handler.
$string['catquizsettings'] = 'Cat quiz settings';
$string['selectmodel'] = 'Choose a model';
$string['model'] = 'Model';
$string['modeldeactivated'] = 'Deactivate CAT engine';
$string['usecatquiz'] = 'Use the catquiz engine for this test instance.';
$string['catscales'] = 'Define catquiz catscales';
$string['catscales:information'] = 'Define catscales: {$a->link}';
$string['catscalesname_exists'] = 'The name is already being used';
$string['cachedef_catscales'] = 'Caches the catscales of catquiz';
$string['catcatscales'] = 'Catscales to be tested';
$string['catcatscales_help'] = 'Every catscales has testitems (questions) which will be used in the test.';
$string['nameexists'] = 'The name of the catscale already exists';
$string['createnewcatscale'] = 'Create new catscale';
$string['parent'] = 'Parent catscale - None if top level catscale';
$string['managecatscale'] = 'Manage catscale';
$string['managetestenvironments'] = 'Manage testenvironments';
$string['showlistofcatscalemanagers'] = "Show list of catscale-Managers";
$string['addcategory'] = "Add category";
$string['documentation'] = "Documentation";
$string['createcatscale'] = 'Create your first catquiz catscale!';
$string['cannotdeletescalewithchildren'] = 'Cannot delete CAT scale with children';
$string['passinglevel'] = 'Passing level in %';
$string['passinglevel_help'] = 'There is a level of personal competency that can be set for the test.';

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

// Tests environment.
$string['newcustomtest'] = 'Custom test';
$string['lang'] = 'Language';
$string['component'] = 'Plugin';
$string['invisible'] = 'Invisible';
$string['edittestenvironment'] = 'Edit testenvironment';
$string['choosetest'] = 'Choose a test environment';
$string['parentid'] = 'Parent id';
$string['force'] = 'Force values';

// Cat contexts.
$string['addcontext'] = 'Add Cat context';
$string['managecatcontexts'] = 'Manage Cat contexts';
$string['manage_catcontexts'] = 'Manage Cat contexts';
$string['starttimestamp'] = 'Context start time';
$string['endtimestamp'] = 'Context end time';
$string['timemodified'] = 'Time modified';
$string['notimelimit'] = 'Kein Zeitlimit';
$string['attempts'] = 'Attempts';
$string['action'] = 'Aktion';
$string['searchcatcontext'] = 'Search Cat contexts';
$string['selectcatcontext'] = 'Select a Cat context';
$string['defaultcontextname'] = 'Default Cat context';
$string['defaultcontextdescription'] = 'Includes all test items';
$string['noint'] = 'Please enter an integer number';
$string['notpositive'] = 'Please enter a positive number';
$string['strategy'] = 'Strategy';
$string['max_iterations'] = 'Maximum number of iterations';

$string['starttimestamp'] = 'Starttime';
$string['endtimestamp'] = 'Endtime';

// Buttons.
$string['subscribe'] = 'Subscribe';
$string['subscribed'] = 'Subscribed';
$string['timemodified'] = 'Time modified';

// Events.
$string['userupdatedcatscale'] = 'User with id {$a->userid} updated catscale with id {$a->objectid}';

// Message.
$string['messageprovider:catscaleupdate'] = 'Notification of catscale update';
$string['catscaleupdatedtitle'] = 'A catscale was updated';
$string['catscaleupdatedbody'] = 'A catscale was updated. TODO: more description.';

// Access.php.
$string['catquiz:canmanage'] = 'Is allowed to manage Catquiz plugin';
$string['catquiz:subscribecatscales'] = 'Is allowed to subscribe to Catquiz catscales';
$string['catquiz:manage_catscales'] = 'Is allowed to maange Catquiz catscales';

// Role.
$string['catquizroledescription'] = 'Catquiz Manager';

// Navbar.
$string['managecatscales'] = 'Manage CAT Scales';
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

$string['removetestitemtitle'] = 'Remove test item from CAT scale';
$string['removetestitembody'] = 'Do you want to remove the following test items from the current CAT scale? <br> {$a->data}';
$string['removetestitemsubmit'] = 'Remove';
$string['removetestitem'] = 'Remove test items';

$string['testitems'] = 'Test items';
$string['questioncontextattempts'] = '# Attempts in selected context';

// Students table
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
$string['statindependence'] = "Statistical Independence";
$string['loglikelihood'] = "Loglikelihood";
$string['differentialitem'] = "Differential Item Functioning";
$string['previewquestion'] = "Preview question";
$string['personability'] = "Person ability";
$string['personabilities'] = "Person abilities";
$string['personabilitiesnodata'] = "No person abilities were calucalted";
$string['itemdifficulties'] = "Item difficulties";
$string['itemdifficultiesnodata'] = "No item difficulties were calculated";

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

$string['numberofanswers'] = "Answers total";
$string['numberofusagesintests'] = "In tests";
$string['numberofpersonsanswered'] = "By different persons";
$string['numberofanswerscorrect'] = "Correct";
$string['numberofanswersincorrect'] = "Wrong";
$string['numberofanswerspartlycorrect'] = "Partly correct";
$string['averageofallanswers'] = "Average";

$string['problematic'] = 'Problematic';
$string['notselected'] = 'Not chosen';
$string['selected'] = 'Automatically chosen';
$string['manuallyselected'] = 'Manually chosen';
$string['manuallyexcluded'] = 'Manually excluded';

// Student Details
$string['studentdetails'] = "Student details";

// Tasks
$string['task_recalculate_cat_model_params'] = "Recalculate CAT parameters";