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
use DateTime;
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

    /**
     * @var string columnname
     */
    public $uniquekey; 

    public function __construct ($settings) {
        // optional: switch on type of settings object -> process data according to type (csv, ...)

        $this->apply_settings($settings);
    }
        
    /**
     * Validate and apply settings
     * @param object $settings
     */
    private function apply_settings($settings) {
        global $DB;
        $this->error = '';
        $this->settings = $settings;

        if (!empty($this->settings->columns)) {
            $this->columns = $this->settings->columns;
        } else {
            $this->error .= "No column labels defined in settings object.";
            return false;
        }

        $this->delimiter = !empty($this->settings->delimiter) ? $this->settings->delimiter : 'comma';
        $this->enclosure = !empty($this->settings->enclosure) ? $this->settings->enclosure : '"';
        $this->encoding = !empty($this->settings->encoding) ? $this->settings->encoding : 'utf-8';
        //$updateexisting = !empty($this->settings->updateexisting) ? $this->settings->updateexisting : false; //is this needed?
    }

    /**
     * Imports content and compares to settings.
     *
     * @param $content
     * @return array false when import failed, true when import worked. Line errors might have happend
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function process_csv_data($content) {
        $data = [];
        $iid = csv_import_reader::get_new_iid($this->pluginname);
        $cir = new csv_import_reader($iid, $this->pluginname);

        $readcount = $cir->load_csv_content($content, $this->encoding, $this->delimiter, null, $this->enclosure);

        if (empty($readcount)) {
            $this->error .= $cir->get_error();
            return $data;
        }

        // Csv column headers.
        if (!$fieldnames = $cir->get_columns()) {
            $this->error .= $cir->get_error();
            return $data;
        }
        $this->fieldnames = $fieldnames;
        if (!empty($this->validate_fieldnames())) {
            $this->error .= $this->validate_fieldnames();
            return $data;
        }

        // Check if first column is set mandatory and unique
        $firstcolumn = $this->fieldnames[0];
        if ($this->get_param_value($firstcolumn, 'mandatory') == true 
        && $this->get_param_value($firstcolumn, 'unique') == true) {
            $this->uniquekey = $firstcolumn;
        }

        $cir->init();
        while ($line = $cir->next()) {
            $csvrecord = array_combine($fieldnames, $line);
            // fieldnames hat das array der keys
            // line hat alle values der reihe nach in einem array
            $this->validate_data($csvrecord, $line);

            if (isset($this->uniquekey)) {
                $record = array(
                    $firstcolumn[$csvrecord[$firstcolumn]]);

// Hier mit klarem Kopf das Array bauen!

                                     /*         
                $record = array(
                    $firstcolumn => array(
                        $csvrecord[$firstcolumn] => $recorddata[$columname] = $value)
                )*/
                
               // foreach ($csvrecord as $columnname => $value) {
               //  $values = array($recorddata[$columname] =>  $value);}
            } else {

                // wir haben keinen unique key, also wird data ein sequentielles array

            }

            // ziel ist jetzt 
        }
        $cir->cleanup(true);
        $cir->close();
        return $data;
    }
    
    /**
     * Validate each record by comparing to settings.
     *
     * @param array $csvrecord
     * @param array $line
     */
    private function validate_data($csvrecord, $line) {  
        // Validate data
        foreach ($csvrecord as $column => $value) {

            $valueisset = (("" !== $value) && (null !== $value)) ? true : false;
            
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
            // Validation of field type
            switch($this->get_param_value($column, "type")) {
                case "date":
                    if (!$this->validate_datefields($value)) {
                        $format = $this->settings->dateformat;
                        $this->add_csverror("$value is not a valid date format in $column. Format should be Unix timestamp or like: $format", $line[0]);
                        break;
                    }
                    break;
                default:
                    break;
            }
            // Validation of field format
            switch($this->get_param_value($column, "format")) {
                case "int":
                    $value = $this->cast_string_to_int($value);
                    if (is_string($value)) {
                        $this->add_csverror("$value is not a valid integer in $column", $line[0]);
                    }
                    break;
                default:
                    break;
            }

        };
    }
    /**
     * Check if the given string is a valid int and if possible, cast to int.
     *
     * @param string $value
     * @return * either int or the given value (string)
     */
    protected function cast_string_to_int($value) {

        $validation = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($validation !== false) {
            // The string is a valid integer
            $int = (int)$value; // Casting to integer
            return $int;
        } else {
            return $value;
        }        
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
     * @param string $value
     * @return bool true on validation false on error
     */
    protected function validate_datefields($value) {
        //Check if we have a readable string in correct format.
        $readablestring = false;
        $dateformat = !empty($this->settings->dateformat) ? $this->settings->dateformat : "j.n.Y H:i:s";
        if (date_create_from_format($dateformat, $value) &&
                strtotime($value)) {
                    $readablestring = true;
            }
        // Check accepts all ints.
        $date = DateTime::createFromFormat('U', $value);

        if (($date && $date->format('U') == $value) || $readablestring) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks the value of a given param of the column.
     * @param string $columnname
     * @param string $param
     * @return string true on validation false on error
     */
    protected function get_param_value($columnname, $param) {
        if (isset($this->settings->columns[$columnname]->$param)) {
            return $this->settings->columns[$columnname]->$param;
        } else {
            return "";
        }
    }

    /**
     * Checks the value of a given param of the column.
     * @param string $columnname
     * @param string $param
     * @param $value
     * @return string true on validation false on error
     */
    protected function set_param_value($columnname, $param, $value) {
        if (isset($this->settings->columns[$columnname]->$param)) {
            $this->settings->columns[$columnname]->$param = $value;
        }
 
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