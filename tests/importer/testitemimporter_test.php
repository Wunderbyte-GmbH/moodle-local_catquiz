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
 * Tests the testitemimporter functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use coding_exception;
use context_course;
use context_module;
use core_question\local\bank\question_edit_contexts;
use Exception;
use local_catquiz\importer\testitemimporter;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use moodle_exception;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;
use qformat_xml;
use question_engine;
use question_usage_by_activity;
use stdClass;
use UnexpectedValueException;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/local/catquiz/lib.php');
require_once($CFG->dirroot . '/local/catquiz/tests/lib.php');
require_once($CFG->dirroot . '/local/catquiz/tests/lib.php');

/**
 * Tests the testitemimporter functionality.
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_catquiz\importer\testitemimporter
 *
 */
final class testitemimporter_test extends advanced_testcase {

    /**
     * @var An instance of an adaptive quiz
     */
    private \stdClass $adaptivequiz;

    /**
     * @var stdClass $course Stores the course we create for this test.
     */
    private stdClass $course;

    public function setUp(): void {
        parent::setUp();
        $this->import('simulation.xml', 'simulation.csv');
    }

    /**
     * Check if importing a comma separated file works.
     *
     * @return void
     */
    public function test_import_overrides(): void {
        $importer = new testitemimporter();
        $content = file_get_contents(__DIR__ . '/../fixtures/simulation_comma.csv');
        $result = $importer->execute_testitems_csv_import(
                (object) [
                    'delimiter_name' => 'comma',
                    'dateparseformat' => null,
                    'encoding' => null,
                ],
                $content
            );
        // Check that there are no errors.
        $this->assertEquals(0, count($result['errors']), implode(', ', $result['errors']));
    }

    /**
     * Import both questions and item params from fixture files.
     *
     * @param string $questionsfile The path to an XML questions file.
     * @param string $itemparamsfile The path to a CSV itemparams file.
     *
     * @return void
     */
    private function import(string $questionsfile, string $itemparamsfile): void {
        global $DB;
        $this->resetAfterTest(true);

        // Import questions.
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();

        $this->adaptivequiz = $this->getDataGenerator()
            ->get_plugin_generator('mod_adaptivequiz')
            ->create_instance([
                'highestlevel' => 10,
                'lowestlevel' => 1,
                'standarderror' => 14,
                'course' => $this->course->id,
            ]);
        $qformat = $this->create_qformat($questionsfile, $this->course);
        $imported = $qformat->importprocess();
        $this->assertTrue($imported);

        $qformat = $this->create_qformat('pilotquestions.xml', $this->course);
        $imported = $qformat->importprocess();
        $this->assertTrue($imported);

        $this->import_itemparams($itemparamsfile);
    }

    /**
     * Import the item params from the given CSV file
     *
     * @param string $filename The name of the itemparams file.
     *
     * @return array
     */
    private function import_itemparams($filename): array {
        global $DB;
        $questions = $DB->get_records('question');
        if (! $questions) {
            exit('No questions were imported');
        }
        $importer = new testitemimporter();
        $content = file_get_contents(__DIR__ . '/../fixtures/' . $filename);
        $result = $importer->execute_testitems_csv_import(
                (object) [
                    'delimiter_name' => 'semicolon',
                    'encoding' => null,
                    'dateparseformat' => null,
                ],
                $content
            );
        return $result;
    }

    /**
     * Create a new qformat object so that we can import questions.
     *
     * NOTE: copied from qformat_xml_import_export_test.php
     *
     * Create object qformat_xml for test.
     * @param string $filename with name for testing file.
     * @param \stdClass $course
     * @return \qformat_xml XML question format object.
     */
    private function create_qformat($filename, $course) {
        $qformat = new qformat_xml();
        $qformat->setContexts((new question_edit_contexts(context_course::instance($course->id)))->all());
        $qformat->setCourse($course);
        $qformat->setFilename(__DIR__ . '/../fixtures/' . $filename);
        $qformat->setRealfilename($filename);
        $qformat->setMatchgrades('error');
        $qformat->setCatfromfile(1);
        $qformat->setContextfromfile(1);
        $qformat->setStoponerror(1);
        $qformat->setCattofile(1);
        $qformat->setContexttofile(1);
        $qformat->set_display_progress(false);

        return $qformat;
    }
}
