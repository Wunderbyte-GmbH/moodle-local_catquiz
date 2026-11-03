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
 * Jest unit tests for the reloads of the CAT settings form (issue #123).
 *
 * The hidden field triggered_button carries the reason for a reload (template, scale, number of
 * feedback ranges); the technical no-submit button travels in a hidden proxy of its own. The two
 * must not overwrite each other.
 *
 * @package    local_catquiz
 * @copyright  2026 onwards Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init, prepareNoSubmitButton} from '../../amd/src/catquizTestChooser';

/**
 * A form with the elements of the CAT settings that take part in a reload.
 *
 * @return {HTMLFormElement}
 */
const buildForm = () => {
    document.body.innerHTML = `
        <form action="https://example.org/course/modedit.php?update=1">
            <input type="hidden" name="triggered_button" value="">
            <fieldset id="id_catmodelheader">
                <select name="choosetemplate" data-on-change-action="reloadTestForm">
                    <option value="0">Custom test</option>
                    <option value="7">Template</option>
                </select>
                <input type="submit" name="submitcattestoption" value="Reload" data-action="submitCatTest">
            </fieldset>
            <fieldset id="id_catquiz_headercontainer">
                <select name="catquiz_catscales" data-on-change-action="reloadFormFromScaleSelect">
                    <option value="1">Simulation</option>
                    <option value="2">Other</option>
                </select>
                <input type="checkbox" name="catquiz_subscalecheckbox_3" data-on-change-action="reloadFormFromScaleSelect">
                <input type="submit" name="submitcatscaleoption" value="Scale" data-action="submitCatScale">
            </fieldset>
            <fieldset id="id_catquiz_feedback">
                <select name="numberoffeedbackoptionsselect" data-on-change-action="numberOfFeedbacksSubmit">
                    <option value="2">2</option>
                    <option value="3">3</option>
                </select>
                <input type="submit" name="submitnumberoffeedbackoptions" value="numberoffeedbackoptionssubmit"
                    data-action="submitNumberOfFeedbackOptions">
            </fieldset>
            <input type="submit" name="submitbutton" value="Save and display">
        </form>`;
    return document.querySelector('form');
};

/**
 * Changes a field the way a user does and returns the button that was clicked for the reload.
 *
 * @param {HTMLFormElement} form
 * @param {string} name
 * @param {string|boolean} value
 * @return {string|null} Name of the clicked button.
 */
const change = (form, name, value) => {
    let clicked = null;
    form.querySelectorAll('input[type="submit"]').forEach(button => {
        button.click = () => {
            clicked = button.name;
        };
    });
    const field = form.querySelector('[name="' + name + '"]');
    if (field.type === 'checkbox') {
        field.checked = value;
    } else {
        field.value = value;
    }
    field.dispatchEvent(new Event('change', {bubbles: true}));
    return clicked;
};

describe('prepareNoSubmitButton', () => {
    test('leaves the reason for the reload in triggered_button alone', () => {
        const form = buildForm();
        form.querySelector('[name="triggered_button"]').value = 'reloadTestForm';

        prepareNoSubmitButton(form.querySelector('[name="submitcattestoption"]'));

        expect(form.querySelector('[name="triggered_button"]').value).toBe('reloadTestForm');
    });

    test('puts the technical button into a hidden proxy with its value', () => {
        const form = buildForm();

        prepareNoSubmitButton(form.querySelector('[name="submitnumberoffeedbackoptions"]'));

        const proxies = form.querySelectorAll('input[type="hidden"][data-nosubmit-proxy-for="submitnumberoffeedbackoptions"]');
        expect(proxies.length).toBe(1);
        expect(proxies[0].name).toBe('submitnumberoffeedbackoptions');
        expect(proxies[0].value).toBe('numberoffeedbackoptionssubmit');
    });

    test('reuses an existing proxy instead of adding a second one', () => {
        const form = buildForm();
        const button = form.querySelector('[name="submitcattestoption"]');

        prepareNoSubmitButton(button);
        button.value = 'Again';
        prepareNoSubmitButton(button);

        const proxies = form.querySelectorAll('[data-nosubmit-proxy-for="submitcattestoption"]');
        expect(proxies.length).toBe(1);
        expect(proxies[0].value).toBe('Again');
    });

    test('a button without a value is still sent', () => {
        const form = buildForm();
        const button = form.querySelector('[name="submitcatscaleoption"]');
        button.value = '';

        prepareNoSubmitButton(button);

        expect(form.querySelector('[data-nosubmit-proxy-for="submitcatscaleoption"]').value).toBe('1');
    });
});

describe('reloads started by a change', () => {
    test.each([
        ['choosetemplate', '7', 'reloadTestForm', 'submitcattestoption'],
        ['catquiz_catscales', '2', 'reloadFormFromScaleSelect', 'submitcatscaleoption'],
        ['catquiz_subscalecheckbox_3', true, 'reloadFormFromScaleSelect', 'submitcatscaleoption'],
        ['catquiz_subscalecheckbox_3', false, 'reloadFormFromScaleSelect', 'submitcatscaleoption'],
        ['numberoffeedbackoptionsselect', '3', 'numberOfFeedbacksSubmit', 'submitnumberoffeedbackoptions'],
    ])('%s = %s: triggered_button says %s, %s is sent', (name, value, reason, button) => {
        const form = buildForm();
        init();

        const clicked = change(form, name, value);

        expect(form.querySelector('[name="triggered_button"]').value).toBe(reason);
        expect(clicked).toBe(button);
        expect(form.querySelector('[data-nosubmit-proxy-for="' + button + '"]')).not.toBeNull();
        // The reload jumps back to the section it started in.
        expect(new URL(form.action).hash).not.toBe('');
    });

    test('a normal save is not a reload: no reason, no proxy', () => {
        const form = buildForm();
        init();

        expect(form.querySelector('[name="triggered_button"]').value).toBe('');
        expect(form.querySelectorAll('[data-nosubmit-proxy-for]').length).toBe(0);
    });
});
