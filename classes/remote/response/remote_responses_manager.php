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

namespace local_catquiz\remote\response;

use local_catquiz\local\model\model_responses;
use stdClass;

/**
 * Manages response data from remote instances.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @author     David Szkiba
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remote_responses_manager {
    /** @var string Table name for storing ID mappings. */
    const MAPPING_TABLE = 'local_catquiz_remote_mappings';

    /**
     * Import and transform response data from remote instance.
     *
     * @param stdClass $remotedata Data from remote instance
     * @param string $remotehostid Identifier of the remote host
     * @return model_responses
     */
    public function import_responses(stdClass $remotedata, string $remotehostid): model_responses {
        // First, ensure we have mappings for all IDs.
        $useridmappings = $this->ensure_user_mappings($remotedata->byperson, $remotehostid);
        $itemidmappings = $this->ensure_item_mappings($remotedata->byitem, $remotehostid);
        $attemptidmappings = $this->ensure_attempt_mappings($remotedata->byattempt, $remotehostid);

        // Transform the data using our mappings.
        $transformeddata = $this->transform_data($remotedata, $useridmappings, $itemidmappings, $attemptidmappings);

        return model_responses::create_from_transfer($transformeddata);
    }

    /**
     * When sending results back, convert local IDs back to remote IDs.
     *
     * @param model_responses $responses Local responses
     * @param string $remotehostid Remote host identifier
     * @return stdClass Data with original remote IDs
     */
    public function prepare_for_return(model_responses $responses, string $remotehostid): stdClass {
        $data = $responses->export_for_transfer();

        // Fetch reverse mappings.
        $useridmappings = $this->get_reverse_mappings('user', $remotehostid);
        $itemidmappings = $this->get_reverse_mappings('item', $remotehostid);
        $attemptidmappings = $this->get_reverse_mappings('attempt', $remotehostid);

        // Transform back to original IDs.
        return $this->transform_data($data, $useridmappings, $itemidmappings, $attemptidmappings);
    }

    /**
     * Ensure we have mappings for all user IDs.
     * @param array $byperson Remote user data
     * @param string $remotehostid Remote host identifier
     * @return array Mapping of remote ID => local ID
     */
    private function ensure_user_mappings(array $byperson, string $remotehostid): array {
        global $DB;

        $mappings = [];
        foreach (array_keys($byperson) as $remoteuserid) {
            $existing = $DB->get_record(self::MAPPING_TABLE, [
                'remoteid' => $remoteuserid,
                'type' => 'user',
                'remotehostid' => $remotehostid,
            ]);

            if ($existing) {
                $mappings[$remoteuserid] = $existing->localid;
            } else {
                // Create new local user or map to existing one.
                $localid = $this->create_or_map_user($remoteuserid);
                $DB->insert_record(self::MAPPING_TABLE, [
                    'remoteid' => $remoteuserid,
                    'localid' => $localid,
                    'type' => 'user',
                    'remotehostid' => $remotehostid,
                    'timecreated' => time(),
                ]);
                $mappings[$remoteuserid] = $localid;
            }
        }
        return $mappings;
    }

    /**
     * Get reverse mappings to convert local IDs back to remote IDs.
     * @param string $type Type of mapping (user, item, attempt)
     * @param string $remotehostid Remote host identifier
     * @return array Mapping of local ID => remote ID
     */
    private function get_reverse_mappings(string $type, string $remotehostid): array {
        global $DB;

        $records = $DB->get_records(self::MAPPING_TABLE, [
            'type' => $type,
            'remotehostid' => $remotehostid,
        ]);

        $mappings = [];
        foreach ($records as $record) {
            $mappings[$record->localid] = $record->remoteid;
        }
        return $mappings;
    }

    // ... similar methods for item and attempt mappings ...
}
