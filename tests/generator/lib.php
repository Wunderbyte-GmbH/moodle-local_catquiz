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
 * @copyright 2024 Andrii Semenets
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
        // Moodle 5.0+ moves the question bank into a dedicated mod_qbank activity, so
        // questions live in that module context. Moodle 4.5 has no mod_qbank and
        // questions belong to the course context. Choose whichever the running
        // platform provides so the generator works on the 4.5 target and stays
        // forward-compatible with the Moodle 5.x migration. Using a non-existent
        // 'qbank' module makes get_plugin_generator() fail before any question is
        // imported (this broke every Behat scenario on 4.5).
        if (file_exists("{$CFG->dirroot}/mod/qbank/lib.php")) {
            $qbank = $this->datagenerator->create_module('qbank', ['course' => $course->id]);
            $context = context_module::instance($qbank->cmid);
        } else {
            $context = context_course::instance($course->id);
        }
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
        ob_start();
        if (!$qformat->importpreprocess()) {
            $output = ob_get_contents();
            ob_end_clean();
            throw new moodle_exception('Cannot import {$filepath} (preprocessing) Output: {$output}', 'local_catquiz', '');
        }
        // Process the uploaded file.
        if (!$qformat->importprocess()) {
            $output = ob_get_contents();
            ob_end_clean();
            throw new moodle_exception('Cannot import {$filepath} (processing) Output: {$output}', 'local_catquiz', '');
        }
        // In case anything needs to be done after.
        if (!$qformat->importpostprocess()) {
            $output = ob_get_contents();
            ob_end_clean();
            throw new moodle_exception('Cannot import {$filepath} (postprocessing) Output: {$output}', 'local_catquiz', '');
        }
        ob_end_clean();
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
        // Only override the fixture defaults for settings that were actually
        // supplied. Previously every optional setting was assigned "?? null",
        // which destroyed the fixture default (e.g. catquiz_minquestionspersubscale
        // "1" -> null -> intval() 0), making generated environments unrealistic
        // and masking the configured minimum-question behaviour.
        $optionaloverrides = [
            [&$jsondata->catquiz_selectfirstquestion, 'catquiz_selectfirstquestion'],
            [&$jsondata->maxquestionsgroup->catquiz_maxquestions, 'catquiz_maxquestions'],
            [&$jsondata->maxquestionsgroup->catquiz_minquestions, 'catquiz_minquestions'],
            [&$jsondata->maxquestionsscalegroup->catquiz_minquestionspersubscale, 'catquiz_minquestionspersubscale'],
            [&$jsondata->maxquestionsscalegroup->catquiz_maxquestionspersubscale, 'catquiz_maxquestionspersubscale'],
            [&$jsondata->catquiz_standarderrorgroup->catquiz_standarderror_min, 'catquiz_standarderror_min'],
            [&$jsondata->catquiz_standarderrorgroup->catquiz_standarderror_max, 'catquiz_standarderror_max'],
            [&$jsondata->catquiz_includetimelimit, 'catquiz_includetimelimit'],
            [&$jsondata->numberoffeedbackoptionsselect, 'numberoffeedbackoptions'],
            [&$jsondata->catquiz_showquestion, 'catquiz_showquestion'],
            [&$jsondata->catquiz_questionfeedbacksettings->catquiz_showquestionresponse,
                'catquiz_showquestionresponse'],

        ];
        foreach ($optionaloverrides as [&$target, $field]) {
            if (property_exists($adaptivequiz, $field)) {
                $target = $adaptivequiz->$field;
            }
        }
        unset($target);
        $jsondata->json = json_encode($jsondata);

        // Setup testenv finally.
        $testenvironment = new testenvironment($jsondata);
        $testenvironment->save_or_update();
        catquiz_handler::prepare_attempt_caches();
    }
}
