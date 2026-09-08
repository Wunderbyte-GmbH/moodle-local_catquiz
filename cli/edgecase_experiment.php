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
 * Edge-case experiment harness (development-only, export-ignored).
 *
 * Usage: php cli/edgecase_experiment.php [fixture-id]
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/edgecase_lib.php');

use local_catquiz\local\model\model_model;

edgecase_set_trusted_region();

$onlyid = $argv[1] ?? null;

printf(
    "%-30s %-9s %-4s | %-9s %-10s %-11s %s\n",
    'fixture',
    'method',
    'dim',
    'L',
    '||grad||',
    'Nf/Ng/NH',
    'params'
);
echo str_repeat('-', 110) . "\n";

foreach (edgecase_catalog() as $case) {
    if ($onlyid && $case['id'] !== $onlyid) {
        continue;
    }
    $model = model_model::get_instance($case['model']);
    $responses = edgecase_build_responses($model, $case);

    $dist = [];
    foreach ($responses as $r) {
        $v = rtrim(rtrim(sprintf('%.2f', $r->get_response()), '0'), '.');
        $dist[$v] = ($dist[$v] ?? 0) + 1;
    }

    [$objv, $jacv, $hesv, $trf, $z0, $fractions, $counters] = edgecase_build_callables($responses, $model);
    $results = edgecase_run_all($objv, $jacv, $hesv, $trf, $z0, $counters);

    $bestl = -INF;
    foreach ($results as $res) {
        if (is_finite((float) $res['L'])) {
            $bestl = max($bestl, $res['L']);
        }
    }

    echo "# {$case['id']}  model={$case['model']} class={$case['class']} seed={$case['seed']} "
        . 'N=' . count($responses) . ' dist=' . json_encode($dist) . " expect={$case['expected']}\n";
    foreach ($results as $name => $res) {
        printf(
            "%-30s %-9s %-4d | %-9.3f %-10.2e %-11s %s  dL=%.2e\n",
            $case['id'],
            $name,
            count($z0),
            $res['L'],
            $res['gradnorm'],
            "{$res['nf']}/{$res['ng']}/{$res['nh']}",
            json_encode(array_map(fn($x) => round($x, 3), $res['params'])),
            $bestl - $res['L']
        );
    }
    echo "\n";
}
