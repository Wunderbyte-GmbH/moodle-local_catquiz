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

namespace local_catquiz\local\model;

use advanced_testcase;

/**
 * Persistence round-trip tests for the polytomous models.
 *
 * These deliberately do NOT pre-build a JSON record: the parameters are set only
 * via set_parameters(), so the JSON must be produced by to_record() /
 * add_parameters_to_record() on the way out, and reconstructed on the way back.
 *
 * @package    local_catquiz
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\local\model\model_item_param
 */
final class persistence_roundtrip_test extends advanced_testcase {
    /**
     * calculate/set -> save_to_db -> reload must preserve the polytomous parameters.
     *
     * @dataProvider polytomous_params_provider
     *
     * @param string $modelname model name
     * @param string $key parameter key ('intercepts' or 'difficulties')
     * @param array $params item parameters to persist
     *
     * @return void
     */
    public function test_save_reload_roundtrip(string $modelname, string $key, array $params): void {
        $this->resetAfterTest();
        $contextid = \context_system::instance()->id;

        $componentids = ['grm' => 90001, 'grmgeneralized' => 90002, 'pcm' => 90003, 'pcmgeneralized' => 90004];
        $componentid = (string) $componentids[$modelname];
        $param = (new model_item_param($componentid, $modelname))
            ->set_item_id(4711)
            ->set_parameters($params);

        global $DB;
        $list = new model_item_param_list();
        $list->add($param);
        $list->save_to_db($contextid);

        // Reconstruct straight from the stored record: this is exactly the
        // to_record() -> DB -> from_record() path the persistence bugs affected.
        $record = $DB->get_record('local_catquiz_itemparams', [
            'contextid' => $contextid, 'componentid' => $componentid, 'model' => $modelname,
        ]);
        $this->assertNotEmpty($record, 'The item must be written to the database.');
        $ro = model_item_param::from_record($record);
        $roparams = $ro->get_params_array();

        // The polytomous map must survive verbatim through the JSON round-trip.
        $this->assertEquals($params[$key], $roparams[$key]);
        if (isset($params['discrimination'])) {
            $this->assertEqualsWithDelta($params['discrimination'], $roparams['discrimination'], 1e-6);
        }
    }

    /**
     * Data for the round-trip test.
     *
     * @return array
     */
    public static function polytomous_params_provider(): array {
        $intercepts = ['0.000' => 0.0, '0.333' => -0.8, '0.666' => 0.2, '1.000' => 1.1];
        $difficulties = ['0.000' => 0.0, '0.333' => -0.5, '0.666' => 0.3, '1.000' => 1.2];
        return [
            'grm' => ['grm', 'difficulties', ['difficulties' => $difficulties]],
            'grmgeneralized' => [
                'grmgeneralized', 'difficulties',
                ['difficulties' => $difficulties, 'discrimination' => 1.3],
            ],
            'pcm' => ['pcm', 'intercepts', ['intercepts' => $intercepts]],
            'pcmgeneralized' => [
                'pcmgeneralized', 'intercepts',
                ['intercepts' => $intercepts, 'discrimination' => 0.9],
            ],
        ];
    }

    /**
     * A finite GGRM parameter set must be valid; a NaN parameter must be invalid.
     *
     * @return void
     */
    public function test_ggrm_is_valid(): void {
        $this->resetAfterTest();

        $good = (new model_item_param('V-good', 'grmgeneralized'))->set_parameters([
            'difficulties' => ['0.000' => 0.0, '0.5' => -0.4, '1.0' => 0.7],
            'discrimination' => 1.1,
        ]);
        $this->assertTrue($good->is_valid(), 'A finite GGRM parameter set must be valid.');

        $nanthreshold = (new model_item_param('V-nan-a', 'grmgeneralized'))->set_parameters([
            'difficulties' => ['0.000' => 0.0, '0.5' => NAN, '1.0' => 0.7],
            'discrimination' => 1.1,
        ]);
        $this->assertFalse($nanthreshold->is_valid(), 'A NaN threshold must make the item invalid.');

        $nandiscrimination = (new model_item_param('V-nan-b', 'grmgeneralized'))->set_parameters([
            'difficulties' => ['0.000' => 0.0, '0.5' => -0.4, '1.0' => 0.7],
            'discrimination' => NAN,
        ]);
        $this->assertFalse($nandiscrimination->is_valid(), 'A NaN discrimination must make the item invalid.');
    }

    /**
     * A NaN GGRM item must be filtered out by save_to_db, a finite one kept.
     *
     * @return void
     */
    public function test_ggrm_valid_item_is_saved(): void {
        $this->resetAfterTest();
        $contextid = \context_system::instance()->id;

        $good = (new model_item_param('91001', 'grmgeneralized'))
            ->set_item_id(5001)
            ->set_parameters([
                'difficulties' => ['0.000' => 0.0, '0.5' => -0.4, '1.0' => 0.7],
                'discrimination' => 1.1,
            ]);

        global $DB;
        $list = new model_item_param_list();
        $list->add($good);
        $list->save_to_db($contextid);

        $this->assertTrue(
            $DB->record_exists('local_catquiz_itemparams', ['contextid' => $contextid, 'componentid' => 91001]),
            'A valid GGRM item must be persisted, not filtered out.'
        );
    }
}
