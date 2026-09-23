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
 * CI-only workaround for a mod_adaptivequiz dependency bug.
 *
 * adaptivequiz_add_instance() and adaptivequiz_update_instance() read
 * $adaptivequiz->attemptfeedbackeditor unconditionally, but the module's test
 * generator (tests/generator/lib.php) never sets it. As a result every instance
 * created through the generator -- in both PHPUnit and Behat -- fails at instance
 * creation with an "Undefined property" error. This affects the alise_adaptivequiz
 * fork branch too (verified), so a branch swap alone does not help.
 *
 * This script injects the missing attemptfeedbackeditor default into the
 * generator's defaults array so generator-created instances can be saved. It is
 * idempotent and used only from the CI workflows; it is export-ignored from the
 * release ZIP. Remove it once the fork sets attemptfeedbackeditor in its generator.
 *
 * Usage: php patch_adaptivequiz_generator.php [search-root]
 * The search root defaults to the current working directory.
 *
 * @package   local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$searchroot = $argv[1] ?? getcwd();

// Locate the generator inside the installed moodle tree.
$generator = null;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($searchroot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY,
    RecursiveIteratorIterator::CATCH_GET_CHILD
);
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (substr($path, -strlen('/mod/adaptivequiz/tests/generator/lib.php')) === '/mod/adaptivequiz/tests/generator/lib.php') {
        $generator = $path;
        break;
    }
}

if ($generator === null) {
    fwrite(STDERR, "adaptivequiz generator not found under {$searchroot}\n");
    exit(1);
}

$source = file_get_contents($generator);

if (strpos($source, 'attemptfeedbackeditor') !== false) {
    fwrite(STDOUT, "Already patched: {$generator}\n");
    exit(0);
}

// Insert the editor default right after the attemptfeedbackformat default, matching
// the surrounding whitespace loosely so formatting differences do not break it.
$patched = preg_replace(
    "/('attemptfeedbackformat'\\s*=>\\s*FORMAT_MOODLE,)/",
    "$1\n            'attemptfeedbackeditor'  => array('text' => 'Attempt Feedback', 'format' => FORMAT_MOODLE),",
    $source,
    1,
    $count
);

if ($count !== 1) {
    fwrite(STDERR, "Could not find the attemptfeedbackformat anchor in {$generator}\n");
    exit(1);
}

file_put_contents($generator, $patched);
fwrite(STDOUT, "Patched attemptfeedbackeditor default into {$generator}\n");
exit(0);
