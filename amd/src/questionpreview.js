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
 * Lazily loads a question preview into a core modal (issue #20).
 *
 * The question lists used to embed the full text of every row into a hidden modal.
 * The rows now carry only the question id; the text is fetched here when the user
 * actually opens a preview.
 *
 * @module     local_catquiz/questionpreview
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';
import {getString} from 'core/str';

const SELECTORS = {
    TRIGGER: '[data-action="catquiz-question-preview"]',
};

/**
 * Fetches one question and shows it in a modal.
 *
 * @param {Number} questionid
 * @returns {Promise}
 */
const showPreview = (questionid) => {
    return Promise.all([
        Ajax.call([{
            methodname: 'local_catquiz_get_question_preview',
            args: {questionid: questionid},
        }])[0],
        getString('previewquestion', 'local_catquiz'),
    ]).then(([question, title]) => {
        return Modal.create({
            title: question.name || title,
            body: question.questiontext,
            large: true,
            show: true,
            removeOnClose: true,
        });
    }).catch(Notification.exception);
};

/**
 * Binds one delegated listener per page.
 *
 * The tables repaint themselves on sort, filter and pagination, so binding to
 * individual rows would lose the handler on the next repaint. Delegation on the
 * document survives that and costs one listener instead of one per row.
 *
 * @returns {void}
 */
export const init = () => {
    if (document.body.dataset.catquizPreviewBound) {
        return;
    }
    document.body.dataset.catquizPreviewBound = '1';

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest(SELECTORS.TRIGGER);
        if (!trigger) {
            return;
        }
        e.preventDefault();

        const questionid = parseInt(trigger.dataset.questionid, 10);
        if (!questionid) {
            return;
        }

        showPreview(questionid);
    });
};
