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

namespace local_catquiz\data;

use cache;
use context_system;
use local_catquiz\event\dimension_updated;

/**
 * Get and store data from db.
 */
class dataapi {

    /**
     * Get all dimensions either from cache or db
     *
     * @return array
     */
    public static function get_all_dimensions(): array {
        global $DB;
        $cache = cache::make('local_catquiz', 'dimensions');
        $alldimensions = $cache->get('alldimensions');
        if (!$alldimensions) {
            $records = $DB->get_records('local_catquiz_dimensions');
            if (!empty($records)) {
                foreach ($records as $record) {
                    $alldimensions[$record->id] = new dimension_structure((array) $record);
                }
            } else {
                $alldimensions = [];
            }
            $cache->set('alldimensions', $alldimensions);
        }
        return $alldimensions;
    }

    /**
     * Save a new dimension and invalidate cache. Checks if name is unique
     *
     * @param dimension_structure $dimension
     * @return int 0 if name already exists
     */
    public static function create_dimension(dimension_structure $dimension): int {
        global $DB;
        if (self::name_exists($dimension->name)) {
            return 0;
        }
        $id = $DB->insert_record('local_catquiz_dimensions', $dimension);

        // Invalidate cache. TODO: Instead of invalidating cache, add the item to the cache.
        $cache = cache::make('local_catquiz', 'dimensions');
        $cache->delete('alldimensions');
        return $id;
    }

    /**
     * Delete a dimension and invalidate cache.
     *
     * @param dimension_structure $dimension
     * @return bool
     */
    public static function delete_dimension(int $dimensionid): bool {
        global $DB;
        $result = $DB->delete_records('local_catquiz_dimensions', ['id' => $dimensionid]);

        // Invalidate cache. TODO: Instead of invalidating cache, delete the item from the cache.
        $cache = cache::make('local_catquiz', 'dimensions');
        $cache->delete('alldimensions');
        return $result;
    }

    /**
     * Update a dimension and invalidate cache.
     *
     * @param dimension_structure $dimension
     * @return bool
     */
    public static function update_dimension(dimension_structure $dimension): bool {
        global $DB, $USER;
        $result = $DB->update_record('local_catquiz_dimensions', $dimension);

        $context = context_system::instance();

        $event = dimension_updated::create([
            'objectid' => $dimension->id,
            'context' => $context,
            'userid' => $USER->id, // The user who did cancel.
        ]);
        $event->trigger();

        // Invalidate cache. TODO: Instead of invalidating cache, delete and add the item from the cache.
        $cache = cache::make('local_catquiz', 'dimensions');
        $cache->delete('alldimensions');
        return $result;
    }

    /**
     * Check if name of dimension already exsists - must be unique
     * @param string $name dimension name
     * @return bool true if name already exists, false if not
     */
    public static function name_exists(string $name): bool {
        global $DB;
        if ($DB->record_exists('local_catquiz_dimensions', ['name' => $name])) {
            return true;
        } else {
            return false;
        }
    }
}
