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
    FORMCONTAINER: '#csv_import_form',
    BUTTON: 'input[type="submit"]',
};
/**
 * Add event listener to form.
 */
export const init = () => {


    const formContainer = document.querySelector(SELECTORS.FORMCONTAINER);

    // Initialize the form - pass the container element and the form class name.
    const dynamicForm = new DynamicForm(formContainer,
        'local_catquiz\\form\\csvimport'
    );
     // eslint-disable-next-line no-console
     console.log(dynamicForm);

    // If a user imports an element, trigger treatment of input.
    dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, (e) => {

        const response = e.detail;

                // eslint-disable-next-line no-console
                console.log(response);

        dynamicForm.container.innerHTML = '';
        dynamicForm.load({arg1: 'val1'});
    });

    dynamicForm.addEventListener(dynamicForm.events.FORM_CANCELLED, (e) => {
        e.preventDefault();
        dynamicForm.container.innerHTML = '';

        // eslint-disable-next-line no-console
        console.log(e);

    });

    // If a user selects a cat context, submit the form without waiting for the
    // user to click the submit button
    /*
    dynamicForm.addEventListener('change', (e) => {
        e.preventDefault();

        // We have to wait a little bit so that the data are included in the submit
        // request
        setTimeout(() => {
            dynamicForm.submitFormAjax();
        }, 500);
    });
    */

};