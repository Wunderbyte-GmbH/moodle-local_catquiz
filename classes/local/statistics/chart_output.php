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

use core\chart_base;
use html_writer;

/**
 * Ability histograms that are drawn whatever the item pool (issue #150).
 *
 * The bars of an ability histogram are the ability steps of the scale; the test information - the summed
 * Fisher information of the items with parameters - is an optional line over them. Both histograms took their
 * labels from that line: without an item with parameters there were no labels, and the line had no value.
 * core/chart_series refuses a series without values ("Invalid values received for series."), and core/chart_builder
 * creates the chart where that error is not caught: the page kept an empty chart area.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class chart_output {
    /**
     * The labels of the bars: the ability steps.
     *
     * @param float[] $abilitysteps
     * @return string[]
     */
    public static function step_labels(array $abilitysteps): array {
        return array_map('strval', array_values($abilitysteps));
    }

    /**
     * People per ability step: a value counts for the step whose bar (step - halfwidth, step + halfwidth] holds
     * it; the first bar also holds its lower edge, the minimum of the scale. Values outside the bars count for
     * none, and so does a value that is no finite number.
     *
     * The feedback rounded to a grid of a half bar width and kept the steps only: a value next to an integer
     * fell between two bars and was not counted, while the statistics counted it.
     *
     * @param float[] $abilitysteps Centres of the bars, ascending.
     * @param float[] $values One value per person.
     * @param float $halfwidth
     * @return int[] One count per step, in the order of the steps.
     */
    public static function counts_per_step(array $abilitysteps, array $values, float $halfwidth): array {
        $steps = array_values($abilitysteps);
        $counts = array_fill(0, count($steps), 0);
        foreach ($values as $value) {
            // A value that is no number counts for no bar (re-audit 23.10.2026): cast to float, null and text were 0.
            // INF and NAN need no check: no bar holds them.
            if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
                continue;
            }
            $value = (float) $value;
            foreach ($steps as $i => $step) {
                $lower = $step - $halfwidth;
                if (($value > $lower || ($i === 0 && $value == $lower)) && $value <= $step + $halfwidth) {
                    $counts[$i]++;
                    break;
                }
            }
        }
        return $counts;
    }

    /**
     * The test information at the ability steps, or null when it does not cover every step.
     *
     * A missing value is not invented: without an item with parameters - an empty pool, only pilot items,
     * parameters not calculated yet - the line is left out.
     *
     * @param array $fisherinfos Test information keyed by strval(step).
     * @param float[] $abilitysteps
     * @return float[]|null
     */
    public static function testinfo_at_steps(array $fisherinfos, array $abilitysteps): ?array {
        if (!$abilitysteps) {
            return null;
        }
        $values = [];
        foreach ($abilitysteps as $step) {
            $key = strval($step);
            $value = $fisherinfos[$key] ?? null;
            if (!is_numeric($value) || !is_finite((float) $value)) {
                return null;
            }
            $values[] = (float) $value;
        }
        return $values;
    }

    /**
     * Whether the browser can draw the chart: labels, and every series with one value per label - no series without
     * values, which core/chart_series refuses - each a finite number or null (JSON has no INF or NaN).
     *
     * @param chart_base $chart
     * @return bool
     */
    public static function is_consistent(chart_base $chart): bool {
        $labels = count($chart->get_labels());
        if ($labels === 0 || !$chart->get_series()) {
            return false;
        }
        foreach ($chart->get_series() as $series) {
            if ($series->get_count() !== $labels) {
                return false;
            }
            foreach ($series->get_values() as $value) {
                if ($value !== null && (!is_numeric($value) || !is_finite((float) $value))) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * A notice in place of a chart.
     *
     * @param string $text
     * @param string $kind info or warning
     * @return string
     */
    public static function notice(string $text, string $kind = 'info'): string {
        return html_writer::div(s($text), "alert alert-$kind", ['data-region' => 'local_catquiz-chart-notice']);
    }

    /**
     * Renders a chart; a chart without labels gets the no-data text, an inconsistent one a notice - neither is
     * sent to the browser, which would leave an empty area. The template is core/chart with a message for a
     * chart the browser still refuses.
     *
     * @param chart_base $chart
     * @param bool $withtable Whether to add the data table.
     * @param string $nodata Text for a chart without data.
     * @return string HTML
     */
    public static function render(chart_base $chart, bool $withtable, string $nodata): string {
        global $OUTPUT;
        if (!$chart->get_labels() || !$chart->get_series()) {
            return self::notice($nodata);
        }
        if (!self::is_consistent($chart)) {
            $counts = array_map(fn($series) => $series->get_count(), $chart->get_series());
            debugging(
                'local_catquiz: chart not drawn, ' . count($chart->get_labels()) . ' labels and series of '
                    . implode('/', $counts) . ' values.',
                DEBUG_DEVELOPER
            );
            return self::notice(get_string('chartnotdrawn', 'local_catquiz'), 'warning');
        }
        return $OUTPUT->render_from_template('local_catquiz/chart', (object) [
            'chartdata' => json_encode($chart),
            'withtable' => $withtable,
        ]);
    }
}
