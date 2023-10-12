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
 * Class local_catquiz_generator for generation of dummy data
 *
 * @package local_catquiz
 * @category test
 * @copyright 2023 Andrii Semenets
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_catquiz_generator extends testing_module_generator {

    /**
     * Create content in the given user's private files.
     *
     * @param array $data
     * @return void
     */
    protected function create_question_bank_questions(array $data) {
        global $CFG;

        $userid = $data['userid'];
        $fs = get_file_storage();
        $filepath = "{$CFG->dirroot}/{$data['filepath']}";

        if (!file_exists($filepath)) {
            throw new coding_exception("File '{$filepath}' does not exist");
        }

        $filerecord = [
            'userid' => $userid,
            'contextid' => context_user::instance($userid)->id,
            'component' => 'user',
            'filearea' => 'private',
            'itemid' => 0,
            'filepath'  => '/',
            'filename'  => basename($filepath),
        ];
        $fs->create_file_from_pathname($filerecord, $filepath);

        // Get file.
        $file = $fs->get_file($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'],
                      $filerecord['itemid'], $filerecord['filepath'], $filerecord['filename']);
        // Read contents.
        if ($file) {
            $xml = $file->get_content();
            $xmldata = xmlize($xml);

            $importer = new \qformat_xml();
            $q = $importer->try_importing_using_qtypes(
                    $xmldata['question'], null, null, '');
        } else {
            throw new coding_exception("Cannot parse XML from file '{$filepath}'");
        }
    }
}
