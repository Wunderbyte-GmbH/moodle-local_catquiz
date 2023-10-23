/* eslint-disable no-case-declarations */
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
 * @module     local_catquiz/colourpicker
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';

const SELECTORS = {
    SELECTCOLOURPICKER: 'select[name="colourpicker"]',
    COLOURPICKER: 'span.catquiz_colourpick',
    COLOUR: 'span.colourpickercircle',
    COLOURSELECTMENU: '.colourselectnotify'
};

/**
 * Add event listener to form.
 */
export const init = () => {

    const selectcolors = document.querySelector(SELECTORS.SELECTCOLOURPICKER);

    const colours = [...selectcolors.querySelectorAll('option')].map(el => {

        // const colourSelectMenu = document.querySelector(SELECTORS.COLOURSELECTMENU);
        const colour = el.value;
        return {
            colour,
            colourname: el.textContent,
            selected: selectcolors.value == el.value,
            id: colour.replace("#", "")
        };
    });

    // eslint-disable-next-line no-console
    console.log(colours, selectcolors.value);


    Templates.renderForPromise('local_catquiz/colour_picker', {colours}).then(({html}) => {


        selectcolors.classList.add('hidden');

        selectcolors.insertAdjacentHTML('afterend', html);

        const colourpicker = document.querySelector(SELECTORS.COLOURPICKER);

        // eslint-disable-next-line no-console
        console.log(selectcolors, html);

        const colours = colourpicker.querySelectorAll(SELECTORS.COLOUR);

        // eslint-disable-next-line no-console
        console.log(selectcolors, colourpicker, colours, html);

        colours.forEach(el => {
            el.addEventListener('click', e => {

                // eslint-disable-next-line no-console
                console.log(e.target.dataset.colour);

                colours.forEach(el => el.classList.remove('selected'));
                e.target.classList.add('selected');
                selectcolors.value = e.target.dataset.colour;

            });
        });

        return true;
      }).catch((e) => {
          // eslint-disable-next-line no-console
          console.log(e);
      });
};