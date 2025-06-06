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

use context_course;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_model;
use local_catquiz\output\attemptfeedback;
use LogicException;
use moodle_database;
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
     * The precision to use when rounding numbers.
     *
     * @var int
     */
    const PRECISION = 2;

    /**
     * Get feedback data for attempts
     *
     * @param array $args Arguments containing courseid, numberofattempts, instanceid.
     * @param context_course $context Current course context.
     * @param stdClass $USER Global user object.
     * @param stdClass $COURSE Global course object
     * @param moodle_database $DB Global DB object
     * @param stdClass $CFG Global config object
     * @return array Feedback data structure or error message
     */
    public static function get_feedback_data(
        array $args,
        context_course $context,
        stdClass $USER,
        stdClass $COURSE,
        moodle_database $DB,
        stdClass $CFG
    ) {
        // Check capability.
        $capability = has_capability('local/catquiz:view_users_feedback', $context);
        $userid = !$capability ? $USER->id : null;

        // Get course ID.
        $currentcourseid = 0;
        if (isset($COURSE) && !empty($COURSE->id) && $COURSE->id > 1) {
            $currentcourseid = $COURSE->id;
        }
        $courseid = $args['courseid'] ?? $currentcourseid;
        $attemptid = $args['attemptid'] ?? 0;

        // Get attempt records.
        $records = catquiz::return_data_from_attemptstable(
            intval($args['numberofattempts'] ?? 1),
            intval($args['instanceid'] ?? 0),
            intval($courseid),
            intval($attemptid),
            intval($userid ?? -1)
        );

        if (!$records) {
            return ['error' => get_string('attemptfeedbacknotyetavailable', 'local_catquiz')];
        }

        $output = [
            'attempt' => [],
        ];

        foreach ($records as $record) {
            if (!$attemptdata = json_decode($record->json)) {
                if ($CFG->debug > 0) {
                    throw new \moodle_exception(sprintf('Can not read attempt data of attempt %d', $record->attemptid));
                } else {
                    continue;
                }
            }
            $strategyid = $attemptdata->teststrategy;
            $feedbacksettings = new feedbacksettings($strategyid);

            $attemptfeedback = new attemptfeedback($record->attemptid, $record->contextid, $feedbacksettings);
            try {
                $feedback = $attemptfeedback->get_feedback_for_attempt($record->json, $record->debug_info) ?? "";
            } catch (\Throwable $t) {
                $feedback = get_string('attemptfeedbacknotavailable', 'local_catquiz');
            }

            $timestamp = !empty($record->endtime) ? intval($record->endtime) : intval($record->timemodified);
            $timeofattempt = userdate($timestamp, get_string('strftimedatetime', 'core_langconfig'));

            if ($record->userid == $USER->id) {
                $headerstring = get_string(
                    'ownfeedbacksheader',
                    'local_catquiz',
                    $timeofattempt
                );
            } else if (isset($record->userid)) {
                $userrecord = $DB->get_record('user', ['id' => $record->userid], 'firstname, lastname', IGNORE_MISSING);

                $headerstring = get_string(
                    'userfeedbacksheader',
                    'local_catquiz',
                    [
                        'attemptid' => $record->attemptid,
                        'time' => $timeofattempt,
                        'firstname' => $userrecord->firstname,
                        'lastname' => $userrecord->lastname,
                        'userid' => $record->userid,
                    ]
                );
            } else {
                $headerstring = "";
            }

            $data = [
                'feedback' => $feedback,
                'header' => $headerstring,
                'attemptid' => $record->attemptid,
                'active' => empty($output['attempt']) ? true : false,
            ];
            $output['attempt'][] = $data;
        }

        return $output;
    }

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
            $itemparam = model_item_param::from_record($item);
            $model = model_model::get_instance($item->model);
            foreach ($abilitysteps as $ability) {
                $fisherinformation = $model->fisher_info(
                    ['ability' => $ability],
                    $itemparam->get_params_array()
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

        if ($differenceindays <= 30) {
            return LOCAL_CATQUIZ_TIMERANGE_DAY;
        } else if ($differenceindays <= 183) {
            return LOCAL_CATQUIZ_TIMERANGE_WEEK;
        } else if ($differenceindays <= 730) {
            return LOCAL_CATQUIZ_TIMERANGE_MONTH;
        } else {
            return LOCAL_CATQUIZ_TIMERANGE_QUARTEROFYEAR;
        }
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
                ]
            );
        } else if ($timerange === LOCAL_CATQUIZ_TIMERANGE_MONTH) {
            $year = userdate($timestamp, '%y');
            return get_string('statistics_month_' . $date, 'local_catquiz', ['y' => $year]);
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
     * @param ?stdClass $quizsettings
     * @param int $scaleid
     * @param ?float $value
     * @return ?int
     */
    public static function get_range_of_value(?stdClass $quizsettings, int $scaleid, ?float $value): ?int {
        if (!$quizsettings) {
            return null;
        }
        if ($value === null) {
            return null;
        }

        // If the value is outside the defined range, return null.
        $lowest = sprintf('feedback_scaleid_limit_lower_%d_1', $scaleid);
        $highest = sprintf('feedback_scaleid_limit_upper_%d_%d', $scaleid, $quizsettings->numberoffeedbackoptionsselect);
        if (!isset($quizsettings->$lowest)
            || !isset($quizsettings->$highest)
        ) {
            return null;
        }
        if ($value < $quizsettings->$lowest || $value > $quizsettings->$highest) {
            return null;
        }
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

    /**
     * Puts the given string in localized quotes
     *
     * E.g., in German, the left quote is a lower quote whereas in English its an upper quote.
     *
     * @param string $string
     * @return string
     */
    public static function add_quotes(string $string) {
        $leftquote = get_string('catquiz_left_quote', 'local_catquiz');
        $rightquote = get_string('catquiz_right_quote', 'local_catquiz');
        return sprintf('%s%s%s', $leftquote, $string, $rightquote);
    }

    /**
     * Write string to define color gradiant bar.
     *
     * @param object $quizsettings
     * @param string|int $catscaleid
     * @param bool $customlabels Use the labels defined in the settings instead of default labels
     * @param bool $withuncalculated Include the color and description for the "not yet calculated" range
     * @return array
     *
     */
    public static function get_colorbarlegend($quizsettings, $catscaleid, $customlabels = true, $withuncalculated = false): array {
        if (!$quizsettings) {
            return [];
        }
        // We collect the feedbackdata only for the parentscale.
        $feedbacks = [];
        $numberoffeedbackoptions = intval($quizsettings->numberoffeedbackoptionsselect);
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);

        for ($j = 1; $j <= $numberoffeedbackoptions; $j++) {
            $colorkey = 'wb_colourpicker_' . $catscaleid . '_' . $j;
            $feedbacktextkey = 'feedbacklegend_scaleid_' . $catscaleid . '_' . $j;
            $lowerlimitkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_" . $j;
            $upperlimitkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $j;

            // It would probably be a good idea to define a class for $quizsettings.
            // That way, we could more easily check if settings are valid or include a given CAT scale.
            if (
                !isset($quizsettings->$upperlimitkey)
                || !isset($quizsettings->$lowerlimitkey)
            ) {
                throw new LogicException(
                    'Trying to get feedback ranges for a CAT scale that is not configured in the given quizsettings'
                );
            }

            $feedbackrangestring = get_string(
                'subfeedbackrange',
                'local_catquiz',
                [
                    'upperlimit' => self::localize_float($quizsettings->$upperlimitkey),
                    'lowerlimit' => self::localize_float($quizsettings->$lowerlimitkey),
                ]);

            $text = get_string('feedbackrange', 'local_catquiz', $j);
            if ($customlabels && property_exists($quizsettings, $feedbacktextkey)) {
                $text = $quizsettings->$feedbacktextkey;
            }

            $colorname = $quizsettings->$colorkey;
            $colorvalue = $colorarray[$colorname];

            $feedbacks[] = [
                'subcolorcode' => $colorvalue,
                'subfeedbacktext' => $text,
                'subfeedbackrange' => $feedbackrangestring,
            ];
        }

        if ($withuncalculated) {
            $feedbacks[] = [
                'subcolorcode' => LOCAL_CATQUIZ_DEFAULT_GREY,
                'subfeedbacktext' => get_string('noresult', 'local_catquiz'),
                'subfeedbackrange' => '',
            ];
        }

        return $feedbacks;
    }

    /**
     * Returns a localzed, rounded number as string.
     *
     * @param float $number
     * @return string
     */
    public static function localize_float(float $number): string {
        $locale = localeconv();
        return number_format(
            $number,
            self::PRECISION,
            $locale['decimal_point'],
            $locale['thousands_sep']);
    }
}
