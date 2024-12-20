
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
import ModalFactory from 'core/modal_factory';
import {addIconToContainerWithPromise} from 'core/loadingicon';
import Templates from 'core/templates';

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
            // Show loader icon until we have the question.
            let iconPromise = addIconToContainerWithPromise(row);
            const attemptid = this.getAttribute('data-attemptid');
            const slot = this.getAttribute('data-slot');
            const name = this.getAttribute('data-name');
            const questiondata = await fetchQuestionData(slot, attemptid);
            // Hide the loader icon by resolving it.
            iconPromise.resolve();
            const modal = await ModalFactory.create({
                title: name,
                body: '<div data-id="modalbodyquestion"></div>',
            });
            await modal.show();
            const element = document.querySelector('[data-id="modalbodyquestion"]');
            Templates.appendNodeContents(element, questiondata.questionhtml, questiondata.javascript);
        });
    });
};

/**
 * @param {integer} slot Question slot
 * @param {integer} attemptid The attempt ID
 * @return string
 */
const fetchQuestionData = async(slot, attemptid) => {
    let data = await Ajax.call([{
        methodname: 'local_catquiz_render_question_with_response',
        args: {
            slot: slot,
            attemptid: attemptid,
        }
    }])[0];
    return {
        questionhtml: data.questionhtml,
        javascript: data.javascript,
    };
};
