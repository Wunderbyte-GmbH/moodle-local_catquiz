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

namespace local_catquiz\output;

use advanced_testcase;
use local_catquiz\catscale;
use local_catquiz\data\catscale_structure;
use local_catquiz\data\dataapi;
use local_catquiz\event\feedbacktab_clicked;

/**
 * Links of the plugin below a wwwroot with a path, and entry scripts independent of the working
 * directory (issue #32).
 *
 * A site in a subdirectory (https://example.org/sub/moodle) is where a root-relative "/local/..."
 * or a link glued together from a path breaks: the browser resolves it against the host and lands
 * outside Moodle. A moodle_url carries the whole wwwroot. Moodle 5.1 moved the code below public/,
 * so a config.php found relative to the working directory of the web server is a matter of luck.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class plugin_urls_test extends advanced_testcase {
    /** @var string A wwwroot with a path. */
    private const WWWROOT = 'https://example.org/sub/moodle';

    /**
     * Every href of a piece of HTML.
     *
     * @param string $html
     * @return string[]
     */
    private static function hrefs(string $html): array {
        preg_match_all('/\shref="([^"#][^"]*)"/', $html, $matches);
        return array_map('html_entity_decode', $matches[1]);
    }

    /**
     * Asserts that every address starts with the wwwroot of the site and its path.
     *
     * @param string[] $urls
     * @param string $what
     */
    private function assert_below_wwwroot(array $urls, string $what): void {
        $this->assertNotEmpty($urls, $what);
        foreach ($urls as $url) {
            $this->assertStringStartsWith(self::WWWROOT . '/', $url, $what);
        }
    }

    /**
     * Navigation bar, break page, event and export links carry the path of the wwwroot.
     */
    public function test_links_carry_the_path_of_the_wwwroot(): void {
        global $CFG, $DB, $OUTPUT, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        $CFG->wwwroot = self::WWWROOT;

        // The link in the navigation bar.
        $navbar = local_catquiz_render_navbar_output($PAGE->get_renderer('core'));
        $this->assert_below_wwwroot(self::hrefs($navbar), 'navbar');
        $this->assertContains(self::WWWROOT . '/local/catquiz/manage_catscales.php', self::hrefs($navbar));

        // The break page: its address and the rendered link back to the test.
        $breakinfo = (new breakinfo(7, time() + 60))->export_for_template($OUTPUT);
        $this->assertSame(self::WWWROOT . '/mod/adaptivequiz/view.php?id=7', $breakinfo['viewurl']);
        $this->assert_below_wwwroot(
            self::hrefs($OUTPUT->render_from_template('local_catquiz/breakinfo', $breakinfo)),
            'breakinfo'
        );

        // The link of the event a teacher follows from the logs.
        $event = feedbacktab_clicked::create([
            'context' => \context_system::instance(),
            'other' => ['attemptid' => 5, 'feedback' => 'x', 'feedback_translated' => 'x', 'userid' => 2, 'role' => 'x'],
        ]);
        $this->assert_below_wwwroot([$event->get_url()->out(false)], 'event url');
        $this->assert_below_wwwroot(self::hrefs($event->get_description()), 'event description');

        // Links to a scale and to an item, as tables and messages put them.
        $scaleid = dataapi::create_catscale(new catscale_structure([
            'name' => 'URL scale', 'parentid' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]));
        $this->assert_below_wwwroot(self::hrefs(catscale::get_link_to_catscale($scaleid)), 'scale link');
        $this->assert_below_wwwroot(
            self::hrefs(catscale::get_link_to_testitem(3, $scaleid, 1, 'question')),
            'item link'
        );

        // The CSV export of the statistics shortcode, for a scale with a test.
        $DB->insert_record('local_catquiz_tests', (object) [
            'componentid' => 0, 'component' => 'mod_adaptivequiz', 'catscaleid' => $scaleid, 'courseid' => 0,
            'name' => 'URL test', 'json' => json_encode(['name' => 'URL test']), 'status' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $statistics = new catquizstatistics(null, null, $scaleid);
        $export = self::hrefs($statistics->render_export_button());
        $this->assert_below_wwwroot($export, 'statistics export');
        $this->assertStringStartsWith(self::WWWROOT . '/local/catquiz/export_statistics_csv.php?', $export[0]);
    }

    /**
     * The entry scripts find config.php relative to themselves, and say where they are (issue #32).
     */
    public function test_entry_scripts_load_config_relative_to_themselves(): void {
        global $CFG;

        $root = $CFG->dirroot . '/local/catquiz';
        $scripts = array_merge(glob($root . '/*.php'), glob($root . '/downloads/*.php'), glob($root . '/cli/*.php'));
        $this->assertNotEmpty(glob($root . '/downloads/*.php'));
        $relative = [];
        foreach ($scripts as $script) {
            $source = file_get_contents($script);
            // A path relative to the working directory: "../config.php", "../../../config.php".
            if (preg_match('/\brequire(?:_once)?\s*\(?\s*([\'"])\.{1,2}\//', $source)) {
                $relative[] = substr($script, strlen($root) + 1);
            }
        }
        $this->assertSame([], $relative);

        foreach (glob($root . '/downloads/*.php') as $script) {
            $source = file_get_contents($script);
            $name = basename($script);
            $this->assertStringContainsString("require_once(__DIR__ . '/../../../config.php');", $source, $name);
            $this->assertStringContainsString("set_url(new moodle_url('/local/catquiz/downloads/$name'))", $source, $name);
        }
    }
}
