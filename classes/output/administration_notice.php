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

namespace local_catquiz\output;

use local_catquiz\local\attempt\administration_history;
use renderable;
use renderer_base;
use templatable;

/**
 * Tells teachers which questions an attempt administered more than once (issue #125).
 *
 * Shown on the review page of an attempt, which only people allowed to review other users' results
 * reach. It names each repeated question with its original and its duplicate slot, and says what
 * the duplicate did not do: invalidate the result, or count as a second measurement.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class administration_notice implements renderable, templatable {
    /** @var int Id of the attempt in adaptivequiz_attempt. */
    private int $attemptid;

    /**
     * Constructor.
     *
     * @param int $adaptiveattemptid Id of the attempt in adaptivequiz_attempt.
     */
    public function __construct(int $adaptiveattemptid) {
        $this->attemptid = $adaptiveattemptid;
    }

    /**
     * Returns the data for the template; empty when the attempt has no duplicates.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $duplicates = administration_history::technical_duplicates(
            administration_history::for_attempt($this->attemptid)
        );
        if (!$duplicates) {
            return ['hasduplicates' => false, 'items' => []];
        }

        $names = $DB->get_records_list(
            'question',
            'id',
            array_unique(array_map(fn($entry) => $entry->questionid, $duplicates)),
            '',
            'id, name'
        );

        $items = [];
        foreach ($duplicates as $entry) {
            $items[] = [
                'text' => get_string('technicalduplicate_item', 'local_catquiz', (object) [
                    'name' => format_string($names[$entry->questionid]->name ?? ''),
                    'questionid' => $entry->questionid,
                    'originalslot' => $entry->duplicateofslot,
                    'duplicateslot' => $entry->slot,
                ]),
                'questionid' => $entry->questionid,
                'originalslot' => $entry->duplicateofslot,
                'duplicateslot' => $entry->slot,
            ];
        }

        return ['hasduplicates' => true, 'items' => $items];
    }
}
