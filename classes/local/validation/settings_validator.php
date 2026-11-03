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

namespace local_catquiz\local\validation;

use local_catquiz\catquiz_handler;
use local_catquiz\catscale;
use local_catquiz\teststrategy\context\loader\pilotquestions_loader;
use local_catquiz\teststrategy\info;
use local_catquiz\teststrategy\preselect_task\fisherinformation;
use stdClass;

/**
 * Whether the settings of a test, taken together, can produce a test (issue #60).
 *
 * The form checks one value or one pair of values at a time. This checks the combination - with the
 * runtime's own reading: the pool the selection loads (catscale::get_testitems), the items it plays
 * productively (pilotquestions_loader::ispilot), the per-scale maximum only for strategies whose selection
 * it limits, and the fisher information of the selection. A maximum of 0 is no limit.
 *
 * Findings are hard errors (no attempt can meet the settings: saving is refused, and an attempt does not
 * start), warnings (valid, but a criterion can never act) and diagnostics (depends on the answers; for
 * information only).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class settings_validator {
    /** @var string Deterministically unsatisfiable. */
    public const ERROR = 'error';

    /** @var string Valid, but a criterion can never take effect. */
    public const WARNING = 'warning';

    /** @var string Depends on the answers; reported, not judged. */
    public const DIAGNOSTIC = 'diagnostic';


    /** @var float[] Abilities the best case of an item's information is looked for at. */
    private const ABILITY_GRID = [-6.0, -5.0, -4.0, -3.0, -2.5, -2.0, -1.5, -1.0, -0.5, 0.0, 0.5, 1.0, 1.5, 2.0, 2.5, 3.0,
        4.0, 5.0, 6.0];

    /**
     * Validates the settings of a test.
     *
     * @param stdClass $settings As stored in local_catquiz_tests.json, or the submitted form as an object.
     * @return array[] Findings: level, code, field, a (values for the message), message.
     */
    public static function validate(stdClass $settings): array {
        $findings = [];
        $min = (int) ($settings->maxquestionsgroup->catquiz_minquestions ?? 0);
        $max = (int) ($settings->maxquestionsgroup->catquiz_maxquestions ?? 0);
        $minperscale = (int) ($settings->maxquestionsscalegroup->catquiz_minquestionspersubscale ?? 0);
        $maxperscale = (int) ($settings->maxquestionsscalegroup->catquiz_maxquestionspersubscale ?? 0);

        // Stage 1 for every save path: the form checks these itself, a template or an import does not.
        if ($max > 0 && $min > $max) {
            $findings[] = self::finding(self::ERROR, 'minabovemax', 'maxquestionsgroup', ['min' => $min, 'max' => $max]);
        }
        if ($maxperscale > 0 && $minperscale > $maxperscale) {
            $findings[] = self::finding(self::ERROR, 'minperscaleabovemax', 'maxquestionsscalegroup', [
                'min' => $minperscale, 'max' => $maxperscale,
            ]);
        }

        $findings = array_merge($findings, self::time_findings($settings));

        global $DB;
        $rootid = (int) ($settings->catquiz_catscales ?? 0);
        if ($rootid <= 0 || !$DB->record_exists('local_catquiz_catscales', ['id' => $rootid])) {
            return $findings;
        }
        $strategy = info::get_teststrategy((int) ($settings->catquiz_selectteststrategy ?? 0), false);
        $limitsperscale = $strategy ? $strategy->uses_per_scale_selection_limits() : true;
        $coverage = $strategy ? $strategy->requires_scale_coverage() : false;

        $selected = self::selected_subscales($settings);
        [$items, $pilotsonly] = self::productive_items($rootid, $selected);
        $perscale = [];
        foreach ($items as $item) {
            $perscale[(int) $item->catscaleid] = ($perscale[(int) $item->catscaleid] ?? 0) + 1;
        }

        if (!$items) {
            $findings[] = self::finding(self::ERROR, 'nousableitems', 'catquiz_catscales', [
                'scale' => self::scale_name($rootid),
            ]);
            return $findings;
        }
        if ($pilotsonly) {
            $findings[] = self::finding(self::WARNING, 'onlypilotitems', 'catquiz_catscales', [
                'scale' => self::scale_name($rootid), 'items' => count($items),
            ]);
        }

        // The largest number of productive questions a person can get - as the runtime counts.
        $effective = count($items);
        $reason = 'items';
        if ($max > 0 && $max < $effective) {
            $effective = $max;
            $reason = 'maximum';
        }
        if ($limitsperscale && $maxperscale > 0) {
            $capped = 0;
            foreach ($perscale as $count) {
                $capped += min($maxperscale, $count);
            }
            if ($capped < $effective) {
                $effective = $capped;
                $reason = 'perscale';
            }
        }
        if ($min > $effective) {
            $findings[] = self::finding(self::ERROR, 'minimumunreachable', 'maxquestionsgroup', [
                'min' => $min, 'effective' => $effective, 'items' => count($items), 'maxperscale' => $maxperscale,
                'reason' => get_string('settingsvalidator_reason_' . $reason, 'local_catquiz', ['maxperscale' => $maxperscale]),
            ]);
        }

        // A scale the selection has to cover needs candidates of its own subtree - and, with a minimum per scale,
        // that many: its results are measured on them. The runtime counts a question for the scale of its item
        // and every ancestor (progress::add_playedquestion()), one item per question, as in $perscale.
        if ($coverage) {
            $required = array_values(array_unique(array_merge([$rootid], $selected)));
            $demand = [];
            foreach ($required as $scaleid) {
                $descendants = catscale::get_subscale_ids($scaleid);
                $subtree = array_merge([$scaleid], $descendants);
                $candidates = array_sum(array_intersect_key($perscale, array_flip($subtree)));
                if ($candidates === 0) {
                    $findings[] = self::finding(self::ERROR, 'scalewithoutitems', 'catquiz_catscales', [
                        'scale' => self::scale_name($scaleid),
                    ]);
                    continue;
                }
                if ($minperscale > 0 && $candidates < $minperscale) {
                    $findings[] = self::finding(self::ERROR, 'scalebelowminimum', 'maxquestionsscalegroup', [
                        'scale' => self::scale_name($scaleid), 'items' => $candidates, 'min' => $minperscale,
                    ]);
                }
                // The scales at the bottom of what has to be covered: their questions count for those above.
                if (!array_intersect(array_map('intval', $descendants), $required)) {
                    $demand[$scaleid] = min($minperscale, $candidates);
                }
            }
            // ALLSUBS: every covered scale needs its minimum within the maximum of the test (issue #60, example 2).
            $needed = array_sum($demand);
            if ($minperscale > 0 && $max > 0 && $needed > $max) {
                $findings[] = self::finding(self::ERROR, 'coveragebudget', 'maxquestionsgroup', [
                    'needed' => $needed, 'max' => $max, 'scales' => count($demand), 'min' => $minperscale,
                ]);
            }
        }

        // Pilot items take places of the usage: the number of questions to expect for the productive minimum.
        $pilotratio = !empty($settings->catquiz_includepilotquestions) ? (float) ($settings->catquiz_pilotratio ?? 0) : 0.0;
        if ($pilotratio > 0 && $pilotratio < 100 && $min > 0 && !$pilotsonly) {
            $findings[] = self::finding(self::DIAGNOSTIC, 'pilotexpectation', 'catquiz_pilotratio', [
                'expected' => (int) ceil($min / (1 - $pilotratio / 100)), 'min' => $min, 'ratio' => format_float($pilotratio, 0),
            ]);
        }

        $findings = array_merge($findings, self::precision_findings($settings, $items, $effective));
        return $findings;
    }

    /**
     * The hard errors that depend on the settings alone - not on the item pool, which may be filled after the
     * test is configured: what every save path refuses (issue #60, programmatic save paths).
     *
     * @param stdClass $settings
     * @return array[] Error findings.
     */
    public static function structural_errors(stdClass $settings): array {
        $min = (int) ($settings->maxquestionsgroup->catquiz_minquestions ?? 0);
        $max = (int) ($settings->maxquestionsgroup->catquiz_maxquestions ?? 0);
        $minperscale = (int) ($settings->maxquestionsscalegroup->catquiz_minquestionspersubscale ?? 0);
        $maxperscale = (int) ($settings->maxquestionsscalegroup->catquiz_maxquestionspersubscale ?? 0);
        $findings = [];
        if ($max > 0 && $min > $max) {
            $findings[] = self::finding(self::ERROR, 'minabovemax', 'maxquestionsgroup', ['min' => $min, 'max' => $max]);
        }
        if ($maxperscale > 0 && $minperscale > $maxperscale) {
            $findings[] = self::finding(self::ERROR, 'minperscaleabovemax', 'maxquestionsscalegroup', [
                'min' => $minperscale, 'max' => $maxperscale,
            ]);
        }
        return array_merge($findings, self::errors(self::time_findings($settings)));
    }

    /**
     * The hard errors among findings.
     *
     * @param array[] $findings
     * @return array[]
     */
    public static function errors(array $findings): array {
        return array_values(array_filter($findings, fn($f) => $f['level'] === self::ERROR));
    }

    /**
     * The submitted activity form as settings - the shape the json is stored in.
     *
     * @param array $data
     * @return stdClass
     */
    public static function from_form(array $data): stdClass {
        return json_decode(json_encode($data)) ?: new stdClass();
    }

    /**
     * Time limits: switched on with nothing set is an error; a question limit that is not shorter than the
     * attempt limit can never act on its own.
     *
     * @param stdClass $settings
     * @return array[]
     */
    private static function time_findings(stdClass $settings): array {
        $findings = [];
        $limits = catquiz_handler::time_limits($settings);
        if (!empty($settings->catquiz_includetimelimit) && $limits['attempt'] === null && $limits['item'] === null) {
            $findings[] = self::finding(self::ERROR, 'timelimitempty', 'catquiz_timelimitgroup', []);
        }
        if ($limits['attempt'] !== null && $limits['item'] !== null && $limits['item'] >= $limits['attempt']) {
            $findings[] = self::finding(self::WARNING, 'itemtimenotshorter', 'catquiz_timelimitgroup', [
                'item' => format_time($limits['item']), 'attempt' => format_time($limits['attempt']),
            ]);
        }
        $min = (int) ($settings->maxquestionsgroup->catquiz_minquestions ?? 0);
        if ($limits['attempt'] !== null && $min > 0) {
            $findings[] = self::finding(self::DIAGNOSTIC, 'timeperquestion', 'catquiz_timelimitgroup', [
                'time' => format_time((int) floor($limits['attempt'] / $min)), 'min' => $min,
            ]);
        }
        return $findings;
    }

    /**
     * Whether the target precision is reachable at all within the questions a person can get: the best case
     * of every item's information, as the selection computes it, for the most informative items.
     *
     * @param stdClass $settings
     * @param stdClass[] $items Productive items.
     * @param int $effective The number of questions a person can get at most.
     * @return array[]
     */
    private static function precision_findings(stdClass $settings, array $items, int $effective): array {
        $semin = (float) ($settings->catquiz_standarderrorgroup->catquiz_standarderror_min ?? 0);
        if ($semin <= 0 || $effective <= 0) {
            return [];
        }
        $fisher = new fisherinformation();
        $best = [];
        foreach ($items as $item) {
            $value = 0.0;
            foreach (self::ABILITY_GRID as $ability) {
                try {
                    $info = $fisher->get_fisherinformation($item, $ability);
                } catch (\Throwable $e) {
                    $info = null;
                }
                if (is_float($info) && is_finite($info) && $info > $value) {
                    $value = $info;
                }
            }
            $best[] = $value;
        }
        rsort($best);
        $total = array_sum(array_slice($best, 0, $effective));
        $bestse = $total > 0 ? 1 / sqrt($total) : INF;
        if ($bestse > $semin) {
            return [self::finding(self::WARNING, 'precisionunreachable', 'catquiz_standarderrorgroup', [
                'semin' => format_float($semin, 3), 'best' => is_finite($bestse) ? format_float($bestse, 3) : '∞',
                'questions' => $effective,
            ])];
        }
        return [];
    }

    /**
     * The productive items of the runtime pool: what the selection plays productively, pilots and unusable
     * items left out. Pilots never count as productive capacity while there is a productive item.
     *
     * A pool of pilots only - an item bank before its calibration - is decided explicitly (issue #60): the
     * runtime plays it (maybe_return_pilot narrows to productive items only when some are left), and
     * refusing it would leave a new item bank without any way to collect the answers its calibration needs.
     * Its items are then the capacity, and the finding "onlypilotitems" says that the test measures nothing
     * yet. Nothing is reinterpreted silently.
     *
     * @param int $rootid
     * @param int[] $selected
     * @return array [stdClass[] items, bool whether they are pilots only]
     */
    private static function productive_items(int $rootid, array $selected): array {
        $contextid = catscale::get_context_id($rootid);
        $pool = (new catscale($rootid))->get_testitems($contextid, true, null, $selected);
        global $CFG;
        require_once($CFG->dirroot . '/local/catquiz/lib.php');
        $calculated = array_filter($pool, fn($item) => $item->model !== null);
        $loader = new pilotquestions_loader();
        $productive = array_filter(
            $calculated,
            fn($item) => !$loader->ispilot(clone $item, LOCAL_CATQUIZ_THRESHOLD_DEFAULT)
        );
        if ($productive || !$calculated) {
            return [$productive, false];
        }
        return [$calculated, true];
    }

    /**
     * The subscales chosen for the test, read as the runtime reads them.
     *
     * @param stdClass $settings
     * @return int[]
     */
    private static function selected_subscales(stdClass $settings): array {
        $selected = [];
        foreach ((array) $settings as $key => $value) {
            if (preg_match('/^catquiz_subscalecheckbox_(\d+)$/', (string) $key, $m) && $value == '1') {
                $selected[] = (int) $m[1];
            }
        }
        return $selected;
    }

    /**
     * The name of a scale, or its id when it is gone.
     *
     * @param int $scaleid
     * @return string
     */
    private static function scale_name(int $scaleid): string {
        global $DB;
        $name = $DB->get_field('local_catquiz_catscales', 'name', ['id' => $scaleid]);
        return $name !== false ? format_string($name) : (string) $scaleid;
    }

    /**
     * A finding with its translated message.
     *
     * @param string $level
     * @param string $code
     * @param string $field The form element the finding belongs to.
     * @param array $a
     * @return array
     */
    private static function finding(string $level, string $code, string $field, array $a): array {
        return [
            'level' => $level,
            'code' => $code,
            'field' => $field,
            'a' => $a,
            'message' => get_string('settingsvalidator_' . $code, 'local_catquiz', (object) $a),
        ];
    }
}
