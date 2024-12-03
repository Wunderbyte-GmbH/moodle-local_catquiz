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
    RECALCULATEBUTTON: '#recalculate_remote'
};

export const init = () => {
    const recalcButton = document.querySelector(SELECTORS.RECALCULATEBUTTON);
    if (!recalcButton) {
        return;
    }

    recalcButton.addEventListener('click', () => {
        recalculateParameters();
    });
};

const recalculateParameters = async() => {
    const urlParams = new URLSearchParams(window.location.search);
    const scaleid = urlParams.get('scaleid');
    const button = document.querySelector(SELECTORS.RECALCULATEBUTTON);

    try {
        const result = await Ajax.call([{
            methodname: 'local_catquiz_hub_enqueue_parameter_recalculation',
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
