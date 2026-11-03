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
 * The feedback of the CAT settings, one scale at a time (issue #51).
 *
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init, serialisePanel, switchTo, valuesFor, copyScaleSettings, PANELLOADED} from '../../amd/src/feedbackpanel';
import Fragment from 'core/fragment';
import Templates from 'core/templates';
import Notification from 'core/notification';

/**
 * A promise as core/fragment returns it: then() gets html and js.
 *
 * @param {string} html
 * @param {string} js
 * @returns {Object}
 */
const fragmentResult = (html, js) => ({
    then: fn => {
        fn(html, js);
        return {catch: () => undefined};
    },
});

/**
 * A failed fragment.
 *
 * @param {Error} error
 * @returns {Object}
 */
const fragmentFailure = error => ({
    then: () => ({catch: fn => fn(error)}),
});

/**
 * The fields of scale 2, range 1, as the form has them.
 *
 * @param {string} text
 * @returns {string}
 */
const fieldsOfScale2 = text =>
    '<input type="hidden" name="catquiz_scalereportcheckbox_2" value="0">'
    + '<input type="checkbox" name="catquiz_scalereportcheckbox_2" value="1" checked>'
    + '<input type="text" name="feedback_scaleid_limit_upper_2_1" value="-1,5">'
    + '<textarea name="feedbackeditor_scaleid_2_1[text]">' + text + '</textarea>'
    + '<input type="hidden" name="feedbackeditor_scaleid_2_1[format]" value="1">'
    + '<input type="hidden" name="feedbackeditor_scaleid_2_1[itemid]" value="77">'
    + '<select name="catquiz_courses_2_1[]" multiple><option value="0">-</option>'
    + '<option value="5" selected>C5</option><option value="6">C6</option></select>'
    + '<select name="wb_colourpicker_2_1"><option value="3">r</option><option value="6" selected>g</option></select>'
    + '<input type="hidden" name="selectedcolour" value="">';

/**
 * The carrier of a scale - a hidden field, which the form puts at its start.
 *
 * @param {number} scaleid
 * @param {boolean} edited
 * @param {Object} values
 * @returns {string}
 */
const carrierField = (scaleid, edited, values) =>
    '<input type="hidden" name="catquiz_feedbackcarrier_' + scaleid + '" value=\'' + JSON.stringify(values) + '\''
    + (edited ? ' disabled' : '') + '>';

/**
 * A panel of a scale.
 *
 * @param {number} scaleid
 * @param {boolean} edited
 * @param {string} fields
 * @returns {string}
 */
const panel = (scaleid, edited, fields) =>
    '<div id="catquiz_feedback_collapse_' + scaleid + '" class="collapse">'
    + '<div data-region="catquiz-feedback-panel" data-scaleid="' + scaleid + '" data-edited="' + (edited ? 1 : 0) + '">'
    + '<div data-region="catquiz-feedback-carrier" class="' + (edited ? 'd-none' : '') + '">'
    + '<button type="submit" name="catquiz_feedbackedit_' + scaleid + '" data-action="catquizEditFeedback"'
    + ' data-scaleid="' + scaleid + '">Edit</button>'
    + '</div><div data-region="catquiz-feedback-fields">' + fields + '</div></div></div>';

beforeEach(() => {
    jest.clearAllMocks();
    delete window.tinymce;
    document.body.innerHTML = '<form><input type="hidden" name="catquiz_feedbackeditscale" value="2">'
        + carrierField(2, true, {})
        + carrierField(3, false, {'feedback_scaleid_limit_upper_3_1': 1, 'feedbacklegend_scaleid_3_1': 'kept'})
        + '<input type="checkbox" name="catquiz_subscalecheckbox_3" checked>'
        + '<input type="checkbox" name="catquiz_subscalecheckbox_4">'
        + '<div data-region="catquiz-feedback" data-contextid="12" data-rootscaleid="2" data-nranges="1"'
        + ' data-cmid="30" data-instanceid="40">'
        + '<button type="button" data-bs-toggle="collapse" data-bs-target="#catquiz_feedback_collapse_3">Head 3</button>'
        + panel(2, true, fieldsOfScale2('Saved text'))
        + panel(3, false, '')
        + '</div></form>';
    Templates.replaceNodeContents.mockResolvedValue(undefined);
    init();
});

const root = () => document.querySelector('[data-region="catquiz-feedback"]');
const carrier = id => document.querySelector('input[name="catquiz_feedbackcarrier_' + id + '"]');
const panelOf = id => document.querySelector('[data-region="catquiz-feedback-panel"][data-scaleid="' + id + '"]');

test('the fields of a panel are read as its carrier keeps them', () => {
    const values = serialisePanel(panelOf(2));
    expect(values).toEqual({
        'catquiz_scalereportcheckbox_2': 1,
        'feedback_scaleid_limit_upper_2_1': '-1,5',
        'feedbackeditor_scaleid_2_1': {text: 'Saved text', format: 1, itemid: 77},
        'catquiz_courses_2_1': ['5'],
        'wb_colourpicker_2_1': '6',
    });
});

test('an editor is read from TinyMCE, which has not written back its text yet', () => {
    document.querySelector('textarea').id = 'id_feedbackeditor_scaleid_2_1';
    window.tinymce = {get: id => (id === 'id_feedbackeditor_scaleid_2_1' ? {getContent: () => '<p>Typed</p>'} : null)};
    expect(serialisePanel(panelOf(2))['feedbackeditor_scaleid_2_1'].text).toBe('<p>Typed</p>');
});

test('opening a scale loads its fields with its carrier and keeps what the other one held, unsaved', async() => {
    document.querySelector('textarea').value = 'Changed, not saved';
    Fragment.loadFragment.mockReturnValue(fragmentResult('<p>fields of 3</p>', 'js();'));
    const loaded = jest.fn();
    document.addEventListener(PANELLOADED, loaded);

    expect(await switchTo(root(), 3)).toBe(true);

    expect(Fragment.loadFragment).toHaveBeenCalledWith('local_catquiz', 'feedbackpanel', 12, {
        scaleid: '3', rootscaleid: '2', nranges: '1', cmid: '30', instanceid: '40',
        values: JSON.stringify({'feedback_scaleid_limit_upper_3_1': 1, 'feedbacklegend_scaleid_3_1': 'kept'}),
    });
    expect(Templates.replaceNodeContents).toHaveBeenCalledWith(
        panelOf(3).querySelector('[data-region="catquiz-feedback-fields"]'), '<div><p>fields of 3</p></div>', 'js();'
    );
    expect(loaded).toHaveBeenCalledTimes(1);
    // The scale edited before: its fields are gone, its carrier holds them and is sent.
    expect(panelOf(2).querySelector('[data-region="catquiz-feedback-fields"]').innerHTML).toBe('');
    expect(carrier(2).disabled).toBe(false);
    expect(JSON.parse(carrier(2).value)['feedbackeditor_scaleid_2_1']).toEqual(
        {text: 'Changed, not saved', format: 1, itemid: 77}
    );
    expect(panelOf(2).dataset.edited).toBe('0');
    expect(panelOf(2).querySelector('[data-region="catquiz-feedback-carrier"]').classList.contains('d-none')).toBe(false);
    // The scale opened: its carrier is not sent, its fields are.
    expect(carrier(3).disabled).toBe(true);
    expect(panelOf(3).dataset.edited).toBe('1');
    expect(document.querySelector('input[name="catquiz_feedbackeditscale"]').value).toBe('3');
});

test('a scale that fails to load leaves the one being edited as it is', async() => {
    Fragment.loadFragment.mockReturnValue(fragmentFailure(new Error('gone')));

    expect(await switchTo(root(), 3)).toBe(false);

    expect(Notification.exception).toHaveBeenCalledTimes(1);
    expect(panelOf(2).querySelector('textarea')).not.toBeNull();
    expect(carrier(2).disabled).toBe(true);
    expect(carrier(3).disabled).toBe(false);
    expect(document.querySelector('input[name="catquiz_feedbackeditscale"]').value).toBe('2');
});

test('the scale already being edited is not loaded again', async() => {
    expect(await switchTo(root(), 2)).toBe(true);
    expect(Fragment.loadFragment).not.toHaveBeenCalled();
});

test('opening the header of a closed scale opens the scale; its button does too, without reloading', async() => {
    Fragment.loadFragment.mockReturnValue(fragmentResult('<p>3</p>', ''));
    document.querySelector('[data-bs-target="#catquiz_feedback_collapse_3"]').click();
    await switchTo(root(), 3);
    expect(Fragment.loadFragment).toHaveBeenCalledTimes(1);

    const event = new MouseEvent('click', {bubbles: true, cancelable: true});
    document.querySelector('[name="catquiz_feedbackedit_2"]').dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
});

test('the values of a scale are renamed for another scale', () => {
    expect(valuesFor({
        'feedbackeditor_scaleid_2_1': {text: 'a'},
        'catquiz_scalereportcheckbox_2': 1,
        'catquiz_courses_22_1': ['9'],
    }, '2', '3')).toEqual({
        'feedbackeditor_scaleid_3_1': {text: 'a'},
        'catquiz_scalereportcheckbox_3': 1,
    });
});

test('settings are copied into the carriers of the subscales shown', async() => {
    const copied = await copyScaleSettings(document.querySelector('form'), '2', ['3', '4']);

    expect(copied).toBe(1);
    const values = JSON.parse(carrier(3).value);
    expect(values['feedbackeditor_scaleid_3_1']).toEqual({text: 'Saved text', format: 1, itemid: 77});
    expect(values['feedback_scaleid_limit_upper_3_1']).toBe('-1,5');
    expect(values['feedbacklegend_scaleid_3_1']).toBe('kept');
});
