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
 * Entities Class to display list of entity records.
 *
 * @package local_catquiz
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use cache_helper;
use stdClass;

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catscale {

    /**
     *
     * @var stdClass $catscale.
     */
    public ?stdClass $catscale = null;

    /**
     * Catscale constructor.
     *
     * @param integer $catscaleid
     */
    public function __construct(int $catscaleid) {

        $this->catscale = self::return_catscale_object($catscaleid);
    }


    /**
     * Static function to return catscale object.
     *
     * @param integer $catscaleid
     * @return stdClass|null
     */
    public static function return_catscale_object(int $catscaleid) {
        global $DB;
        return $DB->get_record('local_catquiz_catscales', ['id' => $catscaleid]) ?? null;
    }

    /**
     * Adds or updates attribution of question to scale.
     *
     * @param integer $catscaleid
     * @param integer $testidemid
     * @param string $component
     * @return integer
     */
    public static function add_or_update_testitem_to_scale(int $catscaleid, int $testidemid, string $component = 'question') {

        global $DB;

        $data = [
            'componentid' => $testidemid,
            'componentname' => 'question',
            'catscaleid' => $catscaleid,
        ];

        if ($record = $DB->get_record('local_catquiz_items', $data)) {
            // Right now, there is nothing to do, as we don't have more data.
            // $data['id'] = $record->id;
            // $DB->update_record('local_catquiz_items', (object)$data);
            $id = $record->id;
        } else {
            $now = time();
            $data['timemodified'] = $now;
            $data['timecreated'] = $now;
            $id = $DB->insert_record('local_catquiz_items', (object)$data);
        }
        cache_helper::purge_by_event('changesintestitems');
        return $id;
    }

    /**
     * Removes attribution of question to scale.
     *
     * @param integer $catscaleid
     * @param integer $testidemid
     * @param string $component
     * @return void
     */
    public static function remove_testitem_from_scale(int $catscaleid, int $testidemid, string $component = 'question') {

        global $DB;

        $data = [
            'componentid' => $testidemid,
            'componentname' => $component,
            'catscaleid' => $catscaleid,
        ];

        $DB->delete_records('local_catquiz_items', $data);
        cache_helper::purge_by_event('changesintestitems');
    }
}
