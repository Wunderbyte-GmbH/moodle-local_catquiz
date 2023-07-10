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
    CONTEXTFORM: '#select_context_form',
    CHECKBOX: 'input.integrate-subscales-checkbox',
    SCALEFORM: '#select_scale_form',
};


/**
 * Initialize the form with event listener that update url params.
 */
export const init = () => {

    // Initialize the checkbox.
    const checkbox = document.querySelector(SELECTORS.CHECKBOX);
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

    // Context: Add event listener to select and set URL params.
    listenToSelect(SELECTORS.CONTEXTFORM, 'local_catquiz\\form\\contextselector', "contextid");
    listenToSelect(SELECTORS.SCALEFORM, 'local_catquiz\\form\\scaleselector', "scale");


};
/**
 * Set an eventlistener for a select.
 *  @param {string} id
 *  @param {string} location
 *  @param {string} paramname
 *
 */
function listenToSelect(id, location, paramname) {
        // Initialize the form - pass the container element and the form class name.
        const dynamicForm = new DynamicForm(document.querySelector(
            id),
            location
        );
        // If a user selects a context, redirect to a URL that includes the selected
        // context as `contextid` query parameter
        dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, (e) => {
            e.preventDefault();

            const response = e.detail;

            let searchParams = new URLSearchParams(window.location.search);
            if (typeof response === 'object' && response !== null) {
                searchParams.set(Object.keys(response)[0], Object.values(response)[0]);
            } else {
                if (!response.contextid) {
                    return;
                }
                searchParams.set(paramname, response.contextid);
            }
            window.location.search = searchParams.toString();
        });

        // If a user selects a cat context, submit the form without waiting for the
        // user to click the submit button
        dynamicForm.addEventListener('change', (e) => {
            e.preventDefault();

            // We have to wait a little bit so that the data are included in the submit
            // request
            setTimeout(() => {
                dynamicForm.submitFormAjax();
            }, 500);
        });

}
