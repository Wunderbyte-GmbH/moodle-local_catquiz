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

namespace local_catquiz\local;

use advanced_testcase;
use local_catquiz\data\dataapi;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\teststrategy\feedback_helper;
use local_catquiz\testenvironment;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The outer limits of the feedback ranges follow the global scale (issue #86).
 *
 * A global scale -3..3 with a test whose two feedback ranges are -3..0.5 and 0.5..3, for the scale and for a
 * subscale. The scale becomes -4..4.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\feedback_limits
 * @covers \local_catquiz\testenvironment::return_settings
 * @covers \local_catquiz\testenvironment::apply_jsonsaved_values
 * @covers \local_catquiz\data\dataapi::update_catscale
 */
final class feedback_limits_test extends advanced_testcase {
    /** @var int */
    private int $root;

    /** @var int */
    private int $sub;

    /** @var int The test environment. */
    private int $test;

    /**
     * Ranges of a scale: [lower_1, upper_1, lower_2, upper_2].
     *
     * @param stdClass|array $settings
     * @param int $scaleid
     * @return float[]
     */
    private function ranges($settings, int $scaleid): array {
        $settings = (array) $settings;
        $values = [];
        for ($i = 1; $i <= 2; $i++) {
            foreach (['lower', 'upper'] as $side) {
                $values[] = feedback_helper::parse_range_limit($settings["feedback_scaleid_limit_{$side}_{$scaleid}_{$i}"]);
            }
        }
        return $values;
    }

    /**
     * Settings as the form stored them: strings, one of them with a decimal comma.
     *
     * @return stdClass
     */
    private function settings(): stdClass {
        $colours = array_keys(feedbackclass::get_array_of_colors(2));
        $settings = (object) ['catquiz_catscales' => $this->root, 'numberoffeedbackoptionsselect' => '2'];
        foreach ([$this->root, $this->sub] as $scaleid) {
            $settings->{"feedback_scaleid_limit_lower_{$scaleid}_1"} = '-3';
            $settings->{"feedback_scaleid_limit_upper_{$scaleid}_1"} = '0,5';
            $settings->{"feedback_scaleid_limit_lower_{$scaleid}_2"} = '0.5';
            $settings->{"feedback_scaleid_limit_upper_{$scaleid}_2"} = '3';
            $settings->{"wb_colourpicker_{$scaleid}_1"} = $colours[0];
            $settings->{"wb_colourpicker_{$scaleid}_2"} = $colours[1];
        }
        return $settings;
    }

    /**
     * A global scale -3..3, a subscale, a test environment with feedback ranges for both.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->root = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Global', 'parentid' => 0, 'minscalevalue' => -3, 'maxscalevalue' => 3,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->sub = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'name' => 'Sub', 'parentid' => $this->root, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->test = (int) $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => 987654, 'component' => 'mod_adaptivequiz', 'catscaleid' => $this->root, 'courseid' => 0,
            'name' => 'T', 'json' => json_encode($this->settings()), 'status' => 2,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Widening the scale: stored, read, shown in the form and used at runtime with the new outer limits;
     * the limit between the ranges stays; the settings an attempt started with stay as they were.
     */
    public function test_the_scale_widens(): void {
        global $DB;
        $snapshot = json_encode($this->settings());
        $progress = (int) $DB->insert_record('local_catquiz_progress', (object) [
            'attemptid' => null, 'userid' => 2, 'component' => 'mod_adaptivequiz', 'json' => '{}',
            'quizsettings' => $snapshot, 'timemodified' => time(),
        ]);
        $helper = new feedback_helper();

        dataapi::update_catscale((object) ['id' => $this->root, 'parentid' => 0, 'minscalevalue' => -4, 'maxscalevalue' => 4]);

        $stored = json_decode($DB->get_field('local_catquiz_tests', 'json', ['id' => $this->test]));
        $this->assertSame([-4.0, 0.5, 0.5, 4.0], $this->ranges($stored, $this->root), 'Stored: the limits of the scale.');
        $this->assertSame([-4.0, 0.5, 0.5, 4.0], $this->ranges($stored, $this->sub), 'Also for the subscale.');

        $read = (new testenvironment((object) ['id' => $this->test]))->return_settings();
        $this->assertSame([-4.0, 0.5, 0.5, 4.0], $this->ranges($read, $this->root), 'Read for a new attempt.');

        $form = [];
        (new testenvironment((object) ['id' => $this->test]))->apply_jsonsaved_values($form);
        $this->assertSame([-4.0, 0.5, 0.5, 4.0], $this->ranges($form, $this->root), 'The form opened again.');

        $colour = $helper->get_color_for_personability((array) $read, 3.5, $this->root);
        $this->assertNotSame(LOCAL_CATQUIZ_DEFAULT_GREY, $colour, 'An ability inside the scale has a range.');
        $this->assertSame(
            LOCAL_CATQUIZ_DEFAULT_GREY,
            $helper->get_color_for_personability((array) json_decode($snapshot), 3.5, $this->root),
            'With the old ranges it had none - the defect.'
        );
        $this->assertSame(
            $snapshot,
            $DB->get_field('local_catquiz_progress', 'quizsettings', ['id' => $progress]),
            'The settings an attempt started with are not changed.'
        );
    }

    /**
     * Shrinking the scale moves a limit between the ranges into it; the ranges stay in order.
     */
    public function test_the_scale_shrinks(): void {
        $settings = $this->settings();
        $this->assertTrue(feedback_limits::align($settings, ['minscalevalue' => -2, 'maxscalevalue' => 0.25]));
        $this->assertSame([-2.0, 0.25, 0.25, 0.25], $this->ranges($settings, $this->root));
    }

    /**
     * Limits of 0 and negative limits are limits; no range, no options or a scale without a range leave the
     * settings alone; aligned settings stay as they are.
     */
    public function test_bounds_and_nothing_to_align(): void {
        $settings = $this->settings();
        $this->assertTrue(feedback_limits::align($settings, ['minscalevalue' => -6, 'maxscalevalue' => 0]));
        $this->assertSame([-6.0, 0.0, 0.0, 0.0], $this->ranges($settings, $this->root), 'Upper limit 0.');
        $this->assertFalse(feedback_limits::align($settings, ['minscalevalue' => -6, 'maxscalevalue' => 0]), 'Idempotent.');

        $settings = $this->settings();
        $this->assertFalse(feedback_limits::align($settings, ['minscalevalue' => 1, 'maxscalevalue' => 1]), 'No range.');
        $this->assertFalse(feedback_limits::align((object) ['catquiz_catscales' => $this->root]), 'No options.');
        $this->assertFalse(feedback_limits::align((object) ['numberoffeedbackoptionsselect' => 2]), 'No scale.');
        $this->assertFalse(feedback_limits::align($this->settings()), 'Already the limits of the scale.');
    }

    /**
     * The upgrade step aligns stored test environments once; older JSON without ranges is left as it is.
     */
    public function test_the_upgrade_aligns_existing_tests(): void {
        global $DB;
        $DB->set_field('local_catquiz_catscales', 'minscalevalue', -5, ['id' => $this->root]);
        \cache_helper::purge_by_event('changesincatscales');
        $old = (int) $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => 0, 'component' => 'mod_adaptivequiz', 'catscaleid' => $this->root, 'courseid' => 0,
            'name' => 'Old', 'json' => json_encode(['catquiz_catscales' => $this->root]), 'status' => 2,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->assertSame(1, feedback_limits::align_all_tests());

        $stored = json_decode($DB->get_field('local_catquiz_tests', 'json', ['id' => $this->test]));
        $this->assertSame([-5.0, 0.5, 0.5, 3.0], $this->ranges($stored, $this->root));
        $this->assertSame(
            json_encode(['catquiz_catscales' => $this->root]),
            $DB->get_field('local_catquiz_tests', 'json', ['id' => $old]),
            'Older JSON without ranges.'
        );
        $this->assertSame(0, feedback_limits::align_all_tests(), 'Once.');
    }
}
