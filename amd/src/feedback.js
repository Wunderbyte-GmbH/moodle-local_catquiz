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

/*
 * @package    local_catquiz
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Add event listeners to feedback tabs in order to call a webservice.
 */
export const init = () => {
    const tabs = document.querySelectorAll('a.feedbacktab');
    tabs.forEach(tab => {
        if (tab.initialized) {
            return;
        }
        tab.initialized = true;

        tab.addEventListener('click', e => {
            e.preventDefault();
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
