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
 * Shared library for the edge-case experiment and fixture generator.
 *
 * Development-only (export-ignored). Drives Newton / BFGS / GA-standalone /
 * GA->Newton over pathological IP geometries through the SAME catcalc machinery
 * and records the section-E metrics. See doc/session-013-changes.md.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\catcalc;
use local_catquiz\mathcat;
use local_catquiz\matrix;
use local_catquiz\local\model\model_model;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param;

/**
 * Sets a deterministic, wide trusted region so boundary behaviour is defined.
 *
 * @return void
 */
function edgecase_set_trusted_region(): void {
    foreach (['catmodel_grmgeneralized', 'catmodel_pcmgeneralized', 'catmodel_grm', 'catmodel_pcm'] as $p) {
        set_config('trusted_region_factor_sd_a', 3, $p);
        set_config('trusted_region_min_a', 0.01, $p);
        set_config('trusted_region_max_a', 10, $p);
        set_config('trusted_region_factor_max_b', 3, $p);
        set_config('trusted_region_min_b', -10, $p);
        set_config('trusted_region_max_b', 10, $p);
        set_config('trusted_region_placement_b', 0, $p);
        set_config('trusted_region_slope_b', 3, $p);
    }
}

/**
 * The edge-case catalog: fixture definitions per section D.
 *
 * @return array
 */
function edgecase_catalog(): array {
    $cases = [];

    // IP-5: near-zero discrimination (a -> 0). Only GGRM/GPCM (free a). Class W.
    foreach ([['grmgeneralized', 'difficulties'], ['pcmgeneralized', 'intercepts']] as [$m, $key]) {
        foreach (['005' => 0.05, '015' => 0.15, '030' => 0.30] as $tag => $a) {
            $cases[] = [
                'id' => "ip_{$m}_lowdiscrimination_{$tag}",
                'model' => $m, 'estimation' => 'ip', 'class' => 'W', 'seed' => 271828 + (int) $tag,
                'generator' => [
                    'truth' => [$key => ['0.0' => 0.0, '1.0' => 0.3], 'discrimination' => $a],
                    'n' => 500, 'ability' => 'spread',
                ],
                'expected' => 'weakly_identified',
            ];
        }
    }

    // IP-10: missing / empty polytomous category. GRM/GGRM/PCM/GPCM. Class W/N.
    $fivethresholds = ['0.00' => 0.0, '0.25' => -1.2, '0.50' => -0.3, '0.75' => 0.6, '1.00' => 1.4];
    $variants = [
        'missing_middle' => ['0.00' => 180, '0.25' => 170, '0.50' => 0, '0.75' => 80, '1.00' => 70],
        'missing_top'    => ['0.00' => 180, '0.25' => 170, '0.50' => 90, '0.75' => 60, '1.00' => 0],
        'missing_bottom' => ['0.00' => 0, '0.25' => 200, '0.50' => 130, '0.75' => 100, '1.00' => 70],
        'two_of_five'    => ['0.00' => 260, '0.25' => 0, '0.50' => 240, '0.75' => 0, '1.00' => 0],
    ];
    foreach (['grm', 'grmgeneralized', 'pcm', 'pcmgeneralized'] as $m) {
        $key = in_array($m, ['pcm', 'pcmgeneralized']) ? 'intercepts' : 'difficulties';
        foreach ($variants as $vtag => $counts) {
            // K2 fixed the NaN start thresholds and K3 fixed the reduced-structure
            // dimension mismatch, so the GRM-family missing-bottom case now
            // estimates like the other weakly identified missing-category cases.
            $cases[] = [
                'id' => "ip_{$m}_{$vtag}",
                'model' => $m, 'estimation' => 'ip',
                'class' => ($vtag === 'two_of_five') ? 'N' : 'W',
                'seed' => 314159,
                'generator' => [
                    'counts' => $counts,
                    'thresholds' => $fivethresholds,
                    'key' => $key,
                    'discrimination' => (in_array($m, ['grmgeneralized', 'pcmgeneralized']) ? 1.0 : null),
                    'ability' => 'spread',
                ],
                'expected' => ($vtag === 'two_of_five') ? 'not_identified' : 'weakly_identified',
            ];
        }
    }

    // IP-9: bimodal ability with gap ("unklare Mischverhaeltnisse"). GGRM. Class W.
    foreach (['90_10' => 0.90, '75_25' => 0.75, '50_50' => 0.50] as $tag => $share) {
        $cases[] = [
            'id' => "ip_grmgeneralized_abilitymixture_{$tag}",
            'model' => 'grmgeneralized', 'estimation' => 'ip', 'class' => 'W', 'seed' => 161803,
            'generator' => [
                'truth' => ['difficulties' => ['0.0' => 0.0, '1.0' => 0.0], 'discrimination' => 1.2],
                'n' => 400, 'ability' => 'bimodal', 'share' => $share, 'peaks' => [-2.0, 2.0],
            ],
            'expected' => 'weakly_identified',
        ];
    }

    return $cases;
}

/**
 * Builds model_item_response[] for a case (probability sampling or direct counts).
 *
 * @param model_model $model
 * @param array $case
 * @return array
 */
function edgecase_build_responses(model_model $model, array $case): array {
    $g = $case['generator'];
    mt_srand($case['seed']);

    if (isset($g['counts'])) {
        $responses = [];
        $total = array_sum($g['counts']);
        $i = 0;
        foreach ($g['counts'] as $fraction => $count) {
            for ($k = 0; $k < $count; $k++, $i++) {
                $ability = -3.0 + 6.0 * $i / max(1, $total - 1);
                $pp = new model_person_param((string) $i, 1);
                $pp->set_ability($ability);
                $responses[] = new model_item_response('item1', (float) $fraction, $pp);
            }
        }
        shuffle($responses);
        return $responses;
    }

    $truth = $g['truth'];
    $key = isset($truth['difficulties']) ? 'difficulties' : 'intercepts';
    $fractions = array_keys($truth[$key]);
    $n = $g['n'];
    $responses = [];
    for ($i = 0; $i < $n; $i++) {
        if (($g['ability'] ?? 'spread') === 'bimodal') {
            $share = $g['share'];
            $peaks = $g['peaks'];
            $ability = ((mt_rand() / mt_getrandmax()) < $share ? $peaks[0] : $peaks[1])
                + (mt_rand() / mt_getrandmax() - 0.5) * 0.2;
        } else {
            $ability = -3.0 + 6.0 * $i / ($n - 1);
        }
        $roll = mt_rand() / mt_getrandmax();
        $cum = 0.0;
        $resp = (float) end($fractions);
        foreach ($fractions as $f) {
            $cum += (float) $model::likelihood(['ability' => $ability], $truth, (float) $f);
            if ($roll <= $cum) {
                $resp = (float) $f;
                break;
            }
        }
        $pp = new model_person_param((string) $i, 1);
        $pp->set_ability($ability);
        $responses[] = new model_item_response('item1', $resp, $pp);
    }
    return $responses;
}

/**
 * Builds counting vector-callables for the optimisers.
 *
 * @param array $responses
 * @param model_model $model
 * @return array
 */
function edgecase_build_callables(array $responses, model_model $model): array {
    $startip = $model::get_start_ip($responses);
    $tkey = isset($startip['difficulties']) ? 'difficulties' : 'intercepts';
    $fractions = array_keys($startip[$tkey]);
    $z0 = $model::convert_ip_to_vector($startip);

    $obj = catcalc::build_itemparam_objective($responses, $model);
    $jac = catcalc::build_itemparam_jacobian($responses, $model);
    $hes = catcalc::build_itemparam_hessian($responses, $model);

    $counters = (object) ['nf' => 0, 'ng' => 0, 'nh' => 0];
    $tofrac = fn($v) => $model::convert_vector_to_ip($v, $fractions);
    $objv = function ($v) use ($obj, $tofrac, $counters) {
        $counters->nf++;
        return $obj($tofrac($v));
    };
    $jacv = function ($v) use ($jac, $tofrac, $counters) {
        $counters->ng++;
        return $jac($tofrac($v));
    };
    $hesv = function ($v) use ($hes, $tofrac, $counters) {
        $counters->nh++;
        return $hes($tofrac($v));
    };
    $trf = fn($v) => $model::convert_ip_to_vector($model::restrict_to_trusted_region($tofrac($v)));

    return [$objv, $jacv, $hesv, $trf, $z0, $fractions, $counters];
}

/**
 * Runs all four optimisers, returning per-method metrics.
 *
 * @param callable $objv
 * @param callable $jacv
 * @param callable $hesv
 * @param callable $trf
 * @param array $z0
 * @param object $counters
 * @return array
 */
function edgecase_run_all(callable $objv, callable $jacv, callable $hesv, callable $trf, array $z0, object $counters): array {
    $rawobj = function ($v) use ($objv, $counters) {
        $b = clone $counters;
        $r = $objv($v);
        $counters->nf = $b->nf;
        $counters->ng = $b->ng;
        $counters->nh = $b->nh;
        return $r;
    };
    $rawjac = function ($v) use ($jacv, $counters) {
        $b = clone $counters;
        $r = $jacv($v);
        $counters->nf = $b->nf;
        $counters->ng = $b->ng;
        $counters->nh = $b->nh;
        return $r;
    };

    $out = [];
    foreach (['newton', 'bfgs', 'ga', 'ga_newton'] as $m) {
        $counters->nf = $counters->ng = $counters->nh = 0;
        try {
            if ($m === 'newton') {
                $p = mathcat::newton_raphson_multi_stable($jacv, $hesv, $z0, 6, 50, $trf);
            } else if ($m === 'bfgs') {
                $p = mathcat::bfgs($objv, $jacv, $z0, 6, 100, $trf);
            } else if ($m === 'ga') {
                $p = mathcat::gradient_ascent($objv, $jacv, $z0, 6, 200, $trf);
            } else {
                $basin = mathcat::gradient_ascent($objv, $jacv, $z0, 4, 60, $trf);
                $p = mathcat::newton_raphson_multi_stable($jacv, $hesv, $basin, 6, 50, $trf);
            }
            $p = array_values($p);
            $out[$m] = [
                'params' => $p, 'L' => $rawobj($p), 'gradnorm' => matrix::max_absolute_value($rawjac($p)),
                'nf' => $counters->nf, 'ng' => $counters->ng, 'nh' => $counters->nh,
            ];
        } catch (\Throwable $e) {
            $out[$m] = [
                'params' => [], 'L' => NAN, 'gradnorm' => NAN,
                'nf' => $counters->nf, 'ng' => $counters->ng, 'nh' => $counters->nh,
                'error' => get_class($e) . ': ' . $e->getMessage(),
            ];
        }
    }
    return $out;
}
