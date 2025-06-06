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

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const SELECTORS = {
    SUBMITRESPONSESBUTTON: '#submit_responses_remote'
};

export const init = () => {
    const button = document.querySelector(SELECTORS.SUBMITRESPONSESBUTTON);
    if (!button) {
        return;
    }

    button.addEventListener('click', () => {
        submitResponses();
    });
};

const submitResponses = async() => {
    const urlParams = new URLSearchParams(window.location.search);
    const scaleid = urlParams.get('scaleid');
    const button = document.querySelector(SELECTORS.SUBMITRESPONSESBUTTON);

    try {
        const result = await Ajax.call([{
            methodname: 'local_catquiz_node_submit_responses',
            args: {
                scaleid: scaleid
            }
        }])[0];

        if (!result.success) {
            throw new Error(result.message);
        }

        button.setAttribute('disabled', true);
        Notification.addNotification({
            message: result.message,
            type: 'success'
        });

    } catch (error) {
        Notification.exception(error);
    }
};

