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
use local_catquiz\teststrategy\feedbacksettings;
use local_catquiz\catquiz;
use local_catquiz\catscale;
use local_catquiz\feedback\feedbackclass;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_model;
use local_catquiz\local\result\attempt_result;
use local_catquiz\local\result\scale_result;
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
     * Reduces per-attempt items to one value per person by a documented rule.
     *
     * Person-weighted analyses (histograms, cohort trajectories) and
     * the exports derived from them must use the same selection rule, so that a
     * person with several attempts contributes exactly one value. Items with a
     * null value are dropped. This is the single place that rule lives.
     *
     * @param array $items Each item is an object/array with keys/properties
     *                     'userid', 'endtime' and 'value'.
     * @param string $rule 'last' (latest by endtime, default), 'first' or 'best'.
     *
     * @return array Map of userid => value (float).
     */
    public static function reduce_to_one_value_per_person(array $items, string $rule = 'last'): array {
        $byuser = [];
        foreach ($items as $item) {
            $item = (object) $item;
            if (!isset($item->value) || $item->value === null) {
                continue;
            }
            $userid = (int) $item->userid;
            $endtime = (int) ($item->endtime ?? 0);
            $value = (float) $item->value;

            if (!isset($byuser[$userid])) {
                $byuser[$userid] = ['endtime' => $endtime, 'value' => $value];
                continue;
            }
            $current = $byuser[$userid];
            switch ($rule) {
                case 'first':
                    $take = $endtime < $current['endtime'];
                    break;
                case 'best':
                    $take = $value > $current['value'];
                    break;
                case 'last':
                default:
                    $take = $endtime >= $current['endtime'];
                    break;
            }
            if ($take) {
                $byuser[$userid] = ['endtime' => $endtime, 'value' => $value];
            }
        }
        return array_map(fn ($v) => $v['value'], $byuser);
    }

    /**
     * Returns the reportable scales from a list of person abilities.
     *
     * A scale is reportable when it is flagged toreport and is neither excluded
     * nor hidden. This is the single definition of "valid result" used by the
     * feedback assembly (issue #10).
     *
     * @param array $personabilities
     * @return array
     */
    public static function get_reportable_scales(array $personabilities): array {
        // The definition of a reportable/valid scale lives in the
        // central attempt_result_validator. Route through it so feedback,
        // completion and persistence all share one definition. The validator
        // reproduces the historical set (toreport, not excluded, not hidden).
        $result = attempt_result_validator::from_personabilities($personabilities);
        $reportableids = array_flip($result->get_reportable_scale_ids());

        return array_filter(
            $personabilities,
            fn ($a, $scaleid) => is_array($a) && isset($reportableids[(int) $scaleid]),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Whether a person-abilities list contains at least one reportable scale.
     *
     * @param array $personabilities
     * @return bool
     */
    public static function has_reportable_result(array $personabilities): bool {
        return attempt_result_validator::from_personabilities($personabilities)->has_reportable_result();
    }

    /**
     * Builds the authoritative attempt result for the feedback path.
     *
     * Issue #7 DoD 2: every feedback generator must judge a scale by the SAME
     * result object instead of re-implementing the gate over the raw flags
     * `toreport` / `excluded` / `hidden`. Those flags are ambiguous - notably
     * `excluded` is set both for a measurement problem (SE below the minimum) and
     * for a pure display decision (reporting checkbox off) - so every consumer had
     * to know which combination meant what.
     *
     * @param array $personabilities The per-scale abilities including error/flags.
     * @param array $feedbackdata The surrounding feedback data, used for the SE.
     *
     * @return attempt_result
     */
    public static function build_attempt_result(array $personabilities, array $feedbackdata = []): attempt_result {
        $sebyscale = [];
        foreach (($feedbackdata['se'] ?? []) as $scaleid => $se) {
            if (is_numeric($se)) {
                $sebyscale[(int) $scaleid] = (float) $se;
            }
        }

        $primaryscaleid = null;
        foreach ($personabilities as $scaleid => $entry) {
            if (is_array($entry) && !empty($entry['primary'])) {
                $primaryscaleid = (int) $scaleid;
                break;
            }
        }

        return attempt_result_validator::from_personabilities(
            $personabilities,
            $sebyscale,
            [],
            [],
            $primaryscaleid
        );
    }

    /**
     * Shows whether a scale may be displayed in the feedback.
     *
     * A scale is displayed when it is meant to be reported AND its measurement is
     * statistically sound. Both conditions come from the central result object, so
     * display and validity stay in step (issue #7).
     *
     * @param attempt_result $result
     * @param int $scaleid
     *
     * @return bool
     */
    public static function is_displayable(attempt_result $result, int $scaleid): bool {
        $scale = $result->get_scale_result($scaleid);
        if ($scale === null) {
            return false;
        }
        return $scale->reportable && $scale->statisticallyvalid;
    }

    /**
     * Whether a scale belongs in the feedback table shown to the participant.
     *
     * Deliberately not the same question as is_displayable(). That one requires
     * $scale->reportable, which is built as `$toreport && !$hidden && !$notreported`
     * - and $toreport is set by the strategy only for the scale it singles out.
     * inferlowestskillgap marks exactly one, so every other statistically sound
     * subscale was filtered out of the table although it had been measured properly.
     *
     * Three different ideas were being read off one flag:
     *
     *   statisticallyvalid  - the measurement can be used
     *   primary             - the strategy highlights this one scale
     *   reportable          - the scale may be shown at all
     *
     * Being primary is a reason to emphasise a scale, never a condition for showing
     * the others. What has to hold here is that the value was measured, is sound, is
     * not hidden, and reporting was not switched off in the quiz settings.
     *
     * Completion keeps the stricter rule; that is a decision about the test result,
     * not about what a participant may read.
     *
     * @param attempt_result $result
     * @param int $scaleid
     * @return bool
     */
    public static function is_feedback_eligible(attempt_result $result, int $scaleid): bool {
        $scale = $result->get_scale_result($scaleid);
        if ($scale === null) {
            return false;
        }

        if (!$scale->statisticallyvalid) {
            return false;
        }

        // Hidden and "reporting disabled" stay exclusions: both are explicit
        // decisions by whoever configured the test, unlike "not primary", which is an
        // outcome of the strategy.
        foreach (
            [
            scale_result::REASON_HIDDEN,
            scale_result::REASON_REPORTING_DISABLED,
            ] as $reason
        ) {
            if (in_array($reason, $scale->rejectionreasons, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Turns the machine readable rejection reasons into a user facing message.
     *
     * Issue #7 DoD 3: the displayed reason is derived from
     * scale_result::$rejectionreasons rather than from the legacy `error` arrays.
     * The interpolated detail values (thresholds, current values) still come from
     * the error array of the same scale, because the language strings use them.
     *
     * @param attempt_result $result
     * @param array $personabilities Used only for the interpolated detail values.
     *
     * @return string
     */
    public static function get_rejection_reason_string(attempt_result $result, array $personabilities): string {
        foreach ($result->get_scale_results() as $scaleid => $scale) {
            if ($scale->rejectionreasons === []) {
                continue;
            }
            $error = $personabilities[$scaleid]['error'] ?? [];

            foreach ($scale->rejectionreasons as $reason) {
                switch ($reason) {
                    case scale_result::REASON_ROOTONLY:
                        return get_string('error:rootonly', 'local_catquiz', $error['rootonly'] ?? null);
                    case scale_result::REASON_SE_MIN:
                        return get_string('error:semin', 'local_catquiz', $error['se'] ?? null);
                    case scale_result::REASON_SE_MAX:
                        return get_string('error:semax', 'local_catquiz', $error['se'] ?? null);
                    case scale_result::REASON_N_MIN:
                        return get_string('error:nminscale', 'local_catquiz', $error['nminscale'] ?? null);
                    case scale_result::REASON_FRACTION:
                        $fraction = $error['fraction']['fraction'] ?? null;
                        if ((string) $fraction === '1') {
                            return get_string('error:fraction1', 'local_catquiz');
                        }
                        if ((string) $fraction === '0') {
                            return get_string('error:fraction0', 'local_catquiz');
                        }
                        return get_string('noscalesfound', 'local_catquiz');
                    default:
                        // Reporting disabled, hidden, not primary and not measured
                        // are not measurement problems; keep looking for one.
                        continue 2;
                }
            }
        }

        return get_string('noscalesfound', 'local_catquiz');
    }

    /**
     * Maps an excluded scale's error to a human-readable rejection reason.
     *
     * Shared by customscalefeedback and by the central "no valid result" notice
     * (issue #10) so both surface the same reasons.
     *
     * @param array $personabilities
     * @return string
     */
    public static function get_exclusion_reason_string(array $personabilities): string {
        foreach ($personabilities as $personability) {
            $isexcluded = isset($personability['excluded'])
                || isset($personability[feedbacksettings::FIELD_NOTREPORTED]);
            if (!is_array($personability) || !$isexcluded || !isset($personability['error'])) {
                continue;
            }
            $errorcode = array_keys($personability['error'])[0];
            $errorarray = $personability['error'][$errorcode];

            switch ($errorcode) {
                case "rootonly": // Default string: the detail may be too complex for users.
                    return get_string('error:rootonly', 'local_catquiz', $errorarray);
                case "se": // Default string: the detail may be too complex for users.
                    if (isset($errorarray['semindefined'])) {
                        return get_string('error:semin', 'local_catquiz', $errorarray);
                    } else if (isset($errorarray['semaxdefined'])) {
                        return get_string('error:semax', 'local_catquiz', $errorarray);
                    }
                    return get_string('noscalesfound', 'local_catquiz', $errorarray);
                case "nminscale":
                    return get_string('error:nminscale', 'local_catquiz', $errorarray);
                case "fraction":
                    if ($errorarray['fraction'] == 1) {
                        return get_string('error:fraction1', 'local_catquiz');
                    } else if ($errorarray['fraction'] == 0) {
                        return get_string('error:fraction0', 'local_catquiz');
                    }
                    return get_string('noscalesfound', 'local_catquiz', $errorarray);
                default:
                    return get_string('noscalesfound', 'local_catquiz');
            }
        }
        return get_string('noscalesfound', 'local_catquiz');
    }

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
            // The strategy comes from the payload where present, otherwise from the
            // column. Attempts written before the field existed have it only in the
            // column; passing the missing value straight into the constructor raised
            // a TypeError and broke the whole course page, not just this one card.
            $strategyid = (int) ($attemptdata->teststrategy ?? $record->teststrategy ?? 0);
            if ($strategyid <= 0) {
                // Without a strategy there is nothing to build feedback from. Skipped
                // like an unreadable payload above, rather than failing the page.
                if ($CFG->debug > 0) {
                    debugging(
                        sprintf('Attempt %d has no test strategy, skipping.', $record->attemptid),
                        DEBUG_DEVELOPER
                    );
                }
                continue;
            }

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

                $firstname = '';
                $lastname = '';
                if ($userrecord) {
                    $firstname = $userrecord->firstname;
                    $lastname = $userrecord->lastname;
                }

                $headerstring = get_string(
                    'userfeedbacksheader',
                    'local_catquiz',
                    [
                        'attemptid' => $record->attemptid,
                        'time' => $timeofattempt,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
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
     * Locale-robust parse of a configured feedback range limit.
     *
     * The limits may arrive as native numbers (JSON) or as localised strings.
     * A German decimal comma ("1,5") must NOT be truncated by floatval()/(float)
     * to 1.0 - that shifted the colour bands and mis-coloured abilities (an
     * ability of 1.10 fell into the green band because the yellow/green boundary
     * had collapsed from 1.5 to 1). This helper normalises a decimal comma to a
     * dot before casting, so "1,5", "1.5" and the numeric 1.5 all yield 1.5.
     *
     * @param mixed $value
     * @return float
     */
    public static function parse_range_limit($value): float {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $string = trim((string) $value);
        if ($string === '') {
            return 0.0;
        }
        // A decimal comma is only a decimal separator when there is no dot too.
        if (strpos($string, ',') !== false && strpos($string, '.') === false) {
            $string = str_replace(',', '.', $string);
        }
        return (float) $string;
    }

    /**
     * Returns the color for a given person ability.
     *
     * @param array $quizsettings
     * @param float $personability
     * @param int $catscaleid
     *
     * @return string
     */
    public function get_color_for_personability(array $quizsettings, float $personability, int $catscaleid): string {
        $default = LOCAL_CATQUIZ_DEFAULT_GREY;
        $abilityrange = $this->get_ability_range((int) $catscaleid);
        if (
            !$quizsettings ||
            $personability < (float) $abilityrange['minscalevalue'] ||
            $personability > (float) $abilityrange['maxscalevalue']
        ) {
            return $default;
        }
        $numberoffeedbackoptions = intval($quizsettings['numberoffeedbackoptionsselect'])
            ?? LOCAL_CATQUIZ_MAX_SCALERANGE;
        $colorarray = feedbackclass::get_array_of_colors($numberoffeedbackoptions);

        for ($i = 1; $i <= $numberoffeedbackoptions; $i++) {
            $rangestartkey = "feedback_scaleid_limit_lower_" . $catscaleid . "_" . $i;
            $rangeendkey = "feedback_scaleid_limit_upper_" . $catscaleid . "_" . $i;
            $rangestart = self::parse_range_limit($quizsettings[$rangestartkey]);
            $rangeend = self::parse_range_limit($quizsettings[$rangeendkey]);

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
     * @param int $catscaleid
     *
     * @return array
     *
     */
    public function get_ability_range(int $catscaleid): array {
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
        // Nothing to scale when either side is empty, and max() must not be asked:
        // on an empty array PHP 8 raises "must contain at least one element", which
        // reaches the user as a fatal instead of an empty chart.
        //
        // A test range with no attempts at all is the ordinary case right after a
        // test is published, not an edge case.
        if (empty($attemptscounter) || empty($fisherinfos)) {
            return $fisherinfos;
        }

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
     * @param bool $perperson If true, reduce to one value per person and period.
     * @param string $rule Selection rule when reducing per person: last/first/best.
     *
     * @return array
     *
     */
    public static function order_attempts_by_timerange(
        array $attempts,
        int $scaleid,
        int $timerange,
        bool $allowempty = false,
        bool $perperson = false,
        string $rule = 'last'
    ) {
        $attemptsbytimerange = [];

        if ($perperson) {
            // For cohort trajectories, determine exactly one value per
            // person and period before aggregating, using the shared selection
            // rule. Buckets collect (userid, endtime, value) items per period.
            $itemsbytimerange = [];
            foreach ($attempts as $attempt) {
                if (empty($attempt->endtime)) {
                    continue;
                }
                $data = json_decode($attempt->json);
                // Keep a valid value of exactly 0.0 (null check).
                $hasvalue = isset($data->personabilities->$scaleid) && $data->personabilities->$scaleid !== null;
                if (!$hasvalue) {
                    continue;
                }
                $datestring = self::return_datestring_label($timerange, $attempt->endtime);
                $itemsbytimerange[$datestring][] = (object) [
                    'userid' => (int) ($attempt->userid ?? 0),
                    'endtime' => (int) $attempt->endtime,
                    'value' => (float) $data->personabilities->$scaleid,
                ];
            }
            foreach ($itemsbytimerange as $datestring => $items) {
                $attemptsbytimerange[$datestring] = array_values(
                    self::reduce_to_one_value_per_person($items, $rule)
                );
            }
            return $attemptsbytimerange;
        }

        // Create new array with endtime and sort. Create entry for each day.
        foreach ($attempts as $attempt) {
            $data = json_decode($attempt->json);
            if (empty($attempt->endtime)) {
                continue;
            }
            $datestring = self::return_datestring_label($timerange, $attempt->endtime);

            $hasvalue = isset($data->personabilities->$scaleid) && $data->personabilities->$scaleid !== null;
            if ($hasvalue || $allowempty) {
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
        // Delegate to the single half-open resolver so a score is
        // assigned to exactly one range everywhere.
        return self::get_feedback_range_index($quizsettings, $scaleid, $value);
    }

    /**
     * Returns the configured feedback ranges as plain lower/upper bounds.
     *
     * The histogram assigns the range in SQL now, so the boundaries have
     * to leave PHP as numbers. They are read here from the same settings and with
     * the same parser that get_feedback_range_index() uses, so the two cannot end up
     * describing different ranges.
     *
     * @param stdClass|array $quizsettings
     * @param int $scaleid
     * @return array List of ['lower' => float, 'upper' => float], in range order.
     */
    public static function get_feedback_range_bounds($quizsettings, int $scaleid): array {
        $settings = (array) $quizsettings;
        $n = (int) ($settings['numberoffeedbackoptionsselect'] ?? 0);
        if ($n < 1) {
            while (isset($settings[sprintf('feedback_scaleid_limit_lower_%d_%d', $scaleid, $n + 1)])) {
                $n++;
            }
        }

        $bounds = [];
        for ($j = 1; $j <= $n; $j++) {
            $lowerkey = sprintf('feedback_scaleid_limit_lower_%d_%d', $scaleid, $j);
            $upperkey = sprintf('feedback_scaleid_limit_upper_%d_%d', $scaleid, $j);
            if (!isset($settings[$lowerkey]) || !isset($settings[$upperkey])) {
                continue;
            }
            $bounds[] = [
                'lower' => self::parse_range_limit($settings[$lowerkey]),
                'upper' => self::parse_range_limit($settings[$upperkey]),
            ];
        }

        return $bounds;
    }

    /**
     * Returns the 1-based feedback range a value falls into, or null if none.
     *
     * Ranges are treated as half-open [lower, upper) so a value on a
     * shared boundary belongs to exactly one range; the topmost range is closed
     * [lower, upper] so the maximum value is still covered. A value outside every
     * configured range yields null.
     *
     * @param stdClass|array $quizsettings
     * @param int $scaleid
     * @param float|null $value
     * @return int|null
     */
    public static function get_feedback_range_index($quizsettings, int $scaleid, ?float $value): ?int {
        if ($value === null) {
            return null;
        }
        $settings = (array) $quizsettings;
        $n = (int) ($settings['numberoffeedbackoptionsselect'] ?? 0);
        if ($n < 1) {
            // Fall back to probing consecutive range keys (some callers pass the
            // raw settings without the option count).
            while (isset($settings[sprintf('feedback_scaleid_limit_lower_%d_%d', $scaleid, $n + 1)])) {
                $n++;
            }
        }
        if ($n < 1) {
            return null;
        }
        for ($j = 1; $j <= $n; $j++) {
            $lowerkey = sprintf('feedback_scaleid_limit_lower_%d_%d', $scaleid, $j);
            $upperkey = sprintf('feedback_scaleid_limit_upper_%d_%d', $scaleid, $j);
            if (!isset($settings[$lowerkey]) || !isset($settings[$upperkey])) {
                continue;
            }
            $lower = self::parse_range_limit($settings[$lowerkey]);
            $upper = self::parse_range_limit($settings[$upperkey]);
            if ($value < $lower) {
                continue;
            }
            // Top range is inclusive on the upper bound; all others half-open.
            if ($j < $n) {
                if ($value < $upper) {
                    return $j;
                }
            } else if ($value <= $upper) {
                return $j;
            }
        }
        return null;
    }

    /**
     * Returns the range only if the whole confidence interval falls into it.
     *
     * Issue #14 (measurement uncertainty, opt-in): a categorical feedback range
     * is only considered reliably reached when the confidence interval
     * [value - k*se, value + k*se] lies entirely within a single range. If the
     * interval straddles a range boundary the classification is uncertain and
     * null is returned, so the caller can show a neutral transition message
     * instead of a definite range feedback. With $k <= 0 or a null/zero standard
     * error this collapses to the plain point classification.
     *
     * @param stdClass|array $quizsettings
     * @param int $scaleid
     * @param float|null $value
     * @param float|null $se Standard error of the ability estimate.
     * @param float $k Confidence factor (e.g. 1.0). <= 0 disables the widening.
     *
     * @return int|null
     */
    public static function get_feedback_range_index_with_uncertainty(
        $quizsettings,
        int $scaleid,
        ?float $value,
        ?float $se,
        float $k
    ): ?int {
        if ($value === null) {
            return null;
        }
        // No uncertainty configured: behave exactly like the point resolver.
        if ($k <= 0.0 || $se === null || $se <= 0.0) {
            return self::get_feedback_range_index($quizsettings, $scaleid, $value);
        }
        $margin = $k * $se;
        $lowerindex = self::get_feedback_range_index($quizsettings, $scaleid, $value - $margin);
        $upperindex = self::get_feedback_range_index($quizsettings, $scaleid, $value + $margin);
        // Only a definite classification when both interval ends land in the very
        // same range (and neither falls outside the configured ranges).
        if ($lowerindex !== null && $lowerindex === $upperindex) {
            return $lowerindex;
        }
        return null;
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
                ]
            );

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
            $locale['thousands_sep']
        );
    }
}
