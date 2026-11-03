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

namespace local_catquiz\local\monitoring;

use advanced_testcase;
use local_catquiz\catquiz_handler;
use local_catquiz\catscale;
use mod_adaptivequiz\local\attempt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/catquiz/lib.php');

/**
 * The timeline of attempt requests (issue #136).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_catquiz\local\monitoring\timeline
 * @covers \local_catquiz\local\monitoring\trace_report
 */
final class timeline_test extends advanced_testcase {
    /**
     * Starts every test with a clean timeline.
     */
    protected function setUp(): void {
        parent::setUp();
        timeline::reset();
    }

    /**
     * Leaves no state behind for the next test.
     */
    protected function tearDown(): void {
        timeline::reset();
        timeline::set_error_source(null);
        $hostclass = '\\mod_adaptivequiz\\local\\request_timing';
        if (class_exists($hostclass)) {
            $hostclass::reset();
        }
        parent::tearDown();
    }

    /**
     * Switched off - the default - the code runs as before and nothing is written.
     */
    public function test_off_by_default_records_nothing(): void {
        global $DB;
        $this->resetAfterTest();

        $this->assertFalse(timeline::enabled());
        $this->assertSame(42, timeline::span('anything', fn() => 42));
        timeline::attempt(7, 0);
        timeline::start('open');
        timeline::note('n', 1);

        $this->assertSame([], timeline::get_spans());
        $this->assertNull(timeline::flush());
        $this->assertSame(0, $DB->count_records(timeline::TABLE));
    }

    /**
     * Switched on, nested spans keep their depth, duration and queries; one row per request is written.
     */
    public function test_nested_spans_are_written_for_the_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        timeline::attempt(11, 2);
        $value = timeline::span('outer', function () use ($DB) {
            timeline::span('inner', fn() => $DB->get_records('config', ['name' => 'version']), ['rows' => 1]);
            return 'done';
        });
        $this->assertSame('done', $value);

        $id = timeline::flush();
        $this->assertNotNull($id);

        $row = $DB->get_record(timeline::TABLE, ['id' => $id]);
        $this->assertSame(11, (int) $row->adaptiveattemptid);
        $this->assertSame(timeline::KIND_NEXT_ITEM, $row->kind);
        $this->assertSame(3, (int) $row->questionnumber);
        $this->assertSame(2, (int) $row->userid);

        $spans = array_column(json_decode($row->spans, true), null, 'name');
        $this->assertSame(0, $spans['outer']['depth']);
        $this->assertSame(1, $spans['inner']['depth']);
        $this->assertGreaterThanOrEqual(0, $spans['inner']['ms']);
        $this->assertGreaterThanOrEqual($spans['inner']['ms'], $spans['outer']['ms']);
        $this->assertGreaterThanOrEqual(1, $spans['inner']['queries']);
        $this->assertSame(1, $spans['inner']['meta']['rows']);

        // Written once: the buffer is empty afterwards.
        $this->assertSame([], timeline::get_spans());
    }

    /**
     * The first item and a following one are told apart.
     */
    public function test_first_item_is_its_own_kind(): void {
        global $DB;
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        timeline::attempt(12, 0);
        timeline::span('x', fn() => null);
        $row = $DB->get_record(timeline::TABLE, ['id' => timeline::flush()]);

        $this->assertSame(timeline::KIND_FIRST_ITEM, $row->kind);
        $this->assertSame(1, (int) $row->questionnumber);
    }

    /**
     * A request without an attempt is not written.
     */
    public function test_request_without_attempt_is_discarded(): void {
        global $DB;
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        timeline::span('pool:query', fn() => null);

        $this->assertNull(timeline::flush());
        // Not even tried: no failed write, no debugging message.
        $this->assertDebuggingNotCalled();
        $this->assertSame(0, $DB->count_records(timeline::TABLE));
    }

    /**
     * Stopping a span ends the spans left open inside it.
     */
    public function test_stop_closes_spans_left_open_inside(): void {
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        timeline::start('outer');
        timeline::start('forgotten');
        timeline::stop('outer');

        $spans = array_column(timeline::get_spans(), null, 'name');
        $this->assertNotNull($spans['outer']['ms']);
        $this->assertNotNull($spans['forgotten']['ms']);
    }

    /**
     * Only numbers and short identifiers get into the trace - never text such as a question.
     */
    public function test_no_content_gets_into_the_trace(): void {
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        timeline::start('step', ['questiontext' => 'What is 2 + 2?']);
        timeline::note('answer', 'Four, because two and two make four.');
        timeline::note('Bad Key', 1);
        timeline::note('candidates', 17);
        timeline::note('pool_cache', 'hit');
        timeline::note('fraction', 0.5);
        timeline::stop('step');

        $span = timeline::get_spans()[0];
        $this->assertSame(['candidates' => 17, 'pool_cache' => 'hit', 'fraction' => 0.5], $span['meta']);
    }

    /**
     * The host's own spans of the request - lock wait, loading the question - land in the same trace.
     */
    public function test_host_spans_are_added(): void {
        global $DB;
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');
        $hostclass = '\\mod_adaptivequiz\\local\\request_timing';
        if (!class_exists($hostclass)) {
            $this->markTestSkipped('The host offers no request timing.');
        }

        $hostclass::reset();
        $hostclass::start('lock_wait');
        $hostclass::stop('lock_wait', ['acquired' => true]);
        timeline::attempt(13, 1);
        $row = $DB->get_record(timeline::TABLE, ['id' => timeline::flush()]);
        $hostclass::reset();

        $spans = array_column(json_decode($row->spans, true), null, 'name');
        $this->assertArrayHasKey('host:lock_wait', $spans);
        $this->assertTrue($spans['host:lock_wait']['meta']['acquired']);
        $this->assertNotNull($spans['host:lock_wait']['ms']);
    }

    /**
     * Nearest-rank percentiles over the stored traces, per kind of request.
     */
    public function test_summary_gives_percentiles_per_kind(): void {
        global $DB;
        $this->resetAfterTest();

        foreach (range(1, 20) as $i) {
            $DB->insert_record(timeline::TABLE, (object) [
                'adaptiveattemptid' => 1, 'userid' => 2, 'kind' => timeline::KIND_NEXT_ITEM, 'questionnumber' => $i + 1,
                'requestms' => $i * 10, 'dbqueries' => 5, 'timecreated' => time(),
                'spans' => json_encode([['name' => 'strategy', 'depth' => 0, 'offsetms' => 0, 'ms' => (float) $i]]),
            ]);
        }

        $summary = trace_report::summary();

        $this->assertSame(20, $summary[timeline::KIND_NEXT_ITEM]['count']);
        $this->assertEquals(100.0, $summary[timeline::KIND_NEXT_ITEM]['requestms']['p50']);
        $this->assertEquals(190.0, $summary[timeline::KIND_NEXT_ITEM]['requestms']['p95']);
        $this->assertEquals(200.0, $summary[timeline::KIND_NEXT_ITEM]['requestms']['p99']);
        $this->assertEquals(19.0, $summary[timeline::KIND_NEXT_ITEM]['spans']['strategy']['p95']);
        $this->assertCount(20, trace_report::for_attempt(1));
    }

    /**
     * Old traces are deleted by the scheduled task; recent ones stay.
     */
    public function test_old_traces_are_purged(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('attempttraceretentiondays', 14, 'local_catquiz');

        foreach ([time() - 20 * DAYSECS, time()] as $time) {
            $DB->insert_record(timeline::TABLE, (object) [
                'adaptiveattemptid' => 1, 'userid' => 2, 'kind' => timeline::KIND_FIRST_ITEM, 'timecreated' => $time,
            ]);
        }
        (new \local_catquiz\task\purge_attempt_traces())->execute();

        $this->assertSame(1, $DB->count_records(timeline::TABLE));
    }

    /**
     * A real first item: the trace names the steps the issue asks for and holds no question text.
     */
    public function test_first_item_of_a_real_attempt_is_traced(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $adaptivequiz = $this->getDataGenerator()->get_plugin_generator('mod_adaptivequiz')->create_instance([
            'course' => $course->id, 'highestlevel' => 10, 'lowestlevel' => 1, 'standarderror' => 14,
            'attemptfeedbackeditor' => ['text' => '', 'format' => FORMAT_MOODLE],
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_catquiz');
        $generator->create_catquiz_questions([
            'filepath' => 'local/catquiz/tests/fixtures/quiz-adaptivetest-Simulation-small.xml',
            'filename' => 'quiz-adaptivetest-Simulation-small.xml',
            'courseid' => $course->id,
        ]);
        $generator->create_catquiz_importedcatscales([
            'filepath' => 'local/catquiz/tests/fixtures/simulation_small.csv',
            'filename' => 'simulation_small.csv',
        ]);
        $rootscale = $DB->get_record('local_catquiz_catscales', ['parentid' => 0]);
        $generator->create_catquiz_testsettings([
            'courseid' => $course->id,
            'adaptivecatquizid' => $adaptivequiz->id,
            'catscalesid' => $rootscale->id,
            'cateststrategyid' => LOCAL_CATQUIZ_STRATEGY_LOWESTSUB,
            'catmodel' => 'catquiz',
            'catquiz_maxquestions' => 4,
            'catquiz_standarderror_min' => 0.4,
            'catquiz_standarderror_max' => 0.6,
            'numberoffeedbackoptions' => 2,
        ]);

        set_config(timeline::CONFIG, 1, 'local_catquiz');
        timeline::reset();
        catquiz_handler::prepare_attempt_caches();
        $this->preventResetByRollback();
        $this->setUser($student);
        $adaptiveattempt = new attempt($adaptivequiz, $student->id);
        [$questionid] = catquiz_handler::fetch_question_id($adaptivequiz->id, 'mod_adaptivequiz', $adaptiveattempt->get_attempt());
        $this->assertNotEquals(0, $questionid);

        $id = timeline::flush();
        $row = $DB->get_record(timeline::TABLE, ['id' => $id]);
        $this->assertSame(timeline::KIND_FIRST_ITEM, $row->kind);
        $names = array_column(json_decode($row->spans, true), 'name');
        foreach (
            [
                'prepare_attempt_caches', 'catquiz:fetch_question_id', 'testenvironment', 'contextcreator',
                'context:progress', 'context:questions', 'pool:cache_lookup', 'strategy',
            ] as $expected
        ) {
            $this->assertContains($expected, $names, "Span $expected is missing.");
        }
        $lookup = array_values(array_filter(json_decode($row->spans, true), fn($s) => $s['name'] === 'pool:cache_lookup'))[0];
        $this->assertContains($lookup['meta']['pool_cache'], ['hit', 'miss']);

        // No question text in the trace.
        $question = $DB->get_record('question', ['id' => $questionid]);
        $this->assertStringNotContainsString(trim(strip_tags($question->questiontext)), $row->spans);
        $this->assertStringNotContainsString($question->name, $row->spans);
    }
    /**
     * Every span carries its memory: the change in use, the peak at its end and how much the peak grew (adapter #6).
     */
    public function test_spans_carry_their_memory(): void {
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        $kept = timeline::span('allocate', fn() => str_repeat('x', 2 * 1048576));
        $this->assertSame(2 * 1048576, strlen($kept));

        $span = array_column(timeline::get_spans(), null, 'name')['allocate'];
        $this->assertIsFloat($span['memdeltakb']);
        $this->assertGreaterThanOrEqual(2000.0, $span['memdeltakb'], 'Two megabytes stay in use after the span.');
        $this->assertGreaterThanOrEqual($span['memdeltakb'], $span['peakkb']);
        $this->assertGreaterThanOrEqual(0.0, $span['peakgrewkb']);
    }

    /**
     * The written trace begins with the request: its id, the process, the memory at the end and its peak (adapter #6).
     */
    public function test_trace_begins_with_the_request(): void {
        global $DB;
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        timeline::attempt(21, 0);
        $requestid = timeline::request_id();
        timeline::span('step', fn() => null);
        $row = $DB->get_record(timeline::TABLE, ['id' => timeline::flush()]);
        $spans = json_decode($row->spans, true);

        $this->assertSame(timeline::REQUEST_SPAN, $spans[0]['name']);
        $this->assertNull($spans[0]['ms'], 'The request has no duration of its own; the row has requestms.');
        $meta = $spans[0]['meta'];
        $this->assertSame($requestid, $meta['requestid']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_:.\-]{1,64}$/', $meta['requestid']);
        $this->assertSame(getmypid(), $meta['pid']);
        $this->assertGreaterThan(0, $meta['memoryendkb']);
        $this->assertGreaterThanOrEqual($meta['memoryendkb'], $meta['memorypeakkb']);
        $this->assertFalse($meta['fatal']);
        $this->assertArrayNotHasKey('fatalwhere', $meta);
        $this->assertSame('step', $spans[1]['name']);

        // The readers: the summary does not count the request as a step, a single attempt shows it.
        $summary = trace_report::summary();
        $this->assertArrayNotHasKey(timeline::REQUEST_SPAN, $summary[timeline::KIND_FIRST_ITEM]['spans']);
        $this->assertArrayHasKey('step', $summary[timeline::KIND_FIRST_ITEM]['spans']);
        $this->assertSame(timeline::REQUEST_SPAN, trace_report::for_attempt(21)[0]['spans'][0]['name']);
    }

    /**
     * The id of a request stays the same for all its traces and is the host's when the host offers one.
     */
    public function test_request_id_is_stable_for_the_request(): void {
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        $first = timeline::request_id();
        $this->assertSame($first, timeline::request_id());
        timeline::attempt(22, 1);
        timeline::flush();
        $this->assertSame($first, timeline::request_id(), 'A second trace of the same request keeps its id.');

        $hostclass = '\\mod_adaptivequiz\\local\\request_timing';
        if (method_exists($hostclass, 'request_id')) {
            $this->assertSame((string) $hostclass::request_id(), $first);
        } else {
            timeline::reset();
            $this->assertNotSame($first, timeline::request_id(), 'A new request, a new id.');
        }
    }

    /**
     * A host span that was not ended is written as open, without a duration - not as 0 ms (adapter #6).
     */
    public function test_unfinished_host_span_is_open(): void {
        global $DB;
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');
        $hostclass = '\\mod_adaptivequiz\\local\\request_timing';
        if (!class_exists($hostclass)) {
            $this->markTestSkipped('The host offers no request timing.');
        }

        $hostclass::reset();
        $hostclass::start('question_usage');
        timeline::attempt(23, 1);
        $row = $DB->get_record(timeline::TABLE, ['id' => timeline::flush()]);

        $span = array_column(json_decode($row->spans, true), null, 'name')['host:question_usage'];
        $this->assertNull($span['ms']);
        $this->assertTrue($span['open']);

        // A span measured elsewhere without a duration, directly.
        timeline::add('elsewhere', null);
        $span = array_column(timeline::get_spans(), null, 'name')['elsewhere'];
        $this->assertNull($span['ms']);
        $this->assertTrue($span['open']);
    }

    /**
     * A span still open when the trace is written keeps the time it ran and is marked open (adapter #6).
     */
    public function test_span_left_open_is_written_with_its_time(): void {
        global $DB;
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        timeline::attempt(24, 1);
        timeline::start('strategy');
        timeline::start('strategy:fisherinformation');
        $row = $DB->get_record(timeline::TABLE, ['id' => timeline::flush()]);

        $spans = array_column(json_decode($row->spans, true), null, 'name');
        foreach (['strategy', 'strategy:fisherinformation'] as $name) {
            $this->assertTrue($spans[$name]['open'], "$name is open.");
            $this->assertNotNull($spans[$name]['ms'], "$name has its time.");
            $this->assertArrayHasKey('peakkb', $spans[$name]);
        }
        $this->assertArrayNotHasKey('open', $spans[timeline::REQUEST_SPAN]);
    }

    /**
     * After a fatal error the trace says so - type, line and file below the Moodle root, never the message.
     */
    public function test_fatal_error_is_recorded_without_its_message(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        timeline::set_error_source(fn() => [
            'type' => E_ERROR,
            'message' => 'Allowed memory size exhausted while reading Secret Question Text',
            'file' => $CFG->dirroot . '/local/catquiz/classes/catscale.php',
            'line' => 471,
        ]);
        timeline::attempt(25, 3);
        timeline::start('pool:query');
        $row = $DB->get_record(timeline::TABLE, ['id' => timeline::flush()]);

        $spans = array_column(json_decode($row->spans, true), null, 'name');
        $meta = $spans[timeline::REQUEST_SPAN]['meta'];
        $this->assertTrue($meta['fatal']);
        $this->assertSame(E_ERROR, $meta['fataltype']);
        $this->assertSame(471, $meta['fatalline']);
        $this->assertSame('local/catquiz/classes/catscale.php', $meta['fatalwhere']);
        $this->assertTrue($spans['pool:query']['open']);
        $this->assertStringNotContainsString('Secret', $row->spans);
        $this->assertStringNotContainsString($CFG->dirroot, $row->spans);

        // A file outside the Moodle root is named by its base name; a warning is no fatal error.
        timeline::set_error_source(fn() => ['type' => E_ERROR, 'message' => 'x', 'file' => '/srv/secret/lib.php', 'line' => 1]);
        timeline::attempt(25, 3);
        $row = $DB->get_record(timeline::TABLE, ['id' => timeline::flush()]);
        $this->assertSame('lib.php', json_decode($row->spans, true)[0]['meta']['fatalwhere']);

        timeline::set_error_source(fn() => ['type' => E_WARNING, 'message' => 'x', 'file' => __FILE__, 'line' => 1]);
        timeline::attempt(25, 3);
        $row = $DB->get_record(timeline::TABLE, ['id' => timeline::flush()]);
        $this->assertFalse(json_decode($row->spans, true)[0]['meta']['fatal']);
    }

    /**
     * A request that ended the attempt is a kind of its own; its number is that of the items answered.
     */
    public function test_completing_request_is_its_own_kind(): void {
        global $DB;
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        timeline::attempt(26, 7);
        timeline::attempt(26, 7, timeline::KIND_COMPLETE);
        $this->assertSame(26, timeline::current_attempt());
        $row = $DB->get_record(timeline::TABLE, ['id' => timeline::flush()]);

        $this->assertSame(timeline::KIND_COMPLETE, $row->kind);
        $this->assertSame(7, (int) $row->questionnumber);
        $this->assertNull(timeline::current_attempt());
    }

    /**
     * The span of prepare_attempt_caches() ends also when the step throws (adapter #6).
     */
    public function test_prepare_attempt_caches_span_ends_when_it_throws(): void {
        global $USER;
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        $user = $USER;
        // A user whose id cannot be read: the last step of the preparation throws.
        $GLOBALS['USER'] = new class {
            /** @var string|null The session key, which the session sets on its own. */
            public $sesskey = null;

            /**
             * Refuses every property.
             *
             * @param string $name
             */
            public function __get($name) {
                throw new \coding_exception('no ' . $name);
            }
        };
        try {
            catquiz_handler::prepare_attempt_caches(31);
            $this->fail('The preparation was to throw.');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('no id', $e->getMessage());
        } finally {
            $GLOBALS['USER'] = $user;
        }

        $span = array_column(timeline::get_spans(), null, 'name')['prepare_attempt_caches'];
        $this->assertNotNull($span['ms'], 'The span was ended.');
        $this->assertArrayNotHasKey('open', $span);
    }

    /**
     * The span of the test environment ends also when the step throws (adapter #6).
     */
    public function test_testenvironment_span_ends_when_it_throws(): void {
        $this->resetAfterTest();
        set_config(timeline::CONFIG, 1, 'local_catquiz');

        // The work of fetch_question_id() itself, without its outer span that would end the inner one anyway.
        $method = new \ReflectionMethod(catquiz_handler::class, 'fetch_question_id_traced');
        $method->setAccessible(true);
        try {
            $method->invoke(null, -1, 'mod_adaptivequiz', (object) ['questionsattempted' => 0]);
            $this->fail('A test that does not exist was to throw.');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $e);
        }

        $span = array_column(timeline::get_spans(), null, 'name')['testenvironment'];
        $this->assertNotNull($span['ms'], 'The span was ended.');
    }
}
