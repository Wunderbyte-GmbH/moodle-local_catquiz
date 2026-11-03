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

namespace local_catquiz\feedback;

use advanced_testcase;
use local_catquiz\data\catscale_structure;
use local_catquiz\data\dataapi;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The nested scale feedback of the test settings form is balanced HTML (issue #32).
 *
 * The form opens a collapsible per scale and closes them as the depth changes. Open and close are
 * fragments of their own, kept out of the template lint - so the balance is checked here, on what the
 * form actually puts together. A list that ended in a subscale left a div open per level.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\feedback\feedbackclass::instance_form_definition
 */
final class feedback_form_markup_test extends advanced_testcase {
    /**
     * A scale.
     *
     * @param string $name
     * @param int $parentid
     * @return int
     */
    private function scale(string $name, int $parentid = 0): int {
        return dataapi::create_catscale(new catscale_structure([
            'name' => $name, 'parentid' => $parentid,
            'timecreated' => time(), 'timemodified' => time(),
        ]));
    }

    /**
     * The HTML the feedback part of the form is made of, for a scale tree.
     *
     * @param int $rootid The scale of the test.
     * @param int[] $selected The subscales selected for it.
     * @return string
     */
    private function form_html(int $rootid, array $selected): string {
        $_POST = ['catquiz_catscales' => $rootid];
        foreach ($selected as $scaleid) {
            $_POST['catquiz_subscalecheckbox_' . $scaleid] = 1;
        }
        $mform = new \MoodleQuickForm('feedbackmarkup', 'post', '');
        $elements = [];
        feedbackclass::instance_form_definition($mform, $elements, null);
        $_POST = [];
        $html = '';
        foreach ($mform->_elements as $element) {
            if ($element->getType() === 'html') {
                $html .= $element->toHtml();
            }
        }
        return $html;
    }

    /**
     * Ending in a subscale, in a sibling, with a level left out: every opened div is closed, and none more.
     */
    public function test_nested_feedback_is_balanced(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $root = $this->scale('Markup root');
        $a = $this->scale('Markup A', $root);
        $a1 = $this->scale('Markup A1', $a);
        $b = $this->scale('Markup B', $root);
        $cases = [
            'ends two levels down' => [$root, [$a, $a1]],
            'back up a level, then a sibling' => [$root, [$a, $a1, $b]],
            'a level left out' => [$root, [$a1, $b]],
            'only the root' => [$root, []],
        ];
        foreach ($cases as $case => [$testscale, $selected]) {
            $html = $this->form_html($testscale, $selected);
            foreach ($selected as $scaleid) {
                $this->assertStringContainsString('catquiz_feedback_collapse_' . $scaleid . '"', $html, $case);
            }
            $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'), $case);
        }
    }

    /**
     * A scale name with a quote does not break the markup of its range.
     */
    public function test_scale_name_is_escaped_in_the_markup(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $root = $this->scale('Say "hi"');
        $html = $this->form_html($root, []);

        $this->assertStringNotContainsString('data-name="feedback_scale_Say "hi"', $html);
        $this->assertStringContainsString('data-name="feedback_scale_Say &quot;hi&quot;_range_1"', $html);
    }
}
