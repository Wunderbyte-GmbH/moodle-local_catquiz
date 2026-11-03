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

/**
 * No method takes a generic $attemptid that could mean either attempt (issues #97, #107).
 *
 * adaptivequiz_attempt.id and local_catquiz_attempts.id are separate sequences that often run in
 * step in a test database, so mixing them up goes unnoticed. Methods name the one they take:
 * $adaptiveattemptid or $catattemptid. This scans the plugin's classes and fails on a new generic
 * parameter; doc/id-namespaces.md lists the two places where the name is deliberately generic.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class attempt_id_naming_test extends advanced_testcase {
    /** @var string[] Methods allowed a generic name, as "file::method" - see doc/id-namespaces.md. */
    private const ALLOWED = [
        'local/model/model_responses.php::get_last_response',
        'local/model/model_responses.php::set',
        'event/alise_debug_event.php::log',
    ];

    /**
     * Every method with an attempt id parameter names its namespace.
     */
    public function test_no_generic_attempt_id_parameter(): void {
        global $CFG;

        $root = $CFG->dirroot . '/local/catquiz/classes/';
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            if (!preg_match_all('/function\s+(\w+)\s*\(([^)]*)\)/', $source, $matches, PREG_SET_ORDER)) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root));
            foreach ($matches as [, $method, $parameters]) {
                if (preg_match('/\$attemptid\b/', $parameters) && !in_array("$relative::$method", self::ALLOWED, true)) {
                    $found[] = "$relative::$method";
                }
            }
        }

        $this->assertSame([], $found, 'Name the attempt id: $adaptiveattemptid or $catattemptid.');
    }
}
