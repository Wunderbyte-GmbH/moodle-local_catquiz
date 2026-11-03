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

namespace local_catquiz;

use advanced_testcase;
use core_plugin_manager;

/**
 * The versions of the plugin, its subplugins and what it depends on agree (issue #31).
 *
 * Two lines: 1.2 for Moodle 4.5, 1.3 for Moodle 5.1 to 5.3. Moodle refuses an installation only for "requires"
 * and "incompatible" - "supported" informs. So the line of 4.5 is incompatible with 5.0 and later, the line of
 * 5.x requires 5.1; the seven model subplugins ask for what the plugin asks for; and the host and the adapter
 * installed are at least the versions the plugin names - the ones it is delivered and tested with.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class plugin_versions_test extends advanced_testcase {
    /**
     * The version information of a version.php.
     *
     * @param string $file
     * @return \stdClass
     */
    private function read(string $file): \stdClass {
        $plugin = new \stdClass();
        include($file);
        return $plugin;
    }

    /**
     * The model subplugins ask for the Moodle of the plugin and say which they support.
     */
    public function test_subplugins_follow_the_plugin(): void {
        global $CFG;
        $main = $this->read($CFG->dirroot . '/local/catquiz/version.php');
        $files = glob($CFG->dirroot . '/local/catquiz/catmodel/*/version.php');
        $this->assertCount(7, $files);
        foreach ($files as $file) {
            $sub = $this->read($file);
            $name = $sub->component;
            $this->assertSame($main->requires, $sub->requires, "$name requires");
            $this->assertSame($main->supported, $sub->supported ?? null, "$name supported");
            $this->assertSame($main->incompatible ?? null, $sub->incompatible ?? null, "$name incompatible");
            $this->assertSame($main->release, $sub->release ?? null, "$name release");
            $this->assertNotEmpty($sub->maturity ?? null, "$name maturity");
        }
    }

    /**
     * The line installs on its Moodle only: the other line's Moodle is refused by "requires" or "incompatible".
     */
    public function test_the_line_is_refused_on_the_other_moodle(): void {
        global $CFG;
        $info = core_plugin_manager::instance()->get_plugin_info('local_catquiz');
        [$first, $last] = $this->read($CFG->dirroot . '/local/catquiz/version.php')->supported ?? [0, 0];
        $this->assertGreaterThan(0, $first, 'supported is set.');
        $this->assertTrue($info->is_core_compatible_satisfied($first));
        $this->assertTrue($info->is_core_compatible_satisfied($last));
        if ($first >= 501) {
            // The line of Moodle 5.x: Moodle 4.5 is below "requires".
            $this->assertFalse($info->is_core_dependency_satisfied(2024100700), 'Moodle 4.5 is refused.');
            $this->assertTrue($info->is_core_dependency_satisfied(2025100600));
        } else {
            // The line of Moodle 4.5: Moodle 5.0 and later are refused.
            $this->assertFalse($info->is_core_compatible_satisfied(500), 'Moodle 5.0 is refused.');
            $this->assertFalse($info->is_core_compatible_satisfied(501), 'Moodle 5.1 is refused.');
            $this->assertTrue($info->is_core_dependency_satisfied(2024100700));
        }
    }

    /**
     * The host and the adapter installed are at least the versions the plugin depends on - and the plugin
     * depends on them at all.
     */
    public function test_the_dependencies_are_met(): void {
        $manager = core_plugin_manager::instance();
        $required = $manager->get_plugin_info('local_catquiz')->get_other_required_plugins();
        foreach (['local_wunderbyte_table', 'mod_adaptivequiz', 'adaptivequizcatmodel_catquiz'] as $component) {
            $this->assertArrayHasKey($component, $required, "local_catquiz depends on $component.");
            $installed = $manager->get_plugin_info($component);
            $this->assertNotNull($installed, "$component is installed.");
            $this->assertGreaterThanOrEqual((int) $required[$component], (int) $installed->versiondisk, $component);
        }
        $this->assertTrue($manager->get_plugin_info('local_catquiz')->is_core_dependency_satisfied($GLOBALS['CFG']->version));
    }
}
