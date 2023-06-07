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
use mod_bigbluebuttonbn\event\recording_protected;

defined('MOODLE_INTERNAL') || die();

class csvsettings {
/**
 * @var string
 */
public $delimiter = 'comma';

/**
 * @var string
 */
public $enclosure = '"';

/**
 * @var string
 */
public $encoding = 'UTF-8';

/**
 * @var string
 */
public $dateformat = 'j.n.Y H:i:s';

/**
 * @var array of column names
 */
public $columns = [];

/**
 * @var boolean
 */
public $columnsarrayisassociative = false;

/**
 * @var boolean
 */
public $acceptunknowncolumn = false;


public function __construct($columns) {
    $this->create_columns($columns);
}

private function create_columns($columns) {
    if(!isset($columns)) {
        return;
    }
    //Check if columns array is sequential or associative.
    $keys = array_keys($columns);
    if ($keys !== array_keys($keys)) {
        $this->columnsarrayisassociative = true;
        foreach($columns as $ckey => $cvalue) {
            $this->columns[$ckey] = new csvcolumn(
                $ckey,
                !empty($cvalue['localizedname']) ? $cvalue['localizedname'] : $ckey,
                null !== $cvalue['mandatory'] ? $cvalue['mandatory'] : null,
                null !== $cvalue['unique'] ? $cvalue['unique'] : null,
                !empty($cvalue['type']) ? $cvalue['type'] : null,
                !empty($cvalue['format']) ? $cvalue['format'] : null,
                !empty($cvalue['defaultvalue']) ? $cvalue['defaultvalue'] : null,
                !empty($cvalue['transform']) ? $cvalue['transform'] : null,
            );
        }
    } else {
        foreach($columns as $c) {
            if(!isset($c['name'])) {
                break;
            }
            $this->columns[$c['name']] = new csvcolumn(
                $c['name'],
                !empty($c['localizedname']) ? $c['localizedname'] : $c['name'],
                null !== $c['mandatory'] ? $c['mandatory'] : null,
                null !== $c['unique'] ? $c['unique'] : null,
                !empty($c['type']) ? $c['type'] : null,
                !empty($c['format']) ? $c['format'] : null,
                !empty($c['defaultvalue']) ? $c['defaultvalue'] : null,
                !empty($c['transform']) ? $c['transform'] : null,

            );
        }
    }
}   

/**
 * Calling column class to update property with given value.
 * @param string $columnname 
 * @param string $param 
 * @param string $value 
 * @return bool 
 */
public function set_param_in_column($columnname, $param, $value) {
    if(property_exists($this->columns[$columnname], $param)) {
        return $this->columns[$columnname]->set_property($param, $value);
    } else {
        return false;
    }
}


//Getters and Setters
/**
 * 
 * @return string
 */
public function get_delimiter() {
    return $this->delimiter;
}
/**
 * 
 * @param string $delimiter 
 */
public function set_delimiter($delimiter) {
    $this->delimiter = $delimiter;
}

/**
 * 
 * @return string
 */
public function get_enclosure() {
    return $this->enclosure;
}

/**
 * 
 * @param string $enclosure 
 */
public function set_enclosure($enclosure) {
    $this->enclosure = $enclosure;
}

/**
 * 
 * @return string
 */
public function get_encoding() {
    return $this->encoding;
}

/**
 * 
 * @param string $encoding 
 */
public function set_encoding($encoding) {
    $this->encoding = $encoding;
}

/**
 * 
 * @return string
 */
public function get_dateformat() {
    return $this->dateformat;
}

/**
 * 
 * @param string $dateformat 
 */
public function set_dateformat($dateformat) {
    $this->dateformat = $dateformat;
}
}