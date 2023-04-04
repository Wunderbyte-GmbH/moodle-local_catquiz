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
import Ajax from 'core/ajax';
import {showNotification} from 'local_catquiz/notifications';

const SELECTORS = {
    'MANAGECATCONTEXT' : '.manage-catcontext',
};


/**
 * Add event listener to buttons.
 */
export const init = () => {

    // eslint-disable-next-line no-console
    console.log('manage catcontext init');

    let buttons = document.querySelectorAll(SELECTORS.MANAGECATCONTEXT);
    buttons.forEach(button => {

        // eslint-disable-next-line no-console
        console.log(button);

        if (button.initialized) {
            return;
        }

        button.initialized = true;

        button.addEventListener('click', e => {
            e.preventDefault();
            const element = e.target;
            if (element.dataset.action === "delete") {
                performDeletion(element);
            } else {
                managecatcontext(element);
            }
        });
    });
};

/**
 *
 * @param {*} button
 */
function managecatcontext(button) {
    // const parentelement = button.closest('.list-group-item');
    const action = button.dataset.action;
    let formclass = "local_catquiz\\form\\edit_catcontext";
    let formvalues = {
        id: button.dataset.id ?? 0,
    };

    // eslint-disable-next-line no-console
    console.log(action, formvalues);
    switch (action) {
        case 'create':
            formvalues = {parentid: button.dataset.id ?? 0};
            break;
    }
    let modalForm = new ModalForm({
        // Name of the class where form is defined (must extend \core_form\dynamic_form):
        formClass: formclass,
        // Add as many arguments as you need, they will be passed to the form:
        args: formvalues,
        // Pass any configuration settings to the modal dialogue, for example, the title:
        modalConfig: {title: getString('managecatcontexts', 'local_catquiz')},
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
        console.log('createcatcontext: form submitted');
    });

    // Show the form.
    modalForm.show();
}

/**
 *
 * @param {*} element
 */
export const performDeletion = async(element) => {

    // eslint-disable-next-line no-console
    console.log('performDeletion');

    const parentelement = element.closest('.list-group-item');
    const id = parentelement.dataset.id;
    Ajax.call([{
        methodname: 'local_catquiz_delete_catcontext',
        args: {id: id}
        ,
        done: function(res) {
            // eslint-disable-next-line no-console
            console.log(res);

            if (res.success) {
                window.location.reload();
            } else {
                showNotification(res.message, 'danger');
            }
        },
        fail: ex => {
            // eslint-disable-next-line no-console
            console.log("ex:" + ex);
        },
    }]);
};
