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
import Notification from 'core/notification';

const SELECTORS = {
    FORMCONTAINER: '#lcq_select_context_form',
    CALCULATEBUTTON: '#model_button'
};
/**
 * Add event listener to buttons.
 */
export const init = () => {

    const calculateButton = document.querySelector(SELECTORS.CALCULATEBUTTON);
    if (!calculateButton) {
        return;
    }
    const contextId = parseInt(calculateButton.dataset.contextid);
    calculateButton.onclick = () => {
        updateParameters(contextId);
    };

    const updateParameters = async(contextid) => {
        const urlParams = new URLSearchParams(window.location.search);
        const catscaleid = urlParams.get('scaleid');

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
