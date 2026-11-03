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

namespace local_catquiz\output;

use advanced_testcase;

/**
 * Several feedbacks and statistics on one page do not share ids (issue #30).
 *
 * Tabs, collapses and modals find their target by id. Two instances with the same ids - the
 * attempts of a feedback list, the same shortcode twice, a shortcode next to the result page -
 * make a click in one open the panel of the other, or none at all.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class template_ids_test extends advanced_testcase {
    /**
     * All ids of a piece of HTML.
     *
     * @param string $html
     * @return string[]
     */
    private static function ids(string $html): array {
        preg_match_all('/\sid\s*=\s*"([^"]+)"/', $html, $matches);
        return $matches[1];
    }

    /**
     * The ids a piece of HTML points to through tabs, collapses and aria-controls.
     *
     * @param string $html
     * @return string[]
     */
    private static function targets(string $html): array {
        preg_match_all(
            '/(?:href|data-bs-target|data-target|data-bs-parent|data-parent)="#([^"]+)"|aria-(?:controls|labelledby)="([^"]+)"/',
            $html,
            $matches
        );
        return array_values(array_filter(array_merge($matches[1], $matches[2])));
    }

    /**
     * Asserts unique ids, and that every target exists within the same piece.
     *
     * @param string[] $pieces Rendered instances, as they would sit on one page.
     */
    private function assert_ids_are_page_safe(array $pieces): void {
        $all = [];
        foreach ($pieces as $html) {
            $ids = self::ids($html);
            foreach (self::targets($html) as $target) {
                $this->assertContains($target, $ids, "Target #$target is not part of its own instance.");
            }
            $all = array_merge($all, $ids);
        }
        $duplicates = array_keys(array_filter(array_count_values($all), fn($n) => $n > 1));
        $this->assertSame([], $duplicates, 'Ids used twice on one page.');
        $this->assertNotEmpty($all);
    }

    /**
     * Feedback of one attempt with a generator that has both a student and a teacher tab.
     *
     * @param int $attemptid
     * @return array
     */
    private static function feedback(int $attemptid): array {
        return [
            'attemptid' => $attemptid,
            'feedback' => [
                'studentfeedback' => [
                    ['generatorname' => 'customscalefeedback', 'heading' => 'Result', 'content' => 'x', 'frontpage' => '1'],
                    ['generatorname' => 'graphicalsummary', 'heading' => 'History', 'content' => 'x', 'othertabs' => '1'],
                ],
                'teacherfeedback' => [
                    ['generatorname' => 'graphicalsummary', 'heading' => 'History', 'content' => 'x'],
                    ['generatorname' => 'debuginfo', 'heading' => 'Export', 'content' => 'x'],
                ],
            ],
        ];
    }

    /**
     * Two attempts, and the same attempt twice, on one page.
     */
    public function test_attempt_feedback(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $this->assert_ids_are_page_safe([
            $OUTPUT->render_from_template('local_catquiz/attemptfeedback', self::feedback(5)),
            $OUTPUT->render_from_template('local_catquiz/attemptfeedback', self::feedback(6)),
            $OUTPUT->render_from_template('local_catquiz/attemptfeedback', self::feedback(5)),
        ]);
    }

    /**
     * A list of attempts, twice on one page - several attempts in one render, as the shortcode and the
     * feedback page render them: the accordion around them exists once per render (issue #30).
     */
    public function test_feedback_list(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $attempts = array_map(
            fn($id) => ['attemptid' => $id, 'header' => 'Attempt ' . $id, 'active' => $id === 7] + self::feedback($id),
            [7, 8, 9]
        );
        foreach (['local_catquiz/feedback/collapsablefeedback', 'local_catquiz/feedback_page'] as $template) {
            $render = fn() => $OUTPUT->render_from_template($template, ['attempt' => $attempts]);
            $html = $render();
            $this->assert_ids_are_page_safe([$html, $render()]);
            // Every attempt closes the others of its own list.
            preg_match_all('/data-(?:bs-)?parent="#([^"]+)"/', $html, $parents);
            $this->assertCount(3, $parents[1], $template);
            $this->assertCount(1, array_unique($parents[1]), $template);
        }
    }

    /**
     * Export buttons, the graphical summary and the scale overview, each twice on one page: no fixed ids
     * (issue #30).
     */
    public function test_export_summary_and_scale_overview(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $export = ['attemptid' => 5, 'isteacher' => true, 'iscatmanager' => true, 'debugenabled' => true, 'data' => 'x',
            'debuginfo_raw' => 'x', 'cid' => 1];
        $summary = ['chart' => '', 'table' => ''];
        $overview = ['itemtree' => [['name' => 'Scale', 'id' => 1, 'description' => '', 'ischild' => false]]];
        foreach (
            ['local_catquiz/feedback/exportattempt' => $export, 'local_catquiz/feedback/graphicalsummary' => $summary,
                'local_catquiz/catscaleshortcodes/catscaleshortcodetable' => $overview] as $template => $context
        ) {
            $pieces = [$OUTPUT->render_from_template($template, $context), $OUTPUT->render_from_template($template, $context)];
            $all = array_merge(self::ids($pieces[0]), self::ids($pieces[1]));
            $this->assertSame([], array_keys(array_filter(array_count_values($all), fn($n) => $n > 1)), $template);
        }
        $this->assertStringContainsString('</th>', $OUTPUT->render_from_template(
            'local_catquiz/catscaleshortcodes/catscaleshortcodetable',
            $overview
        ));
    }

    /**
     * The link in the navigation bar has no id - it is on every page - and an absolute address (issues #30, #32).
     */
    public function test_navbar_link(): void {
        global $CFG, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/local/catquiz/lib.php');

        $html = local_catquiz_render_navbar_output($PAGE->get_renderer('core'));

        $this->assertSame([], self::ids($html));
        $this->assertStringContainsString('href="' . $CFG->wwwroot . '/local/catquiz/manage_catscales.php"', $html);
        $this->assertStringNotContainsString('aria-haspopup', $html);
    }

    /**
     * The same statistics shortcode twice on one page.
     */
    public function test_statistics_shortcode(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $context = ['heading' => ['title' => 'Statistics', 'description' => ''], 'shortcodeid' => 'a b'];
        foreach ([$context, $context + ['exportbutton' => '<a href="#">CSV</a>'], ['shortcodeid' => 'a']] as $variant) {
            $this->assert_ids_are_page_safe([
                $OUTPUT->render_from_template('local_catquiz/catscaleshortcodes/catscalestatistics', $variant),
                $OUTPUT->render_from_template('local_catquiz/catscaleshortcodes/catscalestatistics', $variant),
            ]);
        }
    }

    /**
     * Feedback for teachers only: the tab list is closed and the panes have their wrapper (issue #32).
     */
    public function test_teacher_only_feedback_is_balanced(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $context = self::feedback(11);
        $context['feedback']['studentfeedback'] = [];
        $html = $OUTPUT->render_from_template('local_catquiz/attemptfeedback', $context);

        $this->assertSame(substr_count($html, '<ul'), substr_count($html, '</ul>'));
        $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'));
        $this->assertStringContainsString('id="local-catquiz-feedback-', $html);
        $this->assertMatchesRegularExpression('/class="tab-content"[^>]*>\s*<div class="tab-pane/', $html);
        $this->assertStringNotContainsString('<i>', $html);
    }

    /**
     * The tabs of the statistics shortcode follow the ARIA tabs pattern: a tab list, tabs that name
     * their panel and say which one is selected, panels named by their tab (issue #30).
     */
    public function test_statistics_tabs_are_aria_tabs(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $html = $OUTPUT->render_from_template('local_catquiz/catscaleshortcodes/catscalestatistics', [
            'heading' => ['title' => 'Statistics', 'description' => ''],
            'exportbutton' => '<a href="#">CSV</a>',
        ]);

        $this->assertSame(1, substr_count($html, 'role="tablist"'));
        preg_match_all('/<a [^>]*role="tab"[^>]*>/', $html, $tabs);
        $this->assertCount(4, $tabs[0]);
        $selected = 0;
        foreach ($tabs[0] as $tab) {
            $this->assertMatchesRegularExpression('/\sid="[^"]+-tab"/', $tab);
            $this->assertMatchesRegularExpression('/aria-controls="[^"]+"/', $tab);
            $this->assertMatchesRegularExpression('/aria-selected="(true|false)"/', $tab);
            $this->assertStringNotContainsString('aria-expanded', $tab);
            $selected += (int) str_contains($tab, 'aria-selected="true"');
        }
        $this->assertSame(1, $selected);
        preg_match_all('/<div [^>]*role="tabpanel"[^>]*>/', $html, $panels);
        $this->assertCount(4, $panels[0]);
        foreach ($panels[0] as $panel) {
            $this->assertMatchesRegularExpression('/aria-labelledby="[^"]+-tab"/', $panel);
        }
    }

    /**
     * The collapsible of the feedback form is one template: open and close rendered together make
     * balanced markup, and the two parts the form renders separately are its halves (issue #32).
     */
    public function test_feedback_collapsible_is_one_template(): void {
        global $CFG, $OUTPUT;
        $this->resetAfterTest();

        $context = ['headerid' => 'h1', 'collapseid' => 'c1', 'accordionid' => 'a1', 'headername' => 'Scale',
            'dataname' => 'Scale', 'datadepth' => 0];
        $whole = $OUTPUT->render_from_template(
            'local_catquiz/feedback/feedbackform_collapsible',
            ['open' => true, 'close' => true] + $context
        );
        $open = $OUTPUT->render_from_template('local_catquiz/feedback/feedbackform_collapsible', ['open' => true] + $context);
        $close = $OUTPUT->render_from_template('local_catquiz/feedback/feedbackform_collapsible', ['close' => true]);

        $this->assertSame(substr_count($whole, '<div'), substr_count($whole, '</div>'));
        $this->assertSame(substr_count($open, '<div') - substr_count($open, '</div>'), substr_count($close, '</div>'));
        $this->assertSame(0, substr_count($close, '<div'));
        $this->assertSame(preg_replace('/\s+/', '', $whole), preg_replace('/\s+/', '', $open . $close));
        $this->assert_ids_are_page_safe([$whole]);
        $this->assertFileDoesNotExist($CFG->dirroot . '/local/catquiz/templates/feedback/feedbackform_collapsible_open.mustache');
        $this->assertFileDoesNotExist($CFG->dirroot . '/local/catquiz/templates/feedback/feedbackform_collapsible_close.mustache');
    }

    /**
     * The tabs of the CAT manager with what each of them renders: the ids of one page do not repeat,
     * and the ids that do not change from one render to the next - the fixed ones of the page - carry
     * the plugin prefix (issue #30).
     */
    public function test_manager_tabs_have_page_safe_ids(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $tree = [['id' => 3, 'name' => 'Scale', 'description' => '', 'parentid' => 0, 'children' => []]];
        $tabs = [
            'issummary' => [],
            'iscatscales' => ['catscaledetailview' => ['itemtree' => $tree]],
            'iscatscales detail' => ['catscaledetailview' => ['scaledetailview' => [
                'scaledetailviewheading' => 'Scale', 'is_root' => true, 'sync_as_node_enabled' => true,
                'sync_as_hub_enabled' => true,
                'backtoscaleslink' => ['class' => 'btn-primary', 'type' => 'button', 'label' => 'Back'],
            ]]],
            'isquestions' => ['questionsdisplay' => [
                'notable' => false, 'modaltable' => '<p>t</p>', 'table' => '<table><tr><td>x</td></tr></table>',
                'checkbox' => ['label' => 'Subscales', 'checked' => ''], 'hiddenparams' => [],
                'testitemdashboard' => [
                    'backtotablelink' => ['class' => 'btn-primary', 'type' => 'button', 'label' => 'Back'],
                    'models' => [['title' => 'Rasch', 'body' => '<div>x</div>']],
                ],
            ]],
            'istestsandtemplates' => ['testsandtemplatesdisplay' => ['checkbox' => ['label' => 'Subscales', 'checked' => '']]],
            'isquizattempts' => [],
            'iscalculations' => [],
            'isversioning' => [],
            'isimporter' => ['testitemsimporter' => '<div>importer</div>'],
        ];
        foreach ($tabs as $tab => $context) {
            $context[strtok($tab, ' ')] = true;
            $context['summaryheading'] = 'CAT manager';
            $first = $OUTPUT->render_from_template('local_catquiz/catscalemanager/managecatscaledashboard', $context);
            $second = $OUTPUT->render_from_template('local_catquiz/catscalemanager/managecatscaledashboard', $context);

            $ids = self::ids($first);
            $this->assertSame([], array_keys(array_filter(array_count_values($ids), fn($n) => $n > 1)), $tab);
            foreach (self::targets($first) as $target) {
                $this->assertContains($target, $ids, "$tab: target #$target is not on the page.");
            }
            $fixed = array_intersect($ids, self::ids($second));
            foreach ($fixed as $id) {
                $this->assertStringStartsWith('local-catquiz-', $id, "$tab: a fixed id without the plugin prefix.");
            }
            $this->assertContains('local-catquiz-manager-tabs', $fixed, $tab);
        }
    }

    /**
     * The parts of the manager that may stand more than once - the scale and context selector, the
     * collapsibles of a scale - take their ids from the render, not from the template.
     */
    public function test_repeatable_manager_parts_have_render_ids(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $parts = [
            'local_catquiz/catscalemanager/scaleandcontextselector' => ['checkbox' => ['label' => 'Subscales', 'checked' => '']],
            'local_catquiz/catscalemanager/catscales' => ['catscaledetailview' => ['scaledetailview' => [
                'is_root' => true, 'sync_as_node_enabled' => true, 'sync_as_hub_enabled' => true,
            ]]],
            'local_catquiz/fetch_parameters_result' => ['hasWarnings' => true,
                'warnings' => [['message' => 'm', 'count' => 1, 'items' => ['a'], 'index' => 1]]],
        ];
        foreach ($parts as $template => $context) {
            $this->assert_ids_are_page_safe([
                $OUTPUT->render_from_template($template, $context),
                $OUTPUT->render_from_template($template, $context),
            ]);
        }
    }

    /**
     * The "add test items" dialog is found by an id of its render and named by its title, so two of
     * them on one page do not open each other (issue #30). Bootstrap 4 markup: Moodle 4.5.
     */
    public function test_add_test_items_dialog_has_render_ids(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $context = ['questionsdisplay' => ['notable' => false, 'modaltable' => '<p>ITEMTABLE</p>', 'hiddenparams' => []]];
        $html = $OUTPUT->render_from_template('local_catquiz/catscalemanager/questions/questionsdisplay', $context);

        $this->assertMatchesRegularExpression('/data-target="#local-catquiz-addtestitems-[^"]+"/', $html);
        $this->assertMatchesRegularExpression('/aria-labelledby="local-catquiz-addtestitems-[^"]+-title"/', $html);
        $this->assertStringNotContainsString('data-target=".bd-example-modal', $html);
        $this->assertStringNotContainsString('myLargeModalLabel', $html);
        // Fixed ids of the page (the search form) aside, the dialogs of two renders are apart.
        $second = $OUTPUT->render_from_template('local_catquiz/catscalemanager/questions/questionsdisplay', $context);
        preg_match_all('/id="(local-catquiz-addtestitems-[^"]+)"/', $html . $second, $dialogs);
        $this->assertCount(4, array_unique($dialogs[1]));
    }
}
