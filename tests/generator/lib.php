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

use local_catquiz\importer\testitemimporter;
use local_catquiz\catscale;
use local_catquiz\testenvironment;
use local_catquiz\catquiz_handler;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Class local_catquiz_generator for generation of dummy data
 *
 * @package local_catquiz
 * @category test
 * @copyright 2023 Andrii Semenets
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_catquiz_generator extends testing_module_generator {

    /**
     * Create course questions by importing from Moodle XML file.
     *
     * @param array $data
     * @return void
     */
    public function create_catquiz_questions(array $data) {
        global $CFG;

        $filepath = "{$CFG->dirroot}/{$data['filepath']}";

        if (!file_exists($filepath)) {
            throw new coding_exception("File '{$filepath}' does not exist");
        }

        $course = get_course($data['courseid']);
        $context = context_course::instance($course->id);
        $category = question_get_top_category($context->id, true);

        // Load data into class.
        $qformat = new qformat_xml();
        $qformat->setCategory($category);
        $qformat->setContexts([$context]);
        $qformat->setCourse($course);
        $qformat->setFilename($filepath);
        $qformat->setRealfilename($filepath);
        $qformat->setCatfromfile(true);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);
        // Do anything before that we need to.
        if (!$qformat->importpreprocess()) {
            throw new moodle_exception('Cannot import {$filepath} (preprocessing)', '', '');
        }
        // Process the uploaded file.
        if (!$qformat->importprocess()) {
            throw new moodle_exception('Cannot import {$filepath} (processing)', '', '');
        }
        // In case anything needs to be done after.
        if (!$qformat->importpostprocess()) {
            throw new moodle_exception('Cannot import {$filepath} (postprocessing)', '', '');
        }
    }

    /**
     * Create catscale structure by importing from CSV file.
     *
     * @param array $data
     * @return void
     */
    public function create_catquiz_importedcatscales(array $data) {
        $importer = new testitemimporter();
        $content = file_get_contents(__DIR__ . '/../fixtures/' . $data['filename']);
        $importer->execute_testitems_csv_import(
            (object) [
                'delimiter_name' => 'semicolon',
                'encoding' => null,
                'dateparseformat' => null,
            ],
            $content
        );
    }

    /**
     * Create catquiz_testsettings structure by importing from JSON file.
     *
     * @param array $data
     * @return void
     */
    public function create_catquiz_testsettings(array $data) {
        global $DB;

        $adaptivequiz = (object)(array)$data;

        // Force catmodel to adaptivequiz 1st.
        $DB->set_field('adaptivequiz', 'catmodel', $adaptivequiz->catmodel, ['id' => $adaptivequiz->adaptivecatquizid]);

        // TODO: create correct json somehow. One from phpunittests does not mutch dynamic IDs in behat.
        $json = file_get_contents(__DIR__ . '/../fixtures/testenvironmentdummy.json');
        $jsondata = json_decode($json);

        $catscale = $DB->get_record('local_catquiz_catscales', ['id' => $adaptivequiz->catscalesid]);
        $jsondata->catquiz_catscales = $adaptivequiz->catscalesid;
        $jsondata->catscaleid = $adaptivequiz->catscalesid;

        // Include all subscales in the test.
        foreach ([$catscale->id, ...catscale::get_subscale_ids($catscale->id)] as $scaleid) {
            $propertyname = sprintf('catquiz_subscalecheckbox_%d', $scaleid);
            $jsondata->$propertyname = true;
        }

        $jsondata->courseid = $adaptivequiz->courseid;
        $jsondata->componentid = $adaptivequiz->adaptivecatquizid;
        $jsondata->component = 'mod_adaptivequiz';
        $jsondata->catquiz_selectteststrategy = $adaptivequiz->cateststrategyid;
        $jsondata->catquiz_selectfirstquestion = $adaptivequiz->catquiz_selectfirstquestion ?? null;
        $jsondata->maxquestionsgroup->catquiz_maxquestions = $adaptivequiz->catquiz_maxquestions ?? null;
        $jsondata->maxquestionsgroup->catquiz_minquestions = $adaptivequiz->catquiz_minquestions ?? null;
        $jsondata->maxquestionsscalegroup->catquiz_minquestionspersubscale = $adaptivequiz->catquiz_minquestionspersubscale ?? null;
        $jsondata->maxquestionsscalegroup->catquiz_maxquestionspersubscale = $adaptivequiz->catquiz_maxquestionspersubscale ?? null;
        $jsondata->catquiz_standarderrorgroup->catquiz_standarderror_min = $adaptivequiz->catquiz_standarderror_min ?? null;
        $jsondata->catquiz_standarderrorgroup->catquiz_standarderror_max = $adaptivequiz->catquiz_standarderror_max ?? null;
        $jsondata->catquiz_includetimelimit = $adaptivequiz->catquiz_includetimelimit ?? null;
        $jsondata->numberoffeedbackoptionsselect = $adaptivequiz->numberoffeedbackoptions ?? null;
        $jsondata->json = json_encode($jsondata);

        // Setup testenv finally.
        $testenvironment = new testenvironment($jsondata);
        $testenvironment->save_or_update();
        catquiz_handler::prepare_attempt_caches();
    }
}
