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
 *
 *
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH <georg.maisser@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\import;

use csv_import_reader;
use html_writer;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/csvlib.class.php");

class fileparser {

    /**
     * @var string
     */
    protected $pluginname = "local_catquiz";

    /**
     * @var string
     */
    protected $delimiter = 'comma';

    /**
     * @var string
     */
    protected $enclosure = '';

    /**
     * @var string
     */
    protected $encoding = 'UTF-8';

    /**
     * @var array of column names
     */
    protected $columns = [];

    /**
     * @var array of fieldnames imported from csv
     */
    protected $fieldnames = [];

    /**
     * @var string with errors one per line
     */
    protected $csverrors = '';

    /**
     * @var object
     */
    protected $settings = null;

    /**
     * @var string error message
     */
    protected $error = '';

    /**
     * @var array of fieldnames from other db tables
     */
    protected $additionalfields = [];

    /**
     * @var array of objects
     */
    protected $customfields = [];

    /**
     * @var object of strings
     */
    protected $requirements;

    public function __construct($content, $settings) {
        // optional: switch on type of settings object -> process data according to type (csv, ...)
        $process = $this->process_csv_data($content, $settings);
    }
                                                                   
    /**
     * Imports data and settings for parsing of csv data
     *
     * @param $content
     * @param object $settings
     * @return bool false when import failed, true when import worked. Line errors might have happend
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function process_csv_data($content, $settings) {
        global $DB;
        $this->error = '';
        $this->settings = $settings;

        if (!empty($this->settings->columns)) {
            $this->columns = $this->settings->columns;
        } else {
            $this->error .= "No column labels defined in settings object.";
            return false;
        }

        $iid = csv_import_reader::get_new_iid($this->pluginname);
        $cir = new csv_import_reader($iid, $this->pluginname);

        $delimiter = !empty($this->settings->delimiter) ? $this->settings->delimiter : 'comma';
        $enclosure = !empty($this->settings->enclosure) ? $this->settings->enclosure : '"';
        $encoding = !empty($this->settings->encoding) ? $this->settings->encoding : 'utf-8';
        //$updateexisting = !empty($this->settings->updateexisting) ? $this->settings->updateexisting : false; //is this needed?
        $readcount = $cir->load_csv_content($content, $encoding, $delimiter, null, $enclosure);

        if (empty($readcount)) {
            $this->error .= $cir->get_error();
            return false;
        }

        // Csv column headers.
        if (!$fieldnames = $cir->get_columns()) {
            $this->error .= $cir->get_error();
            return false;
        }
        $this->fieldnames = $fieldnames;
        if (!empty($this->validate_fieldnames())) {
            $this->error .= $this->validate_fieldnames();
            return false;
        }

        $cir->init();
        while ($line = $cir->next()) {
            // Import option data (not user data). Do not update booking option if it exists.
            $csvrecord = array_combine($fieldnames, $line);

            // Validate data
            foreach ($csvrecord as $column => $value) {

                $valueisset = (null !== $value) ? true : false;
                
                // Check if empty fields are mandatory
                if (!$valueisset) {
                    if ($this->field_is_mandatory($column)) {
                        $this->add_csverror("The field $column is mandatory but contains no value.", $line[0]);
                        break;
                    }
                    // If no value is set, use defaultvalue
                    if (isset($this->settings->columns->$column->defaultvalue)) {
                        $value = $this->settings->columns->$column->defaultvalue;
                    }
                }
                // Validate fields of type date.
                if (!$this->validate_datefields($column, $value)) {
                    $format = $this->settings->dateformat;
                    $this->add_csverror("$value is not a valid date format in $column. Format should be like: $format", $line[0]);
                    break;
                }
                // Should we additionally check, if value is of given type? or cast to type according to settings?
                // Other validations?
            };
        }
        $cir->cleanup(true);
        $cir->close();
        return true;
    }

    /**
     * Comparing labels of content to required labels.
     * @return string empty if ok, errormsg if fieldname not correct in csv file.
     */
    protected function validate_fieldnames() {
        $error = '';
        foreach ($this->fieldnames as $fieldname) {
            if(!in_array($fieldname, array_keys($this->columns))) {
                $error .= "Imported CSV not containing the right labels. Check first line of imported csv file.";
                break;
            } 
        }
        return $error;
    }

    /**
     * Check if field is mandatory have values. Adds error and returns false in case of fail.
     *
     * @param string $columnname
     * @return bool true on validation false on error
     */
    protected function field_is_mandatory($columnname) {

        if ($this->settings->columns[$columnname]->mandatory) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Check if date fields format is valid. Adds error and returns false in case of fail.
     * @param string $columnname
     * @param $value
     * @return bool true on validation false on error
     */
    protected function validate_datefields($columnname, $value) {
        $foo = $this->settings->columns[$columnname];
        $foo1 = $this->settings->columns[$columnname]->type;
        //$foo2 = $this->settings->columns[$columnname]['type'];

        if ($foo1 = $this->settings->columns[$columnname]->type == "date") {
            $dateformat = !empty($this->settings->dateformat) ? $this->settings->dateformat : "j.n.Y H:i:s";
            //Check if we have a numeric unix timestamp.
            if (is_numeric($value)) {
                $timestamp = (int) $value;
                $value = date($dateformat, $timestamp);
            }
            //Check if we have a readable string.
            if (!date_create_from_format($dateformat, $value) &&
                    !strtotime($value)) {
                        return false;
                }
        }
        return true;
    }

    /**
     * Add error message to $this->csverrors
     *
     * @param $errorstring
     */
    protected function add_csverror($errorstring, $i) {
        $this->csverrors .= html_writer::empty_tag('br');
        $this->csverrors .= "Error in line $i: ";
        $this->csverrors .= $errorstring;
    }
}