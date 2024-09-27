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
 * Debug_Info PDF export
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @author     Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\teststrategy\feedbackgenerator\debuginfo;

require_once('../../config.php');

$attemptid = required_param('attemptid', PARAM_INT);

require_login();

if (!has_capability('local/catquiz:canmanage', context_system::instance())) {

    die(get_string('error:permissionforcsvdownload', 'local_catquiz', 'local/catquiz:canmanage'));
}

require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->libdir . '/pdflib.php');

$filename = date("Ymd Hi-")."export_debuginfo_attempt_$attemptid.pdf";

$downloadfilename = clean_filename( $filename );
$pdfexport = new pdf();

$debuginfo = $DB->get_record('local_catquiz_attempts', ['attemptid' => $attemptid], 'json,debug_info', IGNORE_MISSING);
$pdfexport->AddPage('P', "A4");
$pdfexport->writeHTML("<h1>Debug Info, Attempt $attemptid</h1>".nl2br(var_export(json_decode($debuginfo->debug_info), true)));

$progressinfo = $DB->get_record('local_catquiz_progress', ['attemptid' => $attemptid], 'json', IGNORE_MISSING);

$pdfexport->AddPage('P', "A4");
$pdfexport->writeHTML("<h1>Progress Info, Attempt $attemptid</h1>".nl2br(var_export(json_decode($progressinfo->json), true)));

$attemptinfo = $DB->get_record('local_catquiz_attempts', ['attemptid' => $attemptid], 'json', IGNORE_MISSING);

$pdfexport->AddPage('P', "A4");
$pdfexport->writeHTML("<h1>Attempt Info, Attempt $attemptid</h1>".nl2br(var_export(json_decode($attemptinfo->json), true)));

$pdfexport->Output($downloadfilename, 'D');
