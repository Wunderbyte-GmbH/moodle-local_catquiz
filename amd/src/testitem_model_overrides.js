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

    dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, (e) => {

        // eslint-disable-next-line no-console
        console.log("form submitted");
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
        // eslint-disable-next-line
        //window.location.reload();
    });

    dynamicForm.addEventListener(dynamicForm.events.NOSUBMIT_BUTTON_PRESSED, (e) => {

        let formcontainer = document.querySelector(
            SELECTORS.FORMCONTAINER);
        e.preventDefault();
        const searchParams = new URLSearchParams(window.location.search);

        dynamicForm.load({
            editing: formcontainer.querySelector(SELECTORS.NOEDITBUTTON) ? false : true,
            testitemid: searchParams.get("id"),
            contextid: searchParams.get("contextid"),
            scaleid: searchParams.get("scaleid"),
            component: searchParams.get("component"),
        });
    });

};