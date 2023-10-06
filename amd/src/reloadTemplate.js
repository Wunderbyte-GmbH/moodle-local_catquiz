/* eslint-disable require-jsdoc */
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
import {showNotification} from 'local_catquiz/notifications';
import Templates from 'core/templates';

export const init = (selector) => {

    let elements = document.querySelectorAll(selector);

    elements.forEach(element => {
        let data = element.dataset;
        element.addEventListener('click', e => {
            e.stopPropagation();

            let jsondata = JSON.stringify(data);

            transmitAction(jsondata);
        });
    });
};

/**
 * Ajax function to handle action buttons.
 * @param {string} data
 */
export function transmitAction(data) {
Ajax.call([{
  methodname: "local_catquiz_reload_template",
  args: {
    'data': data,
  },
  done: function(response) {

    if (response.success == 1) {
      showNotification(response.message, "success");
      reloadTemplate(data, response);
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

/**
 * Reloads template defined in the data of the object that was clicked on.
 * @param {object} data
 * @param {string} response
 */
function reloadTemplate(data, response) {

// Parse the data of object that triggered the change.
const dataobject = JSON.parse(data);
const template = dataobject.templatelocation;
const templateid = "[data-templateid='" + dataobject.templatelocation + "']";

// The data of the response gives us the context for the template.
const responseobject = JSON.parse(response.data);
      // eslint-disable-next-line no-console
      console.log(template);
            // eslint-disable-next-line no-console
            console.log(responseobject);
                        // eslint-disable-next-line no-console
                        console.log(templateid);

Templates.renderForPromise(template, responseobject).then(({html, js}) => {

    Templates.replaceNode(templateid, html, js);
    return true;
  }).catch((e) => {
      // eslint-disable-next-line no-console
      console.log(e);
  });
}
}