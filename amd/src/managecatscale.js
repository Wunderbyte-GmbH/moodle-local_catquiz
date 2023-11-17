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

/**
 * Add event listener to buttons.
 */
export const init = () => {

    let buttons = document.querySelectorAll('.manage-catscale');
    buttons.forEach(button => {
        button.addEventListener('click', e => {
            e.preventDefault();
            const element = e.target;

            if (element.dataset.action === "delete") {
                performDeletion(element);
            } else if (element.dataset.action === "view") {
                displayDetailView(element);
            } else {
                manageCatscale(element);
            }
        });
    });
};

/**
 *
 * @param {*} button
 */
function manageCatscale(button) {
    const parentelement = button.closest('.list-group-item');
    const action = button.dataset.action;
    let formclass = "local_catquiz\\form\\modal_manage_catscale";
    let formvalues = {
        id: parentelement.dataset.id ?? 0,
        description: parentelement.dataset.description ?? '',
        name: parentelement.dataset.name ?? '',
        parentid: parentelement.dataset.parentid ?? 0};

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
        modalConfig: {title: getString('managecatscale', 'local_catquiz')},
        // DOM element that should get the focus after the modal dialogue is closed:
        returnFocus: button,
    });

    // Listen to events if you want to execute something on form submit.
    // Event detail will contain everything the process() function returned:
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        window.console.log(e.detail);

        // Reload window after cancelling.
        window.location.reload();

    });

    // Show the form.
    modalForm.show();
}

/**
 *
 * @param {*} element
 */
export const performDeletion = async(element) => {

    const parentelement = element.closest('.list-group-item');
    const id = parentelement.dataset.id;
    Ajax.call([{
        methodname: 'local_catquiz_delete_catscale',
        args: {id: id},
        done: function(res) {

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

/**
 *
 * @param {*} element
 */
function displayDetailView(element) {

    let searchParams = new URLSearchParams(window.location.search);
    let scaleid = element.dataset.scaleid;
    let urlscaleid = searchParams.get('scaleid');
    searchParams.set('scaleid', scaleid);

    // If it's a new scale, we want to display on first click.
    // Otherwise we switch the value.
    let sdv = (searchParams.get('sdv') == 0 || searchParams.get('sdv') === null || urlscaleid != scaleid) ? 1 : 0;

    searchParams.set('sdv', sdv);
    window.location.search = searchParams.toString();
}