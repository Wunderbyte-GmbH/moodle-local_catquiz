<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Seeds an installed site for the attempt load test of mod_adaptivequiz #17 (tests/load/attempt_load.py).
 *
 * One course, a number of participants with a known password, one CATquiz activity over the Simulation scale
 * (422 questions with item parameters) whose attempts administer exactly --items questions. The attempt trace
 * (local_catquiz/attempttrace) is switched on: every request of an attempt leaves one row in local_catquiz_trace
 * with the host's and the CAT model's spans, memory and database queries, under the request id the host sends.
 *
 * Prints "export KEY='value'" lines for the load driver. Not for a production site: it switches the trace on.
 *
 * Usage (from the Moodle root): php local/catquiz/tests/load/seed_attempt_load.php [--users=8] [--items=160]
 *     [--shortname=catquiz-load] [--userprefix=loaduser]
 *
 * A second run into the same site needs another --shortname and --userprefix: the activity then gets the idnumber
 * of the short name without its dashes (catquiz-load: catquizload), which analyse_attempt_load.php takes as --idnumber.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/catquiz/lib.php');

[$options] = cli_get_params(
    ['users' => 8, 'items' => 160, 'strategy' => 'adaptive', 'shortname' => 'catquiz-load', 'userprefix' => 'loaduser',
        'help' => false],
    ['h' => 'help']
);
if ($options['help']) {
    cli_writeln("Seed the attempt load test (mod_adaptivequiz #17).\n  --users=N  participants (default 8)\n"
        . "  --items=N  questions per attempt (default 160)\n  --strategy=adaptive|classic\n"
        . "  --shortname=S  short name of the course (default catquiz-load)\n  --userprefix=P  (default loaduser)");
    exit(0);
}
$numusers = max(1, (int) $options['users']);
$numitems = max(1, (int) $options['items']);
$shortname = clean_param((string) $options['shortname'], PARAM_ALPHANUMEXT);
$idnumber = str_replace(['-', '_'], '', $shortname);
$userprefix = clean_param((string) $options['userprefix'], PARAM_ALPHANUM);
$strategy = $options['strategy'] === 'classic' ? LOCAL_CATQUIZ_STRATEGY_CLASSIC : LOCAL_CATQUIZ_STRATEGY_ALLSUBS;

if (class_exists('\core\test\phpunit\phpunit_util')) {
    $generator = \core\test\phpunit\phpunit_util::get_data_generator();
} else {
    require_once($CFG->dirroot . '/lib/phpunit/classes/util.php');
    $generator = phpunit_util::get_data_generator();
}
\core\session\manager::set_user(get_admin());

$course = $DB->get_record('course', ['shortname' => $shortname]);
if ($course) {
    cli_error("A course '{$shortname}' exists already; take another --shortname and --userprefix or delete it first.");
}
$course = $generator->create_course(['fullname' => 'CATquiz attempt load ' . $shortname, 'shortname' => $shortname]);
$catquiz = $generator->get_plugin_generator('local_catquiz');
$catquiz->create_catquiz_questions([
    'filepath' => 'local/catquiz/tests/fixtures/simulation.xml',
    'filename' => 'simulation.xml',
    'courseid' => $course->id,
]);
$catquiz->create_catquiz_importedcatscales(['filename' => 'simulation.csv']);
$scaleid = (int) $DB->get_field('local_catquiz_catscales', 'id', ['name' => 'Simulation', 'parentid' => 0], MUST_EXIST);

// The activity's own limit must not end the attempt before the CAT settings do: the generator of the host picks one.
$quiz = $generator->create_module('adaptivequiz', ['course' => $course->id, 'name' => 'Load', 'idnumber' => $idnumber,
    'minimumquestions' => 1, 'maximumquestions' => $numitems]);
$catquiz->create_catquiz_testsettings([
    'courseid' => $course->id,
    'adaptivecatquizid' => $quiz->id,
    'catmodel' => 'catquiz',
    'catscalesid' => $scaleid,
    'cateststrategyid' => $strategy,
    'catquiz_minquestions' => $numitems,
    'catquiz_maxquestions' => $numitems,
    'catquiz_minquestionspersubscale' => 1,
    'catquiz_standarderror_min' => 0.0,
    'catquiz_standarderror_max' => 1000.0,
]);

$password = 'Load!' . substr(sha1((string) random_int(0, PHP_INT_MAX)), 0, 10);
$usernames = [];
for ($i = 1; $i <= $numusers; $i++) {
    $user = $generator->create_user(['username' => $userprefix . $i, 'password' => $password]);
    $generator->enrol_user($user->id, $course->id, 'student');
    $usernames[] = $user->username;
}
set_config('attempttrace', 1, 'local_catquiz');
purge_all_caches();

$cm = get_coursemodule_from_instance('adaptivequiz', $quiz->id, $course->id, false, MUST_EXIST);
printf("export LOAD_BASE_URL='%s'\n", $CFG->wwwroot);
printf("export LOAD_CMID='%d'\n", $cm->id);
printf("export LOAD_USERS='%s'\n", implode(',', $usernames));
printf("export LOAD_PASSWORD='%s'\n", $password);
printf("export LOAD_ITEMS='%d'\n", $numitems);
printf("export LOAD_IDNUMBER='%s'\n", $idnumber);
