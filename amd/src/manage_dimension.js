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
 * @package    local_shopping_cart
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import {call as fetchMany} from 'core/ajax';

/**
 * Add event listener to buttons.
 */
export const init = () => {
    let buttons = document.querySelectorAll('.manage-dimension');
    buttons.forEach(button => {
        button.addEventListener('click', e => {
            e.preventDefault();
            const element = e.target;
            if (element.dataset.action === "delete") {
                performDeletion(element);
            } else {
                manageDimension(element);
            }
        });
    });
};

/**
 *
 * @param {*} button
 */
function manageDimension(button) {
    const parentelement = button.closest('.list-group-item');
    const action = button.dataset.action;
    let formclass = "local_catquiz\\form\\modal_manage_dimension";
    let formvalues = { id: parentelement.dataset.id, description: parentelement.dataset.description,
        name: parentelement.dataset.name, parentid: parentelement.dataset.parentid };
    // eslint-disable-next-line no-console
    console.log(formvalues);
    switch (action) {
        case 'create':
            formvalues = {parentid: parentelement.dataset.id};
            break;
    }
    let modalForm = new ModalForm({
        // Name of the class where form is defined (must extend \core_form\dynamic_form):
        formClass: formclass,
        // Add as many arguments as you need, they will be passed to the form:
        args: formvalues,
        // Pass any configuration settings to the modal dialogue, for example, the title:
        modalConfig: {title: getString('managedimension', 'local_catquiz')},
        // DOM element that should get the focus after the modal dialogue is closed:
        returnFocus: button,
    });

    // Listen to events if you want to execute something on form submit.
    // Event detail will contain everything the process() function returned:
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        window.console.log(e.detail);

        // Reload window after cancelling.
        window.location.reload();

        // eslint-disable-next-line no-console
        console.log('createDimension: form submitted');
    });

    // Show the form.
    modalForm.show();
}

const deleteDimension = (id) => ({
    methodname: 'local_catquiz_delete_dimension',
    args: { id: id },
});

export const performDeletion = async(element) => {
    const parentelement = element.closest('.list-group-item');
    const id = parentelement.dataset.id;
    const response = fetchMany([
        deleteDimension(id),
    ]);
    window.console.log(response);
    // Reload window after deleting.
    window.location.reload();
};
