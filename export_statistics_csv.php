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
 * Attempts CSV export
 *
 * Based on https://gist.github.com/kralo/293dbc07b9b318eabe43.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @author     David Szkiba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\output\catquizstatistics;

require_once('../../config.php');

$scaleid = required_param('scaleid', PARAM_INT);
$cid = required_param('cid', PARAM_INT);

$courseid = optional_param('courseid', 0, PARAM_INT) ?: null;
$testid = optional_param('testid', 0, PARAM_INT) ?: null;
$starttime = optional_param('starttime', 0, PARAM_INT) ?: null;
$endtime = optional_param('endtime', 0, PARAM_INT) ?: null;

require_login();

$PAGE->set_context(context_course::instance($cid));

if (!has_capability('local/catquiz:view_users_feedback', context_course::instance($cid)) &&
    !has_capability('local/catquiz:canmanage', context_system::instance())) {

    die(get_string('error:permissionforcsvdownload', 'local_catquiz', 'local/catquiz:view_users_feedback'));
}

require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$catquizstatistics = new catquizstatistics($courseid, $testid, $scaleid, $endtime, $starttime);

$filename = date("Ymd Hi-")."export_attempts_scale_$scaleid";
if ($courseid != 0) {
    $filename .= "_course_$courseid";
}
if ($testid != 0) {
    $filename .= "_test_$testid";
}
if ($starttime && $starttime != 0) {
    $filename .= "_from_".date("Ymd Hi", $starttime);
}
if ($endtime && $endtime != 0) {
    $filename .= "_till_".date("Ymd Hi", $endtime);
}

$downloadfilename = clean_filename ( $filename );
$csvexport = new csv_export_writer ( 'semicolon' );
$csvexport->set_filename ( $downloadfilename );

$exporttitle = [
// phpcs:disable
// -- [x] UserID,
// -- [x] UserName,
// -- [x] UserE-Mail,
// -- [x] Startzeit,
// -- [x] Endzeit,
// -- [X] Strategie,
// -- [X] Status Testresult (Maximalanzahl Fragen erreicht, keine Fragen, SE unterschritten, Zeit überschritten etc.),
// -- [x] Anz. Fragen gesamt (auch nicht-gewertete und Pilotierungsfragen),
// -- [/] Ergebnis-Range,
// -- [/] PP global,
// -- [ ] SE global,
// -- [/] N global,
// -- [/] frac global,
// -- [/] Ergebnis-Skala (je Strategie),
// -- [/] PP Ergebnisskala,
// -- [/] SE Ergebnisskala,
// -- [/] N Ergebnisskala,
// -- [/] frac Ergebnisskala,
// -- [?] JSON aller detektierten Ergebnisse
    get_string('csvexportheader:userid', 'local_catquiz'),
    get_string('csvexportheader:username', 'local_catquiz'),
    get_string('csvexportheader:useremail', 'local_catquiz'),
    get_string('csvexportheader:testid', 'local_catquiz'),
    get_string('csvexportheader:attemptid', 'local_catquiz'),
    get_string('csvexportheader:attemptstart', 'local_catquiz'),
    get_string('csvexportheader:attemptend', 'local_catquiz'),
    get_string('csvexportheader:attemptduration', 'local_catquiz'),
    get_string('csvexportheader:attemptstatus', 'local_catquiz'),
    get_string('csvexportheader:teststrategy', 'local_catquiz'),
    get_string('csvexportheader:attemptquestionno', 'local_catquiz'),
    // 'Ergebnis-Range',
    get_string('csvexportheader:resultscaleglobal', 'local_catquiz'),
    get_string('csvexportheader:resultppglobal', 'local_catquiz'),
    get_string('csvexportheader:resultseglobal', 'local_catquiz'),
    // 'N global',
    # 'frac global',
    get_string('csvexportheader:resultscaledetail', 'local_catquiz'),
    get_string('csvexportheader:resultppdetail', 'local_catquiz'),
    get_string('csvexportheader:resultsedetail', 'local_catquiz'),
    // 'N Ergebnisskala',
    // 'frac Ergebnisskala',
// phpcs:enable
];

$csvexport->add_data($exporttitle);

$decsep = get_string('decsep', 'langconfig');

foreach ($catquizstatistics->get_export_data() as $row) {

    // phpcs:disable
    $csvexport->add_data(
        [
            $row->userid,
            $row->username,
            $row->email,
            $row->testid,
            $row->attemptid,
            $row->starttime,
            $row->endtime,
            $row->timediff,
            $row->status,
            $row->teststrategy,
            $row->number_of_testitems_used,
            $row->globalname,
            str_replace('.', $decsep, (string) $row->globalpp),
            str_replace('.', $decsep, (string) round((float) $row->globalse, 2)),
            /*
            $row->globaln,
            $row->globalf,
            */
            $row->primaryname,
            str_replace('.', $decsep, (string) $row->primarypp),
            str_replace('.', $decsep, (string) round((float) $row->primaryse, 2)),
            /*
            $row->primaryn,
            $row->primaryf,

            $row->allresults,
            */
        ]
    );
    // phpcs:enable
}

$csvexport->download_file ();
