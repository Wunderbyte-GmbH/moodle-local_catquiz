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
 * Regression test for the per-model item parameter contract.
 *
 * A 2PL item with discrimination 0 is mathematically mute: P(theta) = 0.5 for
 * every ability, score and Fisher information are exactly 0, so the ability
 * estimate stops moving. Such parameters must never be played productively; the
 * item has to fall back to being a pilot item.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\model\model_strategy::validate_item_parameters
 * @covers \local_catquiz\teststrategy\context\loader\pilotquestions_loader::ispilot
 */

namespace local_catquiz\local\model;

use advanced_testcase;
use local_catquiz\teststrategy\context\loader\pilotquestions_loader;
use stdClass;

/**
 * Guards the per-model item parameter contract.
 *
 * @package    local_catquiz
 * @covers \local_catquiz\local\model\model_strategy::validate_item_parameters
 * @covers \local_catquiz\teststrategy\context\loader\pilotquestions_loader::ispilot
 */
final class item_parameter_contract_test extends advanced_testcase {
    /**
     * Builds a raw item parameter record.
     *
     * @param string $model
     * @param mixed $difficulty
     * @param mixed $discrimination
     * @param mixed $guessing
     * @return stdClass
     */
    private function record(string $model, $difficulty, $discrimination = null, $guessing = null): stdClass {
        $r = new stdClass();
        $r->id = 1;
        $r->model = $model;
        $r->difficulty = $difficulty;
        $r->discrimination = $discrimination;
        $r->guessing = $guessing;
        return $r;
    }

    /**
     * Valid parameters must pass for every model.
     *
     * @return void
     */
    public function test_valid_parameters_pass(): void {
        $this->resetAfterTest(true);

        // Negative, zero and positive difficulties are all legal.
        $this->assertSame([], model_strategy::validate_item_parameters($this->record('rasch', -3.13)));
        $this->assertSame([], model_strategy::validate_item_parameters($this->record('rasch', 0.0)));
        $this->assertSame([], model_strategy::validate_item_parameters($this->record('raschbirnbaum', 0.0, 1.96)));
        $this->assertSame([], model_strategy::validate_item_parameters($this->record('raschbirnbaum', -1.88, 5.0)));
        $this->assertSame(
            [],
            model_strategy::validate_item_parameters($this->record('mixedraschbirnbaum', 0.14, 2.04, 0.25))
        );
    }

    /**
     * A 2PL/3PL discrimination of zero or less must be rejected.
     *
     * @return void
     */
    public function test_nonpositive_discrimination_is_rejected(): void {
        $this->resetAfterTest(true);

        $this->assertNotEmpty(model_strategy::validate_item_parameters($this->record('raschbirnbaum', -5.0, 0.0)));
        $this->assertNotEmpty(model_strategy::validate_item_parameters($this->record('raschbirnbaum', 2.0, -1.0)));
        $this->assertNotEmpty(model_strategy::validate_item_parameters($this->record('mixedraschbirnbaum', 0.5, 0.0, 0.2)));
        // The 1PL model does not use a discrimination, so a stored 0 is harmless.
        $this->assertSame([], model_strategy::validate_item_parameters($this->record('rasch', -5.0, 0.0)));
    }

    /**
     * The 3PL guessing parameter must lie in [0, 1).
     *
     * @return void
     */
    public function test_guessing_range_is_enforced(): void {
        $this->resetAfterTest(true);

        $this->assertNotEmpty(model_strategy::validate_item_parameters(
            $this->record('mixedraschbirnbaum', 0.5, 1.5, -0.1)
        ));
        $this->assertNotEmpty(model_strategy::validate_item_parameters(
            $this->record('mixedraschbirnbaum', 0.5, 1.5, 1.0)
        ));
        $this->assertSame([], model_strategy::validate_item_parameters(
            $this->record('mixedraschbirnbaum', 0.5, 1.5, 0.0)
        ));
    }

    /**
     * A missing or unknown model means there is nothing calibrated.
     *
     * @return void
     */
    public function test_missing_or_unknown_model_is_invalid(): void {
        $this->resetAfterTest(true);

        $this->assertNotEmpty(model_strategy::validate_item_parameters($this->record('', 0.5)));
        $this->assertNotEmpty(model_strategy::validate_item_parameters($this->record('doesnotexist', 0.5)));
    }

    /**
     * An item whose parameters violate its model contract becomes a pilot item,
     * even when it is flagged as calibrated.
     *
     * @return void
     */
    public function test_item_with_invalid_parameters_is_treated_as_pilot(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $loader = new pilotquestions_loader();

        // Mute 2PL item (discrimination 0) although it claims to be calibrated.
        $mute = $this->record('raschbirnbaum', -5.0, 0.0);
        $mute->status = \LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY;
        $mute->attempts = 100;
        $this->assertTrue($loader->ispilot($mute, 30));

        // The same item with a usable discrimination is productive.
        $usable = $this->record('raschbirnbaum', -5.0, 1.5);
        $usable->status = \LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY;
        $usable->attempts = 100;
        $this->assertFalse($loader->ispilot($usable, 30));

        // A 1PL item with a stored discrimination of 0 stays productive.
        $rasch = $this->record('rasch', -5.0, 0.0);
        $rasch->status = \LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY;
        $rasch->attempts = 100;
        $this->assertFalse($loader->ispilot($rasch, 30));
    }

    /**
     * When an item carries parameters for several models, the one with the HIGHEST
     * status must become the active parameter - not the least calibrated one.
     *
     * @return void
     */
    public function test_active_itemparam_picks_highest_status(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $itemid = $DB->insert_record('local_catquiz_items', (object) [
            'componentid' => 1,
            'componentname' => 'question',
            'catscaleid' => 1,
            'contextid' => 1,
            'status' => 0,
        ]);

        $stale = $DB->insert_record('local_catquiz_itemparams', (object) [
            'itemid' => $itemid,
            'componentid' => 1,
            'componentname' => 'question',
            'contextid' => 1,
            'model' => 'rasch',
            'difficulty' => 0.0,
            'discrimination' => 0.0,
            'status' => \LOCAL_CATQUIZ_STATUS_NOT_CALCULATED,
        ]);
        $calibrated = $DB->insert_record('local_catquiz_itemparams', (object) [
            'itemid' => $itemid,
            'componentid' => 1,
            'componentname' => 'question',
            'contextid' => 1,
            'model' => 'raschbirnbaum',
            'difficulty' => -1.88,
            'discrimination' => 5.0,
            'status' => \LOCAL_CATQUIZ_STATUS_UPDATED_MANUALLY,
        ]);

        \local_catquiz\catquiz::set_active_itemparam($itemid);

        $active = $DB->get_field('local_catquiz_items', 'activeparamid', ['id' => $itemid]);
        $this->assertEquals(
            $calibrated,
            $active,
            'The calibrated parameter must win over the uncalibrated one.'
        );
        $this->assertNotEquals($stale, $active);
    }

    /**
     * Polytomous models are judged on their json payload, not on the derived
     * scalar difficulty column.
     *
     * @return void
     */
    public function test_polytomous_models_validate_their_json_payload(): void {
        $this->resetAfterTest(true);

        // Valid GRM/GGRM payload (thresholds) and PCM/GPCM payload (intercepts).
        $grm = $this->record('grm', 0.0);
        $grm->json = json_encode(['difficulties' => [-1.5, 0.2, 1.1]]);
        $this->assertSame([], model_strategy::validate_item_parameters($grm));

        $pcm = $this->record('pcm', 0.0);
        $pcm->json = json_encode(['intercepts' => [-0.8, 0.4]]);
        $this->assertSame([], model_strategy::validate_item_parameters($pcm));

        // A missing json payload must be rejected even though the scalar
        // difficulty column looks perfectly fine.
        $nojson = $this->record('grm', 0.0);
        $nojson->json = '';
        $this->assertNotEmpty(model_strategy::validate_item_parameters($nojson));

        // Wrong key, empty array and non-numeric entries are all invalid.
        $wrongkey = $this->record('grm', 0.0);
        $wrongkey->json = json_encode(['intercepts' => [1.0]]);
        $this->assertNotEmpty(model_strategy::validate_item_parameters($wrongkey));

        $empty = $this->record('pcm', 0.0);
        $empty->json = json_encode(['intercepts' => []]);
        $this->assertNotEmpty(model_strategy::validate_item_parameters($empty));

        $nonnumeric = $this->record('pcm', 0.0);
        $nonnumeric->json = json_encode(['intercepts' => [0.5, 'abc']]);
        $this->assertNotEmpty(model_strategy::validate_item_parameters($nonnumeric));
    }

    /**
     * The generalized polytomous models additionally require a positive slope.
     *
     * @return void
     */
    public function test_generalized_polytomous_models_require_positive_discrimination(): void {
        $this->resetAfterTest(true);

        $ok = $this->record('grmgeneralized', 0.0, 1.4);
        $ok->json = json_encode(['difficulties' => [-1.0, 0.5]]);
        $this->assertSame([], model_strategy::validate_item_parameters($ok));

        $mute = $this->record('grmgeneralized', 0.0, 0.0);
        $mute->json = json_encode(['difficulties' => [-1.0, 0.5]]);
        $this->assertNotEmpty(model_strategy::validate_item_parameters($mute));

        $mutegpcm = $this->record('pcmgeneralized', 0.0, -1.0);
        $mutegpcm->json = json_encode(['intercepts' => [-1.0, 0.5]]);
        $this->assertNotEmpty(model_strategy::validate_item_parameters($mutegpcm));

        // The non-generalized variants do not use a discrimination at all.
        $pcm = $this->record('pcm', 0.0, 0.0);
        $pcm->json = json_encode(['intercepts' => [-1.0, 0.5]]);
        $this->assertSame([], model_strategy::validate_item_parameters($pcm));
    }

    /**
     * Every installed model must implement the contract - no model may be missed.
     *
     * @return void
     */
    public function test_every_installed_model_implements_the_contract(): void {
        $this->resetAfterTest(true);

        $models = model_strategy::get_installed_models();
        $this->assertNotEmpty($models);
        foreach ($models as $name => $class) {
            $this->assertTrue(
                method_exists($class, 'validate_parameters'),
                sprintf('Model "%s" does not implement validate_parameters().', $name)
            );
        }
    }
}
