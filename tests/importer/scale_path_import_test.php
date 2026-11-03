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

namespace local_catquiz\importer;

use advanced_testcase;
use context_course;
use context_module;
use core_question\local\bank\question_edit_contexts;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/editlib.php');

/**
 * The CSV import builds the scale tree from parentscalenames exactly (issue #76).
 *
 * The path is resolved from the root: the same name below two parents gives two scales, a repeated import
 * creates nothing new, "0" names a root scale, an ambiguous path is an error of its row.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\model\model_item_param_list
 * @covers \local_catquiz\importer\testitemimporter
 */
final class scale_path_import_test extends advanced_testcase {
    /** @var string Head of the CSV. */
    private const HEAD = "componentid;status;qtype;model;difficulty;discrimination;guessing;label;"
        . "catscalename;parentscalenames;componentname\n";

    /**
     * The simulation questions, imported into a course.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qformat = new \qformat_xml();
        $qformat->setContexts((new question_edit_contexts(context_course::instance($course->id)))->all());
        $qformat->setCourse($course);
        if (class_exists('\core_question\local\bank\question_bank_helper')) {
            $qbank = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type($course, true);
            $category = question_get_default_category(context_module::instance($qbank->id)->id, true);
        } else {
            $category = question_get_default_category(context_course::instance($course->id)->id, true);
        }
        if ($category) {
            $qformat->setCategory($category);
        }
        $qformat->setFilename(__DIR__ . '/../fixtures/simulation.xml');
        $qformat->setRealfilename('simulation.xml');
        $qformat->setMatchgrades('error');
        $qformat->setCatfromfile(1);
        $qformat->setContextfromfile(1);
        $qformat->setStoponerror(1);
        $qformat->setCattofile(1);
        $qformat->setContexttofile(1);
        $qformat->set_display_progress(false);
        $this->assertTrue($qformat->importprocess());
    }

    /**
     * Imports rows: [label, scale name, parent scale names].
     *
     * @param array $rows
     */
    private function import(array $rows): void {
        $csv = self::HEAD;
        foreach ($rows as [$label, $name, $parents]) {
            $csv .= "0;4;Multiple-Choice;raschbirnbaum;0.5;1.2;0.0000;$label;$name;$parents;question\n";
        }
        testitemimporter::execute_testitems_csv_import(
            (object) ['delimiter_name' => 'semicolon', 'encoding' => null, 'dateparseformat' => null],
            $csv
        );
    }

    /**
     * The names from the root to a scale.
     *
     * @param int $scaleid
     * @return string[]
     */
    private function path_of(int $scaleid): array {
        global $DB;
        $names = [];
        while ($scaleid) {
            $scale = $DB->get_record('local_catquiz_catscales', ['id' => $scaleid], '*', MUST_EXIST);
            array_unshift($names, $scale->name);
            $scaleid = (int) $scale->parentid;
        }
        return $names;
    }

    /**
     * The scale an item of a label was assigned to.
     *
     * @param string $label
     * @return int[]
     */
    private function scales_of(string $label): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT lci.catscaleid
               FROM {local_catquiz_items} lci
               JOIN {question_versions} qv ON qv.questionid = lci.componentid
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              WHERE qbe.idnumber = :label",
            ['label' => $label]
        ));
    }

    /**
     * A|B with C gives exactly A -> B -> C, and the item lands in C.
     */
    public function test_path_is_built_exactly(): void {
        $this->import([['SIMA01-00', 'SimA01', 'Simulation|SimA']]);
        $scales = $this->scales_of('SIMA01-00');
        $this->assertCount(1, $scales);
        $this->assertSame(['Simulation', 'SimA', 'SimA01'], $this->path_of($scales[0]));
    }

    /**
     * The same name below two parents gives two scales.
     */
    public function test_same_name_below_two_parents(): void {
        $this->import([
            ['SIMA01-00', 'Shared', 'Simulation|SimA'],
            ['SIMA01-01', 'Shared', 'Simulation|SimB'],
        ]);
        $first = $this->scales_of('SIMA01-00')[0];
        $second = $this->scales_of('SIMA01-01')[0];
        $this->assertNotSame($first, $second);
        $this->assertSame(['Simulation', 'SimA', 'Shared'], $this->path_of($first));
        $this->assertSame(['Simulation', 'SimB', 'Shared'], $this->path_of($second));
    }

    /**
     * A repeated import - with spaces around the separators, too - and a root named by "0" create nothing new.
     */
    public function test_repeated_import_creates_nothing_new(): void {
        global $DB;
        $rows = [
            ['SIMA01-00', 'SimA01', 'Simulation|SimA'],
            ['SIMA01-01', 'Rootscale', '0'],
        ];
        $this->import($rows);
        $count = $DB->count_records('local_catquiz_catscales');
        $this->import($rows);
        $this->import([['SIMA01-00', 'SimA01', ' Simulation | SimA ']]);
        $this->assertSame($count, $DB->count_records('local_catquiz_catscales'));
        $this->assertSame(1, $DB->count_records('local_catquiz_catscales', ['name' => 'Rootscale', 'parentid' => 0]));
    }

    /**
     * A root name below a subscale of the same name is not mistaken for the root.
     */
    public function test_root_segment_is_a_root(): void {
        $this->import([['SIMA01-00', 'Simulation', 'Other|Deep']]);
        $this->import([['SIMA01-01', 'SimA01', 'Simulation|SimA']]);
        $scale = $this->scales_of('SIMA01-01')[0];
        $this->assertSame(['Simulation', 'SimA', 'SimA01'], $this->path_of($scale));
    }

    /**
     * Two scales of the same name below the same parent make the path ambiguous: nothing is created for the row.
     */
    public function test_ambiguous_path_is_refused(): void {
        global $DB;
        foreach ([1, 2] as $i) {
            $DB->insert_record('local_catquiz_catscales', (object) [
                'name' => 'Twice', 'parentid' => 0, 'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $this->import([['SIMA01-00', 'Child', 'Twice']]);
        $this->assertFalse($DB->record_exists('local_catquiz_catscales', ['name' => 'Child']));
        $this->assertSame([], $this->scales_of('SIMA01-00'));
    }
}
