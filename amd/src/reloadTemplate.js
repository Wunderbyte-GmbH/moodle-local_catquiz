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

            // Issue #66: named, typed fields instead of the whole dataset as JSON.
            // Sending everything meant the server received whatever happened to be on
            // the element - it could not declare its parameters, and so could not
            // validate them either.
            transmitAction({
                renderer: data.renderer,
                action: data.admethodname || '',
                actionparams: data.adparams || '',
                testitemid: parseInt(data.testitemid || 0, 10),
                contextid: parseInt(data.contextid || 0, 10),
                catscaleid: parseInt(data.catscaleid || 0, 10),
                component: data.component || 'question',
            }, data.templatelocation);
        });
    });
};

/**
 * Ajax function to handle action buttons.
 * @param {object} args Server parameters, exactly the ones the endpoint declares.
 * @param {string} templatelocation Template to redraw; never sent to the server.
 */
export function transmitAction(args, templatelocation) {
Ajax.call([{
  methodname: "local_catquiz_reload_template",
  args: args,
  done: function(response) {

    if (response.success == 1) {
      showNotification(response.message, "success");
      reloadTemplate(templatelocation, response);
    } else {
      showNotification(response.message, "danger");
    }
  },
  fail: function(ex) {
    // eslint-disable-next-line no-console
    console.log("ex:" + ex);

    showNotification("Something went wrong", "danger");
  },
}]);

/**
 * Reloads the template that belongs to the clicked element.
 * @param {string} templatelocation
 * @param {object} response
 */
function reloadTemplate(templatelocation, response) {

// Issue #66: the template to redraw is passed directly. It used to be parsed back
// out of the JSON payload that also carried the server parameters - two unrelated
// concerns travelling in one string.
const template = templatelocation;
const templateid = "[data-templateid='" + templatelocation + "']";

// The data of the response gives us the context for the template.
const responseobject = JSON.parse(response.data);

Templates.renderForPromise(template, responseobject).then(({html, js}) => {

    Templates.replaceNode(templateid, html, js);
    return true;
  }).catch((e) => {
      // eslint-disable-next-line no-console
      console.log(e);
  });
}
}