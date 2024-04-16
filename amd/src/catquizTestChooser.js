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
    NUMBEROFFEEDBACKSSUBMIT: '[data-action="submitNumberOfFeedbackOptions"]'
};

/**
 * Initialise it all.
 */
export const init = () => {

    const selectors = document.querySelectorAll(SELECTORS.CATTESTCHOOSER);
    const checkboxes = document.querySelectorAll(SELECTORS.CATTESTCHECKBOXES);

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

};

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