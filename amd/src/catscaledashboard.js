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

import {get_string as getString} from 'core/str';
import Ajax from 'core/ajax';
import DynamicForm from 'core_form/dynamicform';
import Notification from 'core/notification';

const SELECTORS = {
    FORMCONTAINER: '#select_context_form',
    CALCULATEBUTTON: '#model_button'
};
/**
 * Add event listener to buttons.
 */
export const init = () => {
    // Initialize the form - pass the container element and the form class name.
    const dynamicForm = new DynamicForm(document.querySelector(
        SELECTORS.FORMCONTAINER),
        'local_catquiz\\form\\contextselector'
    );

    const calculateButton = document.querySelector(SELECTORS.CALCULATEBUTTON);
    const contextId = parseInt(calculateButton.dataset.contextid);
    calculateButton.onclick = () => {
        updateParameters(contextId);
    };

    // If a user selects a context, redirect to a URL that includes the selected
    // context as `contextid` query parameter
    dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, (e) => {
        e.preventDefault();
        const response = e.detail;

        if (!response.contextid) {
            return;
        }

        let searchParams = new URLSearchParams(window.location.search);
        searchParams.set("contextid", response.contextid);
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

    const updateParameters = async(contextid) => {
        const urlParams = new URLSearchParams(window.location.search);
        const catscaleid = urlParams.get('id');

        // Fallback if the translation can not be loaded
        let errorMessage = 'Something went wrong';
        try {
            errorMessage = await getString('somethingwentwrong', 'local_catquiz');
        } catch (error) {
            // We already have a fallback message, nothing to do here.
        }
        Ajax.call([{
            methodname: 'local_catquiz_update_parameters',
            args: {contextid, catscaleid},
            done: async function(res) {
                if (res.success) {
                    disableButton();
                    // Fallback if the translation can not be loaded
                    let successMessage = 'Recalculation was scheduled';
                    try {
                        successMessage = await getString('recalculationscheduled', 'local_catquiz');
                    } catch (error) {
                        // We already have a fallback message, nothing to do here.
                    }

                    Notification.addNotification({
                        message: successMessage,
                        type: 'success'
                    });
                } else {
                    disableButton();
                    Notification.addNotification({
                        message: errorMessage,
                        type: 'danger'
                    });
                }
            },
            fail: () => {
                    disableButton();
                    Notification.addNotification({
                        message: errorMessage,
                        type: 'danger'
                    });
            },
        }]);
    };

    const disableButton = () => {
        document.querySelector(SELECTORS.CALCULATEBUTTON).setAttribute('disabled', true);
    };
};