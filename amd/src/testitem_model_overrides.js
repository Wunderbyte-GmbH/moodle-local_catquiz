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

const collectNewParamData = (addedParamIds) => {
    console.log("Getting field values for added parameters");
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
        finalData[model].push(params)
    });
    return finalData;
}

function restructureFormElements() {
    // Find all .align-items-center containers
    const containers = document.querySelectorAll('#lcq_model_override_form .param-group .align-items-center');
    
    containers.forEach(container => {
        // Check if this container has a fraction input
        const fractionInput = container.querySelector('input[type^="fraction_"]');
        if (!fractionInput) return; // Skip if no fraction input found
        
        // Find the model name from the Add button
        const addButton = container.querySelector('[data-action="additemparams"]');
        const modelName = addButton?.getAttribute('data-model') || '';
        
        const elements = Array.from(container.children);
        
        // Create new array to store restructured elements
        const restructured = [];
        // Keep track of how many pairs we've processed for data-param-num
        let pairCounter = 0;
        
        // Process elements sequentially
        for (let i = 0; i < elements.length; i++) {
            const currentElement = elements[i];
            
            // Check if this is the Add button container
            if (currentElement.querySelector('[data-action="additemparams"]')) {
                restructured.push(currentElement.cloneNode(true));
                continue;
            }
            
            // Check if this is the start of a fraction input group
            if (currentElement.tagName === 'LABEL' && 
                elements[i + 1]?.tagName === 'INPUT' && 
                elements[i + 1].getAttribute('type')?.startsWith('fraction_')) {
                
                // Find the corresponding difficulty elements
                const nextLabel = elements[i + 3];
                const nextInput = elements[i + 4];
                
                if (nextInput?.getAttribute('type')?.startsWith('difficulty_')) {
                    // Create pair wrapper
                    const pairDiv = document.createElement('div');
                    pairDiv.className = 'param-pair';
                    
                    // Create fraction wrapper
                    const wrapper1 = document.createElement('div');
                    wrapper1.className = 'input-wrapper';
                    wrapper1.appendChild(currentElement.cloneNode(true));
                    wrapper1.appendChild(elements[i + 1].cloneNode(true));
                    
                    // Create difficulty wrapper
                    const wrapper2 = document.createElement('div');
                    wrapper2.className = 'input-wrapper';
                    wrapper2.appendChild(nextLabel.cloneNode(true));
                    wrapper2.appendChild(nextInput.cloneNode(true));
                    
                    // Create delete button with data attributes
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'btn btn-danger param-delete';
                    deleteBtn.textContent = 'Delete';
                    deleteBtn.setAttribute('data-param-num', pairCounter);
                    deleteBtn.setAttribute('data-param-model', modelName);
                    deleteBtn.onclick = function() {
                        // Add your delete logic here
                        pairDiv.remove();
                    };
                    
                    // Assemble the pair
                    pairDiv.appendChild(wrapper1);
                    pairDiv.appendChild(wrapper2);
                    pairDiv.appendChild(deleteBtn);
                    
                    restructured.push(pairDiv);
                    
                    // Skip the elements we just processed
                    i += 4;
                    // Increment pair counter
                    pairCounter++;
                }
            } else if (currentElement.classList.contains('break')) {
                // Keep break elements
                restructured.push(currentElement.cloneNode(true));
            }
        }
        
        // Clear and repopulate the container
        container.innerHTML = '';
        restructured.forEach(element => container.appendChild(element));
    });
}

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
                // Add delete buttons etc.
                restructureFormElements();
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
        const paramGroup = e.detail.closest('.param-group');
        // Get the current highest number from existing fraction/difficulty inputs
        const existingInputs = paramGroup.querySelectorAll('input[type^="fraction_"], input[type^="difficulty_"]');
        const currentMax = Math.max(...Array.from(existingInputs)
            .map(input => parseInt(input.getAttribute('type').split('_')[1] || '0'))
        );
        const newNumber = currentMax + 1;

        const newFractionLabel = document.createElement('label');
        newFractionLabel.textContent = `Fraction ${newNumber}`;
        newFractionLabel.setAttribute('for', `fraction_${newNumber}`);
        const newFractionInput = document.createElement('input');
        newFractionInput.className = 'form-control param-input';
        newFractionInput.id = `fraction_${newNumber}`;
        newFractionInput.setAttribute('type', `fraction_${newNumber}`);

        const newDifficultyLabel = document.createElement('label');
        newDifficultyLabel.textContent = `Difficulty ${newNumber}`;
        newDifficultyLabel.setAttribute('for', `difficulty_${newNumber}`);
        const newDifficultyInput = document.createElement('input');
        newDifficultyInput.className = 'form-control param-input';
        newDifficultyInput.id = `difficulty_${newNumber}`;
        newDifficultyInput.setAttribute('type', `difficulty_${newNumber}`);

        lastBreak.insertAdjacentElement('afterend', newDifficultyInput);
        lastBreak.insertAdjacentElement('afterend', newDifficultyLabel);
        lastBreak.insertAdjacentElement('afterend', newFractionInput);
        lastBreak.insertAdjacentElement('afterend', newFractionLabel);


        // Add the IDs of newly added fields to the tempFieldsInput, so that we
        // can collect them easily when the form is submitted.
        const tempFieldsInput = document.querySelector(SELECTORS.TEMP_FIELDS_INPUT);
        let tempids = JSON.parse(tempFieldsInput.value);
        const tempData = {
            model: e.detail.dataset.model,
            ids: [newDifficultyInput.getAttribute('id'), newFractionInput.getAttribute('id')]
        }
        tempids.push(tempData);
        tempFieldsInput.value = JSON.stringify(tempids);
    }

    dynamicForm.addEventListener(dynamicForm.events.SUBMIT_BUTTON_PRESSED, (e) => {
        console.log('Submit button pressed');
        const tempFieldsInput = document.querySelector(SELECTORS.TEMP_FIELDS_INPUT);
        const addedParamIds = JSON.parse(tempFieldsInput.value);
        console.log(addedParamIds);
        const newParamData = collectNewParamData(addedParamIds);
        // TODO: Replace with working code.
        tempFieldsInput.value = JSON.stringify(newParamData);
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
