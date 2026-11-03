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

namespace local_catquiz\local;

use local_catquiz\catscale;
use local_catquiz\teststrategy\feedback_helper;
use stdClass;

/**
 * The outer limits of the feedback ranges follow the global scale (issue #86).
 *
 * The limits of a global scale (minscalevalue, maxscalevalue of the root) are the one source for the range of
 * ability: estimation, the gate of the feedback and the scale of the charts read them from the scale table.
 * A test environment keeps feedback ranges in its JSON, and the first and the last range began and ended at
 * the limits the scale had when the activity was saved. After a change of the scale, -3..3 to -4..4, the
 * stored ranges still ended at 3: the form showed the old limits again, and an ability of 3.5 - inside the
 * scale - fell into no range and got no feedback colour.
 *
 * The rule: the outer limits of the ranges of every scale of the test are the limits of its global scale; the
 * limits between the ranges are the teacher's and stay, moved into the range of the scale if it shrank. The
 * JSON of a test is aligned when the global scale changes (and once by an upgrade step), and whatever reads
 * the settings of a test - the form, a new attempt - gets them aligned. An attempt keeps the settings it
 * started with (progress::get_quiz_settings()): what was stored for it is not changed.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_limits {
    /**
     * Aligns the feedback ranges of test settings to the limits of the global scale.
     *
     * @param stdClass $settings The settings of a test environment, as stored in its JSON; changed in place.
     * @param array|null $range minscalevalue, maxscalevalue; null reads them from the global scale of the test.
     * @return bool Whether a value was changed.
     */
    public static function align(stdClass $settings, ?array $range = null): bool {
        $options = (int) ($settings->numberoffeedbackoptionsselect ?? 0);
        if ($options < 1) {
            return false;
        }
        if ($range === null) {
            global $DB;
            $rootid = (int) ($settings->catquiz_catscales ?? 0);
            if ($rootid < 1 || !$DB->record_exists('local_catquiz_catscales', ['id' => $rootid])) {
                return false;
            }
            $range = (new catscale($rootid))->get_ability_range($rootid);
        }
        $min = (float) $range['minscalevalue'];
        $max = (float) $range['maxscalevalue'];
        if (!($max > $min)) {
            // No range to align to: a configuration error, reported where the ranges are used.
            return false;
        }

        $changed = false;
        foreach (self::scales_with_ranges($settings) as $scaleid) {
            $previous = $min;
            for ($i = 1; $i <= $options; $i++) {
                foreach (['lower', 'upper'] as $side) {
                    $key = "feedback_scaleid_limit_{$side}_{$scaleid}_{$i}";
                    if (
                        !property_exists($settings, $key) || is_array($settings->$key) || is_object($settings->$key)
                        || trim((string) $settings->$key) === ''
                    ) {
                        continue;
                    }
                    // Stored as the form sent it: a number, or a string, with a decimal comma or point.
                    $stored = feedback_helper::parse_range_limit($settings->$key);
                    if ($i === 1 && $side === 'lower') {
                        $value = $min;
                    } else if ($i === $options && $side === 'upper') {
                        $value = $max;
                    } else {
                        // Inside the range of the scale, and never below the limit before it.
                        $value = max($previous, min($max, max($min, $stored)));
                    }
                    $previous = $value;
                    if ($stored !== $value) {
                        $settings->$key = $value;
                        $changed = true;
                    }
                }
            }
        }
        return $changed;
    }

    /**
     * Aligns the stored JSON of every test environment of a global scale.
     *
     * @param int $rootscaleid
     * @return int Number of test environments changed.
     */
    public static function align_tests_of_scale(int $rootscaleid): int {
        global $DB;
        $range = (new catscale($rootscaleid))->get_ability_range($rootscaleid);
        $changed = 0;
        $tests = $DB->get_recordset('local_catquiz_tests', ['catscaleid' => $rootscaleid], 'id', 'id, json');
        foreach ($tests as $test) {
            if (self::align_record($test, $range)) {
                $changed++;
            }
        }
        $tests->close();
        return $changed;
    }

    /**
     * Aligns the stored JSON of every test environment (upgrade).
     *
     * @return int Number of test environments changed.
     */
    public static function align_all_tests(): int {
        global $DB;
        $changed = 0;
        $tests = $DB->get_recordset('local_catquiz_tests', null, 'id', 'id, json');
        foreach ($tests as $test) {
            if (self::align_record($test, null)) {
                $changed++;
            }
        }
        $tests->close();
        return $changed;
    }

    /**
     * Aligns and stores one test environment.
     *
     * @param stdClass $test id, json
     * @param array|null $range
     * @return bool Whether it was changed.
     */
    private static function align_record(stdClass $test, ?array $range): bool {
        global $DB;
        $settings = json_decode((string) $test->json);
        if (!$settings instanceof stdClass || !self::align($settings, $range)) {
            return false;
        }
        $DB->update_record('local_catquiz_tests', (object) [
            'id' => $test->id, 'json' => json_encode($settings), 'timemodified' => time(),
        ]);
        return true;
    }

    /**
     * The scales that have feedback ranges in the settings.
     *
     * @param stdClass $settings
     * @return int[]
     */
    private static function scales_with_ranges(stdClass $settings): array {
        $scaleids = [];
        foreach (array_keys(get_object_vars($settings)) as $key) {
            if (preg_match('/^feedback_scaleid_limit_lower_(\d+)_1$/', $key, $matches)) {
                $scaleids[] = (int) $matches[1];
            }
        }
        return $scaleids;
    }
}
