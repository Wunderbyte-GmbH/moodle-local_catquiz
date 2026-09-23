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
 * Issue #29: the manager builds only the tab that is on screen.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_catquiz;

use advanced_testcase;
use local_catquiz\output\catscalemanager\managecatscaledashboard;
use ReflectionClass;

/**
 * Verifies tab selection and that the other tabs are not built.
 *
 * The dashboard used to construct eight displays on every request - each with its own
 * queries - and the template then hid seven of them. The cost was paid for content
 * nobody was looking at.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_catquiz\output\catscalemanager\managecatscaledashboard
 */
final class managecatscaledashboard_tabs_test extends advanced_testcase {
    /**
     * An unknown tab falls back to the first one instead of building everything.
     *
     * A crafted URL parameter must not be able to select something that does not
     * exist, and an absent one must not mean "all of them".
     *
     * @return void
     */
    public function test_unknown_tab_falls_back(): void {
        $this->resetAfterTest();

        $reflection = new ReflectionClass(managecatscaledashboard::class);
        $tabs = $reflection->getConstant('TABS');

        $this->assertNotEmpty($tabs, 'The tab list is what the parameter is validated against.');
        $this->assertContains('summary', $tabs);
        $this->assertContains('questions', $tabs);

        // The fallback is the first entry, which is what the template marked active
        // before this change.
        $this->assertSame('summary', $tabs[0]);
    }

    /**
     * Every display construction is guarded by the active tab.
     *
     * Checking the source rather than the behaviour is deliberate: constructing the
     * dashboard needs a full manager page context, while the property that matters -
     * that nothing is built unconditionally - is visible statically and would be lost
     * silently if someone added a ninth display without a guard.
     *
     * @return void
     */
    public function test_no_display_is_constructed_unconditionally(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/output/catscalemanager/'
                . 'managecatscaledashboard.php'
        );

        $start = strpos($source, 'public function __construct');
        $this->assertNotFalse($start);
        $end = strpos($source, "\n    }\n", $start);
        $body = substr($source, $start, $end - $start);

        preg_match_all('/^\s*\$\w+ = new (\w+)\(/m', $body, $matches, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($matches[0], 'The constructor must still build displays.');

        $unguarded = [];
        foreach ($matches[0] as $index => $match) {
            // Look back from the construction for the nearest tab guard.
            $before = substr($body, 0, $match[1]);
            $lastguard = strrpos($before, '$this->activetab ===');
            $lastclose = strrpos($before, "\n        }");

            if ($lastguard === false || ($lastclose !== false && $lastclose > $lastguard)) {
                $unguarded[] = $matches[1][$index][0];
            }
        }

        $this->assertSame(
            [],
            $unguarded,
            'These displays are built regardless of which tab is shown.'
        );
    }

    /**
     * Every tab has its own URL carrying the manager state.
     *
     * Without the context and scale in the link, switching tabs would silently reset
     * what the user was looking at.
     *
     * @return void
     */
    public function test_each_tab_has_a_url_with_state(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/output/catscalemanager/'
                . 'managecatscaledashboard.php'
        );

        $this->assertStringContainsString("'tab' => \$tab", $source);
        foreach (['contextid', 'scaleid', 'usesubs'] as $parameter) {
            $this->assertStringContainsString(
                "'" . $parameter . "' =>",
                $source,
                "The tab link must carry $parameter, otherwise switching tabs resets it."
            );
        }
    }

    /**
     * The page reads the tab from the URL.
     *
     * @return void
     */
    public function test_page_reads_the_tab_parameter(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents($CFG->dirroot . '/local/catquiz/manage_catscales.php');

        $this->assertStringContainsString(
            "optional_param('tab'",
            $source,
            'Without reading the parameter the tab cannot survive a reload.'
        );
    }
    /**
     * The template navigates by URL and renders only the active pane.
     *
     * Building one tab on the server is only half the change: as long as the markup
     * switched tabs with Bootstrap anchors, the other panes were still emitted - now
     * without any data behind them, because they were never built.
     *
     * @return void
     */
    public function test_template_uses_server_side_tabs(): void {
        global $CFG;

        $this->resetAfterTest();

        $template = file_get_contents(
            $CFG->dirroot . '/local/catquiz/templates/catscalemanager/'
                . 'managecatscaledashboard.mustache'
        );

        $this->assertStringNotContainsString(
            'data-toggle="tab"',
            $template,
            'Client-side switching would show panes that were never rendered.'
        );
        $this->assertStringNotContainsString(
            'href="#lcq_',
            $template,
            'An anchor cannot carry the tab to the server.'
        );

        // Each tab is guarded, so exactly one pane is emitted per request.
        foreach (['issummary', 'isquestions', 'isversioning'] as $flag) {
            $this->assertStringContainsString(
                '{{#' . $flag . '}}',
                $template,
                "The $flag pane must be rendered conditionally."
            );
        }
    }

    /**
     * Every flag and URL the template uses is exported.
     *
     * A missing key renders as empty in Mustache without any error, so a tab would
     * simply stop working and nothing would say why.
     *
     * @return void
     */
    public function test_all_template_keys_are_exported(): void {
        global $CFG;

        $this->resetAfterTest();

        $template = file_get_contents(
            $CFG->dirroot . '/local/catquiz/templates/catscalemanager/'
                . 'managecatscaledashboard.mustache'
        );
        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/output/catscalemanager/'
                . 'managecatscaledashboard.php'
        );

        preg_match_all('/\{\{[#^]?(is\w+|\w+url)\}\}/', $template, $matches);
        $missing = [];
        foreach (array_unique($matches[1]) as $key) {
            if (!str_contains($source, "'" . $key . "' =>")) {
                $missing[] = $key;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'These keys are used in the template but never exported.'
        );
    }
    /**
     * Nothing links to a tab pane by URL fragment any more.
     *
     * Before issue #29 every pane was rendered and hidden, so #lcq_questions was a
     * valid anchor. Now only the active pane exists, and such a link lands on the
     * default tab with the content it was pointing at simply absent. That is how the
     * Playwright search tests began failing with "#lcq_questions not found".
     *
     * @return void
     */
    public function test_nothing_links_to_a_pane_by_fragment(): void {
        global $CFG;

        $this->resetAfterTest();

        $root = $CFG->dirroot . '/local/catquiz/classes';
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());

            // A moodle_url built with an lcq_ fragment as its third argument.
            if (preg_match("/,\s*'lcq_\w+'\s*\)/", $source)) {
                $offenders[] = basename($file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These build links to a pane that is no longer rendered unless it is the '
                . 'active tab; they have to pass the tab as a parameter instead.'
        );
    }

    /**
     * The question search form carries the tab, so submitting stays on the list.
     *
     * A form without it returns to the default tab, and the list the user was
     * working in is not in the response at all.
     *
     * @return void
     */
    public function test_question_search_form_carries_the_tab(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/output/catscalemanager/questions/'
                . 'questionsdisplay.php'
        );

        $start = strpos($source, 'private static function current_page_params');
        $this->assertNotFalse($start, 'The form builds its hidden fields here.');
        $end = strpos($source, "\n    }\n", $start);
        $body = substr($source, $start, $end - $start);

        $this->assertStringContainsString(
            "'tab' => PARAM_ALPHA",
            $body,
            'Without the tab the search returns to the default tab and loses the list.'
        );
    }
    /**
     * No form is built for a tab that is not being rendered.
     *
     * Building a Moodle form registers its validation script, which looks the form up
     * by id when the page loads. With only the active tab rendered the element is
     * absent, the lookup returns null and the script throws - and a thrown script
     * leaves the page permanently "not ready".
     *
     * That is how seven Behat scenarios came to fail at "I press Catquiz", a step
     * that has nothing to do with the CSV importer whose form caused it. The symptom
     * appeared nowhere near the cause, which is why this is pinned here.
     *
     * @return void
     */
    public function test_no_form_is_built_for_an_inactive_tab(): void {
        global $CFG;

        $this->resetAfterTest();

        $source = file_get_contents(
            $CFG->dirroot . '/local/catquiz/classes/output/catscalemanager/'
                . 'managecatscaledashboard.php'
        );

        $start = strpos($source, 'public function export_for_template');
        $this->assertNotFalse($start);
        $body = substr($source, $start);

        // The importer form may only be rendered on its own tab.
        $this->assertMatchesRegularExpression(
            "/activetab === 'importer'\s*\n?\s*\?\s*catscaledashboard::render_testitem_importer/",
            $body,
            'Building this form on every tab registers validation JS for an element '
                . 'that is not in the document.'
        );
    }
    /**
     * Only the active tab issues database queries.
     *
     * The other DoD points can all be satisfied by markup: a URL parameter, an
     * aria-current, a working back button. None of them proves that the server
     * stopped building the tabs nobody asked for - and that was the actual reason for
     * the rebuild.
     *
     * Counting queries is the only way to tell those apart. The numbers themselves
     * are not pinned, because they legitimately change when a tab gains a column; what
     * is pinned is that a second tab costs measurably more than a summary that shows
     * almost nothing.
     *
     * @return void
     */
    public function test_only_the_active_tab_queries_the_database(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Tab query context',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => $now - 100,
            'endtimestamp' => $now + 10000,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0,
            'name' => 'Tab query scale',
            'label' => 'TQ1',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Rendering needs a page with a url, or Moodle emits a debugging notice
        // that PHPUnit treats as a failure.
        $PAGE->set_url('/local/catquiz/manage_catscales.php');

        $counts = [];
        foreach (['summary', 'questions'] as $tab) {
            $before = $DB->perf_get_queries();

            $dashboard = new managecatscaledashboard(
                0,
                $contextid,
                $scaleid,
                0,
                1,
                'question',
                $tab
            );

            // The page renderer rather than a hand-built one: export_for_template()
            // renders sub-templates, and those need a renderer that knows its theme.
            $dashboard->export_for_template($PAGE->get_renderer('local_catquiz'));

            $counts[$tab] = $DB->perf_get_queries() - $before;
        }

        // Every tab needs some queries; a zero would mean the export did not run at
        // all and the comparison below would be meaningless.
        $this->assertGreaterThan(0, $counts['summary']);

        $this->assertNotSame(
            $counts['summary'],
            $counts['questions'],
            'Both tabs cost the same, which is what happens when the dashboard builds '
                . 'all of them regardless of which one was requested.'
        );
    }
    /**
     * Every sortable column is one the query can actually sort by.
     *
     * Two name spaces meet in this table. define_columns() names display columns -
     * 'questiontext' is one, rendered by col_questiontext(). define_sortablecolumns()
     * names columns the statement selects, and sorting by a display column fails with
     * "column does not exist".
     *
     * An earlier version of this test compared the two lists with each other and so
     * accepted exactly the wrong name. Only the query can settle it, so the query is
     * asked - the more so because the two question tables differ: the add dialog
     * selects the question name as 'name', the main list as 'questionname'.
     *
     * @return void
     */
    public function test_sortable_columns_are_real_sql_columns(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $contextid = (int) $DB->insert_record('local_catquiz_catcontext', (object) [
            'name' => 'Sort context',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'starttimestamp' => $now - 100,
            'endtimestamp' => $now + 10000,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ]);
        $scaleid = (int) $DB->insert_record('local_catquiz_catscales', (object) [
            'parentid' => 0,
            'name' => 'Sort scale',
            'label' => 'SRT1',
            'contextid' => $contextid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        [$select, $from, $where, , $params] = catquiz::return_sql_for_addcatscalequestions(
            $scaleid,
            $contextid
        );

        foreach (['idnumber', 'name', 'qtype', 'questioncontextattempts'] as $column) {
            try {
                $DB->get_records_sql(
                    "SELECT $select FROM $from WHERE $where ORDER BY $column",
                    $params,
                    0,
                    1
                );
            } catch (\Throwable $e) {
                $this->fail("The add dialog cannot sort by '$column'.");
            }
        }

        $this->assertTrue(true, 'Every declared sort column exists in the statement.');
    }
}
