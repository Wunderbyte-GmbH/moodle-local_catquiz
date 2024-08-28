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

    // throw new \Exception(get_string('error:permissionforcsvdownload', 'local_catquiz','local/catquiz:view_users_feedback'), 404);
    die(get_string('error:permissionforcsvdownload', 'local_catquiz','local/catquiz:view_users_feedback'));
}

require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$catquizstatistics = new catquizstatistics($courseid, $testid, $scaleid, $endtime, $starttime);

$filename = "export_testresults_scale_$scaleid";
if ($courseid != 0) {
    $filename .= "_course_$courseid";
}
if ($testid != 0) {
    $filename .= "_test_$testid";
}
if ($starttime != 0) {
    $filename .= "_from_".userdate($starttime, get_string('strftimedatetime', 'core_langconfig'));
}
if ($endtime != 0) {
    $filename .= "_till_".userdate($endtime, get_string('strftimedatetime', 'core_langconfig'));
}

$filename .= "_".userdate(time(), get_string('strftimedatetime', 'core_langconfig'));

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
// -- [/] Strategie,
// -- [/] Status Testresult (Maximalanzahl Fragen erreicht, keine Fragen, SE unterschritten, Zeit überschritten etc.),
// -- [x] Anz. Fragen gesamt (auch nicht-gewertete und Pilotierungsfragen),
// -- [/] Ergebnis-Range,
// -- [/] PP global,
// -- [ ] SE global,
// -- [/] N global,
// -- [/] frac global,
// -- [/] Ergebnis-Skala (je Strategie),
// -- [/] PP Ergebnisskala,o
// -- [/] SE Ergebnisskala,
// -- [/] N Ergebnisskala,
// -- [/] frac Ergebnisskala,
// -- [?] JSON aller detektierten Ergebnisse
    'UserID',
    'UserName',
    'UserE-Mail',
    'Startzeit',
    'Endzeit',
    'Dauer',
    'Status',
    'Test-ID',
    'Strategie',
    'Anz. Fragen gesamt',
    // 'Ergebnis-Range',
    'Globalskala',
    'PP global',
    'SE global',
    // 'N global',
    # 'frac global',
    'Ergebnis-Skala (je Strategie)',
    'PP Ergebnisskala',
    'SE Ergebnisskala',
    // 'N Ergebnisskala',
    // 'frac Ergebnisskala',
// phpcs:enable
];

$csvexport->add_data($exporttitle);

foreach ($catquizstatistics->get_export_data() as $row) {

    // phpcs:disable
    $csvexport->add_data(
        [
            $row->userid,
            $row->username,
            $row->email,
            $row->starttime,
            $row->endtime,
            $row->timediff,
            $row->status,
            $row->testid,
            $row->teststrategy,
            $row->number_of_testitems_used,
            $row->globalname,
            $row->globalpp,
            $row->globalse,
            /*
            $row->globaln,
            $row->globalf,
            */
            $row->primaryname,
            $row->primarypp,
            $row->primaryse,
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
