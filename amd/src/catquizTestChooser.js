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
 * JavaScript for mod_form to reload when a CAT model has been chosen.
 *
 * @module     mod_adaptivequiz/catquizTestChooser
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const selectors = {
    catTestChooser: '[data-on-change-action="reloadTestForm"]',
    catTestSubmit: '[data-action="submitCatTest"]'
};

/**
 * Initialise it all.
 */
export const init = () => {

    document.querySelector(selectors.catTestChooser).addEventListener('change', e => {

        // eslint-disable-next-line no-console
        console.log('change');

        const form = e.target.closest('form');
        const submitCatTest = form.querySelector(selectors.catTestSubmit);
        const fieldset = submitCatTest.closest('fieldset');

        const url = new URL(form.action);
        url.hash = fieldset.id;

        form.action = url.toString();
        submitCatTest.click();
    });
};
