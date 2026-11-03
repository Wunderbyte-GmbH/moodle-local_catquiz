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
 * Jest unit tests for the lazy regions: failure, retry, collapsed attempts, statistics (issue #85).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init as initFeedback} from '../../amd/src/feedback';
import {init as initStatistics} from '../../amd/src/statistics';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import {getString} from 'core/str';

/**
 * Let the pending promise chain of the async handlers settle.
 *
 * @return {Promise<void>}
 */
const flush = async() => {
    for (let i = 0; i < 10; i++) {
        // eslint-disable-next-line no-await-in-loop
        await new Promise(resolve => setTimeout(resolve, 0));
    }
};

/**
 * The calls of one web service.
 *
 * @param {string} methodname
 * @return {Array}
 */
const callsOf = (methodname) => Ajax.call.mock.calls.filter(([requests]) => requests[0].methodname === methodname);

beforeEach(() => {
    jest.clearAllMocks();
    jest.spyOn(window.console, 'error').mockImplementation(() => undefined);
    getString.mockImplementation((key) => Promise.resolve(key === 'retry' ? 'Retry' : 'Could not be loaded.'));
    Templates.replaceNodeContents.mockImplementation((node, html) => {
        node.innerHTML = html;
        return Promise.resolve();
    });
});

test('a failed tab shows a message with a retry button, and the retry loads it', async() => {
    document.body.innerHTML =
        '<a class="feedbacktab" href="#pane">Tab</a>'
        + '<div id="pane" class="tab-pane"><div data-region="local_catquiz-lazyfeedback" data-attemptid="7"'
        + ' data-generatorname="graphicalsummary" data-audience="student"></div></div>'
        + '<p id="rest">Rest of the page</p>';
    Ajax.call
        .mockReturnValueOnce([Promise.reject(new Error('Network down'))])
        .mockReturnValue([Promise.resolve({html: '<p>Chart</p>', javascript: ''})]);

    initFeedback();
    document.querySelector('a.feedbacktab').click();
    await flush();

    const region = document.querySelector('[data-region="local_catquiz-lazyfeedback"]');
    expect(region.dataset.state).toBe('failed');
    expect(region.textContent).toContain('Could not be loaded.');
    expect(document.getElementById('rest').textContent).toBe('Rest of the page');
    const retry = region.querySelector('[data-action="local_catquiz-retry"]');
    expect(retry).not.toBeNull();

    retry.click();
    await flush();

    expect(region.dataset.state).toBe('loaded');
    expect(region.innerHTML).toContain('<p>Chart</p>');
    expect(callsOf('local_catquiz_render_feedback_tab')).toHaveLength(2);
});

test('a collapsed attempt loads its feedback when opened, not before, and only once', async() => {
    document.body.innerHTML =
        '<button data-bs-toggle="collapse" data-bs-target="#attempt2">Attempt 2</button>'
        + '<div id="attempt2" class="collapse"><div class="card-body">'
        + '<div data-region="local_catquiz-lazyfeedback" data-attemptid="12" data-generatorname="attempt"'
        + ' data-audience="student"></div></div></div>';
    Ajax.call.mockReturnValue([Promise.resolve({html: '<div class="catquiz_feedback">Feedback</div>', javascript: ''})]);

    initFeedback();
    await flush();
    expect(callsOf('local_catquiz_render_feedback_tab')).toHaveLength(0);

    const button = document.querySelector('[data-bs-toggle="collapse"]');
    button.click();
    await flush();
    button.click();
    await flush();

    const requests = callsOf('local_catquiz_render_feedback_tab');
    expect(requests).toHaveLength(1);
    expect(requests[0][0][0].args).toEqual({attemptid: 12, generatorname: 'attempt', audience: 'student'});
});

test('Bootstrap 4 markup (Moodle 4.5): a collapsed attempt loads when opened as well', async() => {
    document.body.innerHTML =
        '<button data-toggle="collapse" data-target="#attempt3">Attempt 3</button>'
        + '<div id="attempt3" class="collapse"><div class="card-body">'
        + '<div data-region="local_catquiz-lazyfeedback" data-attemptid="13" data-generatorname="attempt"'
        + ' data-audience="student"></div></div></div>';
    Ajax.call.mockReturnValue([Promise.resolve({html: '<div>Feedback</div>', javascript: ''})]);

    initFeedback();
    document.querySelector('[data-toggle="collapse"]').click();
    await flush();

    expect(callsOf('local_catquiz_render_feedback_tab')).toHaveLength(1);
});

test('statistics: the visible tab loads at once, another when it is shown; one failure leaves the rest', async() => {
    const scope = '{"courseid":3,"testid":0,"scaleid":5,"starttime":0,"endtime":0,"attempts":"first"}';
    document.body.innerHTML =
        '<a data-bs-toggle="tab" href="#usage">Usage</a>'
        + '<div data-region="local_catquiz-statistics">'
        + '<div id="overview" class="tab-pane active">'
        + '<div data-region="local_catquiz-lazystatistics" data-chart="abilityprofile" data-scope=\'' + scope + '\'></div>'
        + '<div data-region="local_catquiz-lazystatistics" data-chart="detectedscales" data-scope=\'' + scope + '\'></div>'
        + '</div>'
        + '<div id="usage" class="tab-pane">'
        + '<div data-region="local_catquiz-lazystatistics" data-chart="attemptsperperson" data-scope=\'' + scope + '\'></div>'
        + '</div></div>';
    Ajax.call.mockImplementation(([req]) => [req.args.chart === 'detectedscales'
        ? Promise.reject(new Error('Broken chart'))
        : Promise.resolve({html: '<p>' + req.args.chart + '</p>', javascript: ''})]);

    initStatistics();
    await flush();

    let charts = callsOf('local_catquiz_render_statistics_chart').map(([r]) => r[0].args.chart);
    expect(charts).toEqual(['abilityprofile', 'detectedscales']);
    expect(callsOf('local_catquiz_render_statistics_chart')[0][0][0].args.scope).toEqual(JSON.parse(scope));
    expect(document.querySelector('[data-chart="abilityprofile"]').dataset.state).toBe('loaded');
    expect(document.querySelector('[data-chart="detectedscales"]').dataset.state).toBe('failed');

    document.querySelector('a[href="#usage"]').click();
    await flush();

    charts = callsOf('local_catquiz_render_statistics_chart').map(([r]) => r[0].args.chart);
    expect(charts).toEqual(['abilityprofile', 'detectedscales', 'attemptsperperson']);
    expect(document.querySelector('[data-chart="attemptsperperson"]').dataset.state).toBe('loaded');
});
