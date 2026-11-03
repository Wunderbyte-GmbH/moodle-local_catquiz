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
 * Measures one person-ability fit: time, peak memory and result (issue #145).
 *
 * Synthetic items and answers, no database rows: the numbers belong to the estimator alone, apart
 * from pool, progress and feedback. Run it before and after a change on the same machine.
 *
 * Usage: php local/catquiz/cli/measure_ability_fit.php [--model=raschbirnbaum] [--sizes=10,50,100,150]
 *        [--pattern=mixed|correct|wrong] [--repeat=5]
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\catcalc;
use local_catquiz\local\model\model_item_param;
use local_catquiz\local\model\model_item_param_list;
use local_catquiz\local\model\model_item_response;
use local_catquiz\local\model\model_person_param;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params([
    'model' => 'raschbirnbaum',
    'sizes' => '10,50,100,150',
    'pattern' => 'mixed',
    'repeat' => 5,
    'help' => false,
], ['h' => 'help']);
if ($options['help']) {
    cli_writeln('Measures person-ability fits: --model, --sizes, --pattern=mixed|correct|wrong, --repeat.');
    exit(0);
}

$params = [
    'rasch' => fn($b) => ['difficulty' => $b],
    'raschbirnbaum' => fn($b) => ['difficulty' => $b, 'discrimination' => 1.3],
    'mixedraschbirnbaum' => fn($b) => ['difficulty' => $b, 'discrimination' => 1.3, 'guessing' => 0.2],
];
if (!isset($params[$options['model']])) {
    cli_error('Model must be one of ' . implode(', ', array_keys($params)));
}

cli_writeln(sprintf("%-6s %-8s %10s %12s %22s", 'items', 'pattern', 'ms/fit', 'peak KiB', 'ability'));
foreach (array_map('intval', explode(',', $options['sizes'])) as $n) {
    $person = new model_person_param('p', 1);
    $items = new model_item_param_list();
    $responses = [];
    for ($i = 0; $i < $n; $i++) {
        $item = new model_item_param('it' . $i, $options['model']);
        $item->set_parameters(($params[$options['model']])(-3.0 + 6.0 * $i / max(1, $n - 1)));
        $items->add($item);
        $fraction = match ($options['pattern']) {
            'correct' => 1.0,
            'wrong' => 0.0,
            default => (float) (($i * 7) % 3 !== 0),
        };
        $responses['it' . $i] = new model_item_response('it' . $i, $fraction, $person);
    }

    $ability = null;
    $time = 0.0;
    $peak = 0;
    for ($r = 0; $r < (int) $options['repeat']; $r++) {
        gc_collect_cycles();
        $base = memory_get_usage();
        memory_reset_peak_usage();
        $start = microtime(true);
        $ability = catcalc::estimate_person_ability($responses, $items, 0.0, 0.0, 1.0, -10.0, 10.0);
        $time += microtime(true) - $start;
        $peak = max($peak, memory_get_peak_usage() - $base);
    }
    cli_writeln(sprintf(
        "%-6d %-8s %10.2f %12.1f %22.17g",
        $n,
        $options['pattern'],
        1000 * $time / (int) $options['repeat'],
        $peak / 1024,
        $ability
    ));
}
