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
 * Statistics shortcode: each chart is built when the tab holding it is shown (issue #85).
 *
 * The visible tab loads at once, every other one when it is opened. Each chart is one request: one
 * that fails shows a retry button and leaves the others standing.
 *
 * @module     local_catquiz/statistics
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {loadRegion as loadLazyRegion} from 'local_catquiz/lazyregion';

const SELECTORS = {
    CONTAINER: '[data-region="local_catquiz-statistics"]',
    LAZY: '[data-region="local_catquiz-lazystatistics"]',
    // Bootstrap 5 (Moodle 5) and Bootstrap 4 (Moodle 4.5) markup.
    TAB: 'a[data-bs-toggle="tab"], a[data-toggle="tab"]',
};

/**
 * The request that builds one chart.
 *
 * @param {HTMLElement} region
 * @return {Object}
 */
const request = (region) => ({
    methodname: 'local_catquiz_render_statistics_chart',
    args: {
        scope: JSON.parse(region.dataset.scope),
        chart: region.dataset.chart,
    },
});

/**
 * Loads the chart regions inside an element.
 *
 * @param {HTMLElement|null} element
 */
const loadRegionsIn = (element) => {
    if (element) {
        element.querySelectorAll(SELECTORS.LAZY).forEach(
            region => loadLazyRegion(region, request, 'statisticschartloadfailed')
        );
    }
};

/** @var {boolean} Whether the tab listener is in place. */
let listening = false;

/**
 * Loads the charts of the visible tabs; the others load when their tab is shown.
 */
export const init = () => {
    document.querySelectorAll(SELECTORS.CONTAINER).forEach(container => {
        container.querySelectorAll('.tab-pane.active').forEach(loadRegionsIn);
    });
    if (listening) {
        return;
    }
    listening = true;
    document.addEventListener('click', e => {
        const tab = e.target.closest(SELECTORS.TAB);
        const target = tab ? tab.getAttribute('href') : null;
        if (!target || !target.startsWith('#')) {
            return;
        }
        const pane = document.getElementById(target.substring(1));
        if (pane && pane.closest(SELECTORS.CONTAINER)) {
            loadRegionsIn(pane);
        }
    });
};
