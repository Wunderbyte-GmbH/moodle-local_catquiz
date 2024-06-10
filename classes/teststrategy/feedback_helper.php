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
 * Class feedback_helper.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz\teststrategy;

use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\local\model\model_model;
use stdClass;

/**
 * Contains helper functions for quiz feedback.
 *
 * @package local_catquiz
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_helper {

    /**
     * Write information about colorgradient for colorbar.
     *
     * @param array $quizsettings
     * @param float $personability
     * @param int $catscaleid
     * @return string
     *
     */
    public function get_color_for_personability(array $quizsettings, float $personability, int $catscaleid): string {
        $default = LOCAL_CATQUIZ_DEFAULT_GREY;
        return $default; // TODO: Remove.
        $abilityrange = $this->get_ability_range($catscaleid);
        if (!$quizsettings ||
            $personability < (float) $abilityrange['minscalevalue'] ||
            $personability > (float) $abilityrange['maxscalevalue']) {
            return $default;
        }
        $numberoffeedbackoptions = intval($quizsettings['numberoffeedbackoptionsselect'])
            ?? LOCAL_CATQUIZ_MAX_SCALERANGE;
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);

        for ($i = 1; $i <= $numberoffeedbackoptions; $i++) {
            $rangestartkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_" . $i;
            $rangeendkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $i;
            $rangestart = floatval($quizsettings[$rangestartkey]);
            $rangeend = floatval($quizsettings[$rangeendkey]);

            if ($personability >= $rangestart && $personability <= $rangeend) {
                $colorkey = 'wb_colourpicker_' . $catscaleid . '_' . $i;
                $colorname = $quizsettings[$colorkey];
                return $colorarray[$colorname];
            }

        }
        return $default;
    }


    /**
     * For testing this is called in seperate function.
     *
     * @param mixed $catscaleid
     *
     * @return array
     *
     */
    public function get_ability_range($catscaleid): array {
        $cs = new catscale($catscaleid);
        // Ability range is the same for all scales with same root scale.
        return $cs->get_ability_range();
    }

    /**
     * For testing, this is called here.
     *
     * @param int $catscaleid
     * @param int $contextid
     * @param bool $includesubscales
     *
     * @return array
     *
     */
    public function get_testitems_for_catscale(int $catscaleid, int $contextid, bool $includesubscales) {
        $catscale = new catscale($catscaleid);
        // Prepare data for test information line.
        return $catscale->get_testitems($contextid, $includesubscales);
    }

    /**
     * Get fisherinfos of item for each abilitystep.
     *
     * @param array $items
     * @param array $models
     * @param array $abilitysteps
     *
     * @return array
     */
    public function get_fisherinfos_of_items(array $items, array $models, array $abilitysteps): array {
        $fisherinfos = [];
        foreach ($items as $item) {
            // We can not calculate the fisher information for items without a model.
            if (!$item->model) {
                continue;
            }
            $model = model_model::get_instance($item->model);
            foreach ($model::get_parameter_names() as $paramname) {
                $params[$paramname] = floatval($item->$paramname);
            }
            foreach ($abilitysteps as $ability) {
                $fisherinformation = $model->fisher_info(
                    ['ability' => $ability],
                    $params
                );
                $stringkey = strval($ability);

                if (!isset($fisherinfos[$stringkey])) {
                    $fisherinfos[$stringkey] = $fisherinformation;
                } else {
                    $fisherinfos[$stringkey] += $fisherinformation;
                }
            }

        }
        return $fisherinfos;
    }

    /**
     * Round float to steps as defined.
     *
     * @param float $number
     * @param float $step
     * @param float $interval
     *
     * @return float
     */
    public function round_to_customsteps(float $number, float $step, float $interval): float {
        $roundedvalue = round($number / $step) * $step;

        // Exclude rounding to steps defined in $interval.
        if ($roundedvalue - floor($roundedvalue) == $interval) {
            $roundedvalue = floor($roundedvalue) + $step;
        }

        return $roundedvalue;
    }

    /**
     * Scale values of testinfo (sum of fisherinfos) for better display in chart.
     *
     * @param array $fisherinfos
     * @param array $attemptscounter
     *
     * @return array
     */
    public function scalevalues($fisherinfos, $attemptscounter) {
        // Find the maximum values in arrays.
        $maxattempts = max($attemptscounter);
        $maxfisherinfo = max($fisherinfos);

        // Avoid division by zero.
        if ($maxfisherinfo == 0 || $maxattempts == 0) {
            return $fisherinfos;
        }

        $scalingfactor = $maxattempts / $maxfisherinfo;

        // Scale the values in $fisherinfos based on the scaling factor.
        foreach ($fisherinfos as &$value) {
            $value *= $scalingfactor;
        }
        return $fisherinfos;
    }

    /**
     * Return value to define range of time average.
     *
     * @param int $beginningoftimerange
     * @param int $endtime
     *
     * @return int
     *
     */
    public static function get_timerange_for_attempts(int $beginningoftimerange, int $endtime) {
        $differenceindays = ($endtime - $beginningoftimerange) / (60 * 60 * 24);
        $range = LOCAL_CATQUIZ_TIMERANGE_DAY;

        if ($differenceindays <= 30) {
            $range = LOCAL_CATQUIZ_TIMERANGE_DAY;
        } else if ($differenceindays > 30 && $differenceindays <= 183) {
            $range = LOCAL_CATQUIZ_TIMERANGE_WEEK;
        } else if ($differenceindays > 183 && $differenceindays <= 730) {
            $range = LOCAL_CATQUIZ_TIMERANGE_MONTH;
        } else {
            $range = LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR;
        }

         return $range;
    }

    /**
     * Returns an array of personabilities, indexed by timerange (day, week, ...).
     *
     * @param array $attempts
     * @param int $scaleid
     * @param int $timerange
     * @param bool $allowempty If set to yes, missing abilities are returned as null.
     *
     * @return array
     *
     */
    public static function order_attempts_by_timerange(
        array $attempts,
        int $scaleid,
        int $timerange,
        bool $allowempty = false
    ) {
        $attemptsbytimerange = [];

        // Create new array with endtime and sort. Create entry for each day.
        foreach ($attempts as $attempt) {
            $data = json_decode($attempt->json);
            if (empty($attempt->endtime)) {
                continue;
            }
            $datestring = self::return_datestring_label($timerange, $attempt->endtime);

            if (!empty($data->personabilities->$scaleid) || $allowempty) {
                if (!isset($attemptsbytimerange[$datestring])) {
                    $attemptsbytimerange[$datestring] = [];
                }
                $attemptsbytimerange[$datestring][] = $data->personabilities->$scaleid ?? null;
            }
        }
        return $attemptsbytimerange;
    }


    /**
     * Returns the label for the given date according to format defined in timerange constant.
     *
     * @param int $timerange
     * @param int $timestamp
     *
     * @return string
     *
     */
    public static function return_datestring_label(int $timerange, int $timestamp): string {
        switch ($timerange) {
            case LOCAL_CATQUIZ_TIMERANGE_DAY:
                $dateformat = '%d.%m.%Y';
                $stringfordate = 'day';
                break;
            case LOCAL_CATQUIZ_TIMERANGE_WEEK:
                $dateformat = '%W';
                $stringfordate = 'week';
                break;
            case LOCAL_CATQUIZ_TIMERANGE_MONTH:
                $dateformat = '%m';
                $stringfordate = 'month';
                break;
            case LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR:
                $dateformat = '%m';
                $year = '%Y';
                $stringfordate = 'quarter';
                break;
        }

        $date = userdate($timestamp, $dateformat);

        if ($timerange === LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR) {
            $date = ceil($date / 3); // Divides the number of the month (4 for april) in order to get the quarter.
            $year = userdate($timestamp, $year);
            return get_string(
                'stringdate:quarter',
                'local_catquiz',
                [
                    'q' => $date,
                    'y' => $year,
                ]);
        } else if ($timerange === LOCAL_CATQUIZ_TIMERANGE_MONTH) {
            return get_string('stringdate:month:' . $date, 'local_catquiz');
        } else {
            return get_string('stringdate:' . $stringfordate, 'local_catquiz', $date);
        }
    }

    /**
     * Return keys for all moments in defined timerange.
     *
     * @param int $timerange
     * @param array $beginningandendofrange
     *
     * @return array
     *
     */
    public static function get_timerangekeys($timerange, $beginningandendofrange) {
        $result = [];
        $starttimestamp = $beginningandendofrange[0];
        $endtimestamp = $beginningandendofrange[1];
        $lastkey = self::return_datestring_label($timerange, $endtimestamp);

        $currenttimestamp = $starttimestamp;
        do {
            $key = self::return_datestring_label($timerange, $currenttimestamp);
            $result[$key] = $key;
            $currenttimestamp = strtotime('+1 day', $currenttimestamp);
        } while ($key != $lastkey);

        return $result;
    }

    /**
     * Returns the 1-based range index of an ability
     *
     * If the value is outside the range, returns null.
     *
     * @param stdClass $quizsettings
     * @param int $scaleid
     * @param float $value
     * @return ?int
     */
    public static function get_range_of_value(stdClass $quizsettings, int $scaleid, float $value): ?int {
        // Get the range of the selected value.
        $i = 0;
        do {
            $i++;
            $ranglow = sprintf('feedback_scaleid_limit_lower_%d_%d', $scaleid, $i);
            $rangup = sprintf('feedback_scaleid_limit_upper_%d_%d', $scaleid, $i);

        } while (
            !($quizsettings->$ranglow <= $value && $quizsettings->$rangup >= $value)
            && $i <= $quizsettings->numberoffeedbackoptionsselect
        );
        if ($i > $quizsettings->numberoffeedbackoptionsselect) {
            return null;
        }

        return $i;
    }

    /**
     * Returns the bin number for a given value
     *
     * @param float $value
     * @param float $classwidth
     * @return int
     */
    public static function get_histogram_bin($value, $classwidth): int {
        if ($value == 0) {
            return 0;
        }
        return intval(ceil($value / $classwidth) - 1);
    }
}
