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
 * Test fixture: the progress of an attempt that answered the given number of items per scale.
 *
 * Since issue #128 a scale counts as measured only when its N in the attempt is known and positive.
 * Tests that file a CAT attempt directly have no progress; this gives them the one a real attempt
 * would have, built through progress::load(), not through invented JSON.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Files the progress of an attempt with N answered productive items per scale.
 *
 * The current user must be the owner of the attempt (progress::load() checks it).
 *
 * @param int $adaptiveattemptid Id of the attempt of the component: adaptivequiz_attempt.id.
 * @param array $nbyscale Answered items by scale id.
 * @param int $contextid The CAT context.
 * @param array $quizsettings The quiz settings the progress carries.
 * @param array $fractionbyscale Fraction of every answer by scale id. Defaults to half credit, which keeps
 *      the fraction of each scale inside (0, 1): with all answers right or all wrong a scale is invalid
 *      (issue #140).
 * @param int $pilots Answered pilot items: played, with the first scale, but not in the items by scale.
 */
function local_catquiz_measured_progress(
    int $adaptiveattemptid,
    array $nbyscale,
    int $contextid = 9,
    array $quizsettings = [],
    array $fractionbyscale = [],
    int $pilots = 0
): void {
    static $nextquestionid = 900000;

    $progress = \local_catquiz\teststrategy\progress::load(
        $adaptiveattemptid,
        'mod_adaptivequiz',
        $contextid,
        (object) $quizsettings
    );
    $played = [];
    $byscale = [];
    $responses = [];
    foreach ($nbyscale as $scaleid => $n) {
        for ($i = 0; $i < $n; $i++) {
            $q = (object) ['id' => ++$nextquestionid, 'catscaleid' => (int) $scaleid, 'is_pilot' => false,
                'fisherinformation' => []];
            $played[$q->id] = $q;
            $byscale[(int) $scaleid][$q->id] = $q;
            $fraction = (float) ($fractionbyscale[$scaleid] ?? 0.5);
            $responses[$q->id] = ['questionid' => $q->id, 'fraction' => $fraction];
        }
    }
    for ($i = 0; $i < $pilots; $i++) {
        $q = (object) ['id' => ++$nextquestionid, 'catscaleid' => (int) array_key_first($nbyscale), 'is_pilot' => true,
            'fisherinformation' => []];
        $played[$q->id] = $q;
        $responses[$q->id] = ['questionid' => $q->id, 'fraction' => 1.0];
    }
    $state = [
        'responses' => $responses,
        'playedquestions' => $played,
        'playedquestionsbyscale' => $byscale,
        'lastquestion' => end($played) ?: null,
    ];
    foreach ($state as $name => $value) {
        $property = new \ReflectionProperty($progress, $name);
        $property->setAccessible(true);
        $property->setValue($progress, $value);
    }
    $progress->save();
}
