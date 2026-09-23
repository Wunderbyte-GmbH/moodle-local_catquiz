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
 * Jest unit tests for the lazy question preview (issue #20).
 *
 * Verifies that nothing is requested until a trigger is clicked, that the modal is
 * filled from the web service response, that an AJAX rejection is reported instead
 * of failing silently, and that the delegated listener is bound only once.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init} from '../../amd/src/questionpreview';
import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';
import {getString} from 'core/str';

/**
 * Lets the pending promise chain settle.
 *
 * @return {Promise<void>}
 */
const flush = async() => {
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
};

describe('local_catquiz/questionpreview', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        document.body.innerHTML =
            '<a href="#" data-action="catquiz-question-preview" data-questionid="42">Question name</a>';
        // The bound flag is deliberately NOT reset between tests: the listener is
        // delegated to the document, which jsdom keeps for the whole file. Clearing
        // the flag would let every init() add another listener, so a single click
        // would fire one request per preceding test - an artefact of the harness,
        // not of the module.

        getString.mockResolvedValue('Preview question');
        Modal.create.mockResolvedValue({});
    });

    it('requests nothing until a trigger is clicked', () => {
        init();

        expect(Ajax.call).not.toHaveBeenCalled();
        expect(Modal.create).not.toHaveBeenCalled();
    });

    it('loads the question text and shows it in a modal', async() => {
        Ajax.call.mockReturnValue([Promise.resolve({
            questionid: 42,
            name: 'Question name',
            questiontext: '<p>The body</p>'
        })]);

        init();
        document.querySelector('[data-action="catquiz-question-preview"]').click();
        await flush();

        expect(Ajax.call).toHaveBeenCalledWith([{
            methodname: 'local_catquiz_get_question_preview',
            args: {questionid: 42}
        }]);
        expect(Modal.create).toHaveBeenCalledWith(expect.objectContaining({
            body: '<p>The body</p>'
        }));
    });

    it('reports a rejected request instead of failing silently', async() => {
        Ajax.call.mockReturnValue([Promise.reject(new Error('nope'))]);

        init();
        document.querySelector('[data-action="catquiz-question-preview"]').click();
        await flush();

        expect(Notification.exception).toHaveBeenCalled();
        expect(Modal.create).not.toHaveBeenCalled();
    });

    it('binds the delegated listener only once', async() => {
        Ajax.call.mockReturnValue([Promise.resolve({
            questionid: 42,
            name: 'Question name',
            questiontext: '<p>The body</p>'
        })]);

        // The tables repaint on sort, filter and pagination, so init() can run more
        // than once on the same page. A second binding would fire two requests per
        // click.
        init();
        init();
        document.querySelector('[data-action="catquiz-question-preview"]').click();
        await flush();

        expect(Ajax.call).toHaveBeenCalledTimes(1);
    });
});
