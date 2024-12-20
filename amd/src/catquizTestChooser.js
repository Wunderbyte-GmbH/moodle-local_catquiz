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

const SELECTORS = {
    CATTESTCHOOSER: '[data-on-change-action]',
    CATTESTSUBMIT: '[data-action="submitCatTest"]',
    CATSCALESUBMIT: '[data-action="submitCatScale"]',
    CATSCALESUBMITCONTAINER: '[id="fitem_id_submitcatscaleoption"]',
    CATTESTCHECKBOXES: 'input[name^="catquiz_subscalecheckbox"]',
    REPORTSCALECHECKBOXES: 'input[id^="id_catquiz_scalereportcheckbox"]',
    NUMBEROFFEEDBACKSSUBMIT: '[data-action="submitNumberOfFeedbackOptions"]'
};

/**
 * Initialise it all.
 */
export const init = () => {

    const selectors = document.querySelectorAll(SELECTORS.CATTESTCHOOSER);
    const checkboxes = document.querySelectorAll(SELECTORS.CATTESTCHECKBOXES);
    const reportscalecheckboxes = document.querySelectorAll(SELECTORS.REPORTSCALECHECKBOXES);

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

            switch (e.target.dataset.onChangeAction) {
                case 'reloadTestForm':
                    document.getElementsByName('triggered_button')[0].value = 'reloadTestForm';
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
    if (!checkboxelements || checkboxelements.length == 0) {
        return;
    }

    // On the first run when the page is loaded set the status according to
    // saved fields and add event listeners.
    checkboxelements.forEach(selector => {
        setCardDisabledStatus(selector);
        selector.addEventListener('change', e => setCardDisabledStatus(e.target));
    });
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
        element
            .getElementsByTagName('input')
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
        element
            .getElementsByClassName('editor_atto_content')
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

    // eslint-disable-next-line no-console
    console.log(submitCatTest, 'submitCatTest');

    const url = new URL(form.action);
    url.hash = fieldset.id;

    form.action = url.toString();
    submitCatTest.click();
}
