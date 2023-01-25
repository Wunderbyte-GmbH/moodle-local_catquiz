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
 * @module     local_catquiz/actions
 * @copyright  2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


const SELECTORS = {
    CHECKBOX: 'input.testitem-checkbox',
};


export const initcheckbox = (id) => {

    // eslint-disable-next-line no-console
    console.log('checkboxinit', id);
};

export const initassignbutton = (catscaleid, containerselector) => {

    // eslint-disable-next-line no-console
    console.log('initassignbutton', catscaleid, containerselector);

    const button = document.querySelector(".assign-testitems-to-catscale");

    // eslint-disable-next-line no-console
    console.log('button init', button);

    button.addEventListener('click', e => {
        const checkboxes = document.querySelectorAll(SELECTORS.CHECKBOX);

        const checkedboxes = [];

        checkboxes.forEach(x => {
            if (x.checked === true) {
                checkedboxes.push(x);
            }
        });

        // eslint-disable-next-line no-console
        console.log('checkboxes', checkedboxes, e);
    });
};
