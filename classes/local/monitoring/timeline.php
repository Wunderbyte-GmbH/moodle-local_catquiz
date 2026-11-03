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

/**
 * Timeline of one request of a CAT attempt: where the time to a question goes (issue #136).
 *
 * Switched off by default (setting local_catquiz/attempttrace). When off, span() only calls the
 * function it is given and start()/stop()/note() return at once, so the instrumented code runs as
 * before.
 *
 * When on, the request collects spans - name, offset from the request start, duration from hrtime(), nesting depth,
 * database queries during the span and a few numbers such as cache hit or miss, rows and
 * candidates. At the end of the request one row per attempt request is written to
 * local_catquiz_trace, together with the spans the host recorded for the same request (lock wait,
 * loading the question, the question usage) when the host offers them.
 *
 * The trace never holds the content of a question or an answer. Span names are fixed in the code;
 * the values of note() are numbers, booleans or short identifiers, and anything else is dropped.
 *
 * Every span also carries its memory (adapter #6): the change of the memory in use, the peak at its end
 * and how much the peak grew during it. The first entry of a written trace is the pseudo span 'request'
 * with the numbers of the request as a whole: its id, shared with the host, the process, the memory at
 * the end and its peak, and whether a fatal error ended it - type, line and file, never the message.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class timeline {
    /** @var string Config name of the switch. */
    public const CONFIG = 'attempttrace';

    /** @var string Trace of the request that administers the first item of an attempt. */
    public const KIND_FIRST_ITEM = 'first_item';

    /** @var string Trace of a request that administers a following item. */
    public const KIND_NEXT_ITEM = 'next_item';

    /** @var string Trace of a reload: the pending item is shown again, nothing is selected (adapter #6). */
    public const KIND_RELOAD = 'reload';

    /** @var string Trace of a request that ended the attempt: the last answer, a closing, the time limit (adapter #6). */
    public const KIND_COMPLETE = 'complete';

    /** @var string Name of the first entry of a written trace, the numbers of the request as a whole (adapter #6). */
    public const REQUEST_SPAN = 'request';

    /** @var string Table the traces are written to. */
    public const TABLE = 'local_catquiz_trace';

    /** @var string Host class that offers its own spans of the request; optional. */
    private const HOST_TIMING = '\\mod_adaptivequiz\\local\\request_timing';

    /** @var int[] PHP errors that end the request; only these mark a trace as fatal. */
    private const FATAL_TYPES = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];

    /** @var bool|null Whether tracing is on; null until first asked. */
    private static ?bool $enabled = null;

    /** @var array Recorded spans, in the order they started. */
    private static array $spans = [];

    /** @var int[] Indices of the open spans, innermost last. */
    private static array $open = [];

    /** @var int|null The adaptive quiz attempt the request works on. */
    private static ?int $adaptiveattemptid = null;

    /** @var string|null One of the KIND_ constants. */
    private static ?string $kind = null;

    /** @var int|null Number of the item administered in this request, 1 for the first. */
    private static ?int $questionnumber = null;

    /** @var bool Whether the flush at the end of the request is registered. */
    private static bool $registered = false;

    /** @var string|null Id of the request when the host offers none; kept until reset(). */
    private static ?string $requestid = null;

    /** @var callable|null Where the last PHP error is read from; error_get_last() unless a test sets one. */
    private static $errorsource = null;

    /**
     * Whether the trace is switched on.
     *
     * @return bool
     */
    public static function enabled(): bool {
        if (self::$enabled === null) {
            self::$enabled = (bool) get_config('local_catquiz', self::CONFIG);
        }
        return self::$enabled;
    }

    /**
     * Forgets everything recorded in this request and the cached switch.
     *
     * For tests and for the request end; nothing else needs it.
     */
    public static function reset(): void {
        self::$enabled = null;
        self::$spans = [];
        self::$open = [];
        self::$adaptiveattemptid = null;
        self::$kind = null;
        self::$questionnumber = null;
        self::$requestid = null;
    }

    /**
     * Names the attempt and the kind of request the trace belongs to.
     *
     * Only a request with an attempt is written; spans of a request without one are discarded.
     *
     * A request that ended the attempt (KIND_COMPLETE) administers no item; its question number is the
     * number of items answered.
     *
     * @param int $adaptiveattemptid adaptivequiz_attempt.id
     * @param int $questionsattempted Items already answered before this request.
     * @param string|null $kind KIND_RELOAD for a request that shows the pending item again, KIND_COMPLETE
     *      for one that ended the attempt; otherwise first or next item, from the number answered.
     */
    public static function attempt(int $adaptiveattemptid, int $questionsattempted, ?string $kind = null): void {
        if (!self::enabled()) {
            return;
        }
        self::$adaptiveattemptid = $adaptiveattemptid;
        self::$kind = $kind ?? ($questionsattempted > 0 ? self::KIND_NEXT_ITEM : self::KIND_FIRST_ITEM);
        self::$questionnumber = $kind === self::KIND_COMPLETE ? $questionsattempted : $questionsattempted + 1;
        if (!self::$registered && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            \core_shutdown_manager::register_function([self::class, 'flush']);
            self::$registered = true;
        }
    }

    /**
     * Opens a span.
     *
     * @param string $name Fixed name from the code, e.g. 'context:questions'.
     * @param array $meta Numbers or short identifiers; see note().
     */
    public static function start(string $name, array $meta = []): void {
        if (!self::enabled()) {
            return;
        }
        self::$spans[] = [
            'name' => $name,
            'depth' => count(self::$open),
            'offsetms' => self::offsetms(),
            'startns' => hrtime(true),
            'durationns' => null,
            'queriesatstart' => self::queries(),
            'memoryatstart' => memory_get_usage(),
            'peakatstart' => memory_get_peak_usage(),
            'meta' => self::clean($meta),
        ];
        self::$open[] = array_key_last(self::$spans);
    }

    /**
     * Closes the innermost open span of that name, and any span opened inside it and left open.
     *
     * @param string $name
     * @param array $meta Added to the span; see note().
     */
    public static function stop(string $name, array $meta = []): void {
        if (!self::enabled()) {
            return;
        }
        $now = hrtime(true);
        for ($i = count(self::$open) - 1; $i >= 0; $i--) {
            if (self::$spans[self::$open[$i]]['name'] !== $name) {
                continue;
            }
            // The last span closed in this loop is the one named; spans left open inside it end with it.
            while (count(self::$open) > $i) {
                $index = array_pop(self::$open);
                self::close($index, $now);
            }
            self::$spans[$index]['meta'] += self::clean($meta);
            return;
        }
    }

    /**
     * Ends a span: its duration, its queries and its memory (adapter #6).
     *
     * @param int $index
     * @param int $now hrtime(true) of the end.
     * @param bool $open Whether the span was still open when the trace was written.
     */
    private static function close(int $index, int $now, bool $open = false): void {
        $memory = memory_get_usage();
        $peak = memory_get_peak_usage();
        self::$spans[$index]['durationns'] = $now - self::$spans[$index]['startns'];
        self::$spans[$index]['queries'] = self::queries() - self::$spans[$index]['queriesatstart'];
        self::$spans[$index]['memdeltakb'] = self::kb($memory - self::$spans[$index]['memoryatstart']);
        self::$spans[$index]['peakkb'] = self::kb($peak);
        self::$spans[$index]['peakgrewkb'] = self::kb($peak - self::$spans[$index]['peakatstart']);
        if ($open) {
            self::$spans[$index]['open'] = true;
        }
    }

    /**
     * Runs a function inside a span and returns its result.
     *
     * @param string $name
     * @param callable $fn
     * @param array $meta
     * @return mixed What the function returns.
     */
    public static function span(string $name, callable $fn, array $meta = []) {
        if (!self::enabled()) {
            return $fn();
        }
        self::start($name, $meta);
        try {
            return $fn();
        } finally {
            self::stop($name);
        }
    }

    /**
     * Attaches a number or a short identifier to the innermost open span.
     *
     * Allowed are integers, floats, booleans, null and identifiers of up to 40 characters made of
     * letters, digits and _ : . -; anything else is dropped. This keeps question text, answers and
     * names out of the trace whatever a caller passes.
     *
     * @param string $key
     * @param mixed $value
     */
    public static function note(string $key, $value): void {
        if (!self::enabled() || !self::$open) {
            return;
        }
        $index = end(self::$open);
        self::$spans[$index]['meta'] += self::clean([$key => $value]);
    }

    /**
     * Adds a span measured elsewhere, e.g. by the host.
     *
     * A span without a duration was not ended where it was measured; it is kept as open, without a
     * duration (adapter #6) - not as one of 0 ms.
     *
     * @param string $name
     * @param float|null $ms Duration in milliseconds; null for a span that was not ended.
     * @param float|null $offsetms Start relative to the request start, when known.
     * @param int $depth Nesting depth.
     * @param array $meta
     */
    public static function add(string $name, ?float $ms, ?float $offsetms = null, int $depth = 0, array $meta = []): void {
        if (!self::enabled()) {
            return;
        }
        $span = [
            'name' => $name,
            'depth' => $depth,
            'offsetms' => $offsetms,
            'durationns' => $ms === null ? null : (int) round($ms * 1e6),
            'meta' => self::clean($meta),
        ];
        if ($ms === null) {
            $span['open'] = true;
        }
        self::$spans[] = $span;
    }

    /**
     * The spans recorded so far, in milliseconds, ready to store.
     *
     * @return array
     */
    public static function get_spans(): array {
        $out = [];
        foreach (self::$spans as $span) {
            $row = [
                'name' => $span['name'],
                'depth' => $span['depth'],
                'offsetms' => $span['offsetms'],
                'ms' => $span['durationns'] === null ? null : round($span['durationns'] / 1e6, 3),
            ];
            foreach (['queries', 'memdeltakb', 'peakkb', 'peakgrewkb', 'open'] as $key) {
                if (isset($span[$key])) {
                    $row[$key] = $span[$key];
                }
            }
            if ($span['meta']) {
                $row['meta'] = $span['meta'];
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Writes the trace of this request and starts afresh.
     *
     * Runs at the end of the request, also after a fatal error. Nothing is written when the trace is
     * off, or when the request named no attempt. Spans the host recorded for the request are added
     * under host:. Spans still open - a fatal error ended the request inside them - are written with
     * the time they ran until now and open = true (adapter #6).
     *
     * The first entry is the pseudo span REQUEST_SPAN, without a duration of its own (the row has
     * requestms); its meta holds requestid, pid, memoryendkb, memorypeakkb, fatal and, after a fatal
     * error, fataltype, fatalline and fatalwhere - the file below the Moodle root, never the message.
     *
     * @return int|null Id of the written row.
     */
    public static function flush(): ?int {
        global $DB, $USER;

        if (!self::enabled() || self::$adaptiveattemptid === null) {
            self::discard();
            return null;
        }

        $fatal = self::fatal_error();
        if ($fatal !== null) {
            // A request that ran out of memory still has to write its trace.
            raise_memory_limit(MEMORY_EXTRA);
        }
        $now = hrtime(true);
        while (self::$open) {
            self::close(array_pop(self::$open), $now, true);
        }

        $requestid = self::request_id();
        $hostsummary = [];
        $hostclass = self::HOST_TIMING;
        if (class_exists($hostclass)) {
            foreach ($hostclass::get_spans() as $span) {
                self::add(
                    'host:' . $span['name'],
                    // A span the host did not end has no duration; 0 ms would hide it (adapter #6).
                    isset($span['ms']) ? (float) $span['ms'] : null,
                    isset($span['offsetms']) ? (float) $span['offsetms'] : null,
                    (int) ($span['depth'] ?? 0),
                    (array) ($span['meta'] ?? [])
                );
            }
            if (method_exists($hostclass, 'summary')) {
                foreach ((array) $hostclass::summary() as $key => $value) {
                    $hostsummary['host_' . $key] = $value;
                }
            }
            // Read once: a process that serves several attempts (a CLI benchmark) must not carry the
            // host's spans into the next trace.
            $hostclass::reset();
        }

        $request = [
            'requestid' => $requestid,
            'pid' => (int) getmypid(),
            'memoryendkb' => self::kb(memory_get_usage()),
            'memorypeakkb' => self::kb(memory_get_peak_usage(true)),
            'fatal' => $fatal !== null,
        ];
        if ($fatal !== null) {
            $request['fataltype'] = (int) $fatal['type'];
            $request['fatalline'] = (int) ($fatal['line'] ?? 0);
            $request['fatalwhere'] = self::where((string) ($fatal['file'] ?? ''));
        }
        $request += self::clean($hostsummary);

        $requestms = self::offsetms();
        $spans = self::get_spans();
        array_unshift($spans, [
            'name' => self::REQUEST_SPAN,
            'depth' => 0,
            'offsetms' => $requestms === null ? null : 0.0,
            'ms' => null,
            'meta' => $request,
        ]);
        $record = (object) [
            'adaptiveattemptid' => self::$adaptiveattemptid,
            'userid' => (int) ($USER->id ?? 0),
            'kind' => self::$kind,
            'questionnumber' => self::$questionnumber,
            'requestms' => $requestms,
            'dbqueries' => self::queries(),
            'spans' => json_encode($spans),
            'timecreated' => time(),
        ];
        self::discard();

        try {
            return (int) $DB->insert_record(self::TABLE, $record);
        } catch (\Throwable $e) {
            // A diagnosis must never cost the attempt anything.
            debugging('local_catquiz: the attempt trace could not be written: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Drops what was recorded, keeping the switch and the id of the request.
     */
    private static function discard(): void {
        $enabled = self::$enabled;
        $requestid = self::$requestid;
        self::reset();
        self::$enabled = $enabled;
        self::$requestid = $requestid;
    }

    /**
     * The attempt the trace of this request belongs to, if one is named.
     *
     * @return int|null adaptivequiz_attempt.id
     */
    public static function current_attempt(): ?int {
        return self::$adaptiveattemptid;
    }

    /**
     * The id of this request, the same for the host's spans and for CATquiz's (adapter #6).
     *
     * The host's id when it offers one; otherwise a random id of this request, kept until reset().
     *
     * @return string
     */
    public static function request_id(): string {
        $hostclass = self::HOST_TIMING;
        if (method_exists($hostclass, 'request_id')) {
            $id = (string) $hostclass::request_id();
            if (preg_match('/^[A-Za-z0-9_:.\-]{1,64}$/', $id)) {
                return $id;
            }
        }
        return self::$requestid ??= bin2hex(random_bytes(8));
    }

    /**
     * Sets where the last PHP error is read from, for tests; null for error_get_last().
     *
     * @param callable|null $source Returns what error_get_last() would.
     */
    public static function set_error_source(?callable $source): void {
        self::$errorsource = $source;
    }

    /**
     * The last PHP error, when it is one that ends the request.
     *
     * @return array|null type, file, line - the message is not used.
     */
    private static function fatal_error(): ?array {
        $error = (self::$errorsource ?? 'error_get_last')();
        if (!is_array($error) || !in_array((int) ($error['type'] ?? 0), self::FATAL_TYPES, true)) {
            return null;
        }
        return $error;
    }

    /**
     * A file as a path below the Moodle root, e.g. local/catquiz/classes/catscale.php.
     *
     * A file outside the root is named by its base name only: the trace holds no paths of the server.
     *
     * @param string $file
     * @return string
     */
    private static function where(string $file): string {
        global $CFG;
        $file = str_replace('\\', '/', $file);
        $where = basename($file);
        foreach ([$CFG->dirroot ?? '', $CFG->root ?? ''] as $root) {
            $root = rtrim(str_replace('\\', '/', (string) $root), '/');
            if ($root !== '' && str_starts_with($file, $root . '/')) {
                $where = substr($file, strlen($root) + 1);
                break;
            }
        }
        $where = preg_replace('/[^A-Za-z0-9_.\/\-]/', '', $where);
        return strlen($where) > 120 ? substr($where, -120) : $where;
    }

    /**
     * Bytes in kilobytes, one decimal.
     *
     * @param int $bytes
     * @return float
     */
    private static function kb(int $bytes): float {
        return round($bytes / 1024, 1);
    }

    /**
     * Milliseconds since the start of the request - the time axis the host uses too.
     *
     * @return float|null
     */
    public static function offsetms(): ?float {
        if (!isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return null;
        }
        return round((microtime(true) - (float) $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 3);
    }

    /**
     * Database queries of this request so far.
     *
     * @return int
     */
    private static function queries(): int {
        global $DB;
        return method_exists($DB, 'perf_get_queries') ? (int) $DB->perf_get_queries() : 0;
    }

    /**
     * Keeps numbers, booleans, null and short identifiers; drops everything else.
     *
     * @param array $meta
     * @return array
     */
    private static function clean(array $meta): array {
        $out = [];
        foreach ($meta as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z0-9_]{1,40}$/', $key)) {
                continue;
            }
            if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $out[$key] = $value;
            } else if (is_string($value) && preg_match('/^[A-Za-z0-9_:.\-]{0,40}$/', $value)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
