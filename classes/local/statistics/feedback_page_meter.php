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

namespace local_catquiz\local\statistics;

use local_catquiz\external\render_feedback_tab;
use local_catquiz\external\render_statistics_chart;
use local_catquiz\output\attemptfeedback;
use local_catquiz\shortcodes;

/**
 * Measures what the feedback pages cost: queries, reads, bytes, session size, time (issue #85).
 *
 * Used by cli/measure_feedback_page.php and by the PHPUnit test of the same name, so that the numbers in
 * doc/statistics-population.md come from the code that is tested. Every measurement renders like the
 * page does: the result page (export_for_template + template), the shortcodes catquizfeedback and
 * catquizstatistics, and every region that loads later through its endpoint.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class feedback_page_meter {
    /**
     * Runs a rendering and measures it.
     *
     * @param callable $render Returns the HTML.
     * @param int $repeat How often; the time is reported as median and 95th percentile.
     * @return array queries, reads, bytes, session (bytes of the serialised $SESSION afterwards),
     *               sessiongrowth, median, p95 (milliseconds), html (of the first run).
     */
    public static function measure(callable $render, int $repeat = 1): array {
        global $DB, $SESSION;

        $times = [];
        $first = null;
        for ($i = 0; $i < max(1, $repeat); $i++) {
            $sessionbefore = strlen(serialize($SESSION));
            $queries = $DB->perf_get_queries();
            $reads = $DB->perf_get_reads();
            $start = microtime(true);
            $html = (string) $render();
            $times[] = (microtime(true) - $start) * 1000;
            if ($first === null) {
                $first = [
                    'queries' => $DB->perf_get_queries() - $queries,
                    'reads' => $DB->perf_get_reads() - $reads,
                    'bytes' => strlen($html),
                    'session' => strlen(serialize($SESSION)),
                    'sessiongrowth' => strlen(serialize($SESSION)) - $sessionbefore,
                    'html' => $html,
                ];
            }
        }
        sort($times);
        $first['median'] = round($times[intdiv(count($times) - 1, 2)], 2);
        $first['p95'] = round($times[(int) min(count($times) - 1, ceil(0.95 * count($times)) - 1)], 2);
        return $first;
    }

    /**
     * The result page of an attempt, as show_attemptfeedback.php renders it.
     *
     * @param int $adaptiveattemptid
     * @param int $courseid
     * @return string
     */
    public static function render_result_page(int $adaptiveattemptid, int $courseid = 0): string {
        global $PAGE;
        $output = $PAGE->get_renderer('core');
        $feedback = new attemptfeedback($adaptiveattemptid, 0, null, $courseid ?: null);
        return $output->render_from_template('local_catquiz/attemptfeedback', $feedback->export_for_template($output));
    }

    /**
     * The result page with the JavaScript it asks for (the data of its charts).
     *
     * @param int $adaptiveattemptid
     * @param int $courseid
     * @return array [html, javascript]
     */
    public static function render_result_page_with_javascript(int $adaptiveattemptid, int $courseid = 0): array {
        global $OUTPUT, $PAGE;
        self::new_request();
        $PAGE->set_pagelayout('embedded');
        $OUTPUT->doctype();
        $PAGE->start_collecting_javascript_requirements();
        $html = self::render_result_page($adaptiveattemptid, $courseid);
        $javascript = $PAGE->requires->get_end_code();
        $PAGE->end_collecting_javascript_requirements();
        return [$html, $javascript];
    }

    /**
     * A page as a new request has it: an endpoint collects the JavaScript of its page, once per request.
     *
     * @return void
     */
    public static function new_request(): void {
        global $OUTPUT, $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_url('/local/catquiz/cli/measure_feedback_page.php');
        $PAGE->set_context(\context_system::instance());
        $OUTPUT = new \bootstrap_renderer();
    }

    /**
     * The shortcode catquizfeedback.
     *
     * @param array $args Shortcode arguments (numberofattempts, courseid, instanceid).
     * @return string
     */
    public static function render_feedback_shortcode(array $args): string {
        return (string) shortcodes::catquizfeedback('catquizfeedback', $args, null, null, null);
    }

    /**
     * The shortcode catquizstatistics.
     *
     * @param array $args Shortcode arguments (globalscale, courseid, testid, attempts, ...).
     * @return string
     */
    public static function render_statistics_shortcode(array $args): string {
        return (string) shortcodes::catquizstatistics('catquizstatistics', $args, null, null, null);
    }

    /**
     * The regions of a page that load later.
     *
     * @param string $html
     * @return array Feedback regions [attemptid, generatorname, audience] and chart regions [scope, chart].
     */
    public static function lazy_regions(string $html): array {
        $regions = ['feedback' => [], 'statistics' => []];
        preg_match_all(
            '/data-region="local_catquiz-lazyfeedback"\s+data-attemptid="(\d+)"\s+data-generatorname="([a-z_]+)"'
                . '\s+data-audience="([a-z]+)"/',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as [, $attemptid, $generator, $audience]) {
            $regions['feedback'][$attemptid . '/' . $generator . '/' . $audience] = [(int) $attemptid, $generator, $audience];
        }
        preg_match_all(
            '/data-region="local_catquiz-lazystatistics"\s+data-chart="([a-z]+)"\s+data-scope="([^"]*)"/',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as [, $chart, $scope]) {
            $regions['statistics'][$chart] = [json_decode(html_entity_decode($scope), true), $chart];
        }
        return $regions;
    }

    /**
     * The content of one feedback tab, through its endpoint.
     *
     * @param int $adaptiveattemptid
     * @param string $generatorname
     * @param string $audience
     * @return string
     */
    public static function render_tab(int $adaptiveattemptid, string $generatorname, string $audience): string {
        self::new_request();
        return render_feedback_tab::execute($adaptiveattemptid, $generatorname, $audience)['html'];
    }

    /**
     * The content of one feedback tab and its JavaScript, through its endpoint.
     *
     * @param int $adaptiveattemptid
     * @param string $generatorname
     * @param string $audience
     * @return array [html, javascript]
     */
    public static function render_tab_with_javascript(int $adaptiveattemptid, string $generatorname, string $audience): array {
        self::new_request();
        $result = render_feedback_tab::execute($adaptiveattemptid, $generatorname, $audience);
        return [$result['html'], $result['javascript']];
    }

    /**
     * One chart of the statistics, through its endpoint.
     *
     * @param array $scope
     * @param string $chart
     * @return string
     */
    public static function render_chart(array $scope, string $chart): string {
        self::new_request();
        return render_statistics_chart::execute($scope, $chart)['html'];
    }

    /**
     * The content of the tab panes of a page rendered at once, by generator and audience.
     *
     * What the template puts below the heading of a pane: the comment and the content, which is what the
     * endpoint returns for the tab.
     *
     * @param string $html
     * @return string[] "generatorname/audience" => HTML.
     */
    public static function eager_panes(string $html): array {
        $panes = [];
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query(
            '//div[contains(concat(" ", @class, " "), " tab-pane ")][starts-with(@id, "local-catquiz-feedback-")]'
        );
        foreach ($nodes as $pane) {
            if (!preg_match('/-([a-z_]+)-(student|teacher)$/', $pane->getAttribute('id'), $match)) {
                continue;
            }
            $content = '';
            foreach ($xpath->query('./div[starts-with(@class, "feedbackcontent-")]/*[not(self::h5)]', $pane) as $child) {
                $content .= $document->saveHTML($child);
            }
            $panes[$match[1] . '/' . $match[2]] = $content;
        }
        return $panes;
    }

    /**
     * A fingerprint of HTML that ignores what differs from render to render: generated ids, scripts,
     * white space and the italic comment wrapper of a pane.
     *
     * The data of every chart in the HTML is part of it: it is taken from the JavaScript the chart asked
     * for (core/chart), by the id of the chart, and put in place of the id.
     *
     * @param string $html
     * @param string $javascript The JavaScript of the page or the endpoint; empty to leave the charts out.
     * @return string
     */
    public static function fingerprint(string $html, string $javascript = ''): string {
        preg_match_all('/var data = (\{.*?\}),\s*uniqid = "([^"]+)"/s', $javascript, $charts, PREG_SET_ORDER);
        foreach ($charts as [, $data, $uniqid]) {
            $html = str_replace('id="chart-area-' . $uniqid . '"', 'data-chart="' . sha1($data) . '"', $html);
        }
        $html = preg_replace('/<script\b.*?<\/script>/s', '', $html);
        $html = preg_replace('/\s(id|for|aria-[a-z]+|data-[a-z-]*id|data-bs-target|href)="[^"]*"/', '', $html);
        $html = preg_replace('/\s+>/', '>', $html);
        // An empty italic note: Bootstrap 5 (fst-italic) or Bootstrap 4 (font-italic) markup.
        $html = preg_replace('/<div class="(fst|font)-italic">\s*<\/div>/', '', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        $html = preg_replace('/\s+/', ' ', trim($html));
        $html = str_replace(['<br/>', '<br />'], '<br>', $html);
        // The endpoint returns the content; the pane holds it in one more element.
        $html = preg_replace('/^<div> ?(.*?) ?<\/div>$/s', '$1', $html);
        return sha1(trim($html));
    }
}
