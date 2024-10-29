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

import DynamicForm from 'core_form/dynamicform';

const SELECTORS = {
    FORMCONTAINER: '#lcq_model_override_form',
    NOEDITBUTTON: '[name="noedititemparams"]',
    MODELSTATUSSELECTS: '#lcq_model_override_form .custom-select[name^="override_"]',
    ACTIVEMODELSELECT: '[name="active_model"]',
    TEMP_FIELDS_INPUT: '[name="temporaryfields"]'
};

const disabledStates = ["0", "-5"];

/**
 * Updates the active_model select according to the state of the model status
 *
 * If the state of the model is changed to excluded or not yet calculated, it can not be set as active model.
 * If the state is changed to something else, the disabled attribute is removed.
 *
 * @param {HTMLSelectElement} model
 */
const syncSelectedState = (model) => {
        const selected = model.value;
        const selectorModel = model.id.match(/id_override_(.*)_select/)[1];
        let optionUpdateFun = (option) => option.removeAttribute('disabled');
        if (disabledStates.includes(selected)) {
            optionUpdateFun = (option) => option.setAttribute('disabled', 'disabled');
        }
        const activeModelSelect = document.querySelector(SELECTORS.ACTIVEMODELSELECT);
        activeModelSelect.options.forEach((o) => {
            if (o.value == selectorModel) {
                optionUpdateFun(o);
                return;
            }
        });
};

/**
 * Add event listener to the form
 */
export const init = () => {
    // Initialize the form - pass the container element and the form class name.
    const dynamicForm = new DynamicForm(document.querySelector(
        SELECTORS.FORMCONTAINER),
        'local_catquiz\\form\\item_model_override_selector'
    );

    const switchEditMode = (targetModeIsEditing) => {
        console.log(`Switching edit mode to: ${targetModeIsEditing}`);
        const searchParams = new URLSearchParams(window.location.search);
        dynamicForm.load({
            editing: targetModeIsEditing,
            testitemid: searchParams.get("id"),
            contextid: searchParams.get("contextid"),
            scaleid: searchParams.get("scaleid"),
            component: searchParams.get("component"),
            updateitem: true
        }).then(
            // Now that the model fields were added, we can add listeners to them.
            (result) => {
                const modelSelectors = document.querySelectorAll(SELECTORS.MODELSTATUSSELECTS);
                modelSelectors.forEach(model => {
                    syncSelectedState(model);
                    model.addEventListener('change', (e) => syncSelectedState(e.target));
            });
                return result;
            }
        ).catch(err => err);
    }

    const addItemParams = (e) => {
        const searchParams = new URLSearchParams(window.location.search);
        console.log('In add item params');
        console.log(e);
        // Construct the new input fields.
        const lastBreak = e.detail.parentElement.parentElement.previousElementSibling;

        const newFractionLabel = document.createElement('label');
        newFractionLabel.textContent = 'Fraction X';
        newFractionLabel.setAttribute('for', 'fraction_x');
        const newFractionInput = document.createElement('input');
        newFractionInput.className = 'form-control param-input';
        newFractionInput.id = 'fraction_x';

        const newDifficultyLabel = document.createElement('label');
        newDifficultyLabel.textContent = 'Difficulty X';
        newDifficultyLabel.setAttribute('for', 'difficulty_x');
        const newDifficultyInput = document.createElement('input');
        newDifficultyInput.className = 'form-control param-input';
        newDifficultyInput.id = 'difficulty_x';

        lastBreak.insertAdjacentElement('afterend', newDifficultyInput);
        lastBreak.insertAdjacentElement('afterend', newDifficultyLabel);
        lastBreak.insertAdjacentElement('afterend', newFractionInput);
        lastBreak.insertAdjacentElement('afterend', newFractionLabel);


        // Add the IDs of newly added fields to the tempFieldsInput, so that we
        // can collect them easily when the form is submitted.
        const tempFieldsInput = document.querySelector(SELECTORS.TEMP_FIELDS_INPUT);
        let tempids = JSON.parse(tempFieldsInput.value);
        tempids.push('difficulty_x')
        tempids.push('fraction_x')
        tempFieldsInput.value = JSON.stringify(tempids);
    }

    dynamicForm.addEventListener(dynamicForm.events.SUBMIT_BUTTON_PRESSED, (e) => {
        console.log('Submit button pressed');
        const tempFieldsInput = document.querySelector(SELECTORS.TEMP_FIELDS_INPUT);
        // TODO: Replace with working code.
        tempFieldsInput.value = JSON.stringify(
            {
                grm: {
                    difficulty_x: 1.2,
                    fraction_x: 3.4
                }
            }
        );
    });

    dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, (e) => {
        console.log('Form submitted');
        e.preventDefault();
        let formcontainer = document.querySelector(
            SELECTORS.FORMCONTAINER);
        const searchParams = new URLSearchParams(window.location.search);
        dynamicForm.load({
            editing: formcontainer.querySelector(SELECTORS.NOEDITBUTTON) ? false : true,
            testitemid: searchParams.get("id"),
            contextid: searchParams.get("contextid"),
            scaleid: searchParams.get("scaleid"),
            component: searchParams.get("component"),
            updateitem: true,
        });
    });

    dynamicForm.addEventListener(dynamicForm.events.NOSUBMIT_BUTTON_PRESSED, (e) => {
        let formcontainer = document.querySelector(
            SELECTORS.FORMCONTAINER);
        e.preventDefault();
        switch (e.detail.name) {
            case 'edititemparams':
                switchEditMode(true);
                break;
            case 'noedititemparams':
                switchEditMode(false);
                break;
            case 'override_grm[additemparams]':
                addItemParams(e);
                break;
            default:
                console.log(`Unknown no-submit action: ${e.detail.name}`)
        }
    });
};
