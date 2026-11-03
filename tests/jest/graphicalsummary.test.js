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
 * Jest unit tests for the "show question" modal handler.
 *
 * Verifies that the click handler opens a modal on success, always clears the
 * loading spinner and reports the error on an AJAX rejection (so the spinner can
 * never hang), and does not start parallel requests on a double click.
 *
 * @package    local_catquiz
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init} from '../../amd/src/graphicalsummary';
import Ajax from 'core/ajax';
import Modal from 'core/modal';
import {addIconToContainerWithPromise} from 'core/loadingicon';
import Templates from 'core/templates';
import Notification from 'core/notification';
import Fragment from 'core/fragment';

/**
 * Let the pending promise chain of the async click handler settle.
 *
 * @return {Promise<void>}
 */
const flush = async() => {
    for (let i = 0; i < 8; i++) {
        // eslint-disable-next-line no-await-in-loop
        await new Promise(resolve => setTimeout(resolve, 0));
    }
};

/**
 * Build a table row with a single clickable cell and return that element.
 *
 * @return {HTMLElement}
 */
const setupDom = () => {
    document.body.innerHTML =
        '<table><tbody><tr><td>'
        + '<span class="clickable" data-attemptid="1" data-slot="2"'
        + ' data-questionattemptid="3" data-name="Q1"></span>'
        + '</td></tr></tbody></table>';
    return document.querySelector('.clickable');
};

/**
 * A minimal modal stub.
 *
 * @return {object}
 */
const fakeModal = () => ({
    setRemoveOnClose: jest.fn(),
    show: jest.fn().mockResolvedValue(undefined),
    getBody: jest.fn(() => [document.createElement('div')]),
});

beforeEach(() => {
    jest.clearAllMocks();
    addIconToContainerWithPromise.mockReturnValue({resolve: jest.fn()});
    Templates.appendNodeContents.mockResolvedValue(undefined);
});

test('successful AJAX opens the modal and clears the spinner', async() => {
    const element = setupDom();
    const iconPromise = {resolve: jest.fn()};
    addIconToContainerWithPromise.mockReturnValue(iconPromise);
    Ajax.call.mockReturnValue([Promise.resolve({questionhtml: '<p>Q</p>', javascript: ''})]);
    const modal = fakeModal();
    Modal.create.mockResolvedValue(modal);

    await init();
    element.click();
    await flush();

    expect(Ajax.call).toHaveBeenCalledTimes(1);
    expect(Modal.create).toHaveBeenCalledTimes(1);
    expect(modal.show).toHaveBeenCalledTimes(1);
    expect(iconPromise.resolve).toHaveBeenCalledTimes(1);
    expect(Notification.exception).not.toHaveBeenCalled();
    expect(element.hasAttribute('aria-busy')).toBe(false);
});

test('AJAX rejection clears the spinner, shows a notification and opens no modal', async() => {
    const element = setupDom();
    const iconPromise = {resolve: jest.fn()};
    addIconToContainerWithPromise.mockReturnValue(iconPromise);
    const error = new Error('boom');
    Ajax.call.mockReturnValue([Promise.reject(error)]);

    await init();
    element.click();
    await flush();

    expect(iconPromise.resolve).toHaveBeenCalledTimes(1);
    expect(Notification.exception).toHaveBeenCalledWith(error);
    expect(Modal.create).not.toHaveBeenCalled();
    expect(element.hasAttribute('aria-busy')).toBe(false);
});

test('a message from the server is shown in the dialogue, not as an exception (issue #139)', async() => {
    const element = setupDom();
    const iconPromise = {resolve: jest.fn()};
    addIconToContainerWithPromise.mockReturnValue(iconPromise);
    const body = document.createElement('div');
    const modal = {...fakeModal(), getBody: jest.fn(() => [body])};
    Modal.create.mockResolvedValue(modal);
    Ajax.call.mockReturnValue([Promise.reject({
        errorcode: 'reviewquestionunavailable',
        message: 'This question of the attempt is no longer available.',
    })]);

    await init();
    element.click();
    await flush();

    expect(Notification.exception).not.toHaveBeenCalled();
    expect(Modal.create).toHaveBeenCalledWith({title: 'Q1', body: ''});
    expect(modal.show).toHaveBeenCalledTimes(1);
    expect(body.querySelector('[role="alert"]').textContent)
        .toBe('This question of the attempt is no longer available.');
    expect(iconPromise.resolve).toHaveBeenCalledTimes(1);
    expect(element.hasAttribute('aria-busy')).toBe(false);
    expect(element.dataset.loading).toBeUndefined();
});

test('the collected JavaScript is unwrapped before it runs, so it runs once (issue #139)', async() => {
    const element = setupDom();
    addIconToContainerWithPromise.mockReturnValue({resolve: jest.fn()});
    Ajax.call.mockReturnValue([Promise.resolve({
        questionhtml: '<p>Q</p>',
        javascript: '<script>\n//<![CDATA[\nrun();\n//]]>\n</script>',
    })]);
    Fragment.processCollectedJavascript.mockReturnValueOnce(' run();');
    Modal.create.mockResolvedValue(fakeModal());

    await init();
    element.click();
    await flush();

    expect(Fragment.processCollectedJavascript).toHaveBeenCalledWith('<script>\n//<![CDATA[\nrun();\n//]]>\n</script>');
    expect(Templates.appendNodeContents).toHaveBeenCalledWith(expect.anything(), '<p>Q</p>', ' run();');
});

test('a double click does not start parallel requests', async() => {
    const element = setupDom();
    let resolveAjax;
    Ajax.call.mockReturnValue([new Promise(resolve => {
        resolveAjax = resolve;
    })]);
    Modal.create.mockResolvedValue(fakeModal());

    await init();
    element.click();
    element.click();
    await flush();

    expect(Ajax.call).toHaveBeenCalledTimes(1);

    resolveAjax({questionhtml: '<p>Q</p>', javascript: ''});
    await flush();
});
