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
 * Jest unit tests for the lazily loaded feedback tabs (issue #85, #139).
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init} from '../../amd/src/feedback';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Fragment from 'core/fragment';

/**
 * Let the pending promise chain of the async handlers settle.
 *
 * @return {Promise<void>}
 */
const flush = async() => {
    for (let i = 0; i < 8; i++) {
        // eslint-disable-next-line no-await-in-loop
        await new Promise(resolve => setTimeout(resolve, 0));
    }
};

beforeEach(() => {
    jest.clearAllMocks();
    document.body.innerHTML =
        '<a class="feedbacktab" href="#pane">Tab</a>'
        + '<div id="pane"><div data-region="local_catquiz-lazyfeedback" data-attemptid="7"'
        + ' data-generatorname="graphicalsummary" data-audience="student"></div></div>';
    Templates.replaceNodeContents.mockResolvedValue(undefined);
});

test('a tab runs the JavaScript of its content once: unwrapped, not inside script tags (issue #139)', async() => {
    const wrapped = '<script>\n//<![CDATA[\ndrawChart();\n//]]>\n</script>';
    Ajax.call.mockReturnValue([Promise.resolve({html: '<p>Chart</p>', javascript: wrapped})]);
    Fragment.processCollectedJavascript.mockReturnValueOnce(' drawChart();');

    init();
    document.querySelector('a.feedbacktab').click();
    await flush();

    expect(Fragment.processCollectedJavascript).toHaveBeenCalledWith(wrapped);
    expect(Templates.replaceNodeContents).toHaveBeenCalledTimes(1);
    expect(Templates.replaceNodeContents).toHaveBeenCalledWith(
        document.querySelector('[data-region="local_catquiz-lazyfeedback"]'),
        '<div><p>Chart</p></div>',
        ' drawChart();'
    );
});

test('opening a tab again does not load it a second time', async() => {
    Ajax.call.mockReturnValue([Promise.resolve({html: '<p>Chart</p>', javascript: ''})]);

    init();
    const tab = document.querySelector('a.feedbacktab');
    tab.click();
    await flush();
    tab.click();
    await flush();

    expect(Templates.replaceNodeContents).toHaveBeenCalledTimes(1);
});
