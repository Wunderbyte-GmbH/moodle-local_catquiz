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

use local_catquiz\import\csvsettings;
use local_catquiz\import\fileparser;
use stdClass;


/**
 * Renderable class for the catscalemanagers
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer, Magdalena Holczik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testitemimporter {

    /**
     * Define settings and call fileparser.
     *
     * @param stdClass $data ajaxdata from import form
     * @param string $content
     * @return array
     *
     */
    public static function execute_testitems_csv_import(stdClass $data, string $content) {

        $definedcolumns = self::define_testitem_columns();
        $callback = self::get_callbackfunction();

        $settings = self::define_settings(
            $definedcolumns,
            $callback,
            $data->delimiter_name,
            $data->encoding,
            $data->dateparseformat,
        );

        $parser = new fileparser($settings);
        return $parser->process_csv_data($content);
    }

    /** @return array formdata for filepicker */
    public static function return_ajaxformdata() : array {
        $ajaxformdata = [
            'id' => 'lcq_csv_import_form',
            'settingscallback' => 'local_catquiz\importer\testitemimporter::execute_testitems_csv_import'
        ];
        return $ajaxformdata;
    }

    /** @return string callbackfunction */
    private static function get_callbackfunction() {
        return "local_catquiz\local\model\model_item_param_list::save_or_update_testitem_in_db";
    }

    /**
     * Configure and return settings object.
     *
     * @return stdClass
     */
    private static function define_settings(
        array $definedcolumns,
        string $callbackfunction = null,
        string $delimiter = null,
        string $encoding = null,
        string $dateformat = null
        ) {

        $settings = new csvsettings($definedcolumns);

        if (!empty($callbackfunction)) {
            $settings->set_callback($callbackfunction);
        }

        if (!empty($delimiter)) {
            $settings->set_delimiter($delimiter);
        }
        if (!empty($encoding)) {
            $settings->set_encoding($encoding);
        }
        if (!empty($dateformat)) {
            $settings->set_dateformat($dateformat);
        }

        return $settings;
    }

    /**
     * Define settings for csv import form.
     *
     * @return array
     *
     */
    private static function define_testitem_columns() {

        $columnssequential = [
            array(
                'name' => 'componentid',
                'mandatory' => true,
                'format' => PARAM_INT,
                'importinstruction' => get_string('canbesetto0iflabelgiven', 'local_catquiz'),
            ),
            array(
                'name' => 'componentname',
                'mandatory' => true,
                'format' => 'string',
            ),
            array(
                'name' => 'contextid',
                'mandatory' => true,
                'format' => PARAM_INT,
                // We could set the selected cat context (optional_param id) as default.
            ),
            array(
                'name' => 'model',
                'mandatory' => false,
                'format' => 'string',
                'importinstruction' => get_string('modelinformation', 'local_catquiz'),
            ),
            array (
                'name' => 'difficulty',
                'mandatory' => false,
                'format' => PARAM_FLOAT,
            ),
            array (
                'name' => 'status',
                'mandatory' => false,
                'format' => PARAM_INT,
                'defaultvalue' => 0,
            ),
            array (
                'name' => 'discrimination',
                'mandatory' => false,
                'format' => PARAM_FLOAT,
            ),
            array (
                'name' => 'timecreated',
                'mandatory' => false,
                'type' => 'date', // Will throw warning if not empty and not in correct format.
            ),
            array (
                'name' => 'timemodified',
                'mandatory' => false,
            ),
            array (
                'name' => 'guessing',
                'mandatory' => false,
                'format' => PARAM_FLOAT,
            ),
            array (
                'name' => 'label',
                'mandatory' => false,
                'format' => PARAM_TEXT,
                'importinstruction' => get_string('ifdefinedusedtomatch', 'local_catquiz'),
            ),
            array (
                'name' => 'catscaleid',
                'mandatory' => false,
                'format' => PARAM_INT,
                'importinstruction' => get_string('scaleinformation', 'local_catquiz'),
            ),
            array (
                'name' => 'catscalename',
                'mandatory' => false,
                'format' => PARAM_TEXT,
                'importinstruction' => get_string('scalenameinformation', 'local_catquiz'),
            ),
            array (
                'name' => 'parentscalenames',
                'mandatory' => false,
                'format' => PARAM_TEXT,
                'importinstruction' => get_string('parentscalenamesinformation', 'local_catquiz'),
            ),
            array (
                'name' => 'qtype',
                'mandatory' => false,
                'format' => PARAM_TEXT,
            ),
            array (
                'name' => 'name',
                'mandatory' => false,
                'format' => PARAM_TEXT,
            ),
            array (
                'name' => 'attempts',
                'mandatory' => false,
            ),
            array (
                'name' => 'lastattempttime',
                'mandatory' => false,
            )
            ];
        return $columnssequential;
    }

    /** @return array  */
    public static function export_columns_for_template() {
        return self::define_testitem_columns();
    }
}
