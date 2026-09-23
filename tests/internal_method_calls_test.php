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
 * Every method a class calls on itself has to exist.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Guards against calls to methods that do not exist.
 *
 * PHP resolves method names at run time. A call to a method that was never written
 * looks perfectly fine until the line is actually reached, and only then raises
 * "undefined method". Two such calls reached production in this plugin:
 *
 * - progress::get_step(), invented while adding the trace mode. It sits on the hot
 *   path of the ability estimation, so every running attempt broke once trace mode
 *   was switched on.
 * - learningprogress::get_color_for_personability(), which belongs to the feedback
 *   helper. Four other call sites in the plugin get it right.
 *
 * Neither was caught by a test, because no test executed that particular branch.
 * Aiming at full branch coverage would be the thorough answer and a large one; this
 * test takes the cheap half of it: it does not run the code, it checks that every
 * name the code refers to can be resolved at all.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class internal_method_calls_test extends advanced_testcase {
    /**
     * Names that are function calls rather than method calls.
     *
     * Moodle's optional_param() and friends are plain functions; written inside a
     * class they still match the pattern for a static call.
     *
     * @var string[]
     */
    private const IGNORED = [
        'optional_param',
        'required_param',
        'clean_param',
    ];

    /**
     * Every $this->x(), self::x() and static::x() resolves to an existing method.
     *
     * Inheritance is honoured: the check asks the loaded class, so methods from a
     * parent or a trait count as present.
     *
     * @return void
     */
    public function test_every_internal_call_resolves(): void {
        global $CFG;

        $this->resetAfterTest();

        $root = $CFG->dirroot . '/local/catquiz/classes';
        $this->assertDirectoryExists($root);

        $unresolved = [];
        $scanned = 0;

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if (!preg_match('/\bnamespace\s+([^;]+);/', $source, $namespacematch)) {
                continue;
            }
            if (!preg_match('/\b(?:final\s+)?(?:abstract\s+)?class\s+(\w+)/', $source, $classmatch)) {
                continue;
            }

            $classname = trim($namespacematch[1]) . '\\' . $classmatch[1];
            if (!class_exists($classname)) {
                // Not loadable here - an abstract base pulled in elsewhere, or a class
                // whose file does not follow the autoloader. Skipped rather than
                // reported: a false alarm would train people to ignore this test.
                continue;
            }

            $scanned++;
            $reflection = new ReflectionClass($classname);

            preg_match_all('/(?:\$this->|self::|static::)(\w+)\s*\(/', $source, $callmatches);
            foreach (array_unique($callmatches[1]) as $called) {
                if (in_array($called, self::IGNORED, true)) {
                    continue;
                }
                if ($reflection->hasMethod($called)) {
                    continue;
                }
                $unresolved[] = $classname . '::' . $called . '()';
            }
        }

        $this->assertGreaterThan(50, $scanned, 'The scan must actually reach the classes.');
        $this->assertSame(
            [],
            $unresolved,
            'These calls refer to methods that do not exist and fail when the line is reached.'
        );
    }
    /**
     * Static calls to other plugin classes resolve as well.
     *
     * The same class of mistake in a different shape: catquiz::something() fails at
     * run time in exactly the same way as $this->something(). Only calls to the
     * plugin's own classes are checked - the imports name them, so the target can be
     * resolved without guessing.
     *
     * @return void
     */
    public function test_static_calls_to_own_classes_resolve(): void {
        global $CFG;

        $this->resetAfterTest();

        $root = $CFG->dirroot . '/local/catquiz/classes';
        $unresolved = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            // Imports give the short name its full meaning.
            preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $source, $imports, PREG_SET_ORDER);
            $aliases = [];
            foreach ($imports as $import) {
                $parts = explode('\\', $import[1]);
                $short = $import[2] ?? end($parts);
                $aliases[$short] = $import[1];
            }

            // Class names in Moodle are lower case; a pattern that insists on an
            // initial capital would silently skip almost every call in this plugin.
            preg_match_all('/\b(\w+)::(\w+)\s*\(/', $source, $calls, PREG_SET_ORDER);
            foreach ($calls as $call) {
                [, $shortname, $method] = $call;
                if (!isset($aliases[$shortname])) {
                    continue;
                }
                $target = $aliases[$shortname];
                if (strpos($target, 'local_catquiz') !== 0 || !class_exists($target)) {
                    continue;
                }
                if (method_exists($target, $method)) {
                    continue;
                }
                $unresolved[] = $target . '::' . $method . '()';
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($unresolved)),
            'These static calls refer to methods that do not exist.'
        );
    }
}
