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
import {get_string as getString} from 'core/str';

const SELECTORS = {
    FORMCONTAINER: '#lcq_model_override_form',
    NOEDITBUTTON: '[name="noedititemparams"]',
    MODELSTATUSSELECTS: '#lcq_model_override_form .custom-select[name^="override_"]',
    ACTIVEMODELSELECT: '[name="active_model"]',
    TEMP_FIELDS_INPUT: '[name="temporaryfields"]',
    DELETED_PARAMS_FIELD: '[name="deletedparams"]'
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
 * Add values of newly added parameters to a hidden field.
 *
 * When new parameters are added, we store their IDs.
 * This function is called just before we submit the form. It collects the values of those
 * newly added fields and returns them.
 *
 * @param {Array} addedParamIds Array of objects containing model and parameter IDs to be collected.
 * @return {Object} Object containing collected parameter values grouped by model.
 */
const collectNewParamData = (addedParamIds) => {
    let finalData = {};
    addedParamIds.forEach(newParam => {
        const model = newParam.model;
        finalData[model] = finalData[model] || [];
        const ids = newParam.ids;
        const params = {};
        ids.forEach(id => {
            const element = document.getElementById(id);
            const value = element.value;
            params[id] = value;
        });
        finalData[model].push(params);
    });
    return finalData;
};

/**
 * Deletes parameter values for the given model at the given index.
 *
 * If the params were newly added, they are removed from the hidden field that tracks new params.
 * Otherwise, we store information in a hidden field so that the parameters are deleted on the server
 * side once the form is submitted.
 *
 * @param {string} model The model identifier.
 * @param {number} index The index of the parameter set to delete.
 */
const deleteParameters = (model, index) => {
    // First, check if this is found in the tempinput data. If so, just remove it from there.
    const tempFieldsInput = document.querySelector(SELECTORS.TEMP_FIELDS_INPUT);
    let tempids = JSON.parse(tempFieldsInput.value);
    let filtered = tempids.filter((newparams) => {
        return newparams.model != model && newparams.index != index;
    });
    tempFieldsInput.value = JSON.stringify(filtered);
    // If the parameter was found, it means we do not have to delete it on the server side.
    // It was temporarily added but not yet saved. So we can return here.
    if (filtered.length != tempids.length) {
        return;
    }
    // If we are here, the parameter should be deleted on the server side.
    const deletedParamsField = document.querySelector(SELECTORS.DELETED_PARAMS_FIELD);
    let deletedParams = JSON.parse(deletedParamsField.value);
    const deleteParam = {
        model: model,
        index: index
    };
    deletedParams.push(deleteParam);
    deletedParamsField.value = JSON.stringify(deletedParams);
};

/**
 * This adds delete buttons to multiparam models.
 * While at it, it also restructures the HTML a bit by adding some wrapper elements to facilitate styling.
 */
function restructureFormElements() {
    // Find all .align-items-center containers.
    const containers = document.querySelectorAll('#lcq_model_override_form .param-group .align-items-center');

    containers.forEach(async container => {
        // Find the Add button and get model data.
        const addButton = container.querySelector('[data-action="additemparams"]');
        if (!addButton) {
            return; // Skip if no add button found.
        }

        const modelName = addButton.getAttribute('data-model') || '';
        // Parse field data into array of field names.
        const fielddata = addButton.dataset.fields.split(';');
        const modelFields = fielddata.map(fd => fd.split(':')[0]);

        if (modelFields.length === 0) {
            return; // Skip if no fields defined.
        }

        // Create new array to store restructured elements.
        const restructured = [];
        let pairCounter = 0;
        const elements = Array.from(container.children);

        // Process elements sequentially.
        for (let i = 0; i < elements.length; i++) {
            const currentElement = elements[i];

            // Preserve Add button.
            if (currentElement.querySelector('[data-action="additemparams"]')) {
                restructured.push(currentElement.cloneNode(true));
                continue;
            }

            // Check if this is the start of an input group (first field's label).
            if (currentElement.tagName === 'LABEL' &&
                elements[i + 1]?.tagName === 'INPUT' &&
                elements[i + 1].getAttribute('type')?.startsWith(`${modelFields[0]}`)) {

                // Create wrapper for the parameter group.
                const paramDiv = document.createElement('div');
                paramDiv.className = 'param-pair';

                let isValidGroup = true;
                let currentIndex = i;

                // Create wrappers for each field in modelFields.
                for (const field of modelFields) {
                    const label = elements[currentIndex];
                    const input = elements[currentIndex + 1];
                    const breakElement = elements[currentIndex + 2];

                    // Validate the field group.
                    if (!label?.tagName === 'LABEL' ||
                        !input?.tagName === 'INPUT' ||
                        !input?.getAttribute('type')?.startsWith(field)) {
                        isValidGroup = false;
                        break;
                    }

                    // Create and populate wrapper for this field.
                    const wrapper = document.createElement('div');
                    wrapper.className = 'input-wrapper';
                    wrapper.appendChild(label.cloneNode(true));
                    wrapper.appendChild(input.cloneNode(true));
                    paramDiv.appendChild(wrapper);

                    // Move index past current field group.
                    currentIndex += breakElement?.classList.contains('break') ? 3 : 2;
                }

                if (isValidGroup) {
                    // Create delete button.
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'btn btn-danger param-delete';
                    deleteBtn.textContent = 'Delete';
                    try {
                        deleteBtn.textContent = await getString('delete');
                    } catch (error) {
                        // We already have a fallback, nothing to do here.
                    }
                    deleteBtn.setAttribute('data-param-num', pairCounter);
                    deleteBtn.setAttribute('data-model', modelName);
                    deleteBtn.onclick = function() {
                        deleteParameters(this.dataset.model, this.dataset.paramNum);
                        paramDiv.remove();
                    };

                    paramDiv.appendChild(deleteBtn);
                    restructured.push(paramDiv);

                    // Skip processed elements.
                    i = currentIndex - 1;
                    pairCounter++;
                }
            } else if (currentElement.classList.contains('break')) {
                // Preserve break elements.
                restructured.push(currentElement.cloneNode(true));
            }
        }

        // Clear and repopulate the container.
        container.innerHTML = '';
        restructured.forEach(element => container.appendChild(element));
    });
}

const updateModelDisabledStates = (element) => {
    const model = element.id.match(/id_override_(.*)_select/)[1];
    const disabled = element.value == 1;
    // Find the corresponding input fields
    const inputElements = document.querySelectorAll(`input[name^="override_${model}["]`);
    const deleteButtons = document.querySelectorAll(`button[data-model="${model}"]`);
    const addButton = document.querySelector(`input[value="Add"][data-model="${model}"]`);
    const toUpdate = [...inputElements, ...deleteButtons, addButton];
    toUpdate.forEach(e => {
        if (disabled) {
            e.setAttribute('disabled', 'disabled');
        } else {
            e.removeAttribute('disabled');
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
                    model.addEventListener('change', e => updateModelDisabledStates(e.target));
            });
                // Add delete buttons etc.
                restructureFormElements();
                return result;
            }
        ).catch(err => err);
    };

    const addItemParams = (e) => {
        // Construct the new input fields.
        const lastBreak = e.detail.parentElement.parentElement.previousElementSibling;
        const paramGroup = e.detail.closest('.param-group');
        // Get the current highest number from existing fraction/difficulty inputs
        const existingInputs = paramGroup.querySelectorAll('input[type^="fraction_"], input[type^="difficulty_"]');
        const currentMax = Math.max(...Array.from(existingInputs)
            .map(input => parseInt(input.getAttribute('type').split('_')[1] || '0'))
        );
        const newNumber = currentMax + 1;
        const pairDiv = document.createElement('div');
        pairDiv.className = 'param-pair';

        // The fielddata describe the fields to add: "internalname1:Translated;internalname2:Translated".
        // For example: "difficulty:Schwierigkeit;fraction:Fraction".
        const fielddata = e.detail.dataset.fields.split(';');
        let newIds = [];
        fielddata.forEach(field => {
            const fieldarr = field.split(':');
            const internalName = fieldarr[0];
            const label = fieldarr[1];

            const newLabel = document.createElement('label');
            newLabel.textContent = `${label} ${newNumber}`;
            newLabel.setAttribute('for', `${internalName}_${newNumber}`);

            const newInput = document.createElement('input');
            newInput.className = 'form-control param-input';
            newInput.id = `${internalName}_${newNumber}`;
            newInput.setAttribute('type', `${internalName}_${newNumber}`);

            // Create wrapper.
            const wrapper = document.createElement('div');
            wrapper.className = 'input-wrapper';
            wrapper.appendChild(newLabel);
            wrapper.appendChild(newInput);

            pairDiv.appendChild(wrapper);
            newIds.push(newInput.id);
        });

        // Create delete button with data attributes
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'btn btn-danger param-delete';
        deleteBtn.textContent = 'Delete';
        deleteBtn.setAttribute('data-param-num', currentMax);
        deleteBtn.setAttribute('data-param-model', e.detail.dataset.model);
        deleteBtn.onclick = function() {
            const model = this.dataset.model;
            const paramNum = this.dataset.paramNum;

            deleteParameters(model, paramNum);
            // Remove the input elements.
            pairDiv.remove();
        };

        // Assemble the pair
        pairDiv.appendChild(deleteBtn);

        lastBreak.insertAdjacentElement('afterend', pairDiv);

        const newBreak = document.createElement('span');
        newBreak.className = "break new-break";
        pairDiv.insertAdjacentElement('afterend', newBreak);

        // Add the IDs of newly added fields to the tempFieldsInput, so that we
        // can collect them easily when the form is submitted.
        const tempFieldsInput = document.querySelector(SELECTORS.TEMP_FIELDS_INPUT);
        let tempids = JSON.parse(tempFieldsInput.value);
        const tempData = {
            model: e.detail.dataset.model,
            ids: newIds,
            index: currentMax, // This is 0-based, so lower than newNumber.
        };
        tempids.push(tempData);
        tempFieldsInput.value = JSON.stringify(tempids);
    };

    dynamicForm.addEventListener(dynamicForm.events.SUBMIT_BUTTON_PRESSED, () => {
        const tempFieldsInput = document.querySelector(SELECTORS.TEMP_FIELDS_INPUT);
        const addedParamIds = JSON.parse(tempFieldsInput.value);
        const newParamData = collectNewParamData(addedParamIds);
        tempFieldsInput.value = JSON.stringify(newParamData);
    });

    dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, (e) => {
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
        }).then(result => {
            return result;
        }).catch(err => err);
    });

    dynamicForm.addEventListener(dynamicForm.events.NOSUBMIT_BUTTON_PRESSED, (e) => {
        e.preventDefault();
        const action = e.detail.dataset.action;
        const targetModeIsEditing = e.detail.name == 'edititemparams';
        switch (action) {
            case 'edititemparams':
                switchEditMode(targetModeIsEditing);
                break;
            case 'additemparams':
                addItemParams(e);
                break;
            default:
                // eslint-disable-next-line no-console
                console.error(`Unknown no-submit action: ${action}`);
        }

    });
};
