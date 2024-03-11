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
 * Class recalculate_cat_model_params.
 *
 * @package local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\task;

use local_catquiz\catmodel_info;
use local_catquiz\catquiz;

/**
 * Runs through all contexts and recalculates values for all CAT models.
 *
 * @package local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalculate_cat_model_params extends \core\task\scheduled_task {

    /**
     * Returns task name.
     * @return string
     */
    public function get_name() {
        return get_string('task_recalculate_cat_model_params', 'local_catquiz');
    }

    /**
     * Update all model params of all contexts.
     * @return void
     */
    public function execute() {
        global $DB;

        $now = time();
        // Get all contexts.
        $contexts = $DB->get_records_sql(
            <<<SQL
                SELECT * FROM {local_catquiz_catcontext} cc
                WHERE starttimestamp <= :now1 AND endtimestamp >= :now2
                ;
            SQL,
            [
                'now1' => $now,
                'now2' => $now,
            ]
        );
        $catscales = catquiz::get_all_catscales();
        $cmi = new catmodel_info();
        foreach ($contexts as $context) {
            foreach ($catscales as $catscale) {
                if (! $cmi->needs_update($context, $catscale->id)) {
                    continue;
                }
                $cmi->update_params($context->id, $catscale->id);
            }
        }
    }
}
