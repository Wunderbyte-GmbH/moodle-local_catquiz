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
 * Feedback tabs: log which tab is opened, and load a tab's content when it is opened (issue #85).
 *
 * Collapsed attempts of the shortcode catquizfeedback load their feedback when they are opened.
 *
 * @module     local_catquiz/feedback
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {loadRegion as loadLazyRegion} from 'local_catquiz/lazyregion';

const SELECTORS = {
    LAZY: '[data-region="local_catquiz-lazyfeedback"]',
    // Bootstrap 5 (Moodle 5) and Bootstrap 4 (Moodle 4.5) markup.
    COLLAPSE: '[data-bs-toggle="collapse"][data-bs-target], [data-toggle="collapse"][data-target]',
};

/**
 * The request that fills a region: one tab, or the whole feedback of a collapsed attempt.
 *
 * @param {HTMLElement} region
 * @return {Object}
 */
const request = (region) => ({
    methodname: 'local_catquiz_render_feedback_tab',
    args: {
        attemptid: parseInt(region.dataset.attemptid, 10),
        generatorname: region.dataset.generatorname,
        audience: region.dataset.audience,
    },
});

/**
 * Loads the content of one lazy region; on failure shows a message with a retry button.
 *
 * @param {HTMLElement} region
 * @return {Promise<void>}
 */
const loadRegion = (region) => loadLazyRegion(region, request, 'feedbacktabloadfailed');

/**
 * Loads the lazy regions directly inside an element - not those of tabs nested in it.
 *
 * @param {HTMLElement|null} element
 */
const loadRegionsIn = (element) => {
    if (!element) {
        return;
    }
    element.querySelectorAll(SELECTORS.LAZY).forEach(region => {
        const pane = region.closest('.tab-pane');
        if (pane && pane !== element && element.contains(pane)) {
            // A tab of the attempt: it loads when the tab is opened.
            return;
        }
        loadRegion(region);
    });
};

/**
 * Loads the lazy regions inside the pane a tab shows.
 *
 * @param {HTMLElement} tab
 */
const loadPaneOf = (tab) => {
    const target = tab.getAttribute('href');
    if (!target || !target.startsWith('#')) {
        return;
    }
    loadRegionsIn(document.getElementById(target.substring(1)));
};

/**
 * Collapsed attempts: load the feedback when the attempt is opened.
 */
const initCollapsedAttempts = () => {
    document.querySelectorAll(SELECTORS.COLLAPSE).forEach(button => {
        if (button.dataset.localCatquizLazy) {
            return;
        }
        const target = button.dataset.bsTarget || button.dataset.target;
        if (!target || !target.startsWith('#')) {
            return;
        }
        const body = document.getElementById(target.substring(1));
        if (!body || !body.querySelector(SELECTORS.LAZY)) {
            return;
        }
        button.dataset.localCatquizLazy = '1';
        button.addEventListener('click', () => loadRegionsIn(body));
    });
};

/**
 * Add event listeners to feedback tabs in order to call a webservice.
 */
export const init = () => {
    initCollapsedAttempts();
    const tabs = document.querySelectorAll('a.feedbacktab');
    tabs.forEach(tab => {
        if (tab.initialized) {
            return;
        }
        tab.initialized = true;

        tab.addEventListener('click', e => {
            e.preventDefault();
            loadPaneOf(tab);
            const feedback = e.target.dataset.feedbackname;
            const feedbacktranslated = e.target.dataset.feedbacknameTranslated;
            if (!feedback) {
                return;
            }

            // Try to get the attemptid from the data-attemptid attribute. Use the query parameters attempt and attemptid as
            // fallback values.
            const attemptid = e.target.dataset.attemptid
            || (new URLSearchParams(window.location.search)).get('attempt')
            || (new URLSearchParams(window.location.search)).get('attemptid');
            Ajax.call([{
                methodname: 'local_catquiz_feedback_tab_clicked',
                args: {attemptid, feedback, feedbacktranslated}
            }]);
        });
    });
};
