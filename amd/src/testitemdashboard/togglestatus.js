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

/**
 * @module     local_catquiz/backbutton
 * @copyright  2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {showNotification} from 'local_catquiz/notifications';
import Ajax from 'core/ajax';

var SELECTORS = {
    EYEICON: '#status-activity-eye',
    TABCONTAINER: '#questions',
};
/**
 * Init
 * @param {*} testitemid
 * @param {*} scaleid
 * @param {*} contextid
 * @param {*} component
 */
export const init = (testitemid, scaleid, contextid, component) => {

    const container = document.querySelector(SELECTORS.TABCONTAINER);
    const eyeicon = container.querySelector(SELECTORS.EYEICON);

    if (!eyeicon) {
        return;
    }

    if (!eyeicon.dataset.initialized) {
        eyeicon.dataset.initialized = 'true';

        eyeicon.addEventListener('click', e => {
            e.stopPropagation();
            // Active status is 0;
            let newstatus = 0;
                        // eslint-disable-next-line no-console
                        console.log("eyeicon.className ", eyeicon.className);
                                                // eslint-disable-next-line no-console
                                                console.log("eyeicon ", eyeicon);
            if (eyeicon.className == 'fa fa-eye') {
                // If the status was active (eye not slashed) when toggled, set newstatus to inactive.
                newstatus = 1;
            }

            const data = {
                'testitemid': testitemid,
                'contextid': contextid,
                'scaleid': scaleid,
                'component': component,
                'newstatus': newstatus,
            };

            // eslint-disable-next-line no-console
            console.log("data ", data);

            transmitAction('local_catquiz_toggle_testitemstatus', JSON.stringify(data));
        });
    }
};

/**
 * Ajax function to handle action buttons.
 * @param {string} methodname
 * @param {string} datastring
 */
export function transmitAction(methodname, datastring) {
    Ajax.call([{
      methodname: "local_catquiz_execute_action",
      args: {
        'methodname': methodname,
        'data': datastring,
      },
      done: function(data) {

        if (data.success == 1) {
          showNotification(data.message, "success");
        } else {
          showNotification(data.message, "danger");
        }

      },
      fail: function(ex) {
        // eslint-disable-next-line no-console
        console.log("ex:" + ex);

        showNotification("Something went wrong", "danger");
      },
    }]);
}