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
    SELECTCOLOURPICKER: 'select[name^="wb_colourpicker_"]',
    COLOURPICKER: 'span.wb_colourpick',
    COLOUR: 'span.colourpickercircle',
    COLOURSELECTMENU: '.colourselectnotify'
};

/**
 * Add event listener to form.
 */
export const init = () => {

    const selectcolors = document.querySelectorAll(SELECTORS.SELECTCOLOURPICKER);

    selectcolors.forEach(selectcolor => {
        if (selectcolor.dataset.initialized) {
            return;
        }
        selectcolor.dataset.initialized = 'true';

        addClickListener(selectcolor);
    });
};

/**
 * Add Click Listener to element.
 *
 * @param {mixed} selectcolor
 */
function addClickListener(selectcolor) {
    const colours = [...selectcolor.querySelectorAll('option')].map(el => {

        // const colourSelectMenu = document.querySelector(SELECTORS.COLOURSELECTMENU);
        const colour = el.value;
        return {
            colour,
            colourname: el.textContent,
            selected: selectcolor.value == el.value,
            id: selectcolor.name
        };
    });

    // eslint-disable-next-line no-console
    console.log(colours, selectcolor.value);

    const colourobject = colours.filter(e => e.selected).pop();

    // eslint-disable-next-line no-console
    console.log(colourobject, 'colourobject');

    const data = {
        colours,
        colour: colourobject.colourname,
        id: selectcolor.name
    };

    // eslint-disable-next-line no-console
    console.log(data, 'data');

    Templates.renderForPromise('local_catquiz/colour_picker', data).then(({html}) => {

        //selectcolor.classList.add('hidden');

        selectcolor.insertAdjacentHTML('afterend', html);

        const colourpicker = document.querySelector('span[data-id=wb_colourpick_id_' + selectcolor.name + ']');
        // eslint-disable-next-line no-console
        console.log(colourpicker, 'colourpicker');

        // eslint-disable-next-line no-console
        console.log(selectcolor, html);

        const colours = colourpicker.querySelectorAll(SELECTORS.COLOUR);

        // eslint-disable-next-line no-console
        console.log(selectcolor, colourpicker, colours, html);

        colours.forEach(el => {
            el.addEventListener('click', e => {

                // eslint-disable-next-line no-console
                console.log(e.target.dataset.colour);

                colours.forEach(el => el.classList.remove('selected'));
                e.target.classList.add('selected');
                selectcolor.value = e.target.dataset.colour;

            });
        });

        return true;
      }).catch((e) => {
          // eslint-disable-next-line no-console
          console.log(e);
      });
}
