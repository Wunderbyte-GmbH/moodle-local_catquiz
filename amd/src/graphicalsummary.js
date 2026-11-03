
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
import Fragment from 'core/fragment';
// The modal itself, not core/modal_factory: the factory was deprecated in Moodle 4.3 and is gone in 5.2,
// where the question modal no longer opened at all.
import Modal from 'core/modal';
import {addIconToContainerWithPromise} from 'core/loadingicon';
import Templates from 'core/templates';
import Notification from 'core/notification';

/**
 * Add event listeners.
 */
export const init = async() => {
    const rows = document.querySelectorAll('tr>td>.clickable');
    rows.forEach(row => {
        if (row.initialized) {
            return;
        }
        row.initialized = true;
        row.addEventListener('click', async function() {
            // Prevent parallel requests and duplicate modals while one question
            // is already loading.
            if (this.dataset.loading === '1') {
                return;
            }
            this.dataset.loading = '1';
            this.setAttribute('aria-busy', 'true');
            // Show loader icon until we have the question.
            const iconPromise = addIconToContainerWithPromise(row);
            const name = this.getAttribute('data-name');
            try {
                const attemptid = this.getAttribute('data-attemptid');
                const slot = this.getAttribute('data-slot');
                const questionattemptid = this.getAttribute('data-questionattemptid') || 0;
                const questiondata = await fetchQuestionData(slot, attemptid, questionattemptid);
                const modal = await Modal.create({
                    title: name,
                    body: '',
                });
                // Remove the modal from the DOM when it is closed, so opening the
                // next question creates a fresh modal instead of leaving stale,
                // hidden ones behind.
                modal.setRemoveOnClose(true);
                await modal.show();
                // Write into THIS modal's own body node, never a global selector: a
                // global lookup would target the first (now hidden) modal from the
                // second question onwards, leaving later modals empty.
                const bodyElement = modal.getBody()[0];
                await Templates.appendNodeContents(bodyElement, questiondata.questionhtml, questiondata.javascript);
            } catch (error) {
                // Never leave a spinner running. A refusal or a question that no longer
                // exists comes from the server with a message for the user: it is shown in
                // the dialogue the user asked for (issue #139). Anything else - a broken
                // script, a failed request without a message - is reported as an exception.
                if (error && error.errorcode && error.message) {
                    await showMessage(name, error.message).catch(Notification.exception);
                } else {
                    Notification.exception(error);
                }
            } finally {
                // Always clear the loader and busy state, even on error.
                iconPromise.resolve();
                this.removeAttribute('aria-busy');
                delete this.dataset.loading;
            }
        });
    });
};

/**
 * Shows a message from the server in a dialogue titled like the question.
 *
 * @param {string} title Name of the question
 * @param {string} message Message for the user
 * @return {Promise<void>}
 */
const showMessage = async(title, message) => {
    const modal = await Modal.create({
        title: title,
        body: '',
    });
    modal.setRemoveOnClose(true);
    await modal.show();
    const note = document.createElement('p');
    note.className = 'alert alert-warning catquiz-question-unavailable';
    note.setAttribute('role', 'alert');
    note.textContent = message;
    modal.getBody()[0].appendChild(note);
};

/**
 * @param {integer} slot Question slot
 * @param {integer} attemptid The attempt ID
 * @param {integer} questionattemptid The question attempt ID (0 to skip the check)
 * @return string
 */
const fetchQuestionData = async(slot, attemptid, questionattemptid) => {
    let data = await Ajax.call([{
        methodname: 'local_catquiz_render_question_with_response',
        args: {
            slot: slot,
            attemptid: attemptid,
            questionattemptid: questionattemptid,
        }
    }])[0];
    return {
        questionhtml: data.questionhtml,
        // Collected page JavaScript arrives inside <script> tags; unwrapped it runs once, not twice
        // (the question's own scripts - STACK, MathJax - were bound twice in the modal).
        javascript: Fragment.processCollectedJavascript(data.javascript),
    };
};
