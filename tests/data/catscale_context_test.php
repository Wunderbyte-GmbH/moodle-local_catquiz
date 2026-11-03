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

namespace local_catquiz\data;

use advanced_testcase;
use local_catquiz\catscale;
use local_catquiz\form\modal_manage_catscale;

/**
 * The context of a scale created or edited through the scale form (issue #81).
 *
 * Context 0 is what the form sends for "New default context for scale": a root scale gets a context of its
 * own, a subscale none (it uses its root's). The scale and its context are written together or not at all.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\data\dataapi
 * @covers \local_catquiz\form\modal_manage_catscale
 */
final class catscale_context_test extends advanced_testcase {
    /**
     * Admin, and a clean state.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        dataapi::$aftercontextstoredhook = null;
    }

    /**
     * The seam is never left behind for the next test.
     */
    protected function tearDown(): void {
        dataapi::$aftercontextstoredhook = null;
        parent::tearDown();
    }

    /**
     * Submits the scale form as the modal does.
     *
     * @param array $data
     * @return object What process_dynamic_submission() returns.
     */
    private function submit(array $data): object {
        $data += [
            'id' => 0,
            'description' => '',
            'parentid' => 0,
            'contextid' => 0,
            'catquiz_minscalevalue' => -4,
            'catquiz_maxscalevalue' => 4,
        ];
        $submitted = modal_manage_catscale::mock_ajax_submit($data);
        $form = new modal_manage_catscale(null, null, 'post', '', null, true, $submitted, true);
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_validated(), 'The form did not validate.');
        return $form->process_dynamic_submission();
    }

    /**
     * The stored context of a scale.
     *
     * @param int $id
     * @return mixed
     */
    private function stored_context(int $id) {
        global $DB;
        return $DB->get_field('local_catquiz_catscales', 'contextid', ['id' => $id], MUST_EXIST);
    }

    /**
     * "New default context for scale" (0) gives a new root scale a context of its own.
     */
    public function test_root_with_new_default_context_gets_its_own_context(): void {
        global $DB;
        $result = $this->submit(['name' => 'Neue Root']);
        $contextid = (int) $this->stored_context((int) $result->id);
        $this->assertGreaterThan(0, $contextid);
        $name = $DB->get_field('local_catquiz_catcontext', 'name', ['id' => $contextid], MUST_EXIST);
        $this->assertStringStartsWith('autocontext_Neue Root_', $name);
        $this->assertSame($contextid, catscale::get_context_id((int) $result->id));
    }

    /**
     * An existing context chosen in the form is kept, and no new one is made.
     */
    public function test_root_with_chosen_context_keeps_it(): void {
        global $DB;
        $first = $this->submit(['name' => 'First root']);
        $contextid = (int) $this->stored_context((int) $first->id);
        $contexts = $DB->count_records('local_catquiz_catcontext');

        $second = $this->submit(['name' => 'Second root', 'contextid' => $contextid]);

        $this->assertSame($contextid, (int) $this->stored_context((int) $second->id));
        $this->assertSame($contexts, $DB->count_records('local_catquiz_catcontext'));
    }

    /**
     * A subscale stores no context - not 0 - and resolves to its root's.
     */
    public function test_subscale_stores_null_and_uses_the_root_context(): void {
        global $DB;
        $root = $this->submit(['name' => 'Sub root']);
        $contexts = $DB->count_records('local_catquiz_catcontext');

        $sub = $this->submit(['name' => 'Sub child', 'parentid' => (int) $root->id, 'contextid' => 0]);

        $this->assertNull($this->stored_context((int) $sub->id));
        $this->assertSame($contexts, $DB->count_records('local_catquiz_catcontext'));
        $this->assertSame((int) $this->stored_context((int) $root->id), catscale::get_context_id((int) $sub->id));

        // Edited through the form, it still stores none.
        $this->submit(['id' => (int) $sub->id, 'name' => 'Sub child', 'parentid' => (int) $root->id, 'contextid' => 0]);
        $this->assertNull($this->stored_context((int) $sub->id));
    }

    /**
     * Editing a root scale with "New default context for scale" makes a new context instead of storing 0.
     */
    public function test_editing_a_root_with_new_default_context_makes_a_new_one(): void {
        $root = $this->submit(['name' => 'Edit root']);
        $old = (int) $this->stored_context((int) $root->id);

        $this->submit(['id' => (int) $root->id, 'name' => 'Edit root', 'contextid' => 0]);

        $new = (int) $this->stored_context((int) $root->id);
        $this->assertGreaterThan(0, $new);
        $this->assertNotSame($old, $new);
        $this->assertSame($new, catscale::get_context_id((int) $root->id));
    }

    /**
     * An update that carries no context (the webservice) leaves the stored one.
     */
    public function test_update_without_context_keeps_the_stored_one(): void {
        $root = $this->submit(['name' => 'Kept root']);
        $contextid = (int) $this->stored_context((int) $root->id);

        dataapi::update_catscale(new catscale_structure([
            'id' => (int) $root->id, 'name' => 'Kept root renamed', 'parentid' => 0, 'timemodified' => time(),
        ]));

        $this->assertSame($contextid, (int) $this->stored_context((int) $root->id));
    }

    /**
     * A scale name of 255 characters still gets its context; the context name fits the column.
     */
    public function test_long_name_fits_the_context_column(): void {
        global $DB;
        $name = str_repeat('Ä', 255);
        $result = $this->submit(['name' => $name]);
        $contextid = (int) $this->stored_context((int) $result->id);
        $contextname = $DB->get_field('local_catquiz_catcontext', 'name', ['id' => $contextid], MUST_EXIST);
        $this->assertLessThanOrEqual(255, \core_text::strlen($contextname));
        $this->assertStringStartsWith('autocontext_ÄÄÄ', $contextname);
        // The time stays at the end.
        $this->assertMatchesRegularExpression('/_[^_]+$/', $contextname);
    }

    /**
     * The form refuses a name longer than the column.
     */
    public function test_form_refuses_a_name_longer_than_the_column(): void {
        $submitted = modal_manage_catscale::mock_ajax_submit([
            'id' => 0, 'name' => str_repeat('x', 256), 'description' => '', 'parentid' => 0, 'contextid' => 0,
            'catquiz_minscalevalue' => -4, 'catquiz_maxscalevalue' => 4,
        ]);
        $form = new modal_manage_catscale(null, null, 'post', '', null, true, $submitted, true);
        $form->set_data_for_dynamic_submission();
        $this->assertFalse($form->is_validated());
    }

    /**
     * A failure while the context is made leaves neither scale nor context, and the exception reaches the
     * caller.
     */
    public function test_failure_after_the_context_rolls_back_both(): void {
        global $DB;
        // A real rollback: not inside the transaction the test framework wraps around the test.
        $this->preventResetByRollback();
        $scales = $DB->count_records('local_catquiz_catscales');
        $contexts = $DB->count_records('local_catquiz_catcontext');
        dataapi::$aftercontextstoredhook = function () {
            throw new \moodle_exception('error', 'error', '', null, 'Injected failure (issue #81)');
        };
        $sink = $this->redirectEvents();

        try {
            dataapi::create_catscale(new catscale_structure([
                'name' => 'Rolled back', 'parentid' => 0, 'contextid' => 0,
                'timecreated' => time(), 'timemodified' => time(),
            ]));
            $this->fail('The failure was swallowed.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('Injected failure', $e->debuginfo);
        }

        $this->assertSame($scales, $DB->count_records('local_catquiz_catscales'));
        $this->assertSame($contexts, $DB->count_records('local_catquiz_catcontext'));
        $names = array_map(fn($e) => $e->eventname, $sink->get_events());
        $this->assertNotContains('\local_catquiz\event\catscale_created', $names);
        $this->assertNotContains('\local_catquiz\event\catscale_updated', $names);
        $sink->close();
    }

    /**
     * Creating a root scale is one creation: no update event for a scale that is not committed yet.
     */
    public function test_creating_triggers_no_update_event(): void {
        $sink = $this->redirectEvents();
        $this->submit(['name' => 'One event']);
        $names = array_map(fn($e) => $e->eventname, $sink->get_events());
        $sink->close();
        $this->assertContains('\local_catquiz\event\catscale_created', $names);
        $this->assertNotContains('\local_catquiz\event\catscale_updated', $names);
    }
}
