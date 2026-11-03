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
 * JavaScript for mod_form to reload when a CAT model has been chosen.
 *
 * @module     mod_adaptivequiz/catquizTestChooser
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {copyScaleSettings, PANELLOADED} from 'local_catquiz/feedbackpanel';

const SELECTORS = {
    CATTESTCHOOSER: '[data-on-change-action]',
    CATTESTSUBMIT: '[data-action="submitCatTest"]',
    CATSCALESUBMIT: '[data-action="submitCatScale"]',
    CATSCALESUBMITCONTAINER: '[id="fitem_id_submitcatscaleoption"]',
    CATTESTCHECKBOXES: 'input[name^="catquiz_subscalecheckbox"]',
    REPORTSCALECHECKBOXES: 'input[id^="id_catquiz_scalereportcheckbox"]',
    NUMBEROFFEEDBACKSSUBMIT: '[data-action="submitNumberOfFeedbackOptions"]',
    FEEDBACKVALUESSUBMIT: '[data-action="submitFeedbackValues"]'
};

/**
 * Initialise it all.
 */
export const init = () => {

    const selectors = document.querySelectorAll(SELECTORS.CATTESTCHOOSER);
    const checkboxes = document.querySelectorAll(SELECTORS.CATTESTCHECKBOXES);
    const reportscalecheckboxes = document.querySelectorAll(SELECTORS.REPORTSCALECHECKBOXES);
    const feedbacksubmitbuttons = document.querySelectorAll(SELECTORS.FEEDBACKVALUESSUBMIT);

    var elements = new Set([
        ...selectors,
        ...checkboxes
    ]);
    if (!elements) {
        return;
    }

    if (elements.length === 0) {
        return;
    }
    elements.forEach(selector =>
        selector.addEventListener('change', e => {
            // Setting defines if reload should be triggered automatically.
            if (e.target.dataset.manualreload) {
                let submitbuttoncontainer = document.querySelector(SELECTORS.CATSCALESUBMITCONTAINER);
                submitbuttoncontainer.classList.remove('hidden');

                let submitbutton = document.querySelector(SELECTORS.CATSCALESUBMIT);
                submitbutton.classList.remove('btn-primary');
                submitbutton.classList.add('btn-danger');
                submitbutton.classList.remove('hidden');
                return;
            }
            // The field carries the reason for the reload, and it is set here - the one place where
            // that reason is known. The technical no-submit button travels separately under its own
            // name (see prepareNoSubmitButton()); the two must not share this field.
            let triggeredButtonField = document.getElementsByName('triggered_button')[0];
            const action = e.target.dataset.onChangeAction;
            triggeredButtonField.value = action || '';

            switch (action) {
                case 'reloadTestForm':
                    clickNoSubmitButton(e.target, SELECTORS.CATTESTSUBMIT);
                    break;
                case 'reloadFormFromScaleSelect':
                    clickNoSubmitButton(e.target, SELECTORS.CATSCALESUBMIT);
                    break;
                case 'numberOfFeedbacksSubmit':
                    clickNoSubmitButton(e.target, SELECTORS.NUMBEROFFEEDBACKSSUBMIT);
                    break;
            }

        })
    );

    // Add a listener to the report checkboxes
    var checkboxelements = new Set([
        ...reportscalecheckboxes
    ]);
    feedbacksubmitbuttons.forEach(button => {
        button.addEventListener('click', e => {
            e.preventDefault();
            copySettingsToSubscales(button);
        });
    });

    // On the first run when the page is loaded set the status according to saved fields. The fields of
    // a scale come and go as it is opened (issue #51): the listeners are on the document, and the
    // status is set for the fields loaded.
    checkboxelements.forEach(selector => setCardDisabledStatus(selector));
    if (!document.body.dataset.catquizReportListeners) {
        document.body.dataset.catquizReportListeners = '1';
        document.addEventListener('change', e => {
            if (e.target.matches && e.target.matches(SELECTORS.REPORTSCALECHECKBOXES)) {
                setCardDisabledStatus(e.target);
            }
        });
        document.addEventListener(PANELLOADED, e => {
            e.target.querySelectorAll(SELECTORS.REPORTSCALECHECKBOXES).forEach(checkbox => setCardDisabledStatus(checkbox));
        });
    }
};

/**
 * Checks the report scale checkbox and disables/enables the input fields accordingly
 *
 * @param {HTMLElement} element
 */
function setCardDisabledStatus(element) {
    let reportScale = element.checked;
    let ownId = element.id || element.name;
    // Get the closest parent.
    let cardBody = element.closest('.card-body');
    if (!reportScale) {
        cardBody.classList.add('card-body-disabled');
    } else {
        cardBody.classList.remove('card-body-disabled');
    }
    // We want to just disable the form fields for the currently selected scale, not the nested scales.
    let currentScaleFields = [...cardBody.children].filter(c => !c.id.match(/^accordion/));

    currentScaleFields.forEach(element => {
        // Add or remove a 'disabled' class to all child input elements.
        Array.from(element.getElementsByTagName('input'))
            .forEach((i) => {
                if (i.id == ownId) {
                    return;
                }
                if (!reportScale) {
                    i.classList.add('disabled');
                } else {
                    i.classList.remove('disabled');
                }
            });

        // Set the 'contenteditable' attribute of the text editor to disable/enable editing.
        Array.from(element.getElementsByClassName('editor_atto_content'))
            .forEach((el) => {
                el.setAttribute('contenteditable', reportScale);
            });
    });
}

/**
 * No Submit Button triggered.
 * @param {HTMLElement} element
 * @param {string} buttonselector
 */
function clickNoSubmitButton(element, buttonselector) {

    const form = element.closest('form');
    // Find container for query selector.
    const submitCatTest = form.querySelector(buttonselector);
    const fieldset = submitCatTest.closest('fieldset');

    const url = new URL(form.action);
    url.hash = fieldset.id;

    form.action = url.toString();
    prepareNoSubmitButton(submitCatTest);
    submitCatTest.click();
}

/**
 * Ensures no-submit buttons are consistently present in the submitted payload.
 * Exported for the unit tests (issue #123).
 *
 * @param {HTMLElement} button
 */
export function prepareNoSubmitButton(button) {
    const form = button.closest('form');

    // Only the technical no-submit button is put into the request here. This used to write the
    // button's name into triggered_button as well, which overwrote the reason for the reload that
    // had just been set - 'reloadTestForm' arrived as 'submitcattestoption' (issue #123).
    let hiddenButtonField = form.querySelector('[data-nosubmit-proxy-for="' + button.name + '"]');
    if (!hiddenButtonField) {
        hiddenButtonField = document.createElement('input');
        hiddenButtonField.type = 'hidden';
        hiddenButtonField.name = button.name;
        hiddenButtonField.setAttribute('data-nosubmit-proxy-for', button.name);
        form.appendChild(hiddenButtonField);
    }

    hiddenButtonField.value = button.value || '1';
}

/**
 * Copies all feedback settings from one scale to the selected subscales (issue #51: also into the
 * carriers of the scales not being edited).
 *
 * @param {HTMLElement} button
 */
async function copySettingsToSubscales(button) {
    setCopyButtonStatus(button, button.dataset.copyingText);

    const subscaleIds = (button.dataset.subscaleIds || '')
        .split(',')
        .map(id => id.trim())
        .filter(id => id.length > 0);
    let copied = 0;
    try {
        copied = await copyScaleSettings(button.closest('form'), button.dataset.sourceScaleId, subscaleIds);
    } catch (error) {
        setCopyButtonStatus(button, '');
        Notification.exception(error);
        return;
    }

    if (copied === 0) {
        setCopyButtonStatus(button, button.dataset.nothingCopiedText);
        return;
    }

    setCopyButtonStatus(button, button.dataset.copiedText.replace('{$a}', copied));
}

/**
 * Shows a short status message next to the copy button.
 *
 * @param {HTMLElement} button
 * @param {string} message
 */
function setCopyButtonStatus(button, message) {
    let statusNode = button.parentElement.querySelector('[data-copy-status-for="' + button.name + '"]');

    if (!statusNode) {
        statusNode = document.createElement('span');
        statusNode.className = 'ml-2 text-muted small';
        statusNode.setAttribute('data-copy-status-for', button.name);
        statusNode.setAttribute('role', 'status');
        statusNode.setAttribute('aria-live', 'polite');
        button.parentElement.appendChild(statusNode);
    }

    statusNode.textContent = message || '';
}
