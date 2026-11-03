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
 * Fills a region of a page when it is shown: feedback tabs, collapsed attempts, statistics charts (issue #85).
 *
 * One loader for all of them. The region keeps its state in data-state: loading, loaded or failed. A
 * failure stays inside the region - a message with a retry button - and the rest of the page keeps
 * working.
 *
 * @module     local_catquiz/lazyregion
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Fragment from 'core/fragment';
import Pending from 'core/pending';
import Templates from 'core/templates';
import {addIconToContainer} from 'core/loadingicon';
import {getString} from 'core/str';

/**
 * Shows the failure inside the region, with a button that tries again.
 *
 * @param {HTMLElement} region
 * @param {Function} request
 * @param {string} failedstring
 */
const showFailure = async(region, request, failedstring) => {
    const [message, retry] = await Promise.all([
        getString(failedstring, 'local_catquiz'),
        getString('retry', 'core'),
    ]);
    region.innerHTML = '';
    const alert = document.createElement('div');
    alert.className = 'alert alert-warning';
    alert.setAttribute('role', 'alert');
    alert.textContent = message + ' ';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-link p-0';
    button.dataset.action = 'local_catquiz-retry';
    button.textContent = retry;
    button.addEventListener('click', () => {
        region.dataset.state = '';
        loadRegion(region, request, failedstring);
    });
    alert.appendChild(button);
    region.appendChild(alert);
};

/**
 * Loads the content of one region once; on failure shows a message with a retry button.
 *
 * @param {HTMLElement} region
 * @param {Function} request Returns the Ajax request ({methodname, args}) for the region.
 * @param {string} failedstring Identifier of the failure message in local_catquiz.
 * @return {Promise<void>}
 */
export const loadRegion = async(region, request, failedstring) => {
    if (region.dataset.state === 'loading' || region.dataset.state === 'loaded') {
        return;
    }
    region.dataset.state = 'loading';
    region.innerHTML = '';
    // Behat and other waiting code wait for the content, not only for the request.
    const pending = new Pending('local_catquiz/lazyregion:load');
    const icon = addIconToContainer(region);
    try {
        const response = await Ajax.call([request(region)])[0];
        await icon;
        // Wrapped: replaceNodeContents() hands the HTML to jQuery, which reads a string that does not
        // start with a tag as a selector - a feedback beginning with text then failed to load.
        // The JavaScript comes as the page would print it, inside <script> tags. Handed over like that,
        // it ran twice - jQuery runs the inner tag, the page the outer one - and every chart was drawn
        // a second time over the table below it. Fragment unwraps it, as core's dynamic tabs do.
        await Templates.replaceNodeContents(
            region,
            '<div>' + response.html + '</div>',
            Fragment.processCollectedJavascript(response.javascript)
        );
        region.dataset.state = 'loaded';
    } catch (error) {
        region.dataset.state = 'failed';
        await showFailure(region, request, failedstring);
        window.console.error(error);
    } finally {
        pending.resolve();
    }
};
