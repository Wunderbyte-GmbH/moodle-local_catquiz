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

defined('MOODLE_INTERNAL') || die();

class csvcolumn {

/**
 * @var string
 */
public $columnname = '';

/**
 * @var string
 */
public $localizedname = '';

/**
 * @var boolean
 */
public $mandatory = true;

/**
 * @var string
 */
public $format = 'string';

/**
 * @var string
 */
public $type = 'default';

/**
 * @var *
 */
public $defaultvalue;

/**
 * @var *
 */
public $transform;

public function __construct(
    $columnname = '', 
    $localizedname = '', 
    $mandatory = true,
    $type = 'default',
    $format = PARAM_TEXT,
    $defaultvalue = null,
    $transform = null) {
    
    $this->columnname = $columnname;
    $this->localizedname = $localizedname;
    $this->mandatory = $mandatory;
    $this->format = $format;
    $this->type = $type;
    $this->defaultvalue = $defaultvalue;
    $this->transform = $transform;

    $this->apply('pluginname');
}

public function apply($value) {

    if(empty($this->transform)) {
        return;
    }
    $func = $this->transform;
    $result = $func($value);
    return $result;
}

/**
 * @param string $param 
 * @param string $value 
 * @return boolean 
 */
public function set_property($param, $value) {
    if(isset($this->$param)) {
        $this->$param = $value;
        return true;
    } else {
        return false;
    }
}

/**
 * Transform a date object to a string in the defined format.
 * @param mixed $date 
 * @param string $format 
 * @return string 
 */
public function date_to_string($date, $format) {

    //input is date return string in defined format
    return "";
}
/**
 * Transform a string to an object in the defined format.
 * @param mixed $date 
 * @param string $format 
 * @return mixed
 */
public function string_to_date($date, $format) {

    //input is date return string in defined format
    return "";
}





}