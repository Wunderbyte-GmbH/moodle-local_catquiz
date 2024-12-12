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
    CONTEXTFORM: "div[id='lcq_select_context_form']",
    CHECKBOXSELECTOR: 'input.integrate-subscales-checkbox',
    SCALEFORM: '#select_scale_form',
    SCALECONTAINER: "[id='catmanagerquestions-scaleselectors']", // Make sure to change in the code below.
    SELECTS: '[id*="select_scale_form_scaleid"]',
    CONTAINERCLASSSELECTOR: '.catscales-dashboard',
};


/**
 * Initialize the form with event listener that update url params.
 */
export const init = () => {

    const containers = document.querySelectorAll(SELECTORS.CONTAINERCLASSSELECTOR);
    containers.forEach(container => {
        initComponents(container);
    });
};
/**
 * Set an eventlistener for a select.
 *  @param {*} container
 *
 */
function initComponents(container) {
    // Initialize the checkbox.
    var checkbox = container.querySelector(SELECTORS.CHECKBOXSELECTOR);
    if (checkbox) {
        // eslint-disable-next-line no-unused-vars
        checkbox.addEventListener('click', e => {
        let searchParams = new URLSearchParams(window.location.search);
        if (checkbox.checked === true) {
            searchParams.set("usesubs", 1);
            window.location.search = searchParams.toString();
        } else {
            searchParams.set("usesubs", 0);
            window.location.search = searchParams.toString();
        }
        });
    }

    var contextcontainer = document.querySelectorAll(SELECTORS.CONTEXTFORM);
    contextcontainer.forEach(contextcontainer => {
        if (contextcontainer) {
            // Find the select element within each div.
            var contextselector = contextcontainer.querySelector('select');
            // Check if a select element was found.
            if (contextselector) {
                contextselector.addEventListener('change', function() {
                    let searchParams = new URLSearchParams(window.location.search);
                    searchParams.set('contextid', contextselector.value);
                    window.location.search = searchParams.toString();
                    });
            }
        }
    });

    // Attach listener to each scale select
    const selectcontainer = container.querySelector(SELECTORS.SCALECONTAINER);
    if (selectcontainer) {
        var selects = selectcontainer.querySelectorAll(SELECTORS.SELECTS);
        if (selects) {
            selects.forEach(select => {
                listenToSelect(select, 'local_catquiz\\form\\scaleselector', "scaleid");
            });
        }
    }
}

/**
 * Set an eventlistener for a select.
 *  @param {*} element
 *  @param {string} location
 *  @param {string} paramname
 *
 */
export function listenToSelect(element, location, paramname) {
        // Initialize the form - pass the container element and the form class name.
        const dynamicForm = new DynamicForm(element,
            location
        );

        // If a user selects a context, redirect to a URL that includes the selected
        // context as `contextid` query parameter
        dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, (e) => {
            if (!dynamicForm) {
                return;
            }

            const response = e.detail;

            let searchParams = new URLSearchParams(window.location.search);
            // If we have scaleselector set to default, we check if there is a parentscale to apply.
            if (paramname == "scaleid" && response[paramname] == "-1") {
                let scaleid = getvalueofparentscaleselector(element);
                searchParams.set(paramname, scaleid);
            } else {
                searchParams.set(paramname, response[paramname]);
            }
            searchParams.delete('contextid');
            searchParams.delete('id');
            window.location.search = searchParams.toString();

        });

        dynamicForm.addEventListener('change', (e) => {

            e.preventDefault();

            // We have to wait a little bit so that the data are included in the submit
            // request
            setTimeout(() => {
                if (dynamicForm) {
                    dynamicForm.submitFormAjax();
                }
            }, 100);
        });
}

/**
 * Check if there is a parentscaleselector,
 * read and return the value.
 * @param {*} element
 * @return {String} parentscaleid
 */
function getvalueofparentscaleselector(element) {

    var selectcontainer = element.closest(SELECTORS.SCALECONTAINER);
    var selects = selectcontainer.querySelectorAll(SELECTORS.SELECTS);
    let last;

    // Make sure to get the select.
    const select = element.closest(SELECTORS.SELECTS);

    // Keep deleting the last selects until the one that triggered the change is deleted.
    let keepdeletinglastnode = true;
    while (keepdeletinglastnode) {
        selects = selectcontainer.querySelectorAll(SELECTORS.SELECTS);
        if (selects.length > 1) {
            last = selects[selects.length - 1];
            if (last == select) {
                keepdeletinglastnode = false;
            }
            last.remove();
        } else {
            keepdeletinglastnode = false;
        }
    }
    selects = selectcontainer.querySelectorAll(SELECTORS.SELECTS);
    last = selects[selects.length - 1];
    // Fetch the value of the last selector.
    const selectedscaleid = last.querySelector('[name="scaleid"]').value;
    return selectedscaleid;
}
