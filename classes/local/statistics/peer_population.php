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

namespace local_catquiz\local\statistics;

use local_catquiz\local\model\model_person_param;
use local_catquiz\local\result\scale_result;

/**
 * The people the result of an attempt is compared with in the feedback (issue #88).
 *
 * One value per person: the newest valid estimate of the person in the CAT context and scale
 * (local_catquiz_personparams, a valid measurement: scale_result::is_valid_measurement()), never a saturated
 * one. Values without an attempt (legacy, recalibration) count: they are estimates of the person in this
 * context and scale. The percentile and the histogram of
 * "Your result" (comparetotestaverage) both read it - the percentile used every newest estimate that was
 * not saturated, valid or not, while the histogram counted valid ones only, so the two parts of one
 * feedback described different groups.
 *
 * Deliberate difference: the percentile leaves out the person compared ($excludeuserid), the histogram
 * shows everybody, the person included.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class peer_population {
    /**
     * The rows of the population: userid, ability.
     *
     * @param int $contextid
     * @param int $scaleid
     * @param int|null $excludeuserid The person compared, left out; null for everybody.
     * @param string $prefix Prefix of the parameter names.
     * @return array [sql, params]
     */
    public static function sql(int $contextid, int $scaleid, ?int $excludeuserid = null, string $prefix = 'peer'): array {
        $inf = model_person_param::MODEL_POS_INF;
        // Issue #150 (re-audit 27.10.2026): a valid measurement of the scale, not only the reported scale of the
        // attempt it came from - under "lowest skill gap" each person reports another subscale, and the peers of a
        // subscale were only those for whom it was the lowest one.
        [$measured, $measuredparams] = scale_result::valid_measurement_sql('pp2', $prefix);
        $exclude = $excludeuserid === null ? '' : "AND pp.userid <> :{$prefix}exclude";
        $sql = "SELECT pp.userid, pp.ability
                  FROM {local_catquiz_personparams} pp
                 WHERE pp.contextid = :{$prefix}context
                   AND pp.catscaleid = :{$prefix}scale
                   $exclude
                   AND pp.id = (
                       SELECT MAX(pp2.id)
                         FROM {local_catquiz_personparams} pp2
                        WHERE pp2.contextid = pp.contextid
                          AND pp2.catscaleid = pp.catscaleid
                          AND pp2.userid = pp.userid
                          AND $measured
                   )
                   AND pp.ability IS NOT NULL
                   AND ABS(pp.ability) < :{$prefix}inf";
        $params = [
            $prefix . 'context' => $contextid,
            $prefix . 'scale' => $scaleid,
            $prefix . 'inf' => $inf,
        ] + $measuredparams;
        if ($excludeuserid !== null) {
            $params[$prefix . 'exclude'] = $excludeuserid;
        }
        return [$sql, $params];
    }
}
